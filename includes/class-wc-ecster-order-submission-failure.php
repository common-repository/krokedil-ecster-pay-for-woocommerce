<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_OSF class.
 */
class WC_Ecster_OSF {

	/** @var string Ecster API username. */
	private $username;

	/** @var string Ecster API password. */
	private $password;

	/** @var boolean Ecster API testmode. */
	private $testmode;

	/**
	 * WC_Ecster_OSF constructor.
	 */
	function __construct() {
		$ecster_settings = get_option( 'woocommerce_ecster_settings' );
		$this->testmode  = 'yes' === $ecster_settings['testmode'];
		$this->username  = $this->testmode ? $ecster_settings['test_username'] : $ecster_settings['username'];
        $this->password  = $this->testmode ? $ecster_settings['test_password'] : $ecster_settings['password'];
        
        add_action( 'init', array( $this, 'maybe_create_backup_order_finalization' ) );
    }

    /**
	 * If order is not submitted correctly via javascript in checkout the parameter ecster-osf=true is added to the url when checkout page is reloaded.
     * We listen to this in the maybe_create_backup_order_finalization() function.
	 */
    public function maybe_create_backup_order_finalization() {
        if ( isset( $_GET['ecster-osf'] ) && true == $_GET['ecster-osf'] && isset( $_GET['order-id'] ) ) {
            $order_id   = $_GET['order-id'];
            $order      = wc_get_order( $order_id );
            
            if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {

                $internal_reference 	= get_post_meta( $order_id, '_wc_ecster_internal_reference', true );
                $ecster_status			= '';
        
                // Get purchase data from Ecster
                if ( $internal_reference ) {
                    $request            = new WC_Ecster_Request_Get_Order( $this->username, $this->password, $this->testmode );
                    $response           = $request->response( $internal_reference );
                    $response_body      = json_decode( $response['body'] );
                    $ecster_status		= $response_body->response->order->status;
                }

                // Check Ecster order status
                switch ( $ecster_status ) {
                    case 'awaitingContract' : // Part payment wit no contract signed yet
                        $order->update_status( 'on-hold', __( 'Ecster payment approved but Ecster awaits signed customer contract. Order can NOT be delivered yet.', 'krokedil-ecster-pay-for-woocommerce' ) );
                        break;
                    default :
                        if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
                            $order->payment_complete();
                        }
                        break;
                }
                   
                $order->add_order_note( sprintf(
                    __( 'WooCommerce order finalized via submission backup.', 'krokedil-ecster-pay-for-woocommerce' ),
                    $order_id
                ) );
                $order->update_status( 'on-hold' );
                update_post_meta( $local_order_id, '_wc_ecster_osf', true );
            }   
            wp_safe_redirect( $order->get_checkout_order_received_url() );
            exit;
        }
    }
}
$wc_ecster_osf = new WC_Ecster_OSF();