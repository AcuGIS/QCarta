<?php
    session_start(['read_and_close' => true]);
		require('../incl/const.php');
    require('../class/database.php');
		require('../class/table.php');
    require('../class/access_key.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
			$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
    	$obj = new access_key_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
			
			$id = empty($_POST['id']) ? 0 : intval($_POST['id']);
			$action = empty($_POST['action']) ? 0 : $_POST['action'];
			
				if(($id > 0) && !$obj->isOwnedByUs($id)){
					$result = ['success' => false, 'message' => 'Action not allowed!'];
			
				}else if($action == 'save') {
						
						$_POST['ip_restricted'] = empty($_POST['allow_from']) ? 'false' : 'true';
						$_POST['valid_until'] = str_replace('T', ' ', $_POST['valid_until']);
						
            $newId = ($id) ? $obj->update($_POST) : $obj->create($_POST);
						if($newId > 0){
            	$result = ['success' => true, 'message' => 'Access key Successfully Saved!', 'id' => $newId];
						}else{
							$result = ['success' => false, 'message' => 'Failed to create/update access key!'];
						}
						
        } else if(($action == 'delete')) {

						if($obj->delete($id)){
							$result = ['success' => true, 'message' => 'Key Successfully Deleted!'];
						}else{
							$result = ['success' => false,'message' => 'Failed to delete key!'];
						}						
        } else if(($action == 'clear-expired')) {

						if($obj->clear_expired()){
							$result = ['success' => true, 'message' => 'Expired Keys Deleted!'];
						}else{
							$result = ['success' => false,'message' => 'No expired keys!'];
						}						
        }
    }

    echo json_encode($result);
?>
