<?php

function handle_pending_actions( Postbot_User $user, array $pending_items ) {
	$response = false;

	handle_get_actions( $user );

	if ( isset( $_POST['action'] ) && $_POST['action'] == 'postbot_delete_pending' )
		$response = handle_delete_pending_item( $user );
	elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'postbot_set_blog' ) {
		$media_items = Postbot_Photo::get_for_user( $user->user_id );
		$response = handle_set_blog( $user, $media_items );
	}

	if ( $response ) {
		echo json_encode( $response );
		die();
	}

	return false;
}

function handle_scheduler_actions( Postbot_User $user, array $media_items ) {
	if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
		return handle_post_actions( $user, $media_items );
	return handle_get_actions( $user );
}

function handle_get_actions( Postbot_User $user ) {
	if ( isset( $_GET['code'] ) )
		handle_authorize_blog( $user );
	elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'logout' )
		handle_logout( $user );

	return false;
}

function handle_post_actions( Postbot_User $user, array $media_items ) {
	$response = false;

	if ( isset( $_POST['action'] ) && $_POST['action'] == 'postbot_get_dates' )
		$response = handle_get_dates( $user );
	elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'postbot_delete' )
		$response = handle_delete_item( $user );
	elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'postbot_set_blog' )
		$response = handle_set_blog( $user, $media_items );
	elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'postbot_uploading' )
		$response = handle_pre_upload( $user, $media_items );
	elseif ( isset( $_POST['schedule_title'] ) )
		$response = handle_schedule( $user, $media_items );

	if ( $response ) {
		echo json_encode( $response );
		die();
	}

	return false;
}

function handle_logout( Postbot_User $user ) {
	$user->logout();
	wp_safe_redirect( SCHEDULE_URL );
	die();
}

function handle_set_blog( Postbot_User $user, $media_items ) {
	$blog_id = intval( $_POST['blog_id'] );

	if ( wp_verify_nonce( $_POST['nonce'], 'scheduler-blog-'.$blog_id ) ) {
		$user->set_last_blog_id( $blog_id );

		return array(
			'button' => sprintf( _n( 'Schedule %d post on %s', 'Schedule %d posts on %s', count( $media_items ) ), count( $media_items ), $user->get_last_blog()->get_blog_name() )
		);
	}

	postbot_log_error( $user->get_user_id(), 'Invalid nonce check setting blog', print_r( $_POST, true ) );
	return array( 'error' => __( 'Unable to perform action. Please refresh your browser and try again.' ) );
}

function handle_authorize_blog( Postbot_User $user ) {
	// Called when authing a blog
	if ( Postbot_Blog::authorize_blog( $user, $_GET['code'] ) )
		wp_safe_redirect( SCHEDULE_URL );
	else
		wp_safe_redirect( SCHEDULE_URL.'?msg=failedauth' );

	die();
}

function handle_pre_upload( Postbot_User $user, array $media_items ) {
	$interval = intval( $_POST['interval'] );
	$time     = intval( $_POST['date'] );
	$ignore   = false;

	if ( isset( $_POST['ignore_weekend'] ) && intval( $_POST['ignore_weekend'] ) === 1 )
		$ignore = true;

	if ( wp_verify_nonce( $_POST['nonce'], 'scheduler' ) ) {
		$file_data  = array();
		$start_date = Postbot_Time::get_start_time( $time, intval( $_POST['hour'] ), intval( $_POST['minute'] ) );
		$time       = new Postbot_Time( $start_date, $interval, $ignore );
		$blog       = $user->get_last_blog();

		foreach ( $_POST['files'] AS $pos => $file ) {
			$new_data = array();

			$file_pos = count( $media_items ) + $pos;
			$filename = Postbot_Photo::get_title_from_filename( $file['filename'], $time, $file_pos );

			$new_data['id']   = $file['id'];
			$new_data['html'] = str_replace( array( "\n", "\t" ), '', get_schedule_item_html( null, $time, $file_pos ) );
			$new_data['html'] = str_replace( '[id]', $file['id'], $new_data['html'] );
			$new_data['html'] = str_replace( '[filename]', $filename, $new_data['html'] );

			$file_data[] = $new_data;
		}

		$file_pos = count( $media_items ) + count( $_POST['files'] );

		return array(
			'files' => $file_data,
			'button' => sprintf( _n( 'Schedule %d post on %s', 'Schedule %d posts on %s', $file_pos ), $file_pos, $blog->get_blog_name() ),
		);
	}

	postbot_log_error( $user->get_user_id(), 'Invalid nonce check getting upload data', print_r( $_POST, true ) );
	return array( 'error' => __( 'Unable to perform action. Please refresh your browser and try again.' ) );
}

function handle_delete_item( Postbot_User $user ) {
	// AJAX handler for updating schedule times
	if ( wp_verify_nonce( $_POST['nonce'], 'scheduler-delete-'.$_POST['media_id'] ) ) {
		$media = Postbot_Photo::get_by_id( intval( $_POST['media_id'] ) );

		if ( $media->get_user_id() == $user->get_user_id() ) {
			if ( $media->delete() )
				return array( 'success' => true );
		}

		postbot_log_error( $user->get_user_id(), 'Failed deleting item', print_r( $_POST, true ) );
		return array( 'error' => __( 'Sorry, we were unable to delete your photo!' ) );
	}

	postbot_log_error( $user->get_user_id(), 'Invalid nonce check deleting item', print_r( $_POST, true ) );
	return array( 'error' => __( 'Unable to perform action. Please refresh your browser and try again.' ) );
}

function handle_get_dates( Postbot_User $user ) {
	$total    = intval( $_POST['total'] );
	$interval = intval( $_POST['interval'] );
	$time     = intval( $_POST['date'] );

	// AJAX handler for updating schedule times
	if ( wp_verify_nonce( $_POST['nonce'], 'scheduler' ) ) {
		$blog       = $user->get_last_blog();
		$scheduler  = new Postbot_Scheduler();
		$start_date = Postbot_Time::get_start_time( $time, intval( $_POST['hour'] ), intval( $_POST['minute'] ) );

		$schedule_text = _n( 'Schedule Post', 'Schedule Posts', $total );

		if ( $total == 1 ) {
			$body_text_2 = sprintf( __( 'The post will go out on %s.' ), date_i18n( 'l, F jS', $start_date ) );
			$body_text_1 = sprintf( __( '%d post to be scheduled on %s.' ), $total, esc_html( $blog->get_blog_name() ) );
		}
		else {
			$body_text_1 = sprintf( _n( '%d posts to be scheduled on %s, with %d day between each post.', '%d posts to be scheduled on %s, with %d days between each post.', $interval ), $total, esc_html( $blog->get_blog_name() ), $interval );
			$body_text_2 = sprintf( __( 'The first post will go out on %s.' ), date_i18n( 'l, F jS', $start_date ) );
		}

		$response = array(
			'text'            => date_i18n( 'l, F jS', $start_date ),
			'time'            => date_i18n( 'H:i', $start_date ),
			'dates'           => $scheduler->schedule_get_dates( $_POST ),
			'button'          => sprintf( _n( 'Schedule %d post on %s', 'Schedule %d posts on %s', $total ), $total, $blog->get_blog_name() ),
			'body_text_1'     => $body_text_1,
			'body_text_2'     => $body_text_2,
			'schedule_text'   => $schedule_text,
			'scheduling_text' => sprintf( _n( 'Your post is being scheduled, please wait.', 'Your posts are being scheduled, please wait.', $total ), $total )
		);

		return $response;
	}

	postbot_log_error( $user->get_user_id(), 'Invalid nonce check getting dates', print_r( $_POST, true ) );
	return array( 'error' => __( 'Unable to perform action. Please refresh your browser and try again.' ) );
}

function handle_schedule( Postbot_User $user, $media_items ) {
	if ( wp_verify_nonce( $_POST['schedule_nonce'], 'scheduler-blog-'.intval( $_POST['schedule_on_blog'] ) ) ) {
		// Does the user have access to the blog?
		$blog = $user->get_blog( intval( $_POST['schedule_on_blog'] ) );
		if ( $blog ) {
			$auto = new Postbot_Auto( $user );

			if ( $blog->is_authorized() ) {
				$scheduler = new Postbot_Scheduler();
				$scheduled = $scheduler->post_media( $user, $blog, $_POST, $media_items );

				if ( is_wp_error( $scheduled ) ) {
					Postbot_Blog::remove_for_user( $user->get_user_id(), $blog->get_blog_id() );
					postbot_log_error( $user->get_user_id(), 'Failed schedule - '.$scheduled->get_error_message(), print_r( $_POST, true ) );
					return array( 'error' => __( 'Failed to schedule' ), 'redirect' => SCHEDULE_URL.'?msg=failedschedule' );
				}

				do_action( 'postbot_scheduled', $scheduled );

				return $scheduled;
			}

			$auto->store_for_later( $blog, $_POST, $media_items );

			return array( 'redirect' => WPCOM_Rest_Client::get_blog_auth_url( $blog->get_blog_url() ) );
		}
		else {
			postbot_log_error( $user->get_user_id(), 'No access to blog while scheduling', print_r( $_POST, true ) );
			return array( 'error' => __( 'You have no access to that blog.' ) );
		}
	}

	postbot_log_error( $user->get_user_id(), 'Invalid nonce check scheduling', print_r( $_POST, true ) );
	return array( 'error' => __( 'Unable to perform action. Please refresh your browser and try again.' ) );
}

function handle_delete_pending_item( Postbot_User $user ) {
	if ( wp_verify_nonce( $_POST['nonce'], 'pending-delete-'.intval( $_POST['id'] ) ) ) {
		$pending = Postbot_Pending::get_by_id( intval( $_POST['id'] ), $user );

		if ( $pending ) {
			$blog = $pending->get_blog();

			if ( $blog && $blog->is_authorized() ) {
				$client = new WPCOM_Rest_Client( $blog->get_access_token() );
				$response = $client->delete_blog_post( $blog->get_blog_id(), $pending->post_id );

				if ( !is_wp_error( $response ) ) {
					do_action( 'postbot_delete_pending', $blog->get_blog_id(), $pending->post_id );

					$pending->delete();
					return array( 'success' => true );
				}

				postbot_log_error( $user->get_user_id(), 'Failed to delete pending item', print_r( $_POST, true ) );
				return array( 'error' => __( 'Unable to delete post on your blog - do you still have access?' ) );
			}

			postbot_log_error( $user->get_user_id(), 'Failed to delete pending item', print_r( $_POST, true ) );
			return array( 'error' => __( 'You do not have access to this blog.' ) );
		}

		postbot_log_error( $user->get_user_id(), 'Attempt to delete an item that doesnt exist', print_r( $_POST, true ) );
		return array( 'error' => __( 'Cannot delete that item' ) );
	}

	postbot_log_error( $user->get_user_id(), 'Invalid nonce check deleting pending item', print_r( $_POST, true ) );
	return array( 'error' => __( 'Unable to perform action. Please refresh your browser and try again.' ) );
}
