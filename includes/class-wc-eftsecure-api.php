<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Eftsecure_API class.
 *
 * Communicates with EftSecure API.
 */
class WC_Eftsecure_API {

	/**
	 * EftSecure API Endpoint
	 */
	const ENDPOINT = 'https://services.callpay.com/api/v1/';

	/**
	 * Secret API Username.
	 * @var string
	 */
	private static $username = '';

    /**
     * Secret API Password.
     * @var string
     */
    private static $password = '';

	/**
	 * Set api username.
	 * @param string $username
	 */
	public static function set_username( $username ) {
		self::$username = $username;
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_username() {
		if ( ! self::$username ) {
			$options = get_option( 'woocommerce_eftsecure_settings' );

			if ( isset($options['username'])) {
				self::set_username( $options['username']  );
			}
		}
		return self::$username;
	}

    /**
     * Set api password.
     * @param string $password
     */
    public static function set_password( $password ) {
        self::$password = $password;
    }

    /**
     * Get api password.
     * @return string
     */
    public static function get_password() {
        if ( ! self::$password ) {
            $options = get_option( 'woocommerce_eftsecure_settings' );

            if ( isset($options['password'])) {
                self::set_password( $options['password']  );
            }
        }
        return self::$password;
    }

	/**
	 * Send the request to EftSecure's API
	 *
	 * @param array $request
	 * @param string $api
	 * @return array|WP_Error
	 */
	public static function request( $request, $api = 'charges', $method = 'POST' ) {
		WC_Eftsecure::log( "{$api} request: " . print_r( $request, true ) );

		$response = wp_remote_post(
			self::ENDPOINT . $api,
			array(
				'method'        => $method,
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_username(). ':' . self::get_password() ),
				),
				'body'       => apply_filters( 'woocommerce_eftsecure_request_body', $request, $api ),
				'timeout'    => 70,
				'user-agent' => 'WooCommerce ' . WC()->version
			)
		);

        $parsed_response = json_decode( $response['body'] );

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {

			WC_Eftsecure::log( "Error Response: " . print_r( $response, true ) );
			return new WP_Error( 'eftsecure_error', __( 'There was a problem connecting to the payment gateway.'.$parsed_response->name, 'woocommerce-gateway-eftsecure' ) );
		}

		// Handle response
		if ( ! empty( $parsed_response->status ) && $parsed_response->status != 200 ) {
            $code = $parsed_response->code;
			return new WP_Error( $code, $parsed_response->name );
		} else {
			return $parsed_response;
		}
	}

    /**
     * Fetches an api token
     *
     * @return mixed
     * @throws Exception
     */
	public static function get_token_data() {
        $response = self::request( '', 'token', 'POST' );
        if ( is_wp_error( $response ) ) {
            throw new Exception($response->get_error_message());
        }
        return $response;
    }

    public static function get_transaction_data($transaction_id) {
        $response = self::request('', 'gateway-transaction/'.$transaction_id, 'GET');
        if ( is_wp_error( $response ) ) {
            throw new Exception($response->get_error_message());
        }
        return $response;
    }
}
