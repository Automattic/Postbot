<?php

class Postbot_Uploader {
	const MAX_IMAGE_SIZE = 5200;

	private $user_id;

	private $file_size;
	private $file_type;
	private $original_name;
	private $stored_name;
	private $image_width;
	private $image_height;

	public function __construct( $user_id ) {
		$this->user_id = $user_id;
	}

	public function upload_file( $uploaded_name, $uploaded_file ) {
		$file_size = filesize( $uploaded_file );
		$uploaded_name = strtolower( sanitize_file_name( $uploaded_name ) );
		$uploaded_name = str_replace( array( ';', "'", '"' ), '', $uploaded_name );

		$filetype = wp_check_filetype_and_ext( $uploaded_file, $uploaded_name );
		$type = $filetype['type'];

		if ( $filetype['proper_filename'] )
			$uploaded_name = $filetype['proper_filename'];

		if ( $type == 'image/bmp' || !$this->is_image( $type ) )
			return new WP_Error( 'upload', __( 'Unsupported file type' ) );

		$this->file_size     = $file_size;
		$this->file_type     = $type;
		$this->original_name = basename( $uploaded_name );
		$this->stored_name   = sprintf( '%s-%d.%s', md5( $this->user_id.time() ), microtime( true ), strtolower( $filetype['ext'] ) );

		// If an image, get some extra details
		$size = getimagesize( $uploaded_file );

		$this->image_width  = $size[0];
		$this->image_height = $size[1];

		if ( $size[0] > self::MAX_IMAGE_SIZE || $size[1] > self::MAX_IMAGE_SIZE )
			return new WP_Error( 'upload', __( 'Image is too big' ) );
		elseif ( $this->file_size == 0 )
			return new WP_Error( 'upload', __( 'Image has no size' ) );

		if ( postbot_store_photo( $uploaded_file, $this->stored_name ) ) {
			global $wpdb;

			$media_data = array(
				'user_id'      => $this->user_id,
				'name'         => $this->original_name,
				'stored_name'  => $this->stored_name,
				'media_type'   => $this->file_type,
				'created_at'   => current_time( 'mysql' ),
				'image_width'  => $this->image_width,
				'image_height' => $this->image_height,
				'size'         => $this->file_size,
			);

			do_action( 'postbot_upload', $media_data );

			$wpdb->insert( $wpdb->postbot_photos, $media_data );

			$this->create_thumbnail( $uploaded_file, POSTBOT_THUMBNAIL_SIZE * 2, POSTBOT_THUMBNAIL_SIZE * 2 );
			return Postbot_Photo::get_by_id( $wpdb->insert_id );
		}

		postbot_log_error( $this->user_id, 'Unable to store photo' );
		return new WP_Error( 'upload', __( 'Unable to store photo - please try again' ) );
	}

	private function create_thumbnail( $uploaded_file, $new_width, $new_height ) {
		if ( $this->image_width > $new_width || $this->image_height > $new_height ) {
			$new_target = postbot_crop_photo( $uploaded_file, $new_width, $new_height );

			if ( is_wp_error( $new_target ) )
				$new_target = $uploaded_file;
		}
		else {
			$new_width  = $this->image_width;
			$new_height = $this->image_height;
			$new_target = $uploaded_file;
		}

		$parts           = pathinfo( $this->stored_name );
		$new_target_name = $parts['filename'].'-thumb.'.$parts['extension'];

		$result = postbot_store_photo( $new_target, $new_target_name );
		if ( !$result )
			postbot_log_error( $this->user_id, 'Unable to store thumbnail' );

		postbot_forget_photo( $new_target );
		return $result;
	}

	private function is_image( $file_type ) {
		return strpos( $file_type, 'image/' ) !== false;
	}
}

class Postbot_Photo {
	private $media_id;
	private $user_id;
	private $name;
	private $stored_name;
	private $media_type;
	private $created_at;
	private $image_width;
	private $image_height;
	private $size;
	private $scheduled;

	public function __construct( $data ) {
		foreach ( $data AS $key => $value ) {
			if ( property_exists( $this, $key ) )
				$this->$key = $value;
		}

		$this->created_at = mysql2date( 'U', $this->created_at );
	}

	public function get_media_type() {
		return $this->media_type;
	}

	public function get_thumnail_name() {
		$parts = pathinfo( $this->stored_name );
		return $parts['filename'].'-thumb.'.$parts['extension'];
	}

	public function get_thumbnail_url() {
		return postbot_photo_url( $this->get_thumnail_name() );
	}

	public function get_thumbnail_img() {
		$size = min( POSTBOT_THUMBNAIL_SIZE, $this->image_width );

		return '<img class="preview" src="'.$this->get_thumbnail_url().'" width="'.$size.'"/>';
	}

	public function get_id() {
		return $this->media_id;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function get_stored_name() {
		return $this->stored_name;
	}

	public function get_filename() {
		return $this->name;
	}

	public static function get_title_from_filename( $filename, $time, $pos ) {
		$default  = sprintf( __( 'Photo for %s, %s' ), $time->get_as_day_of_week( $pos ), $time->get_as_full_date( $pos ) );

		$filename = preg_replace( '/\.\w*$/', '', stripslashes( $filename ) );
		$filename = str_replace( array( '@' ), ' ', $filename );

		$camera_filenames = array(
			'/^\w{1,3}\d+/'
		);

		foreach ( $camera_filenames AS $regex ) {
			if ( preg_match( $regex, $filename ) > 0 )
				return $default;
		}

		$filename = strtolower( $filename );
		$filename = str_replace( array( '_', '-' ), ' ', $filename );
		$filename = trim( $filename );
		$filename = ucwords( $filename );

		// iOS devices set filename to 'Image' - pick something better
		if ( $filename == 'Image' )
			$filename = $default;

		return $filename;
	}

	public static function get_by_id( $media_id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_photos} WHERE media_id=%d", $media_id ) );
		if ( $row )
			return new Postbot_Photo( $row );
		return false;
	}

	public static function get_by_stored_name( $stored_name ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_photos} WHERE stored_name=%s", $stored_name ) );
		if ( $row )
			return new Postbot_Photo( $row );
		return false;
	}

	public static function get_for_user( $user_id ) {
		global $wpdb;

		$photos = array();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_photos} WHERE user_id=%d AND scheduled_at IS NULL", $user_id ) );
		foreach ( $rows AS $row ) {
			$photos[] = new Postbot_Photo( $row );
		}

		return $photos;
	}

	public static function get_for_post( $post_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postbot_photos} WHERE posted_id=%d", $post_id ) );
		$photos = array();

		foreach ( $rows AS $row ) {
			$photos[] = new Postbot_Photo( $row );
		}

		return $photos;
	}

	public function delete() {
		global $wpdb;

		if ( postbot_delete_photo( $this->stored_name ) && postbot_delete_photo( $this->get_thumnail_name() ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postbot_photos} WHERE media_id=%d AND user_id=%d", $this->media_id, $this->user_id ) );
			return true;
		}

		return false;
	}

	public static function cleanup_old_photos( $days_old = 1 ) {
		global $wpdb;

		$old = $wpdb->get_results( $wpdb->prepare( "SELECT photos.*,posts.publish_date FROM {$wpdb->postbot_photos} AS photos LEFT JOIN {$wpdb->postbot_posts} AS posts ON posts.posted_id=photos.posted_id WHERE scheduled_at < DATE_SUB(NOW(),INTERVAL %d DAY)", $days_old ) );

		foreach ( $old AS $pos => $photo ) {
			if ( $old->publish_date && mysql2date( 'U', $old->publish_date ) < time() ) {
				$media = new Postbot_Photo( $photo );
				$media->delete();
			}

			if ( $pos % 100 )
				sleep( 1 );
		}
	}
}
