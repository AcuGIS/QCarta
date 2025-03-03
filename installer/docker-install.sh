#!/bin/bash -e

APP_DB='qgapp'
APP_DB_PASS=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c32);

ADMIN_APP_PASS=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c8);
ADMIN_PG_PASS=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c32);

POSTGRES_PASSWORD=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c32);

ADMIN_APP_PASS='quail';
#ADMIN_APP_PASS_ENCODED=$(php-cli -r "echo password_hash('${ADMIN_APP_PASS}', PASSWORD_DEFAULT);")

WWW_DIR='/var/www/html'
DATA_DIR='/var/www/data'
CACHE_DIR='/var/www/cache'

touch /root/auth.txt
export DEBIAN_FRONTEND=noninteractive

NUM_PROCS=$(cat /proc/cpuinfo | grep -c 'physical id')

cat >docker/const.php <<CAT_EOF
<?php
const DB_HOST="db";
const DB_NAME="${APP_DB}";
const DB_USER="${APP_DB}";
const DB_PASS="${APP_DB_PASS}";
const DB_PORT = 5432;
const DB_SCMA='public';
const SESS_USR_KEY = 'qgis_user';
const SUPER_ADMIN_ID = 1;
const WWW_DIR = '${WWW_DIR}';
const CACHE_DIR = "${CACHE_DIR}";
const DATA_DIR = "${DATA_DIR}";
const NUM_PROCS = ${NUM_PROCS};
const WITH_MAPPROXY = true;
const PREVIEW_TYPES = ['leaflet' => 'Leaflet', 'openlayers' => 'OpenLayers'];
?>
CAT_EOF

cat >docker/.env <<CAT_EOF
POSTGRES_USER=postgres
PGUSER=postgres
POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
ADMIN_APP_PASS=${ADMIN_APP_PASS}
ADMIN_APP_PASS_ENCODED=\$2y\$10\$a5E.968RtIJUobxKKE438.D6Tv.M1teOh2ocPTFKbxZpaLmxFPEXO
ADMIN_PG_PASS=${ADMIN_PG_PASS}
APP_DB=${APP_DB}
APP_DB_PASS=${APP_DB_PASS}
CAT_EOF

cat >docker/pg_service.conf <<CAT_EOF
[qgapp]
host=db
port=5432
dbname=${APP_DB}
user=${APP_DB}
password=${APP_DB_PASS}

[usdata]
host=db
port=5432
dbname=states
user=admin1
password=${ADMIN_PG_PASS}
CAT_EOF
