<?php

include dirname( __FILE__ ).'/postbot-config.php';
include dirname( __FILE__ ).'/lib/lib.scheduler.php';
include dirname( __FILE__ ).'/lib/lib.uploader.php';
include dirname( __FILE__ ).'/lib/lib.display.php';

$user = Postbot_User::get_from_cookie();
if ( !$user ) {
	wp_safe_redirect( SCHEDULE_URL );
	die();
}

if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'scheduler' ) ) {
	if ( isset( $_FILES['media'] ) && $user && is_uploaded_file( $_FILES['media']['tmp_name'] ) ) {
		$existing = Postbot_Photo::get_for_user( $user->user_id );

		if ( count( $existing ) + 1 > POSTBOT_MAX_SCHEDULE )
			$result = array( 'error' => __( 'You have reached the limit for the number of photos that can be scheduled in one go. Finish these off and do the rest later!' ) );
		elseif ( filesize( $_FILES['media']['tmp_name'] ) > POSTBOT_MAX_UPLOAD * 1024 * 1024 )
			$result = array( 'error' => __( 'Your file is larger than the maximum allowed size.' ) );
		else {
			$uploader  = new Postbot_Uploader( $user->user_id );
			$scheduler = new Postbot_Scheduler();
			$media     = $uploader->upload_file( $_FILES['media']['name'], $_FILES['media']['tmp_name'] );

			if ( $media && !is_wp_error( $media ) )
				$result = array( 'item' => get_schedule_item_html( $media ), 'id' => $media->get_id(), 'img' => $media->get_thumbnail_url(), 'nonce' => wp_create_nonce( 'scheduler-delete-'.$media->get_id() ) );
			else {
				postbot_log_error( $user->get_user_id(), 'Unable to store uploaded file - '.$media->get_error_message(), print_r( $_FILES, true ) );
				$result = array( 'error' => $media->get_error_message() );
			}
		}
	}
	else {
		$result = array( 'error' => __( 'Not a valid upload - please try another file' ) );
		postbot_log_error( $user->get_user_id(), 'Invalid upload', print_r( $_FILES, true ) );
	}
}
else {
	$result = array( 'error' => __( 'Unable to perform action. Please refresh your browser and try again.' ) );
	postbot_log_error( $user->get_user_id(), 'Invalid nonce check uploading', print_r( $_FILES, true ) );
}

echo json_encode( $result );
