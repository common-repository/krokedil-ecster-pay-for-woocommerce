<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ecster API Annul Order.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Annul_Order extends WC_Ecster_Request {

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
		$request     = wp_remote_request( $request_url, $this->get_request_args() );
		WC_Gateway_Ecster::log( 'Ecster annul order request response (URL ' . $request_url . '): ' . stripslashes_deep( json_encode($request) ) );
		return $request;
	}

	/**
	 * Gets Create Cart request arguments.
	 *
	 * @return array
	 */
	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header(),
			'body'    => $this->get_request_body(),
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
	private function get_request_body() {
		$formatted_request_body = array(
			'type'            => 'ANNUL',
		);
		
		WC_Gateway_Ecster::log( 'Ecster annul order request body: ' . json_encode($formatted_request_body) );

		return wp_json_encode( $formatted_request_body );
	}

}