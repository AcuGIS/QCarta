<?php
	require('../../admin/incl/index_prefix.php');
	// copy from Users->Secret Key
	$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
	$content = file_get_contents($proto.'://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key=3b0f29cf-6c76-49c8-981c-c67cd1bbdf13&ip='.$_SERVER['REMOTE_ADDR']);
	$auth = json_decode($content);
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
	<script src="../../assets/dist/js/leaflet.browser.print.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/0.4.2/leaflet.draw.css"/>

	<link rel="stylesheet" href="../../assets/dist/css/Control.MiniMap.css"/>
	<link rel="stylesheet" href="../../assets/dist/css/leaflet.measurecontrol.css"/>
      	<link rel="stylesheet" href="../../assets/dist/css/L.Control.Opacity.css"/>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/0.4.2/leaflet.draw.js"></script>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="../../assets/dist/js/L.BetterWMS.js"></script>
	<script src="../../assets/dist/js/Control.MiniMap.js"></script>
	<script src="../../assets/dist/js/leaflet.measurecontrol.js"></script>
      	<script src="../../assets/dist/js/L.Control.Opacity.js"></script>

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
    <div id="map">
      <!--<h1 class="leaflet-control">WMS/WFS Demo</h1>-->
    </div>
<script>
var myStyle = {
    "color": "#ffffff",
    "weight": 0,
    "fillOpacity": 0.0
};

 var map = L.map("map", {zoomControl: false}).setView([39.8781, -93.6298], 5);
 
//OSM Basemap
var osm = new L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png',{ 
				attribution: '© <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'}).addTo(map);
  
var carto = new L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',{ 
				attribution: '© <a href="https://carto.com/attributions">CARTO</a>'}).addTo(map);  

  
var esri = new L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}.png',{ 
				attribution: '© <a href="www.esri.com">ESRI</a>'}).addTo(map); 
  

  
//wfs layer
var url = '<?=$proto?>://<?=$_SERVER['HTTP_HOST']?>/layers/2/proxy_qgis.php?service=WFS&version=1.1.0&request=GetFeature&typeName=states&maxFeatures=500&OUTPUTFORMAT=application/geo%20json&access_key=<?=$auth->access_key?>';

		
//Loop through JSON file for features information -->
function forEachFeature(feature, layer) {
    
// Print all feature information
 var mypopupContent = '<pre>'+JSON.stringify(feature.properties,null,' ').replace(/[\{\}"]/g,'')+'</pre>';
    
// above Bind to popup content
layer.bindPopup(mypopupContent);
            
		}
	
// Null var that will hold layer
var myLayer = L.geoJson(null, {onEachFeature: forEachFeature, style: myStyle});

// Add mylayer above to map
$.getJSON(url, function(data) {
myLayer.addData(data);
});
myLayer.addTo(map);



//wms layer via MapProxy
  var wms = L.tileLayer.wms("<?=$proto?>://<?=$_SERVER['HTTP_HOST']?>/mproxy/service?access_key=<?=$auth->access_key?>", {
    layers: 'usa',
    transparent: 'true',
		format: 'image/png',
		maxZoom:25
  });
  wms.addTo(map);

// Get Overlay(s)
var overlayMap = {
    "WMS Layer" :wms,
    "PostGIS Data Layer" :myLayer  
};

// Get Basemaps
var baseMap = {
    "OpenStreetMap" :osm,
    "ESRI Satellite" :esri,
    "CartoLight" :carto,
};

	// Add to control
	L.control.layers(baseMap, overlayMap,{collapsed:false}).addTo(map);

	L.control
	.opacity(overlayMap, {
        label: 'Layers Opacity',
	})
    	.addTo(map);



	var legend = L.control({position: 'bottomleft'}); 
	legend.onAdd = function (map) {        
    	var div = L.DomUtil.create('div', 'info legend');
    	div.innerHTML = '<img src="proxy_qgis.php?SERVICE=WMS&REQUEST=GetLegendGraphic&LAYERS=<?=urlencode(implode(',', QGIS_LAYERS))?>&FORMAT=image/png">';     
    	return div;
	};      
	legend.addTo(map);

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

					L.Control.measureControl().addTo(map);

					var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
					var osmAttrib='Map data &copy; OpenStreetMap contributors';

					var osmMini = new L.TileLayer(osmUrl, {minZoom: 0, maxZoom: 13, attribution: osmAttrib });
					var miniMap = new L.Control.MiniMap(osmMini, { toggleDisplay: true }).addTo(map);
</script>



</body>
</html>
