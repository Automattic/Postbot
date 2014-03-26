<?php

include dirname( dirname( __FILE__ ) ).'/postbot-config.php';
include dirname( dirname( __FILE__ ) ).'/lib/lib.uploader.php';
include dirname( dirname( __FILE__ ) ).'/lib/lib.scheduler.php';

$user = Postbot_User::get_from_cookie();
if ( !$user ) {
	wp_safe_redirect( SCHEDULE_URL );
	die();
}

$photo = Postbot_Photo::get_by_stored_name( $_GET['file'] );

if ( $photo && $photo->get_user_id() === $user->get_user_id() ) {
	$local_name = postbot_get_photo( $photo->get_stored_name() );

	header( 'Content-Type: '.$photo->get_media_type() );
	header( 'Content-Length: ' . filesize( $local_name ) );

	readfile( $local_name );
}
else
	wp_safe_redirect( SCHEDULE_URL );
