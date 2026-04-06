<?php
namespace Opencart\Admin\Controller\Extension\Coinsnap\Total;
class Discount extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('extension/coinsnap/total/discount');
        $this->document->setTitle($this->language->get('heading_title'));
        
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
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/coinsnap/total/discount', 'user_token=' . $this->session->data['user_token'])
		];
		
		$data['save'] = $this->url->link('extension/coinsnap/total/discount.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total');

		$data['total_discount_status'] = $this->config->get('total_discount_status');
		$data['total_discount_sort_order'] = $this->config->get('total_discount_sort_order');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');		
			
        $this->response->setOutput($this->load->view('extension/coinsnap/total/discount', $data));
    }

    public function install(): void {
        
        $this->load->model('setting/setting');

        $this->model_setting_setting->editSetting('total_discount', [
            'total_discount_status' => 1,
            'total_discount_sort_order' => 6
        ]);
    }
    
    /**
	 * Save
	 *
	 * @return void
	 */
    public function save(): void {
        
        $this->load->language('extension/coinsnap/total/discount');
        $json = [];
        
        if ($this->request->server['REQUEST_METHOD'] == 'POST'){
            
        }

        if (!$this->user->hasPermission('modify', 'extension/coinsnap/total/discount')) {
            $json['error'] = $this->language->get('error_permission');
        }

        //if (!isset($json['error'])) {
			// Setting
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('total_discount', $this->request->post);

			$json['success'] = $this->language->get('text_success') . json_encode($this->request->post);
		//}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
    }
}