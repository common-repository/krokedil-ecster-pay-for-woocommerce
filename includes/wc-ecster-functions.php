<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get localized and formatted payment method name.
 *
 * @param $payment_method
 *
 * @return string
 */
function wc_ecster_get_payment_method_name( $payment_method ) {
	switch ( $payment_method ) {
		case 'INVOICE':
			$payment_method = __( 'Invoice', 'collector-checkout-for-woocommerce' );
			break;
		case 'ACCOUNT':
			$payment_method = __( 'Part payment', 'collector-checkout-for-woocommerce' );
			break;
		case 'CARD':
			$payment_method = __( 'Card payment', 'collector-checkout-for-woocommerce' );
			break;
		default:
			break;
	}
	return $payment_method;
}
