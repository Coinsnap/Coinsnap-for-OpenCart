<?php
namespace Opencart\Catalog\Controller\Extension\Coinsnap\Payment;
define( 'COINSNAP_REFERRAL_CODE', 'D19823' );
require_once(DIR_EXTENSION.'coinsnap/library/autoload.php');


class Coinsnap extends \Opencart\System\Engine\Controller	{	
	
	public function index() {
		$this->load->model('checkout/order');
		$this->language->load('extension/coinsnap/payment/coinsnap');
		$data['button_confirm'] = $this->language->get('button_confirm');		
		$action_url = $this->url->link('extension/coinsnap/payment/coinsnap.doPayment');
		$data['action'] = $action_url;
		return $this->load->view('extension/coinsnap/payment/coinsnap', $data);	
			
	}

	
		
	public function webhook() {
		$this->language->load('extension/coinsnap/payment/coinsnap');
		
		$notify_json = file_get_contents('php://input');		
		$this->log->write('Coinsnap Webhook - Data: '.$notify_json);
		$notify_ar = json_decode($notify_json, true);
		$invoice_id = $notify_ar['invoiceId'];

		try {
			$client = new \Coinsnap\Client\Invoice( $this->getApiUrl(), $this->getApiKey() );			
			$invoice = $client->getInvoice($this->getStoreId(), $invoice_id);
			$status = $invoice->getData()['status'] ;

			$order_id = $invoice->getData()['orderId'] ;			
			
			$this->log->write('Coinsnap Webhook - Order Id: '.$order_id);		
	
		}catch (\Throwable $e) {									
			$this->log->write('Coinsnap Webhook - Error: '.$e->getMessage());						
			echo "Error";
			exit;
		}

		 
		$order_status = $this->config->get('payment_coinsnap_new_status');
		if ($status == 'Expired') $order_status =$this->config->get('payment_coinsnap_expired_status') ;
		else if ($status == 'Processing') $order_status = $this->config->get('payment_coinsnap_processing_status') ;
		else if ($status == 'Settled') $order_status = $this->config->get('payment_coinsnap_settled_status') ;
		$this->update_payment($order_id, $invoice_id, $order_status);
		echo "OK";
		
		exit;
		
		
	}
	public function getApiUrl() {
		return 'https://app.coinsnap.io';
	}	

	public function getStoreId() {
		return $this->config->get('payment_coinsnap_store_id');
	}	
	public function getApiKey() {
		return $this->config->get('payment_coinsnap_api_key');
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

	public function doPayment() {
		$this->language->load('extension/coinsnap/payment/coinsnap');
        $this->load->model('setting/setting');
		$this->load->model('checkout/order');
		
		$order_id = $this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($order_id);										
		
		$redirectUrl = HTTP_SERVER.'index.php?route=checkout/success';
		$amount = round($order_info['total'], 2);
		$buyerEmail = $order_info['email'];
		$buyerName =  $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
		$currency_code = $order_info['currency_code'];		

		$metadata = [];
		$metadata['orderNumber'] = $order_id;
		$metadata['customerName'] = $buyerName;

		$checkoutOptions = new \Coinsnap\Client\InvoiceCheckoutOptions();
		$checkoutOptions->setRedirectURL( $redirectUrl );
		$client =new \Coinsnap\Client\Invoice( $this->getApiUrl(), $this->getApiKey() );
		$camount = \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
		$invoice = $client->createInvoice(
			$this->getStoreId(),  
			$currency_code,
			$camount,
			$order_id,
			$buyerEmail,
			$buyerName, 
			$redirectUrl,
			COINSNAP_REFERRAL_CODE,     
			$metadata,
			$checkoutOptions
		);
		
		$payurl = $invoice->getData()['checkoutLink'] ;		
		
		if ($payurl) {	
			$comment = 'new';		
			$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_coinsnap_new_status'), $comment);
								
			$this->clearCart();
            $this->sessionClear();
			$this->response->redirect($payurl);			
		} else {			
			$errorText = 'API Error';
			echo $errorText;
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