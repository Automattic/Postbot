<?php

/**
 * Postbot configuration
 */

define( 'POSTBOT_VERSION', 4 );          // Used for cache busting
define( 'POSTBOT_MAX_SCHEDULE', 25 );    // Max number of items to schedule at once
define( 'POSTBOT_MAX_UPLOAD', 10 );      // Max upload size, in MB
define( 'POSTBOT_LOCAL_STORE', dirname( __FILE__ ).'/uploads/' );
define( 'POSTBOT_LOCAL_URL', '/postbot/uploads/' );
define( 'POSTBOT_THUMBNAIL_SIZE', 150 ); // Note this is the size on screen. A retina ready version will actually be produced

/**
 * Where Postbot lives
 */
define( 'BASE_URL', '/postbot/' );
define( 'SCHEDULE_URL', BASE_URL );
define( 'PENDING_URL', BASE_URL.'pending.php' );

/**
 * Postbot Cookies
 */
define( 'POSTBOT_COOKIE_SSL', false );
define( 'POSTBOT_COOKIE_AUTH', 'POSTBOT_AUTH' );
define( 'POSTBOT_COOKIE_SETTING', 'POSTBOT_SETTINGS' );
define( 'POSTBOT_COOKIE_DOMAIN', 'YOURDOMAIN' );
define( 'POSTBOT_COOKIE_EXPIRE', 0 );
define( 'POSTBOT_COOKIE_PATH', BASE_URL );

define( 'POSTBOT_LOGIN_SALT', 'SOMELONGMESSAGE' );   // Salt for login cookie

function postbot_is_live() {
	if ( isset( $_SERVER['SERVER_NAME'] ) && $_SERVER['SERVER_NAME'] == POSTBOT_COOKIE_DOMAIN )
		return true;
	return false;
}

define( 'OAUTH_KEY', APP_KEY );
define( 'OAUTH_SECRET', 'APP_SECRET' );
define( 'OAUTH_REDIRECT_URL', 'APP_REDIRECT' );

define( 'OAUTH_WPCC_KEY', WPCC_KEY );
define( 'OAUTH_WPCC_SECRET', 'WPCC_SECRET' );
define( 'OAUTH_WPCC_REDIRECT_URL', 'WPCC_REDIRECT' );

$postbot_tables = array(
	'postbot_auto'   => 'postbot_auto',
	'postbot_blogs'  => 'postbot_blogs',
	'postbot_errors' => 'postbot_errors',
	'postbot_photos' => 'postbot_photos',
	'postbot_posts'  => 'postbot_posts',
	'postbot_users'  => 'postbot_users',
);

include dirname( __FILE__ ).'/postbot-local.php';
