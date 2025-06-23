#!/bin/bash -e

function cleanup() {
  kill $XVFB_PID $QGIS_PID
}

function waitfor() {
  while ! pidof $1 >/dev/null; do
      sleep 1
  done
  pidof $1
}

trap cleanup SIGINT SIGTERM

rm -f /tmp/.X99-lock

# Update font cache
fc-cache

/usr/bin/Xvfb :99 -ac -screen 0 1280x1024x16 +extension GLX +render -noreset >/dev/null &
XVFB_PID=$(waitfor /usr/bin/Xvfb)

# give some time of Xvfb to start
sleep 5;

# To avoid issues with GeoPackages when scaling out QGIS should not run as root
spawn-fcgi -n -u www-data -g www-data -d $HOME -P /run/qgis.pid -p 9000 -- /usr/lib/cgi-bin/qgis_mapserv.fcgi &
QGIS_PID=$(waitfor qgis_mapserv.fcgi)
wait $QGIS_PID