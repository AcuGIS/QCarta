<?php
	session_start(['read_and_close' => true]);
	require('../incl/const.php');
	require('../class/database.php');
	require('../class/table.php');
	require('../class/table_ext.php');

	require('../class/layer.php');
	require('../class/qgs_layer.php');

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		$dbconn = $database->getConn();	
		
		$id = (empty($_POST['id'])) ? -1 : intval($_POST['id']);
		$action = empty($_POST['action']) ? '' : $_POST['action'];
		$v = empty($_POST['v']) ? '' : $_POST['v'];

		$obj = new qgs_layer_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
		if(($id <= 0) && !$obj->isOwnedByUs($id)){
			http_response_code(405);	//not allowed
			die(405);
		}else if($action == 'Restore'){
			if(is_file(DATA_DIR.'/layers/'.$id.'/seed.yaml_'.$v)){
				copy(DATA_DIR.'/layers/'.$id.'/seed.yaml_'.$v, DATA_DIR.'/layers/'.$id.'/seed.yaml');
			}
			header('Location: ../services.php?tab=seed');
		}else if($action == 'Submit'){
			// make a snapshot of old value
			copy(DATA_DIR.'/layers/'.$id.'/seed.yaml', DATA_DIR.'/layers/'.$id.'/seed.yaml_'.date("Y-m-d-H-i-s"));
			
			file_put_contents(DATA_DIR.'/layers/'.$id.'/seed.yaml', $_POST['seed_yaml']);
			header('Location: ../services.php?tab=seed');
		} else if($action == 'Delete') {
			
			if(is_file(DATA_DIR.'/layers/'.$id.'/seed.yaml_'.$v)){
				header('Location: ../edit_seed.php?id='.$id);
				unlink(DATA_DIR.'/layers/'.$id.'/seed.yaml_'.$v);
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