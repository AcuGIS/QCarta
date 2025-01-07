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
	
	readfile('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.urlencode(QGIS_FILENAME).'&SERVICE=WFS&'.$_SERVER['QUERY_STRING']);
?>
