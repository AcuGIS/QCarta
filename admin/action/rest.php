<?php
	session_start(['read_and_close' => true]);
	require('../incl/const.php');
	require('../incl/app.php');
	require('../incl/qgis.php');
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
	
	require('../class/geostory.php');
	require('../class/web_link.php');
	require('../class/topic.php');
	require('../class/doc.php');
	require('../class/basemap.php');
	
	function filter_by_user_access($db_obj, $tbl_prefix, $user_id, $rows){
	    $filtered = [];
	    foreach($rows as $row){
        	if($row['public'] || $db_obj->check_user_tbl_access($tbl_prefix, $row['id'], $user_id)){
             $filtered[] = $row;
            }
        }
        return $filtered;
	}
	
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
        header('WWW-Authenticate: Basic realm="QCarta REST"');
        header('HTTP/1.0 401 Unauthorized');
        exit(0);
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
			    $row['public'] = ($row['public'] == 't');   // change to boolean
				$rows[] = $row;
			}
		}else{
			$obj = new qgs_Class($database->getConn(), SUPER_ADMIN_ID);
			
			$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
			while ($row = pg_fetch_assoc($result)) {
				$row['wms_url'] = $base_url.'/'.$row['id'].'/wms'.$url_key_param;
				$row['wfs_url'] = $base_url.'/'.$row['id'].'/wfs'.$url_key_param;
				$row['wmts_url'] = $base_url.'/'.$row['id'].'/wmts'.$url_key_param;
				$row['public'] = ($row['public'] == 't');   // change to boolean

				$qgis_file = find_qgs(DATA_DIR.'/stores/'.$id);
				if($qgis_file !== false){
				    $xml = simplexml_load_file($qgis_file);
					list($DefaultViewExtent) = $xml->xpath('/qgis/ProjectViewSettings/DefaultViewExtent');
					$row['bbox'] = ['minx' => $DefaultViewExtent['xmin'], 'miny' => $DefaultViewExtent['ymin'], 'maxx' => $DefaultViewExtent['xmax'], 'maxy' => $DefaultViewExtent['ymax'] ];
					$row['layers'] = qgs_ordered_layers($xml);
				}
				$rows[] = $row;
			}
		}
		pg_free_result($result);
		
		$single = substr($_GET['q'], 0, -1);
		$stores = [$single => filter_by_user_access($database, 'store', $user_id, $rows)];
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
				$row['public'] = ($row['public'] == 't');   // change to boolean
				$rows[] = $row;
			}

		}else{
			$obj = new qgs_Class($database->getConn(), SUPER_ADMIN_ID);
			$stores = $obj->getArr();
			
			$obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);
			$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
			while ($row = pg_fetch_assoc($result)) {
				$row['name'] = $stores[$row['store_id']].':'.$row['name'];
				$row['public'] = ($row['public'] == 't');   // change to boolean

				if($row['proxyfied'] == 't'){
					require_once(__DIR__ . '/../../inc/mproxy.php');
					try {
						$base_url = getMproxyBaseUrl($row['store_id']);
						$row['url'] = $proto.'://'.$_SERVER['HTTP_HOST'].$base_url.$url_key_param;
					} catch (Exception $e) {
						error_log("admin/action/rest.php: Failed to get mproxy base URL for store_id {$row['store_id']}: " . $e->getMessage());
						$row['url'] = $proto.'://'.$_SERVER['HTTP_HOST'].'/mproxy/service'.$url_key_param; // Fallback
					}
				}else{
					$row['url'] = $base_url.'/'.$row['id'].'/geojson.php'.$url_key_param;
				}
				$rows[] = $row;
			}
		}
		pg_free_result($result);
		$layers = ['layer' => filter_by_user_access($database, 'layer', $user_id, $rows)];
		$reply = ['success' => true, 'layers' => $layers];

	}else if($_GET['q'] == 'layer'){	// /rest/layer/top:usa
	    if(is_numeric($_GET['l'])){
			$layer_row = $database->get('public.layer', 'id=\''.$_GET['l'].'\'');
		}else{
    		list($store_name, $layer_name) = explode(':', $_GET['l']);
    		$store_row = $database->get('public.store', 'name=\''.$store_name.'\'');
    		$layer_row = $database->get('public.layer', 'name=\''.$layer_name.'\' AND store_id='.$store_row['id']);
		}
		
		$_GET['t'] = $layer_row['type'];
		
		$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/layers';
		
		if($_GET['t'] == 'pg'){
			$obj = new pg_layer_Class($database->getConn(), SUPER_ADMIN_ID);
			
			$result = $obj->getById($layer_row['id']);
			if($result){
				$row = pg_fetch_assoc($result);
				$row['public'] = ($row['public'] == 't');   // change to boolean
				
				if(!$row['public'] && !$database->check_user_tbl_access('layer', $row['id'], $user_id)){
					http_response_code(401);	//unauthorized
					$reply = ['success' => false, 'message' => 'Error: Invalid credentials'];
					echo json_encode($reply);
					exit(0);
				}else{
					//$row['name'] = $_GET['l'];
					$row['url'] = $base_url.'/'.$row['id'].'/geojson.php'.$url_key_param;
				}
			}

		}else{

			$obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);
			$result = $obj->getById($layer_row['id']);
			if($result){
				$row = pg_fetch_assoc($result);
				$row['public'] = ($row['public'] == 't');   // change to boolean
				
				if(!$row['public'] && !$database->check_user_tbl_access('layer', $row['id'], $user_id)){
					http_response_code(401);	//unauthorized
					$reply = ['success' => false, 'message' => 'Error: Invalid credentials'];
					echo json_encode($reply);
					exit(0);
				}else{
					//$row['name'] = $_GET['l'];
					if($row['proxyfied'] == 't'){
						require_once(__DIR__ . '/../../inc/mproxy.php');
						try {
							$base_url = getMproxyBaseUrl($row['store_id']);
							$row['url'] = $proto.'://'.$_SERVER['HTTP_HOST'].$base_url.$url_key_param;
						} catch (Exception $e) {
							error_log("admin/action/rest.php: Failed to get mproxy base URL for store_id {$row['store_id']}: " . $e->getMessage());
							$row['url'] = $proto.'://'.$_SERVER['HTTP_HOST'].'/mproxy/service'.$url_key_param; // Fallback
						}
					}else{
						$row['url'] = $base_url.'/'.$row['id'].'/proxy_qgis.php'.$url_key_param;
					}
				}
			}
		}
		pg_free_result($result);
		$reply = ['success' => true, 'layer' => $row];
	}else if($_GET['q'] == 'store'){	// /rest/store/top
        
	    $row = [];
					
		$obj = new qgs_Class($database->getConn(), SUPER_ADMIN_ID);
		if(is_numeric($_GET['l'])){
		    $store_res = $obj->getById($_GET['l']);
		}else{
		    $store_res = $obj->getByName($_GET['l']);
		}

		if($store_res == null){
		  $reply = ['success' => true, 'message' => 'Database error'];
		}else if(pg_num_rows($store_res) == 0){
		  $reply = ['success' => true, 'message' => 'Store not found'];
		}else{
            $store_row = pg_fetch_assoc($store_res);
    		pg_free_result($store_res);
    		
            $row['name'] = $store_row['name'];
      
            if(!$store_row['public'] && !$database->check_user_tbl_access('store', $store_row['id'], $user_id)){
				http_response_code(401);	//unauthorized
				$reply = ['success' => false, 'message' => 'Error: Invalid credentials'];
				echo json_encode($reply);
				exit(0);
			}else{
      		    $row['id'] = $store_row['id'];
                $row['public'] = ($store_row['public'] == 't');   // change to boolean
      		
              	$path = DATA_DIR.'/stores/'.$store_row['id'];
                $path_len = strlen($path) + 1;
      		
       	        $directory  = new \RecursiveDirectoryIterator($path);
                $iterator   = new \RecursiveIteratorIterator($directory);
                
                $files = array();
                foreach ($iterator as $info) {
                    $fp = $info->getPathname();
                    if (is_file($fp)) {
                        $files[] = array('path' =>substr($fp, $path_len), 'mtime' => filemtime($fp));
                    }
                }
                $row['files'] = $files;
                $row['post_max_size'] = return_bytes(ini_get('post_max_size'));
                
                $id = $store_row['id'];
                $qgis_file = find_qgs(DATA_DIR.'/stores/'.$id);
				if($qgis_file !== false){
					
					$xml = simplexml_load_file($qgis_file);
					list($DefaultViewExtent) = $xml->xpath('/qgis/ProjectViewSettings/DefaultViewExtent');
					
					$bounding_box = ['minx' => floatval($DefaultViewExtent['xmin']), 'miny' => floatval($DefaultViewExtent['ymin']), 'maxx' => floatval($DefaultViewExtent['xmax']), 'maxy' => floatval($DefaultViewExtent['ymax']) ];
					list($projection) = $xml->xpath('/qgis/ProjectViewSettings/DefaultViewExtent/spatialrefsys/authid');
					
					$layout_names = [];
					list($layouts) = $xml->xpath('/qgis/Layouts//Layout/@name');
					foreach($layouts as $name){
					    $layout_names[] = (string)$name;
					}
					
					$layer_names = qgs_ordered_layers($xml);
					
					$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
					$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/stores/'.$id;
					$html_dir = WWW_DIR.'/layers/'.$id;
					$kv = ['projection' => (string) $projection, 'bbox' => $bounding_box, 'layouts' => $layout_names, 'layers' => $layer_names,
						'WMS'  => $base_url.'/wms?REQUEST=GetCapabilities',
						'WFS' => $base_url.'/wfs?REQUEST=GetCapabilities',
						'WMTS' => $base_url.'/wmts?REQUEST=GetCapabilities',
						'OpenLayers' => $base_url.'/wms?REQUEST=GetProjectSettings'
					];
					foreach($kv as $k => $v){
					    $row[$k] = $v;
					}
				}
					
                $reply = ['success' => true, 'store' => $row];
			}
		}
	}else if($_GET['q'] == 'geostories'){	// /rest/geostories
		
		$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/geostory';
		
		$obj = new geostory_Class($database->getConn(), SUPER_ADMIN_ID);
		$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
		while ($row = pg_fetch_assoc($result)) {
		    $row['public'] = ($row['public'] == 't');   // change to boolean
			$row['url'] = $base_url.'/'.$row['id'].'/index.php'.$url_key_param;
			$rows[] = $row;
		}
		
		pg_free_result($result);
		$geostories = ['geostory' => filter_by_user_access($database, 'geostory', $user_id, $rows)];
		$reply = ['success' => true, 'geostories' => $geostories];

	}else if($_GET['q'] == 'geostory'){	// /rest/geostory/Bee%20Farming

		if(is_numeric($_GET['l'])){
		    $row = $database->get('public.geostory', 'id=\''.$_GET['l'].'\'');
		}else{
		    $row = $database->get('public.geostory', 'name=\''.$_GET['l'].'\'');
		}
				
		$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/geostory';
		
		$row['public'] = ($row['public'] == 't');   // change to boolean
        $row['url'] = $base_url.'/'.$row['id'].'/index.php'.$url_key_param;

        if($row['public'] || $database->check_user_tbl_access('geostory', $row['id'], $user_id)){
		    $reply = ['success' => true, 'geostory' => $row];
		}else{
            http_response_code(403);	//403 Forbidden
            $reply = ['success' => false, 'message' => 'Error: Access not allowed'];
		}

	}else if($_GET['q'] == 'web_links'){	// /rest/web_links
		
		$obj = new web_link_Class($database->getConn(), SUPER_ADMIN_ID);
		$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
		while ($row = pg_fetch_assoc($result)) {
		    $row['public'] = ($row['public'] == 't');   // change to boolean
			$rows[] = $row;
		}
		
		pg_free_result($result);
		$web_links = ['web_link' => filter_by_user_access($database, 'web_link', $user_id, $rows)];
		$reply = ['success' => true, 'web_links' => $web_links];

	}else if($_GET['q'] == 'web_link'){	// /rest/geostory/Bee%20Farming
    	if(is_numeric($_GET['l'])){
    	    $row = $database->get('public.web_link', 'id=\''.$_GET['l'].'\'');
    	}else{
    	    $row = $database->get('public.web_link', 'name=\''.$_GET['l'].'\'');
    	}
		$row['public'] = ($row['public'] == 't');   // change to boolean
		
		if($row['public'] || database->check_user_tbl_access('web_link', $row['id'], $user_id)){
		    $reply = ['success' => true, 'web_link' => $row];
		}else{
		    http_response_code(403);	//403 Forbidden
            $reply = ['success' => false, 'message' => 'Error: Access not allowed'];
		}

	}else if($_GET['q'] == 'docs'){	// /rest/web_links

		$obj = new doc_Class($database->getConn(), SUPER_ADMIN_ID);
		$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
		while ($row = pg_fetch_assoc($result)) {
		    $row['public'] = ($row['public'] == 't');   // change to boolean
			$rows[] = $row;
		}

		pg_free_result($result);
		$docs = ['doc' => filter_by_user_access($database, 'doc', $user_id, $rows)];
		$reply = ['success' => true, 'docs' => $docs];

	}else if($_GET['q'] == 'doc'){	// /rest/geostory/Bee%20Farming
		
        if(is_numeric($_GET['l'])){
    	    $row = $database->get('public.doc', 'id=\''.$_GET['l'].'\'');
    	}else{
    	    $row = $database->get('public.doc', 'name=\''.$_GET['l'].'\'');
    	}
		$row['public'] = ($row['public'] == 't');   // change to boolean
		
		if($row['public'] || $database->check_user_tbl_access('doc', $row['id'], $user_id)){
		    $reply = ['success' => true, 'doc' => $row];
		}else{
		    http_response_code(403);	//403 Forbidden
            $reply = ['success' => false, 'message' => 'Error: Access not allowed'];
		}

	}else if($_GET['q'] == 'topics'){	// /rest/topics

		$obj = new topic_Class($database->getConn(), SUPER_ADMIN_ID);
		$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
		while ($row = pg_fetch_assoc($result)) {
			$rows[] = $row;
		}

		pg_free_result($result);
		$topics = ['topic' => $rows];
		$reply = ['success' => true, 'topics' => $topics];

	}else if($_GET['q'] == 'gemets'){	// /rest/gemets

		$obj = new topic_Class($database->getConn(), SUPER_ADMIN_ID, 'gemet');
		$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
		while ($row = pg_fetch_assoc($result)) {
			$rows[] = $row;
		}

		pg_free_result($result);
		$gemets = ['gemet' => $rows];
		$reply = ['success' => true, 'gemets' => $gemets];

	}else if($_GET['q'] == 'basemaps'){	// /rest/basemaps

		$obj = new basemap_Class($database->getConn(), SUPER_ADMIN_ID);
		$result = ($user_id == 0) ? $obj->getPublic() : $obj->getRows();
		while ($row = pg_fetch_assoc($result)) {
		    $row['public'] = ($row['public'] == 't');   // change to boolean
			$rows[] = $row;
		}

		pg_free_result($result);
		$basemaps = ['basemap' => filter_by_user_access($database, 'basemaps', $user_id, $rows)];
		$reply = ['success' => true, 'basemaps' => $basemaps];

	}else if($_GET['q'] == 'basemap'){	// /rest/basemap/OpenStreetMap
	    if(is_numeric($_GET['l'])){
    	    $row = $database->get('public.basemaps', 'id=\''.$_GET['l'].'\'');
    	}else{
    	    $row = $database->get('public.basemaps', 'name=\''.$_GET['l'].'\'');
    	}
		$row['public'] = ($row['public'] == 't');   // change to boolean
		
		if($row['public'] || $database->check_user_tbl_access('basemaps', $row['id'], $user_id)){
		    $reply = ['success' => true, 'basemap' => $row];
		}else{
		    http_response_code(403);	//403 Forbidden
            $reply = ['success' => false, 'message' => 'Error: Access not allowed'];
		}

	}else if($_GET['q'] == 'layer_metadata'){	// /rest/layer_metadata/top:usa
		$layer_name = explode($_GET['l']);

		$l_row = $database->get('public.layer', 'name=\''.$layer_name.'\'');
		$l_row['public'] = ($l_row['public'] == 't');   // change to boolean
		
		if($l_row['public'] || $database->check_user_tbl_access('layer', $l_row['id'], $user_id)){
		    $row = $database->get('public.layer_metadata', 'layer_id = '.$l_row['id']);
		    $reply = ['success' => true, 'layer_metadata' => $row];
		}else{
            http_response_code(403);	//403 Forbidden
            $reply = ['success' => false, 'message' => 'Error: Access not allowed'];
		}

	}else{
		$reply = ['success' => false, 'message' => 'Error: Unknown query'];
		http_response_code(400);	// bad request
	}

	header('Content-Type: application/json');
	echo json_encode($reply);
?>
