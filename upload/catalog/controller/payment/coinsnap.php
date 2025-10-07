<?php
namespace Opencart\Catalog\Controller\Extension\Coinsnap\Payment;

if(!defined( 'COINSNAP_OPENCART_REFERRAL_CODE')) { define( 'COINSNAP_OPENCART_REFERRAL_CODE', 'D19823' ); }
if(!defined('COINSNAP_SERVER_URL')){ define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}

require_once(DIR_EXTENSION.'coinsnap/library/loader.php');
use Coinsnap\Client\Webhook;

class Coinsnap extends \Opencart\System\Engine\Controller	{	
	
    public function index(){
        $this->load->model('checkout/order');
        $this->language->load('extension/coinsnap/payment/coinsnap');
        $data['button_confirm'] = $this->language->get('button_confirm');		
        $action_url = $this->url->link('extension/coinsnap/payment/coinsnap.doPayment');
	$data['action'] = $action_url;
        return $this->load->view('extension/coinsnap/payment/coinsnap', $data);
    }

    public function webhook(){

        $this->language->load('extension/coinsnap/payment/coinsnap');
		
	// First check if we have any input
        $rawPostData = file_get_contents('php://input');
        
        if (!$rawPostData) {
            http_response_code(400);
            die('No raw post data received');
        } else {
            $this->log->write('Coinsnap Webhook Payload: '.$rawPostData);
        }
        
        // Get headers and check for signature
        $headers = getallheaders();
        $signature = null;
        $payloadKey = null;
        $_provider = ($this -> getProvider() === 'btcpay') ? 'btcpay' : 'coinsnap';
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'x-coinsnap-sig' || strtolower($key) === 'btcpay-sig') {
                $signature = $value;
                $payloadKey = strtolower($key);
            }
        }

        // Handle missing or invalid signature
        if (!isset($signature)) {
            http_response_code(401);
            die('Authentication required');
        }

        // Validate the signature
        $webhook = json_decode($this->config->get('payment_coinsnap_webhook'),true);
        if (!Webhook::isIncomingWebhookRequestValid($rawPostData, $signature, $webhook['secret'])) {
            http_response_code(401);
            die('Invalid authentication signature for '.$payloadKey);
        }
        
        try {

            // Parse the JSON payload
            $postData = json_decode($rawPostData, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($postData->invoiceId)) {
                http_response_code(400);
                die('No Coinsnap invoiceId provided');
            }

            if (strpos($postData->invoiceId, 'test_') !== false) {
                http_response_code(200);
                die('Successful webhook test');
            }

            $invoice_id = $postData->invoiceId;

            try {
                $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
                $csinvoice = $client->getInvoice($this->getStoreId(), $invoice_id);
                $status = $csinvoice->getData()['status'] ;
                $order_id = ($_provider === 'btcpay') ? $csinvoice->getData()['metadata']['orderId'] : $csinvoice->getData()['orderId'];
                $this->log->write('Coinsnap Webhook Payload Order Id: '.$order_id.', Status: '.$status);

                $order_status = $this->config->get('payment_coinsnap_new_status');
                
		switch ($status) {
                    case 'Expired':
                    case 'InvoiceExpired':
                        $order_status = $this->config->get('payment_coinsnap_expired_status');
                        break;

                    case 'Processing':
                    case 'InvoiceProcessing':
                        $order_status = $this->config->get('payment_coinsnap_processing_status');
                        break;

                    case 'Settled':
                    case 'InvoiceSettled':
                        $order_status = $this->config->get('payment_coinsnap_settled_status');
                        break;
                    default: break;
                }

                $this->update_payment($order_id, $invoice_id, $order_status);
                echo "OK";
                exit;
            } catch (JsonException $e) {
                $this->log->write('Coinsnap Webhook Payload Error: '.$e->getMessage());
                http_response_code(400);
                die('Invalid JSON payload');
            }

        } catch (\Throwable $e) {
            http_response_code(500);
            die('Internal server error');
        }
        
    }
    
    public function getWebhookUrl(){
        return $webhook_url = HTTP_CATALOG . 'index.php?route=extension/coinsnap/payment/coinsnap.webhook';
    }
    
    public function getProvider() {
        return ($this->config->get('payment_coinsnap_provider') === 'btcpay') ? 'btcpay' : 'coinsnap';
    }        
            
    public function getApiUrl() {
        return ($this->getProvider() === 'btcpay')? $this->config->get('payment_coinsnap_btcpay_server_url') : 'https://app.coinsnap.io';
    }	

    public function getStoreId() {
        return ($this->getProvider() === 'btcpay')? $this->config->get('payment_coinsnap_btcpay_store_id') : $this->config->get('payment_coinsnap_store_id');
    }	
    
    public function getApiKey() {
        return ($this->getProvider() === 'btcpay')? $this->config->get('payment_coinsnap_btcpay_api_key') : $this->config->get('payment_coinsnap_api_key');
    }
	
    public function clearCart(){
        if (isset($this->session->data['order_id'])) {
            $this->cart->clear();
        }
    }

    public function sessionClear(){
        if (isset($this->session->data['order_id'])) {
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);
        }
    }
    
    public function checkAmount($amount, $currency){
        
        $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
        $checkInvoice = [];

        try {
            if ($this->getProvider() === 'btcpay') {
                try {
                    $storePaymentMethods = $store->getStorePaymentMethods($this->getStoreId());
                    
                    $this->log->write('Store Payment Methods: '.print_r($storePaymentMethods,true));
                    
                    if ($storePaymentMethods['code'] === 200 && !isset($storePaymentMethods['result']['error'])){
                        if (!$storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                            $errorMessage = 'No payment method is configured on BTCPay server';
                            $checkInvoice = array('result' => false,'error' => $errorMessage);
                        }
                    }
                    else {
                        $errorMessage = 'Error store loading. Wrong or empty Store ID';
                        $checkInvoice = array('result' => false,'error' => $errorMessage);
                    }

                    if( isset($storePaymentMethods['result']['error']) ){
                        $errorMessage = 'Error data handling ('.$storePaymentMethods['result']['error'].')';
                        $checkInvoice = array('result' => false,'error' => $errorMessage);
                    }
                    elseif ($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                        $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency), 'bitcoin');
                    } elseif ($storePaymentMethods['result']['lightning']) {
                        $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency), 'lightning');
                    }
                } catch (\Throwable $e) {
                    $errorMessage = 'API connection is not established';
                    $checkInvoice = array('result' => false,'error' => $errorMessage);
                }
            } else {
                $checkInvoice = $client->checkPaymentData((float)$amount, strtoupper($currency));
            }
        } catch (\Throwable $e) {
            $errorMessage = 'API connection is not established';
            $checkInvoice = array('result' => false,'error' => $errorMessage);
        }
        return $checkInvoice;
    }    

    public function doPayment() {
		
        $this->language->load('extension/coinsnap/payment/coinsnap');
        $this->load->model('setting/setting');
        $this->load->model('checkout/order');
		
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);										
		
        $amount = round($order_info['total'], 2);
        $buyerEmail = $order_info['email'];
	$buyerName =  $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
	$currency = $order_info['currency_code'];
                
        $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $checkInvoice = $this->checkAmount($amount, strtoupper($currency));

        if ($checkInvoice['result'] === true) {
            
            $redirectUrl = (!empty($this->config->get('payment_coinsnap_returnurl')))? $this->config->get('payment_coinsnap_returnurl') : HTTP_SERVER.'index.php?route=checkout/success';
            $metadata = [];
            $metadata['orderNumber'] = $order_id;
            $metadata['customerName'] = $buyerName;

            if ($this->getProvider() === 'btcpay') {
                $metadata['orderId'] = $order_id;
            }

            $redirectAutomatically = ($this->config->get('payment_coinsnap_autoredirect') > 0) ? true : false;
            $walletMessage = '';

            // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
            if (($currency === 'SATS' || $currency === 'RUB') && $this->config->get('payment_coinsnap_provider') === 'btcpay') {
                $currency = 'BTC';
                $rate = 1/$checkInvoice['rate'];
                $amountBTC = bcdiv(strval($amount), strval($rate), 8);
                $amount = (float)$amountBTC;
            }
        
            $camount = ($currency === 'BTC')? \Coinsnap\Util\PreciseNumber::parseFloat($amount,8) : \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
            
            $invoice = $client->createInvoice(
                $this->getStoreId(),  
                $currency,
		$camount,
		$order_id,
		$buyerEmail,
		$buyerName, 
		$redirectUrl,
		COINSNAP_OPENCART_REFERRAL_CODE,     
		$metadata,
                $redirectAutomatically,
                $walletMessage
            );
		
            $payurl = $invoice->getData()['checkoutLink'] ;		
		
            if ($payurl) {	
                $comment = 'New';		
                $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_coinsnap_new_status'), $comment);
                $this->clearCart();
                $this->sessionClear();
                $this->response->redirect($payurl);			
            } else {			
                $errorMessage = 'Invoice request error';
                $this->log->write('Invoice request error: '.$e->getMessage());
                echo $errorMessage;
                exit;
            }
        }
        else {

            if ($checkInvoice['error'] === 'currencyError') {
                $errorMessage = 'Currency '.strtoupper($currency).' is not supported by Coinsnap';
            } elseif ($checkInvoice['error'] === 'amountError') {
                $errorMessage = 'Invoice amount cannot be less than '.$checkInvoice['min_value'].' '.strtoupper($currency);
            } else {
                $errorMessage = $checkInvoice['error'];
            }
            $this->log->write('Invoice request error: '.$errorMessage);
            echo $errorMessage;
            exit;
        }
    }

    public function update_payment($order_id, $invoice_id, $order_status){				
        $comment = 'Coinsnap Invoice ID: '.$invoice_id;
        $this->load->model('checkout/order');
        $this->model_checkout_order->addHistory($order_id, $order_status, $comment);
    }
}
?>