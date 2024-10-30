<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_Order_Management class.
 */
class WC_Ecster_Order_Management {

	/** @var string Ecster API username. */
	private $username;

	/** @var string Ecster API password. */
	private $password;

	/** @var boolean Ecster API testmode. */
	private $testmode;

	/**
	 * WC_Ecster_Order_Management constructor.
	 */
	public function __construct() {
		$ecster_settings 		= get_option( 'woocommerce_ecster_settings' );
		$this->testmode  		= 'yes' === $ecster_settings['testmode'];
		$this->manage_orders 	= isset( $ecster_settings['manage_ecster_orders'] ) ? $ecster_settings['manage_ecster_orders'] : '';
		$this->username  		= $this->testmode ? $ecster_settings['test_username'] : $ecster_settings['username'];
		$this->password  		= $this->testmode ? $ecster_settings['test_password'] : $ecster_settings['password'];
		$this->api_key 			= isset( $ecster_settings['api_key'] ) ? $ecster_settings['api_key'] : '';
		$this->merchant_key 	= isset( $ecster_settings['merchant_key'] ) ? $ecster_settings['merchant_key'] : '';

		add_action( 'woocommerce_order_status_completed', array( $this, 'complete_ecster_order' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_ecster_order' ) );
	}

	/**
	 * Cancel order.
	 */
	public function cancel_ecster_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( 'ecster' === $order->get_payment_method() && 'yes' == $this->manage_orders && !empty( $this->api_key ) && !empty( $this->merchant_key ) ) {
			if ( get_post_meta( $order_id, '_ecster_order_cancelled_id', true ) ) {
				$order->add_order_note( __( 'Ecster reservation is already cancelled.', 'krokedil-ecster-pay-for-woocommerce' ) );
				return;
			}
			$request  = new WC_Ecster_Request_Annul_Order( $this->username, $this->password, $this->testmode, $this->api_key, $this->merchant_key );
			$response = $request->response( $order_id );
			$decoded  = json_decode( $response['body'] );

			if ( 201 == $response['response']['code'] ) {
				if ( 'ANNULLED' == $decoded->orderStatus ) {
					// Add transaction id, used to prevent duplicate cancellations for the same order.
					update_post_meta( $order_id, '_ecster_order_cancelled_id', $decoded->transaction->id );
					$order->add_order_note( sprintf( __( 'Ecster payment was successfully cancelled (transaction id %s).', 'krokedil-ecster-pay-for-woocommerce' ), $decoded->transaction->id ) );
				}
			} else {
				if( $decoded->message ) {
					$error_message = $decoded->message;
					$error_code = $decoded->code;
				} else {
					$error_message = $response['response']['message'];
					$error_code = $response['response']['code'];
				}
				$order->add_order_note( sprintf( __( 'Ecster annul request failed. Error code: %s. Error message: %s', 'krokedil-ecster-pay-for-woocommerce' ), $error_code,  $error_message) );
			}
		}
	}

	/**
	 * Complete order.
	 */
	public function complete_ecster_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( 'ecster' === $order->get_payment_method() && 'yes' == $this->manage_orders  && !empty( $this->api_key ) && !empty( $this->merchant_key ) ) {
			if ( get_post_meta( $order_id, '_ecster_order_captured_id', true ) ) {
				$order->add_order_note( __( 'Ecster reservation is already captured.', 'krokedil-ecster-pay-for-woocommerce' ) );
				return;
			}
			$request  = new WC_Ecster_Request_Debit_Order( $this->username, $this->password, $this->testmode, $this->api_key, $this->merchant_key );
			$response = $request->response( $order_id );
			$decoded  = json_decode( $response['body'] );
			
			if ( 201 == $response['response']['code'] ) {
				if ( 'FULLY_DELIVERED' == $decoded->orderStatus ) {
					// Add transaction id, used to prevent duplicate cancellations for the same order.
					update_post_meta( $order_id, '_ecster_order_captured_id', $decoded->transaction->id );
					$order->add_order_note( sprintf( __( 'Ecster payment debited (transaction id %s).', 'krokedil-ecster-pay-for-woocommerce' ), $decoded->transaction->id ) );
				} else {
					if( $decoded->transaction->id ) {
						update_post_meta( $order_id, '_ecster_order_captured_id', $decoded->transaction->id );
					}
					$order->add_order_note( sprintf( __( 'Ecster debit request problem. Ecster order status: %s', 'krokedil-ecster-pay-for-woocommerce' ), $decoded->orderStatus ) );
					$order->update_status( apply_filters( 'ecster_failed_capture_status', 'on-hold', $order_id ) );
				}
			} else {
				if( $decoded->message ) {
					$error_message = $decoded->message;
					$error_code = $decoded->code;
				} else {
					$error_message = $response['response']['message'];
					$error_code = $response['response']['code'];
				}
				$order->add_order_note( sprintf( __( 'Ecster debit request failed. Error code: %s. Error message: %s', 'krokedil-ecster-pay-for-woocommerce' ), $error_code,  $error_message) );
				
				// Maybe change order status to On hold. 
				// Don't change it if status code is 4424 (order is not in a valid state). 
				// This could indicate that the order already has been activated in Ecters backend.
				if( 4424 !== $decoded->code ) {
					$order->update_status( apply_filters( 'ecster_failed_capture_status', 'on-hold', $order_id ) );
				}
			}
	
		}
	}
	

}
$wc_ecster_order_management = new WC_Ecster_Order_Management;
