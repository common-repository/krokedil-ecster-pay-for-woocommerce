<?php
/*
 * Plugin Name: Krokedil Ecster Pay for WooCommerce (deprecated)
 * Plugin URI: https://krokedil.se/
 * Description: Take payments in your store using Ecster Pay.
 * Author: Krokedil
 * Author URI: https://krokedil.se/
 * Version: 1.8.7
 * Text Domain: krokedil-ecster-pay-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.7.0
 *
 * Copyright (c) 2016-2020 Krokedil
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_ECSTER_VERSION', '1.8.7' );
define( 'WC_ECSTER_MIN_PHP_VER', '5.3.0' );
define( 'WC_ECSTER_MIN_WC_VER', '3.0.0' );
define( 'WC_ECSTER_MAIN_FILE', __FILE__ );
define( 'WC_ECSTER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_ECSTER_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_ECSTER_BASE_URL_TEST', 'https://labs.ecster.se/rest/' );
define( 'WC_ECSTER_BASE_URL_PROD', 'https://secure.ecster.se/rest/' );
define( 'WC_ECSTER_BASE_URL_PUBLIC_TEST', 'https://labs.ecster.se/rest/public/' );
define( 'WC_ECSTER_BASE_URL_PUBLIC_PROD', 'https://secure.ecster.se/rest/public/' );
define( 'WC_ECSTER_ECP_ID', 'b5aa56f2-7fa9-464f-ae10-97feea64e8f9' );

if ( ! class_exists( 'WC_Ecster' ) ) {

	class WC_Ecster {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {}

		/**
		 * Notices (array)
		 *
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}

			// Init the gateway itself
			$this->init_gateways();

			include_once plugin_basename( 'includes/class-wc-ecster-ajax.php' );

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			// CSS for settings page.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_css' ) );
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * The primary sanity check, automatically disable the plugin on activation if it doesn't
		 * meet minimum requirements.
		 *
		 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
		 */
		public static function activation_check() {
			$environment_warning = self::get_environment_warning( true );
			if ( $environment_warning ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( $environment_warning );
			}
		}

		/**
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation.
		 */
		public function check_environment() {
			$environment_warning = self::get_environment_warning();
			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning( $during_activation = false ) {
			if ( version_compare( phpversion(), WC_ECSTER_MIN_PHP_VER, '<' ) ) {
				if ( $during_activation ) {
					$message = __( 'The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'krokedil-ecster-pay-for-woocommerce' );
				} else {
					$message = __( 'The WooCommerce Ecster plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'krokedil-ecster-pay-for-woocommerce' );
				}
				return sprintf( $message, WC_STRIPE_MIN_PHP_VER, phpversion() );
			}

			if ( version_compare( WC_VERSION, WC_ECSTER_MIN_WC_VER, '<' ) ) {
				if ( $during_activation ) {
					$message = __( 'The plugin could not be activated. The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'krokedil-ecster-pay-for-woocommerce' );
				} else {
					$message = __( 'The WooCommerce Ecster plugin has been deactivated. The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'krokedil-ecster-pay-for-woocommerce' );
				}
				return sprintf( $message, WC_ECSTER_MIN_WC_VER, WC_VERSION );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @since 1.0.0
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'krokedil-ecster-pay-for-woocommerce' ) . '</a>',
				'<a href="https://krokedil.se/">' . __( 'Docs', 'krokedil-ecster-pay-for-woocommerce' ) . '</a>',
				'<a href="https://krokedil.se/">' . __( 'Support', 'krokedil-ecster-pay-for-woocommerce' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = version_compare( WC()->version, '2.6', '>=' );

			$section_slug = $use_id_as_section ? 'ecster' : strtolower( 'WC_Gateway_Ecster' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			include_once WC_ECSTER_PLUGIN_PATH . '/includes/class-wc-gateway-ecster.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request-create-cart.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request-update-cart.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request-update-reference.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request-get-order.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request-annul-order.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request-debit-order.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/class-wc-ecster-request-credit-order.php';

			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/helpers/class-wc-ecster-request-header.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/helpers/class-wc-ecster-request-cart.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/helpers/class-wc-ecster-request-delivery-methods.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/helpers/class-wc-ecster-request-customer.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/helpers/class-wc-ecster-request-order-items.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/requests/helpers/class-wc-ecster-request-refund-order-items.php';

			include_once WC_ECSTER_PLUGIN_PATH . '/includes/krokedil-wc-compatability.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/wc-ecster-functions.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/class-wc-ecster-order-submission-failure.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/class-wc-ecster-api-callbacks.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/class-wc-ecster-order-management.php';
			include_once WC_ECSTER_PLUGIN_PATH . '/includes/admin/class-wc-ecster-admin-notices.php';

			load_plugin_textdomain( 'krokedil-ecster-pay-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		}

		/**
		 * Load Admin CSS
		 **/
		public function enqueue_admin_css( $hook ) {
			if ( 'woocommerce_page_wc-settings' == $hook && isset( $_GET['section'] ) && 'ecster' == $_GET['section'] ) {
				wp_register_style( 'ecster-admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', false );
				wp_enqueue_style( 'ecster-admin' );
			}
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.0
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'WC_Gateway_Ecster';

			return $methods;
		}

	}

	$GLOBALS['wc_ecster'] = WC_Ecster::get_instance();
	register_activation_hook( __FILE__, array( 'WC_Ecster', 'activation_check' ) );

}

/**
 * Removes mandatory from checkout fields if Ecster is the selected payment method.
 */
function wc_ecster_remove_required_from_checkout_fields() {
	if ( 'ecster' === WC()->session->get( 'chosen_payment_method' ) ) {
		$checkout_fields = WC()->checkout->checkout_fields;

		foreach ( $checkout_fields as $fieldset_key => $fieldset ) {
			foreach ( $fieldset as $field_key => $field ) {
				$checkout_fields[ $fieldset_key ][ $field_key ]['required'] = false;
			}
		}
	}

	WC()->checkout->checkout_fields = $checkout_fields;
}
add_action( 'woocommerce_checkout_process', 'wc_ecster_remove_required_from_checkout_fields' );

/**
 * Add Ecster invoice fee to cart if invoice is the selected payment method and a fee is received from Ecster.
 * The fee is added in ajax_on_payment_success.
 */
function wc_ecster_add_invoice_fee() {
	if ( WC()->session->get( 'wc_ecster_invoice_fee' ) ) {
		$fees = WC()->session->get( 'wc_ecster_invoice_fee' );
		foreach ( $fees as $fee ) {
			WC()->cart->add_fee( __( 'Ecster Invoice Fee', 'krokedil-ecster-pay-for-woocommerce' ), $fee / 100, false, '' );
		}
	}
}
// add_action( 'woocommerce_cart_calculate_fees', 'wc_ecster_add_invoice_fee' );
