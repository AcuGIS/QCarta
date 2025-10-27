<?php
    session_start();
		require('../incl/const.php');
    require('../class/database.php');
		require('../class/table.php');
    require('../class/user.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
				$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
				$obj = new user_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
				$id = isset($_POST['id']) ? intval($_POST['id']) : -1;
				$action = empty($_POST['action']) ? 0 : $_POST['action'];
				
				if(($id > 0) && !$obj->isOwnedByUs($id)){
					$result = ['success' => false, 'message' => 'Action not allowed!'];
				
        }else if($action == 'save') {
            $newId = 0;

					  if($id > 0) { // update
              $newId = $obj->update($_POST) ? $id : 0;
							
            } else { // insert
							
							if(	($_SESSION[SESS_USR_KEY]->id != SUPER_ADMIN_ID) &&
									($_POST['accesslevel'] == 'Admin')	){	// only Super Admin can create admins
								$result = ['success' => false, 'message' => 'Access level not allowed!'];
							}else{
								$email_user = explode('@', $_POST['email'])[0];
								$_POST['ftp_user'] = user_Class::uniqueName($email_user);
								$_POST['pg_password'] = user_Class::randomPassword();
								
	              $newId = $obj->create($_POST);
								$database->create_user($_POST['ftp_user'], $_POST['pg_password']);
								
								# make admins own their user
								if($_POST['accesslevel'] == 'Admin'){
									$obj->admin_self_own($newId);
								}
							}
            }

						if($newId > 0){
							$res = $obj->getById($newId);
							$row = pg_fetch_object($res);
							pg_free_result($res);
							$result = ['success' => true, 'message' => 'User successfully created!', 'id' => $newId,
							     'password' => $row->password, 'secret_key' => $row->secret_key];
						}else{
							$result = ['success' => false, 'message' => 'Failed to save user!'];
						}
        
				} else if($action == 'delete') {
				
					if($id == SUPER_ADMIN_ID){
						$result = ['success' => false, 'message' => 'Can\'t delete Super Admin'];
					
					}else if($id == $_SESSION[SESS_USR_KEY]->id){
							$result = ['success' => false, 'message' => 'Can\'t delete yourself!'];
					}else{
						
						$ref_ids = array();
						$tbls = array('access_group', 'store', 'layer', 'access_key');
						
						foreach($tbls as $k){
							$rows = $database->getAll('public.'.$k, 'owner_id = '.$id);							
							foreach($rows as $row){
								$ref_ids[] = $row['id'];
							}
							
							if(count($ref_ids) > 0){
								$ref_name = $k;
								break;
							}
						}						
						
						if(count($ref_ids) > 0){
							$result = ['success' => false, 'message' => 'Error: Can\'t delete user because it owns '.count($ref_ids).' '.$ref_name.'(s) with ID(s) ' . implode(',', $ref_ids) . '!' ];
						}else {
							$res = $obj->getById($id);
							$row = pg_fetch_assoc($res);
							pg_free_result($res);
							
							if($obj->drop_access($id) && $obj->delete($id)){
								
								$database->drop_user($row['ftp_user']);
								
	            	$result = ['success' => true, 'message' => 'User successfully deleted!'];
							}else{
								$result = ['success' => false, 'message' => 'Failed to delete user!'];
							}
						}
					}
				} else if($action == 'secret_reset') {
					if($obj->secret_reset($id)){
						$res = $obj->getById($id);
						if($res){
							$row = pg_fetch_object($res);
							pg_free_result($res);
							$result = ['success' => true, 'secret_key' => $row->secret_key];
						}else{
							$result = ['success' => false, 'message' => 'Failed to get secret user key!'];
						}
						
					}else{
						$result = ['success' => false, 'message' => 'Failed to reset secret user key!'];
					}
				}
    }

    echo json_encode($result);
?>
