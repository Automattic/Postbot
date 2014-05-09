Postbot
=======

Scheduled posting of photos using the [WordPress.com REST API](https://developer.wordpress.com/docs/api/).

Photos are stored locally before being scheduled on a blog. All scheduling and blog authorization is done via the REST API. Once scheduled, photos are shown in a pending list and can be deleted (through the API) before they are posted.

Scheduling involves picking a start date and the number of days between each post. Weekends can be avoided.

Nothing is sent to a blog without explicit instructions.

It is possible to connect multiple blogs.

Postbot is responsive and mobile ready. It is fully localized, although no languages files are provided.

API Usage
=========

The following API commands are used:
- [GET /sites/$site](https://developer.wordpress.com/docs/api/1/get/sites/%24site/) - information about a blog
- [POST /sites/$site/posts/new](https://developer.wordpress.com/docs/api/1/post/sites/%24site/posts/new/) - create blog post
- [POST /sites/$site/posts/$post_ID/delete](https://developer.wordpress.com/docs/api/1/post/sites/%24site/posts/%24post_ID/delete/) - delete a pending post

Requirements
============

- PHP 5 with GD or ImageMagick
- A working WordPress with connection to MySQL
- An upload directory writeable to by the web server

Installation
============

- Get WordPress installed and connected to your database. You don't need to have WordPress available to anyone, but the database functions are used by Postbot
- Edit `postbot-local.php` and changed the WordPress include to refer to your WordPress installation
- Create the [Postbot tables](https://github.com/Automattic/Postbot/blob/master/postbot.sql)
- Create a new [oAuth app](https://developer.wordpress.com/apps/) for the WordPress.com Connect signin. Set the redirect_uri to be the `wpcc.php` file in Postbot.
- Edit `postbot-config.php` and set the `OAUTH_WPCC_KEY`, `OAUTH_WPCC_SECRET`, and `OAUTH_WPCC_REDIRECT` to the details in the oAuth app
- Create another oAuth app for the WordPress.com blog authorisation. Set the `redirect_uri` to be the `index.php` file in Postbot.
- Edit `postbot-config.php` and set the `OAUTH_KEY`, `OAUTH_SECRET`, and `OAUTH_REDIRECT` to the details in this oAuth app
- Edit other settings in `postbot-config.php` as appropriate

Vagrant
=======

Postbot comes with a Vagrantfile that will create a working Postbot environment. To use:

1. Install [Vagrant](http://www.vagrantup.com/)
2. Install [VirtualBox](https://www.virtualbox.org)
3. Install [HostsUpdater plugin](https://github.com/cogitatio/vagrant-hostsupdater)
4. Go to the Postbot root directory and run `vagrant up`

Postbot is available from `local.postbot.dev`
