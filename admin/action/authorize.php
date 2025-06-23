<?php
    session_start(['read_and_close' => true]);
		require('../incl/const.php');
    require('../class/database.php');
		require('../class/table.php');
		require('../class/user.php');
    require('../class/access_key.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(empty($_GET['secret_key'])){
			http_response_code(400);	// Bad Request
		}else{
			
			if(isset($_GET['ip']) && empty($_GET['ip'])){ // if ip is set, but empty
				$_GET['ip'] = $_SERVER['REMOTE_ADDR'];	//use remote address
			}
			
			$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
			$obj = new user_Class($database->getConn(), SUPER_ADMIN_ID);
    	
			$sec_row = $obj->secretCheck($_GET['secret_key']);
			if($sec_row == null){
				http_response_code(405);	//not allowed
			}else{
				// secret key is valid, generate access key
				$obj = new access_key_Class($database->getConn(), SUPER_ADMIN_ID);
				
				if(isset($_GET['ip'])){
					$row = $obj->getByIP($sec_row->id, $_GET['ip']);
				}else{
					$row = null;
				}
				
				if($row){	// if a key already exists
					$result = ['success' => true, 'access_key' => $row->access_key, 'valid_until' => $row->valid_until];
				}else{
					// re-create access_key obj to have secret key owner
					unset($obj);
					$obj = new access_key_Class($database->getConn(), $sec_row->owner_id);
					
					// make a new key valid for 15 minutes
					$time = new DateTime();
					$time->modify('+15 minutes');

					$data = ['ip_restricted' => isset($_GET['ip']), 'valid_until' => $time->format("Y-m-d H:i:s")];
					if(isset($_GET['ip'])){
						$data['allow_from'] = $_GET['ip'];
					}
					
					$newId = $obj->create($data);
					if($newId > 0){
						$res = $obj->getById($newId);
						$row = pg_fetch_object($res);
						pg_free_result($res);
						
						$result = ['success' => true, 'access_key' => $row->access_key, 'ip' => $_GET['ip'], 'valid_until' => $row->valid_until];
					}else{
						$result = ['success' => false, 'message' => 'Failed to create access'];
					}
				}
			}
    }

    echo json_encode($result);
?>
