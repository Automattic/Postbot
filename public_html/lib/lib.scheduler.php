<?php

class Postbot_Time {
	const DEFAULT_INTERVAL = 1;
	const DEFAULT_INTERVAL_MAX = 31;

	private $start_date;
	private $interval;
	private $ignore_weekend = false;

	public function __construct( $start_date, $interval, $ignore_weekend = false ) {
		$interval = intval( $interval );

		if ( $interval > self::DEFAULT_INTERVAL_MAX || $interval === 0 )
			$interval = self::DEFAULT_INTERVAL;

		$this->interval       = $interval;
		$this->start_date     = $start_date;
		$this->ignore_weekend = $ignore_weekend;

		// If the start date is a weekend and we need to ignore then bump the start date forward
		if ( $this->ignore_weekend ) {
			$start = new DateTime( '@'.$this->start_date );

			if ( $start->format( 'w' ) == 0 )
				$start->add( new DateInterval( 'P1D' ) );   // Skip Sunday
			elseif ( $start->format( 'w' ) == 6 )
				$start->add( new DateInterval( 'P2D' ) );   // Skip Saturday and Sunday

			$this->start_date = $start->format( 'U' );
		}
	}

	public function get_time( $schedule_position ) {
		$start    = new DateTime( '@'.$this->start_date );
		$interval = new DateInterval( 'P'.$this->interval.'D' );

		for ( $pos = 0; $pos < $schedule_position; $pos++ ) {
			$start->add( $interval );

			if ( $this->ignore_weekend ) {
				if ( $start->format( 'w' ) == 0 )
					$start->add( new DateInterval( 'P1D' ) );   // Skip Sunday
				elseif ( $start->format( 'w' ) == 6 )
					$start->add( new DateInterval( 'P2D' ) );   // Skip Saturday and Sunday
			}
		}

		return $start->format( 'U' );
	}

	public function get_as_full_date( $schedule_position ) {
		$time = $this->get_time( $schedule_position );

		return date_i18n( 'jS F', $time );
	}

	public function get_as_date( $schedule_position ) {
		$time = $this->get_time( $schedule_position );

		return date_i18n( 'jS M', $time );
	}

	public function get_as_day_of_week( $schedule_position ) {
		$time = $this->get_time( $schedule_position );

		return date_i18n( 'l', $time );
	}

	public static function get_start_time( $js_unix_time, $hour = false, $minute = false ) {
		$js_unix_time = intval( $js_unix_time );

		// Don't allow times more than 1 day ago
		if ( $js_unix_time < time() - ( DAY_IN_MINUTES * 60 ) )
			$js_unix_time = time();

		if ( $hour )
			$js_unix_time = mktime( intval( $hour ), intval( $minute ), 0, date( 'n', $js_unix_time ), date( 'd', $js_unix_time ), date( 'Y', $js_unix_time ) );

		return $js_unix_time;
	}
}

class Postbot_Post {
	private $blog_id;
	private $post_data = array();
	private $attachment;

	public function __construct( $blog_id, $title, $content, $tags ) {
		$this->blog_id = $blog_id;
		$this->post_data = array(
			'title'   => $title,
			'content' => $content,
			'tags'    => $tags,
		);

		if ( empty( $this->post_data['title'] ) )
			$this->post_data['title'] = __( 'Photo' );

		$this->post_data = array_map( 'trim', $this->post_data );
		$this->post_data = array_map( 'stripslashes', $this->post_data );
	}

	private function get_media_name( $filename, $post_title ) {
		if ( defined( 'POSTBOT_RENAME_IMAGES' ) && POSTBOT_RENAME_IMAGES === true ) {
			$parts = pathinfo( $filename );

			$filename = preg_replace( '/[[:punct:]]/', '', $post_title );
			$filename = str_replace( ' ', '-', $filename );

			$filename = trim( $filename );
			$filename .= '.'.$parts['extension'];
		}

		return strtolower( $filename );
	}

	public function create_new_post( $access_token, $time, Postbot_Photo $media, $gmt_offset ) {
		$client = new WPCOM_Rest_Client( $access_token );
		$post = new WP_Error( 'new-post', __( 'Unable to find upload' ) );

		$local_copy = postbot_get_photo( $media->get_stored_name() );
		if ( $local_copy ) {
			$post_data = $this->post_data;
			$post_data['media[]'] = '@'.$local_copy.';filename='.$this->get_media_name( $media->get_filename(), $this->post_data['title'] );

			if ( $time > time() + ( $gmt_offset * 60 * 60 ) ) {
				$post_data['date'] = date( 'Y-m-d\TH:i:s', $time );
				$min_offset = ceil( abs( $gmt_offset ) ) - abs( $gmt_offset );
				if ( $min_offset > 0 )
					$min_offset = ( 60 * ( 1 - $min_offset ) );

				$post_data['date'] .= sprintf( '%s%02s:%02d', $gmt_offset < 0 ? '-' : '+', abs( intval( floor( $gmt_offset ) ) ), $min_offset );
			}

			$result = $client->new_post( $this->blog_id, $post_data );

			if ( !is_wp_error( $result ) ) {
				$post = $this->extract_post_from_api( $result );

				if ( !is_wp_error( $post ) && stripos( $this->post_data['content'], '[image]' ) !== false )
					$this->replace_image( $access_token, $post );
			}
			else
				$post = $result;

			postbot_forget_photo( $local_copy );
		}
		else
			$post = new WP_Error( 'new-post', __( 'Unable to copy media file' ) );

		return $post;
	}

	private function replace_image( $access_token, $existing_post ) {
		$img = sprintf( '<a href="%s"><img src="%s" alt="%s" width="%d" height="%d" class="aligncenter size-full wp-image-%d" /></a>', esc_url( $this->attachment->URL ), esc_url( $this->attachment->URL ), esc_attr( $this->post_data['title'] ), $this->attachment->width, $this->attachment->height, $this->attachment->ID );
		$content = str_replace( '[image]', $img, $this->post_data['content'] );

		$client = new WPCOM_Rest_Client( $access_token );
		$client->update_post( $this->blog_id, $existing_post['post_id'], array( 'content' => $content ) );
	}

	private function extract_post_from_api( $result ) {
		$attachments = (array)$result->attachments;
		if ( empty( $attachments ) ) {
			postbot_log_error( 0, 'Created a post with no attachment', print_r( $this, true ) );
			return new WP_Error( 'new-post', __( 'Unable to create a post with image' ) );
		}

		$this->attachment = array_values( $attachments );
		$this->attachment = $this->attachment[0];

		return array(
			'url'        => $result->URL,
			'time'       => strtotime( $result->date ),
			'title'      => $result->title,
			'short'      => $result->short_URL,
			'post_id'    => $result->ID,
			'attachment' => $this->attachment->URL,
		);
	}
}

class Postbot_Blog {
	private $blog_url;
	private $blog_name;
	private $blog_id;
	private $blog_auth_token;
	private $blavatar_url;
	private $gmt_offset;

	public function __construct( $data ) {
		$this->blog_url        = $data->blog_url;
		$this->blog_id         = $data->blog_id;
		$this->blog_name       = $data->blog_name;
		$this->blog_auth_token = $data->access_token;
		$this->blavatar_url    = $data->blavatar_url;
		$this->gmt_offset      = $data->gmt_offset;
	}

	public function get_blog_id() {
		return $this->blog_id;
	}

	public function get_blog_name() {
		return $this->blog_name;
	}

	public function get_blog_url() {
		return $this->blog_url;
	}

	public function get_access_token() {
		return $this->blog_auth_token;
	}

	public function get_gmt_offset() {
		return $this->gmt_offset;
	}

	public static function save( $user_id, $blog_id, $blog, $access_token = null ) {
		global $wpdb;

		$blavatar_url = self::extract_blavatar( $blog );
		$blog_url     = $blog->URL;
		$blog_name    = $blog->name;
		$gmt_offset   = 0;

		if ( isset( $blog->options->gmt_offset ) )
			$gmt_offset = floatval( $blog->options->gmt_offset );

		if ( empty( $blog_name ) )
			$blog_name = parse_url( $blog_url, PHP_URL_HOST );

		$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->postbot_blogs} WHERE user_id=%d AND blog_id=%d", $user_id, $blog_id ) );

		$blog_data = array(
			'access_token' => $access_token,
			'blog_name'    => $blog_name,
			'blog_url'     => $blog_url,
			'blavatar_url' => $blavatar_url,
			'gmt_offset'   => $gmt_offset,
		);

		if ( $existing_id )
			$wpdb->update( $wpdb->postbot_blogs, $blog_data, array( 'user_id' => $user_id, 'blog_id' => $blog_id ) );
		else
			$wpdb->insert( $wpdb->postbot_blogs, array_merge( $blog_data, array( 'blog_id' => $blog_id, 'user_id' => $user_id ) ) );
	}

	public static function authorize_blog( $user, $authorize_code ) {
		$api    = new WPCOM_Rest_Client();
		$access = $api->request_access_token( $authorize_code, OAUTH_KEY, OAUTH_SECRET, OAUTH_REDIRECT_URL );

		 if ( !is_wp_error( $access ) && $access->token_type == 'bearer' ) {
			$api  = new WPCOM_Rest_Client( $access->access_token );
			$blog = $api->get_blog_details( $access->blog_id );

			if ( $blog && !is_wp_error( $blog ) ) {
				$blog = Postbot_Blog::save( $user->user_id, $access->blog_id, $blog, $access->access_token );
				$user->set_last_blog_id( $access->blog_id );
				return true;
			}

			return $blog;
		}

		return $access;
	}

	public static function extract_blavatar( $details ) {
		// Jetpack has no Blavatar yet - return false so we can display a default one
		if ( $details->jetpack == 1 )
			return false;

		$blavatar_url = $details->URL;

		if ( strpos( $details->URL, 'wordpress.com' ) === false )
			$blavatar_url = $details->meta->links->xmlrpc;

		$blavatar_url = parse_url( $blavatar_url, PHP_URL_HOST );
		$hash         = md5( $blavatar_url );

		if ( is_ssl() )
			$host = 'https://secure.gravatar.com';
		else
			$host = sprintf( 'http://%d.gravatar.com', hexdec( $hash[0] ) % 2 );

		return $host . '/blavatar/' . $hash;
	}

	public function is_authorized() {
		if ( !empty( $this->blog_auth_token ) )
			return true;
		return false;
	}

	public function get_blavatar_url( $size = 96 ) {
		$blavatar = $this->blavatar_url;
		if ( empty( $blavatar ) )
			$blavatar = 'http://en.wordpress.com/i/webclip.png';

		$params = array();
		$size   = absint( $size );

		if ( $size > 0 )
			$params['s'] = $size;

		return $blavatar . '?' . http_build_query( $params );
	}

	public static function remove_for_user( $user_id, $blog_id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postbot_blogs} WHERE user_id=%d AND blog_id=%d", $user_id, $blog_id ) );
	}
}

class Postbot_Scheduler {
	protected function get_media_item( array $items, $item_id ) {
		foreach ( $items AS $item ) {
			if ( $item->get_id() == $item_id )
				return $item;
		}

		return false;
	}

	public function post_media( Postbot_User $user, Postbot_Blog $blog, array $data, array $media_items ) {
		global $wpdb;

		$start_date = Postbot_Time::get_start_time( strtotime( $data['schedule_date'] ), $data['schedule_time_hour'], $data['schedule_time_minute'] );
		$scheduled  = array();
		$skip       = isset( $data['ignore_weekend'] ) ? true : false;
		$post_time  = new Postbot_Time( $start_date, $data['schedule_interval'], $skip );
		$pos        = intval( $data['pos'] );

		if ( isset( $data['media_id'] ) )
			$data['schedule_title'] = array( $data['media_id'] => $data['schedule_title'][$data['media_id']] );

		foreach ( $data['schedule_title'] AS $media_id => $post_title ) {
			$media = $this->get_media_item( $media_items, $media_id );

			if ( $media ) {
				$blog_post = new Postbot_Post( $blog->get_blog_id(), $post_title, $data['schedule_content'][$media_id], $data['schedule_tags'][$media_id] );

				$result = $blog_post->create_new_post( $blog->get_access_token(), $post_time->get_time( $pos ), $media, $blog->get_gmt_offset() );
				if ( is_wp_error( $result ) )
					return $result;

				$posted_id = $this->store_publish_data( $user, $blog, $result );

				// Mark the photo as being scheduled
				$wpdb->update( $wpdb->postbot_photos, array( 'scheduled_at' => current_time( 'mysql' ), 'posted_id' => $posted_id ), array( 'media_id' => $media_id ) );

				$result['media_id'] = $media_id;
				$scheduled[] = array_map( 'stripslashes', $result );

				$pos++;
			}
		}

		// Finally we mark the last blog ID so it will start at this the next time round
		$user->set_last_blog_id( $blog->get_blog_id() );
		return $scheduled;
	}

	private function store_publish_data( $user, $blog, $result ) {
		global $wpdb;

		$publish_data = array(
			'user_id'      => $user->get_user_id(),
			'blog_id'      => $blog->get_blog_id(),
			'post_id'      => $result['post_id'],
			'title'        => $result['title'],
			'post_url'     => $result['url'],
			'media_url'    => $result['attachment'],
			'publish_date' => date( 'Y-m-d H:i:s', $result['time'] ),
		);

		if ( substr( $publish_data['media_url'], 0, 4 ) !== 'http' ) {
			// Posts to Jetpack blogs return a relative URL - make it absolute to the blog
			$new_media_url  = parse_url( $publish_data['post_url'], PHP_URL_SCHEME );
			$new_media_url .= '://';
			$new_media_url .= parse_url( $publish_data['post_url'], PHP_URL_HOST );
			$new_media_url .= $publish_data['media_url'];

			$publish_data['media_url'] = $new_media_url;
		}

		$wpdb->insert( $wpdb->postbot_posts, $publish_data );

		return $wpdb->insert_id;
	}

	public function schedule_get_dates( $values ) {
		$total = min( intval( $values['total'] ), POSTBOT_MAX_SCHEDULE );
		$start = Postbot_Time::get_start_time( strtotime( $values['date'] ), $values['hour'], $values['minute'] );
		$skip  = false;
		$times = array();
		$interval = intval( $values['interval'] );

		if ( isset( $values['ignore_weekend'] ) && intval( $values['ignore_weekend'] ) === 1 )
			$skip = true;

		for ( $loop = 0; $loop < $total; $loop++ ) {
			$schedule_time = new Postbot_Time( $start, $interval, $skip );
			$times[] = array( 'date' => $schedule_time->get_as_date( $loop ), 'day' => $schedule_time->get_as_day_of_week( $loop ) );
		}

		return $times;
	}
}

class Postbot_User {
	public $wpcc_access_token;
	private $password;
	private $blogs = false;

	public $user_id;
	public $username;
	public $display_name;
	public $avatar;
	public $last_blog_id;
	public $profile;

	public $ID;

	public function __construct( $data ) {
		$this->user_id           = $data->user_id;
		$this->username          = $data->username;
		$this->display_name      = $data->display_name;
		$this->avatar            = str_replace( 'http:', '//', $data->avatar );
		$this->profile           = $data->profile;
		$this->wpcc_access_token = $data->wpcc_access_token;
		$this->password          = $data->password;
		$this->last_blog_id      = $data->last_posted_blog_id;

		$this->ID = $data->user_id;
	}

	public function has_cap( $thing ) {
		return true;
	}

	public static function get_from_cookie() {
		global $current_user;

		if ( isset ( $_COOKIE[POSTBOT_COOKIE_AUTH] ) ) {
			$cookie_elements = explode( '|', $_COOKIE[POSTBOT_COOKIE_AUTH] );

			if ( count( $cookie_elements ) == 3 ) {
				$user_id     = intval( $cookie_elements[0] );
				$expiration  = intval( $cookie_elements[1] );
				$cookie_hash = $cookie_elements[2];

				if ( $user_id > 0 && ( $expiration > time() || $expiration == 0 ) ) {
					$user = self::get_user( $user_id );

					if ( $user ) {
						$hash = self::get_password_hash( $user->user_id, $user->password, $expiration );

						if ( $hash == $cookie_hash ) {
							self::set_auth_password( $user_id, $user->password );
							$current_user = $user;
							return $user;
						}
					}
				}
			}
		}

		return false;
	}

	public static function get_user( $user_id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_users} WHERE user_id=%d", $user_id ) );
		if ( $row )
			return new Postbot_User( $row );
		return false;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	private static function get_password_hash( $user_id, $password, $expiration ) {
		$password_frag = mb_substr( $password, 8, 5 );
		$key           = hash_hmac( 'md5', $user_id . $password_frag . '|' . $expiration, wp_salt() );

		return hash_hmac( 'md5', $user_id . '|' . POSTBOT_COOKIE_EXPIRE, $key );
	}

	public static function set_auth_password( $user_id, $password ) {
		$hash         = self::get_password_hash( $user_id, $password, POSTBOT_COOKIE_EXPIRE );
		$cookie_value = implode( '|', array( $user_id, POSTBOT_COOKIE_EXPIRE, $hash ) );

		setcookie( POSTBOT_COOKIE_AUTH, $cookie_value, POSTBOT_COOKIE_EXPIRE, POSTBOT_COOKIE_PATH, POSTBOT_COOKIE_DOMAIN, POSTBOT_COOKIE_SSL, true );
	}

	public static function set_access_token( $user_details, $token, $blog_details ) {
		global $wpdb;

		$hash = new PasswordHash( 8, true );

		$data = array(
			'username'            => $user_details->username,
			'display_name'        => $user_details->display_name,
			'avatar'              => $user_details->avatar_URL,
			'wpcc_access_token'   => $token,
			'password'            => $hash->HashPassword( $token ),
			'profile'             => $user_details->profile_URL,
			'last_posted_blog_id' => $user_details->primary_blog,
		);

		$user = self::get_user( $user_details->ID );
		if ( $user )
			$wpdb->update( $wpdb->postbot_users, $data, array( 'user_id' => $user_details->ID ) );
		else {
			$data['user_id'] = $user_details->ID;
			$wpdb->insert( $wpdb->postbot_users, $data );

			$user = self::get_user( $user_details->ID );
		}

		if ( $user ) {
			if ( $blog_details )
				Postbot_Blog::save( $user_details->ID, $user_details->primary_blog, $blog_details );

			self::set_auth_password( $user_details->ID, $data['password'] );
			return true;
		}

		return false;
	}

	public function logout() {
		setcookie( POSTBOT_COOKIE_AUTH, '', time() - YEAR_IN_SECONDS, POSTBOT_COOKIE_PATH, POSTBOT_COOKIE_DOMAIN, POSTBOT_COOKIE_SSL, true );
	}

	public function get_connected_blogs() {
		global $wpdb;

		if ( $this->blogs === false ) {
			$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_blogs} WHERE user_id=%d", $this->user_id ) );

			$this->blogs = array();
			foreach ( $blogs AS $blog ) {
				$this->blogs[] = new Postbot_Blog( $blog );
			}
		}

		return $this->blogs;
	}

	public function get_blog( $blog_id ) {
		foreach ( $this->get_connected_blogs() AS $blog ) {
			if ( $blog->get_blog_id() == $blog_id )
				return $blog;
		}

		return false;
	}

	public function get_last_blog() {
		$blog = $this->get_blog( $this->last_blog_id );
		if ( !$blog && count( $this->blogs ) > 0 ) {
			// Get any blog
			$blog = $this->blogs[0];
			if ( !$blog ) {
				return false;
			}

			$this->set_last_blog_id( $blog->get_blog_id() );
		}

		return $blog;
	}

	public function set_last_blog_id( $blog_id ) {
		global $wpdb;

		$this->last_blog_id = $blog_id;
		$wpdb->update( $wpdb->postbot_users, array( 'last_posted_blog_id' => $blog_id ), array( 'user_id' => $this->user_id ) );
	}
}

class Postbot_Auto extends Postbot_Scheduler {
	private $user;
	private $pending = false;
	private $start_date = 0;
	private $skip_weekend = false;
	private $interval = 1;
	private $auto_publish = false;

	public function __construct( Postbot_User $user, $blog = false ) {
		$this->user = $user;
		$this->start_date = mktime( date( 'H' ), 0, 0, date( 'n' ), date( 'j' ) + 1, date( 'Y' ) );

		$gmt_offset = 0;
		if ( $blog ) {
			$gmt_offset = ( $blog->get_gmt_offset() * 60 * 60 );
			$this->start_date += $gmt_offset;
		}

		if ( isset( $_COOKIE[POSTBOT_COOKIE_SETTING] ) ) {
			$parts = explode( '|', $_COOKIE[POSTBOT_COOKIE_SETTING] );

			$date               = intval( $parts[0] );
			$this->interval     = intval( $parts[1] );
			$this->skip_weekend = intval( $parts[2] );
			$this->auto_publish = intval( $parts[3] );

			if ( $date > time() + $gmt_offset )
				$this->start_date = $date;

			if ( $this->publish_immediately() )
				$this->clear_publish_immediatley();
		}
	}

	public function get_start_date() {
		return $this->start_date;
	}

	public function can_skip_weekend() {
		return $this->skip_weekend;
	}

	public function get_interval() {
		return $this->interval;
	}

	public function publish_immediately() {
		return $this->auto_publish;
	}

	private function load_pending() {
		global $wpdb;

		if ( $this->pending === false )
			$this->pending = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_auto} WHERE user_id=%d ORDER BY position", $this->user->get_user_id() ) );
	}

	public function get_data_for_media( Postbot_Photo $media ) {
		$this->load_pending();

		foreach ( $this->pending AS $pending ) {
			if ( $pending->media_id == $media->get_id() )
				return $pending;
		}

		return false;
	}

	public function store_for_later( Postbot_Blog $blog, array $data, array $media_items ) {
		global $wpdb;

		$total = 0;

		foreach ( (array)$data['schedule_title'] AS $media_id => $post_title ) {
			$media = $this->get_media_item( $media_items, $media_id );

			if ( $media ) {
				$pending = array(
					'post_title'   => $post_title,
					'post_content' => $data['schedule_content'][$media_id],
					'post_tags'    => $data['schedule_tags'][$media_id],
					'position'     => $total,
				);

				$pending = array_map( 'stripslashes', $pending );

				if ( $wpdb->get_var( $wpdb->prepare( "SELECT media_id FROM {$wpdb->postbot_auto} WHERE media_id=%d", $media_id ) ) )
					$wpdb->update( $wpdb->postbot_auto, $pending, array( 'media_id' => $media_id ) );
				else {
					$pending['media_id'] = $media_id;
					$pending['user_id']  = $this->user->get_user_id();

					$wpdb->insert( $wpdb->postbot_auto, $pending );
				}

				$total++;
			}
		}

		$this->user->set_last_blog_id( $blog->get_blog_id() );
		$this->store_time_for_later( strtotime( $data['schedule_date'] ), $data['schedule_time_hour'], $data['schedule_time_minute'], intval( $data['schedule_interval'] ), isset( $data['ignore_weekend'] ) ? true : false );
		return $total;
	}

	public function store_time_for_later( $date, $hour, $minute, $interval, $skip ) {
		$start_date = Postbot_Time::get_start_time( $date, $hour, $minute );

		setcookie( POSTBOT_COOKIE_SETTING, implode( '|', array( $start_date, $interval, $skip ? 1 : 0, 0 ) ), POSTBOT_COOKIE_EXPIRE, POSTBOT_COOKIE_PATH, POSTBOT_COOKIE_DOMAIN, POSTBOT_COOKIE_SSL, true );
	}

	public function set_publish_immediatley() {
		setcookie( POSTBOT_COOKIE_SETTING, implode( '|', array( $this->start_date, $this->interval, $this->skip_weekend ? 1 : 0, 1 ) ), POSTBOT_COOKIE_EXPIRE, POSTBOT_COOKIE_PATH, POSTBOT_COOKIE_DOMAIN, POSTBOT_COOKIE_SSL, true );
	}

	public function clear_publish_immediatley() {
		setcookie( POSTBOT_COOKIE_SETTING, implode( '|', array( $this->start_date, $this->interval, $this->skip_weekend ? 1 : 0, 0 ) ), POSTBOT_COOKIE_EXPIRE, POSTBOT_COOKIE_PATH, POSTBOT_COOKIE_DOMAIN, POSTBOT_COOKIE_SSL, true );
	}

	public static function clear_for_media( Postbot_Photo $media ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postbot_auto} WHERE media_id=%d", $media->get_id () ) );
	}

	public function clear() {
		global $wpdb;

		setcookie( POSTBOT_COOKIE_SETTING, '', time() - YEAR_IN_SECONDS, POSTBOT_COOKIE_PATH, POSTBOT_COOKIE_DOMAIN, POSTBOT_COOKIE_SSL, true );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postbot_auto} WHERE user_id=%d", $this->user->get_user_id() ) );
	}

	public function reorder_items( array $media_items ) {
		$this->load_pending();
		$new_order = array();

		if ( count( $this->pending ) > 0 ) {
			foreach ( $this->pending AS $item ) {
				foreach ( $media_items AS $media_item ) {
					if ( $media_item->get_id() == $item->media_id ) {
						$new_order[] = $media_item;
						break;
					}
				}
			}
		}
		else
			$new_order = $media_items;

		return $new_order;
	}
}

class Postbot_Pending {
	private $posted_id;
	private $user_id;
	private $blog_id;
	private $stored_name;
	private $image_height;
	private $image_width;

	public $post_id;
	public $publish_date;
	public $title;
	public $post_url;
	public $media_url;

	public function __construct( $data ) {
		foreach ( $data AS $name => $value ) {
			if ( property_exists( $this, $name ) )
				$this->$name = $value;
		}

		$this->title = stripslashes( $this->title );
		$this->publish_date = mysql2date( 'U', $this->publish_date );
	}

	public function get_id() {
		return $this->posted_id;
	}

	private function get_thumbnail_name() {
		$parts = pathinfo( $this->stored_name );
		if ( count( $parts ) > 0 && isset( $parts['extension'] ) )
			return $parts['filename'].'-thumb.'.$parts['extension'];
		return false;
	}

	public function get_thumbnail_url() {
		return postbot_photo_url( $this->get_thumbnail_name() );
	}

	public function get_thumbnail_img() {
		$size = min( POSTBOT_THUMBNAIL_SIZE, $this->image_width );

		return '<img class="preview" src="'.$this->get_thumbnail_url().'" width="'. $size.'"/>';
	}

	public static function get_by_id( $posted_id, Postbot_User $user ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_posts} WHERE posted_id=%d AND user_id=%d", $posted_id, $user->get_user_id() ) );
		if ( $row )
			return new Postbot_Pending( $row );
		return false;
	}

	public static function get_for_user( Postbot_User $user ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT posts.*,photos.stored_name,photos.image_width,photos.image_height FROM {$wpdb->postbot_posts} AS posts LEFT JOIN {$wpdb->postbot_photos} AS photos ON posts.posted_id=photos.posted_id WHERE posts.user_id=%d AND posts.publish_date > NOW() ORDER BY publish_date", $user->get_user_id() ) );
		$posts = array();

		foreach ( $rows AS $row ) {
			$posts[mysql2date( 'U', $row->publish_date )] = new Postbot_Pending( $row );
		}

		ksort( $posts );
		return $posts;
	}

	public function delete() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postbot_posts} WHERE posted_id=%d", $this->posted_id ) );

		// Get photos that refer to this
		$photos = Postbot_Photo::get_for_post( $this->posted_id );
		foreach ( $photos AS $photo ) {
			$photo->delete();
		}
	}

	public function get_blog() {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_blogs} WHERE blog_id=%d", $this->blog_id ) );
		if ( $row )
			return new Postbot_Blog( $row );
		return false;
	}
}
