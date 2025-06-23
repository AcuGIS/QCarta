<?php
    session_start(['read_and_close' => true]);
	require('../incl/const.php');
    require('../class/database.php');
    require('../class/table.php');
    require('../class/doc.php');
    require('../incl/app.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	    $obj = new doc_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		
        $id     = empty($_POST['id']) ? 0 : intval($_POST['id']);
		$action = empty($_POST['action']) ? 0 : $_POST['action'];
					
        if($action == 'save') {
            $newId = 0;
            
            if(empty($_POST['public'])){		$_POST['public'] = 'false';	}
            
            if($id) { // update
              $newId = $obj->update($_POST) ? $id : 0;
            } else { // insert
              $newId = $obj->create($_POST);
            }
            
            if(isset($_POST['filename'])){
                $source = escape_filename($_POST['filename']);
                $upload_file = DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$source;
                if($upload_file){
                    $target_file = DATA_DIR.'/docs/'.$newId;
                    if(is_file($target_file)){
                        unlink($target_file);
                    }
                  rename($upload_file, $target_file);
                }
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
					imagepng($imgResized, "../../assets/docs/".$newId.'.png');
				}
			}

			if($newId > 0){
				$result = ['success' => true, 'message' => 'Doc successfully created!', 'id' => $newId];
			}else{
				$result = ['success' => false, 'message' => 'Failed to save doc!'];
			}
        }else if($action == 'delete') {
            
            $tbls = array('topic_doc' => 'topic_id', 'gemet_doc' => 'gemet_id');
            $ref_ids = $database->get_ref_ids($tbls, 'doc_id', $id);

 			if(count($ref_ids) > 0){
                $result = ['success' => false, 'message' => 'Error: Can\'t delete doc because it is used in '.count($ref_ids).' '.$ref_name.'(s) with ID(s) ' . implode(',', $ref_ids) . '!' ];
            }else if($obj->drop_access($id) && $obj->delete($id)){
                unlink(DATA_DIR.'/docs/'.$id);
                
                $thumbnail = '../../assets/docs/'.$id.'.png';
                if(is_file($thumbnail)){
                    unlink($thumbnail);
                }
    			$result = ['success' => true, 'message' => 'Doc successfully deleted!'];
    		}else{
    			$result = ['success' => false, 'message' => 'Error: Doc not deleted!'];
    		}
        }
    }

    echo json_encode($result);
?>
