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

	private const RECORDS_SINCE_CODE = 'coinsnap_invoice_records';
	private const RECORDS_SINCE_KEY = 'coinsnap_invoice_records_since';

	private static bool $tableReady = false;

	// Invoices this extension created: the order each pays for, the amount and currency sent, and whether it was applied.
	// When the table is first created, the time is saved: orders from before it have no records (see isLegacyOrder()).
	public function ensureInvoiceTable(): void {
		if (self::$tableReady) {
			return;
		}
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "coinsnap_invoice` (
			`invoice_id` VARCHAR(64) NOT NULL,
			`order_id` INT UNSIGNED NOT NULL,
			`amount` VARCHAR(32) NOT NULL,
			`currency` VARCHAR(8) NOT NULL,
			`paid_at` DATETIME NULL,
			PRIMARY KEY (`invoice_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		// Own setting code, so saving the payment settings (which rewrites every payment_coinsnap key) keeps it.
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`store_id`, `code`, `key`, `value`, `serialized`)
			SELECT 0, '" . self::RECORDS_SINCE_CODE . "', '" . self::RECORDS_SINCE_KEY . "', NOW(), 0 FROM DUAL
			WHERE NOT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "setting` WHERE `store_id` = 0 AND `key` = '" . self::RECORDS_SINCE_KEY . "')");
		self::$tableReady = true;
	}

	// An order placed before this version kept invoice records, while the one-day window after the upgrade is open.
	public function isLegacyOrder(int $orderId): bool {
		$this->ensureInvoiceTable();
		$query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "order` o
			JOIN `" . DB_PREFIX . "setting` s ON s.`store_id` = 0 AND s.`key` = '" . self::RECORDS_SINCE_KEY . "'
			WHERE o.`order_id` = " . (int)$orderId . "
			AND o.`date_added` < s.`value`
			AND NOW() < DATE_ADD(s.`value`, INTERVAL 1 DAY)");
		return (bool)$query->num_rows;
	}

	// Whether the order's current status came from this extension (its newest history entry is ours).
	// Anything else was set by the merchant and is not changed by Processing or Expired webhooks.
	public function statusSetByExtension(int $orderId, int $statusId): bool {
		$query = $this->db->query("SELECT `order_status_id`, `comment` FROM `" . DB_PREFIX . "order_history` WHERE `order_id` = " . (int)$orderId . " ORDER BY `order_history_id` DESC LIMIT 1");
		if (!$query->num_rows || (int)$query->row['order_status_id'] !== $statusId) {
			return false;
		}
		$comment = (string)$query->row['comment'];
		// 'New' is what versions before 1.3.0 wrote when the invoice was created.
		return $comment === 'New' || strpos($comment, 'Coinsnap Invoice ID: ') === 0;
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

	// Whether any invoice has already paid this order.
	public function orderIsPaid(int $orderId): bool {
		$this->ensureInvoiceTable();
		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "coinsnap_invoice` WHERE `order_id` = " . (int)$orderId . " AND `paid_at` IS NOT NULL LIMIT 1")->num_rows;
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
