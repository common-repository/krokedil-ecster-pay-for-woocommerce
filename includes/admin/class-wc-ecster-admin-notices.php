<?php
/**
 * Admin notice class file.
 *
 * @package WC_Ecster/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Returns error messages depending on
 *
 * @class    WC_Ecster_Admin_Notices
 * @package  WC_Ecster/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_Ecster_Admin_Notices {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Checks if Ecster gateway is enabled.
	 *
	 * @var $enabled
	 */
	protected $enabled;

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
	 * WC_Ecster_Admin_Notices constructor.
	 */
	public function __construct() {
		$settings      = get_option( 'woocommerce_ecster_settings' );
		$this->enabled = $settings['enabled'];

		add_action( 'admin_init', array( $this, 'check_settings' ) );
	}
	/**
	 * Checks the settings.
	 */
	public function check_settings() {
		add_action( 'admin_notices', array( $this, 'version_update_message' ) );
	}



	/**
	 * Display message explaining that a new Ecster plugin is availabe.
	 */
	public function version_update_message() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

        ?>
        <div class="kco-message notice woocommerce-message notice-info">
            <?php echo wp_kses_post( wpautop( '<p>' . sprintf( __( 'There is a new plugin available for Ecsters new platform. <a href="%s">Read more about it here</a>.', 'krokedil-ecster-pay-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ecster' ) ) . '</p>' ) ); ?>
        </div>
        <?php
		
	}
}

WC_Ecster_Admin_Notices::get_instance();
