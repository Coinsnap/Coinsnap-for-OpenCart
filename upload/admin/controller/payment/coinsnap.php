<?php
namespace Opencart\Admin\Controller\Extension\Coinsnap\Payment;
if(!defined('COINSNAP_SERVER_URL')){ define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}

require_once(DIR_EXTENSION.'coinsnap/library/loader.php');
use Coinsnap\Client\Webhook;

class Coinsnap extends \Opencart\System\Engine\Controller {	
	
    private $error = array();		
    public const COINSNAP_WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];
    public const BTCPAY_WEBHOOK_EVENTS = ['InvoiceCreated','InvoiceExpired','InvoiceSettled','InvoiceProcessing'];
	
    public function index() {
        $this->load->language('extension/coinsnap/payment/coinsnap');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('/extension/coinsnap/admin/view/javascript/admin.js');
	$this->load->model('setting/setting');		
				
		
        $this->load->model('localisation/order_status');
	$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		/*
		 * labels and fieldsets
		 */
		$data['heading_title']					= $this->language->get('heading_title');
		$data['text_enabled']						= $this->language->get('text_enabled');
		$data['text_disabled']					= $this->language->get('text_disabled');		
		$data['text_all_zones']					= $this->language->get('text_all_zones');
		
		
		$data['fieldset_payment_coinsnap_module']			= $this->language->get('fieldset_payment_coinsnap_module');		
		$data['fieldset_coinsnap']			= $this->language->get('fieldset_coinsnap');
		
		/*
		 * Module settings
		 */
		$data['entry_payment_coinsnap_status']	= $this->language->get('entry_payment_coinsnap_status');
		$data['entry_geo_zone']		= $this->language->get('entry_geo_zone');
		$data['entry_sort_order']		= $this->language->get('entry_sort_order');
		$data['entry_method_name']	= $this->language->get('entry_method_name');
		
		//helps
		$data['help_method_name']		= $this->language->get('help_method_name');
		$data['help_payment_coinsnap_status']	= $this->language->get('help_payment_coinsnap_status');
		$data['help_geo_zone']		= $this->language->get('help_geo_zone');
		$data['help_sort_order']		= $this->language->get('help_sort_order');
		$data['help_sort_order']		= $this->language->get('help_sort_order');		
		
			
		

		if (isset($this->request->post['payment_coinsnap_status'])) {
			$data['payment_coinsnap_status'] = $this->request->post['payment_coinsnap_status'];
		} else {
			$data['payment_coinsnap_status'] = $this->config->get('payment_coinsnap_status');
		}

                if (isset($this->request->post['payment_coinsnap_autoredirect'])) {
			$data['payment_coinsnap_autoredirect'] = $this->request->post['payment_coinsnap_autoredirect'];
		} else {
			$data['payment_coinsnap_autoredirect'] = $this->config->get('payment_coinsnap_autoredirect');
		}
                
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_coinsnap_geo_zone_id'])) {
			$data['payment_coinsnap_geo_zone_id'] = $this->request->post['payment_coinsnap_geo_zone_id'];
		} else {
			$data['payment_coinsnap_geo_zone_id'] = $this->config->get('payment_coinsnap_geo_zone_id');
		}
		if (isset($this->request->post['payment_coinsnap_sort_order'])) {
			$data['payment_coinsnap_sort_order'] = $this->request->post['payment_coinsnap_sort_order'];
		} else {
			$data['payment_coinsnap_sort_order'] = $this->config->get('payment_coinsnap_sort_order');
		}
		
		
		if (isset($this->error['method_name'])) {
			$data['error_method_name'] = $this->error['method_name'];
		} else {
			$data['error_method_name'] = '';
		}
		
		/*
		 * Coinsnap 
		 */
		$data['entry_store_id']	= $this->language->get('entry_store_id');		
		$data['entry_api_key']	= $this->language->get('entry_api_key');
                
                $data['entry_btcpay_server_url']= $this->language->get('entry_btcpay_server_url');
                $data['entry_btcpay_store_id']	= $this->language->get('entry_btcpay_store_id');		
		$data['entry_btcpay_api_key']	= $this->language->get('entry_btcpay_api_key');
                
		$data['entry_new_status']	= $this->language->get('entry_new_status');		
		$data['entry_expired_status']	= $this->language->get('entry_expired_status');				
		$data['entry_settled_status']	= $this->language->get('entry_settled_status');		
		$data['entry_processing_status']	= $this->language->get('entry_processing_status');	
                
                $data['entry_autoredirect']     = $this->language->get('entry_autoredirect');
		
		
		$data['help_store_id']		= $this->language->get('help_store_id');		
		$data['help_api_key']		= $this->language->get('help_api_key');				
		
		//errors
		
		if (isset($this->error['store_id'])) {
			$data['error_store_id'] = $this->error['store_id'];
		} else {
			$data['error_store_id'] = '';
		}
		if (isset($this->error['api_key'])) {
			$data['error_api_key'] = $this->error['api_key'];
		} else {
			$data['error_api_key'] = '';
		}
		
		
		
		
		if (isset($this->request->post['payment_coinsnap_store_id'])) {
			$data['payment_coinsnap_store_id'] = $this->request->post['payment_coinsnap_store_id'];
		} else {
			$data['payment_coinsnap_store_id'] = $this->config->get('payment_coinsnap_store_id');
		}
		
		if (isset($this->request->post['payment_coinsnap_api_key'])) {
			$data['payment_coinsnap_api_key'] = $this->request->post['payment_coinsnap_api_key'];
		} else {
			$data['payment_coinsnap_api_key'] = $this->config->get('payment_coinsnap_api_key');
		}
                
                if (isset($this->request->post['payment_coinsnap_btcpay_server_url'])) {
			$data['payment_coinsnap_btcpay_server_url'] = $this->request->post['payment_coinsnap_btcpay_server_url'];
		} else {
			$data['payment_coinsnap_btcpay_server_url'] = $this->config->get('payment_coinsnap_btcpay_server_url');
		}
		
		if (isset($this->request->post['payment_coinsnap_btcpay_store_id'])) {
			$data['payment_coinsnap_btcpay_store_id'] = $this->request->post['payment_coinsnap_btcpay_store_id'];
		} else {
			$data['payment_coinsnap_btcpay_store_id'] = $this->config->get('payment_coinsnap_btcpay_store_id');
		}
		
		if (isset($this->request->post['payment_coinsnap_btcpay_api_key'])) {
			$data['payment_coinsnap_btcpay_api_key'] = $this->request->post['payment_coinsnap_btcpay_api_key'];
		} else {
			$data['payment_coinsnap_btcpay_api_key'] = $this->config->get('payment_coinsnap_btcpay_api_key');
		}
		

		if (isset($this->request->post['payment_coinsnap_new_status'])) {			
			$data['payment_coinsnap_new_status'] = $this->request->post['payment_coinsnap_new_status'];
		} else {
			$new_status = $this->config->get('payment_coinsnap_new_status');			
			$data['payment_coinsnap_new_status'] = (!empty($new_status)) ? $new_status : '1';
		}	
		
		
		if (isset($this->request->post['payment_coinsnap_expired_status'])) {			
			$data['payment_coinsnap_expired_status'] = $this->request->post['payment_coinsnap_expired_status'];
		} else {
			$expired_status = $this->config->get('payment_coinsnap_expired_status');			
			$data['payment_coinsnap_expired_status'] = (!empty($expired_status)) ? $expired_status : '14';
		}
                
                if (isset($this->request->post['payment_coinsnap_settled_status'])) {			
			$data['payment_coinsnap_settled_status'] = $this->request->post['payment_coinsnap_settled_status'];
		} else {
			$settled_status = $this->config->get('payment_coinsnap_settled_status');			
			$data['payment_coinsnap_settled_status'] = (!empty($settled_status)) ? $settled_status : '2';
		}

		if (isset($this->request->post['payment_coinsnap_processing_status'])) {
			$data['payment_coinsnap_processing_status'] = $this->request->post['payment_coinsnap_processing_status'];
		} else {
			$processing_status = $this->config->get('payment_coinsnap_processing_status'); 			
			$data['payment_coinsnap_processing_status'] = (!empty($processing_status)) ? $processing_status : '2';
		}		
		

		if (isset($this->request->post['payment_coinsnap_method_name'])) {
			$data['payment_coinsnap_method_name'] = $this->request->post['payment_coinsnap_method_name'];
		} else {
			$method_name = $this->config->get('payment_coinsnap_method_name'); 			
			$data['payment_coinsnap_method_name'] = (!empty($method_name)) ? $method_name : 'Bitcoin + Lightning';
		}
		

		/*
		 * Buttons
		 */
		$data['button_save']    = $this->language->get('button_save');
		$data['button_cancel']	= $this->language->get('button_cancel');
		$data['button_search']	= $this->language->get('button_search');		

			

		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		
		/*
		 * Navigation
		 */
		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/coinsnap/payment/coinsnap', 'user_token=' . $this->session->data['user_token'])
		];
		
		$data['action'] = $this->url->link('extension/coinsnap/payment/coinsnap|save', 'user_token=' . $this->session->data['user_token']);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');
		

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');		
			
        $this->response->setOutput($this->load->view('extension/coinsnap/payment/coinsnap', $data));
    }

    public function save(): void {
	
        $this->load->language('extension/coinsnap/payment/coinsnap');
	$json = [];
		
	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {			
			
            if($this->model_setting_setting->getValue('payment_coinsnap_webhook') !== null){
                $this->request->post['payment_coinsnap_webhook'] = $this->model_setting_setting->getValue('payment_coinsnap_webhook');
            }
            else {
                $this->request->post['payment_coinsnap_webhook'] = '';
            }
            
            $this->model_setting_setting->editSetting('payment_coinsnap', $this->request->post);
            
            $provider = ($this->request->post['payment_coinsnap_provider'] === 'btcpay')? 'btcpay' : 'coinsnap';
            $api_url =  ($provider === 'btcpay')? $this->request->post['payment_coinsnap_btcpay_server_url'] : COINSNAP_SERVER_URL;
            $store_id = ($provider === 'btcpay')? $this->request->post['payment_coinsnap_btcpay_store_id'] : $this->request->post['payment_coinsnap_store_id'];			
            $api_key =  ($provider === 'btcpay')? $this->request->post['payment_coinsnap_btcpay_api_key'] : $this->request->post['payment_coinsnap_api_key'];
                    
            if (! $this->webhookExists($api_url, $api_key, $store_id)){
                if (! $this->registerWebhook($api_url, $api_key, $store_id, $provider)) {
                    $json['error'] = $this->language->get('error_webhook');										
                }
                else {
                    $json['info'] = 'Webhook is successfully registered';
                }
            }
            else {
                $json['info'] = 'Webhook exists';
            }
            
            $client = new \Coinsnap\Client\Invoice($api_url, $api_key);
            $store = new \Coinsnap\Client\Store($api_url, $api_key);
            $currency = $this->model_setting_setting->getValue('config_currency');
            if(!isset($currency) || $currency === null){
                $currency = 'EUR';
            }

            $connectionData = '';

            if ($this->request->post['payment_coinsnap_provider'] === 'btcpay') {

                    try {
                        $storePaymentMethods = $store->getStorePaymentMethods($store_id);

                        if ($storePaymentMethods['code'] === 200) {
                            if ($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']) {
                                $checkInvoice = $client->checkPaymentData(0, $currency, 'bitcoin', 'calculation');
                            } elseif ($storePaymentMethods['result']['lightning']) {
                                $checkInvoice = $client->checkPaymentData(0, $currency, 'lightning', 'calculation');
                            }
                        }
                    } catch (\Exception $e) {
                        $errorMessage = $this->language->get('error_connection');
                    }

            }
            else {
                    $checkInvoice = $client->checkPaymentData(0, $currency, 'coinsnap', 'calculation');
            }

            if (isset($checkInvoice) && $checkInvoice['result']) {
                    $connectionData = $this->language->get('text_order_amount') .' '. $checkInvoice['min_value'].' '.$currency;
            } else {
                    $connectionData = $this->language->get('error_no_payment_method');
            }
                    
            $json['success'] = $this->language->get('text_success') . ' '. $connectionData;			
        }
        else {							
            $json['error'] = $this->error['warning'] . ' '. $connectionData;
        }

        $this->response->addHeader('Content-Type: application/json');
	$this->response->setOutput(json_encode($json));
    }
	
    private function validate() {
		
        $exit = false;
        
        //  Modify permission check
        if (!$this->user->hasPermission('modify', 'extension/coinsnap/payment/coinsnap')) {			
            $this->error['warning'] = $this->language->get('error_permission');
            $exit = true;
        }
	
        //  Payment method name
        if (!$this->request->post['payment_coinsnap_method_name']) {
            $this->error['warning'] = $this->language->get('required_method_name');			
            $exit = true;
	}
	
        
        if ($this->request->post['payment_coinsnap_provider'] === 'coinsnap' && !$this->request->post['payment_coinsnap_store_id']) {
			
			$this->error['warning'] = $this->language->get('required_store_id');			
			$exit = true;
		}
		if ($this->request->post['payment_coinsnap_provider'] === 'coinsnap' && !$this->request->post['payment_coinsnap_api_key']) {
			
			$this->error['warning'] = $this->language->get('required_api_key');			
			$exit = true;
		}
		
		if ($this->request->post['payment_coinsnap_provider'] === 'btcpay' && !$this->request->post['payment_coinsnap_btcpay_server_url']) {
			
			$this->error['warning'] = $this->language->get('required_btcpay_server_url');			
			$exit = true;
		}
		if ($this->request->post['payment_coinsnap_provider'] === 'btcpay' && !$this->request->post['payment_coinsnap_btcpay_store_id']) {
			
			$this->error['warning'] = $this->language->get('required_btcpay_store_id');			
			$exit = true;
		}
		if ($this->request->post['payment_coinsnap_provider'] === 'btcpay' && !$this->request->post['payment_coinsnap_btcpay_api_key']) {
			
			$this->error['warning'] = $this->language->get('required_btcpay_api_key');			
			$exit = true;
		}
                
        if (null === $this->config->get('payment_coinsnap_webhook')){
            $this->request->post['payment_coinsnap_webhook'] = [];
        }
                
		
        if ($exit) {
            return false;
	}
	$exit = false;
		
        if (!$exit) {
            return true;
	} else {
            return false;
        }
    }	
	
    public function getWebhookUrl(){
        return $webhook_url = HTTP_CATALOG . 'index.php?route=extension/coinsnap/payment/coinsnap.webhook';
    }
    
    public function getProvider() {
        return ($this->config->get('payment_coinsnap_provider') === 'btcpay') ? 'btcpay' : 'coinsnap';
    }        
            
    public function getApiUrl() {
        return ($this->getProvider() === 'btcpay')? $this->config->get('payment_coinsnap_btcpay_server_url') : COINSNAP_SERVER_URL;
    }	

    public function getStoreId() {
        return ($this->getProvider() === 'btcpay')? $this->config->get('payment_coinsnap_btcpay_store_id') : $this->config->get('payment_coinsnap_store_id');
    }	
    
    public function getApiKey() {
        return ($this->getProvider() === 'btcpay')? $this->config->get('payment_coinsnap_btcpay_api_key') : $this->config->get('payment_coinsnap_api_key');
    }

    public function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool{
        
        $whClient = new \Coinsnap\Client\Webhook($apiUrl, $apiKey);
        $storedWebhook = json_decode($this->config->get('payment_coinsnap_webhook'),true);
                
        if ($storedWebhook && is_array($storedWebhook)) {

            try {
                $existingWebhook = $whClient->getWebhook($storeId, $storedWebhook['id']);

                if ($existingWebhook->getData()['id'] === $storedWebhook['id'] && strpos($existingWebhook->getData()['url'], $storedWebhook['url']) !== false) {
                    return true;
                }
            } catch (\Throwable $e) {
                $errorMessage = 'Error fetching existing Webhook. Message: ' .$e->getMessage();
                return false;
            }
        }
        try {
            $storeWebhooks = $whClient->getWebhooks($storeId);
            foreach ($storeWebhooks as $webhook) {
                if (strpos($webhook->getData()['url'], $this->getWebhookUrl()) !== false) {
                    $whClient->deleteWebhook($storeId, $webhook->getData()['id']);
                }
            }
        } catch (\Throwable $e) {
            $errorMessage = 'Error fetching webhooks for store ID '.$storeId.'. Message: ' .$e->getMessage();
            return false;
        }

        return false;
    }
	
    public function registerWebhook(string $apiUrl, string $apiKey, string $storeId, string $provider){
        $this->load->language('extension/coinsnap/payment/coinsnap');
        $this->load->model('setting/setting');
        try {
            $whClient = new Webhook($apiUrl, $apiKey);
            $webhook_events = ($provider === 'btcpay') ? self::BTCPAY_WEBHOOK_EVENTS : self::COINSNAP_WEBHOOK_EVENTS;
            $webhook = $whClient->createWebhook(
                $storeId,   //$storeId
                $this -> getWebhookUrl(), //$url
                $webhook_events,   //$specificEvents
                null    //$secret
            );
            
            $webhook_data = [
                    'id' => $webhook->getData()['id'],
                    'secret' => $webhook->getData()['secret'],
                    'url' => $webhook->getData()['url']
            ];
            
            
            $this->model_setting_setting->editValue('payment_coinsnap', 'payment_coinsnap_webhook', json_encode($webhook_data));
            
            return $webhook;

        } catch (\Throwable $e) {
            $errorMessage = 'Error creating a new webhook for StoreID '.$storeId.' on Coinsnap instance: ' . $this->config->get('payment_coinsnap_webhook').' - '. $e->getMessage();
            echo $errorMessage;
            return false;
        }
    }
}