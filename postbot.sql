CREATE TABLE `postbot_auto` (
  `pending_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `media_id` int(10) unsigned NOT NULL,
  `post_title` varchar(200) NOT NULL DEFAULT '',
  `post_tags` varchar(200) NOT NULL DEFAULT '',
  `post_content` text NOT NULL,
  `schedule_at` datetime NOT NULL,
  PRIMARY KEY (`pending_id`),
  UNIQUE KEY `media_id` (`media_id`),
  KEY `user_id` (`user_id`)
) CHARSET=utf8;

CREATE TABLE `postbot_blogs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `blog_id` bigint(20) NOT NULL,
  `blog_name` varchar(100) NOT NULL,
  `blog_url` varchar(200) NOT NULL DEFAULT '',
  `blavatar_url` varchar(100) NOT NULL,
  `access_token` varchar(200) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`blog_id`)
) CHARSET=utf8;

CREATE TABLE `postbot_errors` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `error_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `message` varchar(100) NOT NULL DEFAULT '',
  `file` varchar(100) NOT NULL,
  `line` int(11) NOT NULL,
  `data` text,
  PRIMARY KEY (`id`)
) CHARSET=utf8;

CREATE TABLE `postbot_photos` (
  `media_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `media_type` varchar(15) NOT NULL,
  `created_at` datetime NOT NULL,
  `image_width` int(11) NOT NULL DEFAULT '0',
  `image_height` int(11) NOT NULL DEFAULT '0',
  `size` int(11) NOT NULL DEFAULT '0',
  `scheduled_at` datetime DEFAULT NULL,
  `posted_id` int(11) NOT NULL,
  PRIMARY KEY (`media_id`),
  UNIQUE KEY `stored_name` (`stored_name`),
  KEY `user_id` (`user_id`,`scheduled_at`),
  KEY `scheduled_at` (`scheduled_at`),
  KEY `posted_id` (`posted_id`)
) CHARSET=utf8;

CREATE TABLE `postbot_posts` (
  `posted_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `blog_id` bigint(20) NOT NULL,
  `post_id` int(11) NOT NULL,
  `publish_date` datetime NOT NULL,
  `title` varchar(100) NOT NULL DEFAULT '',
  `post_url` varchar(100) NOT NULL DEFAULT '',
  `media_url` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`posted_id`),
  KEY `user_id` (`user_id`,`publish_date`)
) CHARSET=utf8;

CREATE TABLE `postbot_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL DEFAULT '0',
  `username` varchar(100) DEFAULT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `avatar` varchar(100) DEFAULT NULL,
  `profile` varchar(100) DEFAULT NULL,
  `wpcc_access_token` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(35) NOT NULL DEFAULT '',
  `last_posted_blog_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) CHARSET=utf8;
