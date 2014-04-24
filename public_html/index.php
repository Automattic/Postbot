<?php

include dirname( __FILE__ ).'/postbot-config.php';
include dirname( __FILE__ ).'/lib/lib.uploader.php';
include dirname( __FILE__ ).'/lib/lib.wpcom-rest.php';
include dirname( __FILE__ ).'/lib/lib.scheduler.php';
include dirname( __FILE__ ).'/lib/lib.display.php';
include dirname( __FILE__ ).'/lib/lib.actions.php';

$user = Postbot_User::get_from_cookie();

if ( !$user ) {
	include dirname( __FILE__ ).'/wpcc.php';
	die();
}

$media_items = Postbot_Photo::get_for_user( $user->user_id );
$last_blog   = $user->get_last_blog();
$notice      = false;

handle_scheduler_actions( $user, $media_items );

$auto_publish = new Postbot_Auto( $user, $last_blog );
$start_date   = $auto_publish->get_start_date();
$media_items  = $auto_publish->reorder_items( $media_items );

if ( isset( $_GET['msg'] ) ) {
	if ( $_GET['msg'] == 'failedauth' )
		$notice = __( 'Unable to authorize your blog. Please try again.' );
	elseif ( $_GET['msg'] == 'unauthorized' )
		$notice = sprintf( __( '<p>Unable to authorize your blog.</p><p>If using <em>WordPress.org</em> with Jetpack then please enable the <a href="%s">JSON API module</a>.</p><p>If using <em>WordPress.com</em> then your blog cannot be used, please contact <a href="%s">support</a>.</p>' ), 'http://jetpack.me/support/json-api/', 'http://en.support.wordpress.com' );
	elseif ( $_GET['msg'] == 'jetpack' && isset( $_GET['jetpack'] ) )
		$notice = sprintf( __( 'Failed to connect using Jetpack. Refer to this <a href="%s">Jetpack support guide</a> for more details:<br/>' ), 'http://jetpack.me/support/getting-started-with-jetpack/what-do-these-error-messages-mean/' ).'<code>'.esc_html( $_GET['jetpack'] ).'</code>';
	elseif ( $_GET['msg'] == 'failedschedule' )
		$notice = __( 'Unable to publish to blog - your authorization may have been revoked. Please re-authorize the blog and try again.' );
}
elseif ( isset( $_GET['error'] ) && $_GET['error'] == 'access_denied' ) {
	$notice = __( 'Unable to authorize a connection to your blog. Please try again.' );
}

$body_class = 'media-items-';
if ( count( $media_items ) == 0 )
	$body_class .= 'none';
elseif ( count( $media_items ) == 1 )
	$body_class .= 'single';
else
	$body_class .= 'multiple';

$pending = Postbot_Pending::get_for_user( $user );

$submit_text = $body_text_1 = $body_text_2 = $schedule_text = '';
$scheduling_text = sprintf( _n( 'Your post is being scheduled, please wait.', 'Your posts are being scheduled, please wait.', count( $media_items ) ), count( $media_items ) );

if ( $last_blog ) {
	$submit_text   = sprintf( _n( 'Schedule %d post on %s', 'Schedule %d posts on %s', count( $media_items ) ), count( $media_items ), $last_blog->get_blog_name() );
	$body_text_1   = sprintf( '%d posts to be scheduled on %s, with %d day between each post.', count( $media_items ), esc_html( $last_blog->get_blog_name() ), 1 );
	$body_text_2   = sprintf( __( 'The first post will go out on %s.' ), date_i18n( 'l, F jS', $start_date ) );
	$schedule_text = _n( 'Schedule Post', 'Schedule Posts', count( $media_items ) );

	if ( count( $media_items ) == 1 ) {
		$body_text_1 = sprintf( __( '%d post to be scheduled on %s.' ), count( $media_items ), esc_html( $last_blog->get_blog_name() ) );
		$body_text_2 = sprintf( __( 'The post will go out on %s.' ), date_i18n( 'l, F jS', $start_date ) );
	}
}

if ( wp_is_mobile() )
	$body_class .= ' mobile';

if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && stripos( $_SERVER['HTTP_USER_AGENT'], 'ipad' ) !== false )
	$body_class .= ' ipad';

$scripts_css = postbot_bundled_css();
$scripts_js  = postbot_bundled_javascript();

?><!DOCTYPE html>
<html lang="en">
<head>
	<title><?php _e( 'Postbot' ); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
	<meta charset="UTF-8" />
	<link rel="icon" href="favicon.ico">
	<link rel="apple-touch-icon-precomposed" href="apple-touch-icon.png" sizes="512x512" />

	<?php postbot_output_javascript( $scripts_js ); ?>
	<?php postbot_output_css( $scripts_css ); ?>
</head>
<body class="<?php echo $body_class; ?>">
	<?php display_navigation_menu( $user, $last_blog, count( $pending ) > 0 ? true : false, 'schedule' ); ?>

	<div id="upload-help-drop">
	</div>

	<div class="container">
		<div class="alert alert-danger" id="message"<?php if ( $notice === false ) echo ' style="display: none"' ?>>
			<?php echo $notice; ?>
		</div>

		<form action="" method="POST" enctype="multipart/form-data" class="media-upload-form form-inline">

		<div class="row" id="uploaded">
			<ul class="schedule-list sortable">
				<?php foreach ( $media_items AS $pos => $media ) : ?>
					<?php
						$time         = new Postbot_Time( $start_date, 1 );
						$scheduler    = new Postbot_Scheduler();
						$pending_data = $auto_publish->get_data_for_media( $media );

						echo get_schedule_item_html( $media, $time, $pos, $pending_data );
					?>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="row">
			<div id="uploader">
				<?php if ( $last_blog ) : ?>
					<div class="post-interval">
						<div>
							<div class="schedule-dates-interval">
								<?php _e( 'Days between posts' ); ?>

								<select name="schedule_interval" class="schedule-monitor form-control">
									<?php for ( $day = 1; $day <= 31; $day++ ) : ?>
										<option value="<?php echo esc_attr( $day ); ?>"<?php selected( $auto_publish->get_interval(), $day ); ?>>
											<?php echo $day; ?>
										</option>
									<?php endfor; ?>
								</select>
							</div>

							<div class="schedule-pick-times">
								<?php
									$pick_date  = '<input type="hidden" name="schedule_date" value="'.date( 'Y-m-d', $start_date ).'"/>';
									$pick_date .= '<strong><a href="#" id="schedule-pick-date">'.date_i18n( 'l, F jS', $start_date ).'</a></strong>';

									$pick_time = '<strong><a href="#" id="schedule-pick-time">'.date_i18n( 'H:i', $start_date ).'</a></strong>';

									printf( __( 'Start on %s at %s' ), $pick_date, $pick_time );
								?>

								<div id="pick-time">
									<input type="number" name="schedule_time_hour" value="<?php echo date( 'H', $start_date ); ?>" maxlength="2" min="0" max="24"/>
									<input type="number" name="schedule_time_minute" value="<?php echo date( 'i', $start_date ); ?>" maxlength="2" min="0" max="60"/>
									<button><?php _e( 'OK' ); ?></button>
								</div>
							</div>

							<div id="ignore-weekend">
								<label>
									<?php _e( 'Ignore weekends' ); ?>
									<input type="checkbox" name="ignore_weekend"<?php checked( $auto_publish->can_skip_weekend() ); ?>/>
								</label>
							</div>

						</div>

					</div>

					<input type="submit" value="<?php echo esc_attr( $submit_text ); ?>" class="btn btn-primary" id="schedule-submit-button"/>
				<?php else : ?>

					<a class="btn btn-primary" href="<?php echo esc_url( OAUTH_AUTHORIZE_ENDPOINT ); ?>"><?php _e( 'Connect a blog' ); ?></a>

				<?php endif; ?>
			</div>
		</div>

		<div id="upload-help">
			<div>
				<svg x="0px" y="0px" viewBox="0 0 18 13">
				<path d="M16,1H6v10h10V1z M9,3c0.553,0,1,0.448,1,1S9.553,5,9,5C8.448,5,8,4.552,8,4S8.448,3,9,3z M15,10H7
					l2-3l1.5,2.25L14,4l1,1.5V10z M17,3v9H8v1h10V3H17z M3,0H2v2H0v1h2v2h1V3h2V2H3V0z"/>
				</svg>

				<div class="upload-help-text">
					<?php _e( 'Drop Images to Schedule as Posts' ); ?>
				</div>
				<div class="upload-help-mobile">
					<?php _e( 'Upload Photos and Schedule as Posts' ); ?>
				</div>

			</div>
		</div>

		<input type="hidden" name="schedule_on_blog" value="<?php if ( $last_blog ) echo $last_blog->get_blog_id(); else echo '0'; ?>"/>
		<input type="hidden" name="schedule_nonce" value=""/>
		</form>

		<a id="support-link" href="http://en.support.wordpress.com/postbot/" title="<?php esc_attr_e( 'Help' ); ?>"><?php _e( 'Help' ); ?></a>
	</div>

	<div class="modal fade" id="confirm-schedule">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
					<h4 class="modal-title"><?php _e( 'Confirm Schedule' ); ?></h4>
				</div>
				<div class="modal-body">
					<p class="body-text-1"></p>
					<p class="body-text-2"></p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary" id="schedule-confirmed"></button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="schedule-progress">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title"><?php _e( 'Scheduling Posts' ); ?></h4>
				</div>
				<div class="modal-body">
					<p><span><?php echo $scheduling_text; ?></span> <div class="spinner"></div></p>

					<ul>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<div id="uploading-files"><div>
		<?php _e( 'Uploading files, please wait&hellip;' ); ?>

		<span class="uploading-files-editable">
			<?php _e( 'Feel free to edit your photos in the meantime!' ); ?>
		</span>

		<div class="spinner"></div>
	</div></div>

	<script type="text/javascript">
	var postbot = {
		ajax_url: '<?php echo esc_js( SCHEDULE_URL ); ?>',
		nonce: '<?php echo wp_create_nonce( 'scheduler' ); ?>',
		body_text_1: '<?php echo esc_js( $body_text_1 ); ?>',
		body_text_2: '<?php echo esc_js( $body_text_2 ); ?>',
		schedule_text: '<?php echo esc_js( $schedule_text ); ?>',
		scheduling_text: '<?php echo esc_js( $scheduling_text ); ?>',
		pending_url: '<?php echo esc_js( PENDING_URL ); ?>',
		max_upload: <?php echo POSTBOT_MAX_UPLOAD; ?>,
		upload_prompt: '<?php echo esc_js( __( 'Image files' ) ); ?>',
		thumbnail_size: <?php echo POSTBOT_THUMBNAIL_SIZE; ?>
	};

	var auto_publish = <?php if ( $auto_publish->publish_immediately() && $last_blog->is_authorized() ) echo 'true'; else echo 'false'; ?>;
	</script>
</body>
</html>
