<?php

if ( !defined( 'POSTBOT_VERSION' ) )
	die();

function wp_get_current_user() {
	global $current_user;

	if ( $current_user ) {
		return $current_user;
	}
	return false;
}

function wp_salt() {
	return POSTBOT_LOGIN_SALT;
}

require dirname( dirname( __FILE__ ) ) . '/wordpress/wp-load.php';
require dirname( dirname( __FILE__ ) ) . '/wordpress/wp-includes/class-phpass.php';

if ( !class_exists( 'WPCOM_OAuth_Bearer_Client' ) )
	include dirname( __FILE__ ).'/lib/lib.wpcc.php';

if ( !function_exists( 'sslize' ) ) {
	function sslize( $url ) {
		if ( is_ssl() )
			return str_replace( 'http:', 'https:', $url );
		return str_replace( 'https:', 'http:', $url );
	}
}

if ( !defined( 'DAY_IN_MINUTES' ) ) {
	define( 'HOUR_IN_MINUTES', 60 );
	define( 'DAY_IN_MINUTES', 24 * HOUR_IN_MINUTES );
}

// Force SSL cookies
if ( POSTBOT_COOKIE_SSL === true && !is_ssl() ) {
	wp_safe_redirect( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
	die();
}

function postbot_crop_photo( $uploaded_file, $width, $height ) {
	$new_target = tempnam( '/tmp', 'postbot' );

	$image = wp_get_image_editor( $uploaded_file );
	if ( !is_wp_error( $image ) ) {
		$image->resize( $width, $height, true );

		$saved = $image->save( $new_target );
		if ( !is_wp_error( $saved ) )
			return $saved['path'];
		return $saved;
	}

	return false;
}

function postbot_store_photo( $uploaded_filename, $target_filename ) {
	$target_filename = POSTBOT_LOCAL_STORE.$target_filename;

	return copy( $uploaded_filename, $target_filename );
}

function postbot_get_photo( $stored_name ) {
	return POSTBOT_LOCAL_STORE.$stored_name;
}

function postbot_forget_photo( $local_name ) {
}

function postbot_photo_url( $stored_name ) {
	return sslize( POSTBOT_LOCAL_URL.$stored_name );
}

function postbot_delete_photo( $stored_name ) {
	$file = POSTBOT_LOCAL_STORE.$stored_name;

	if ( file_exists( $file ) )
		return @unlink( $file );
	return true;
}

function postbot_log_error( $user_id, $message, $data = NULL ) {
	global $wpdb;

	$caller = array_shift( debug_backtrace() );

	$error = array(
		'message'    => $message,
		'user_id'    => $user_id,
		'error_date' => current_time( 'mysql' ),
		'line'       => $caller['line'],
		'file'       => basename( $caller['file'] )
	);

	if ( $data )
		$error['data'] = maybe_serialize( $data );

	$wpdb->insert( $wpdb->postbot_errors, $error );
}

foreach ( $postbot_tables AS $table_name => $actual_table ) {
	$wpdb->$table_name = $actual_table;
}
