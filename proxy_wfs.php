<?php
session_start(['read_and_close' => true]);
require('admin/incl/const.php');
require('admin/class/database.php');
require('admin/class/table.php');
require('admin/class/table_ext.php');
require('admin/class/layer.php');
require('admin/class/qgs_layer.php');

# Redirect MapProxy requests with SERVICE=WFS to proxy_qgis.php

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);

$_GET = array_change_key_case($_GET, CASE_LOWER); 

if(empty($_GET['layers'])){
	http_response_code(400); // Bad Request
	exit(0);
}

# find app layer name
$delim = str_contains($_GET['layers'], '.') ? '.' : ',';
$app_layer = explode($delim, $_GET['layers'])[0];

$result = $obj->getByName($app_layer);
if(!$result || (pg_num_rows($result) == 0)){
	http_response_code(404); // Not Found
	exit(0);
}
$row = pg_fetch_assoc($result);
pg_free_result($result);

require('layers/'.$row['id'].'/env.php');

// convert layer names from MapProxy to QGIS
if($row['exposed'] == 't'){
    $_GET['query_layers'] = $_GET['layers'] = preg_replace('/(^|,)'.preg_quote($app_layer).'\./', '$1', $_GET['layers']);
    if(isset($_GET['typename'])){
        $_GET['typename'] = preg_replace('/(^|,)'.preg_quote($app_layer).'\./', '$1', $_GET['typename']);
    }
}else{
    $_GET['query_layers'] = $_GET['layers'] = implode(',', QGIS_LAYERS);
}

header('Location: /layers/'.$row['id'].'/proxy_qgis.php?'.http_build_query($_GET));
?>
