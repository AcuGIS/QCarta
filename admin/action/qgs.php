<?php
    session_start(['read_and_close' => true]);
		require('../incl/const.php');
		require('../incl/app.php');
    require('../class/database.php');
		require('../class/table.php');
		require('../class/table_ext.php');
    require('../class/qgs.php');
		require('../class/pglink.php');

function escape_filename($name){
	$name = str_replace('..', '', $name);
	$name = basename($name);
	return $name;
}

function src2qgs($store_id, $qgs_file, $sources){
	# convert sources to .qgs
	$convert_url 	= 'http://localhost/cgi-bin/qgis_mapserv.fcgi?SERVICE=MAPCOMPOSITION';
	$convert_url .= '&PROJECT='.$qgs_file;
	$convert_url .= "&FILES=".implode(';', array_values($sources));
	$convert_url .= '&NAMES='.implode(';', array_keys($sources));
	$convert_url .= '&OVERWRITE=true&MAP='.DATA_DIR.'/stores/usdemo.qgs';
	
	$composition = file_get_contents($convert_url);
	return $qgs_file;
}

function dir2qgs($store_id, $dirname){
	$src_ext = array('geojson', 'shp', 'gpkg', 'tif', 'tiff', 'geotiff', 'geotif');
	
	$entries = scandir($dirname);
	foreach($entries as $e){
		$filepath = $dirname.'/'.$e;
		if(is_file($filepath)){
			$ext = pathinfo($filepath, PATHINFO_EXTENSION);
			if(in_array($ext, $src_ext)){
				$l = pathinfo($filepath, PATHINFO_FILENAME);
				$sources[$l] = $filepath;
			}
		}
	}
	
	$qgs_file = DATA_DIR.'/stores/'.$store_id.'/myproject'.$store_id.'.qgs';
	$qgs_file = src2qgs($store_id, $qgs_file, $sources);

	return $qgs_file;
}

// Convert PostGIS geojson to .qgs file using OTF-Project
function pg2qgis($store_id, $post, $conn){
	$sources = array();
	
	$obj = new pglink_Class($conn, $_SESSION[SESS_USR_KEY]->id);
	
	foreach($post['pg_store_id'] as $id){		
		# get PostGIS row
		$pg_res = $obj->getById($id);
		$pgr = pg_fetch_object($pg_res);
		pg_free_result($pg_res);
		
		$store_db = new Database($pgr->host, $pgr->dbname, $pgr->username, $pgr->password, $pgr->port, $pgr->schema);
		$geom_tbls = $store_db->getGEOMTables($pgr->dbname, $pgr->schema);
								
		# build source files with geojson
		foreach($geom_tbls as $tbl => $geom){
			$sources[$tbl] = DATA_DIR.'/stores/'.$store_id.'/'.$id.'_'.$tbl.'.geojson';			
			$fp = fopen($sources[$tbl], 'w');
			$store_db->getGeoJSON($pgr->schema, $tbl, $geom, '', $fp);
			fclose($fp);
		}
	}
	$qgs_file = DATA_DIR.'/stores/'.$store_id.'/myproject'.$store_id.'.qgs';
	$sources['myproject'.$store_id.'.qgs'] = src2qgs($store_id, $qgs_file, $sources);

	return array_values($sources);
}

function qgis_remove_shortname($qgis_file){
    $qgs_content = file_get_contents($qgis_file);
	$qgs_content = preg_replace('/<shortname>[^<]*<\/shortname>/', '', $qgs_content);
	file_put_contents($qgis_file, $qgs_content);
}

function install_store($id, $post, $conn){
	$data_dir = DATA_DIR.'/stores/'.$id;
	$html_dir = WWW_DIR.'/stores/'.$id;
	
	if(!mkdir($data_dir) || !mkdir($html_dir)){
		return false;
	}
	
	
	if(isset($post['pg_store_id'])){
		$post['source'] = pg2qgis($id, $post, $conn);
	}
	
	foreach($post['source'] as $source){
		if($source[0] == '/'){
			$upload_file = $source;
			$source = basename($source);
		}else if(isset($_POST['src_url'])){
			$source = escape_filename($source);
			$upload_file = DATA_DIR.'/upload/'.$source;
		}else{
			$source = escape_filename($source);
			$upload_file = DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$source;
		}

		if(str_ends_with($upload_file, '.zip')){
			$zip = new ZipArchive;
			$res = $zip->open($upload_file);
			if ($res === TRUE) {
				$zip->extractTo($data_dir);
				$zip->close();
				
				unlink($upload_file);
			} else {
				return false;
			}
		}else{
			rename($upload_file, $data_dir.'/'.$source);
		}
	}
	
	$qgis_file = find_qgs($data_dir);
	if($qgis_file == false){	// if no qgs file found
		$qgis_file = dir2qgs($id, $data_dir);	// try to find source for conversion ( .geojson -> .qgs)
	}
	
	qgis_remove_shortname($qgis_file);
	
	$is_public = $post['public'] == 't' ? 'true' : 'false';
	
	$vars = [ 'LAYER_ID' => $id, 'IS_PUBLIC' => $is_public, 'QGIS_FILENAME_ENCODED' => "'".urlencode($qgis_file)."'",];
	copy('../snippets/store_env.php', $html_dir.'/env.php');
	update_env($html_dir.'/env.php', $vars);
	
	update_template('../snippets/qgs_svc.php', $html_dir.'/wms.php', ['SERVICE_NAME' => 'WMS']);
	update_template('../snippets/qgs_svc.php', $html_dir.'/wfs.php', ['SERVICE_NAME' => 'WFS']);
	update_template('../snippets/qgs_svc.php', $html_dir.'/wmts.php', ['SERVICE_NAME' => 'WMTS']);
	
	return true;
}

function update_store($id, $post){
	$data_dir = DATA_DIR.'/stores/'.$id;
	$html_dir = WWW_DIR.'/stores/'.$id;
	
	$qgis_file = find_qgs($data_dir);
	
	qgis_remove_shortname($qgis_file);
	
	$is_public = $post['public'] == 't' ? 'true' : 'false';
	
	$vars = [ 'IS_PUBLIC' => $is_public, 'QGIS_FILENAME' => "'".$qgis_file."'"];
	update_env($html_dir.'/env.php', $vars);
	return true;
}

function qgs_ordered_layers($xml){
	$layers = $xml->xpath('/qgis/layer-tree-group//layer-tree-layer');
	$layer_by_id = array();
	foreach($layers as $l){
		$layer_by_id[(string)$l->attributes()->id] = (string)$l->attributes()->name;
	}

	$layer_names = array();
	$layers = $xml->xpath('/qgis/layerorder//layer');
	foreach($layers as $l){
		array_push($layer_names, $layer_by_id[(string)$l->attributes()->id]);
	}
	return $layer_names;
}

function parseQGISLayouts($xml){
    $rv = array();
   list($layouts) = $xml->xpath('/qgis/Layouts//Layout/@name');
   foreach($layouts as $l){
       array_push($rv, (String)$l);
   }
   return $rv;
}
	
    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
				
				$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
				$obj = new qgs_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
				
				$id = empty($_POST['id']) ? -1 : intval($_POST['id']);
				$action = empty($_POST['action']) ? '' : $_POST['action'];
				
				if(($id > 0) && !$obj->isOwnedByUs($id)){
					$result = ['success' => false, 'message' => 'Action not allowed!'];
				
        }else if($action == 'save') {
            $newId = 0;
							
						if(empty($_POST['public'])){
							$_POST['public'] = 'false';
						}
						
						  if($id >= 0) { // update
	              $newId = $obj->update($_POST) ? $id : 0;
								update_store($newId, $_POST);
	            } else { // insert
	              $newId = $obj->create($_POST);
								if($newId){									
									if(!install_store($newId, $_POST, $database->getConn())){
										$obj->delete($newId);
										$newId = 0;
									}
								}
	            }
							
							if($newId > 0){
								$result = ['success' => true, 'message' => 'Store successfully created!', 'id' => $newId];
							}else if(isset($_POST['source'])){
								// clear upload files on failure
								foreach($_POST['source'] as $source){
									$upload_file = DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$source;
									if(is_file($upload_file)){
										unlink($upload_file);
									}
								}
								$result = ['success' => false, 'message' => 'Failed to save store!'];
							}
						
        
				}else if($action == 'delete') {
					$ref_ids = array();
					$tbls = array('layer');
					
					foreach($tbls as $k){
						$rows = $database->getAll('public.'.$k, 'store_id = '.$id);							
						foreach($rows as $row){
							$ref_ids[] = $row['store_id'];
						}
						
						if(count($ref_ids) > 0){
							$ref_name = $k;
							break;
						}
					}						
					
					if(count($ref_ids) > 0){
						$result = ['success' => false, 'message' => 'Error: Can\'t delete store because it is used in '.count($ref_ids).' '.$ref_name.'(s) with ID(s) ' . implode(',', $ref_ids) . '!' ];
					}else if($obj->drop_access($id) && $obj->delete($id)){
          	$result = ['success' => true, 'message' => 'Store successfully deleted!'];
						
						# remove data files
						$data_dir = DATA_DIR.'/stores/'.$id;
						$html_dir = WWW_DIR.'/stores/'.$id;
						
						rrmdir($data_dir);
						rrmdir($html_dir);
					}else{
						$result = ['success' => true, 'message' => 'Failed to delete store!'];
					}

				}else if($action == 'info') {
					$qgis_file = find_qgs(DATA_DIR.'/stores/'.$id);
					if($qgis_file !== false){
						
						$xml = simplexml_load_file($qgis_file);
						list($DefaultViewExtent) = $xml->xpath('/qgis/ProjectViewSettings/DefaultViewExtent');
						
						$bounding_box = $DefaultViewExtent['xmin'].',</br>'.$DefaultViewExtent['ymin'].',</br>'.$DefaultViewExtent['xmax'].',</br>'.$DefaultViewExtent['ymax'];
						list($projection) = $xml->xpath('/qgis/ProjectViewSettings/DefaultViewExtent/spatialrefsys/authid');
						list($layouts) = $xml->xpath('/qgis/Layouts//Layout/@name');
						
						$layer_names = qgs_ordered_layers($xml);
						
						$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
						$base_url = $proto.'://'.$_SERVER['HTTP_HOST'].'/stores/'.$id;
						$html_dir = WWW_DIR.'/layers/'.$id;
						$kv = ['Projection' => (string) $projection, 'BoundingBox' => $bounding_box, 'Layouts' => (string)$layouts, 'Layers' => implode(' , ', $layer_names),
							'WMS'  => "<a href=".$base_url.'/wms.php?REQUEST=GetCapabilities'." target='_blank'>".$base_url.'/wms.php?REQUEST=GetCapabilities'."</a>",
							'WFS' => "<a href=".$base_url.'/wfs.php?REQUEST=GetCapabilities'." target='_blank'>".$base_url.'/wfs.php?REQUEST=GetCapabilities'."</a>",
							'WMTS' => "<a href=".$base_url.'/wmts.php?REQUEST=GetCapabilities'." target='_blank'>".$base_url.'/wmts.php?REQUEST=GetCapabilities'."</a>",
							'OpenLayers' => "<a href=".$base_url.'/wms.php?REQUEST=GetProjectSettings'." target='_blank'>".$base_url.'/wms.php?REQUEST=GetProjectSettings'."</a>"
						];
						$result = ['success' => true, 'message' => $kv];
					}else{
						$result = ['success' => false, 'message' => 'Error: No qgs file found!'];
					}
				}else if($action == 'layers'){
					$qgis_file = find_qgs(DATA_DIR.'/stores/'.$id);
					if($qgis_file !== false){
						$xml = simplexml_load_file($qgis_file);
						$result = ['success' => true, 'layers' => qgs_ordered_layers($xml), 'print_layouts' => parseQGISLayouts($xml)];
					}else{
						$result = ['success' => false, 'message' => 'Error: No qgs file found!'];
					}
				}else if($action == 'update_file'){
				    $upload_dir = DATA_DIR.'/stores/'.$id;

					if(!is_dir($upload_dir)){
					   $result = ['success' => false, 'message' => 'Error: No such store!'];
					}else{
					    // convert windows path delimiters    
					    $_POST['relative_path'] = str_replace('\\', '/', $_POST['relative_path']);

					    $source = basename($_POST['relative_path']);
                        $uploaded_file = DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$source;
                        $store_file = DATA_DIR.'/stores/'.$id.'/'.$_POST['relative_path'];
                        if(is_file($store_file)){
                            unlink($store_file);
                        }else if(str_contains($store_file, '/')){
                            $file_dir = dirname($store_file);
                            mkdir($file_dir, 0770, true);
                        }
                        
                        rename($uploaded_file, $store_file);
                        touch($store_file, $_POST['mtime']);
                        $result = ['success' => true, 'message' => 'File updated!'];
					}
				}
    }

    echo json_encode($result);
?>
