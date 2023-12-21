<?php
namespace Opencart\Admin\Controller\Extension\Coinsnap\Payment;

require_once(DIR_EXTENSION.'coinsnap/library/autoload.php');

class Coinsnap extends \Opencart\System\Engine\Controller {	
	
		
	private $error = array();		
	public const WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];	
	
	public function index() {
		$this->load->language('extension/coinsnap/payment/coinsnap');
		$this->document->setTitle($this->language->get('heading_title'));
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
		$data['entry_new_status']	= $this->language->get('entry_new_status');		
		$data['entry_expired_status']	= $this->language->get('entry_expired_status');				
		$data['entry_settled_status']	= $this->language->get('entry_settled_status');		
		$data['entry_processing_status']	= $this->language->get('entry_processing_status');						
		
		
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
		$data['button_save']		= $this->language->get('button_save');
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
			
			$webhook_url = HTTP_CATALOG . 'index.php?route=extension/coinsnap/payment/coinsnap.webhook';			
			$store_id = $this->request->post['payment_coinsnap_store_id'];			
			$api_key = $this->request->post['payment_coinsnap_api_key'];
			
			if (! $this->webhookExists($store_id, $api_key, $webhook_url)){
				if (! $this->registerWebhook($store_id, $api_key,$webhook_url)) {
					$json['error'] = $this->language->get('error_webhook');										
				}

			}

			

			$this->model_setting_setting->editSetting('payment_coinsnap', $this->request->post);
			$json['success'] = $this->language->get('text_success');			
		}
		else {							
			$json['error'] = $this->error['warning'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	
	
	
	private function validate() {
		
		$exit = false;

		
		if (!$this->user->hasPermission('modify', 'extension/coinsnap/payment/coinsnap')) {			
			$this->error['warning'] = $this->language->get('error_permission');
			$exit = true;
		}
		if (!$this->request->post['payment_coinsnap_method_name']) {
			
			$this->error['warning'] = $this->language->get('required_method_name');			
			$exit = true;
		}
		if (!$this->request->post['payment_coinsnap_store_id']) {
			
			$this->error['warning'] = $this->language->get('required_store_id');			
			$exit = true;
		}
		if (!$this->request->post['payment_coinsnap_api_key']) {
			
			$this->error['warning'] = $this->language->get('required_api_key');			
			$exit = true;
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
	public function getApiUrl() {
		return 'https://app.coinsnap.io';
	}	

	public function webhookExists(string $storeId, string $apiKey, string $webhook): bool {	
		try {		
			$whClient = new \Coinsnap\Client\Webhook( $this->getApiUrl(), $apiKey );		
			$Webhooks = $whClient->getWebhooks( $storeId );																		
			
			foreach ($Webhooks as $Webhook){					
//				$this->deleteWebhook($storeId,$apiKey, $Webhook->getData()['id']);
				if ($Webhook->getData()['url'] == $webhook) return true;	
			}
		}catch (\Throwable $e) {			
			return false;
		}
	
		return false;
	}
	public function registerWebhook(string $storeId, string $apiKey, string $webhook): bool {	
		try {			
			$whClient = new \Coinsnap\Client\Webhook($this->getApiUrl(), $apiKey);
			
			$webhook = $whClient->createWebhook(
				$storeId,   //$storeId
				$webhook, //$url
				self::WEBHOOK_EVENTS,   //$specificEvents
				null    //$secret
			);		
			
			return true;
		} catch (\Throwable $e) {
			return false;	
		}

		return false;
	}

	public function deleteWebhook(string $storeId, string $apiKey, string $webhookid): bool {	    
		
		try {			
			$whClient = new \Coinsnap\Client\Webhook($this->api_url, $apiKey);
			
			$webhook = $whClient->deleteWebhook(
				$storeId,   //$storeId
				$webhookid, //$url			
			);					
			return true;
		} catch (\Throwable $e) {
			
			return false;	
		}


    }	
	
}
?>