<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ecster API Update Reference.
 *
 * @since 1.7.0
 */
class WC_Ecster_Request_Update_Reference extends WC_Ecster_Request {

	/** @var string Ecster API request path. */
	private $request_path   = 'orders/v1/';

	/** @var string Ecster API request method. */
	private $request_method = 'POST';

	/**
	 * Gets Update External Reference request response.
	 *
	 * @param $cart_key Ecster cart key
	 *
	 * @return array|WP_Error
	 */
	public function response( $internal_reference, $external_reference ) {
		$request_url = $this->base_url . $this->request_path . $internal_reference . '/' . $external_reference;
		$request     = wp_remote_request( $request_url, $this->get_request_args( $external_reference ) );

		return $request;
	}

	/**
	 * Gets Update Reference request arguments.
	 *
	 * @return array
	 */
	private function get_request_args( $external_reference ) {
		$request_args = array(
			'headers' => $this->request_header(),
			'body'    => $this->get_request_body( $external_reference ),
			'method'  => $this->request_method,
		);

		return $request_args;
	}

	/**
	 * Gets update reference request body.
	 *
	 * @return false|string
	 */
	private function get_request_body( $external_reference ) {
		$formatted_request_body = array(
			'externalReference' => $external_reference
		);

		return wp_json_encode( $formatted_request_body );
	}

}