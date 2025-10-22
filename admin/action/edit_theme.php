<?php
	session_start(['read_and_close' => true]);
	require('../incl/const.php');

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
      const CSS_DIR = WWW_DIR.'/assets/dist/css';
      
		$action = empty($_POST['action']) ? '' : $_POST['action'];
		$v = empty($_POST['v']) ? '' : $_POST['v'];
		
		if($action == 'Restore'){
			if(is_file(CSS_DIR.'/map_index.css_'.$v)){
				copy(CSS_DIR.'/map_index.css_'.$v, CSS_DIR.'/map_index.css');
			}
			header('Location: ../syssettings.php');
		}else if($action == 'Submit'){
			// make a snapshot of old value
			copy(CSS_DIR.'/map_index.css', CSS_DIR.'/map_index.css_'.date("Y-m-d-H-i-s"));
			
			if (!isset($_POST['map_index_css'])) {
               	http_response_code(400);
               	die('Missing form field: map_index_css');
            }
            $css = (string)$_POST['map_index_css'];
            file_put_contents(CSS_DIR.'/map_index.css', $css, LOCK_EX);
            header('Location: ../syssettings.php');
		} else if($action == 'Delete') {
			
			if(is_file(CSS_DIR.'/map_index.css_'.$v)){
				header('Location: ../edit_theme.php');
				unlink(CSS_DIR.'/map_index.css_'.$v);
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
