<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats API request customer for Ecster.
 *
 * @since 1.0
 * @return array
 */
class WC_Ecster_Request_Customer {

	/**
	 * @return array
	 */
	public static function customer() {
		$ecster_customer = array(
			'ssn'     => self::customer_ssn(),
			'name'    => self::customer_name(),
			'address' => self::customer_address(),
			'city'    => self::customer_city(),
			'zip'     => self::customer_zip()
		);

		return apply_filters( 'wc_ecster_customer', $ecster_customer );
	}


	private function customer_ssn() {
		return false;
	}

	private function customer_name() {
		return false;
	}

	private function customer_address() {
		return false;
	}

	private function customer_city() {
		return false;
	}

	private function customer_zip() {
		return false;
	}

}