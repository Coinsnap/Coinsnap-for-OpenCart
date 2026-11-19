<?php
declare(strict_types=1);

namespace CoinsnapOpencart;

/**
 * The metadata sent with a new Coinsnap or BTCPay invoice, built from OpenCart's order, order products
 * and order totals, so the merchant can find the order from the invoice.
 *
 * orderId stays the OpenCart order ID: the webhook loads the order by it.
 */
class InvoiceData
{
    private const MAX_ITEM_DESC = 255;
    // Order total codes that lower the price; their values are negative.
    private const DISCOUNT_TOTALS = ['coupon', 'voucher', 'reward', 'discount'];

    /**
     * @param array    $order    model_checkout_order->getOrder()
     * @param array    $products model_checkout_order->getProducts()
     * @param array    $totals   model_checkout_order->getTotals()
     * @param callable $convert  amount in the default currency => amount in the order currency
     * @param array    $extra    orderUrl, pluginVersion, platformVersion, language, couponCode
     */
    public static function metadata(array $order, array $products, array $totals, callable $convert, array $extra = []): array
    {
        $orderId = (string) (int) ($order['order_id'] ?? 0);

        $name = self::join([self::text($order, 'payment_firstname'), self::text($order, 'payment_lastname')]);
        if ($name === '') {
            $name = self::join([self::text($order, 'firstname'), self::text($order, 'lastname')]);
        }

        $lineItems = [];
        $names = [];
        $skus = [];
        foreach ($products as $product) {
            $quantity = (int) ($product['quantity'] ?? 0);
            $unit = (float) ($product['price'] ?? 0) + (float) ($product['tax'] ?? 0);
            $item = self::withoutEmpty([
                'name' => self::text($product, 'name'),
                'sku' => self::text($product, 'model'),
                'productId' => self::text($product, 'product_id'),
                'quantity' => $quantity,
                'price' => self::amount($convert($unit)),
                'total' => self::amount($convert($unit * $quantity)),
            ]);
            $lineItems[] = $item;
            $names[] = $quantity . ' x ' . ($item['name'] ?? '');
            if (isset($item['sku'])) {
                $skus[] = $item['sku'];
            }
        }

        $shipping = 0.0;
        $tax = 0.0;
        $discount = 0.0;
        foreach ($totals as $total) {
            $code = (string) ($total['code'] ?? '');
            $value = (float) ($total['value'] ?? 0);
            if ($code === 'shipping') {
                $shipping += $value;
            } elseif ($code === 'tax') {
                $tax += $value;
            } elseif (in_array($code, self::DISCOUNT_TOTALS, true)) {
                $discount += abs($value);
            }
        }

        // shipping_method is an array since OpenCart 4.0.2, a string before.
        $shippingMethod = $order['shipping_method'] ?? '';
        $shippingMethod = is_array($shippingMethod) ? (string) ($shippingMethod['name'] ?? '') : (string) $shippingMethod;

        return self::withoutEmpty([
            'orderId' => $orderId,
            'orderNumber' => $orderId,
            'orderUrl' => self::text($extra, 'orderUrl'),
            'platform' => 'opencart',
            'platformVersion' => self::text($extra, 'platformVersion'),
            'pluginVersion' => self::text($extra, 'pluginVersion'),
            'storeName' => self::text($order, 'store_name'),
            'customerName' => $name,
            'buyerName' => $name,
            'buyerEmail' => self::text($order, 'email'),
            'buyerPhone' => self::text($order, 'telephone'),
            'buyerCompany' => self::text($order, 'payment_company'),
            'buyerAddress1' => self::text($order, 'payment_address_1'),
            'buyerAddress2' => self::text($order, 'payment_address_2'),
            'buyerCity' => self::text($order, 'payment_city'),
            'buyerZip' => self::text($order, 'payment_postcode'),
            'buyerState' => self::text($order, 'payment_zone_code') !== '' ? self::text($order, 'payment_zone_code') : self::text($order, 'payment_zone'),
            'buyerCountry' => self::text($order, 'payment_iso_code_2'),
            'buyerMessage' => self::text($order, 'comment'),
            'itemDesc' => self::truncate($names !== [] ? implode(', ', $names) : 'Order ' . $orderId),
            'itemCode' => implode(', ', array_values(array_unique($skus))),
            'lineItems' => $lineItems,
            'couponCodes' => self::text($extra, 'couponCode'),
            'discountTotal' => self::amount($convert($discount)),
            'shippingMethod' => trim($shippingMethod),
            'shippingTotal' => self::amount($convert($shipping)),
            'taxTotal' => self::amount($convert($tax)),
            'language' => self::text($extra, 'language'),
        ]);
    }

    private static function text(array $values, string $key): string
    {
        return isset($values[$key]) && is_scalar($values[$key]) ? trim(html_entity_decode((string) $values[$key], ENT_QUOTES, 'UTF-8')) : '';
    }

    private static function join(array $parts): string
    {
        return trim(implode(' ', array_filter($parts, 'strlen')));
    }

    private static function amount($value): float
    {
        return round((float) $value, 2);
    }

    // Empty strings, nulls and empty lists are left out; zero amounts are kept.
    private static function withoutEmpty(array $values): array
    {
        return array_filter($values, function ($value) {
            return $value !== null && $value !== '' && $value !== [];
        });
    }

    private static function truncate(string $text): string
    {
        return mb_strlen($text) > self::MAX_ITEM_DESC ? mb_substr($text, 0, self::MAX_ITEM_DESC - 3) . '...' : $text;
    }
}
