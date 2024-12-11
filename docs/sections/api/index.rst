.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

************
API
************

.. contents:: Table of Contents

Quail offers basic API for GET calls.

The url /workspaces was added to allow compatibility for tools that query GeoServer.

GET
=======================
  
Get Public Projects:

.. code-block:: console

  https://domain.com/rest/workspaces

Get Query with Username and Password:

.. code-block:: console

  https://admin@admin.com:quail@domain.com/rest/workspaces

Get Workspace:

.. code-block:: console

    https://access_key:06e3c5ff-e84c-415c-bb7f-57f710c8307c@domain.com/rest/workspaces

Get Layers:

.. code-block:: console

  https://access_key:06e3c5ff-e84c-415c-bb7f-57f710c8307c@domain.com/rest/layers

Get Workspace by Type:

.. code-block:: console

  https://domain.com/rest/workspaces/pg
  https://domain.com/rest/workspaces/qgs

.. note::
    Seeding and tile generation can be CPU intensive for larger data sets.  Plan accordingly.


Installer (Recommended)
=======================

Download the Quail binary and unzip:

.. code-block:: console

    wget https://github.com/AcuGIS/quail/quail-2.11.0.zip
    unzip -q quail-server-1.11.0.zip
    

Change to the /quail-server-1.11.0 directory and run the installers in sequence below:

If you already have PostgreSQL with PostGIS enabled, skip the postgres.sh script.

.. code-block:: console
 
    cd quail-server-1.11.0
    ./installer/postgres.sh
    ./installer/app-install.sh [--no-mapproxy]


Optionally, run below to provision SSL using letsencrypt:

.. code-block:: console

   apt-get -y install python3-certbot-apache

   certbot --apache --agree-tos --email hostmaster@yourdomain.com --no-eff-email -d yourdomain.com


Login at https://yourdomain.com/login.php

Default credentials

* Email:  admin@admin.com
* Password: quail

.. image:: _static/quail-login.png

Note: If you see below when navigating to your domain, remove the default index.html page from /var/www/html

.. image:: error-page.png


Docker Install
=======================

To install using Docker:

.. code-block:: console




.. code-block:: console

    docker volume rm quail_{cache_qgis,data_layers,data_qgis,data_mapproxy,data_stores,html_layers,html_stores,pg_data,www_cache}

Navigate to http://yourdomain.com:8000

Default credentials

* Email:  admin@admin.com
* Password: quail










