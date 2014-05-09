#!/usr/bin/env bash

WP_USERNAME=postbot
WP_PASSWORD=postbot_password
WP_DB_NAME=postbot

WP_CONFIG_FILE=/vagrant/wordpress/wp-config.php
PB_CONFIG_FILE=/vagrant/public_html/postbot-config.php
PB_LOCAL_FILE=/vagrant/public_html/postbot-local.php
PB_UPLOAD_DIR=/vagrant/public_html/uploads

VHOST=$(cat <<EOF
<VirtualHost *:80>
  DocumentRoot "/var/www/public_html"
  ServerName localhost
  <Directory "/var/www/public_html">
    AllowOverride All
  </Directory>
</VirtualHost>
EOF
)

sudo debconf-set-selections <<< 'mysql-server-5.5 mysql-server/root_password password rootpass'
sudo debconf-set-selections <<< 'mysql-server-5.5 mysql-server/root_password_again password rootpass'

apt-get update
apt-get install -y mysql-server-5.5 php5-mysql apache2 php5 subversion

# MySQL
if [ ! -f /var/log/databasesetup ];
then
    echo "CREATE USER '$WP_USERNAME'@'localhost' IDENTIFIED BY '$WP_PASSWORD'" | mysql -uroot -prootpass
    echo "CREATE DATABASE $WP_DB_NAME" | mysql -uroot -prootpass
    echo "GRANT ALL ON $WP_DB_NAME.* TO '$WP_USERNAME'@'localhost'" | mysql -uroot -prootpass
    echo "flush privileges" | mysql -uroot -prootpass

	sed -i 's/127.0.0.1/0.0.0.0/g' /etc/mysql/my.cnf
	service mysql restart

    touch /var/log/databasesetup

    if [ -f /vagrant/postbot.sql ];
    then
        mysql -uroot -prootpass postbot < /vagrant/postbot.sql
    fi
fi

# Apache
if [ ! -h /var/www ];
then
	rm -rf /var/www
	ln -fs /vagrant/public_html /var/www

	a2enmod rewrite

	sed -i '/AllowOverride None/c AllowOverride All' /etc/apache2/sites-available/default

	echo "${VHOST}" > /etc/apache2/sites-enabled/000-default
	service apache2 restart
fi

# WordPress
if [ ! -d /vagrant/wordpress ];
then
	svn co http://core.svn.wordpress.org/trunk/ /vagrant/wordpress

	cp /vagrant/wordpress/wp-config-sample.php $WP_CONFIG_FILE
	sed -i 's/database_name_here/$WP_DB_NAME/' $WP_CONFIG_FILE
	sed -i 's/username_here/$WP_USERNAME/' $WP_CONFIG_FILE
	sed -i 's/password_here/$WP_PASSWORD/' $WP_CONFIG_FILE
else
	svn up /vagrant/wordpress
fi

# Upload dir
if [ ! -d $PB_UPLOAD_DIR ];
then
	mkdir $PB_UPLOAD_DIR
	chmod go+w $PB_UPLOAD_DIR
fi

# Postbot
if [ ! -f /vagrant/public_html/postbot-config.php ];
then
	cp /vagrant/postbot-config-wp.php $PB_CONFIG_FILE
	cp /vagrant/postbot-local-wp.php $PB_LOCAL_FILE
	cp /vagrant/.htaccess $PB_UPLOAD_DIR

	sed -i 's/\/path\/to\/wordpress/\/vagrant\/wordpress/' $PB_CONFIG_FILE
	sed -i 's/\/postbot\/uploads/\/vagrant\/public_html\/uploads/' $PB_CONFIG_FILE
	sed -i 's/\/postbot\///' $PB_CONFIG_FILE
	sed -i 's/YOURDOMAIN/127.0.0.1/' $PB_CONFIG_FILE
fi
