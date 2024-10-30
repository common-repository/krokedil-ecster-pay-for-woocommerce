<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats API request header for Ecster.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Header {

	/**
	 * Gets formatted Ecster API request header.
	 *
	 * @param $username
	 * @param $password
	 *
	 * @return array
	 */
	public static function get( $username, $password ) {
		$formatted_request_header = array(
			'X-Ecster-origin'   => 'checkout',
			'X-Ecster-username' => $username,
			'X-Ecster-password' => $password,
			'Content-Type'      => 'application/json'
		);

		return $formatted_request_header;
	}

}