**********************
Exmaples
**********************

.. contents:: Table of Contents
Overview
==================

Exmaple Leaflet Maps

.. image:: mapproxy-1.png


BetterWMS
================

Using BetterWMS.js

<!doctype html>
<html>
  <head>
    <title>WMS GetFeatureInfo</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.0.2/leaflet.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.0.2/leaflet.js"></script></script>
    <style type="text/css">
      html, body, #map {
        margin: 0px;
        height: 100%;
        width: 100%;
      }
    </style>

<style type="text/css">
      html, body, #map {
        margin: 0px;
        height: 100%;
        width: 100%;
      }

      .sidebar {
        max-width: 300px;
        background: white;
        max-height: 400px;
        overflow-x: hidden;
        overflow-y: auto;
        display: none;
      }
      .sidebar .close {
          position: absolute;
          right: 0;
      }

.leaflet-clickable {
  cursor: crosshair !important;
}
/* Change cursor when over entire map */
.leaflet-container {
  cursor: pointer !important;
}

    </style>
  </head>
  <body>
    <div id="map"></div>
    
    <script src="https://code.jquery.com/jquery-1.10.1.min.js"></script>
    <script src="L.BetterWMS.js"></script>
    <script>
      var map = L.map('map', {
        center: [55.3781,3.4360],
        zoom: 6
      });
      

 var customControl = L.Control.extend({
      options: {
        position: 'topleft' // set the position of the control
      },

      onAdd: function (map) {
        // Create a container div for the control
        var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');

        // Add content to the container
        container.innerHTML = `<div class="sidebar">
                <a href="#" class="btn btn-sm mt-1 mx-3 close" id="fg-close-it" onclick="$(this).closest('.sidebar').hide()">X</a>
                <div class="table-container px-3 py-4"></div>
            </div>`;
				L.DomEvent.disableClickPropagation(container);	// Prevent click events propagation to map
				L.DomEvent.disableScrollPropagation(container);
        return container;
      }
    });
    map.addControl(new customControl());

      var url = 'https://quailserver.webgis1.com/mproxy/service?access_key=07e3c5ff-e84c-415d-bb7f-47f710c8307c';


      
      L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png').addTo(map);
      
      L.tileLayer.betterWms(url, {
        layers: 'bgsgrid',
        transparent: true,
        format: 'image/png'
      }).addTo(map);
    </script>
  </body>
</html>


Edit
================

To edit the mapproxy.yaml file, click the edit button as shown below.

.. image:: mapproxy-edit.png

This will open the mapporxy.yaml file for editing.

.. image:: mapproxy-edit-2.png

.. note::
    Be sure to click the Submit button at bottom after making changes.

MapProxy Directory
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















