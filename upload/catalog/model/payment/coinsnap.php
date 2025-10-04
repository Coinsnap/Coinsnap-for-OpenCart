<?php
namespace Opencart\Catalog\Model\Extension\Coinsnap\Payment;

class Coinsnap extends \Opencart\System\Engine\Model {


	public function getMethods(array $address): array {
		$this->load->language('extension/coinsnap/payment/coinsnap');
		
		
		if (!$this->config->get('payment_cardinity_payment_geo_zone_id')) {
			$status = true;
		} elseif(isset($address['country_id']) && isset($address['zone_id'])) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_coinsnap_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");
			if ($query->num_rows) {
				$status = true;
			} else {
				$status = false;
			}
		}else{
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$option_data['coinsnap'] = [
				'code' => 'coinsnap.coinsnap',
				'name' => $this->config->get('payment_coinsnap_method_name')
			];

			$method_data = [
				'code'       => 'coinsnap',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_coinsnap_sort_order')
			];
		}

		return $method_data;
	}
}
?>