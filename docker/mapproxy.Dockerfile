FROM ubuntu:24.04

ENV PGSYSCONFDIR=/var/www/data/qgis/

RUN apt-get -y update && apt-get -y --no-install-suggests --no-install-recommends install mapproxy patch python3-mapproxy python3-psycopg2 && \
	apt-get -y clean all && \
	rm -rf /var/lib/apt/lists/*

COPY installer/wsgiapp_authorize.patch /tmp/wsgiapp_authorize.patch
RUN patch -d /usr/lib/python3/dist-packages/mapproxy -p0 < /tmp/wsgiapp_authorize.patch && \
	apt-get -y remove patch && \
	rm -f /tmp/wsgiapp_authorize.patch

COPY docker/mapproxy.sh /usr/local/bin/mapproxy.sh
COPY --chown=www-data:www-data docker/mapproxy.yaml /tmp/mapproxy.yaml
COPY --chown=www-data:www-data docker/seed.yaml /tmp/seed.yaml

RUN sed -i.save 's/localhost/web/' /tmp/mapproxy.yaml

RUN mkdir -p /var/www/data/mapproxy && \
	chown -R www-data:www-data /var/www/data/mapproxy

VOLUME /var/www/data/mapproxy
VOLUME /var/www/data/qgis

USER www-data
ENTRYPOINT ["/usr/local/bin/mapproxy.sh"]