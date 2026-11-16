<?php
namespace Opencart\Catalog\Model\Extension\Coinsnap\Payment;

class Coinsnap extends \Opencart\System\Engine\Model {


	public function getMethods(array $address): array {
		$this->load->language('extension/coinsnap/payment/coinsnap');
		
		
		if (!$this->config->get('payment_coinsnap_geo_zone_id')) {
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

	// Invoices this extension created: the order each pays for, the amount and currency sent, and whether it was applied.
	public function ensureInvoiceTable(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "coinsnap_invoice` (
			`invoice_id` VARCHAR(64) NOT NULL,
			`order_id` INT UNSIGNED NOT NULL,
			`amount` VARCHAR(32) NOT NULL,
			`currency` VARCHAR(8) NOT NULL,
			`paid_at` DATETIME NULL,
			PRIMARY KEY (`invoice_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}

	public function rememberInvoice(string $invoiceId, int $orderId, string $amount, string $currency): void {
		$this->ensureInvoiceTable();
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "coinsnap_invoice` (`invoice_id`, `order_id`, `amount`, `currency`) VALUES ('" . $this->db->escape($invoiceId) . "', " . (int)$orderId . ", '" . $this->db->escape($amount) . "', '" . $this->db->escape($currency) . "')");
	}

	// The recorded invoice, or null when it was not created for this order.
	public function findInvoice(string $invoiceId, int $orderId): ?array {
		$this->ensureInvoiceTable();
		$query = $this->db->query("SELECT `amount`, `currency`, `paid_at` FROM `" . DB_PREFIX . "coinsnap_invoice` WHERE `invoice_id` = '" . $this->db->escape($invoiceId) . "' AND `order_id` = " . (int)$orderId);
		return $query->num_rows ? $query->row : null;
	}

	// Orders placed before 1.3.0 have no rows.
	public function orderHasInvoices(int $orderId): bool {
		$this->ensureInvoiceTable();
		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "coinsnap_invoice` WHERE `order_id` = " . (int)$orderId . " LIMIT 1")->num_rows;
	}

	// True for the first caller only, so two webhooks for one invoice can't both mark the order paid.
	public function claimPaid(string $invoiceId, int $orderId, string $amount, string $currency): bool {
		$this->ensureInvoiceTable();
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "coinsnap_invoice` (`invoice_id`, `order_id`, `amount`, `currency`, `paid_at`) VALUES ('" . $this->db->escape($invoiceId) . "', " . (int)$orderId . ", '" . $this->db->escape($amount) . "', '" . $this->db->escape($currency) . "', NOW())");
		if ($this->db->countAffected() === 1) {
			return true;
		}
		$this->db->query("UPDATE `" . DB_PREFIX . "coinsnap_invoice` SET `paid_at` = NOW() WHERE `invoice_id` = '" . $this->db->escape($invoiceId) . "' AND `order_id` = " . (int)$orderId . " AND `paid_at` IS NULL");
		return $this->db->countAffected() === 1;
	}
}
