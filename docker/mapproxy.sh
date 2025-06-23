#!/bin/bash -e

if [ ! -f /var/www/data/mapproxy/mapproxy.yaml ]; then
	#mapproxy-util create -t base-config /var/www/data/mapproxy
	mv /tmp/{mapproxy,seed}.yaml /var/www/data/mapproxy/
	chmod 666 /var/www/data/mapproxy/{mapproxy,seed}.yaml
fi

/usr/bin/mapproxy-util serve-develop /var/www/data/mapproxy/mapproxy.yaml -b 0.0.0.0:8011