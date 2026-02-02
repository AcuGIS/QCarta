<?php
    session_start();
	require('../incl/const.php');
    require('../class/database.php');
	require('../class/table.php');
    require('../class/layer_query.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		$obj = new layer_query_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
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
				$result = ['success' => true, 'message' => 'Query was successfully created!', 'id' => $newId];
			}else{
				$result = ['success' => false, 'message' => 'Failed to save query!'];
			}
		} else if($action == 'delete') {							
			if($obj->delete($id)){
   	            $result = ['success' => true, 'message' => 'Query successfully deleted!'];
			}else{
				$result = ['success' => false, 'message' => 'Failed to delete query!'];
			}
		}
    }
    echo json_encode($result);
?>
