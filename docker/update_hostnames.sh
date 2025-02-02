#!/bin/sh

sed -i.save 's/localhost/db/' /var/www/html/admin/dist/js/stores_pg.js
sed -i.save 's/localhost/db/' /var/www/html/admin/action/pglink.php
sed -i.save 's/localhost/db/' /var/www/html/admin/action/import.php
sed -i.save 's/localhost/web/' /var/www/html/admin/class/mapproxy.php
sed -i.save "s|'\.\$_SERVER\['HTTP_HOST'\]\.'|localhost|" /var/www/html/admin/snippets/wms_index.php
sed -i.save "s|str_replace('://:0|str_replace('://localhost|" /var/www/html/admin/snippets/qgs_svc.php

for i in 2 3 4 5 6 7 8; do
    for s in wfs wms wmts; do
        sed -i.save "s|str_replace('://:0|str_replace('://localhost|" /var/www/html/stores/${i}/${s}.php
    done
done

find /var/www/html/layers/ -type f -name index.php -exec sed -i.save "s|'\.\$_SERVER\['HTTP_HOST'\]\.'|localhost|" {} \;


find /var/www/html -type f -name "*.php.save" -delete
