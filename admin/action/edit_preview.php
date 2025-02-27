<?php
	session_start(['read_and_close' => true]);
	require('../incl/const.php');
	
	require('../class/database.php');
	require('../class/table.php');
	require('../class/table_ext.php');
	require('../class/layer.php');
	require('../class/qgs_layer.php');

  $result = ['success' => false, 'message' => 'Error while processing your request!'];

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
			
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		$obj = new qgs_layer_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		
		$action = empty($_POST['action']) ? '' : $_POST['action'];
		$id = empty($_POST['id']) ? -1 : $_POST['id'];
		
		if($id <= 0){
			http_response_code(400);	// Bad Request
			die(400);
		}else if(($id > 0) && !$obj->isOwnedByUs($id)){
			http_response_code(405);	//not allowed
			die(405);
		}else if($action == 'save') {
		    $preview = ($_SESSION[SESS_USR_KEY]->preview_type == 'openlayers') ? 'ol_' : '';
			file_put_contents('../../layers/'.$id.'/'.$preview.'index.php', $_POST['preview_html']);
			header('Location: ../layers.php');
		}
  }
?>
