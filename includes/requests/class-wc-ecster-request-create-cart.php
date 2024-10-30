<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ecster API Create Cart.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Create_Cart extends WC_Ecster_Request {

	/** @var string Ecster API request path. */
	private $request_path   = 'eps/v1/cart';

	/** @var string Ecster API request method. */
	private $request_method = 'POST';

	/**
	 * Returns Create Cart request response.
	 *
	 * @return array|WP_Error
	 */
	public function response() {
		$request_url = $this->base_url . $this->request_path;
		$request     = wp_remote_request( $request_url, $this->get_request_args() );
		WC_Gateway_Ecster::log( 'Ecster create cart request response: ' . json_encode($request) );
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
	 * Gets Create Cart request body.
	 *
	 * @return false|string
	 */
	private function get_request_body() {
		$formatted_request_body = array(
			'locale'            => $this->locale(),
			'cart'              => $this->cart(),
			'deliveryMethods'   => $this->delivery_methods(),
			'eCommercePlatform' => $this->platform(),
			// 'customer'          => $this->customer(),
			'notificationUrl'   => $this->notification_url()
		);
		
		WC_Gateway_Ecster::log( 'Ecster create cart request body: ' . json_encode($formatted_request_body) );

		return wp_json_encode( $formatted_request_body );
	}

}