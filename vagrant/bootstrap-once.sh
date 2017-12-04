#!/bin/bash

echo "--- Setup Swap ---"
# size of swapfile in megabytes
swapsize=2048

# does the swap file already exist?
grep -q "swapfile" /etc/fstab

# if not then create it
if [ $? -ne 0 ]; then
  echo 'swapfile not found. Adding swapfile.'
  fallocate -l ${swapsize}M /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  echo '/swapfile none swap defaults 0 0' >> /etc/fstab
else
  echo 'swapfile found. No changes made.'
fi

echo "--- Add Repositories ---"
add-apt-repository -y ppa:ondrej/php

echo "--- Update OS package list ---"
function aptget {
  # to prevent encoding problem
  DEBIAN_FRONTEND=noninteractive apt-get \
    -o Dpkg::Options::="--force-confdef" \
    -o Dpkg::Options::="--force-confold" \
    $@ -y
}
aptget update
aptget dist-upgrade

echo "--- Installing Language Pack ---"
apt-get install -y language-pack-en

echo "--- Update Timezone ---"
cp /usr/share/zoneinfo/America/Toronto /etc/localtime

echo "--- Reconfigure Locales ---"
locale-gen en_CA.UTF-8
dpkg-reconfigure -f noninteractive locales

echo "--- Installing Git-core ---"
apt-get install -y git-core

echo "--- Installing Build Essential ---"
apt-get install -y build-essential
apt-get install -y make

echo "--- Installing Curl ---"
apt-get install -y curl

echo "--- Installing OpenSsl ---"
apt-get install -y openssl

echo "--- Installing Apache 2 ---"
apt-get install -y apache2
a2enmod headers
a2enmod ssl
a2enmod rewrite
chmod +rx /var/log/apache2

echo "--- Setting Apache VHost ---"
rm /etc/apache2/sites-enabled/*
cp /vagrant/vagrant/apache/* /etc/apache2/sites-enabled/

echo "--- Apache User is now Vagrant ---"
chown -R ubuntu:ubuntu /var/lock/apache2/
chown -R ubuntu:ubuntu /var/log/apache2/
sed -i "s/export APACHE_RUN_USER.*/export APACHE_RUN_USER=ubuntu/" /etc/apache2/envvars
sed -i "s/export APACHE_RUN_GROUP.*/export APACHE_RUN_GROUP=ubuntu/" /etc/apache2/envvars
sed -i 's/ServerTokens .*/ServerTokens ProductOnly/' /etc/apache2/apache2.conf
sed -i 's/ServerSignature .*/ServerSignature Off/' /etc/apache2/apache2.conf

echo "--- Installing PHP for Apache ---"
apt-get install -y php7.1 php7.1-cli php7.1-curl \
php7.1-mbstring php7.1-xml php7.1-xmlrpc php7.1-intl php7.1-gd \
php7.1-gd php7.1-gmp php7.1-zip php7.1-xsl php7.1-bcmath \
libapache2-mod-php7.1 


echo "--- Installing Redis ---"
apt-get install -y redis-server php-redis
 
echo "--- Enable logs for PHP ---"
sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/7.1/apache2/php.ini
sed -i "s/display_errors = .*/display_errors = On/" /etc/php/7.1/apache2/php.ini

echo "--- Vagrant owns PHP ---"
chown -R ubuntu:ubuntu /var/lib/php/

cat << EOF |  tee /etc/php/7.0/apache2/conf.d/vagrant.ini
date.timezone = "America/Toronto"
memory_limit = 64M
expose_php = Off
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379"
EOF

cat << EOF |  tee /etc/php/7.0/cli/conf.d/vagrant.ini
date.timezone = "America/Toronto"
memory_limit = 128M
expose_php = Off
EOF

echo "--- NodeJS Installation ---"
su - ubuntu -c "curl -sL https://deb.nodesource.com/setup_8.x | sudo -E bash -"
apt-get install -y nodejs

echo "--- Composer Installation ---"
su - ubuntu -c "php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\""
su - ubuntu -c "php composer-setup.php --install-dir=/vagrant --no-interaction"
su - ubuntu -c "php -r \"unlink('composer-setup.php');\""
su - ubuntu -c "cd /vagrant; php -d memory_limit=1024M ./composer.phar install --no-interaction --prefer-dist --verbose"

echo "--- Install App ---"
su - ubuntu -c "cd /vagrant; php -d memory_limit=1024M ./bin/console cache:clear --no-warmup --env=prod"
su - ubuntu -c "cd /vagrant; php -d memory_limit=1024M ./bin/console cache:warmup --env=prod"
