<?php
	const SECRET_KEY='56c54125-79f3-4149-a83a-46ec680124d0';	// copy from Users->Secret Key
	$auth = json_decode(file_get_contents('http://10.3.1.2/admin/action/authorize.php?secret_key='.SECRET_KEY.'&ip='.$_SERVER['REMOTE_ADDR']));
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

	//const wmsLayer = L.tileLayer.wms('http://10.3.1.2/mproxy/service?access_key=<?=$auth->access_key?>', {
	//	layers: 'usa'	// use layer by app name
	//}).addTo(map);

	const wmsLayer = L.tileLayer.wms('http://10.3.1.2/layers/1/proxy_qgis.php?access_key=<?=$auth->access_key?>', {
		layers: 'states'	// use layer by QGIS name
	}).addTo(map);

	map.fitBounds([0,0]);
</script>



</body>
</html>
