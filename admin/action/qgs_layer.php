<?php
  session_start(['read_and_close' => true]);
	require('../incl/const.php');
	require('../incl/app.php');
  require('../class/database.php');
	require('../class/table.php');
	require('../class/table_ext.php');
	require('../class/layer.php');
  require('../class/qgs_layer.php');
	require('../incl/qgis.php');
	require('../class/property_filter.php');
		
	function layer_get_featurename($qgs_file, $layers){
		$xml_data = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.1&map='.urlencode($qgs_file).'&SERVICE=WMS&REQUEST=DescribeLayer&LAYERS='.urlencode($layers).'&SLD_VERSION=1.1.1');
		if(preg_match('/FeatureTypeName>(.*)</', $xml_data, $matches)){
			return $matches[1];
		}
		return null;
	}
	
	function layer_save_thumbnail($qgs_file, $post, $bbox){
		$url  = 'http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.1&map='.urlencode($qgs_file).'&SERVICE=WMS&request=GetMap';
		$url .= '&layers='.urlencode($post['layers']).'&bbox='.urlencode($bbox["minx"].','.$bbox["miny"].','.$bbox['maxx'].','.$bbox['maxy']).'&width=300&height=200&srs=EPSG%3A4326&FORMAT=image%2Fpng';
		$png_data = file_get_contents($url);
		
		file_put_contents(WWW_DIR.'/assets/layers/'.$post['id'].'.png', $png_data);
	}
	
	// example: service=WMS&version=1.1.1&request=GetMap&layers=tiger%3Agiant_polygon&bbox=-180.0%2C-90.0%2C180.0%2C90.0&width=768&height=384&srs=EPSG%3A4326&styles=&format=application/openlayers
	function layer_get_wms_query($qgs_file, $row){

		$bbox = layers_get_bbox($qgs_file, $row->layers);
		
		$xlen = abs($bbox['maxx'] - $bbox['minx']);
		$ylen = abs($bbox['maxy'] - $bbox['miny']);
		$wh_ratio = $xlen / $ylen;
		
		$height = 768;
		$width = intval($height * $wh_ratio);
		
		$query  = 'service=WMS&version=1.1.1&request=GetMap';
		$query .= '&layers='.urlencode($row->layers);
		$query .= '&bbox='.urlencode($bbox["minx"].','.$bbox["miny"].','.$bbox['maxx'].','.$bbox['maxy']);
		$query .= '&width='.$width.'&height='.$height.'&srs='.urlencode('EPSG:4326');
		return $query;
	}
	
	// example: service=WFS&version=1.0.0&request=GetFeature&typeName=tiger%3Agiant_polygon&maxFeatures=50
	function layer_get_wfs_query($qgs_file, $row){
		$query  = 'service=WFS&version=1.1.1&request=GetFeature';
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
		
		$bbox = layers_get_bbox($qgs_file, $post['layers']);
		if($bbox == null){
		    return false;
		}

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
		copy('../snippets/layer_wms.php', $html_dir.'/wms.php');
		
		$vars = ["DATA_FOLDER.'/'" => "DATA_DIR.'/stores/".$post['store_id']."/'"];
		update_template(WWW_DIR.'/admin/snippets/img_filep.php', $html_dir.'/img_filep.php', $vars);

		$vars = ["DATA_DIR.'/'.LAYER_ID.'" => "DATA_DIR.'/stores/".$post['store_id']];
		update_template(WWW_DIR.'/admin/snippets/data_filep.php', $html_dir.'/store_filep.php', $vars);

		if($post['proxyfied'] == 't') {
			require_once(__DIR__ . '/../../inc/mproxy.php');
			try {
				$wms_url = getMproxyBaseUrl($post['store_id']);
			} catch (Exception $e) {
				error_log("admin/action/qgs_layer.php: Failed to get mproxy base URL for store_id {$post['store_id']}: " . $e->getMessage());
				$wms_url = '/mproxy/service'; // Fallback
			}
		} else {
			$wms_url = 'proxy_qgis.php';
		}
		
		// Use layer names directly from database - no prefixing
		$wms_layers = $post['layers'];
		
		$vars = ['BBOX_MINY' => $bbox["miny"], 'BBOX_MINX' => $bbox["minx"], 'BBOX_MAXY' => $bbox['maxy'], 'BBOX_MAXX' => $bbox['maxx'],
			'WMS_URL' => $wms_url, 'SECRET_KEY' => $_SESSION[SESS_USR_KEY]->secret_key, 'WMS_LAYERS' => $wms_layers
		];
		update_template('../snippets/wms_index.php', $html_dir.'/layer.php', $vars);
		update_template('../snippets/map_index.php', $html_dir.'/index.php', $vars);
		update_template('../snippets/analysis.php', $html_dir.'/analysis.php', $vars);
		update_template('../snippets/analysis_table.php', $html_dir.'/table.php', $vars);
		
		// create cachee dir tree
		mkdir(CACHE_DIR.'/layers/'.$id);
		
		$hexvals = array_merge( range(0,9), range('a','f'));
		foreach($hexvals as $v){
			mkdir(CACHE_DIR.'/layers/'.$id.'/'.$v);
		}

		if( isset($_FILES["image"]) &&
            file_exists($_FILES['image']['tmp_name']) &&
            is_uploaded_file($_FILES['image']['tmp_name']) && 
            ($_FILES['image']['size'] < 10485760)){ // if image file and is less than 10 MB
            $image = null;
            // scale image to 200x150
            if($_FILES["image"]["type"] == 'image/png'){
            $image = imagecreatefrompng($_FILES["image"]["tmp_name"]);
            }else if($_FILES["image"]["type"] == 'image/jpeg'){
            $image = imagecreatefromjpeg($_FILES["image"]["tmp_name"]);
            }
            
            if($image){
                $width = 200;
                $scale_ratio = imagesx($image) / $width;
                $imgResized = imagescale($image , $width, imagesy($image) / $scale_ratio);
                imagepng($imgResized, "../../assets/layers/".$id.'.png');
            }
        }else if($post['auto_thumbnail'] == 't'){
            layer_save_thumbnail($qgs_file, $post, $bbox);
        }

		return true;
	}
	
	function update_layer($db, $id, $post, $oldrow){
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
		
		$bbox = layers_get_bbox($qgs_file, $post['layers']);
		if($bbox == null){
		    return false;
		}

		if($post['customized'] != 't'){	// if user hasn't updated index file
			if($post['proxyfied'] == 't') {
				require_once(__DIR__ . '/../../inc/mproxy.php');
				try {
					$wms_url = getMproxyBaseUrl($post['store_id']);
				} catch (Exception $e) {
					error_log("admin/action/qgs_layer.php: Failed to get mproxy base URL for store_id {$post['store_id']}: " . $e->getMessage());
					$wms_url = '/mproxy/service'; // Fallback
				}
			} else {
				$wms_url = 'proxy_qgis.php';
			}
			// Use layer names directly from database - no prefixing
			$wms_layers = $post['layers'];

			$vars = ['BBOX_MINY' => $bbox["miny"], 'BBOX_MINX' => $bbox["minx"], 'BBOX_MAXY' => $bbox['maxy'], 'BBOX_MAXX' => $bbox['maxx'],
				'WMS_URL' => $wms_url, 'SECRET_KEY' => $_SESSION[SESS_USR_KEY]->secret_key, 'WMS_LAYERS' => $wms_layers
			];
			update_template('../snippets/wms_index.php', $html_dir.'/layer.php', $vars);
			update_template('../snippets/map_index.php', $html_dir.'/index.php', $vars);
			update_template('../snippets/analysis.php', $html_dir.'/analysis.php', $vars);
			update_template('../snippets/analysis_table.php', $html_dir.'/table.php', $vars);
		}
		
		if( isset($_FILES["image"]) &&
            file_exists($_FILES['image']['tmp_name']) &&
            is_uploaded_file($_FILES['image']['tmp_name']) && 
            ($_FILES['image']['size'] < 10485760)){ // if image file and is less than 10 MB
            $image = null;
            // scale image to 200x150
            if($_FILES["image"]["type"] == 'image/png'){
            $image = imagecreatefrompng($_FILES["image"]["tmp_name"]);
            }else if($_FILES["image"]["type"] == 'image/jpeg'){
            $image = imagecreatefromjpeg($_FILES["image"]["tmp_name"]);
            }
            
            if($image){
                $width = 200;
                $scale_ratio = imagesx($image) / $width;
                $imgResized = imagescale($image , $width, imagesy($image) / $scale_ratio);
                imagepng($imgResized, "../../assets/layers/".$id.'.png');
            }
        }else if($post['auto_thumbnail'] == 't'){
            layer_save_thumbnail($qgs_file, $post, $bbox);
        }
		
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

		$cache_data = DATA_DIR.'/qcarta-tiles/cache_data';
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
          if(!isset($_POST['print_layout'])){
              $_POST['print_layout'] = '';
          }

					if(empty($_POST['public'])){		$_POST['public'] = 'false';	}
					if(empty($_POST['cached'])){		$_POST['cached'] = 'false';	}
					if(empty($_POST['proxyfied'])){	$_POST['proxyfied'] = 'false';	}
					if(empty($_POST['customized'])){	$_POST['customized'] = 'false';	}
					if(empty($_POST['exposed']))	 {	$_POST['exposed'] = 'false';	}
					if(empty($_POST['show_charts'])) {	$_POST['show_charts'] = 'false';}
					if(empty($_POST['show_dt']))	 {	$_POST['show_dt'] = 'false';	}
					if(empty($_POST['show_query']))	 {	$_POST['show_query'] = 'false';	}
					if(empty($_POST['show_fi_edit']))	 {	$_POST['show_fi_edit'] = 'false';	}
					if(empty($_POST['auto_thumbnail']))	 {	$_POST['auto_thumbnail'] = 'false';	}
					if(empty($_POST['basemap_id']))	 {	$_POST['basemap_id'] = '';	}

					$_POST['layers'] = isset($_POST['layers']) && is_array($_POST['layers']) ? implode(',', $_POST['layers']) : '';

				  if($id >= 0) { // update

						$result = $obj->getById($id);
						$oldrow = pg_fetch_object($result);
						pg_free_result($result);

            $newId = $obj->update($_POST) ? $id : 0;
						update_layer($database, $newId, $_POST, $oldrow);
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

         			$tbls = array('geostory_wms' => 'story_id', 'layer_report' => 'id', 'layer_query' => 'id', 'property_filter' => 'id', 'dashboard' => 'id');
       	            list($ref_ids,$ref_name) = $database->get_ref_ids($tbls, 'layer_id', $id);

         			if(count($ref_ids) > 0){
                        $result = ['success' => false, 'message' => 'Error: Can\'t delete layer because it is used in '.count($ref_ids).' '.$ref_name.'(s) with ID(s) ' . implode(',', $ref_ids) . '!' ];
         			}else if($obj->drop_categories($id) && $obj->delete($id)){
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
						'capabilities_query' => $base_url.'/wms?REQUEST=GetCapabilities'
					];
					
					if($row->proxyfied == 't'){
						require_once(__DIR__ . '/../../inc/mproxy.php');
						try {
							$base_url = getMproxyBaseUrl($row->store_id);
							$result['mapproxy_url'] = $proto.'://'.$_SERVER['HTTP_HOST'].$base_url;
						} catch (Exception $e) {
							error_log("admin/action/qgs_layer.php: Failed to get mproxy base URL for store_id {$row->store_id}: " . $e->getMessage());
							$result['mapproxy_url'] = $proto.'://'.$_SERVER['HTTP_HOST'].'/mproxy/service'; // Fallback
						}
					}
				}else{
					$result = ['success' => false, 'message' => 'Error: No layer found'];
				}
			} else if($action == 'cache_clear'){
				
				$result = $obj->getById($id);
				if($result){
					$row = pg_fetch_object($result);
					pg_free_result($result);
					
					// Clear PHP-level cache
					$dir_size = proxy_cache_clear($id);
					
					// Clear Go service cache via purge endpoint
					$purge_token = getenv('QCARTA_CACHE_PURGE_TOKEN');
					if($purge_token){
						$ch = curl_init('http://localhost:8011/admin/cache/purge');
						curl_setopt($ch, CURLOPT_POST, true);
						curl_setopt($ch, CURLOPT_POSTFIELDS, $row->name);
						curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$purge_token]);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						$response = curl_exec($ch);
						$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
						curl_close($ch);
						
						if($http_code == 200){
							$purge_result = json_decode($response, true);
							if(isset($purge_result['removed'])){
								$dir_size += $purge_result['removed'] * 50; // Estimate 50KB per tile
							}
						}
					}
					
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
