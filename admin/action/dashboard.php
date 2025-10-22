<?php
    session_start(['read_and_close' => true]);
	require('../incl/const.php');
    require('../class/database.php');
    require('../class/table.php');
    require('../class/dashboard.php');
    require('../incl/app.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	    $obj = new dashboard_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		
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
            
            mkdir(DATA_DIR.'/dashboards/'.$newId);
            
            if(isset($_POST['filename'])){
                $source = escape_filename($_POST['filename']);
                $upload_file = DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$source;
                if($upload_file){
                    $target_file = DATA_DIR.'/dashboards/'.$newId.'/config.json';
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
					imagepng($imgResized, "../../assets/dashboards/".$newId.'.png');
				}
			}

			if($newId > 0){
				$result = ['success' => true, 'message' => 'Dashboard successfully created!', 'id' => $newId];
			}else{
				$result = ['success' => false, 'message' => 'Failed to save dashboard!'];
			}
        }else if($action == 'delete') {

            if($obj->drop_access($id) && $obj->delete($id)){
                rrmdir(DATA_DIR.'/dashboards/'.$id);
                
                $thumbnail = '../../assets/dashboards/'.$id.'.png';
                if(is_file($thumbnail)){
                    unlink($thumbnail);
                }
    			$result = ['success' => true, 'message' => 'Dashboard successfully deleted!'];
    		}else{
    			$result = ['success' => false, 'message' => 'Error: Dashboard not deleted!'];
    		}
        }else if($action == 'save_config') {

            $config_js = json_encode($_POST['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if(file_put_contents(DATA_DIR.'/dashboards/'.$id.'/config.json', $config_js) === false){
                $result = ['success' => false, 'message' => 'Dashboard config not saved!'];
            }else{
                $result = ['success' => true, 'message' => 'Dashboard config successfully saved!'];
            }
        }
    }

    echo json_encode($result);
?>
