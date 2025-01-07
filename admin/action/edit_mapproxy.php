<?php
	session_start(['read_and_close' => true]);
	require('../incl/const.php');

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
					
		$action = empty($_POST['action']) ? '' : $_POST['action'];
		$v = empty($_POST['v']) ? '' : $_POST['v'];
		
		if($action == 'Restore'){
			if(is_file(DATA_DIR.'/mapproxy/mapproxy.yaml_'.$v)){
				copy(DATA_DIR.'/mapproxy/mapproxy.yaml_'.$v, DATA_DIR.'/mapproxy/mapproxy.yaml');
			}
			header('Location: ../services.php');
		}else if($action == 'Submit'){
			// make a snapshot of old value
			copy(DATA_DIR.'/mapproxy/mapproxy.yaml', DATA_DIR.'/mapproxy/mapproxy.yaml_'.date("Y-m-d-H-i-s"));
			
			file_put_contents(DATA_DIR.'/mapproxy/mapproxy.yaml', $_POST['mapproxy_yaml']);
			header('Location: ../services.php');
		} else if($action == 'Delete') {
			
			if(is_file(DATA_DIR.'/mapproxy/mapproxy.yaml_'.$v)){
				header('Location: ../edit_mapproxy.php');
				unlink(DATA_DIR.'/mapproxy/mapproxy.yaml_'.$v);
			}else{
				http_response_code(400);	// Bad Request
				die(400);
			}
		}else{
			http_response_code(400);	// Bad Request
			die(400);
		}
  }else{
		http_response_code(405);	//not allowed
		die(405);
	}
?>