**********************
Layers
**********************

.. contents:: Table of Contents

Overview
==================

Layers are layers from your QGIS project.

Layers are created from Stores.  Stores contain all Layers.

The Layer section allows you to select how these Layers are served.

The Layer table is show below.

.. image:: layer-1.png

The fields are below

* **Name**. The Layer name. Clicking the Layer name will open the Layer in a Leafletjs map preview.	
* **Layers**. The Layers used fromt the QGIS Project.  You can select which Layers to include.
* **Public**. Public access allows anyone to view the Layer	 (Yes/No)
* **Cached**. Session Caching enabled (Yes/No)
* **MapProxy**. This indicates if MapProxy is enabled (Yes/No)
* **Customized**. This inidicates if Layer is a Custom Leaflet map
* **Store**. The Store used for the Layer	
* **Access Group**. The Group(s) with access to the Layer.
* **Actions**.  Layer actions

Add Layer
==================

To create a new Layer, click the Add New button at top right.

.. image:: layer-add-new.png

Give your Layer a name:

.. image:: create-layer-1.png

Select the Store from the dropdown

.. image:: create-layer-2.png

Select the Layer(s) from the Store to include

.. image:: create-layer-3.png

The Select options are explained below

* **Public**. The Layer will be Public, with no authentication required.
* **Cache**. Session Cache.  This is distinct from MapProxy Cache.
* **MapProxy**. This will enable MapProxy for the Layer
* **Custom**. This option is to signify that this Layer does not use the default map template for Preview   
   

Click the Create button.

.. image:: create-layer-4.png

Your Layer has now been created.

No, click on the Layer name to preview the Layer you just created using Leafletjs

.. image:: create-layer-5.png

The Layer shows the two QGIS project layers we selected, Parks and Waterways

.. image:: create-layer-6.png

Edit Layer
==================

To edit a Layer, click the Edit button at right as shown below

.. image:: layer-edit-1.png

The Layer information is displayed. Make any changes you wish to make and click the Update button

.. image:: show-layer-edit.png


Clear Cache
==================

To clear Session cache, click the Clear Cache button as shown below

.. image:: layer-clear-cache.png

Note: This does not clear MapProxy cache.  Clearing MapProxy cache is done via the MapProxy page.


Show Layer Info
==================

To display information on a layer, click the Show Info button at right

.. image:: layer-show-info.png

The information is displayed below

.. image:: layer-show-info-2.png

* **L.tileLayer.wms URL**	This is the WMS tile layer

* **BBox[min(x,y); max(x,y)]**	Bounding Box 

* **WMS URL**.  This opens the Layer in the following WMS formats
   * PNG
   * PDF
   * WebP
   * JPEG
   * PNG 1 Bit
   * PNG 8 Bit
   * PNG 16 Bit


* **WFS URL**	This opens the Layer in the following formats
   * GML2
   * GML2.1.2
   * GML3.1
   * GML3.1.1
   * GeoJson
   * VND Geo+Json
   * Geo+Json
   * Geo JSON
  




Edit Preview
==================

To edit the Leaflet Preview for a Layer, click the Edit Preview button

.. image:: show-layer-preview.png

Make any edits you wish to and then click Submit

.. image:: layer-show-preview-edit.png


Layer Preview Template
=====================

The template used to create the Layer Preview map is wms_index.php

It is located at::

   /var/www/html/admin/snippets/wms_index.php

You can edit this in any way you like to change the template used to create previews::

      <?php
	require('../../admin/incl/index_prefix.php');
	$wms_url = 'WMS_URL';
	if(str_starts_with($wms_url, '/mproxy/')){
		$content = file_get_contents('https://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key=SECRET_KEY&ip='.$_SERVER['REMOTE_ADDR']);
		$auth = json_decode($content);
		$wms_url .= '?access_key='.$auth->access_key;
	}
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
	<base target="_top">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<title>WMS example - Leaflet</title>
	
	<link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />
	<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
	<script src="../../admin/dist/js/leaflet.browser.print.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/0.4.2/leaflet.draw.css"/>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/0.4.2/leaflet.draw.js"></script>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="../../assets/dist/js/L.BetterWMS.js"></script>

	<style type="text/css">
	html, body, #map {
	margin: 0px;
  	height: 100%;
  	width: 100%;
	}  
	.leaflet-clickable {
		cursor: pointer !important;
	}
	.leaflet-container {
		cursor: pointer !important;
	}
	</style>
	</head>
	<body>

	<div id='map'></div>

	<script type="text/javascript">

	const map = L.map('map', {
		center: [0, 0],
		zoom: 16
	});

	var osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

	var carto = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://carto.com/attributions">CARTO</a>Carto</a>'
        }).addTo(map);

	var esri = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.esri.com">ESRI</a>'
        }).addTo(map);



	const wmsLayer = L.tileLayer.betterWms('<?=$wms_url?>', {
		layers: 'WMS_LAYERS',
		transparent: 'true',
  		format: 'image/png'
	}).addTo(map);

	map.fitBounds(BOUNDING_BOX);


	var overlayMap = {
	"WMS Layer" :wmsLayer  
	};

	var baseMap = {
	"OpenStreetMap" :osm,
	"ESRI Satellite" :esri,
	"CartoLight" :carto,
	};

	
	L.control.layers(baseMap, overlayMap,{collapsed:false}).addTo(map);

	L.control.browserPrint({
			title: 'Just print me!',
			documentTitle: 'My Leaflet Map',
			printLayer: L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
					attribution: 'Map tiles by <a href="http://openstreetmap.com">OpenStreetMap</a>',
					subdomains: 'abcd',
					minZoom: 1,
					maxZoom: 16,
					ext: 'png'
				}),
		closePopupsOnPrint: false,
		printModes: [
            	L.BrowserPrint.Mode.Landscape(),
            	"Portrait",
            	L.BrowserPrint.Mode.Auto("B4",{title: "Auto"}),
            	L.BrowserPrint.Mode.Custom("B5",{title:"Select area"})
			],
			manualMode: false
		}).addTo(map);

	var drawnItems = new L.FeatureGroup();
        	map.addLayer(drawnItems);

        var drawControl = new L.Control.Draw({
            edit: {
                featureGroup: drawnItems
            }
        	});
        	map.addControl(drawControl);

        	map.on('draw:created', function (e) {
            	var type = e.layerType,
                	layer = e.layer;
            	drawnItems.addLayer(layer);
        	});
	</script>

	</body>
	</html>


You can edit above in any way you wish to.


