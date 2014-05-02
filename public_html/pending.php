<?php

include dirname( __FILE__ ).'/postbot-config.php';
include dirname( __FILE__ ).'/lib/lib.scheduler.php';
include dirname( __FILE__ ).'/lib/lib.uploader.php';
include dirname( __FILE__ ).'/lib/lib.wpcom-rest.php';
include dirname( __FILE__ ).'/lib/lib.display.php';
include dirname( __FILE__ ).'/lib/lib.actions.php';

$user = Postbot_User::get_from_cookie();
if ( !$user ) {
	include dirname( __FILE__ ).'/wpcc.php';
	die();
}

$pending = Postbot_Pending::get_for_user( $user );
if ( count( $pending ) == 0 ) {
	wp_safe_redirect( SCHEDULE_URL );
	die();
}

handle_pending_actions( $user, $pending);

$last_blog = $user->get_last_blog();

$scripts_css = postbot_bundled_css();
$scripts_js  = postbot_bundled_javascript();

$body_class = false;
if ( wp_is_mobile() )
	$body_class .= ' mobile';

?><!DOCTYPE html>
<html lang="en">
<head>
	<title><?php _e( 'Pending Posts' ); ?> | <?php _e( 'Postbot' ); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<meta charset="UTF-8" />
	<link rel="icon" href="favicon.ico">
	<link rel="apple-touch-icon-precomposed" href="apple-touch-icon.png" sizes="512x512" />

	<?php postbot_output_javascript( $scripts_js ); ?>
	<?php postbot_output_css( $scripts_css ); ?>
</head>
<body class="<?php echo $body_class; ?>">
	<?php display_navigation_menu( $user, $last_blog, true, 'pending' ); ?>

	<div class="container">
		<div class="row" id="uploaded">
			<div class="col-md-12">
				<ul class="schedule-list">
					<?php foreach ( $pending AS $pos => $post ) : ?>
						<?php $blog = $post->get_blog(); ?>
						<li>
							<div class="schedule-thumb">
								<a href="<?php echo esc_url( $post->post_url ); ?>" target="_blank">
									<?php echo $post->get_thumbnail_img(); ?>
								</a>
							</div>

							<div class="schedule-info">
								<h5><?php echo esc_html( $post->title ); ?></h5>

								<?php printf( __( 'Will be published on <em>%1s</em> on %2s' ), esc_html( $blog->get_blog_name() ), date_i18n( 'M jS, Y', $post->publish_date ) ); ?>

								<div class="schedule-pending-actions">
									<?php if ( $blog && $blog->is_authorized() ) : ?>
										<a href="#" class="schedule-delete-pending" data-id="<?php echo esc_attr( $post->get_id() ); ?>" data-nonce="<?php echo wp_create_nonce( 'pending-delete-'.$post->get_id() ); ?>"><?php _e( 'Delete' ); ?></a> |
									<?php endif; ?>

									<a href="<?php echo esc_url( $post->post_url ); ?>" target="_blank"><?php _e( 'Preview' ); ?></a>
								</div>
							</div>

							<div class="clearfix"></div>
						</li>

					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>

	<script type="text/javascript">
	var postbot = {
		ajax_url: '<?php echo esc_js( PENDING_URL ); ?>',
		nonce: '<?php echo wp_create_nonce( 'scheduler' ); ?>',
		are_you_sure: '<?php echo esc_js( __( 'Are you sure?' ) ) ?>',
		scheduler_url: '<?php echo esc_js( SCHEDULE_URL ); ?>'
	};
	</script>
</body>
</html>
