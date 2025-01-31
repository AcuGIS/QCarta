<?php
	//const QGIS_FILENAME = 'QGIS_FILE_VALUE';
	include('../../admin/incl/index_prefix.php');

	$format = '';
	if(preg_match('/FORMAT=([a-z]+)&/',$_SERVER['QUERY_STRING'], $matches)){
		$format = $matches[1];
		if(strcasecmp($format, 'pdf') == 0){
			header("Content-Type: application/pdf");
			header('Content-Disposition: attachment; filename="'.str_replace('.qgs', '.pdf', basename(QGIS_FILENAME)).'"');
		}else if(strcasecmp($format, 'png') == 0){
			header("Content-Type: application/png");
			header('Content-Disposition: attachment; filename="'.str_replace('.qgs', '.png', basename(QGIS_FILENAME)).'"');
		}else{
			header("Content-Type: text/xml");
		}
	}else{
		header("Content-Type: text/xml");
	}
	
	$url = 'http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.QGIS_FILENAME_ENCODED.'&SERVICE=WFS&'.$_SERVER['QUERY_STRING'];
	if($_GET['request'] == 'GetCapabilities') {
	   $content = file_get_contents($url);
	   $content = preg_replace('/MAP=[a-z0-9%\.]*(&amp;)?/i', '', $content);
	   $acc_key = isset($_GET['access_key']) ? 'access_key='.$_GET['access_key'].'&amp;' : '';
       echo str_replace('://localhost/cgi-bin/qgis_mapserv.fcgi?', '://'.$_SERVER['HTTP_HOST'].'/stores/'.LAYER_ID.'/'.strtolower('WFS').'.php?'.$acc_key, $content);
	}else{
	   readfile($url);
	}
?>
