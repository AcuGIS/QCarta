<?php
    session_start();
	require('../incl/const.php');
	require('../class/database.php');
	require('../incl/app.php');
	require('../class/table.php');
	require('../class/geostory.php');
	
	$result = ['success' => false, 'message' => 'Error while processing your request!'];
	
	if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
	    
       	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
       	$obj = new geostory_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : -1;
		$action = empty($_POST['action']) ? 0 : $_POST['action'];
				
        if(($id > 0) && !$obj->isOwnedByUs($id)){
			$result = ['success' => false, 'message' => 'Action not allowed!'];
		
        }else if($action == 'save') {
            
            if(empty($_POST['public'])){		$_POST['public'] = 'false';	}
            
            $template = WWW_DIR.'/admin/snippets/geostory_'.$_POST['export_type'].'.php';

            if (!isset($_SESSION['content'])) {
                $result = ['success' => false, 'message' => 'No content to export!'];
            }else if(!is_file($template)){
                $result = ['success' => false, 'message' => 'Invalid export type!'];
            }else{
                $layers = array_values($_SESSION['content']);
                $_POST['sections'] = $layers;
                
                if($id > 0){
                    $newId = $obj->update($_POST) ? $id : 0;
                }else{
                    $newId = $obj->create($_POST);
                }
    			
    			if($newId > 0){
                    // Convert content to a numerically indexed array for JS and HTML
                        
                        // For each WMS section, set url and name keys for JS compatibility
                        foreach ($layers as &$section) {
                            if (isset($section['type']) && $section['type'] === 'wms') {
                                $section['url'] = $section['wmsUrl'] ?? '';
                                $section['name'] = $section['layers'] ?? '';
                            }
                        }
                        unset($section);
                        $layersJson = json_encode($layers);
                        
                        // Build story content using numeric indices
                        $storyContent = '';
                        foreach ($layers as $index => $section) {
                            if ($section['type'] === 'html') {
                                $storyContent .= sprintf(
                                    '<div class="story-section content-section" data-place="Content%d">
                                        <div class="content-wrapper">
                                            <h3>%s</h3>
                                            <div class="content-body">%s</div>
                                        </div>
                                    </div>',
                                    $index,
                                    htmlspecialchars($section['title']),
                                    $section['content']
                                );
                            } else if ($section['type'] === 'wms') {
                                $storyContent .= sprintf(
                                    '<div class="story-section" data-place="Layer%d">
                                        <div class="map-section" id="map-section-%d"></div>
                                        <div class="layer-content">%s</div>
                                    </div>',
                                    $index,
                                    $index,
                                    $section['content'] ?? ''
                                );
                            } else if ($section['type'] === 'upload') {
                                $storyContent .= sprintf(
                                    '<div class="story-section" data-place="Upload%d">
                                        <div class="map-section" id="map-section-%d"></div>
                                        <div class="upload-content">%s</div>
                                    </div>',
                                    $index,
                                    $index,
                                    $section['content'] ?? ''
                                );
                                
                                if(!is_dir(DATA_DIR.'/geostories/'.$newId)){
                                    mkdir(DATA_DIR.'/geostories/'.$newId);
                                }

                                if(is_file($section['geojson'])){
                                    rename($section['geojson'], DATA_DIR.'/geostories/'.$newId.'/section'.$index.'.geojson');
                                }
                            } else if ($section['type'] === 'pg') {
                                $storyContent .= sprintf(
                                    '<div class="story-section" data-place="PG%d">
                                        <div class="map-section" id="map-section-%d"></div>
                                        <div class="pg-content">%s</div>
                                    </div>',
                                    $index,
                                    $index,
                                    $section['content'] ?? ''
                                );
                            }
                        }
                        
                        $html_dir = WWW_DIR.'/geostories/'.$newId;
                        
                        if(!is_dir($html_dir)){
                            mkdir($html_dir);
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
           					imagepng($imgResized, "../../assets/geostories/".$newId.'.png');
            				}
             			}

                        $is_public = $_POST['public'] == 't' ? 'true' : 'false';
                        
                        $vars = ['LAYER_ID' => $newId, 'IS_PUBLIC' => $is_public];
                        copy('../snippets/geostory_env.php', $html_dir.'/env.php');
                        update_env($html_dir.'/env.php', $vars);
                        
                        $vars = ["DATA_DIR.'/'.LAYER_ID.'/'" => "DATA_DIR.'/geostories/".$newId."/'"];
                        update_template(WWW_DIR.'/admin/snippets/data_filep.php', $html_dir.'/data_filep.php', $vars);
                        $story_index = $html_dir.'/index.php';
                        
                        copy($template, $story_index);
                        $vars = ['STORY_CONTENT' => $storyContent, 'LAYERS_JSON' => $layersJson, 'SECRET_KEY' => $_SESSION[SESS_USR_KEY]->secret_key];
                        update_template($template, $story_index, $vars);

                        unset($_SESSION['content']);
                        if(isset($_SESSION['story'])){
                            unset($_SESSION['story']);
                        }

                    $result = ['success' => true, 'message' => 'Story saved!'];
    			}else{
    			    $result = ['success' => false, 'message' => 'Story save failed!'];
    			}
            }
        }else if($action == 'delete') {
            
            $tbls = array('topic_geostory' => 'topic_id', 'gemet_geostory' => 'gemet_id');
            $ref_ids = $database->get_ref_ids($tbls, 'geostory_id', $id);

 			if(count($ref_ids) > 0){
                $result = ['success' => false, 'message' => 'Error: Can\'t delete geostory because it is used in '.count($ref_ids).' '.$ref_name.'(s) with ID(s) ' . implode(',', $ref_ids) . '!' ];
            }else if($obj->delete($id)){
                $story_index = WWW_DIR.'/geostories/'.$id.'/index.php';
                if(is_file($story_index)){
                    rrmdir(dirname($story_index));
                }
                
                if(is_dir(DATA_DIR.'/geostories/'.$id)){
                    rrmdir(DATA_DIR.'/geostories/'.$id);
                }
                
                $thumbnail = '../../assets/geostories/'.$id.'.png';
                if(is_file($thumbnail)){
                    unlink($thumbnail);
                }
                
                $result = ['success' => true, 'message' => 'Story removed!'];
            }else{
                $result = ['success' => false, 'message' => 'Story remove failed!'];
            }
        }
}
    echo json_encode($result);
?>
