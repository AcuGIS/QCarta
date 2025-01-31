<?php
	include('../../admin/incl/index_prefix.php');
	include('../../admin/incl/qgis.php');

	$_GET = array_change_key_case($_GET, CASE_LOWER); 
	
	$format = null;
	if(($_GET['request'] == 'GetFeature') && (!empty($_GET['outputformat'])) ){
		$format = urldecode($_GET['outputformat']);
	}else if(($_GET['request'] == 'GetFeatureInfo') && (!empty($_GET['info_format'])) ){
		$format = urldecode($_GET['info_format']);
	}else if($_GET['request'] == 'GetProjectSettings') {
		$format = 'text/xml';
	}else if($_GET['request'] == 'XSL') {
		$format = 'text/xml';
	}else if($_GET['request'] == 'GetCapabilities') {
		$format = 'text/xml';
	}else if(!empty($_GET['format'])){
		$format = urldecode($_GET['format']);
	}else{
		http_response_code(400);	// Bad Request
		die(400);
	}

	format2headers($format, LAYER_ID);
	
	$url = 'http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.QGIS_FILENAME_ENCODED.'&SERVICE=WMTS&'.$_SERVER['QUERY_STRING'];
	if($_GET['request'] == 'GetCapabilities') {
	   $content = file_get_contents($url);
	   $content = preg_replace('/MAP=[a-z0-9%\.]*(&amp;)?/i', '', $content);
	   $acc_key = isset($_GET['access_key']) ? 'access_key='.$_GET['access_key'].'&amp;' : '';
       echo str_replace('://localhost/cgi-bin/qgis_mapserv.fcgi?', '://'.$_SERVER['HTTP_HOST'].'/stores/'.LAYER_ID.'/'.strtolower('WMTS').'.php?'.$acc_key, $content);
	}else{
	   readfile($url);
	}
?>
