<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Ecster_Request_Get_Order
 */
class WC_Ecster_Request_Get_Order extends WC_Ecster_Request {

	/** @var string Ecster API request path. */
	private $request_path = 'orders/v1/';

	/**
	 * Returns Get Order request response.
	 *
	 * @param $internal_reference
	 *
	 * @return array|WP_Error
	 */
	public function response( $internal_reference ) {
		$request_url = $this->base_url . $this->request_path . $internal_reference;
		$request     = wp_safe_remote_get( $request_url, $this->get_request_args() );
		return $request;
	}

	/**
	 * Gets arguments for Get Order request.
	 *
	 * @return array
	 */
	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header(),
		);

		return $request_args;
	}

}
