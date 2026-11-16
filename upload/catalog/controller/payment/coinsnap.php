<?php
namespace Opencart\Catalog\Controller\Extension\Coinsnap\Payment;

if(!defined( 'COINSNAP_OPENCART_REFERRAL_CODE')) { define( 'COINSNAP_OPENCART_REFERRAL_CODE', 'D19823' ); }
if(!defined('COINSNAP_SERVER_URL')){ define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}

require_once(DIR_EXTENSION.'coinsnap/library/loader.php');
require_once(DIR_EXTENSION.'coinsnap/system/library/coinsnap/Signature.php');
require_once(DIR_EXTENSION.'coinsnap/system/library/coinsnap/InvoiceDecision.php');

use CoinsnapOpencart\InvoiceDecision;
use CoinsnapOpencart\Signature;

class Coinsnap extends \Opencart\System\Engine\Controller	{

    private const SECURITY_LINES_PER_MINUTE = 10;

    public function index(){
        $this->load->model('checkout/order');
        $this->load->model('setting/event');
        $this->language->load('extension/coinsnap/payment/coinsnap');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $action_url = $this->url->link('extension/coinsnap/payment/coinsnap.doPayment');
	$data['action'] = $action_url;
        return $this->load->view('extension/coinsnap/payment/coinsnap', $data);
    }

    // Webhook endpoint. Only the invoice id is taken from the request; every decision comes from the API.
    public function webhook(){
        $this->load->model('checkout/order');
        $this->load->model('extension/coinsnap/payment/coinsnap');

        $rawPostData = (string) file_get_contents('php://input');
        $webhook = json_decode((string) $this->config->get('payment_coinsnap_webhook'), true);
        $secret = is_array($webhook) ? (string) ($webhook['secret'] ?? '') : '';

        $problem = ($this->getProvider() === 'btcpay')
            ? Signature::btcpayProblem($this->requestHeader('BTCPay-Sig'), $rawPostData, $secret)
            : Signature::coinsnapProblem($this->requestHeader('X-Coinsnap-Signature'), $rawPostData, $secret, time());
        if ($problem !== null) {
            $this->logSecurity($problem);
            $this->reply(401, 'Unauthorized');
            return;
        }

        $body = json_decode($rawPostData, true);
        $invoiceId = (is_array($body) && is_string($body['invoiceId'] ?? null)) ? $body['invoiceId'] : '';
        if ($invoiceId === '') {
            $this->reply(200, 'OK');
            return;
        }
        $claimsPaid = in_array($body['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        try {
            $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
            $invoice = $client->getInvoice($this->getStoreId(), $invoiceId)->getData();
        } catch (\Coinsnap\Exception\RequestException $e) {
            if (in_array($e->getCode(), [400, 404], true)) {
                // The dashboard's "Send test webhook" button lands here.
                $this->logInfo('unknown invoice', $invoiceId);
                $this->reply(200, 'OK');
                return;
            }
            $this->logInfo('retry: API unavailable', $invoiceId);
            $this->reply(503, 'Retry');
            return;
        } catch (\Throwable $e) {
            $this->logInfo('retry: API unavailable', $invoiceId);
            $this->reply(503, 'Retry');
            return;
        }

        $order = $this->findOrder($invoice);
        if ($order === null) {
            $this->logInfo('not ours', $invoiceId);
            $this->reply(200, 'OK');
            return;
        }
        $expected = $this->expectedPayment($order, $invoiceId);
        if ($expected === null) {
            $this->logSecurity('invoice not created for this order', $invoiceId);
            $this->reply(200, 'OK');
            return;
        }

        $decision = InvoiceDecision::decide($invoice, $this->getProvider() === 'btcpay', $expected['amount'], $expected['currency']);
        if ($decision !== InvoiceDecision::PAID && ($claimsPaid || $decision === InvoiceDecision::MISMATCH)) {
            $this->logSecurity($claimsPaid ? 'forged status' : 'amount mismatch', $invoiceId);
            $this->reply(200, 'OK');
            return;
        }

        $this->applyDecision($decision, $order, $invoiceId, $expected);
        $this->reply(200, 'OK');
    }

    private function requestHeader(string $name): string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->request->server[$key]) ? (string) $this->request->server[$key] : '';
    }

    protected function reply(int $status, string $message): void {
        http_response_code($status);
        $this->response->setOutput($message);
    }

    // The order this extension created the invoice for, or null when the invoice belongs to something else.
    private function findOrder(array $invoice): ?array {
        $metadata = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $orderId = ($this->getProvider() === 'btcpay') ? ($metadata['orderId'] ?? '') : ($invoice['orderId'] ?? '');
        if (!is_scalar($orderId) || !ctype_digit((string) $orderId)) {
            return null;
        }
        $order = $this->model_checkout_order->getOrder((int) $orderId);
        if (empty($order['order_id'])) {
            return null;
        }
        // OpenCart 4.0.2+ keeps the code in payment_method, earlier 4.0 releases in payment_code.
        $code = is_array($order['payment_method'] ?? null) ? ($order['payment_method']['code'] ?? '') : ($order['payment_code'] ?? '');
        return strpos((string) $code, 'coinsnap') === 0 ? $order : null;
    }

    // Amount and currency sent when the invoice was created; null when this order never had that invoice.
    private function expectedPayment(array $order, string $invoiceId): ?array {
        $model = $this->model_extension_coinsnap_payment_coinsnap;
        $recorded = $model->findInvoice($invoiceId, (int) $order['order_id']);
        if ($recorded !== null) {
            return $recorded;
        }
        if ($model->orderHasInvoices((int) $order['order_id'])) {
            return null;
        }
        // Orders placed before 1.3.0 have no record; check against what was sent then.
        return ['amount' => number_format(round((float) $order['total'], 2), 2, '.', ''), 'currency' => (string) $order['currency_code']];
    }

    // Moves an order forward only: an order that left the awaiting statuses never changes from a webhook.
    private function applyDecision(string $decision, array $order, string $invoiceId, array $expected): void {
        $status = (int) $order['order_status_id'];
        $new = (int) $this->config->get('payment_coinsnap_new_status');
        $processing = (int) $this->config->get('payment_coinsnap_processing_status');
        $expired = (int) $this->config->get('payment_coinsnap_expired_status');
        $orderId = (int) $order['order_id'];

        if ($decision === InvoiceDecision::PAID) {
            // A late payment can still settle an order that expired.
            if (!in_array($status, [0, $new, $processing, $expired], true)) {
                $this->logInfo('no change from status ' . $status, $invoiceId);
                return;
            }
            if (!$this->model_extension_coinsnap_payment_coinsnap->claimPaid($invoiceId, $orderId, $expected['amount'], $expected['currency'])) {
                $this->logInfo('already processed', $invoiceId);
                return;
            }
            $this->update_payment($orderId, $invoiceId, $this->config->get('payment_coinsnap_settled_status'));
            $this->logInfo('paid', $invoiceId);
            return;
        }
        if ($decision === InvoiceDecision::PROCESSING && in_array($status, [0, $new], true) && $status !== $processing) {
            $this->update_payment($orderId, $invoiceId, $processing);
            $this->logInfo('status processing', $invoiceId);
            return;
        }
        if ($decision === InvoiceDecision::EXPIRED && in_array($status, [0, $new, $processing], true)) {
            $this->update_payment($orderId, $invoiceId, $expired);
            $this->logInfo('status expired', $invoiceId);
            return;
        }
        $this->logInfo('no change', $invoiceId);
    }

    private function logInfo(string $reason, string $invoiceId = ''): void {
        $this->log->write($this->webhookLine('INFO', $reason, $invoiceId));
    }

    // At most 10 security lines a minute, so a flood of fake requests can't fill the log.
    private function logSecurity(string $reason, string $invoiceId = ''): void {
        $key = 'coinsnap_webhook_seclog_' . gmdate('YmdHi');
        $count = (int) $this->cache->get($key) + 1;
        $this->cache->set($key, $count, 120);
        if ($count === self::SECURITY_LINES_PER_MINUTE + 1) {
            $this->log->write('Coinsnap SECURITY webhook: more rejections this minute, not logged');
        }
        if ($count <= self::SECURITY_LINES_PER_MINUTE) {
            $this->log->write($this->webhookLine('SECURITY', $reason, $invoiceId));
        }
    }

    private function webhookLine(string $level, string $reason, string $invoiceId): string {
        // The invoice id comes from the request.
        $id = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceId), 0, 64);
        return sprintf('Coinsnap %s webhook reason=%s invoice=%s', $level, $reason, $id !== '' ? $id : '-');
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
        $this->load->model('extension/coinsnap/payment/coinsnap');

        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        // The order total is stored in the default currency; convert it to the currency the customer pays in.
        $amount = round((float) $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false), 2);
        $buyerEmail = $order_info['email'];
	$buyerName =  $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
	$currency = $order_info['currency_code'];

        $checkInvoice = $this->checkAmount($amount, strtoupper($currency));
        if (($checkInvoice['result'] ?? false) !== true) {
            $error = $checkInvoice['error'] ?? '';
            if ($error === 'currencyError') {
                $errorMessage = 'Currency '.strtoupper($currency).' is not supported by Coinsnap';
            } elseif ($error === 'amountError') {
                $errorMessage = 'Invoice amount cannot be less than '.$checkInvoice['min_value'].' '.strtoupper($currency);
            } else {
                $errorMessage = 'The payment provider cannot be reached. Please try again later.';
            }
            $this->log->write('Coinsnap: payment not started for order '.(int) $order_id.': '.$errorMessage);
            echo $errorMessage;
            exit;
        }

        $redirectUrl = (!empty($this->config->get('payment_coinsnap_returnurl')))? $this->config->get('payment_coinsnap_returnurl') : HTTP_SERVER.'index.php?route=checkout/success';
        $metadata = ['orderNumber' => $order_id, 'customerName' => $buyerName];
        if ($this->getProvider() === 'btcpay') {
            $metadata['orderId'] = $order_id;
        }
        $redirectAutomatically = ($this->config->get('payment_coinsnap_autoredirect') > 0);

        try {
            // Currencies BTCPay Server does not support are charged in BTC at the current rate.
            if ($currency !== 'BTC' && $this->getProvider() === 'btcpay') {
                $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
                $btcpayCurrencies = $store -> getStoreCurrenciesRates($this->getStoreId(),array($currency));
                $isCurrency = !isset($btcpayCurrencies['result']['error'])
                    && count($btcpayCurrencies['result']['currencies']) > 0
                    && isset($btcpayCurrencies['result']['currencies']['BTC_'.$currency]);
                if( !$isCurrency ){
                    $currency = 'BTC';
                    $rate = 1/$checkInvoice['rate'];
                    $amount = (float) bcdiv(strval($amount), strval($rate), 8);
                }
            }

            $camount = ($currency === 'BTC')? \Coinsnap\Util\PreciseNumber::parseFloat($amount,8) : \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);

            $client = new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
            $invoiceData = $client->createInvoice(
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
                ''
            )->getData();
        } catch (\Throwable $e) {
            $this->log->write('Coinsnap: invoice creation failed for order '.(int) $order_id.' (status '.(int) $e->getCode().')');
            $invoiceData = [];
        }

        if (empty($invoiceData['checkoutLink']) || empty($invoiceData['id'])) {
            echo 'The payment could not be started. Please try again later.';
            exit;
        }

        // Only this invoice, for this amount and currency, can pay the order.
        $this->model_extension_coinsnap_payment_coinsnap->rememberInvoice((string) $invoiceData['id'], (int) $order_id, $camount->__toString(), $currency);
        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_coinsnap_new_status'), 'New');
        $this->clearCart();
        $this->sessionClear();
        $this->response->redirect($invoiceData['checkoutLink']);
    }

    public function update_payment($order_id, $invoice_id, $order_status){
        $comment = 'Coinsnap Invoice ID: '.$invoice_id;
        $this->load->model('checkout/order');
        $this->model_checkout_order->addHistory($order_id, $order_status, $comment);
    }
}
