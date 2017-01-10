<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Eftsecure class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Eftsecure extends WC_Payment_Gateway {

	/**
	 * API credentials
	 *
	 * @var string
	 */
	public $username;
    public $password;

	/**
	 * Api access token
	 *
	 * @var string
	 */
	public $token;

	/**
	 * Logging enabled?
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'eftsecure';
		$this->method_title         = __( 'EFTsecure', 'woocommerce-gateway-eftsecure' );
		$this->method_description   = __( 'EFTsecure allows your customers to pay via their internet banking.', 'woocommerce-gateway-eftsecure' );
		$this->has_fields           = true;
		$this->supports             = array();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description' );
		$this->enabled                = $this->get_option( 'enabled' );
		$this->username               = $this->get_option( 'username' );
		$this->password               = $this->get_option( 'password' );
		$this->logging                = 'yes' === $this->get_option( 'logging' );
        $this->order_button_text = __( 'Continue with payment', 'woocommerce-gateway-eftsecure' );

        if ( ! class_exists( 'WC_Eftsecure_API' ) ) {
            include_once( dirname( __FILE__ ) . '/class-wc-eftsecure-api.php' );
        }

        WC_Eftsecure_API::set_username( $this->username );
        WC_Eftsecure_API::set_password( $this->password );

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon  = '<img src="http://services.callpay.com/img/products/eftsecure/icon-sm.png" alt="EFTsecure" />';
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( 'no' === $this->enabled ) {
			return;
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->username || ! $this->password ) {
				return false;
			}
			else if (get_woocommerce_currency() != 'ZAR') {
                return false;
            }
			return true;
		}
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'settings-eftsecure.php' );
	}

    public function validate_password_field($key, $value = NULL){

        $post_data = $_POST;
	    if ($value == NULL) {
            $value = $post_data['woocommerce_eftsecure_'.$key];
        }
        if( isset($post_data['woocommerce_eftsecure_username']) && empty($value)){
            //not validated
            //add_settings_error($key, 'settings_updated', 'Password is required', 'error');
            WC_Admin_Settings::add_error( __( 'Error: You must enter a API password.', 'woocommerce-gateway-eftsecure' ) );
            return false;
        }else{
            WC_Eftsecure_API::set_username($post_data['woocommerce_eftsecure_username']);
            WC_Eftsecure_API::set_password($value);
            try{
                WC_Eftsecure_API::get_token_data();
            }
            catch(Exception $e) {
                WC_Admin_Settings::add_error( __( 'Error: Incorrect username and/or password.', 'woocommerce-gateway-eftsecure' ) );
                return false;
            }
        }
        return $value;
    }

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user = wp_get_current_user();

		if ( $user->ID ) {
			$user_email = get_user_meta( $user->ID, 'billing_email', true );
			$user_email = $user_email ? $user_email : $user->user_email;
		} else {
			$user_email = '';
		}

        $pay_button_text = '';

		echo '<div
			id="eftsecure-payment-data"
			data-panel-label="' . esc_attr( $pay_button_text ) . '"
			data-description=""
			data-email="' . esc_attr( $user_email ) . '"
			data-amount="' . esc_attr( WC()->cart->total ) . '"
			data-name="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ). '">';

		if ( $this->description ) {
			echo apply_filters( 'wc_eftsecure_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		echo '</div>';
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for eftsecure payment
	 *
	 * @access public
	 */
	public function payment_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        add_action('wp_head', 'customEventJS');

        wp_enqueue_script( 'eftsecure', 'https://eftsecure.callpay.com/ext/eftsecure/js/checkout.js', '', '2.0', true );
        wp_enqueue_script( 'woocommerce_eftsecure', plugins_url( 'assets/js/eftsecure_checkout.js', WC_EFTSECURE_MAIN_FILE ), array( 'eftsecure' ), WC_EFTSECURE_VERSION, true );

        $token_data = WC_Eftsecure_API::get_token_data();
		$eftsecure_params = array(
		    'reference' => uniqid(substr(sanitize_title(get_bloginfo('name')),0,6).'_'),
            'organisation_id' => $token_data->organisation_id,
			'token' => $token_data->token,
            'amount' => WC()->cart->total,
            'pcolor' => $this->get_option( 'pcolor' ),
            'scolor' => $this->get_option( 'scolor' )
		);

		wp_localize_script( 'woocommerce_eftsecure', 'wc_eftsecure_params', apply_filters( 'wc_eftsecure_params', $eftsecure_params ) );
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_customer Force user creation.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {

        try {
            $order  = wc_get_order( $order_id );

            if (!isset($_REQUEST['eftsecure_transaction_id'])) {
                return array(
                    'result'   => 'fail',
                    'redirect' => '',
                );
            }


            // Handle payment.
            if ( $order->get_total() > 0 ) {

                if ( $order->get_total() * 100 < 50 ) {
                    throw new Exception( __( 'Sorry, the minimum allowed order total is 0.50 to use this payment method.', 'woocommerce-gateway-eftsecure' ) );
                }

                WC_Eftsecure::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

                // Make sure the transaction is successful.
                $response = WC_Eftsecure_API::get_transaction_data($_REQUEST['eftsecure_transaction_id']);
                // Process valid response.
                $this->process_response( $response, $order );
            } else {
                $order->payment_complete();
            }

            // Remove cart.
            WC()->cart->empty_cart();

            do_action( 'wc_gateway_eftsecure_process_payment', $response, $order );

            // Return thank you page redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order )
            );

        } catch ( Exception $e ) {
            wc_add_notice( $e->getMessage(), 'error' );
            WC_Eftsecure::log( sprintf( __( 'Error: %s', 'woocommerce-gateway-eftsecure' ), $e->getMessage() ) );

            if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
                $this->send_failed_order_email( $order_id );
            }

            do_action( 'wc_gateway_eftsecure_process_payment_error', $e, $order );

            return array(
                'result'   => 'fail',
                'redirect' => ''
            );
        }
        return;
	}


	/**
	 * Store extra meta data for an order from a Eftsecure Response.
     * @param $order WC_Order
	 */
	public function process_response( $response, $order ) {

        WC_Eftsecure::log( "Processing response: " . print_r( $response, true ) );

	    if ($response->successful == 0) {
            throw new Exception( __( 'Payment and order success do not correspond.', 'woocommerce-gateway-eftsecure' ) );
        }
        else if(floatval($response->amount) != floatval($order->get_total())) {
            throw new Exception( __( 'Order amount ('.floatval($order->get_total()).') and payment amount ('.floatval($response->amount).') do not correspond.', 'woocommerce-gateway-eftsecure' ) );
        }

        add_post_meta( $order->id, '_eftsecure_transaction_id', $response->id, true );

        if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
            $order->reduce_order_stock();
        }

        $order->update_status( 'wc-processing');
        WC_Eftsecure::log( "Successful payment: $response->id" );

		return $response;
	}


	/**
	 * Sends the failed order email to admin
	 *
	 * @version 3.1.0
	 * @since 3.1.0
	 * @param int $order_id
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}
}

function customEventJS() {
    echo '<script type="text/javascript">window.addEventListener("message", function(event) {
    eval(event.data);
});</script>';
}
