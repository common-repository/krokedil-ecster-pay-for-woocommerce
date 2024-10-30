<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Ecster_Request
 */
class WC_Ecster_Request {

	/** @var string Ecster API username. */
	private $username;

	/** @var string Ecster API password. */
	private $password;

	/** @var boolean Ecster API testmode. */
	private $testmode;

	/** @var string Ecster API base url. */
	protected $base_url;

	/**
	 * WC_Ecster_Request constructor.
	 *
	 * @param $username
	 * @param $password
	 * @param $testmode
	 */
	public function __construct( $username, $password, $testmode, $api_key = null, $merchant_key = null ) {
		$this->username 	= $username;
		$this->password 	= $password;
		$this->testmode 	= $testmode;
		$this->api_key  	= $api_key;
		$this->merchant_key	= $merchant_key;

		if ( $this->testmode ) {
			$this->base_url 		= WC_ECSTER_BASE_URL_TEST;
			$this->base_url_public 	= WC_ECSTER_BASE_URL_PUBLIC_TEST;
		} else {
			$this->base_url 		= WC_ECSTER_BASE_URL_PROD;
			$this->base_url_public 	= WC_ECSTER_BASE_URL_PUBLIC_PROD;
		}
	}

	/**
	 * Overridden in child classes.
	 *
	 * @return WP_Error
	 */
	public function request() {
		die( 'function WC_Ecster_Request::request() must be over-ridden in a sub-class.' );
	}

	/**
	 * Ecster API request header.
	 *
	 * @return array
	 */
	protected function request_header() {
		return WC_Ecster_Request_Header::get( $this->username, $this->password );
	}

	/**
	 * Overridden in child classes.
	 *
	 * @return WP_Error
	 */
	protected function request_body() {
		die( 'function WC_Ecster_Request::request_body() must be over-ridden in a sub-class.' );
	}

	/**
	 * Returns locale for Ecster API requests.
	 *
	 * Currently only supports Sweden.
	 *
	 * @TODO: Add support for other countries, once they are available
	 * @return mixed|void
	 */
	protected function locale() {
		$iso_code = explode('_', get_locale());
		if( 'en' == $iso_code[0] ) {
			$lang = $iso_code[0];
		} else {
			$lang = 'sv';
		}
		$ecster_locale = array(
			'language' => $lang,
			'country'  => 'SE'
		);

		return apply_filters( 'wc_ecster_locale', $ecster_locale );
	}

	/**
	 * Returns platform information (WooCommerce and version) for Ecster API requests.
	 *
	 * @return array
	 */
	protected function platform() {
		$ecster_platform = array(
			'reference' => WC_ECSTER_ECP_ID,
			'info'      => 'WooCommerce ' . WC_VERSION
		);

		return $ecster_platform;
	}

	/**
	 * Returns notification URL for Ecster OSN callback.
	 *
	 * @link   https://developer.ecster.se/api-reference/#/get-order
	 * @return string
	 */
	protected function notification_url() {
		$ecster_notification_url = get_home_url() . '/wc-api/WC_Gateway_Ecster/';
		if ( WC()->session->get( 'order_awaiting_payment' ) > 0 ) {
			$ecster_notification_url = add_query_arg( 'order_id', WC()->session->get( 'order_awaiting_payment' ), $ecster_notification_url );
		}
		return $ecster_notification_url;
	}

	/**
	 * Returns WooCommerce cart formatted for Ecster API requests.
	 *
	 * @return array
	 */
	protected function cart() {
		return WC_Ecster_Request_Cart::cart();
	}

	/**
	 * Returns WooCommerce shipping options formatted for Ecster API requests.
	 *
	 * @return array
	 */
	protected function delivery_methods() {
		return WC_Ecster_Request_Delivery_Methods::delivery_methods();
	}


	/**
	 * Returns WooCommerce customer information formatted for Ecster API requests.
	 *
	 * @return array
	 */
	/*
	private function customer() {
		return WC_Ecster_Request_Customer::customer();
	}
	*/

}