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
	
	readfile('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.QGIS_FILENAME_ENCODED.'&SERVICE=WFS&'.$_SERVER['QUERY_STRING']);
?>
