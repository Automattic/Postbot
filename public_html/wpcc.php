<?php

if ( !class_exists( 'Postbot_User' ) ) {
	include dirname( __FILE__ ).'/postbot-config.php';
	include dirname( __FILE__ ).'/lib/lib.scheduler.php';
	include dirname( __FILE__ ).'/lib/lib.wpcom-rest.php';
}

$oauth_client = new WPCOM_OAuth_Bearer_Client( OAUTH_WPCC_KEY, OAUTH_WPCC_SECRET, OAUTH_WPCC_REDIRECT_URL );

if ( isset( $_GET[ 'code' ] ) ) {
	try {
		$request_token = $oauth_client->get_request_token();
		$access_token  = $oauth_client->get_access_token( $request_token );

		$client       = new WPCOM_Rest_Client( $access_token );
		$user_details = $client->get_user_details();

		if ( $user_details && !is_wp_error( $user_details ) ) {
			$blog = $client->get_blog_details( $user_details->primary_blog );

			if ( $blog && Postbot_User::set_access_token( $user_details, $access_token, $blog ) ) {
				wp_safe_redirect( SCHEDULE_URL );
				die();
			}

			postbot_log_error( 0, 'Unable to get blog details', $user_details->primary_blog );
		}
		else
			postbot_log_error( 0, 'Unable to get user details' );
	}
	catch ( WPCOM_OAuth_Exception $exception ) {
		postbot_log_error( 0, 'oAuth Exception - '.$exception->getMessage(), $user_details->primary_blog );
	}

	wp_safe_redirect( '?msg=failed' );
	die();
}
elseif ( isset( $_GET['login'] ) ) {
	$oauth_client->redirect_to_authorization_url();
}
?><html>
<head>
	<title><?php _e( 'Postbot' ); ?></title>
	<link rel="stylesheet" type="text/css" href="css/boot.css"/>
	<link rel="stylesheet" type="text/css" href="https://wpeditor.org/dashicons.css"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<link rel="icon" href="favicon.ico">
	<link rel="apple-touch-icon-precomposed" href="apple-touch-icon.png" sizes="512x512" />
</head>
<body>
	<div id="content">
		<div class="dashboard-container">
			<?php if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'failed' ) : ?>
				<div class="error">
					<?php _e( 'Something went wrong authorizing your WordPress.com account, please try again' ); ?>
				</div>
			<?php endif; ?>

			<div class="wp-logo icon wp"></div>

			<p class="welcome">
				<strong><?php _e( 'Welcome.' ); ?></strong> <?php _e( 'Schedule your photos' ); ?>
			</p>

			<div class="sign-button">
				<a href="<?php echo SCHEDULE_URL; ?>?login">
					<img src="//s0.wp.com/i/wpcc-button.png" width="231" />
				</a>
			</div>
		</div>
	</div>
</body>
</html>
