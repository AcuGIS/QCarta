<?php
session_start();
require('admin/incl/const.php');
require('admin/class/database.php');
require('admin/class/table.php');
require('admin/class/doc.php');

$id = empty($_GET['id']) ? 0 : intval($_GET['id']);
$fpath = DATA_DIR.'/docs/'.$id;

if(is_file($fpath)){
    
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$obj = new doc_Class($database->getConn(), SUPER_ADMIN_ID);

    $result = $obj->getById($id);	
    if(!$result || (pg_num_rows($result) == 0)){
   	    header("HTTP/1.1 404 Not Found");
		exit(0);
    }
    
    $row = pg_fetch_object($result);
	pg_free_result($result);

	// authentication checks
    $tbl = 'doc';
	$allow = false;
	if($row->public == 't'){
	    $allow = true;
	}else if(!empty($_GET['access_key'])){
		$allow = $database->check_key_tbl_access($tbl, $_GET['access_key'], $_SERVER['REMOTE_ADDR'], $id);
	}else if(isset($_SESSION[SESS_USR_KEY])) { 	// local access with login
		$allow = $database->check_user_tbl_access($tbl, $id, $_SESSION[SESS_USR_KEY]->id);
	}
	
	if(!$allow){
	    header("HTTP/1.1 403 Forbidden");
		exit(0);
	}
	
	header('Content-Type: '.mime_content_type($fpath));
	header('Content-disposition: attachment; filename="'.$row->filename.'"');
	readfile($fpath);
}else{
	header("HTTP/1.1 400 Bad Request");
}
?>
