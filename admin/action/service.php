<?php
	session_start(['read_and_close' => true]);
	require('../incl/const.php');
	require('../class/backend.php');
	
	$result = ['success' => false, 'message' => 'Error while processing your request!'];

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		
		if(empty($_POST['name']) || empty($_POST['action'])){
			$result = ['success' => true, 'message' => 'Missing arguments!'];	
		}else{
			$name = empty($_POST['name']) ? '' : $_POST['name'];
			$action = empty($_POST['action']) ? '' : $_POST['action'];
			$bknd = new backend_Class();
			
			switch($action){
				case 'start':
				case 'stop':
				case 'restart':
				case 'enable':
				case 'disable':
					$bknd->systemd_ctl($name, $action);
					$result = ['success' => true, 'message' => 'Success!'];
					break;
				default:
					$result = ['success' => false, 'message' => 'Invalid command!'];
					break;
			}
		}
	}
	
	echo json_encode($result);
?>