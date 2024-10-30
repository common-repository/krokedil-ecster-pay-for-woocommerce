<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Ecster_Api_Callbacks class.
 *
 * Class that handles Ecster API callbacks.
 */
class Ecster_Api_Callbacks {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Order data updated.
	 *
	 * @var bool
	 */
	public $order_data_updated = false;

	/**
	 * Ecster_Api_Callbacks constructor.
	 */
	public function __construct() {
		$ecster_settings = get_option( 'woocommerce_ecster_settings' );
		$this->testmode  = 'yes' === $ecster_settings['testmode'];
		$this->username  = $this->testmode ? $ecster_settings['test_username'] : $ecster_settings['username'];
		$this->password  = $this->testmode ? $ecster_settings['test_password'] : $ecster_settings['password'];

		add_action( 'ecster_execute_osn_callback', array( $this, 'execute_osn_callback' ), 10, 3 );

	}

	public function execute_osn_callback( $decoded, $order_id = '' ) {

		$internal_reference = $decoded->internalReference;
		$external_reference = $decoded->externalReference;
		$request            = new WC_Ecster_Request_Get_Order( $this->username, $this->password, $this->testmode );
		$response           = $request->response( $internal_reference );

		if ( ! is_wp_error( $response ) && ( wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) <= 299 ) ) {
			$response_body = json_decode( $response['body'] );
			WC_Gateway_Ecster::log( 'OSN callback. Order ID:' . $order_id ); // Input var okay.
			WC_Gateway_Ecster::log( 'OSN callback. Response body:' . json_encode( $response_body ) );
		} else {
			$response_body = json_decode( $response['body'] );
			WC_Gateway_Ecster::log( 'HTTP-Status Code: ' . wp_remote_retrieve_response_code( $response ) . ' | WC_Ecster_Request_Get_Order Response ERROR: ' . json_encode( $response_body ) );
		}

		if ( empty( $order_id ) ) { // We're missing Order ID in callback. Try to get it via query by internal reference
			$order_id = $this->get_order_id_from_internal_reference( $internal_reference );
		}

		if ( ! empty( $order_id ) ) { // Input var okay.

			$this->update_woocommerce_order( $response_body, $order_id );

		} else { // We can't find a coresponding Order ID. Let's create an order

			$order = $this->create_woocommerce_order( $response_body, $internal_reference, $external_reference );

			// Send order number to Ecster
			if ( is_object( $order ) ) {
				$this->update_order_reference_in_ecster( $internal_reference, $order );
			}
		} // End if().
	}

	/**
	 * Backup order creation, in case checkout process failed.
	 *
	 * @param string $private_id, $public_token, $customer_type.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function get_order_id_from_internal_reference( $internal_reference ) {

		// Let's check so the internal reference doesn't already exist in an existing order
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'ecster',
				'date_created'   => '>' . ( time() - MONTH_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = '';
		foreach ( $orders as $order_id ) {

			$order_internal_reference = get_post_meta( $order_id, '_wc_ecster_internal_reference', true );

			if ( $order_internal_reference === $internal_reference ) {
				$order_id_match = $order_id;
				WC_Gateway_Ecster::log( 'Order ID is missing in OSN callback but Internal reference ' . $internal_reference . '. already exist in order ID ' . $order_id_match );
				break;
			}
		}

		return $order_id_match;
	}


	/**
	 * Update WooCommerce order on Ecster OSN.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function update_woocommerce_order( $response_body, $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			WC_Gateway_Ecster::log( 'Error. Could not instantiate an order object in OSN callback for order id ' . $order_id . '. Aborting callback.' );
			return;
		}

		// Maybe add missing customer data to order.
		$this->maybe_add_customer_data( $order, $response_body->response );

		// Maybe add missing shipping data to order.
		$this->maybe_add_shipping_data( $order, $response_body->response );

		// Maybe add missing invoice fee to order.
		$this->maybe_add_invoice_fee( $order, $response_body->response );

		// Checks if order data was updated.
		if ( $this->order_data_updated ) {
			$order->add_order_note( __( 'Order updated via Ecster OSN callback. Please verify the order in Ecsters system.', 'krokedil-ecster-pay-for-woocommerce' ) );
		}

		// Get the WC order total and Ecster order total.
		$order_totals = $this->get_order_totals( $order, $response_body->response );

		switch ( $response_body->response->order->status ) {
			case 'awaitingContract': // Do nothing - these order statuses should be handled in process_payment()
				break;
			case 'ready':
				// Check Ecster order total compared to WC order total.
				if ( $order_totals['ecster_order_total'] === $order_totals['wc_order_total'] ) {
					if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
						$order->payment_complete();
						$order->add_order_note( __( 'Ecster reported order status ready.', 'krokedil-ecster-pay-for-woocommerce' ) );
					}
				} else {
					$order->update_status( 'on-hold', sprintf( __( 'A mismatch in order totals between WooCommerce and Ecster was detected. Please verify the order in Ecsters system. Ecster total: %1$d. WooCommerce total: %2$d.', 'krokedil-ecster-pay-for-woocommerce' ), $order_totals['ecster_order_total'], $order_totals['wc_order_total'] ) );
				}
				break;
			case 'fullyDelivered':
				if ( 'INVOICE' == $response_body->response->paymentMethod->type || 'ACCOUNT' == $response_body->response->paymentMethod->type ) {
					$order->add_order_note( __( 'Ecster reported order fully delivered.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->payment_complete();
					$order->add_order_note( __( 'Ecster reported order status fully delivered.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'partiallyDelivered':
				$order->add_order_note( __( 'Ecster reported order partially delivered.', 'krokedil-ecster-pay-for-woocommerce' ) );
				break;
			case 'denied':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Denied', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'failed':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'failed', __( 'Ecster reported order Failed', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'aborted':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Aborted', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'annuled':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Annuled', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'expired':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Expired', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'stopped':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Stopped', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			default:
				break;
		} // End switch().

		if ( $order->get_user_id() > 0 ) {
			update_user_meta( $order->get_user_id(), 'billing_phone', $response_body->response->customer->cellular );
		}
	}

	/**
	 * Processes WooCommerce order on backup order creation.
	 *
	 * @param Object $response_body.
	 * @param string $internal_reference.
	 * @param string $external_reference.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function create_woocommerce_order( $response_body, $internal_reference, $external_reference ) {

		WC_Gateway_Ecster::log( 'Order ID is missing in OSN callback and we could not find Internal reference ' . $internal_reference . ' in an existing order. Starting backup order creation...' );

		// Create local order
		$order = wc_create_order( array( 'status' => 'pending' ) );

		if ( is_wp_error( $order ) ) {
			WC_Gateway_Ecster::log( 'Backup order creation. Error - could not create order. ' . var_export( $order->get_error_message(), true ) );
		} else {
			$order_id = krokedil_get_order_id( $order );
			WC_Gateway_Ecster::log( 'Backup order creation - order ID - ' . $order->get_id() . ' - created.' );

		}

		// Add/update customer and order info to order
		$billing_first_name = ( $response_body->response->customer->firstName ?: $response_body->response->recipient->firstName );
		$billing_last_name  = ( $response_body->response->customer->lastName ?: $response_body->response->recipient->lastName );
		$billing_postcode   = ( $response_body->response->customer->zip ?: $response_body->response->recipient->zip );
		$billing_address    = ( $response_body->response->customer->address ?: $response_body->response->recipient->address );
		$billing_city       = ( $response_body->response->customer->city ?: $response_body->response->recipient->city );
		if ( ! isset( $response_body->response->customer->countryCode ) ) {
			$billing_country = 'SE';
		} else {
			$billing_country = $response_body->response->customer->countryCode;
		}

		$order->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
		$order->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
		$order->set_billing_country( sanitize_text_field( $billing_country ) );
		$order->set_billing_address_1( sanitize_text_field( $billing_address ) );
		$order->set_billing_city( sanitize_text_field( $billing_city ) );
		$order->set_billing_postcode( sanitize_text_field( $billing_postcode ) );
		$order->set_billing_phone( sanitize_text_field( $response_body->response->customer->cellular ) );
		$order->set_billing_email( sanitize_text_field( $response_body->response->customer->email ) );

		if ( isset( $response_body->response->recipient ) ) {
			$order->set_shipping_first_name( sanitize_text_field( $response_body->response->recipient->firstName ) );
			$order->set_shipping_last_name( sanitize_text_field( $response_body->response->recipient->lastName ) );
			$order->set_shipping_country( sanitize_text_field( $response_body->response->recipient->countryCode ) );
			$order->set_shipping_address_1( sanitize_text_field( $response_body->response->recipient->address ) );
			$order->set_shipping_city( sanitize_text_field( $response_body->response->recipient->city ) );
			$order->set_shipping_postcode( sanitize_text_field( $response_body->response->recipient->zip ) );
		} else {
			$order->set_shipping_first_name( sanitize_text_field( $response_body->response->customer->firstName ) );
			$order->set_shipping_last_name( sanitize_text_field( $response_body->response->customer->lastName ) );
			$order->set_shipping_country( sanitize_text_field( $billing_country ) );
			$order->set_shipping_address_1( sanitize_text_field( $response_body->response->customer->address ) );
			$order->set_shipping_city( sanitize_text_field( $response_body->response->customer->city ) );
			$order->set_shipping_postcode( sanitize_text_field( $response_body->response->customer->zip ) );
		}

		$order->set_created_via( 'ecster_api' );
		$order->set_currency( sanitize_text_field( $response_body->response->order->currency ) );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['ecster'];
		$order->set_payment_method( $payment_method );

		// Add items to order
		foreach ( $response_body->response->order->rows as $order_row ) {
			if ( isset( $order_row->partNumber ) ) { // partNumber is only set for product order items.
				if ( isset( $product ) ) {
					unset( $product );
				}

				if ( wc_get_product( $order_row->partNumber ) ) { // If we got product ID.
					$product = wc_get_product( $order_row->partNumber );
				} else { // Get product ID based on SKU.
					global $wpdb;
					$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $order_row->partNumber ) );
					if ( $product_id ) {
						$product = wc_get_product( $product_id );
					}
				}

				if ( $product ) {
					$item_id = $order->add_product( $product, $order_row->quantity, array() );
					if ( ! $item_id ) {
						WC_Gateway_Ecster::log( 'Error. Unable to add product to order ' . $order->get_id() . '. add_product() response - ' . var_export( $item_id, true ) );
						throw new Exception( sprintf( __( 'Error %d: Unable to add product. Please try again.', 'woocommerce' ), 525 ) );
					}
				}
			}
		}

		// Make sure to run Sequential Order numbers if plugin exsists
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			$sequential = new WC_Seq_Order_Number_Pro();
			$sequential->set_sequential_order_number( $order_id );
		} elseif ( class_exists( 'WC_Seq_Order_Number' ) ) {
			$sequential = new WC_Seq_Order_Number();
			$sequential->set_sequential_order_number( $order_id, get_post( $order_id ) );
		}

		update_post_meta( $order_id, '_wc_ecster_internal_reference', $internal_reference );
		update_post_meta( $order_id, '_wc_ecster_external_reference', $external_reference );
		update_post_meta( $order_id, '_wc_ecster_payment_method', $response_body->response->paymentMethod->type );

		$order->calculate_totals();
		$order->save();

		// Check Ecster order status
		switch ( $response_body->response->order->status ) {
			case 'awaitingContract': // Part payment with no contract signed yet
				$order->update_status( 'on-hold', __( 'Ecster payment approved but Ecster awaits signed customer contract. Order can NOT be delivered yet.', 'krokedil-ecster-pay-for-woocommerce' ) );
				break;
			case 'ready': // Invoice
			case 'fullyDelivered': // Card payment with direct charge
				$order->update_status( 'on-hold' );
				break;
			default:
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'on-hold' );
				}
				break;
		}
		$order->add_order_note( __( 'Order created via Ecster Pay API callback. Please verify the order in Ecsters system.', 'krokedil-ecster-pay-for-woocommerce' ) );
		$order->add_order_note(
			sprintf(
				__( 'Payment via Ecster Pay %s.', 'krokedil-ecster-pay-for-woocommerce' ),
				$response_body->response->paymentMethod->type
			)
		);

		return $order;
	}

	/**
	 * Update the Collector Order with the WooCommerce Order number
	 */
	public function update_order_reference_in_ecster( $internal_reference, $order ) {

		$request  = new WC_Ecster_Request_Update_Reference( $this->username, $this->password, $this->testmode );
		$response = $request->response( $internal_reference, $order->get_order_number() );

		WC_Gateway_Ecster::log( 'Update Ecster order reference in backup order creation (for internal reference ' . $internal_reference . ') ' . $order->get_order_number() );
	}

	/**
	 * Maybe adds customer data if it is missing from the order. Mainly for Swish orders that do not get completed correctly.
	 *
	 * @param WC_Order $order WC_Order object.
	 * @param Object   $ecster_order Ecster order object.
	 * @return void
	 */
	public function maybe_add_customer_data( $order, $ecster_order ) {
		if ( isset( $ecster_order->customer ) ) {

			$name = explode( ', ', $ecster_order->customer->name );

			if ( '' === $order->get_billing_first_name() ) {
				$order->set_billing_first_name( $name[1] );
			}
			if ( '' === $order->get_billing_last_name() ) {
				$order->set_billing_last_name( $name[0] );
			}
			if ( '' === $order->get_billing_address_1() ) {
				$order->set_billing_address_1( $ecster_order->customer->address );
			}
			if ( '' === $order->get_billing_city() ) {
				$order->set_billing_city( $ecster_order->customer->city );
			}
			if ( '' === $order->get_billing_postcode() ) {
				$order->set_billing_postcode( $ecster_order->customer->postcode );
			}
			if ( '' === $order->get_billing_email() ) {
				$order->set_billing_email( $ecster_order->customer->email );
			}
			if ( '' === $order->get_billing_phone() ) {
				$order->set_billing_phone( $ecster_order->customer->cellular );
			}
			$order->save();
		}
	}

	/**
	 * Maybe adds shipping data if it is missing from the order. Mainly for Swish orders that do not get completed correctly.
	 *
	 * @param WC_Order $order
	 * @param Object   $ecster_order
	 * @return void
	 */
	public function maybe_add_shipping_data( $order, $ecster_order ) {
		$ecster_shipping = $ecster_order->deliveryMethod; // phpcs:ignore
		if ( isset( $ecster_shipping ) ) {
			if ( empty( $order->get_shipping_method() ) ) {
				$vat_codes  = array();
				$unit_price = 0;
				$tax_rate   = '';

				foreach ( $ecster_order->order->rows as $order_item ) {
					if ( 'Fraktavgift' === $order_item->name ) {
						$vat_codes[] = floatval( $order_item->vatCode );
						$unit_price  += $order_item->unitPrice; // phpcs:ignore

						// Check for shipping tax rate from WC settings.
						$shipping_tax_class = get_option( 'woocommerce_shipping_tax_class' );
						$wc_tax             = new WC_Tax();
						// If shipping tax class is based on cart items, set tax rate to highest value.
						if ( 'inherit' === $shipping_tax_class ) {
							$found_classes      = array_intersect( array_merge( array( '' ), WC_Tax::get_tax_class_slugs() ), $order->get_items_tax_classes() );
							$shipping_tax_class = count( $found_classes ) ? current( $found_classes ) : false;
							$shipping_tax_rates = $wc_tax->get_rates_for_tax_class( $shipping_tax_class );
							foreach ( $shipping_tax_rates as $shipping_tax_rate ) {
								$tax_rate = $shipping_tax_rate->tax_rate;
							}
						} else {
							// Additional shipping tax classes from WC settings.
							$shipping_tax_rates = $wc_tax->get_rates_for_tax_class( $shipping_tax_class );
							foreach ( $shipping_tax_rates as $shipping_tax_rate ) {
								$tax_rate = $shipping_tax_rate->tax_rate;
							}
						}
					}
				}

				// Calculates the shipping cost without tax applied.
				$formatted_tax_rate       = ( $tax_rate / 100 ) + 1;
				$formatted_shipping_total = $unit_price / 100;
				$shipping_unit_price      = $formatted_shipping_total / $formatted_tax_rate;

				// Retrieve the method id and instance id of shipping.
				$shipping_ids = explode( ':', $ecster_shipping->id );
				$method_id    = $shipping_ids[0];
				$instance_id  = $shipping_ids[1];

				$rate = new WC_Shipping_Rate( $ecster_shipping->id, $ecster_shipping->name, $shipping_unit_price, array(), $method_id, $instance_id );
				$item = new WC_Order_Item_Shipping();
				$args = array(
					'method_title' => $rate->label,
					'method_id'    => $rate->id,
					'total'        => wc_format_decimal( $rate->cost ),
					'taxes'        => $rate->taxes,
					'meta_data'    => $rate->get_meta_data(),
				);
				$item->set_props( $args );
				$item->save();
				$order->add_item( $item );
				$this->order_data_updated = true;
			}
		}
	}

	/**
	 * Maybe adds invoice fee if it is missing from the order.
	 *
	 * @param WC_Order $order
	 * @param Object   $ecster_order
	 * @return void
	 */
	public function maybe_add_invoice_fee( $order, $ecster_order ) {
		// Check if Ecster order is Invoice order.
		if ( 'INVOICE' === $ecster_order->paymentMethod->type ) { // phpcs:ignore
			// Check if Ecster Invoice has a Fee.
			$ecster_order_has_invoice_fee = false;
			foreach ( $ecster_order->order->rows as $order_item ) {
				if ( 'Fakturaavgift' === $order_item->name ) {
					$ecster_order_has_invoice_fee = true;
				}
			}
			if ( $ecster_order_has_invoice_fee ) {
				// Check if Fakturaavgift exists in WC Order.
				$wc_order_has_invoice_fee = false;
				$order_fees               = $order->get_items( 'fee' );
				if ( empty( ! $order_fees ) ) {
					foreach ( $order_fees as $fee ) {
						if ( 'Fakturaavgift' === $fee->get_name() ) {
							$wc_order_has_invoice_fee = true;
						}
					}
				}

				// Add invoice fee if it does not exist in WC Order.
				if ( ! $wc_order_has_invoice_fee ) {
					$unit_price = 0;

					// Retrieve the invoice fee name and unit price.
					foreach ( $ecster_order->order->rows as $order_item ) {
						if ( 'Fakturaavgift' === $order_item->name ) {
							$invoive_fee_name = $order_item->name;
							$unit_price 	 += $order_item->unitPrice; // phpcs:ignore
						}
					}

					$invoice_fee_total = $unit_price / 100;

					try {
						$args = array(
							'name'       => $invoive_fee_name,
							'total'      => $invoice_fee_total,
							'tax_status' => 'none',
							'quantity'   => 1,
						);
						$fee  = new WC_Order_Item_Fee();
						$fee->set_props( $args );
						$fee->save();
						$order->add_item( $fee );
					} catch ( Exception $e ) {
						WC_Gateway_Ecster::log( 'Backup order creation error add invoice fee error: ' . $e->getCode() . ' - ' . $e->getMessage() );
					}
					$this->order_data_updated = true;
				}
			}
		}
	}

	/**
	 * Get WC Order total and Ecster order total.
	 *
	 * @param WC_Order $order WC_Order object.
	 * @param Object   $ecster_order Ecster order object.
	 * @return array
	 */
	public function get_order_totals( $order, $ecster_order ) {
		$ecster_order_total_amount = intval( round( $ecster_order->order->amount ) );
		$order->calculate_totals();
		$order->save();
		$wc_order_total_amount = intval( round( $order->get_total() * 100 ) );

		$order_totals = array(
			'ecster_order_total' => $ecster_order_total_amount,
			'wc_order_total'     => $wc_order_total_amount,
		);
		return $order_totals;
	}
}
Ecster_Api_Callbacks::get_instance();
