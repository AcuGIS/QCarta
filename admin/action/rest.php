<?php
	session_start(['read_and_close' => true]);
	require('../incl/const.php');
	require('../class/database.php');
	require('../class/table.php');
	require('../class/user.php');
	require('../class/access_key.php');
	require('../class/table_ext.php');
	require('../class/qgs.php');
	require('../class/pglink.php');
	require('../class/layer.php');
	require('../class/qgs_layer.php');
	require('../class/pg_layer.php');
	
	$reply = ['success' => false, 'message' => 'Error while processing your request!'];
	
	// check method
	if($_SERVER["REQUEST_METHOD"] != 'GET'){
		http_response_code(405);	//Method Not Allowed
		$reply = ['success' => false, 'message' => 'Error: Only GET is supported'];
		echo json_encode($reply);
		exit(0);
	}
	
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	
	// check user
	$user_id = 0;
	$url_key_param = '';

  if(isset($_SESSION[SESS_USR_KEY])) {	// session auth
		$user_id = $_SESSION[SESS_USR_KEY]->id;
	
	}else if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Quail REST"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
	}else if($_SERVER['PHP_AUTH_USER'] == ''){
		// public access, no user entered
	}else {	// HTTP auth

		
		if($_SERVER['PHP_AUTH_USER'] == 'access_key'){
			$acc_obj = new access_key_Class($database->getConn(), SUPER_ADMIN_ID);
			$row = $acc_obj->check_key($_SERVER['PHP_AUTH_PW']);
			if($row){
				$user_id = $row->owner_id;
				$url_key_param = '?access_key='.$_SERVER['PHP_AUTH_PW'];
			}
		}else{
			$user_obj = new user_Class($database->getConn(), SUPER_ADMIN_ID);
			$row = $user_obj->loginCheck($_SERVER['PHP_AUTH_PW'], $_SERVER['PHP_AUTH_USER']);
			if($row){
				$user_id = $row->id;
			}
		}
		
		if($row == null){
			http_response_code(401);	//unauthorized
			$reply = ['success' => false, 'message' => 'Error: Invalid credentials'];
			echo json_encode($reply);
			exit(0);
		}
	}
	
	$rows = array();
	$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
	
	// REST query
	if(($_GET['q'] == 'stores') || ($_GET['q'] == 'workspaces')){
		$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/stores';
		
		if($_GET['t'] == 'pg'){
			$obj = new pglink_Class($database->getConn(), SUPER_ADMIN_ID);
			$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
			while ($row = pg_fetch_assoc($result)) {
				$rows[] = $row;
			}
		}else{
			$obj = new qgs_Class($database->getConn(), SUPER_ADMIN_ID);
			
			$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
			while ($row = pg_fetch_assoc($result)) {
				$row['wms_url'] = $base_url.'/'.$row['id'].'/wms.php'.$url_key_param;
				$row['wfs_url'] = $base_url.'/'.$row['id'].'/wfs.php'.$url_key_param;
				$row['wmts_url'] = $base_url.'/'.$row['id'].'/wmts.php'.$url_key_param;
				
				$rows[] = $row;
			}
		}
		pg_free_result($result);
		
		$single = substr($_GET['q'], 0, -1);
		$stores = [$single => $rows];
		$reply = ['success' => true, $_GET['q'] => $stores];

	}else if($_GET['q'] == 'layers'){	// /rest/layers
		
		$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/layers';
		
		if($_GET['t'] == 'pg'){
			
			$obj = new pglink_Class($database->getConn(), SUPER_ADMIN_ID);
			$stores = $obj->getArr();
			
			$obj = new pg_layer_Class($database->getConn(), SUPER_ADMIN_ID);
			$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
			while ($row = pg_fetch_assoc($result)) {
				$row['name'] = $stores[$row['store_id']].':'.$row['name'];
				$row['url'] = $base_url.'/'.$row['id'].'/geojson.php'.$url_key_param;
				$rows[] = $row;
			}

		}else{
			$obj = new qgs_Class($database->getConn(), SUPER_ADMIN_ID);
			$stores = $obj->getArr();
			
			$obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);
			$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
			while ($row = pg_fetch_assoc($result)) {
				$row['name'] = $stores[$row['store_id']].':'.$row['name'];
				if($row['proxyfied'] == 't'){
					$row['url'] = $proto.'://'.$_SERVER['HTTP_HOST'].'/mproxy/service'.$url_key_param;
				}else{
					$row['url'] = $base_url.'/'.$row['id'].'/geojson.php'.$url_key_param;
				}
				$rows[] = $row;
			}
		}
		pg_free_result($result);
		$layers = ['layer' => $rows];
		$reply = ['success' => true, 'layers' => $layers];

	}else if($_GET['q'] == 'layer'){	// /rest/layer/top:usa
		list($store_name, $layer_name) = explode(':', $_GET['l']);
		
		$store_row = $database->get('public.store', 'name=\''.$store_name.'\'');
		$layer_row = $database->get('public.layer', 'name=\''.$layer_name.'\' AND store_id='.$store_row['id']);
		
		$_GET['t'] = $layer_row['type'];
		
		$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/layers';
		
		if($_GET['t'] == 'pg'){
			$obj = new pg_layer_Class($database->getConn(), SUPER_ADMIN_ID);
			
			$result = $obj->getById($layer_row['id']);
			if($result){
				$row = pg_fetch_assoc($result);
				
				if(($row['public'] != 't') && ($row['owner_id'] != $user_id)){
					http_response_code(401);	//unauthorized
					$reply = ['success' => false, 'message' => 'Error: Invalid credentials'];
					echo json_encode($reply);
					exit(0);
				}else{
					$row['name'] = $_GET['l'];
					$row['url'] = $base_url.'/'.$row['id'].'/geojson.php'.$url_key_param;
				}
			}

		}else{

			$obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);
			$result = $obj->getById($layer_row['id']);
			if($result){
				$row = pg_fetch_assoc($result);
				
				if(($row['public'] != 't') && ($row['owner_id'] != $user_id)){
					http_response_code(401);	//unauthorized
					$reply = ['success' => false, 'message' => 'Error: Invalid credentials'];
					echo json_encode($reply);
					exit(0);
				}else{
					$row['name'] = $_GET['l'];
					if($row['proxyfied'] == 't'){
						$row['url'] = $proto.'://'.$_SERVER['HTTP_HOST'].'/mproxy/service'.$url_key_param;
					}else{
						$row['url'] = $base_url.'/'.$row['id'].'/proxy_qgis.php'.$url_key_param;
					}
				}
			}
		}
		pg_free_result($result);
		$reply = ['success' => true, 'layer' => $row];
		
	}else{
		$reply = ['success' => false, 'message' => 'Error: Unknown query'];
		http_response_code(400);	// bad request
	}

	header('Content-Type: application/json');
	echo json_encode($reply);
?>