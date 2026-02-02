FROM ubuntu:24.04
ENV DEBIAN_FRONTEND=noninteractive

ENV LANG=C
ENV APACHE_RUN_DIR=/var/run/apache2
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_PID_FILE=/var/run/apache2/apache2.pid
ENV APACHE_RUN_DIR=/var/run/apache2
ENV APACHE_LOCK_DIR=/var/lock/apache2
ENV APACHE_LOG_DIR=/var/log/apache2

#RUN a2enmod rewrite 
RUN apt-get -y update && \
	apt-get -y --no-install-suggests --no-install-recommends install apache2 libapache2-mod-php php-pgsql php-mbstring php-xml php-zip php-yaml php-sqlite3 php-gd gdal-bin postgresql-client mapproxy && \
	apt-get -y clean all && \
	rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data docker/envvars /etc/apache2/envvars

RUN mkdir -p /var/lock/apache2 /var/run/apache2 /var/log/apache2 /var/www/html && \
  chown -R www-data:www-data /var/lock/apache2 /var/run/apache2 /var/log/apache2 /var/www/html
			
#RUN sed -i.save "s/^upload_max_filesize =.*/upload_max_filesize = ${POST_MAX}/" /etc/php/${PHP_VER}/fpm/php.ini

RUN mkdir -p /var/www/cache/qgis 		&& \
		mkdir -p /var/www/cache/layers && \
		mkdir -p /var/www/data/upload 	&& \
		mkdir -p /var/www/data/stores 	&& \
		mkdir -p /var/www/data/docs 	&& \
		mkdir -p /var/www/data/layers 	&& \
		mkdir -p /var/www/html/layers 	&& \
		mkdir -p /var/www/html/stores   && \
		mkdir -p /var/www/html/geostories

# copy web files
COPY --chown=www-data:www-data *.php /var/www/html/
COPY --chown=www-data:www-data admin /var/www/html/admin
COPY --chown=www-data:www-data assets /var/www/html/assets
COPY --chown=www-data:www-data installer/usdemo.qgs /var/www/data/stores/usdemo.qgs

RUN a2enmod headers proxy_http proxy_fcgi rewrite
COPY docker/quail.conf /etc/apache2/sites-available/000-default.conf

COPY installer/demo/data /var/www/data
COPY installer/demo/html /var/www/html
COPY docker/make_cache_dirs.sh /tmp/make_cache_dirs.sh
RUN chmod +x /tmp/make_cache_dirs.sh && \
	/tmp/make_cache_dirs.sh && rm -rf /tmp/make_cache_dirs.sh && \
	chown -R www-data:www-data /var/www/

# update db/qgis-server hostname
COPY docker/update_hostnames.sh /tmp/update_hostnames.sh
RUN chmod +x /tmp/update_hostnames.sh && \
    /tmp/update_hostnames.sh && \
    rm -f /tmp/update_hostnames.sh

VOLUME /var/www/html/layers
VOLUME /var/www/html/stores
VOLUME /var/www/html/geostories
VOLUME /var/www/data/qgis/
VOLUME /var/www/data/stores/
VOLUME /var/www/data/docs/
VOLUME /var/www/data/layers/
VOLUME /var/www/cache

RUN sed -i.save 's/sudo //g' /var/www/html/admin/class/backend.php

RUN ln -sf /proc/self/fd/1 /var/log/apache2/access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/error.log

ENTRYPOINT ["/usr/sbin/apache2", "-DFOREGROUND"]
