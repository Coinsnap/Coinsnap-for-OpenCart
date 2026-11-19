<?php
// Run from the repository root: php tests/InvoiceDataTest.php
// Captures the create-invoice request (orderId + metadata) sent through the bundled library.
// Runs on the oldest supported PHP (7.4) as well as current PHP.
if (PHP_SAPI !== 'cli') {
    exit;
}
define('COINSNAP_SERVER_PATH', 'stores');
require __DIR__ . '/../upload/library/loader.php';
require __DIR__ . '/../upload/system/library/coinsnap/InvoiceData.php';

use CoinsnapOpencart\InvoiceData;

$pass = 0;
$fail = 0;
function check($name, $cond)
{
    global $pass, $fail;
    if ($cond) {
        ++$pass;
    } else {
        ++$fail;
        echo "FAIL: $name\n";
    }
}

class RecordingHttpClient implements \Coinsnap\Http\ClientInterface
{
    public $requests = [];

    public function request(string $method, string $url, array $headers = [], string $body = ''): \Coinsnap\Http\ResponseInterface
    {
        $this->requests[] = ['url' => $url, 'body' => json_decode($body, true)];
        return new \Coinsnap\Http\Response(200, '{"id":"inv_1","checkoutLink":"https://pay.example/i/inv_1"}', []);
    }
}

// Rows as OpenCart 4.0.2's model_checkout_order returns them (values HTML-escaped, amounts in the default currency).
function order($withBuyer = true)
{
    return [
        'order_id' => '17',
        'store_name' => 'Your Store',
        'firstname' => $withBuyer ? 'Ada' : '',
        'lastname' => $withBuyer ? 'Lovelace' : '',
        'email' => $withBuyer ? 'ada@example.com' : '',
        'telephone' => $withBuyer ? '+44 20 1234' : '',
        'payment_firstname' => $withBuyer ? 'Ada' : '',
        'payment_lastname' => $withBuyer ? 'Lovelace' : '',
        'payment_company' => $withBuyer ? 'Babbage &amp; Co' : '',
        'payment_address_1' => $withBuyer ? '1 Main St' : '',
        'payment_address_2' => '',
        'payment_city' => $withBuyer ? 'London' : '',
        'payment_postcode' => $withBuyer ? 'N1 1AA' : '',
        'payment_zone' => $withBuyer ? 'Greater London' : '',
        'payment_zone_code' => $withBuyer ? 'LND' : '',
        'payment_iso_code_2' => $withBuyer ? 'GB' : '',
        'comment' => $withBuyer ? 'Leave it &quot;at the door&quot;' : '',
        'shipping_method' => ['name' => 'Flat Shipping Rate', 'code' => 'flat.flat'],
        'currency_code' => 'EUR',
        'currency_value' => '0.5',
    ];
}

$products = [
    ['product_id' => '40', 'name' => 'iPhone', 'model' => 'product 11', 'quantity' => '2', 'price' => '100.0000', 'tax' => '20.0000', 'total' => '200.0000'],
    ['product_id' => '43', 'name' => 'MacBook', 'model' => '', 'quantity' => '1', 'price' => '500.0000', 'tax' => '0.0000', 'total' => '500.0000'],
];
$totals = [
    ['code' => 'sub_total', 'value' => '700.0000'],
    ['code' => 'coupon', 'value' => '-70.0000'],
    ['code' => 'discount', 'value' => '-10.0000'],
    ['code' => 'shipping', 'value' => '5.0000'],
    ['code' => 'tax', 'value' => '40.0000'],
    ['code' => 'total', 'value' => '665.0000'],
];
// Order currency EUR at 0.5 of the default currency.
$convert = function ($value) {
    return $value * 0.5;
};
$extra = [
    'orderUrl' => 'http://shop.example/admin/index.php?route=sale/order.info&order_id=17',
    'platformVersion' => '4.0.2.3',
    'pluginVersion' => '1.3.0',
    'language' => 'en-gb',
    'couponCode' => '2222',
];

foreach (['coinsnap' => 'https://app.coinsnap.io', 'btcpay' => 'https://btcpay.example'] as $provider => $apiUrl) {
    $http = new RecordingHttpClient();
    (new \Coinsnap\Client\Invoice($apiUrl, 'test-key', $http))->createInvoice(
        'store-1', 'EUR', \Coinsnap\Util\PreciseNumber::parseFloat(332.5, 2), '17', 'ada@example.com', 'Ada Lovelace',
        'https://shop.example/index.php?route=checkout/success', 'D19823', InvoiceData::metadata(order(), $products, $totals, $convert, $extra), true, ''
    );
    $body = $http->requests[0]['body'];
    $m = $body['metadata'];

    // The webhook loads the order by these: top-level orderId (Coinsnap), metadata.orderId (BTCPay).
    check("$provider top-level orderId", $body['orderId'] === '17');
    check("$provider metadata.orderId", $m['orderId'] === '17');
    check("$provider buyerEmail", $body['buyerEmail'] === 'ada@example.com');

    $expected = [
        'orderId' => '17',
        'orderNumber' => '17',
        'orderUrl' => 'http://shop.example/admin/index.php?route=sale/order.info&order_id=17',
        'platform' => 'opencart',
        'platformVersion' => '4.0.2.3',
        'pluginVersion' => '1.3.0',
        'storeName' => 'Your Store',
        'customerName' => 'Ada Lovelace',
        'buyerName' => 'Ada Lovelace',
        'buyerEmail' => 'ada@example.com',
        'buyerPhone' => '+44 20 1234',
        'buyerCompany' => 'Babbage & Co',
        'buyerAddress1' => '1 Main St',
        'buyerCity' => 'London',
        'buyerZip' => 'N1 1AA',
        'buyerState' => 'LND',
        'buyerCountry' => 'GB',
        'buyerMessage' => 'Leave it "at the door"',
        'itemDesc' => '2 x iPhone, 1 x MacBook',
        'itemCode' => 'product 11',
        'lineItems' => [
            ['name' => 'iPhone', 'sku' => 'product 11', 'productId' => '40', 'quantity' => 2, 'price' => 60, 'total' => 120],
            ['name' => 'MacBook', 'productId' => '43', 'quantity' => 1, 'price' => 250, 'total' => 250],
        ],
        'couponCodes' => '2222',
        'discountTotal' => 40,
        'shippingMethod' => 'Flat Shipping Rate',
        'shippingTotal' => 2.5,
        'taxTotal' => 20,
        'language' => 'en-gb',
    ];
    foreach ($expected as $key => $value) {
        check("$provider metadata.$key", array_key_exists($key, $m) && $m[$key] == $value);
    }
    check("$provider no other keys", array_diff(array_keys($m), array_keys($expected)) === []);
}

// Guest without data: no empty values, order still identified. Pre-4.0.2 string shipping_method.
$o = order(false);
$o['shipping_method'] = 'Free Shipping';
$m = InvoiceData::metadata($o, [], [], $convert, []);
$empty = array_filter($m, function ($v) {
    return $v === '' || $v === null || $v === [];
});
check('guest: no empty values', $empty === []);
check('guest: no buyer keys', preg_grep('/^(buyer|customerName)/', array_keys($m)) === []);
check('guest: orderId', $m['orderId'] === '17' && $m['itemDesc'] === 'Order 17');
check('guest: string shipping method', $m['shippingMethod'] === 'Free Shipping');
check('guest: zero totals kept', $m['discountTotal'] === 0.0 && $m['taxTotal'] === 0.0);

echo "PHP " . PHP_VERSION . ": $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
