#!/usr/bin/env bash

WP_USERNAME=postbot
WP_PASSWORD=postbot_password
WP_DB_NAME=postbot

WP_BASE_DIR=/var/www/wordpress
WP_CONFIG_FILE=/var/www/wordpress/wp-config.php

PB_CONFIG_FILE=/vagrant/public_html/postbot-config.php
PB_LOCAL_FILE=/vagrant/public_html/postbot-local.php
PB_UPLOAD_DIR=/vagrant/public_html/uploads
PB_URL=local.postbot.dev

VHOST=$(cat <<EOF
<VirtualHost *:80>
  DocumentRoot "/var/www/postbot/public_html"
  ServerName localhost
  <Directory "/var/www/postbot/public_html">
    AllowOverride All
  </Directory>
</VirtualHost>
EOF
)

sudo debconf-set-selections <<< 'mysql-server-5.5 mysql-server/root_password password rootpass'
sudo debconf-set-selections <<< 'mysql-server-5.5 mysql-server/root_password_again password rootpass'

echo postfix postfix/main_mailer_type select Internet Site | debconf-set-selections
echo postfix postfix/mailname string vvv | debconf-set-selections

# PACKAGE INSTALLATION
#
# Build a bash array to pass all of the packages we want to install to a single
# apt-get command. This avoids doing all the leg work each time a package is
# set to install. It also allows us to easily comment out or add single
# packages. We set the array as empty to begin with so that we can append
# individual packages to it as required.
apt_package_install_list=()

# Start with a bash array containing all packages we want to install in the
# virtual machine. We'll then loop through each of these and check individual
# status before adding them to the apt_package_install_list array.
apt_package_check_list=(
	apache2
	libapache2-mod-php5

	# PHP5
	#
	# Our base packages for php5. As long as php5-fpm and php5-cli are
	# installed, there is no need to install the general php5 package, which
	# can sometimes install apache as a requirement.
	php5-fpm
	php5-cli

	# Common and dev packages for php
	php5-common
	php5-dev

	# Extra PHP modules that we find useful
	php5-imagick
	php5-mcrypt
	php5-mysql
	php5-curl
	php-pear
	php5-gd

	# mysql is the default database
	mysql-server

	# other packages that come in handy
	imagemagick
	subversion
	git-core
	zip
	unzip
	ngrep
	curl
	vim
	colordiff
	postfix
)

echo "Check for apt packages to install..."

# Loop through each of our packages that should be installed on the system. If
# not yet installed, it should be added to the array of packages to install.
for pkg in "${apt_package_check_list[@]}"; do
	package_version="$(dpkg -s $pkg 2>&1 | grep 'Version:' | cut -d " " -f 2)"
	if [[ -n "${package_version}" ]]; then
		space_count="$(expr 20 - "${#pkg}")" #11
		pack_space_count="$(expr 30 - "${#package_version}")"
		real_space="$(expr ${space_count} + ${pack_space_count} + ${#package_version})"
		printf " * $pkg %${real_space}.${#package_version}s ${package_version}\n"
	else
		echo " *" $pkg [not installed]
		apt_package_install_list+=($pkg)
	fi
done

# If there are any packages to be installed in the apt_package_list array,
# then we'll run `apt-get update` and then `apt-get install` to proceed.
if [[ ${#apt_package_install_list[@]} = 0 ]]; then
	echo -e "No apt packages to install.\n"
else
	# Before running `apt-get update`, we should add the public keys for
	# the packages that we are installing from non standard sources via
	# our appended apt source.list

	# Nginx.org nginx key ABF5BD827BD9BF62
	gpg -q --keyserver keyserver.ubuntu.com --recv-key ABF5BD827BD9BF62
	gpg -q -a --export ABF5BD827BD9BF62 | apt-key add -

	# update all of the package references before installing anything
	echo "Running apt-get update..."
	apt-get update --assume-yes

	# install required packages
	echo "Installing apt-get packages..."
	apt-get install --assume-yes ${apt_package_install_list[@]}

	# Clean up apt caches
	apt-get clean
fi

# COMPOSER
#
# Install or Update Composer based on current state. Updates are direct from
# master branch on GitHub repository.
if [ -x /usr/local/bin/composer ]; then
	echo "Updating Composer..."
	COMPOSER_HOME=/usr/local/src/composer composer self-update
	COMPOSER_HOME=/usr/local/src/composer composer global update
else
	echo "Installing Composer..."
	curl -sS https://getcomposer.org/installer | php
	chmod +x composer.phar
	mv composer.phar /usr/local/bin/composer

	COMPOSER_HOME=/usr/local/src/composer composer -q global config bin-dir /usr/local/bin
	COMPOSER_HOME=/usr/local/src/composer composer global update
fi

# MySQL
if [ ! -f /var/log/databasesetup ];
then
	echo "Configuring MySQL..."

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
if [ ! -d /var/www/postbot ];
then
	echo "Configuring Apache..."

	rm -rf /var/www
	mkdir /var/www
	ln -fs /vagrant /var/www/postbot

	a2enmod rewrite
	a2enmod php5

	sed -i '/AllowOverride None/c AllowOverride All' /etc/apache2/sites-available/default

	echo "${VHOST}" > /etc/apache2/sites-enabled/000-default
	service apache2 restart
fi

# WP-CLI Install
if [[ ! -d /var/www/wp-cli ]]; then
	echo -e "\nDownloading wp-cli, see http://wp-cli.org"
	git clone git://github.com/wp-cli/wp-cli.git /var/www/wp-cli
	cd /var/www/wp-cli
	composer install
	ln -sf /var/www/wp-cli/bin/wp /usr/local/bin/wp
else
	echo -e "\nUpdating wp-cli..."
	cd /var/www/wp-cli
	git pull --rebase origin master
	composer update
fi

# WordPress
if [ ! -d "$WP_BASE_DIR" ];
then
	echo "Getting WordPress latest..."
	svn co --quiet http://core.svn.wordpress.org/trunk/ $WP_BASE_DIR

	cd $WP_BASE_DIR

	echo "Configuring WordPress..."
	wp core config --dbname=$WP_DB_NAME --dbuser=$WP_USERNAME --dbpass=$WP_PASSWORD --quiet --extra-php --allow-root <<PHP
define( 'WP_DEBUG', true );
PHP
	wp core install --url=$PB_URL --quiet --title="Local Postbot Dev" --admin_name=admin --admin_email="admin@local.dev" --admin_password="password" --allow-root
else
	echo "Updating WordPress..."
	svn up $WP_BASE_DIR

	# cd $WP_BASE_DIR
	# wp core upgrade --allow-root
fi

# Upload dir
if [ ! -d "$PB_UPLOAD_DIR" ];
then
	echo "Creating photo upload directory..."
	mkdir $PB_UPLOAD_DIR
	chmod go+w $PB_UPLOAD_DIR
	cp /vagrant/.htaccess $PB_UPLOAD_DIR
fi

# Postbot
if [ ! -f /vagrant/public_html/postbot-config.php ];
then
	echo "Setting up Postbot..."
	cp /vagrant/postbot-config-wp.php $PB_CONFIG_FILE
	cp /vagrant/postbot-local-wp.php $PB_LOCAL_FILE

	sed -i 's/\/path\/to\/wordpress/\/var\/www\/wordpress/' $PB_CONFIG_FILE
	sed -i 's/\/postbot\/uploads/\/vagrant\/public_html\/uploads/' $PB_CONFIG_FILE
	sed -i 's/\/postbot\///' $PB_CONFIG_FILE
	sed -i "s/YOURDOMAIN/$PB_URL/" $PB_CONFIG_FILE
fi
