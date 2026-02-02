<?php
	include('../../admin/incl/index_prefix.php');

	require('../../admin/class/table_ext.php');
	require('../../admin/class/pglink.php');
	require('../../admin/class/layer.php');
  require('../../admin/class/pg_layer.php');
	
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$user_id = (IS_PUBLIC) ? SUPER_ADMIN_ID : $_SESSION[SESS_USR_KEY]->id;
	$obj = new pg_layer_Class($database->getConn(), $user_id);
	
	// get layer
	$result = $obj->getById(LAYER_ID);
	if($result){
		$row = pg_fetch_object($result);
		pg_free_result($result);
		
		//get store
		$pg_obj = new pglink_Class($database->getConn(), $user_id);
		
		$pg_res = $pg_obj->getById($row->store_id);
		$pgr = pg_fetch_object($pg_res);
		pg_free_result($pg_res);
		
		$store_db = new Database($pgr->host, $pgr->dbname, $pgr->username, $pgr->password, $pgr->port, $pgr->schema);
		
		header('Content-Type: application/json');
		$store_db->getGeoJSON($pgr->schema, $row->tbl, $row->geom);
		
	}else{
		die('Error: No such layer');
	}
?>