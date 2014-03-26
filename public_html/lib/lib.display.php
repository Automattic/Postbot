<?php

function display_navigation_menu( Postbot_User $user, $last_blog, $show_pending_tab, $active ) {
?>
	<nav class="navbar navbar-default" role="navigation">
		<div class="container">
			<div class="navbar-header">
				<a class="navbar-brand" href="<?php echo SCHEDULE_URL; ?>">
					<?php _e( 'Postbot' ); ?>
				</a>
				<a id="responsive-menu-button" href="/" class="icon">Menu</a>
			</div>

			<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
				<ul class="nav navbar-nav navbar-left">
					<li<?php if ( $active == 'schedule' ) echo ' class="active"'; ?>>
						<a href="<?php echo SCHEDULE_URL; ?>"><?php _e( 'Upload &amp; Schedule' ); ?></a>
					</li>
					<?php if ( $show_pending_tab ) : ?>
					<li<?php if ( $active == 'pending' ) echo ' class="active"'; ?>>
						<a href="<?php echo PENDING_URL; ?>"><?php _e( 'Pending' ); ?></a>
					</li>
					<?php endif; ?>
				</ul>
				<ul class="nav navbar-nav navbar-right">
					<li class="dropdown">
						<?php if ( $last_blog ) : ?>
						<a href="<?php echo SCHEDULE_URL; ?>" class="dropdown-toggle" data-toggle="dropdown" id="dropdown-toggle">
							<img src="<?php echo sslize( $last_blog->get_blavatar_url( 40 ) ); ?>" width="20" height="20"/> <span><?php echo esc_html( $last_blog->get_blog_name() ); ?></span>

							<strong class="caret"></strong>
						</a>
						<?php endif; ?>

						<ul class="dropdown-menu">
							<?php if ( $last_blog ) : ?>
								<?php foreach ( $user->get_connected_blogs() AS $blog ) : ?>
									<li class="dropdown-blog <?php if ( $blog->get_blog_id() == $last_blog->get_blog_id() ) echo "selected"; ?>">
										<a class="swap-blog" href="#" id="blog-<?php echo $blog->get_blog_id(); ?>" data-blog-id="<?php echo esc_attr( $blog->get_blog_id() ); ?>" data-blog-name="<?php echo esc_attr( $blog->get_blog_name() ); ?>" data-nonce="<?php echo wp_create_nonce( 'scheduler-blog-'.$blog->get_blog_id() ); ?>" data-blavatar="<?php echo esc_attr( sslize( $blog->get_blavatar_url( 40 ) ) ); ?>">
											<img src="<?php echo sslize( $blog->get_blavatar_url( 40 ) ); ?>" width="20" height="20"/> <?php echo esc_html( $blog->get_blog_name() ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							<?php endif; ?>

							<li id="dropdown-connect">
								<a href="<?php echo esc_url( OAUTH_AUTHORIZE_ENDPOINT ); ?>"><?php _e( 'Connect another blog' ); ?></a>
							</li>

							<li id="dropdown-logout"><a href="?action=logout"><?php _e( 'Log out' ); ?></a></li>
						</ul>
					</li>
				</ul>
			</div>
		</div>
	</nav>
<?php
}

function get_schedule_item_html( Postbot_Photo $media = null, $time = false, $pos = 0, $pending_data = false ) {
	$filename = '';

	if ( $time && $media )
		$filename = $media->get_title_from_filename( $media->get_filename(), $time, $pos );

	$tags     = '';
	$content  = '';

	if ( $pending_data ) {
		$filename = $pending_data->post_title;
		$tags     = $pending_data->post_tags;
		$content  = $pending_data->post_content;
	}

	$media_id  = '[id]';
	$thumbnail = false;

	if ( $media ) {
		$media_id  = $media->get_id();
		$thumbnail = $media->get_thumbnail_img();
	}
	else
		$filename = '[filename]';

	ob_start();
?>
<li class="schedule-item" id="media-item-<?php echo esc_attr( $media_id ); ?>" data-media-id="<?php echo esc_attr( $media_id ); ?>">
	<div class="schedule-thumb">
		<?php if ( $thumbnail ) : ?>

			<?php echo $thumbnail; ?>

		<?php else : ?>

			<img class="pending-upload"/>
			<div class="progress">
				<div class="progress-bar" style="width: 0%">
				</div>

				<?php _e( '<span>0%</span> of <span>1kB</span>' ); ?>
			</div>

		<?php endif; ?>
	</div>

	<div class="schedule-edit">
		<div class="schedule-title">
			<label for="media-item-title-<?php echo esc_attr( $media_id ); ?>" class="sr-only">
				<?php _e( 'Title' ); ?>
			</label>

			<input placeholder="<?php echo esc_attr( 'Title' ); ?>" id="media-item-title-<?php echo esc_attr( $media_id ); ?>" type="text" name="schedule_title[<?php echo esc_attr( $media_id ); ?>]" maxlength="100" size="40" class="form-control schedule-edit-title" value="<?php echo esc_attr( $filename ); ?>"/>
		</div>

		<div class="schedule-tags">
			<label for="media-item-tags-<?php echo esc_attr( $media_id ); ?>" class="sr-only">
				<?php _e( 'Tags' ); ?>
			</label>

			<input placeholder="<?php echo esc_attr( 'Tags' ); ?>" id="media-item-tags-<?php echo esc_attr( $media_id ); ?>" type="text" name="schedule_tags[<?php echo esc_attr( $media_id ); ?>]" maxlength="100" size="40" class="form-control schedule-edit-tags" value="<?php echo esc_attr( $tags ); ?>"/>
		</div>

		<div class="schedule-content">
			<label for="media-item-content-<?php echo esc_attr( $media_id ); ?>" class="sr-only">
				<?php _e( 'Post Content' ); ?>
			</label>

			<textarea placeholder="<?php echo esc_attr( __( 'Post Content. Use [image] to place your image within the content (defaults to end of content).' ) ); ?>" id="media-item-content-<?php echo esc_attr( $media_id ); ?>" name="schedule_content[<?php echo esc_attr( $media_id ); ?>]" class="form-control"><?php echo esc_textarea( $content ); ?></textarea>
		</div>
	</div>

 	<div class="schedule-time">
	<?php if ( $time ) : ?>
		<?php echo esc_html( $time->get_as_day_of_week( $pos ) ); ?><br/>
		<?php echo esc_html( $time->get_as_date( $pos ) ); ?><br/>
	<?php endif; ?>
	</div>

	<div class="schedule-actions">
		<a href="#" data-nonce="<?php echo wp_create_nonce( 'scheduler-delete-'.$media_id ); ?>" class="schedule-remove">
			<?php _e( 'Remove' ); ?>
		</a>
	</div>

	<div class="clearfix"></div>
</li>
<?php
	$value = ob_get_contents();
	ob_end_clean();
	return $value;
}

function postbot_bundled_javascript() {
	if ( postbot_is_live() )
		return array( 'js/compressed.js?v='.POSTBOT_VERSION );

	$bundled = array(
		'js/jquery.js',
		'js/jquery-ui-custom.js',
		'js/bootstrap.js',
		'js/jquery.form.js',
		'js/plupload.min.js',
		'js/postbot.js?v='.time(),
	);

	return $bundled;
}

function postbot_bundled_css() {
	return array(
		'//s0.wordpress.com/wp-content/mu-plugins/genericons/genericons.css',
		'//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,700italic,300,400,700',
		'css/jquery-ui.css',
		'css/postbot.css?v='.time(),
	);
}

function postbot_output_css( array $scripts ) {
	foreach ( $scripts AS $script ) {
		echo '<link rel="stylesheet" type="text/css" href="'.esc_attr( $script ).'"/>';
	}
}

function postbot_output_javascript( array $scripts ) {
	foreach ( $scripts AS $script ) {
		echo '<script type="text/javascript" src="'.esc_attr( $script ).'"></script>';
	}
}
