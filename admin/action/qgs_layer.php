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
	
	function layer_get_capabilities($qgs_file){
		$xml_data = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.urlencode($qgs_file).'&SERVICE=WMS&REQUEST=GetCapabilities');
		$xml = simplexml_load_string($xml_data);
		return $xml;
	}
	
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
	
	function layer_get_bounding_box($xml, $layer_name){
	
		foreach($xml->Capability->Layer->Layer as $l){
			if($l->Name == $layer_name){
				foreach($l->BoundingBox as $bb){
					if($bb['SRS'] == 'EPSG:4326'){
						return $bb;
					}
				}
			}
		}
		return null;
	}
	
	// merge two bounding boxes to form one
	function merge_bbox($a, $b){
		if($a == null){
			return $b;
		}
		
		if($a['minx'] > $b['minx']){ // min left
			$a['minx'] = $b['minx'];
		}
		
		if($a['maxx'] < $b['maxx']){ // max right
			$a['maxx'] = $b['maxx'];
		}
		
		if($a['miny'] > $b['miny']){ // min bottom
			$a['miny'] = $b['miny'];
		}
		
		if($a['maxy'] < $b['maxy']){	// max top
			$a['maxy'] = $b['maxy'];
		}
		
		return $a;
	}
	
	function layers_get_bbox($qgs_file, $layers){
		$xml = layer_get_capabilities($qgs_file);
		
		$bbox = null;
		$layers = explode(',', $layers);
		foreach($layers as $l){
			$b = layer_get_bounding_box($xml, $l);
			$bbox = merge_bbox($bbox, $b);
		}
		return $bbox;
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
		
		$vars = [ 'LAYER_ID' => $post['id'],
			'IS_PUBLIC' => $is_public, 'QGIS_FILENAME_ENCODED' => "'".urlencode($qgs_file)."'",
			'QGIS_LAYERS' => $qgs_layers, 'CACHE_ENABLED' => $is_cached];

		copy('../snippets/layer_env.php', $html_dir.'/env.php');
		update_env($html_dir.'/env.php', $vars);

		copy('../snippets/proxy_qgis.php', $html_dir.'/proxy_qgis.php');
		
		$bbox = layers_get_bbox($qgs_file, $post['layers']);
		$wms_url = ($post['proxyfied'] == 't') ? '/mproxy/service' : 'proxy_qgis.php';
		$wms_layers = ($post['proxyfied'] == 't') ? $post['name'] : $post['layers'];
		
		$vars = ['BOUNDING_BOX' => '[['.$bbox["miny"].','.$bbox["minx"].'],['.$bbox['maxy'].','.$bbox['maxx'].']]',
			'WMS_URL' => $wms_url, 'SECRET_KEY' => $_SESSION[SESS_USR_KEY]->secret_key, 'WMS_LAYERS' => $wms_layers
		];
		update_template('../snippets/wms_index.php', $html_dir.'/index.php', $vars);
		
		// create cachee dir tree
		mkdir(CACHE_DIR.'/layers/'.$id);
		
		$hexvals = array_merge( range(0,9), range('a','f'));
		foreach($hexvals as $v){
			mkdir(CACHE_DIR.'/layers/'.$id.'/'.$v);
		}
		
		if($post['proxyfied'] == 't'){
			mapproxy_Class::mapproxy_add_source($post['name'], $qgs_file, $post['layers']);
		}
		
		layer_save_thumbnail($qgs_file, $post, $bbox);
		
		shell_exec('mapproxy_seed_ctl.sh enable '.$id);
		$seed_yaml = file_get_contents('../snippets/seed.yaml');
		$seed_yaml = str_replace('[osm_cache]', '['.$post['name'].'_cache]', $seed_yaml);
		file_put_contents(DATA_DIR.'/layers/'.$id.'/seed.yaml', $seed_yaml);

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
		
		$vars = [ 
			'IS_PUBLIC' => $is_public, 'QGIS_FILENAME_ENCODED' => "'".urlencode($qgs_file)."'",
			'QGIS_LAYERS' => $qgs_layers, 'CACHE_ENABLED' => $is_cached];

		update_env($html_dir.'/env.php', $vars);
		
		if($post['customized'] != 't'){	// if user hasn't updated index file
			$bbox = layers_get_bbox($qgs_file, $post['layers']);
			$wms_url = ($post['proxyfied'] == 't') ? '/mproxy/service' : 'proxy_qgis.php';
			$wms_layers = ($post['proxyfied'] == 't') ? $post['name'] : $post['layers'];

			$vars = ['BOUNDING_BOX' => '[['.$bbox["miny"].','.$bbox["minx"].'],['.$bbox['maxy'].','.$bbox['maxx'].']]',
				'WMS_URL' => $wms_url, 'SECRET_KEY' => $_SESSION[SESS_USR_KEY]->secret_key, 'WMS_LAYERS' => $wms_layers
			];
			update_template('../snippets/wms_index.php', $html_dir.'/index.php', $vars);
		}

		if($oldrow->proxyfied){
			mapproxy_Class::mapproxy_delete_source($oldrow->name);
		}

		if($post['proxyfied']){
			mapproxy_Class::mapproxy_add_source($post['name'], $qgs_file, $post['layers']);
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
		
		if(row->proxyfied){
    		if(mapproxy_Class::mapproxy_delete_source($row->name)){
    			shell_exec('sudo /usr/local/bin/mapproxy_ctl.sh restart');
    		}
            mproxy_cache_clear($row);
    		
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

	function mproxy_cache_clear($row){
		$dir_size = 0;

		$cache_data = DATA_DIR.'/mapproxy/cache_data';
		$cache_dir_prefix = $row->name.'_cache_';
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
					
					if($obj->drop_access($id) && $obj->delete($id)){
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
						'bbox' => $bbox["miny"].','.$bbox["minx"].','.$bbox['maxy'].','.$bbox['maxx']
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
					
					$dir_size = ($row->proxyfied == 't') ? mproxy_cache_clear($row) : proxy_cache_clear($id);
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
