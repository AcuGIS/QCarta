FROM ubuntu:24.04

ARG DOCKER_IP="192.168.0.25"
ARG DOCKER_PORT="8000"

ADD https://download.qgis.org/downloads/qgis-archive-keyring.gpg /etc/apt/keyrings/qgis-archive-keyring.gpg
COPY docker/qgis.sources /etc/apt/sources.list.d/qgis.sources

RUN apt-get -y update && \
	apt-get -y --no-install-suggests --no-install-recommends --allow-unauthenticated install unzip spawn-fcgi qgis-server xauth xvfb && \
	fc-cache && \
	apt-get -y clean all && \
	rm -rf /var/lib/apt/lists/*
	
RUN mkdir -p /var/www/cache/qgis && \
		mkdir -p /var/www/data/qgis/plugins && \
		mkdir -p /var/www/data/stores && \
		chown -R www-data:www-data /var/www/cache/qgis && \
		chown -R www-data:www-data /var/www/data

ADD https://github.com/3liz/qgis-wfsOutputExtension/releases/download/1.8.2/wfsOutputExtension.1.8.2.zip /tmp/wfsOutputExtension.1.8.2.zip
RUN unzip -d /var/www/data/qgis/plugins/ /tmp/wfsOutputExtension.1.8.2.zip && \
	rm -rf /tmp/wfsOutputExtension.1.8.2.zip

# SimpleBrowser
ADD https://github.com/elpaso/qgis-server-simple-browser/archive/refs/heads/master.zip /tmp/master.zip
RUN unzip -d /var/www/data/qgis/plugins/ /tmp/master.zip && \
	mv /var/www/data/qgis/plugins/qgis-server-simple-browser-master /var/www/data/qgis/plugins/simple-browser && \
	rm -rf /tmp/master.zip
ADD installer/serversimplebrowser.py /tmp/serversimplebrowser.py
RUN sed "s/BASE_URL = 'localhost'/BASE_URL = '${DOCKER_IP}:${DOCKER_PORT}'/" < /tmp/serversimplebrowser.py > /var/www/data/qgis/plugins/simple-browser/serversimplebrowser.py && \
	rm -rf /tmp/serversimplebrowser.py
ADD installer/map_template.html /var/www/data/qgis/plugins/simple-browser/assets/map_template.html

COPY --chown=www-data:www-data docker/pg_service.conf /var/www/data/qgis/pg_service.conf
COPY --chown=www-data:www-data docker/start-qgis.sh /usr/local/bin/start-qgis.sh

# Temp fix for https://github.com/qgis/QGIS/issues/59613
RUN mkdir -p /.cache/QGIS/ && \
	chown -R www-data:www-data /.cache/QGIS/ && \
	ln -s /var/www/cache/qgis /.cache/QGIS/QGIS3

VOLUME /var/www/cache/qgis
VOLUME /var/www/data/qgis
VOLUME /var/www/data/stores

ENV QT_GRAPHICSSYSTEM raster
ENV DISPLAY :99
ENV HOME /var/www/data/qgis

EXPOSE 9000
CMD /usr/local/bin/start-qgis.sh
