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

- PHP with GD or ImageMagick
- A working WordPress with connection to MySQL
- An upload directory writeable to by the web server
