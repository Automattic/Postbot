<?php

if ( !class_exists( 'Postbot_User' ) ) {
	include dirname( __FILE__ ).'/postbot-config.php';
	include dirname( __FILE__ ).'/lib/lib.scheduler.php';
	include dirname( __FILE__ ).'/lib/lib.wpcom-rest.php';
}

$oauth_client = new WPCOM_OAuth_Bearer_Client( OAUTH_WPCC_KEY, OAUTH_WPCC_SECRET, OAUTH_WPCC_REDIRECT_URL );

if ( isset( $_GET[ 'code' ] ) ) {
	$fail = 'failed';

	try {
		$request_token = $oauth_client->get_request_token();
		$access_token  = $oauth_client->get_access_token( $request_token );

		$client       = new WPCOM_Rest_Client( $access_token );
		$user_details = $client->get_user_details();

		if ( $user_details && !is_wp_error( $user_details ) ) {
			$blog = $client->get_blog_details( $user_details->primary_blog );  // this gets private blogs

			if ( !$blog || is_wp_error( $blog ) ) {
				// Failed, try it without a token - gets everything else
				$client = new WPCOM_Rest_Client();
				$blog = $client->get_blog_details( $user_details->primary_blog );
			}

			if ( is_wp_error( $blog ) )
				$blog = false;

			Postbot_User::set_access_token( $user_details, $access_token, $blog );
			wp_safe_redirect( SCHEDULE_URL );
			die();
		}
		else
			postbot_log_error( 0, 'Unable to get user details' );
	}
	catch ( WPCOM_OAuth_Exception $exception ) {
		postbot_log_error( 0, 'oAuth Exception - '.$exception->getMessage() );
	}

	wp_safe_redirect( '?msg='.$failed );
	die();
}
elseif ( isset( $_GET['login'] ) ) {
	$oauth_client->redirect_to_authorization_url();
}

$message = false;
if ( isset( $_GET['msg'] ) ) {
	$message = __( 'Something went wrong authorizing your WordPress.com account, please try again' );
}
?><html>
<head>
	<title><?php _e( 'Postbot' ); ?></title>
	<link rel="stylesheet" type="text/css" href="css/boot.css"/>
	<link rel="stylesheet" type="text/css" href="//s0.wordpress.com/wp-content/mu-plugins/genericons/genericons.css"/>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<link rel="icon" href="favicon.ico">
	<link rel="apple-touch-icon-precomposed" href="apple-touch-icon.png" sizes="512x512" />
</head>
<body>
	<div id="content">
		<div class="dashboard-container">
			<?php if ( $message ) : ?>
				<div class="alert alert-danger" id="message">
					<?php echo $message; ?>
				</div>
			<?php endif; ?>

			<p class="welcome">
				<strong><?php _e( 'Welcome to Postbot.' ); ?></strong><br> <?php _e( 'Upload one, two, or 50 photos and schedule them to publish individually on different dates with only a few clicks!' ); ?>
			</p>

			<div class="postbot-anim">
				<video width="752" height="376" autoplay="autoplay" src="//postbot.co/postbot.mp4" type="video/mp4" loop="" id="video">
					<object data="//postbot.co/postbot.mp4" width="752" height="376">
						<embed src="//postbot.co/postbot.mp4" width="752" height="376"/>
						Your browser does not support video
					</object>
				</video>
			</div>

			<div class="sign-button">
				<a href="<?php echo SCHEDULE_URL; ?>?login">
					<img src="//s0.wp.com/i/wpcc-button.png" width="231" />
				</a>
			</div>

			<p class="footer">
				<?php _e( 'An <a href="http://automattic.com">Automattic</a> Machine' ); ?>
			</p>
		</div>

		<a id="support-link" href="http://en.support.wordpress.com/postbot/" title="<?php esc_attr_e( 'Help' ); ?>"><?php _e( 'Help' ); ?></a>
	</div>

	<script type="text/javascript">
		var video = document.getElementById('video');
		window.addEventListener('load',function(){
			video.setAttribute("controls","controls");
		},false);
	</script>
</body>
</html>
