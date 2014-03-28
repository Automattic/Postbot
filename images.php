<?php

include dirname( dirname( __FILE__ ) ).'/postbot-config.php';
include dirname( dirname( __FILE__ ) ).'/lib/lib.uploader.php';
include dirname( dirname( __FILE__ ) ).'/lib/lib.scheduler.php';

$user = Postbot_User::get_from_cookie();
if ( !$user ) {
	wp_safe_redirect( SCHEDULE_URL );
	die();
}

$is_thumbnail = false;
$filename     = $_GET['file'];

if ( stripos( $filename, '-thumb' ) !== false ) {
	$is_thumbnail = true;
	$filename = str_replace( '-thumb', '', $filename );
}

$photo = Postbot_Photo::get_by_stored_name( $filename );

if ( $photo && $photo->get_user_id() === $user->get_user_id() ) {
	$stored_name = $photo->get_stored_name();
	if ( $is_thumbnail ) {
		$stored_name = $photo->get_thumnail_name();
	}

	$local_name = postbot_get_photo( $stored_name );

	header( 'Content-Type: '.$photo->get_media_type() );
	header( 'Content-Length: ' . filesize( $local_name ) );

	readfile( $local_name );
}
else
	wp_safe_redirect( SCHEDULE_URL );
