<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_Ajax class.
 */
class WC_Ecster_Ajax {

	/** @var string Ecster API username. */
	private $username;

	/** @var string Ecster API password. */
	private $password;

	/** @var boolean Ecster API testmode. */
	private $testmode;

	/**
	 * WC_Ecster_Ajax constructor.
	 */
	function __construct() {
		$ecster_settings = get_option( 'woocommerce_ecster_settings' );
		$this->testmode  = 'yes' === $ecster_settings['testmode'];
		$this->username  = $this->testmode ? $ecster_settings['test_username'] : $ecster_settings['username'];
		$this->password  = $this->testmode ? $ecster_settings['test_password'] : $ecster_settings['password'];

		add_action( 'wp_ajax_wc_ecster_create_cart', array( $this, 'ajax_create_cart' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_create_cart', array( $this, 'ajax_create_cart' ) );

		add_action( 'wp_ajax_wc_ecster_update_cart', array( $this, 'ajax_update_cart' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_update_cart', array( $this, 'ajax_update_cart' ) );

		add_action( 'wp_ajax_wc_ecster_fail_local_order', array( $this, 'ajax_fail_local_order' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_fail_local_order', array( $this, 'ajax_fail_local_order' ) );

		add_action( 'wp_ajax_wc_ecster_on_customer_authenticated', array( $this, 'ajax_on_customer_authenticated' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_customer_authenticated', array( $this, 'ajax_on_customer_authenticated' ) );

		add_action( 'wp_ajax_wc_ecster_on_changed_delivery_address', array( $this, 'ajax_on_changed_delivery_address' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_changed_delivery_address', array( $this, 'ajax_on_changed_delivery_address' ) );

		add_action( 'wp_ajax_wc_ecster_on_payment_success', array( $this, 'ajax_on_payment_success' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_payment_success', array( $this, 'ajax_on_payment_success' ) );

		add_action( 'wp_ajax_wc_ecster_on_checkout_error', array( $this, 'ajax_on_checkout_error' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_checkout_error', array( $this, 'ajax_on_checkout_error' ) );
	}

	/**
	 * Creates Ecster cart.
	 */
	function ajax_create_cart() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - create_cart.' );
			exit( 'Nonce can not be verified.' );
		}

		$data     = array();
		$request  = new WC_Ecster_Request_Create_Cart( $this->username, $this->password, $this->testmode );
		$response = $request->response();

		if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );
			if ( is_string( $decoded->response->key ) ) {
				$data['wc_ecster_cart_key'] = $decoded->response->key;
				wp_send_json_success( $data );
			}
		} else {
			if ( is_wp_error( $response ) ) {
				$error_title  = $response->get_error_code();
				$error_detail = $response->get_error_message();
			} else {
				$decoded      = json_decode( $response['body'] );
				$error_title  = $decoded->title;
				$error_detail = $decoded->detail;
			}
			WC_Gateway_Ecster::log( 'Ecster create cart ' . $error_title . ': ' . $error_detail );
			$data['error_message'] = __( 'Ecster Pay create cart request failed ' . $error_title . ' (' . $error_detail . ').', 'krokedil-ecster-pay-for-woocommerce' );
			wp_send_json_error( $data );
		}

		wp_die();
	}

	/**
	 * Updates Ecster cart.
	 */
	function ajax_update_cart() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - update_cart.' );
			exit( 'Nonce can not be verified.' );
		}
		$cart_key = $_POST['cart_key'];
		$data     = array();
		$request  = new WC_Ecster_Request_Update_Cart( $this->username, $this->password, $this->testmode );
		$response = $request->response( $cart_key );

		if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );
			if ( is_string( $decoded->response->key ) ) {
				$data['wc_ecster_cart_key'] = $decoded->response->key;
				wp_send_json_success( $data );
			}
		} else {
			if ( is_wp_error( $response ) ) {
				$error_title  = $response->get_error_code();
				$error_detail = $response->get_error_message();
			} else {
				$decoded      = json_decode( $response['body'] );
				$error_title  = $decoded->title;
				$error_detail = $decoded->detail;
			}
			WC_Gateway_Ecster::log( 'Ecster update cart ' . $error_title . ': ' . $error_detail );
			$data['error_message'] = __( 'Ecster Pay update cart request failed ' . $error_title . ' (' . $error_detail . ').', 'krokedil-ecster-pay-for-woocommerce' );
			wp_send_json_error( $data );
		}
		wp_die();
	}

	/**
	 * Fails WooCommerce order.
	 */
	function ajax_fail_local_order() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - fail_local_order.' );
			exit( 'Nonce can not be verified.' );
		}
		$reason = $_POST['reason'];

		if ( WC()->session->get( 'order_awaiting_payment' ) > 0 ) {
			$local_order_id = WC()->session->get( 'order_awaiting_payment' );
			$local_order    = wc_get_order( $local_order_id );

			if ( 'failed' === $reason ) {
				$local_order->add_order_note( __( 'Ecster payment failed', 'krokedil-ecster-pay-for-woocommerce' ) );
			} elseif ( 'denied' === $reason ) {
				$local_order->add_order_note( __( 'Ecster payment denied', 'krokedil-ecster-pay-for-woocommerce' ) );
			}

			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
		wp_die();
	}

	/**
	 * On customer authentication.
	 *
	 * Maybe create local order.
	 * Populate local order.
	 * Populate session customer data.
	 * Calculate cart totals.
	 * Update order cart hash.
	 */
	function ajax_on_customer_authenticated() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) { // Input var okay.
			WC_Gateway_Ecster::log( 'Nonce can not be verified - on_customer_authenticated.' );
			exit( 'Nonce can not be verified.' );
		}

		$must_login         = 'no';
		$must_login_message = apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ) );

		$customer_data  = $_POST['customer_data']; // Input var okay.
		$local_order_id = $this->helper_maybe_create_local_order();
		update_post_meta( $local_order_id, '_wc_ecster_customer_authenticated', true );

		$this->helper_add_items_to_local_order( $local_order_id );
		$this->helper_add_customer_data_to_local_order( $local_order_id, $customer_data, array( 'billing', 'shipping' ) );
		$this->helper_add_customer_data_to_session( $customer_data, array( 'billing', 'shipping' ) );
		$this->helper_calculate_cart_totals();
		// $this->helper_calculate_order_totals( $local_order_id );
		$this->helper_calculate_order_cart_hash( $local_order_id );

		// If customer exist, is not logged and guest checkout isn't enabled - tell the customer to login.
		if ( ! is_user_logged_in() && 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ) {
			if ( email_exists( $customer_data['email'] ) ) {
				// Email exist in a user account, customer must login.
				$must_login = 'yes';
			}
		}

		wp_send_json_success(
			array(
				'local_order_id'   => $local_order_id,
				'mustLogin'        => $must_login,
				'mustLoginMessage' => $must_login_message,
			)
		);
		wp_die();
	}

	/**
	 * On changed delivery address.
	 *
	 * Maybe create local order.
	 * Populate local order.
	 * Populate session customer data.
	 * Calculate cart totals.
	 * Update order cart hash.
	 */
	function ajax_on_changed_delivery_address() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) { // Input var okay.
			WC_Gateway_Ecster::log( 'Nonce can not be verified - on_changed_delivery_address.' );
			exit( 'Nonce can not be verified.' );
		}

		$delivery_address = $_POST['delivery_address']; // Input var okay.

		$local_order_id = $this->helper_maybe_create_local_order();

		if ( get_post_meta( $local_order_id, '_wc_ecster_customer_authenticated', true ) ) {
			$addresses = array( 'shipping' );
		} else {
			$addresses = array( 'billing', 'shipping' );
		}

		$this->helper_add_items_to_local_order( $local_order_id );
		$this->helper_add_customer_data_to_local_order( $local_order_id, $delivery_address, $addresses );
		$this->helper_add_customer_data_to_session( $delivery_address, $addresses );
		$this->helper_calculate_cart_totals();
		$this->helper_calculate_order_totals( $local_order_id );
		$this->helper_calculate_order_cart_hash( $local_order_id );

		wp_send_json_success(
			array(
				'local_order_id' => $local_order_id,
			)
		);
		wp_die();
	}

	/**
	 * On payment success.
	 *
	 * Maybe add invoice fee to order.
	 * Calculate cart totals.
	 * Update order cart hash.
	 */
	function ajax_on_payment_success() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) { // Input var okay.
			WC_Gateway_Ecster::log( 'Nonce can not be verified - on_payment_success.' );
			exit( 'Nonce can not be verified.' );
		}

		$payment_data   = $_POST['payment_data']; // Input var okay.
		$local_order_id = $this->helper_maybe_create_local_order();

		if ( isset( $payment_data['fees'] ) ) {
			$this->helper_add_invoice_fees_to_session( $payment_data['fees'] );
		}

		$this->helper_calculate_order_cart_hash( $local_order_id );

		update_post_meta( $local_order_id, '_wc_ecster_internal_reference', $payment_data['internalReference'] );
		WC_Gateway_Ecster::log( 'On payment success event triggered for order ID ' . $local_order_id . '. Payment data ' . json_encode( $payment_data ) );

		wp_send_json_success(
			array(
				'local_order_id' => $local_order_id,
			)
		);
		wp_die();
	}

	/**
	 * Handles WooCommerce checkout error, after Ecster order has already been created.
	 */
	public function ajax_on_checkout_error() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$local_order_id = $this->helper_maybe_create_local_order();
		$order          = wc_get_order( $local_order_id );

		// Add order fees.
		$this->helper_add_order_fees( $order );

		// Add Ecster invoice fee
		$this->helper_maybe_add_invoice_fee( $order );

		// Add order shipping.
		$this->helper_add_order_shipping( $order );

		// Add order taxes.
		$this->helper_add_order_tax_rows( $order );

		// Store coupons.
		$this->helper_add_order_coupons( $order );

		// Save order totals
		$this->helper_calculate_order_totals( $local_order_id );

		$redirect_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
		$redirect_url = add_query_arg(
			array(
				'ecster-osf' => 'true',
				'order-id'   => $local_order_id,
			),
			$redirect_url
		);

		wp_send_json_success( array( 'redirect' => $redirect_url ) );
		wp_die();
	}


	/**
	 * Helpers.
	 */


	/**
	 * Creates WooCommerce order, if needed.
	 *
	 * @return int $local_order_id WooCommerce order ID.
	 */
	function helper_maybe_create_local_order() {
		if ( WC()->session->get( 'order_awaiting_payment' ) > 0 ) { // Create local order if there already isn't an order awaiting payment.
			$local_order_id = WC()->session->get( 'order_awaiting_payment' );
			$local_order    = wc_get_order( $local_order_id );
			$this->add_order_payment_method( $local_order ); // Store payment method.
			$local_order->update_status( 'pending' ); // If the order was failed in the past, unfail it because customer was successfully authenticated again.
		} else {
			$local_order    = wc_create_order();
			$local_order_id = $local_order->id;
			$this->add_order_payment_method( $local_order ); // Store payment method.
			WC()->session->set( 'order_awaiting_payment', $local_order_id );
			do_action( 'woocommerce_checkout_update_order_meta', $local_order_id, array() ); // Let plugins add their own meta data.
		}

		return $local_order_id;
	}

	/**
	 * Adds order items to ongoing order.
	 *
	 * @param  integer $local_order_id WooCommerce order ID.
	 * @throws Exception PHP Exception.
	 */
	function helper_add_items_to_local_order( $local_order_id ) {
		$local_order = wc_get_order( $local_order_id );
		// Remove items as to stop the item lines from being duplicated.
		$local_order->remove_order_items();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) { // Store the line items to the new/resumed order.
			$item_id = $local_order->add_product(
				$values['data'],
				$values['quantity'],
				array(
					'variation' => $values['variation'],
					'totals'    => array(
						'subtotal'     => $values['line_subtotal'],
						'subtotal_tax' => $values['line_subtotal_tax'],
						'total'        => $values['line_total'],
						'tax'          => $values['line_tax'],
						'tax_data'     => $values['line_tax_data'],
					),
				)
			);

			if ( ! $item_id ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 525 ) );
			}

			do_action( 'woocommerce_add_order_item_meta', $item_id, $values, $cart_item_key ); // Allow plugins to add order item meta.
		}
	}

	/**
	 * Adds customer data to WooCommerce order.
	 *
	 * @param integer $local_order_id WooCommerce order ID.
	 * @param array   $customer_data  Customer data returned by Ecster.
	 * @param array   $addresses      Addresses to update (shipping and/or billing).
	 */
	function helper_add_customer_data_to_local_order( $local_order_id, $customer_data, $addresses ) {

		$country = isset( $customer_data['countryCode'] ) ? $customer_data['countryCode'] : 'SE';

		if ( in_array( 'billing', $addresses, true ) ) {
			update_post_meta( $local_order_id, '_billing_first_name', $customer_data['firstName'] );
			update_post_meta( $local_order_id, '_billing_last_name', $customer_data['lastName'] );
			update_post_meta( $local_order_id, '_billing_address_1', $customer_data['address'] );
			update_post_meta( $local_order_id, '_billing_city', $customer_data['city'] );
			update_post_meta( $local_order_id, '_billing_postcode', $customer_data['zip'] );
			update_post_meta( $local_order_id, '_billing_country', $country );
			if ( $customer_data['cellular'] ) {
				update_post_meta( $local_order_id, '_billing_phone', $customer_data['cellular'] );
			}
			if ( $customer_data['email'] ) {
				update_post_meta( $local_order_id, '_billing_email', $customer_data['email'] );
			}
		}

		if ( in_array( 'shipping', $addresses, true ) ) {
			update_post_meta( $local_order_id, '_shipping_first_name', $customer_data['firstName'] );
			update_post_meta( $local_order_id, '_shipping_last_name', $customer_data['lastName'] );
			update_post_meta( $local_order_id, '_shipping_address_1', $customer_data['address'] );
			update_post_meta( $local_order_id, '_shipping_city', $customer_data['city'] );
			update_post_meta( $local_order_id, '_shipping_postcode', $customer_data['zip'] );
			update_post_meta( $local_order_id, '_shipping_country', $country );
		}
	}

	/**
	 * Adds customer data to WooCommerce order.
	 *
	 * @param array $customer_data Customer data returned by Ecster.
	 * @param array $addresses     Addresses to update (shipping and/or billing).
	 */
	function helper_add_customer_data_to_session( $customer_data, $addresses ) {
		$country = isset( $customer_data['countryCode'] ) ? $customer_data['countryCode'] : 'SE';

		if ( in_array( 'billing', $addresses, true ) ) {
			krokedil_customer_set_country( $country );
			krokedil_customer_set_postcode( $customer_data['zip'] );
			krokedil_customer_set_city( $customer_data['city'] );
			krokedil_customer_set_address( $customer_data['address'] );
		}

		if ( in_array( 'shipping', $addresses, true ) ) {
			WC()->customer->set_shipping_country( $country );
			WC()->customer->set_shipping_postcode( $customer_data['zip'] );
			WC()->customer->set_shipping_city( $customer_data['city'] );
			WC()->customer->set_shipping_address( $customer_data['address'] );
		}
	}

	/**
	 * Adds order items to ongoing order.
	 *
	 * @param integer $local_order_id WooCommerce order ID.
	 */
	function helper_calculate_order_cart_hash( $local_order_id ) {
		if ( method_exists( WC()->cart, 'get_cart_hash' ) ) {
			$hash = WC()->cart->get_cart_hash();
		} else {
			$hash = md5( wp_json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
		}
		update_post_meta( $local_order_id, '_cart_hash', $hash );
	}

	/**
	 * Calculates cart totals.
	 */
	function helper_calculate_cart_totals() {
		WC()->cart->calculate_fees();
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
	}

	/**
	 * Calculates cart totals.
	 */
	function helper_calculate_order_totals( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->calculate_totals();
		if ( krokedil_wc_gte_3_0() ) {
			$order->save();
		}

	}

	/**
	 * Adds invoice fee to WooCommerce order
	 *
	 * @param array $fees Array of fees returned by Ecster.
	 * @TODO: Fix this
	 */
	function helper_add_invoice_fees_to_session( $fees ) {
		WC()->session->set( 'wc_ecster_invoice_fee', $fees );
	}

	/**
	 * Adds order fees to local order.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_add_order_fees( $order ) {
		$order_id = krokedil_get_order_id( $order );
		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
			$item_id = $order->add_fee( $fee );
			if ( ! $item_id ) {
				WC_Gateway_Ecster::log( 'Unable to add order fee.' );
				throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
			}
			// Allow plugins to add order item meta to fees.
			do_action( 'woocommerce_add_order_fee_meta', $order_id, $item_id, $fee, $fee_key );
		}
	}


	/**
	 * Adds Ecster invoice fee to local order if session exist.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_maybe_add_invoice_fee( $order ) {
		if ( WC()->session->get( 'wc_ecster_invoice_fee' ) ) {
			$fees = WC()->session->get( 'wc_ecster_invoice_fee' );
			foreach ( $fees as $fee ) {
				$ecster_fee            = new stdClass();
				$ecster_fee->id        = sanitize_title( __( 'Ecster Invoice Fee', 'krokedil-ecster-pay-for-woocommerce' ) );
				$ecster_fee->name      = __( 'Ecster Invoice Fee', 'krokedil-ecster-pay-for-woocommerce' );
				$ecster_fee->amount    = $fee / 100;
				$ecster_fee->taxable   = false;
				$ecster_fee->tax       = 0;
				$ecster_fee->tax_data  = array();
				$ecster_fee->tax_class = '';
				$fee_id                = $order->add_fee( $ecster_fee );

				if ( ! $fee_id ) {
					$order->add_order_note( __( 'Unable to add Ecster invoice fee to the order.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				WC()->session->__unset( 'wc_ecster_invoice_fee' );
			}
		}
	}

	/**
	 * Adds order shipping to local order.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 * @internal param object $klarna_order Klarna order.
	 */
	public function helper_add_order_shipping( $order ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$order_id              = krokedil_get_order_id( $order );
		$this_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		WC()->cart->calculate_shipping();
		// Store shipping for all packages.
		foreach ( WC()->shipping->get_packages() as $package_key => $package ) {
			if ( isset( $package['rates'][ $this_shipping_methods[ $package_key ] ] ) ) {
				$item_id = $order->add_shipping( $package['rates'][ $this_shipping_methods[ $package_key ] ] );
				if ( ! $item_id ) {
					WC_Gateway_Ecster::log( 'Unable to add shipping item.' );
					throw new Exception( __( 'Error: Unable to add shipping item. Please try again.', 'woocommerce' ) );
				}
				// Allows plugins to add order item meta to shipping.
				do_action( 'woocommerce_add_shipping_order_item', $order_id, $item_id, $package_key );
			}
		}
	}
	/**
	 * Adds order tax rows to local order.
	 *
	 * @since  1.6.0
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_add_order_tax_rows( $order ) {
		// Store tax rows.
		foreach ( array_keys( WC()->cart->taxes + WC()->cart->shipping_taxes ) as $tax_rate_id ) {
			if ( $tax_rate_id && ! $order->add_tax( $tax_rate_id, WC()->cart->get_tax_amount( $tax_rate_id ), WC()->cart->get_shipping_tax_amount( $tax_rate_id ) ) && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				WC_Gateway_Ecster::log( 'Unable to add taxes.' );
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 405 ) );
			}
		}
	}
	/**
	 * Adds order coupons to local order.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_add_order_coupons( $order ) {
		foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
			if ( ! $order->add_coupon( $code, WC()->cart->get_coupon_discount_amount( $code ) ) ) {
				WC_Gateway_Ecster::log( 'Unable to create order.' );
				throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
			}
		}
	}

	/**
	 * Adds payment method to local order.
	 *
	 * @since  1.4.1
	 * @access public
	 */
	public function add_order_payment_method( $order ) {
		global $woocommerce;
		$available_gateways = $woocommerce->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['ecster'];
		$order->set_payment_method( $payment_method );
		$order->save();
	}

}
$wc_ecster_ajax = new WC_Ecster_Ajax();
