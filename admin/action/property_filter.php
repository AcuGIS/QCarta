<?php
    session_start();
	require('../incl/const.php');
    require('../class/database.php');
    require('../class/table.php');
	require('../class/table_ext.php');
	require('../class/layer.php');
    require('../class/qgs_layer.php');
    require('../class/property_filter.php');
    require('../class/mapproxy.php');

    function store_filter_values($row, $fv_path){
        require('../../layers/'.$row['layer_id'].'/env.php');

        $feat_url = 'http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.QGIS_FILENAME_ENCODED.'&service=WFS&version=1.1.0&request=GetFeature&typeName='.$row['feature'].'&OUTPUTFORMAT=application/json';

        shell_exec('curl "'.$feat_url.'" | jq -c \'[.features[].properties["'.$row['property'].'"]]\' | jq "unique" > '.$fv_path);
    }
    
    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		$obj = new property_filter_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		
		$l_obj = new qgs_layer_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		
		$id = isset($_POST['id']) ? intval($_POST['id']) : -1;
		$action = empty($_POST['action']) ? 0 : $_POST['action'];		
		
		if(($id > 0) && !$obj->isOwnedByUs($id)){
			$result = ['success' => false, 'message' => 'Action not allowed!'];
        }else if($action == 'save') {

            $newId = 0;
            if($id > 0) { // update
                $newId = $obj->update($_POST) ? $id : 0;
            } else { // insert								
	            $newId = $obj->create($_POST);
            }

			if($newId > 0){
                $res = $l_obj->getById($_POST['layer_id']);
    			if($res){
              		$row = pg_fetch_object($res);
              		pg_free_result($res);
                    
                    $_POST['id'] = $newId;
    				$fv_path = DATA_DIR.'/stores/'.$row->store_id.'/fv_'.$_POST['feature'].'_'.$_POST['property'].'.json';
    				
    			    store_filter_values($_POST, $fv_path);
                    
                    if($row->proxyfied == 't'){
                        mapproxy_Class::mapproxy_disable_storage($row->name, true);
                    }
                    
    				$result = ['success' => true, 'message' => 'Filter was successfully created!', 'id' => $newId];
    			}else{
    			    $result = ['success' => true, 'message' => 'Filter was successfully created!', 'id' => $newId];
    			}
			}else{
				$result = ['success' => false, 'message' => 'Failed to save filter!'];
			}
		} else if($action == 'delete') {
		    
		    $res = $obj->getById($id);
    		if($res){
            	$row = pg_fetch_object($res);
            	pg_free_result($res);
            
    			if($obj->delete($id)){
			    //if this was last filter using this feature/property
			    $rows = $database->select('SELECT id FROM public.property_filter WHERE feature=\''.$row->feature.'\' AND property=\''.$row->property.'\'');
     			    if(count($rows) == 0){
                        $res = $l_obj->getById($row->layer_id);
                        if($res){
                           	$l_row = pg_fetch_object($res);
                           	pg_free_result($res);
                                
                            $fv_path = DATA_DIR.'/stores/'.$l_row->store_id.'/fv_'.$row->feature.'_'.$row->property.'.json';
                            if(is_file($fv_path)){
                               	unlink($fv_path);
                            }
                            if($l_row->proxyfied == 't'){
                                mapproxy_Class::mapproxy_disable_storage($l_row->name, false);
                            }
             			}
                    }
               	    $result = ['success' => true, 'message' => 'Filter successfully deleted!'];
    			}else{
    				$result = ['success' => false, 'message' => 'Failed to delete filter!'];
    			}
		    }else{
				$result = ['success' => false, 'message' => 'Failed to find filter!'];
			}
		}
    }
    echo json_encode($result);
?>
