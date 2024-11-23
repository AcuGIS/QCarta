.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Security
**********************

.. contents:: Table of Contents
Overview
==================

There are two options for Store and Layer security.

The first is making Stores or Layers Public.  Doing so will expose them to anyone with the url.

The second option is to set store or Layer to private and access using User Secret Key.

Example
================

Below is an example using User Secret key:

**PHP**::

	<?php
		const SECRET_KEY='0825aeed-6d95-48d0-81d2-ef90aac615b6';	// copy from Users->Secret Key
		$auth = json_decode(file_get_contents('https://yourdomain.com/admin/action/authorize.php?secret_key='.SECRET_KEY.'&ip='.$_SERVER['REMOTE_ADDR']));
	?>

Replace SECRET_KEY with your secret key and yourdomain.com with your domain. 

Then call the url as needed.

For exmaple, using MapProxy url for layer::


	const wmsLayer = L.tileLayer.wms('https://fish.webgis1.com:443/mproxy/service?', {
			layers: 'layer_3'
		}).addTo(map);


A full example would look like::


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

		<style>
			html, body {
			height: 100%;
			margin: 0;
			}
			.leaflet-container {
			height: 100%;
			width: 100%;
			max-width: 100%;
			max-height: 100%;
			}
		</style>	
		</head>
		<body>

	<div id='map'></div>

	<script type="text/javascript">

	const map = L.map('map', {
		center: [0, 0],
		zoom: 16,
		crs: L.CRS.EPSG4326
	});

	const wmsLayer = L.tileLayer.wms('https://fish.webgis1.com:443/mproxy/service?', {
		layers: 'layer_3'
	}).addTo(map);

	map.fitBounds([[-0.71995,-126.175462],[75.047652,-65.525809]]);
	</script>
	</body>
	</html>


To reset a User's Secret Key, click on the Edit button.

In the modal box, click the Reset Key icon:

.. image:: keys-reset.png


add new

.. note::
    By default, links open in a new window.






