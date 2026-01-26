**********************
QCarta Cache
**********************

.. contents:: Table of Contents
Overview
==================

QCarta Cache is run as a service.

To access QCarta Cache, click the QCarta Cache link on the left menu

.. image:: QCarta Cache-1.png


Restart
================

To stop/start/restart MapProxy, click the Stop or Restart button as shown below.

.. image:: QCarta Cache-restart.png

Edit
================

To edit the mapproxy.yaml file, click the edit button as shown below.

.. image:: mapproxy-edit.png

This will open the mapporxy.yaml file for editing.

.. image:: mapproxy-edit-2.png

.. note::
    Be sure to click the Submit button at bottom after making changes.

QCarta Cache Directory
================

The MapProxy config directory is located at::

        /var/www/data/mapproxy

The default configuration files are shown below

.. image:: mapproxy-files.png


Cache Directory
================

The MapProxy config directory is located at::

        /var/www/data/mapproxy/cache_data

The ouput from the demo data is shown below

.. image:: maproxy-cache-directory.png


Authentication
================


[Unit]
Description=QCarta Tile Service (replaces MapProxy)
After=network.target qgis-server.service
Requires=network.target
Conflicts=mapproxy.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/data/qcarta-tiles
ExecStart=/usr/local/bin/qcarta-tiles
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

# Environment variables
Environment="QGIS_SERVER_URL=http://localhost/cgi-bin/qgis_mapserv.fcgi"
Environment="QCARTA_CACHE_PURGE_TOKEN=YourSecretToken"

# Security settings
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target


When a Layer is set to Private, MapProxy authenticates requests against the QeoSerer user database.

Authentication is accomplished using the wsgiapp_authorize.patch file::

	patch -d /usr/lib/python3/dist-packages/mapproxy -p0 < installer/wsgiapp_authorize.patch

This file is located in the QeoServer installer directory.

Layer Preview
================

To change Layer Preview or Custom Layers to use MapProxy in place of PHP Session Cache, change section below from::

	    const wmsLayer = L.tileLayer.wms('proxy_qgis.php?', {
		    layers: '<?=implode(',', QGIS_LAYERS)?>'
	    }).addTo(map);


to::


        const wmsLayer = L.tileLayer.wms('https://domain.com/mproxy/service', {
            layers: 'neighborhoods'
        }).addTo(map);


Note that in addition to the new url, we are also referencing the Layer name explicitly.


Seed Layer
==================

Cache is created by MapProxy when requests are made for layers.

You can also seed Layers to specified zoom levels.

To do so, go to MapProxy > Seed on the left menu

Select the layer to seed and click the Start button as shown below.

The progress and status are displayed.  

.. image:: seed-edit-3.png

To edit the seed yaml file for the layer, click the edit icon as shown below:

.. image:: seed-edit-1.png

Make any edits and then click the submit button.

.. image:: seed-edit-2.png


Seed Versioning
==================

Each update to the yaml file for each layer creates a restorable backup.

If you wish to restore a previous version, simply select it from the dropdown as show below

.. image:: seed-editor.png


Service File
=================

MapProxy is configured to run as a systemd service.

The mapproxy.service file contains below by default::

	[Unit]
	Description=MapProxy
	After=multi-user.target

	[Service]
	User=www-data
	Group=www-data

	WorkingDirectory=/var/www/data/mapproxy
	Type=simple
	Restart=always

	EnvironmentFile=/etc/environment
	Environment=PGSYSCONFDIR=/var/www/data/qgis/
	Environment=SKIP_AUTH=fish.webgis1.com

	ExecStart=mapproxy-util serve-develop /var/www/data/mapproxy/mapproxy.yaml -b 127.0.0.1:8011

	[Install]
	WantedBy=multi-user.target







