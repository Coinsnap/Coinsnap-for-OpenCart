<?php

$_['heading_title']		= 'Coinsnap';

// Text
$_['text_payment']		= 'Payment';
$_['text_success']		= 'Coinsnap configuration is saved.';
$_['text_incomplete']	= 'Coinsnap configuration is incomplete ';
$_['text_all_zones']	= 'All zones';
$_['text_extension']        = 'Extensions';

$_['text_order_amount'] = 'Min order amount is';
$_['error_connection'] = 'API connection is not established';
$_['error_no_payment_method'] = 'No payment method is configured';

// Entry
$_['entry_geo_zone']     		= 'Geo Zone';
$_['entry_status']       		= 'Status';
$_['entry_sort_order']   		= 'Sort order';
$_['entry_method_name']   		= 'Method name';
$_['entry_coinsnap_status'] = 'Module status';

$_['entry_provider']        = 'Provider';

$_['entry_store_id']  			= 'Coinsnap Store ID';
$_['entry_api_key']   			= 'Coinsnap API Key';

$_['entry_btcpay_server_url']  			= 'BTCPay server URL';
$_['entry_btcpay_store_id']  			= 'BTCPay Store ID';
$_['entry_btcpay_api_key']  			= 'BTCPay API Key';

$_['entry_autoredirect']    = 'Redirect after payment';
$_['entry_returnurl']       = 'Return URL after payment';

$_['entry_new_status']     = 'New Status';
$_['entry_expired_status']     = 'Expired Status';
$_['entry_settled_status']     = 'Settled Status';
$_['entry_processing_status']     = 'Processing Status';
$_['error_webhook']     = 'Unable to register Webhook URL, check Store ID and API Key';
$_['coinsnap_logo']        = '<a href="http://coinsnap.io" target="_blank"><img src="../extension/coinsnap/admin/view/image/logo.png" alt="coinsnap" title="coinsnap"  /></a>';
$_['coinsnap_description']  = 'Coinsnap - Bitcoin + Lightning Payments';
$_['text_edit']             = 'Edit Coinsnap Payment Extension';




$_['fieldset_coinsnap_module']			= 'Settings';

// Help
$_['help_geo_zone']     	= '';
$_['help_coinsnap_status']   = '';
$_['help_sort_order']   	= '';
$_['help_method_name']   	= 'Name of the method displayed to the customer at payment method selection (checkout step 5)';

$_['help_store_id']  	= 'Enter Store ID Given by Coinsnap';
$_['help_api_key']   	= 'Enter API Key Given by Coinsnap';

$_['help_btcpay_server_url']   	= 'Enter BTCPay server URL';
$_['help_btcpay_store_id']  	= 'Enter Store ID on BTCPay server';
$_['help_btcpay_api_key']   	= 'Enter API Key for BTCPay server';

$_['help_returnurl']   = 'Custom return URL after successful payment (default URL if blank)';

// Error
$_['error_permission']				= 'You do not have permission to modify Coinsnap payment module';

$_['required_method_name']			= $_['entry_method_name'].' required';
$_['required_store_id']			= $_['entry_store_id'].' required';
$_['required_api_key']			= $_['entry_api_key'].' required';

$_['required_btcpay_server_url']			= $_['entry_btcpay_server_url'].' required';
$_['required_btcpay_store_id']			= $_['entry_btcpay_store_id'].' required';
$_['required_btcpay_api_key']			= $_['entry_btcpay_api_key'].' required';

$_['error_config']					= 'Your Coinsnap configuration is wrong';


?>