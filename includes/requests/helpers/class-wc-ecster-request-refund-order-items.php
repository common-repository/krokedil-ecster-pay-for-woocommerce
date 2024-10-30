<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Gets Order Items.
 *
 * @class    WC_Ecster_Get_Refund_Order_Items
 * @package  Ecster/Classes/Requests/Helpers
 * @category Class
 * @author   Krokedil <info@krokedil.se>
 */
class WC_Ecster_Get_Refund_Order_Items {
	/**
	 * Gets items.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public static function get_items( $order_id, $amount, $reason ) {

		$refund_id = self::get_refunded_order_id( $order_id );
		if ( '' === $reason ) {
			$reason = '';
		} else {
			$reason = ' (' . $reason . ')';
		}

		$order       = wc_get_order( $order_id );
		$line_number = 0;
		$items       = array();

		if ( null !== $refund_id ) {
			$refund_order   = wc_get_order( $refund_id );
			$refunded_items = $refund_order->get_items();

			if ( $refunded_items || $refund_order->get_shipping_total() < 0 || ! empty( $refund_order->get_fees() ) ) {
				// Cart.
				foreach ( $refunded_items as $item ) {
					$formated_item = self::get_item( $item );
					array_push( $items, $formated_item );
				}
				// Shipping.
				if ( $refund_order->get_shipping_total() < 0 ) {
					$formated_shipping = self::get_shipping( $refund_order );
					array_push( $items, $formated_shipping );
				}
				// Fees.
				foreach ( $refund_order->get_fees() as $fee ) {
					$formated_fee = self::get_fee( $fee );
					array_push( $items, $formated_fee );
				}
			} else {
				$formated_item = array(
					'partNumber' => 'ref1',
					'name'       => 'Refund #' . $refund_id . $reason,
					'unitAmount' => round( $amount * 100 ),
					'vatRate'    => 0,
					'quantity'   => 1,
				);
				array_push( $items, $formated_item );
			}
			update_post_meta( $refund_id, '_krokedil_refunded', 'true' );
		} else {
			// Log empty response?
		}

		return $items;
	}

	/**
	 * Gets single item.
	 *
	 * @param array $item
	 * @return array
	 */
	private static function get_item( $item ) {
		$product = $item->get_product();

		if ( $item['variation_id'] ) {
			$product_id = $item['variation_id'];
		} else {
			$product_id = $item['product_id'];
		}
		return array(
			'partNumber' => self::get_sku( $product, $product_id ),
			'name'       => $product->get_name(),
			'unitAmount' => round( ( $item->get_total() + $item->get_total_tax() ) / $item['qty'] * 100 ),
			'vatRate'    => self::product_vat_rate( $item ),
			'quantity'   => abs( $item['qty'] ),
		);
	}

	/**
	 * Gets shipping
	 *
	 * @param string $shipping_method
	 * @param int    $line_number
	 * @return array
	 */
	private static function get_shipping( $refund_order ) {

		return array(
			'partNumber' => 'Shipping',
			'name'       => $refund_order->get_shipping_method(),
			'unitAmount' => round( ( abs( $refund_order->get_shipping_total() + $refund_order->get_shipping_tax() ) ) * 100 ),
			'vatRate'    => self::get_shipping_vat_rate( $refund_order ),
			'quantity'   => 1,
		);
	}

	/**
	 * Gets order Fee.
	 *
	 * @param array $fee
	 * @param int   $line_number
	 * @return array
	 */
	private static function get_fee( $fee ) {
		if ( $fee->tax ) {
			$fee_tax_rate = round( abs( $fee->tax ) / abs( $fee->amount ) * 100 );
			if ( in_array( $fee_tax_rate, array( 0, 6, 12, 25 ) ) ) {
				$fee_vat_code = $fee_tax_rate * 100;
			} else {
				$fee_vat_code = 0;
			}
		} else {
			$fee_vat_code = 0;
		}

		return array(
			'name'        => $fee->get_name(),
			'description' => $fee->get_id(),
			'unitAmount'  => round( abs( $fee->get_total() + $fee->get_total_tax() ) * 100 ),
			'vatRate'     => $fee_vat_code,
			'quantity'    => 1,
		);
	}

	/**
	 * Calculates tax
	 *
	 * @param array $product
	 * @return int
	 */
	private static function calculate_tax( $product ) {
		$price_incl_tax   = wc_get_price_including_tax( $product );
		$price_excl_tax   = wc_get_price_excluding_tax( $product );
		$price_difference = $price_incl_tax - $price_excl_tax;
		$tax_percent      = intval( ( $price_difference / $price_excl_tax ) * 100 );

		return $tax_percent;
	}

	/**
	 * Gets SKU
	 *
	 * @param array $product
	 * @param int   $product_id
	 * @return string
	 */
	private static function get_sku( $product, $product_id ) {
		if ( get_post_meta( $product_id, '_sku', true ) !== '' ) {
			$part_number = $product->get_sku();
		} else {
			$part_number = $product->get_id();
		}
		return substr( $part_number, 0, 32 );
	}

	/**
	 * @param $cart_item
	 *
	 * @TODO: Add tax rates for other countries once they are available.
	 * @return string|WP_Error
	 */
	private static function product_vat_rate( $item ) {
		$tax_rate = round( $item['line_subtotal_tax'] / $item['line_subtotal'] * 100 );
		if ( in_array( $tax_rate, array( 0, 6, 12, 25 ) ) ) {
			return $tax_rate * 100;
		} else {
			WC_Gateway_Ecster::log( 'Invalid tax rate used in WC_Ecster_Request_Cart::product_vat_code() (can only be 0%, 6%, 12% or 25%), using 0% instead' );

			return 0;
		}
	}

	/**
	 * @param $refund_order
	 *
	 * @TODO: Add tax rates for other countries once they are available.
	 * @return string|WP_Error
	 */
	private static function get_shipping_vat_rate( $refund_order ) {
		$tax_rate = round( abs( $refund_order->get_total_tax() ) / abs( $refund_order->get_total() ) * 100 );

		if ( in_array( $tax_rate, array( 0, 6, 12, 25 ) ) ) {
			return $tax_rate * 100;
		} else {
			WC_Gateway_Ecster::log( 'Invalid tax rate used in WC_Ecster_Get_Refund_Order_Items::shipping_vat_rate() (can only be 0%, 6%, 12% or 25%), using 0% instead' );

			return 0;
		}
	}

	/**
	 * Gets refunded order
	 *
	 * @param int $order_id
	 * @return string
	 */
	public static function get_refunded_order_id( $order_id ) {
		$query_args = array(
			'fields'         => 'id=>parent',
			'post_type'      => 'shop_order_refund',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);
		$refunds    = get_posts( $query_args );
		$refund_id  = array_search( $order_id, $refunds );
		if ( is_array( $refund_id ) ) {
			foreach ( $refund_id as $key => $value ) {
				if ( ! get_post_meta( $value, '_krokedil_refunded' ) ) {
					$refund_id = $value;
					break;
				}
			}
		}
		return $refund_id;
	}
}
