<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Gets Order Items.
 * 
 * @class    WC_Ecster_Get_Order_Items
 * @package  Ecster/Classes/Requests/Helpers
 * @category Class
 * @author   Krokedil <info@krokedil.se>
 */
class WC_Ecster_Get_Order_Items {
    /**
     * Gets items.
     *
     * @param int $order_id
     * @return array
     */
    public static function get_items( $order_id ) {
        $order = wc_get_order( $order_id );
        $line_number = 0;
        $items = array();
        foreach( $order->get_items() as $item ) {
            $line_number = $line_number + 1;
            $formated_item = self::get_item( $item, $line_number );
            array_push( $items, $formated_item );
		}
		
        foreach( $order->get_shipping_methods() as $shipping_method ) {
            $line_number = $line_number + 1;
            $formated_shipping = self::get_shipping( $shipping_method, $line_number );
            array_push( $items, $formated_shipping );
		}
		
        foreach( $order->get_fees() as $fee ) {
            $line_number = $line_number + 1;
            $formated_fee = self::get_fee( $fee, $line_number );
            array_push( $items, $formated_fee );
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
            'partNumber'	=>  self::get_sku( $product, $product_id ),
            'name'          =>  $product->get_name(),
            'unitAmount'	=>  round( ( $item->get_total() + $item->get_total_tax() ) / $item['qty'] * 100 ),
            'vatRate'   	=>  self::product_vat_rate( $item ),
            'quantity'		=>  $item['qty'],
        );
    }

    /**
     * Gets shipping
     *
     * @param string $shipping_method
     * @param int $line_number
     * @return array
     */
    private static function get_shipping( $shipping_method, $line_number ) {
        $free_shipping = false;
        if( 0 === intval( $shipping_method->get_total() ) ) {
            $free_shipping = true;
        }

        return array(
            'partNumber'    => 'Shipping',
            'name'          => $shipping_method->get_method_title(),
            'unitAmount'    => ( $free_shipping ) ? 0 : round( ( $shipping_method->get_total() + $shipping_method->get_total_tax() ) * 100 ),
            'vatRate'       => ( $free_shipping ) ? 0 : ( $shipping_method->get_total_tax() / $shipping_method->get_total() ) * 10000,
            'quantity'      => 1,
        );
    }

    /**
     * Gets order Fee.
     *
     * @param array $fee
     * @param int $line_number
     * @return array
     */
    private static function get_fee( $fee, $line_number ) {
		$fee_tax_rate = round( $fee->tax / $fee->amount * 100 );
		if ( in_array( $fee_tax_rate, array( 0, 6, 12, 25 ) ) ) {
			$fee_vat_code = $fee_tax_rate * 100;
		} else {
			$fee_vat_code = 0;
		}

        return array(
			'name'				=>  $fee->get_name(),
			'description'		=>  $fee->get_id(),
            'unitAmount'		=>  ($fee->get_total() + $fee->get_total_tax()) * 100,
            'vatRate'			=>  $fee_vat_code,
            'quantity'			=>  1,
        );
    }

    /**
     * Calculates tax
     *
     * @param array $product
     * @return int
     */
    private static function calculate_tax( $product ) {
        $price_incl_tax = wc_get_price_including_tax( $product );
        $price_excl_tax = wc_get_price_excluding_tax( $product );
        $price_difference = $price_incl_tax - $price_excl_tax;
        $tax_percent = intval( ( $price_difference / $price_excl_tax ) * 100 );

        return $tax_percent;
    }

    /**
     * Gets SKU
     *
     * @param array $product
     * @param int $product_id
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
}