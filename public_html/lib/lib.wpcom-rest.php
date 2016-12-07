<?php

define( 'OAUTH_ACCESS_TOKEN_ENDPOINT', 'https://public-api.wordpress.com/oauth2/token' );
define( 'OAUTH_AUTHORIZE_ENDPOINT', 'https://public-api.wordpress.com/oauth2/authorize?client_id='.OAUTH_KEY.'&response_type=code&redirect_uri='.urlencode( OAUTH_REDIRECT_URL ) );
define( 'OAUTH_AUTHENTICATE_URL', 'https://public-api.wordpress.com/oauth2/authenticate' );

class WPCOM_Rest_Client {
	const API_NEW_POST     = 'https://public-api.wordpress.com/rest/v1/sites/%d/posts/new';
	const API_EDIT_POST    = 'https://public-api.wordpress.com/rest/v1/sites/%d/posts/%d';
	const API_USER_DETAILS = 'https://public-api.wordpress.com/rest/v1/me/';
	const API_BLOG_DETAILS = 'https://public-api.wordpress.com/rest/v1/sites/%d';
	const API_DELETE_POST  = 'https://public-api.wordpress.com/rest/v1/sites/%d/posts/%d/delete';

	private $access_token;

	public function __construct( $access_token = false ) {
		$this->access_token = $access_token;
	}

	private function api_request( $url, $auth_header = null, $post_data = null ) {
		$curl = curl_init( $url );

		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_FAILONERROR, false );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );

		if ( $auth_header ) {
			if ( is_string( $auth_header ) )
				$auth_header = array( $auth_header );

			curl_setopt( $curl, CURLOPT_HTTPHEADER, $auth_header );
		}

		if ( !empty( $post_data ) ) {
			curl_setopt( $curl, CURLOPT_POST, 1 );
			@curl_setopt( $curl, CURLOPT_POSTFIELDS, $post_data );
		}

		$response = curl_exec( $curl );
		$info     = curl_getinfo( $curl );
		$error    = curl_error( $curl );

		curl_close( $curl );

		if ( is_array( $info ) && isset( $info['http_code'] ) && !in_array( $info['http_code'], array( 200, 301, 302 ) ) && $info['size_download'] == 0 )
			return new WP_Error( 'curl', 'Request endpoint failed with HTTP '.$info['http_code'] );
		elseif ( !$response )
			return new WP_Error( 'curl', $error );

		return $response;
	}

	private function request_and_decode( $url, $post_data ) {
		$response = $this->api_request( $url, 'Authorization: Bearer '.$this->access_token, $post_data );

		if ( is_wp_error( $response ) )
			return $response;

		$decoded = json_decode( $response );

		if ( $decoded ) {
			if ( isset( $decoded->error ) && ( isset( $decoded->message ) || isset( $decoded->error_description ) ) )
				return new WP_Error( $decoded->error, isset( $decoded->error_description ) ? $decoded->error_description : $decoded->message );

			return $decoded;
		}

		return new WP_Error( 'send', 'Failed to decode data from endpoint' );
	}

	public function new_post( $site_id, $post_data ) {
		$new_post_url = sprintf( self::API_NEW_POST, $site_id );

		return $this->request_and_decode( $new_post_url, $post_data );
	}

	public function update_post( $site_id, $post_id, $post_data ) {
		$edit_post_url = sprintf( self::API_EDIT_POST, $site_id, $post_id );

		return $this->request_and_decode( $edit_post_url, $post_data );
	}

	public function get_user_details() {
		return $this->request_and_decode( self::API_USER_DETAILS, array() );
	}

	public function get_blog_details( $blog_id ) {
		return $this->request_and_decode( sprintf( self::API_BLOG_DETAILS, $blog_id ), array() );
	}

	public function delete_blog_post( $blog_id, $post_id ) {
		return $this->request_and_decode( sprintf( self::API_DELETE_POST, $blog_id, $post_id ), array( 'pretty' => false ) );
	}

	public function request_access_token( $authorize_code, $client_key, $client_secret, $redirect_url ) {
		$params = array(
			'client_id'     => $client_key,
			'redirect_uri'  => $redirect_url,
			'client_secret' => $client_secret,
			'code'          => $authorize_code,
			'grant_type'    => 'authorization_code'
		);

		return $this->request_and_decode( OAUTH_ACCESS_TOKEN_ENDPOINT, $params );
	}

	public static function get_blog_auth_url( $blog_url ) {
		return OAUTH_AUTHORIZE_ENDPOINT.'&blog='.urlencode( $blog_url );
	}
}
