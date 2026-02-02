<?php
    session_start(['read_and_close' => true]);
	require('../incl/const.php');
    require('../class/database.php');
    require('../class/table.php');
    require('../class/topic.php');
    require('../incl/app.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	    $obj = new topic_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		
        $id     = empty($_POST['id']) ? 0 : intval($_POST['id']);
		$action = empty($_POST['action']) ? 0 : $_POST['action'];
					
        if($action == 'save') {
            $newId = 0;
            
            foreach(TOPIC_RESOURCES as $type){
                if(empty($_POST[$type.'_id'])){
                    $_POST[$type.'_id'] = [];
                }
            }
            
            if($id) { // update
              $newId = $obj->update($_POST) ? $id : 0;
            } else { // insert
              $newId = $obj->create($_POST);
            }

			if($newId > 0){
				$result = ['success' => true, 'message' => 'Topic successfully created!', 'id' => $newId];
			}else{
				$result = ['success' => false, 'message' => 'Failed to save topic!'];
			}
        }else if($action == 'delete') {
    		if($obj->delete($id)){
    			$result = ['success' => true, 'message' => 'Topic successfully deleted!'];
    		}else{
    			$result = ['success' => false, 'message' => 'Error: Topic not deleted!'];
    		}
        }
    }

    echo json_encode($result);
?>
