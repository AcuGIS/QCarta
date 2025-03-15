#!/bin/bash -e

APP_DB='qgapp'
APP_DB_PASS=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c32);

ADMIN_APP_PASS='quail';
ADMIN_PG_PASS=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c32);

WWW_DIR='/var/www/html'
DATA_DIR='/var/www/data'
CACHE_DIR='/var/www/cache'

WITH_MAPPROXY=true
WITH_DEMO=true

HNAME=$(hostname -f)
#NOTE: Update this to http, if your site is not secured
BASE_PROTO='https'

function install_qgis_server(){

	RELEASE=$(lsb_release -cs)
	wget --no-check-certificate --quiet -O /etc/apt/keyrings/qgis-archive-keyring.gpg https://download.qgis.org/downloads/qgis-archive-keyring.gpg

	# 3.28.x Firenze 				​-> URIs: https://qgis.org/ubuntu
	# 3.22.x Białowieża LTR	-> URIs: https://qgis.org/ubuntu-ltr
	cat >>/etc/apt/sources.list.d/qgis.sources <<CAT_EOF
Types: deb deb-src
URIs: https://qgis.org/ubuntu
Suites: ${RELEASE}
Architectures: amd64
Components: main
Signed-By: /etc/apt/keyrings/qgis-archive-keyring.gpg
CAT_EOF

	apt-get update -y || true
  apt-get install -y qgis-server
	
	if [ -d /etc/logrotate.d ]; then
		cat >/etc/logrotate.d/qgisserver <<CAT_EOF
/var/log/qgisserver.log {
	su www-data www-data
	size 100M
	notifempty
	missingok
	rotate 3
	daily
	compress
	create 660 www-data www-data
}
CAT_EOF
	fi
	
	mkdir -p ${DATA_DIR}/qgis/
	chown www-data:www-data ${DATA_DIR}/qgis
	
	touch /var/log/qgisserver.log
	chown www-data:www-data /var/log/qgisserver.log

	# Temp fix for https://github.com/qgis/QGIS/issues/59613
	mkdir -p /.cache/QGIS/
	chown -R www-data:www-data /.cache/QGIS/
	ln -s ${CACHE_DIR}/qgis /.cache/QGIS/QGIS3
}

function install_qgis_server_plugins(){
	
	apt-get -y install python3-virtualenv
	
	mkdir -p ${DATA_DIR}/qgis/plugins

	# install plugins manager
	pushd ${DATA_DIR}/qgis/plugins
		virtualenv --python=/usr/bin/python3 --system-site-packages .venv
		source .venv/bin/activate
		
		pip3 install qgis-plugin-manager
		
		export QGIS_PLUGINPATH=${DATA_DIR}/qgis/plugins
		qgis-plugin-manager init
		qgis-plugin-manager update
		qgis-plugin-manager install wfsOutputExtension
		
		# SimpleBrowser
		wget -P/tmp https://github.com/elpaso/qgis-server-simple-browser/archive/refs/heads/master.zip
		unzip /tmp/master.zip
		rm -rf /tmp/master.zip
		
		mv qgis-server-simple-browser-master simple-browser
	popd
	
	# Fixes for OpenLayers service
	sed "s/BASE_URL =.*/BASE_URL = '${HNAME}'/
s/BASE_PROTO =.*/BASE_PROTO = '${BASE_PROTO}'/" < installer/serversimplebrowser.py > ${DATA_DIR}/qgis/plugins/simple-browser/serversimplebrowser.py
	cp installer/map_template.html ${DATA_DIR}/qgis/plugins/simple-browser/assets/
	
	# install otf-project
	pushd ${DATA_DIR}/qgis/plugins
		wget -P/tmp https://github.com/kaloyan13/otf-project/archive/refs/heads/master.zip
		unzip /tmp/master.zip
		rm -rf /tmp/master.zip
		
		mv otf-project-master otf-project
	popd
	
	chown -R www-data:www-data ${DATA_DIR}/qgis/plugins
}

function install_map_proxy(){
	
	apt-get -y install mapproxy python3-mapproxy patch
	apt-mark hold python3-mapproxy

	pushd ${DATA_DIR}/
		mapproxy-util create -t base-config mapproxy
	popd
	cp installer/mapproxy.yaml ${DATA_DIR}/mapproxy/
	chown -R www-data:www-data ${DATA_DIR}/mapproxy
	
	cat >/etc/systemd/system/mapproxy.service <<CAT_EOF
[Unit]
Description=MapProxy
After=multi-user.target

[Service]
User=www-data
Group=www-data

WorkingDirectory=${DATA_DIR}/mapproxy
Type=simple
Restart=always

EnvironmentFile=/etc/environment
Environment=PGSYSCONFDIR=${DATA_DIR}/qgis/

ExecStart=mapproxy-util serve-develop ${DATA_DIR}/mapproxy/mapproxy.yaml -b 127.0.0.1:8011

[Install]
WantedBy=multi-user.target
CAT_EOF
	
	# apply patch for layer authentication
	patch -d /usr/lib/python3/dist-packages/mapproxy -p0 < installer/wsgiapp_authorize.patch

	chmod +x /etc/systemd/system/mapproxy.service
	systemctl daemon-reload

	systemctl enable mapproxy
	systemctl start mapproxy
	
	a2enmod proxy_http
}

touch /root/auth.txt
export DEBIAN_FRONTEND=noninteractive

if [ ! -f /usr/bin/createdb ]; then
	echo "Error: Missing PG createdb! First run ./installer/postgres.sh"; exit 1;
fi

if [ ! -d installer ]; then
	echo "Usage: ./installer/app-installer.sh"
	exit 1
fi

for opt in $@; do
	if [ "${opt}" == '--no-mapproxy' ]; then
		WITH_MAPPROXY='false'
	elif [ "${opt}" == '--no-demo' ]; then
		WITH_DEMO='false'
	fi
done

# 1. Install packages (assume PG is preinstalled)
apt-get -y install apache2 libapache2-mod-fcgid php-{pgsql,mbstring,xml,zip,fpm,yaml}

# manual check to avoid apt exit, if gdal is preinstalled from gdal-formats, package is on hold
if [ ! -f /usr/bin/ogr2ogr ]; then
	apt-get -y install gdal-bin
fi

install_qgis_server
install_qgis_server_plugins
if [ "${WITH_MAPPROXY}" == 'true' ]; then
	install_map_proxy
fi

ADMIN_APP_PASS_ENCODED=$(php -r "echo password_hash('${ADMIN_APP_PASS}', PASSWORD_DEFAULT);")

sed -i.save "s|ADMIN_APP_PASS|${ADMIN_APP_PASS_ENCODED}|
s|ADMIN_PG_PASS|${ADMIN_PG_PASS}|" installer/init.sql

# 2. Create db
su postgres <<CMD_EOF
createdb ${APP_DB}
createuser -sd ${APP_DB}
psql -c "alter user ${APP_DB} with password '${APP_DB_PASS}'"
psql -c "ALTER DATABASE ${APP_DB} OWNER TO ${APP_DB}"

createuser -sd admin1
psql -c "alter user admin1 with password '${ADMIN_PG_PASS}'"

createuser -sd jane1
psql -c "alter user jane1 with password '${ADMIN_PG_PASS}'"

psql -d ${APP_DB} < installer/setup.sql
psql -d ${APP_DB} < installer/init.sql
CMD_EOF

echo "admin app pass: ${ADMIN_APP_PASS}" >> /root/auth.txt
echo "admin1 pg pass: ${ADMIN_PG_PASS}" >> /root/auth.txt
echo "${APP_DB} pass: ${APP_DB_PASS}" >> /root/auth.txt

NUM_PROCS=$(cat /proc/cpuinfo | grep -c 'physical id')

cat >admin/incl/const.php <<CAT_EOF
<?php
const DB_HOST="localhost";
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
const WITH_MAPPROXY = ${WITH_MAPPROXY};
const PREVIEW_TYPES = ['leaflet' => 'Leaflet', 'openlayers' => 'OpenLayers'];
?>
CAT_EOF

sed "s|\$DATA_DIR|$DATA_DIR|
s|QGIS_SERVER_MAX_THREADS 2|QGIS_SERVER_MAX_THREADS ${NUM_PROCS}|
" < installer/qgis_apache2.conf > /etc/apache2/sites-available/qgis.conf

a2enmod ssl headers expires fcgid cgi rewrite
a2ensite qgis
a2disconf serve-cgi-bin

# switch to mpm_event to server faster and use HTTP2
PHP_VER=$(php -version | head -n 1 | cut -f2 -d' ' | cut -f1,2 -d.)
a2enmod proxy_fcgi setenvif http2
a2enconf php${PHP_VER}-fpm

systemctl restart apache2
a2enmod mpm_event

# fixes for file upload size
POST_MAX=$(grep -m 1 '^post_max_size =' /etc/php/${PHP_VER}/fpm/php.ini | cut -f2 -d=)
sed -i.save "s/^upload_max_filesize =.*/upload_max_filesize = ${POST_MAX}/" /etc/php/${PHP_VER}/fpm/php.ini
systemctl restart php${PHP_VER}-fpm

mkdir -p "${CACHE_DIR}/qgis"
mkdir -p "${CACHE_DIR}/layers"

mkdir -p "${DATA_DIR}/qgis"
mkdir -p "${DATA_DIR}/upload"
mkdir -p "${DATA_DIR}/layers"
mkdir -p "${DATA_DIR}/stores"

mkdir -p "${WWW_DIR}/layers"
mkdir -p "${WWW_DIR}/stores"

cp installer/usdemo.qgs "${DATA_DIR}/stores"

chown -R www-data:www-data "${CACHE_DIR}"
chown -R www-data:www-data "${DATA_DIR}"

cp -r . ${WWW_DIR}/
chown -R www-data:www-data ${WWW_DIR}
rm -rf ${WWW_DIR}/{installer,plugins}

sed "
s|\$WWW_DIR|$WWW_DIR|
" < installer/quail.conf > /etc/apache2/sites-available/000-default.conf

systemctl restart apache2

# setup cron to clean expired access keys
cat >/etc/cron.d/qgis_app_cleaner <<CAT_EOF
*/10 * * * * postgres psql -d ${APP_DB} -c 'DELETE FROM public.access_key WHERE valid_until < now();'
CAT_EOF

for f in mapproxy_ctl mapproxy_seed_ctl qfield_ctl; do
	cp installer/${f}.sh /usr/local/bin/
	chown www-data:www-data /usr/local/bin/${f}.sh
	chmod 0550 /usr/local/bin/${f}.sh
done

cat >/etc/sudoers.d/qgapp <<CAT_EOF
www-data ALL = NOPASSWD: /usr/local/bin/mapproxy_ctl.sh,/usr/local/bin/mapproxy_seed_ctl.sh,/usr/local/bin/qfield_ctl.sh
CAT_EOF

# create entry for mapproxy
if [ "${WITH_MAPPROXY}" == 'true' ]; then
	cat >${DATA_DIR}/qgis/pg_service.conf <<CAT_EOF
[qgapp]
host=localhost
port=5432
dbname=${APP_DB}
user=${APP_DB}
password=${APP_DB_PASS}

CAT_EOF
	chown www-data:www-data ${DATA_DIR}/qgis/pg_service.conf
	
	sed "s|DATA_DIR|$DATA_DIR|g" < installer/mapproxy-seed.service	>/etc/systemd/system/mapproxy-seed@.service
	systemctl daemon-reload
fi

if [ "${WITH_DEMO}" == 'true' ]; then
	# install demo
export PGSERVICEFILE="${DATA_DIR}/qgis/pg_service.conf"
export PGSERVICE=qgapp
sed "
s/ADMIN_PG_PASS/${ADMIN_PG_PASS}/
s/'db'/'localhost'/" installer/demo/demo.sql | psql -d ${APP_DB}
su postgres <<CMD_EOF
pg_restore --create -Fc -d postgres installer/demo/states.dump
CMD_EOF

	if [ "${WITH_MAPPROXY}" == 'true' ]; then
		sed 's/qgis\-server/localhost/' < docker/mapproxy.yaml > ${DATA_DIR}/mapproxy/mapproxy.yaml
	fi

	for d in data html; do
		cp -r installer/demo/${d}/* /var/www/${d}/
	done

	for i in 2 3 4 5 6 7 8 9; do
		mkdir ${CACHE_DIR}/layers/${i}
		for l in 0 1 2 3 4 5 6 7 8 9 a b c d e f; do
			mkdir ${CACHE_DIR}/layers/${i}/${l}
		done
	done
	chown -R www-data:www-data /var/www/
	
	cat >>${DATA_DIR}/qgis/pg_service.conf <<CAT_EOF
[usdata]
host=localhost
port=5432
dbname=states
user=admin1
password=${ADMIN_PG_PASS}
CAT_EOF
fi


# save 1Gb of space
apt-get -y clean all
