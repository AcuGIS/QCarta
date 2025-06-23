<?php
	session_start();
	require('../incl/const.php');
	require('../class/database.php');
	require('../class/table.php');
	require('../class/user.php');
	
	$settings = json_decode(file_get_contents(DATA_DIR.'/settings.json'), true);
	
	if(isset($_SESSION[SESS_USR_KEY])) {
		header("Location: ../../".$settings['login_redirect']);
	}

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$user_obj = new user_Class($database->getConn(), 0);

	if(isset($_POST['submit'])&&!empty($_POST['submit'])){
			$row = $user_obj->loginCheck($_POST['pwd'], $_POST['email']);
			if($row){
				$_SESSION[SESS_USR_KEY] = $row;
				header("Location: ../../".$settings['login_redirect']);
			}else{			
				header("Location: ../../login.php?err=".urlencode('Error: Failed to login!'));
			}
	}

?>
