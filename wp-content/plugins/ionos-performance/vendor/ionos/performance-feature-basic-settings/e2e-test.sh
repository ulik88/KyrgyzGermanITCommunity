#!/bin/bash

docker pull reg.1u1.it/wpdev/playwright-wordpress:latest
docker run --name=playwright-wordpress -d reg.1u1.it/wpdev/playwright-wordpress

export $(grep -v '^#' .env | xargs)
: ${WP_ADMIN_USERNAME=admin}
: ${WP_ADMIN_PASS=admin}
: ${WP_ENVIRONMENT_TYPE=development}
sleep 3
# prepare database
docker exec -i playwright-wordpress bash -c "mysql -u root -e 'ALTER USER \"root\"@\"localhost\" IDENTIFIED BY \"root\"; flush privileges; DROP DATABASE IF EXISTS wordpress; CREATE DATABASE wordpress;'"

# add WP-CLI config.
docker exec -i playwright-wordpress bash -c "export https_proxy=http://itproxy.1and1.org:3128"
docker exec -i playwright-wordpress bash -c "export http_proxy=http://itproxy.1and1.org:3128"
docker exec -i playwright-wordpress bash -c "export no_proxy=bitbucket.1and1.org,artifactory.1and1.org,united-internet.org,chat.united-internet.org,cname.lan,server.lan,loc.lan,pki.1and1.org,united.domain,cinetic.de,gmx.net,localhost,1u1.it,registry-1.docker.io,iplatform.1and1.org"

# install wordpress
docker exec -i playwright-wordpress bash -c "rm -rf /var/www/html/*"
docker exec -i playwright-wordpress bash -c "wp core download --path=/var/www/html --allow-root"
sleep 5
docker exec -i playwright-wordpress bash -c "wp config create --dbname=wordpress --dbuser=root --dbpass=root --locale=de_DE --path=/var/www/html --allow-root"
sleep 5
docker exec -i playwright-wordpress bash -c "wp core install --url=http://localhost --title=Test --admin_user=${WP_ADMIN_USERNAME} --admin_password=${WP_ADMIN_PASS} --admin_email=adming@local.host --path=/var/www/html --allow-root"
docker exec -i playwright-wordpress bash -c "wp config set WP_DEBUG true --path=/var/www/html --allow-root"
docker exec -i playwright-wordpress bash -c "wp config set WP_DEBUG_LOG true --path=/var/www/html --allow-root"
docker exec -i playwright-wordpress bash -c "wp config set WP_ENVIRONMENT_TYPE ${WP_ENVIRONMENT_TYPE} --path=/var/www/html --allow-root"

docker exec -i playwright-wordpress bash -c "touch /var/www/html/.htaccess"
docker exec -i playwright-wordpress bash -c "chmod 644 /var/www/html/.htaccess"
docker exec -i playwright-wordpress bash -c "touch /var/www/html/wp-content/debug.log"

# Copy plugin
docker cp ./ playwright-wordpress:/var/www/html/wp-content/plugins/ionos-performance

# change permissions
docker exec -i playwright-wordpress bash -c "chown -R www-data:www-data /var/www/html"

# install plugin dependencies
# docker exec -i playwright-wordpress bash -c "cd /var/www/html/wp-content/plugins/ionos-performance && composer install"
docker exec -i playwright-wordpress bash -c "cd /var/www/html/wp-content/plugins/ionos-performance && npm install"

# Send response header for PHP requests.
docker exec -i playwright-wordpress bash -c "sed -i \"0,/<?php/{s/<?php/<?php\nheader\('X-IONOS-PERFORMANCE-CACHING: 1'\);/}\" /var/www/html/wp-config.php"

# run playwright test
docker exec -i playwright-wordpress bash -c "cd /var/www/html/wp-content/plugins/ionos-performance && npx playwright test"

docker stop playwright-wordpress
docker rm playwright-wordpress

#docker-compose -f ./docker/e2e-tests/docker-compose.yml down
# commit image
# docker commit playwright-wordpress wpdev:stable
