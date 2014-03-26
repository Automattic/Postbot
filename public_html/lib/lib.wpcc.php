<?php

define( 'WPCOM_OAUTH_HOST'       , 'https://public-api.wordpress.com/oauth2' );
define( 'WPCOM_AUTHORIZE_URL'    , WPCOM_OAUTH_HOST . '/authorize' );
define( 'WPCOM_ACCESS_TOKEN_URL' , WPCOM_OAUTH_HOST . '/token'  );
define( 'WPCOM_USER_DETAILS_URL' , 'https://public-api.wordpress.com/rest/v1/me' );

if ( !defined( 'OAUTH_NONCE_LIFE' ) )
	define( 'OAUTH_NONCE_LIFE', 3600 ); // 1 hour.

class WPCOM_OAuth_Exception extends Exception {
	const 
		INVALID_NONCE_VALUE   = 1,
		INVALID_REQUEST_TOKEN = 2,
		INVALID_JSON_DATA     = 3,
		INVALID_ACCESS_TOKEN  = 4,
		REMOTE_REQUEST_ERROR  = 5,
		INVALID_HTTP_RESPONSE = 6,
		OAUTH_SERVER_ERROR    = 7;

	public function explain_error() {
		switch( $this->getCode() ) {
			case WPCOM_OAuth_Exception::INVALID_NONCE_VALUE:
				$message = 'The token of this login URL has expired.';
				break;
			case WPCOM_OAuth_Exception::INVALID_REQUEST_TOKEN:
				$message = 'The login URL is invalid. A valid token from WordPress.com was expected.';
				break;
			case WPCOM_OAuth_Exception::OAUTH_SERVER_ERROR:
				switch( $this->getMessage() ) {
					case 'access_denied':
						$message = 'You need to login to WordPress.com';
						break 2;
				}
			case WPCOM_OAuth_Exception::INVALID_HTTP_RESPONSE:
			case WPCOM_OAuth_Exception::REMOTE_REQUEST_ERROR:
			case WPCOM_OAuth_Exception::INVALID_JSON_DATA:
			default:
				$message = 'Unable to get your user details from WordPress.com.';
		}
		return $message;
	}
}

/**
  * A base oAuth client implementation that connects to the public WordPress.com oAuth servers.
  */
class WPCOM_OAuth_Bearer_Client {
	const NONCE_COOKIE = '__wpcc';
	// WPCOM client id
	private $client_id;
	// WPCOM client secret
	private $secret;
	// The login url to be used.
	private $login_url;

	public function __construct( $client_id, $client_secret, $login_url ) {
		$this->client_id = $client_id;
		$this->secret = $client_secret;
		$this->login_url = add_query_arg( 'action', 'request_access_token', preg_replace( '~https?://~i', 'https://', $login_url ) );
	}

	/**
	 * Generates a time based nonce that is valid OAUTH_NONCE_LIFE seconds.
	 **/
	protected function get_nonce_life() {
		$nonce_tick = ceil( time() / OAUTH_NONCE_LIFE );
		return substr( hash_hmac( 'sha256', ceil( $nonce_tick / OAUTH_NONCE_LIFE ), $this->secret ), -12, 10 );
	}

	/**
	  * Creates a nonce to be used to avoid CSRF attacks.
	  **/
	protected function get_request_nonce() {
		$salt = sha1( $this->secret );
		// Try to create an unique nonce for an unauthenticated user. At this point, the user_id is unknown.
		foreach( array( 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR' ) as $key ) {
			if ( isset( $_SERVER[$key] ) )
				$salt .= '|' . sha1 ( $_SERVER[$key] );
		}
		$nonce_tick = $this->get_nonce_life();
		return (object) array(
			'value'  => hash_hmac( 'sha256', sprintf( '%s:%s:%s', $this->client_id, sha1( $this->secret ), $nonce_tick ), $salt ),
			'expire' => $nonce_tick 
		);
	}

	/**
	 * Verifies that the specified value is the same we stored in a secure cookie.
	 **/
	protected function verify_nonce( $value ) {
		if ( !isset( $_COOKIE[self::NONCE_COOKIE] ) )
			return false;
		$elements = explode( ':', $_COOKIE[self::NONCE_COOKIE], 2 );
		if ( 2 != count( $elements ) )
			return false;
		$expire = $this->get_nonce_life(); 
		return ( $expire === $elements[1] ) && ( $elements[0] === hash_hmac( 'sha256', $value . $expire, $this->secret ) );
	}

	/**
	  * Redirects the current page to the Authorization URL. It doesn't check if the user is already logged in.
	  */
	public function redirect_to_authorization_url( $options=array() ) {
		if ( !is_array( $options ) )
			$options=array();
		$nonce = $this->get_request_nonce();
		$nonce_cookie = sprintf( '%s:%s',
			hash_hmac( 'sha256', $nonce->value . $nonce->expire, $this->secret ),
			$nonce->expire
		);
		// Store the nonce value in a cookie.
		setcookie( self::NONCE_COOKIE, $nonce_cookie, 0, null, null, true, true );
		wp_redirect( add_query_arg(
			array_merge( 
				array(
					'client_id'     => $this->client_id,
					'response_type' => 'code',
					'blog_id'       => 0,
					'state'         => urlencode( $nonce->value ),
					'redirect_uri'  => urlencode( $this->login_url ),
				),
				$options
			),
			WPCOM_AUTHORIZE_URL
		) );
		exit();
	}

	/**
	  * Grabs the request token send by the authorization server.
	  */
	public function get_request_token() {
		// Always remove the nonce cookie on the client, even if this request fails.
		setcookie( self::NONCE_COOKIE, false, time() - 3600, null, null, true, true );
		if ( !empty( $_GET['error'] ) )
			throw new WPCOM_OAuth_Exception( $_GET['error'],  WPCOM_OAuth_Exception::OAUTH_SERVER_ERROR );
		if ( !isset( $_GET['state'] ) || !$this->verify_nonce( $_GET['state'] ) )
			throw new WPCOM_OAuth_Exception( 'Invalid state value', WPCOM_OAuth_Exception::INVALID_NONCE_VALUE );
		if ( !isset( $_GET['code'] ) )
			throw new WPCOM_OAuth_Exception( 'Invalid request token', WPCOM_OAuth_Exception::INVALID_REQUEST_TOKEN );
		// TODO Add some signature checking, to avoid replay attacks with a validi previous state nonce.
		return $_GET['code'];
	}

	/**
	  * Requests an access token by contacting directly the authorization server and a request token obtained in a previous step.
	  * @param $request_token|string the request token produced by the authorization server.
	  */
	public function get_access_token( $request_token ) {
		// Retry if the request fails. It shouldn't normally happen, but it's for good measure.
		$retry = 3;
		do {
			$retry--;
			$r = wp_remote_post( WPCOM_ACCESS_TOKEN_URL, array(
				'sslverify' => true, // Explicitly set SSL verification to avoid MitM attacks.
				'body'      => array(
					'client_id'     => $this->client_id,
					'redirect_uri'  => $this->login_url,
					'client_secret' => $this->secret,
					'code'          => $request_token,
					'grant_type'    => 'authorization_code',
				)
			) );
		} while( $retry > 0 && is_wp_error( $r ) );

		$secret = $this->parse_server_response( $r );
		return $secret->access_token;
	}

	/**
	  * Request the details of the user that granted the application.
	  * @param $access_token|string an access token generated by the authorization server.
	  */
	public function get_user_details( $access_token ) {
		$r = wp_remote_get( WPCOM_USER_DETAILS_URL, array( 'headers' => array( 'Authorization' => sprintf( 'Bearer %s', $access_token ) ) ) );
		return $this->parse_server_response( $r );
	}

	private function parse_server_response( $response ) {
		if ( is_wp_error( $response ) )
			throw new WPCOM_OAuth_Exception( sprintf( 'An unexpected error was produced while querying the WP.com server: %s.', $response->get_error_message() ), WPCOM_OAuth_Exception::REMOTE_REQUEST_ERROR );
		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( !$data )
			throw new WPCOM_OAuth_Exception( 'Unable to parse the response. JSON encoded data is expected.', WPCOM_OAuth_Exception::INVALID_JSON_DATA );
		if ( isset( $data->error ) )
			throw new WPCOM_OAuth_Exception( $data->error, WPCOM_OAuth_Exception::OAUTH_SERVER_ERROR );
		if ( 200 != wp_remote_retrieve_response_code( $response ) )
			throw new WPCOM_OAuth_Exception( sprintf( 'The OAuth server returned an unexpected http response (%d).', wp_remote_retrieve_response_code( $response ) ), WPCOM_OAuth_Exception::INVALID_HTTP_RESPONSE );

		return $data;
	}
}
