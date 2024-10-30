<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ecster API Debit Order.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Debit_Order extends WC_Ecster_Request {

	/** @var string Ecster API request path. */
	private $request_path   = 'v1/orders';

	/** @var string Ecster API request method. */
	private $request_method = 'POST';

	/**
	 * Returns Create Cart request response.
	 *
	 * @return array|WP_Error
	 */
	public function response( $order_id ) {
		$ecster_reference = get_post_meta( $order_id, '_wc_ecster_internal_reference', true );
		$request_url = $this->base_url_public . $this->request_path . '/' . $ecster_reference . '/transactions';
		$request     = wp_remote_request( $request_url, $this->get_request_args( $order_id ) );
		WC_Gateway_Ecster::log( 'Ecster debit order request response (URL ' . $request_url . '): ' . stripslashes_deep( json_encode($request) ) );
		return $request;
	}

	/**
	 * Gets Create Cart request arguments.
	 *
	 * @return array
	 */
	private function get_request_args( $order_id ) {
		$request_args = array(
			'headers' => $this->request_header(),
			'body'    => $this->get_request_body( $order_id ),
			'method'  => $this->request_method
		);

		return $request_args;
	}

	/**
	 * Ecster API request header.
	 *
	 * @return array
	 */
	protected function request_header() {
		$formatted_request_header = array(
			'x-api-key'   		=> $this->api_key,
			'x-merchant-key' 	=> $this->merchant_key,
			'Content-Type'      => 'application/json'
		);

		return $formatted_request_header;
	}

	/**
	 * Gets Create Cart request body.
	 *
	 * @return false|string
	 */
	private function get_request_body( $order_id ) {
		$order = wc_get_order( $order_id );

		$formatted_request_body = array(
			'type'          => 'DEBIT',
			'amount'        => round($order->get_total() * 100 ),
			'rows'			=> WC_Ecster_Get_Order_Items::get_items( $order_id ),
		);
		
		WC_Gateway_Ecster::log( 'Ecster debit order request body: ' . stripslashes_deep( json_encode( $formatted_request_body ) ) );

		return wp_json_encode( $formatted_request_body );
	}

}