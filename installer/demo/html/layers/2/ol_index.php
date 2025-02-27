<?php
    // https://github.com/walk/ermatt/ol-layerswitcher/blob/master/examples/sidebar.html
    // https://openlayers.org/en/latest/examples/bing-maps.html
    
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

	$layers = explode(',', 'usa');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?=implode(',', QGIS_LAYERS)?></title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/openlayers/7.5.2/ol.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/ol@v7.5.2/dist/ol.js"></script>
    
    
	<script src="../../assets/dist/js/ol7-sidebar.js"></script>
	<link rel="stylesheet" href="../../assets/dist/css/ol7-sidebar.css" />
	<script src="../../assets/dist/js/ol-layerswitcher.js"></script>
	<link rel="stylesheet" href="../../assets/dist/css/ol-layerswitcher.css" />
	
    <style>
html, body {
    height: 100%;
    padding: 0;
    margin: 0;
    font-family: sans-serif;
    font-size: small;
    overflow: hidden;
    font: 10pt 'Helvetica Neue', Arial, Helvetica, sans-serif;
}

#map {
    width: 100%;
    height: 100%;
    font: 10pt 'Helvetica Neue', Arial, Helvetica, sans-serif;
}

/* As we've given the element we're displaying the layer tree within a class of
    * layer-switcher (so we can benefit from the default layer-switcher styles) we
    * need to override the position to avoid it being absolutely positioned */
.layer-switcher {
    position: initial;
}

.ol-popup {
  position: absolute;
  background-color: white;
  padding: 15px;
  border: 1px solid #ccc;
  bottom: 12px;
  left: -50px;
  min-width: 200px;
}

.ol-popup:after,
.ol-popup:before {
  top: 100%;
  border: solid transparent;
  content: ' ';
  height: 0;
  width: 0;
  position: absolute;
  pointer-events: none;
}

.ol-popup:after {
  border-top-color: white;
  border-width: 10px;
  left: 48px;
  margin-left: -10px;
}

.ol-popup:before {
  border-top-color: #ccc;
  border-width: 11px;
  left: 48px;
  margin-left: -11px;
}
    </style>
    
</head>
<body>
   	<!-- START OF SIDEBAR DIV -->
    <div id="sidebar" class="sidebar collapsed">
      <!-- Nav tabs -->
      <div class="sidebar-tabs">
        <ul role="tablist">
          <li>
		<a href="#controls" class="sidebar-tab-link" role="tab" data-tab-link="tab-5">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-layers" viewBox="0 0 16 16">
  <path d="M8.235 1.559a.5.5 0 0 0-.47 0l-7.5 4a.5.5 0 0 0 0 .882L3.188 8 .264 9.559a.5.5 0 0 0 0 .882l7.5 4a.5.5 0 0 0 .47 0l7.5-4a.5.5 0 0 0 0-.882L12.813 8l2.922-1.559a.5.5 0 0 0 0-.882zm3.515 7.008L14.438 10 8 13.433 1.562 10 4.25 8.567l3.515 1.874a.5.5 0 0 0 .47 0zM8 9.433 1.562 6 8 2.567 14.438 6z"/>
</svg>
							</a>
          </li>
          <li>
		<a href="#info" class="sidebar-tab-link" role="tab" data-tab-link="tab-2">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
</svg>
							</a>
          </li>
        </ul>

      </div>

      <!-- Tab panes -->
      <div class="sidebar-content">
        <div class="sidebar-pane" id="controls">
          <h1 class="sidebar-header">
            Layers
            <span class="sidebar-close"><i class="fa fa-caret-left"></i></span>
          </h1>
          <div id="basemaps">
                <laybel for="basemap-select">Base Layer</laybel>
                <select id="basemap-select">
                    <option value="osm" selected>OpenStreetMap</option>
                    <option value="carto">Carto</option>
                    <option value="esri">ESRI</option>
                </select>
          </div>

          <!-- !!! HERE WILL GO THE CONTENT OF LAYERSWITCHER !!! -->
          <div id="layers" class="layer-switcher"></div>
          <label>
            <p>Layer opacity:</p>
            <?php $li = 0; foreach($layers as $lay){ ?>
                <label for="opacity-input<?=$li?>"><?=$lay?></label>
                <input id="opacity-input<?=$li?>" type="range" min="0" max="1" step="0.01" value="1" />
                <span id="opacity-output<?=$li?>"><?=$lay?></span>
            <?php $li = $li + 1; } ?>
          </label>
        </div>

	<div class="sidebar-pane" id="info">
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
        </div>

      </div>
    </div>
    <!-- END OF SIDEBAR DIV -->

    <div id="map" class="map"></div>
    <script>
        var osm = new ol.layer.Tile({
            title: 'Base layer',  // A layer must have a title to appear in the layerswitcher
            visible: true,
            source: new ol.source.OSM()
        });
        
        const basemaps = {
          'osm'  : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          'carto': 'https://{1-4}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
          'esri': 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}.png'
        };
        
        <?php $li = 0; foreach($layers as $lay){ ?>
        var wms<?=$li?> = new ol.layer.Image({
            title: '<?=$lay?>', // A layer must have a title to appear in the layerswitcher
            source: new ol.source.ImageWMS({
                url: '<?=$wms_url?>',
                params: {'LAYERS': '<?=$lay?>'},
                serverType: 'geoserver',
                crossOrigin: 'anonymous'
            })
        });
        <?php $li = $li + 1; } ?>

        // Initialize the map
        const bbox = [[24.955967,-124.731423],[49.371735,-66.969849]]
        // swap [min(y,x), max(y,x)] with [min(x,y), max(x,y)]
        const extent = [bbox[0][1], bbox[0][0], bbox[1][1], bbox[1][0]]
        const center = ol.extent.getCenter(extent);

        const map = new ol.Map({
            target: 'map',
            layers: [
                osm  // Base map layer
                <?php $li = 0; foreach($layers as $lay){ ?>
                ,wms<?=$li?>   // WMS layer
                <?php $li = $li + 1; } ?>
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat(center), // Replace with your center coordinates
                zoom: 4 // Adjust zoom level as needed
            })
        });


        // Create a div element for the popup
        const popupContainer = document.createElement('div');
        popupContainer.id = 'popup';
        popupContainer.className = 'ol-popup';
        document.body.appendChild(popupContainer);
        
        // Create the overlay
        const popupOverlay = new ol.Overlay({
          element: popupContainer,
          autoPan: true,
          autoPanAnimation: {
            duration: 250,
          },
        });
        map.addOverlay(popupOverlay);
        
        var sidebar = new ol.control.Sidebar({ element: 'sidebar', position: 'left' });
        var toc = document.getElementById('layers');
        ol.control.LayerSwitcher.renderPanel(map, toc, { reverse: false });
        map.addControl(sidebar);

    // Add click event to display feature info
    map.on('singleclick', function (evt) {
        const viewResolution = map.getView().getResolution();
        const wmsSource = map.getLayers().item(1).getSource();
        const url = wmsSource.getFeatureInfoUrl(
            evt.coordinate,
            viewResolution,
            'EPSG:3857', // Coordinate system
            {'INFO_FORMAT': 'application/json'} // Response format
        );

        if (url) {
          fetch(url)
            .then((response) => response.json())
            .then((data) => {
              if (data.features.length > 0) {
                const feature = data.features[0];
                const properties = feature.properties;
                let content = '<h3>Feature Information</h3><ul>';
                for (const key in properties) {
                  content += `<li><strong>${key}:</strong> ${properties[key]}</li>`;
                }
                content += '</ul>';
                popupContainer.innerHTML = content;
                popupOverlay.setPosition(evt.coordinate);
              } else {
                popupContainer.innerHTML = '<p>No features found.</p>';
                popupOverlay.setPosition(evt.coordinate);
              }
            })
            .catch((error) => {
              console.error('Error fetching feature info:', error);
            });
        }
    });
    
    <?php $li = 0; foreach($layers as $lay){ ?>      
      function update<?=$li?>() {
        const opacityInput<?=$li?> = document.getElementById('opacity-input<?=$li?>');
        const opacityOutput<?=$li?> = document.getElementById('opacity-output<?=$li?>');
        const opacity = parseFloat(opacityInput<?=$li?>.value);
        wms<?=$li?>.setOpacity(opacity);
        opacityOutput<?=$li?>.innerText = opacity.toFixed(2);
      }
    <?php $li = $li + 1; } ?>
    
    function onChange() {
      const base_select = document.getElementById('basemap-select');
      let src = new ol.source.XYZ({
        url : basemaps[base_select.value]
      });
      osm.setSource(src);
    }
    
    $(document).ready(function() {
        <?php $li = 0; foreach($layers as $lay){ ?>      
          const opacityInput<?=$li?> = document.getElementById('opacity-input<?=$li?>');
          opacityInput<?=$li?>.addEventListener('input', update<?=$li?>);
          update<?=$li?>();
          <?php $li = $li + 1; } ?>
        
        const base_select = document.getElementById('basemap-select');
        base_select.addEventListener('change', onChange);
        onChange();
      });
    </script>
</body>
</html>
