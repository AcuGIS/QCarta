<?php
session_start(['read_and_close' => true]);
require('admin/incl/const.php');
require('admin/class/database.php');

# Redirect MapProxy requests with SERVICE=WFS to proxy_qgis.php

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);

$l = null;
if(!empty($_GET['layers'])){
	$l = $_GET['layers'];
}else if(!empty($_GET['LAYERS'])){
	$l = $_GET['LAYERS'];
}else{
	http_response_code(400); // Bad Request
	exit(0);
}

$row = $database->get('public.layer', 'name=\''.$l.'\'');
if(!$row){
	http_response_code(404); // Not Found
	exit(0);
}

// change $_GET['LAYERS'] from MapProxy to QGIS names
require('layers/'.$row['id'].'/env.php');
$_GET['QUERY_LAYERS'] = $_GET['LAYERS'] = implode(',', QGIS_LAYERS);

header('Location: /layers/'.$row['id'].'/proxy_qgis.php?'.http_build_query($_GET));

#chdir('layers/'.$row['id']);
#include('proxy_qgis.php');
?>