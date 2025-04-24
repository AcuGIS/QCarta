<?php
  session_start(['read_and_close' => true]);
	require('../incl/const.php');
	require('../incl/app.php');
  require('../class/database.php');
	require('../class/table.php');
	require('../class/table_ext.php');
	require('../class/layer.php');
  require('../class/qgs_layer.php');
	require('../class/mapproxy.php');
	require('../incl/qgis.php');
		
	function layer_get_featurename($qgs_file, $layers){
		$xml_data = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.urlencode($qgs_file).'&SERVICE=WMS&REQUEST=DescribeLayer&LAYERS='.urlencode($layers).'&SLD_VERSION=1.1.0');
		if(preg_match('/FeatureTypeName>(.*)</', $xml_data, $matches)){
			return $matches[1];
		}
		return null;
	}
	
	function layer_save_thumbnail($qgs_file, $post, $bbox){
		$url  = 'http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.urlencode($qgs_file).'&SERVICE=WMS&request=GetMap';
		$url .= '&layers='.urlencode($post['layers']).'&bbox='.urlencode($bbox["minx"].','.$bbox["miny"].','.$bbox['maxx'].','.$bbox['maxy']).'&width=300&height=200&srs=EPSG%3A4326&FORMAT=image%2Fpng';
		$png_data = file_get_contents($url);
		
		file_put_contents(WWW_DIR.'/assets/layers/'.$post['id'].'.png', $png_data);
	}
	
	function qgs_get_bounding_box($qgs_file){
		$xml = layer_get_capabilities($qgs_file);
		$bboxes = $xml->Capability->Layer->BoundingBox;
		foreach($bboxes as $bb){
			if($bb['SRS'] == 'EPSG:4326'){
				return $bb;
			}
		}
		return null;
	}
	
	// example: service=WMS&version=1.1.0&request=GetMap&layers=tiger%3Agiant_polygon&bbox=-180.0%2C-90.0%2C180.0%2C90.0&width=768&height=384&srs=EPSG%3A4326&styles=&format=application/openlayers
	function layer_get_wms_query($qgs_file, $row){

		$bbox = layers_get_bbox($qgs_file, $row->layers);
		
		$xlen = abs($bbox['maxx'] - $bbox['minx']);
		$ylen = abs($bbox['maxy'] - $bbox['miny']);
		$wh_ratio = $xlen / $ylen;
		
		$height = 768;
		$width = intval($height * $wh_ratio);
		
		$query  = 'service=WMS&version=1.1.0&request=GetMap';
		$query .= '&layers='.urlencode($row->layers);
		$query .= '&bbox='.urlencode($bbox["minx"].','.$bbox["miny"].','.$bbox['maxx'].','.$bbox['maxy']);
		$query .= '&width='.$width.'&height='.$height.'&srs='.urlencode('EPSG:4326');
		return $query;
	}
	
	// example: service=WFS&version=1.0.0&request=GetFeature&typeName=tiger%3Agiant_polygon&maxFeatures=50
	function layer_get_wfs_query($qgs_file, $row){
		$query  = 'service=WFS&version=1.1.0&request=GetFeature';
		$query .= '&typeName='.urlencode(layer_get_featurename($qgs_file, $row->layers));
		$query .= '&maxFeatures=50';
		return $query;
	}
	
	function install_layer($id, $post){

		$html_dir = WWW_DIR.'/layers/'.$id;
		$data_dir = DATA_DIR.'/layers/'.$id;
		
		mkdir($html_dir);
		mkdir($data_dir);
		
		if(!is_dir($html_dir) || !is_dir($data_dir)){
			return false;
		}

		$post['id'] = $id;
		$qgs_file = find_qgs(DATA_DIR.'/stores/'.$post['store_id']);
		
		//create .env
		$is_public = $post['public'] == 't' ? 'true' : 'false';
		$is_cached = $post['cached'] == 't' ? 'true' : 'false';
		$qgs_layers = "array('".str_replace(",", "','", $post['layers'])."')";
		$show_dt = $post['show_dt'] == 't'  ? 'true' : 'false';
		
		$vars = [ 'LAYER_ID' => $post['id'],
			'IS_PUBLIC' => $is_public, 'QGIS_FILENAME_ENCODED' => "'".urlencode($qgs_file)."'",
			'QGIS_LAYERS' => $qgs_layers, 'CACHE_ENABLED' => $is_cached,
			'SHOW_DATATABLES' => $show_dt, 'QGIS_LAYOUT' => "'".$post['print_layout']."'" ];

		copy('../snippets/layer_env.php', $html_dir.'/env.php');
		update_env($html_dir.'/env.php', $vars);

		copy('../snippets/proxy_qgis.php', $html_dir.'/proxy_qgis.php');
		copy('../snippets/layer_wms.php', $html_dir.'/wms.php');
		
		$vars = ["DATA_FOLDER.'/'" => "DATA_DIR.'/stores/".$post['store_id']."'"];
		update_template(WWW_DIR.'/admin/snippets/img_filep.php', $html_dir.'/img_filep.php', $vars);

		$bbox = layers_get_bbox($qgs_file, $post['layers']);
		$wms_url = ($post['proxyfied'] == 't') ? '/mproxy/service' : 'proxy_qgis.php';
		
		$mproxy_layer_names = array();
		if(($post['proxyfied'] == 't') && ($post['exposed'] == 't')){
			$lays = explode(',', $post['layers']);
			foreach($lays as $l){
				array_push($mproxy_layer_names, $post['name'].'.'.$l);
			}
		}else{
			array_push($mproxy_layer_names, $post['name']);
		}
		
		$wms_layers = ($post['proxyfied'] == 't') ? implode(',', $mproxy_layer_names) : $post['layers'];
		
		$vars = ['BOUNDING_BOX' => '[['.$bbox["miny"].','.$bbox["minx"].'],['.$bbox['maxy'].','.$bbox['maxx'].']]',
			'WMS_URL' => $wms_url, 'SECRET_KEY' => $_SESSION[SESS_USR_KEY]->secret_key, 'WMS_LAYERS' => $wms_layers
		];
		update_template('../snippets/wms_index.php', $html_dir.'/index.php', $vars);
		update_template('../snippets/ol_index.php', $html_dir.'/ol_index.php', $vars);
		
		// create cachee dir tree
		mkdir(CACHE_DIR.'/layers/'.$id);
		
		$hexvals = array_merge( range(0,9), range('a','f'));
		foreach($hexvals as $v){
			mkdir(CACHE_DIR.'/layers/'.$id.'/'.$v);
		}
		
		if($post['proxyfied'] == 't'){
			if($post['exposed'] == 't'){
				$lays = explode(',', $post['layers']);
				foreach($lays as $l){
					mapproxy_Class::mapproxy_add_source($post['name'].'.'.$l, $qgs_file, $l);
				}
			}else{
				mapproxy_Class::mapproxy_add_source($post['name'], $qgs_file, $post['layers']);
			}
		 mapproxy_Class::mapproxy_add_seed($mproxy_layer_names, $id);
		}

		layer_save_thumbnail($qgs_file, $post, $bbox);

		return true;
	}
	
	function update_layer($id, $post, $oldrow){
		$html_dir = WWW_DIR.'/layers/'.$id;
		$data_dir = DATA_DIR.'/layers/'.$id;

		$post['id'] = $id;
		$qgs_file = find_qgs(DATA_DIR.'/stores/'.$post['store_id']);
		
		//create .env
		$is_public = $post['public'] == 't' ? 'true' : 'false';
		$is_cached = $post['cached'] == 't' ? 'true' : 'false';
		$qgs_layers = "array('".str_replace(",", "','", $post['layers'])."')";
		$show_dt = $post['show_dt'] == 't'  ? 'true' : 'false';
		
		$vars = [ 
			'IS_PUBLIC' => $is_public, 'QGIS_FILENAME_ENCODED' => "'".urlencode($qgs_file)."'",
			'QGIS_LAYERS' => $qgs_layers, 'CACHE_ENABLED' => $is_cached,
			'SHOW_DATATABLES' => $show_dt, 'PRINT_LAYOUT' => "'".$post['print_layout']."'"];

		update_env($html_dir.'/env.php', $vars);
		
		$mproxy_layer_names = array();
		if($post['exposed'] == 't'){
			$lays = explode(',', $post['layers']);
			foreach($lays as $l){
				array_push($mproxy_layer_names, $post['name'].'.'.$l);
			}
		}else{
			array_push($mproxy_layer_names, $post['name']);
		}
		
		if($post['customized'] != 't'){	// if user hasn't updated index file
			$bbox = layers_get_bbox($qgs_file, $post['layers']);
			$wms_url = ($post['proxyfied'] == 't') ? '/mproxy/service' : 'proxy_qgis.php';			
			$wms_layers = ($post['proxyfied'] == 't') ? implode(',', $mproxy_layer_names) : $post['layers'];

			$vars = ['BOUNDING_BOX' => '[['.$bbox["miny"].','.$bbox["minx"].'],['.$bbox['maxy'].','.$bbox['maxx'].']]',
				'WMS_URL' => $wms_url, 'SECRET_KEY' => $_SESSION[SESS_USR_KEY]->secret_key, 'WMS_LAYERS' => $wms_layers
			];
			update_template('../snippets/wms_index.php', $html_dir.'/index.php', $vars);
			update_template('../snippets/ol_index.php', $html_dir.'/ol_index.php', $vars);
		}

		if($oldrow->exposed == 't'){
			$lays = explode(',', $oldrow->layers);
			foreach($lays as $l){
				mapproxy_Class::mapproxy_delete_source($oldrow->name.'.'.$l);
			}
		}else{
			mapproxy_Class::mapproxy_delete_source($oldrow->name);
		}
		
		if($post['proxyfied'] == 't'){
			if($post['exposed'] == 't'){
				$lays = explode(',', $post['layers']);
				foreach($lays as $l){
					mapproxy_Class::mapproxy_add_source($post['name'].'.'.$l, $qgs_file, $l);
				}
			}else{
				mapproxy_Class::mapproxy_add_source($post['name'], $qgs_file, $post['layers']);
			}
			
			mapproxy_Class::mapproxy_add_seed($mproxy_layer_names, $id);
		}else{
		  shell_exec('mapproxy_seed_ctl.sh disable '.$id);
		}
		shell_exec('sudo /usr/local/bin/mapproxy_ctl.sh restart');
		
		return true;
	}

	function delete_layer($id, $row){
		
		$html_dir  = WWW_DIR.'/layers/'.$id;
		$data_dir  = DATA_DIR.'/layers/'.$id;
		$cache_dir = CACHE_DIR.'/layers/'.$id;
		
		if($html_dir.'/proxy_qgis.php'){
			rrmdir($html_dir);
			if(is_dir($cache_dir)){
				rrmdir($cache_dir);
			}
		}

		if(is_file(WWW_DIR.'/assets/layers/'.$id.'.png')){
			unlink(WWW_DIR.'/assets/layers/'.$id.'.png');
		}
		
		if($row->proxyfied == 't'){
			if($row->exposed == 't'){
				$lays = explode(',', $row->layers);
				foreach($lays as $l){
					mapproxy_Class::mapproxy_delete_source($row->name.'.'.$l);
					mproxy_cache_clear($row->name.'.'.$l);
				}
			}else{
				mapproxy_Class::mapproxy_delete_source($row->name);
				mproxy_cache_clear($row->name);
			}
			shell_exec('sudo /usr/local/bin/mapproxy_ctl.sh restart');
			
			shell_exec('mapproxy_seed_ctl.sh disable '.$id);
		}

		rrmdir($data_dir);
	}

	function proxy_cache_clear($id){
		$dir_size = 0;

		$cache_dir = CACHE_DIR.'/layers/'.$id;
		if(is_dir($cache_dir)){
			$hexvals = array_merge( range(0,9), range('a','f'));

			foreach($hexvals as $v){
				$vdir = $cache_dir.'/'.$v;
				$files = scandir($vdir);
				foreach($files as $f){
					$vfile = $vdir.'/'.$f;
					if(is_file($vfile)){
						$dir_size += filesize($vfile);
						unlink($vfile);
					}
				}
			}
		}
		return $dir_size;
	}

	function mproxy_cache_clear_r($dir){
		$dir_size = 0;

		if (is_dir($dir)) {
 			$objects = scandir($dir);
			foreach ($objects as $object) {
 				if ($object != "." && $object != "..") {
					$path = $dir. DIRECTORY_SEPARATOR .$object;
 					if (is_dir($path) && !is_link($path)){
 						$dir_size += mproxy_cache_clear_r($path);
 					}else{
						$dir_size += filesize($path);
 						unlink($path);
					}
 				}
 			}
			rmdir($dir);
 	 	}
		return $dir_size;
	}

	function mproxy_cache_clear($name){
		$dir_size = 0;

		$cache_data = DATA_DIR.'/mapproxy/cache_data';
		$cache_dir_prefix = $name.'_cache_';
		$entries = scandir($cache_data);
		foreach($entries as $e){
			if(is_dir($cache_data.'/'.$e) && str_starts_with($e, $cache_dir_prefix)){
				$dir_size += mproxy_cache_clear_r($cache_data.'/'.$e);
			}
		}
		return $dir_size;
	}

  $result = ['success' => false, 'message' => 'Error while processing your request!'];

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
			
			$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
			$obj = new qgs_layer_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
			
			$id = empty($_POST['id']) ? -1 : intval($_POST['id']);
			$action = empty($_POST['action']) ? '' : $_POST['action'];
			
			if(($id > 0) && !$obj->isOwnedByUs($id)){
				$result = ['success' => false, 'message' => 'Action not allowed!'];
			
      }else if($action == 'save') {
          $newId = 0;

					if(empty($_POST['public'])){		$_POST['public'] = 'false';	}
					if(empty($_POST['cached'])){		$_POST['cached'] = 'false';	}
					if(empty($_POST['proxyfied'])){	$_POST['proxyfied'] = 'false';	}
					if(empty($_POST['customized'])){	$_POST['customized'] = 'false';	}
					if(empty($_POST['exposed']))	 {	$_POST['exposed'] = 'false';	}
					if(empty($_POST['show_dt']))	 {	$_POST['show_dt'] = 'false';	}

					$_POST['layers'] = implode(',', $_POST['layers']);

				  if($id >= 0) { // update

						$result = $obj->getById($id);
						$oldrow = pg_fetch_object($result);
						pg_free_result($result);

            $newId = $obj->update($_POST) ? $id : 0;
						update_layer($newId, $_POST, $oldrow);
          } else { // insert
            $newId = $obj->create($_POST);
						if($newId){

							if(!install_layer($newId, $_POST)){
								$obj->delete($newId);
								$newId = 0;
							}
						}
          }
					
					if($newId > 0){
						$result = ['success' => true, 'message' => 'Layer successfully created!', 'id' => $newId];
					}else{
						$result = ['success' => false, 'message' => 'Failed to save layer!'];
					}
      
			}else if($action == 'delete') {
				
				$result = $obj->getById($id);
				if($result){
					$row = pg_fetch_object($result);
					pg_free_result($result);
					
					if($obj->delete($id)){
	        	$result = ['success' => true, 'message' => 'Layer successfully deleted!'];
	
						delete_layer($id, $row);
					}					
				}else{
					$result = ['success' => true, 'message' => 'Failed to delete layer!'];
				}

			}else if($action == 'info') {
				$kv = array();
				
				$result = $obj->getById($id);
				if($result){
					$row = pg_fetch_object($result);
					pg_free_result($result);

					$proto = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
					$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/layers/'.$id;
					
					$qgs_file = find_qgs(DATA_DIR.'/stores/'.$row->store_id);
					
					$qgis_url = $base_url.'/proxy_qgis.php?';
					$wms_query = layer_get_wms_query($qgs_file, $row);
					$wfs_query = layer_get_wfs_query($qgs_file, $row);
					$bbox = layers_get_bbox($qgs_file, $row->layers);

					$result = ['success' => true, 'qgis_url' => $qgis_url, 'wms_query' => $wms_query, 'wfs_query' => $wfs_query,
						'bbox' => $bbox["miny"].','.$bbox["minx"].','.$bbox['maxy'].','.$bbox['maxx'],
						'capabilities_query' => $base_url.'/wms.php?REQUEST=GetCapabilities'
					];
					
					if($row->proxyfied == 't'){
						$result['mapproxy_url'] = $proto.'://'.$_SERVER['HTTP_HOST'].'/mproxy/service';
					}
				}else{
					$result = ['success' => false, 'message' => 'Error: No layer found'];
				}
			} else if($action == 'cache_clear'){
				
				$result = $obj->getById($id);
				if($result){
					$row = pg_fetch_object($result);
					pg_free_result($result);
					
					$dir_size = ($row->proxyfied == 't') ? mproxy_cache_clear($row->name) : proxy_cache_clear($id);
					if($dir_size == 0){
						$result = ['success' => false, 'message' => 'Error: No cache!'];
					}else{
						$result = ['success' => true, 'message' => 'Successfully removed '.human_size($dir_size)];
					}

				}else{
					$result = ['success' => false, 'message' => 'Error: No layer found'];
				}
			}
  }

  echo json_encode($result);
?>
