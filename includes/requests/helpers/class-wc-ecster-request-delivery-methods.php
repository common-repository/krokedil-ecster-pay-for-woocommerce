<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats API request delivery methods for Ecster.
 *
 * @since 1.0
 * @return array
 */
class WC_Ecster_Request_Delivery_Methods {

	/**
	 * Returns WooCommerce delivery methods formatted for Ecster Pay's create cart and update cart.
	 *
	 * @return array
	 */
	public static function delivery_methods() {
		if ( WC()->cart->needs_shipping() ) {
			WC()->cart->calculate_shipping();
			$ecster_delivery_methods = array();
			$packages = WC()->shipping->get_packages();

			foreach ( $packages as $i => $package ) {
				$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
				foreach ( $package['rates'] as $method ) {

					$method_id                 = $method->id;
					$method_name               = $method->label;
					$method_price              = intval( round( $method->cost + array_sum( $method->taxes ), 2 ) * 100 );
					$method_selected           = $method->id === $chosen_method ? true : false;

					$ecster_delivery_methods[] = array(
						'id'       => $method_id,
						'name'     => $method_name,
						'price'    => $method_price,
						'selected' => $method_selected,
					);
				}
			}

			return apply_filters( 'wc_ecster_delivery_methods', $ecster_delivery_methods );
		} else {
			return array();
		}
	}

}