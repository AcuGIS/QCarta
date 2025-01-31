<?php
	require('../../admin/incl/index_prefix.php');
	require('../../admin/incl/qgis.php');

	$wms_url = '/mproxy/service';
	$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
	if(str_starts_with($wms_url, '/mproxy/')){
		$content = file_get_contents($proto.'://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key=3b0f29cf-6c76-49c8-981c-c67cd1bbdf13&ip='.$_SERVER['REMOTE_ADDR']);
		$auth = json_decode($content);
		$wms_url .= '?access_key='.$auth->access_key;
	}
	
	$qgis_file = urldecode(QGIS_FILENAME_ENCODED);
	# bbox or all selected layers
	$bbox = layers_get_bbox($qgis_file, implode(',', QGIS_LAYERS));
	
	$xml = simplexml_load_file($qgis_file);
	# qgis bbox
	#$bounding_box = $DefaultViewExtent['xmin'].',</br>'.$DefaultViewExtent['ymin'].',</br>'.$DefaultViewExtent['xmax'].',</br>'.$DefaultViewExtent['ymax'];
	$proj_meta = $xml->xpath('/qgis/projectMetadata');
	if(is_array($proj_meta)){
	   $proj_meta = $proj_meta[0];
	   $qgs_title = (string)$proj_meta->title;
	   $qgs_abstract = (string)$proj_meta->abstract;
	}else{
       $qgs_title = "";
       $qgs_abstract = "";
	}
	list($projection) = $xml->xpath('/qgis/ProjectViewSettings/DefaultViewExtent/spatialrefsys/authid');
	$parts = explode('/', $qgis_file);
	$store_id = $parts[count($parts) - 2];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<base target="_top">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<title><?=implode(',', QGIS_LAYERS)?></title>
	
	<link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />
	<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
	<script src="../../assets/dist/js/leaflet.browser.print.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/0.4.2/leaflet.draw.css"/>

	<link rel="stylesheet" href="../../assets/dist/css/L.Control.Opacity.css"/>
      <link rel="stylesheet" href="../../assets/dist/css/leaflet-sidepanel.css" />
<script src="../../assets/dist/js/leaflet-sidepanel.min.js"></script>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/0.4.2/leaflet.draw.js"></script>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="../../assets/dist/js/L.BetterWMS.js"></script>
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

  .leaflet-popup-content {
    max-width: 600px;
    height: 300px;
    overflow-y: scroll;
}
  
  .sidepanel-content-wrapper
  {background-color:#f8f9f9}
  
  .leaflet-touch .leaflet-control-layers, .leaflet-touch .leaflet-bar {
    border: 0px solid rgba(0, 0, 0, 0.2);
    background-clip: padding-box;
  	background-color:#f8f9f9!important;
    color:#666!important;
    font-size: 16px;
  	padding-bottom: 30px;
}
  
.p {font-size:15px!important}   

  
.leaflet-touch .leaflet-bar.leaflet-draw-toolbar,
.leaflet-touch .leaflet-bar.leaflet-control-zoom {
	display: flex;
	flex-direction: column;
	gap: 5px;
	background-color: transparent !important;
}

.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a,
.leaflet-touch .leaflet-bar.leaflet-control-browser-print a,
.leaflet-touch .leaflet-bar.leaflet-control-zoom a {
	border: 1px solid #282828;
  	box-shadow: 0 0 0px 2px rgba(0, 0, 0, 0.3);
	border-radius: 3px;
	background-size: contain;
  	color:#fff!important;
	background-position: center !important;
	background-repeat: no-repeat;
}
.leaflet-touch .leaflet-right .leaflet-draw-actions {
	right: 37px;
}

.leaflet-touch .leaflet-bar.leaflet-control-browser-print,
.leaflet-touch .leaflet-bar.leaflet-control-zoom {
	padding-bottom: 0;
}


.leaflet-touch .leaflet-bar.leaflet-control-zoom a.leaflet-control-zoom-in {
	background-image: url('../../assets/images/qicons/tzoom.png'); /* Update Image for + icon */
}
.leaflet-touch .leaflet-bar.leaflet-control-zoom a.leaflet-control-zoom-out {
	background-image: url('../../assets/images/qicons/tzoom.png'); /* Update Image for - icon */
}
.leaflet-touch .leaflet-bar.leaflet-control-browser-print a.leaflet-browser-print {
	background-image: url('../../assets/images/qicons/tprinter.png'); /* Update Image for printer icon */
}
.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a.leaflet-draw-draw-polyline {
	background-image: url('../../assets/images/qicons/tline.png');
}
.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a.leaflet-draw-draw-polygon {
	background-image: url('../../assets/images/qicons/tpoly.png');
}
.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a.leaflet-draw-draw-rectangle {
	background-image: url('../../assets/images/qicons/tsquare.png');
}
.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a.leaflet-draw-draw-circle {
	background-image: url('../../assets/images/qicons/tcircle.png');
}
.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a.leaflet-draw-draw-marker {
	background-image: url('../../assets/images/qicons/tmarker.png');
}


.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a.leaflet-draw-edit-edit {
	background-image: url('../../assets/images/qicons/tedit.png');
}
.leaflet-touch .leaflet-bar.leaflet-draw-toolbar a.leaflet-draw-edit-remove {
	background-image: url('../../assets/images/qicons/tdelete-edit.png');
}
  

  
  
</style>
</head>
<body>

<div id='map'>
  <div id="mySidepanelLeft" class="sidepanel" aria-label="side panel" aria-hidden="false">
			<div class="sidepanel-inner-wrapper">
				<nav class="sidepanel-tabs-wrapper" aria-label="sidepanel tab navigation">
					<ul class="sidepanel-tabs">
						<!--<li class="sidepanel-tab">
							<a href="#" class="sidebar-tab-link" role="tab" data-tab-link="tab-1">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
									<path fill-rule="evenodd"
										d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z" />
								</svg>
							</a>
						</li>-->
                      <li class="sidepanel-tab">
							<a href="#" class="sidebar-tab-link" role="tab" data-tab-link="tab-5">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-layers" viewBox="0 0 16 16">
  <path d="M8.235 1.559a.5.5 0 0 0-.47 0l-7.5 4a.5.5 0 0 0 0 .882L3.188 8 .264 9.559a.5.5 0 0 0 0 .882l7.5 4a.5.5 0 0 0 .47 0l7.5-4a.5.5 0 0 0 0-.882L12.813 8l2.922-1.559a.5.5 0 0 0 0-.882zm3.515 7.008L14.438 10 8 13.433 1.562 10 4.25 8.567l3.515 1.874a.5.5 0 0 0 .47 0zM8 9.433 1.562 6 8 2.567 14.438 6z"/>
</svg>
							</a>
						</li>
                      
						<li class="sidepanel-tab">
							<a href="#" class="sidebar-tab-link" role="tab" data-tab-link="tab-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
</svg>
							</a>
						</li>
											
						
					</ul>
				</nav>
				<div class="sidepanel-content-wrapper">
					<div class="sidepanel-content">
						<!--<div class="sidepanel-tab-content" data-tab-content="tab-1">
							<h4 style="color:#fff!important">Description</h4>
							<p style="color:#fff!important"><b>Title</b>: <?=$qgs_title?></p>
							<p style="color:#fff!important"><b>Abstract</b>: <?=$qgs_abstract?></p>
						</div>-->
						<div class="sidepanel-tab-content" data-tab-content="tab-2">
						    <h4 style="color:#666!important">Metadata</h4>
                          <p style="color:#666!important"><b>Title</b>: <?=$qgs_title?></p>
							<p style="color:#666!important"><b>Abstract</b>: <?=$qgs_abstract?></p>
                          
							<p style="color:#666!important"><b>Projection</b>: <?=$projection?></p>
							<p style="color:#666!important"><b>Bounding Box</b>:<?=$bbox["miny"].','.$bbox["minx"].','.$bbox['maxy'].','.$bbox['maxx']?></p>
							<p style="color:#666!important"><b>OGC Web Services:</b>:
                              <br>
							 <a href="<?=$proto.'://'.$_SERVER['HTTP_HOST']?>/stores/<?=$store_id?>/wms.php?REQUEST=GetCapabilities" target="_blank" style="color:#0078A8; text-decoration:none!important">WMS</a>
                                <br>
							 <a href="<?=$proto.'://'.$_SERVER['HTTP_HOST']?>/stores/<?=$store_id?>/wfs.php?REQUEST=GetCapabilities" target="_blank" style="color:#0078A8!; text-decoration:none!important">WFS</a>
                                  <br>
							 <a href="<?=$proto.'://'.$_SERVER['HTTP_HOST']?>/stores/<?=$store_id?>/wmts.php?REQUEST=GetCapabilities" target="_blank" style="color:#0078A8!; text-decoration:none!important">WMTS</a>
							</p>
							<p>* Bounding box format is <i>min(Y,X);max(Y,X)</i></p>
						</div>
						
						
						<div class="sidepanel-tab-content" data-tab-content="tab-5">
							<h4 style="color:#666!important"><?=$qgs_title?></h4>
                            <div id="custom-map-controls"></div>
							<p></p>
                          
							<p></p>
						</div>
					</div>
				</div>
			</div>
			<div class="sidepanel-toggle-container">
				<button class="sidepanel-toggle-button" type="button" aria-label="toggle side panel"></button>
			</div>
		</div>
  
  </div>

<script type="text/javascript">

	const map = L.map('map', {
		center: [0, 0],
		zoom: 16,
  		zoomControl: 'false'
	});
  
  L.control.zoom({
position: 'topright'
}).addTo(map);

	// Basemaps

	var osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

	var carto = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://carto.com/attributions">CARTO</a>Carto</a>'
        }).addTo(map);

	var esri = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}.png', {
            maxZoom: 19,
            attribution: '© <a href="http://www.esri.com">ESRI</a>'
        }).addTo(map);

      // WMS Layer

			<?php $layers = explode(',', 'WMS_LAYERS'); $li = 0;
			 foreach($layers as $lay){ ?>
	const wms<?=$li?> = L.tileLayer.betterWms('<?=$wms_url?>', {
		layers: '<?=$lay?>',
		transparent: 'true',
  	format: 'image/png',
		maxZoom:25
	}).addTo(map);
	<?php $li = $li + 1; } ?>

	map.fitBounds(BOUNDING_BOX);

	// Group overlays and basemaps

	var overlayMap = {
		<?php $li=0; $del = ''; foreach($layers as $lay){ ?>
			<?=$del?>'<?=$lay?>' : wms<?=$li?>
		<?php $del = ','; $li = $li + 1; } ?>
	};

	var baseMap = {
	"OpenStreetMap" :osm,
	"ESRI Satellite" :esri,
	"CartoLight" :carto,
	};

	// Layer Selector

	L.control.layers(baseMap, overlayMap,{collapsed:false, position:'topleft'}).addTo(map);

	L.control
	.opacity(overlayMap, {
        label: 'Layers Opacity',
        position: 'topleft'      
	})
    	.addTo(map);
              
   map.removeControl(map.zoomControl);

	// Legend

	var legend = L.control({position: 'bottomright'}); 
	legend.onAdd = function (map) {        
    	var div = L.DomUtil.create('div', 'info legend');
    	div.innerHTML = '<img src="proxy_qgis.php?SERVICE=WMS&REQUEST=GetLegendGraphic&LAYERS=<?=urlencode(implode(',', QGIS_LAYERS))?>&FORMAT=image/png">';     
    	return div;
	};      
	legend.addTo(map);

	// Broswer Print

	L.control.browserPrint({
			title: '<?=implode(',', QGIS_LAYERS)?>',
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
			manualMode: false,
  			position: 'topright'
		}).addTo(map);
        

	// Draw

    var featureGroup = new L.FeatureGroup().addTo(map);
	 
	 var drawControl = new L.Control.Draw({
        position: 'topright',      
        edit: {
            featureGroup: featureGroup
        }
    });

    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function (event) {
        featureGroup.addLayer(event.layer);
    });
    
              
              
              
    const sidepanelLeft = L.control.sidepanel('mySidepanelLeft', {
			tabsPosition: 'top',
			startTab: 'tab-5'
		}).addTo(map);

 
  $(document).ready(function () {

var newParent = document.getElementById('custom-map-controls');
        var oldParent = document.getElementsByClassName("leaflet-top leaflet-left")

        while (oldParent[0].childNodes.length > 0) {
            newParent.appendChild(oldParent[0].childNodes[0]);
        }
 });
  
  
  const $jQuerysidePanel = $("#mySidepanelLeft");

if ($jQuerysidePanel.hasClass("closed")) {
	$jQuerysidePanel.removeClass("closed")
	$jQuerysidePanel.addClass("opened")
} else {
	$jQuerysidePanel.addClass("opened")
}



	
</script>

</body>
</html>
