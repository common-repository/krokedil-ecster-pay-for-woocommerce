<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_ecster_settings',
	array(
		'enabled'                         => array(
			'title'       => __( 'Enable/Disable', 'krokedil-ecster-pay-for-woocommerce' ),
			'label'       => __( 'Enable Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'                           => array(
			'title'       => __( 'Title', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => __( 'Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ),
			'desc_tip'    => true,
		),
		'description'                     => array(
			'title'       => __( 'Description', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => __( 'Pay using Ecster Pay.', 'krokedil-ecster-pay-for-woocommerce' ),
			'desc_tip'    => true,
		),
		'testmode'                        => array(
			'title'       => __( 'Test mode', 'krokedil-ecster-pay-for-woocommerce' ),
			'label'       => __( 'Enable Test Mode', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'username'                        => array(
			'title'       => __( 'Live username', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay live account username.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'password'                        => array(
			'title'       => __( 'Live password', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay live account password.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_username'                   => array(
			'title'       => __( 'Test username', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay test account username.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_password'                   => array(
			'title'       => __( 'Test password', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay test account password.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'logging'                         => array(
			'title'       => __( 'Logging', 'krokedil-ecster-pay-for-woocommerce' ),
			'label'       => __( 'Log debug messages', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'select_another_method_text'      => array(
			'title'       => __( 'Other payment method button text', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Ecster Pay. Leave blank to use the default (and translatable) text.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'order_management_settings_title' => array(
			'title' => __( 'Order management settings', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'  => 'title',
		),
		'manage_ecster_orders'            => array(
			'title'   => __( 'Manage orders', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => sprintf( __( 'Enable WooCommerce to manage orders in Ecsters backend (when order status changes to Cancelled and Completed in WooCommerce). Learn more in the <a href="%s" target="_blank">documentation</a>.', 'krokedil-ecster-pay-for-woocommerce' ), 'http://docs.krokedil.com/documentation/ecster-pay-woocommerce/ecster-pay-order-management/' ),
			'default' => 'no',
		),
		'api_key'                         => array(
			'title'       => __( 'API key', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay API key. Used for order management. Can be retrieved from your Ecster Dashboard.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'merchant_key'                    => array(
			'title'       => __( 'Merchant key', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay Merchant key. Used for order management. Can be retrieved from your Ecster Dashboard.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
	)
);
