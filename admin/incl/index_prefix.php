<?php
session_start(['read_and_close' => true]);
require('../../admin/incl/const.php');
require('../../admin/class/database.php');
require('../../admin/class/table.php');
require('../../admin/class/access_key.php');
require('env.php');

if(!IS_PUBLIC){
	$whitelist = array('127.0.0.1', '::1', '127.0.1.1');
	
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$user_id = 0;
	$tbl = defined('QGIS_LAYERS') ? 'layer' : 'store';
	$allow = false;
	
	if(in_array($_SERVER['REMOTE_ADDR'], $whitelist)){
		$allow = true;
	}else if(!empty($_GET['access_key'])){
		$allow = $database->check_key_tbl_access($tbl, $_GET['access_key'], $_SERVER['REMOTE_ADDR'], LAYER_ID);
	}else if(isset($_SESSION[SESS_USR_KEY])) { 	// local access with login
		$allow = $database->check_user_tbl_access($tbl, LAYER_ID, $_SESSION[SESS_USR_KEY]->id);
	}else{
		header('Location: ../../login.php');
		exit;
	}
	
	// check if user can access the resource
	if(!$allow){
		http_response_code(405);	//not allowed
		die('Sorry, access not allowed!');
	}
}
?>