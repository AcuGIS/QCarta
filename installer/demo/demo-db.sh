#!/bin/bash -e

sed "s/ADMIN_PG_PASS/${ADMIN_PG_PASS}/" demo.sql | psql service=qgapp -d ${APP_DB}

pg_restore --clean --create -Fc -d postgres states.dump