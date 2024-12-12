**********************
Exmaples
**********************

.. contents:: Table of Contents
Overview
==================

Exmaple Leaflet Maps

BetterWMS
================

Using BetterWMS.js::

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
	.leaflet-clickable {
  	cursor: pointer !important;
	}
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


















