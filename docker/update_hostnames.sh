#!/bin/sh

sed -i.save 's/localhost/db/' /var/www/html/admin/dist/js/stores_pg.js
sed -i.save 's/localhost/db/' /var/www/html/admin/action/pglink.php
sed -i.save 's/localhost/db/' /var/www/html/admin/action/import.php
sed -i.save 's/localhost/web/' /var/www/html/admin/class/mapproxy.php
for f in wms_index geostory_horizontal geostory_vertical; do
    sed -i.save "s|'\.\$_SERVER\['HTTP_HOST'\]\.'|localhost|" /var/www/html/admin/snippets/${f}.php
done
for f in wms-editor pg-editor; do
    sed -i.save "s|'\.\$_SERVER\['HTTP_HOST'\]\.'|localhost|" /var/www/html/admin/${f}.php
done
sed -i.save "s|str_replace('http://localhost|str_replace('http://:0|" /var/www/html/admin/snippets/qgs_svc.php

for i in 2 3; do
    for s in wfs wms wmts; do
        sed -i.save "s|str_replace('http://localhost|str_replace('http://:0|" /var/www/html/stores/${i}/${s}.php
    done
done

for i in 2 3; do
    sed -i.save "s|str_replace('http://localhost|str_replace('http://:0|" /var/www/html/layers/${i}/wms.php
done

for f in index layer; do
    find /var/www/html/layers/ -type f -name ${f}.php -exec sed -i.save "s|'\.\$_SERVER\['HTTP_HOST'\]\.'|localhost|" {} \;
done

find /var/www/html -type f -name "*.php.save" -delete
