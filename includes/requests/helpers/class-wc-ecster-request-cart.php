<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats API request cart for Ecster.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Cart {

	/**
	 * Return WooCommerce cart formatted for Ecster Pay's create cart and update cart requests.
	 * @return array
	 */
	public static function cart() {
		$ecster_cart        = array();
		$ecster_cart_rows   = array();
		$ecster_cart_amount = 0;

		// Cart items, description is commented out, because if one item misses description the iframe gets confused.
		foreach ( WC()->cart->cart_contents as $item ) {
			if ( $item['variation_id'] ) {
				$product = wc_get_product( $item['variation_id'] );
				$product_id = $item['variation_id'];
			} else {
				$product = wc_get_product( $item['product_id'] );
				$product_id = $item['product_id'];
			}
			

			$ecster_cart_rows[] = array(
				'partNumber'  => self::product_part_number( $product ),
				'name'        => self::product_name( $product, $item ), // Mandatory.
				'quantity'    => $item['quantity'],                     // Mandatory
				'unitPrice'   => self::product_unit_price( $item ),     // Mandatory.
				'unit'        => ' ',
				'vatCode'     => self::product_vat_code( $item ),       // Mandatory.
				'discount'    => self::product_discount( $item ),
				// 'description' => '',
				// 'serials'     => ''
			);
			$ecster_cart_amount += self::product_unit_price( $item ) * $item['quantity'] - self::product_discount( $item );
		}

		// Fees.
		if ( WC()->cart->get_fees() ) {
			foreach ( WC()->cart->get_fees() as $fee ) {
				// @TODO: Throw an error if tax rate is not in the array
				$fee_tax_rate = round( $fee->tax / $fee->amount * 100 );
				if ( in_array( $fee_tax_rate, array( 0, 6, 12, 25 ) ) ) {
					$fee_vat_code = $fee_tax_rate . '%';
				} else {
					$fee_vat_code = '0%';
				}
				$ecster_cart_rows[] = array(
					'name'        => $fee->name,
					'description' => $fee->id,
					'quantity'    => 1,
					'unitPrice'   => ( $fee->amount + $fee->tax ) * 100,
					'unit'        => ' ',
					'vatCode'     => $fee_vat_code,
					'discount'    => 0,
				);
				$ecster_cart_amount += ( $fee->amount + $fee->tax ) * 100;
			}
		}

		$ecster_cart['externalReference'] = self::external_reference();
		$ecster_cart['rows']              = $ecster_cart_rows;
		$ecster_cart['amount']            = $ecster_cart_amount;
		$ecster_cart['currency']          = get_woocommerce_currency();

		return apply_filters( 'wc_ecster_cart', $ecster_cart );
	}

	/**
	 * Returns product SKU or ID
	 *
	 * @param $product
	 *
	 * @return string 32 characters max
	 */
	private static function product_name( $product, $item ) {
		if( krokedil_wc_gte_3_0() ) {
			$product_name = $product->get_name();
		} else {
			$product_name = $product->get_title();
			if ( ! empty( $item['variation'] ) ) {
				foreach ( $item['variation'] as $variation) {
					$product_name .= " [$variation]";
				}
			}
		}

		return $product_name;
	}

	/**
	 * Returns product SKU or ID
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string 32 characters max.
	 */
	private static function product_part_number( $product ) {
		if ( $product->get_sku() ) {
			$part_number = $product->get_sku();
		} elseif ( krokedil_get_variation_id( $product ) ) {
			$part_number = krokedil_get_variation_id( $product );
		} else {
			$part_number = krokedil_get_product_id( $product );
		}

		return substr( $part_number, 0, 32 );
	}

	/**
	 * @param $cart_item
	 *
	 * @return string 128 characters max
	 */
	private static function product_description( $cart_item ) {

	}

	/**
	 * @param $cart_item
	 *
	 * @return mixed
	 */
	private static function product_quantity( $cart_item ) {
		return $cart_item['quantity'];
	}

	/**
	 * @param $cart_item
	 *
	 * @return float
	 */
	private static function product_unit_price( $cart_item ) {
		return round( ( $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] ) / $cart_item['quantity'] * 100 );
	}

	/**
	 * @param $cart_item
	 *
	 * @return string
	 */
	private static function product_unit( $cart_item ) {
		return ' ';
	}

	/**
	 * @param $cart_item
	 *
	 * @TODO: Add tax rates for other countries once they are available.
	 * @return string|WP_Error
	 */
	private static function product_vat_code( $cart_item ) {
		$tax_rate = round( $cart_item['line_subtotal_tax'] / $cart_item['line_subtotal'] * 100 );
		if ( in_array( $tax_rate, array( 0, 6, 12, 25 ) ) ) {
			return $tax_rate . '%';
		} else {
			WC_Gateway_Ecster::log( 'Invalid tax rate used in WC_Ecster_Request_Cart::product_vat_code() (can only be 0%, 6%, 12% or 25%), using 0% instead' );

			return '0%';
		}
	}

	/**
	 * @param $cart_item
	 *
	 * @return mixed
	 */
	private static function product_discount( $cart_item ) {
		return round( ( $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'] - $cart_item['line_total'] - $cart_item['line_tax'] ) * 100 );
	}

	/**
	 * Grabs ID for order awaiting payment
	 *
	 * If the order doesn't yet exist, an empty string is used.
	 *
	 * @return string
	 */
	private static function external_reference() {
		if ( WC()->session->get( 'order_awaiting_payment' ) > 0 ) {
			$ongoing_order = wc_get_order( WC()->session->get( 'order_awaiting_payment' ) );
			if ( $ongoing_order ) {
				$external_reference = $ongoing_order->get_order_number();
			} else {
				$external_reference = WC()->session->get( 'order_awaiting_payment' );
			}
		} else {
			$external_reference = substr( md5( json_encode( WC()->cart->get_cart_for_session() ) . time() ), 0, 15 );
		}

		return $external_reference;
	}

}