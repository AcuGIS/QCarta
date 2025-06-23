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
	$xml = simplexml_load_file($qgis_file);
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

?><!DOCTYPE html>
<html lang="en">
<head>
	<base target="_top">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<title><?=implode(',', QGIS_LAYERS)?></title>
	
	<link rel="shortcut icon" type="image/x-icon" href="docs/images/favicon.ico" />

	<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>	
	<script src="../../assets/dist/js/L.BetterWMS.js"></script>
	<link rel="stylesheet" href="../../assets/dist/css/wms_index.css"/>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

     <style type="text/css">
       
      

       
   .body {
    display: table;
        height: 100%;
    
}

.left-side {
    float: none;
    display: table-cell;
    border: 1px solid;
}

#map {
    float: none;
    display: table-cell;
    border: 1px solid;
}
       
       
</style> 

</head>
<body>
  
  
  <div class="row body">
        <div class="col-xs-9 left-side" style="padding: 20px;">
            <h4 style="color:#666!important">Metadata</h4>
                          <p style="color:#666!important"><b>Title</b>: <?=$qgs_title?></p>
							<p style="color:#666!important"><b>Abstract</b>: <?=$qgs_abstract?></p>
                          
							<p style="color:#666!important"><b>Projection</b>: <?=$projection?></p>
							<p style="color:#666!important"><b>Bounding Box</b>:24.955967, -124.731423, 49.371735, -66.969849</p>
							<p style="color:#666!important"><b>OGC Web Services:</b>:
                              <br>
							 <a href="<?=$proto.'://'.$_SERVER['HTTP_HOST']?>/stores/<?=$store_id?>/wms?REQUEST=GetCapabilities" target="_blank" style="color:#0078A8; text-decoration:none!important">WMS</a>
                                <br>
							 <a href="<?=$proto.'://'.$_SERVER['HTTP_HOST']?>/stores/<?=$store_id?>/wfs?REQUEST=GetCapabilities" target="_blank" style="color:#0078A8!; text-decoration:none!important">WFS</a>
                                  <br>
							 <a href="<?=$proto.'://'.$_SERVER['HTTP_HOST']?>/stores/<?=$store_id?>/wmts?REQUEST=GetCapabilities" target="_blank" style="color:#0078A8!; text-decoration:none!important">WMTS</a>
							</p>
							<p>* Bounding box format is <i>min(Y,X);max(Y,X)</i></p>   
                                  
                                  
    	
                                  
        </div>
  
  

<div id='map'></div>
 

<script type="text/javascript">    
	const map = L.map('map', {
		center: [0, 0],
		zoom: 16,
  		zoomControl: 'false'
	});
    
	const bbox = {
      minx: -124.731423,
      miny: 24.955967,
      maxx: -66.969849,
      maxy: 49.371735
    };

// WMS Layer
	<?php $layers = explode(',', 'usa'); $li = 0;
		 foreach($layers as $lay){ ?>
	const wms<?=$li?> = L.tileLayer.betterWms('<?=$wms_url?>', {
		layers: '<?=$lay?>',
		transparent: 'true',
  	format: 'image/png',
		maxZoom:25
	}).addTo(map);

	<?php $li = $li + 1; } ?>

	map.fitBounds([[bbox.miny, bbox.minx],[bbox.maxy,bbox.maxx]]);

	// Legend
	var legend = L.control({position: 'bottomright'}); 
	legend.onAdd = function (map) {        
    	var div = L.DomUtil.create('div', 'info legend');
    	div.innerHTML = '<img src="proxy_qgis.php?SERVICE=WMS&REQUEST=GetLegendGraphic&LAYERS=<?=urlencode(implode(',', QGIS_LAYERS))?>&FORMAT=image/png">';     
    	return div;
	};
	legend.addTo(map);
</script>  
</body>
</html>
