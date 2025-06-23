<?php
    session_start(['read_and_close' => true]);
		require('../incl/const.php');
    require('../class/database.php');
		require('../class/table.php');
    require('../class/access_group.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
			$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
    	$obj = new access_group_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
			$id = empty($_POST['id']) ? 0 : intval($_POST['id']);
			$action = empty($_POST['action']) ? 0 : $_POST['action'];
			
				if(($id > 0) && !$obj->isOwnedByUs($id)){
					$result = ['success' => false, 'message' => 'Action not allowed!'];
			
				}else if($action == 'save') {
            $newId = ($id) ? $obj->update($_POST) : $obj->create($_POST);
						if($newId > 0){
            	$result = ['success' => true, 'message' => 'Access group Successfully Saved!', 'id' => $newId];
						}else{
							$result = ['success' => false, 'message' => 'Failed to create/update access group!'];
						}
						
        } else if(($action == 'delete') && ($id != 1)) {
						
						$ref_ids = array();
						$tbls = array('user', 'store', 'layer');
						
						foreach($tbls as $k){
							$rows = $database->getAll('public.'.$k.'_access', 'access_group_id = '.$id);							
							foreach($rows as $row){
								$ref_ids[] = $row[$k.'_id'];
							}
							
							if(count($ref_ids) > 0){
								$ref_name = $k;
								break;
							}
						}						
						
						if(count($ref_ids) > 0){
							$result = ['success' => false, 'message' => 'Error: Can\'t delete access group because it is used by '.count($ref_ids).' '.$ref_name.'(s) with ID(s) ' . implode(',', $ref_ids) . '!' ];
						}else {
							
							foreach($tbls as $tbl){
								$obj->remove_access($tbl, $id);
							}
							
							if($obj->delete($id)){
								$result = ['success' => true, 'message' => 'Group Successfully Deleted!'];
							}else{
								$result = ['success' => false,'message' => 'Failed to delete group!'];
							}
						}
        }
    }

    echo json_encode($result);
?>
