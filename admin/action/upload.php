<?php
  session_start();
	require('../incl/const.php');
	require('../incl/app.php');
  
function escape_filename($name){
	$name = str_replace('..', '', $name);
	$name = basename($name);
	return $name;
}

  $result = ['success' => false, 'message' => 'Error while processing your request!'];

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {

			$action = empty($_POST['action']) ? '' : $_POST['action'];
			
			if($action == 'delete') {
				if(is_file(DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$_POST['source'])){
					# remove upload file
					unlink(DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$_POST['source']);
					$result = ['success' => true, 'message' => 'Upload successfully deleted!'];
				}else{
					$result = ['success' => true, 'message' => 'Failed to delete Upload!'];
				}
      
			}else if($action == 'upload') {
				$_POST['source'] = escape_filename($_POST['source']);

				$src = fopen($_FILES['chunk']['tmp_name'], 'r');

				$mode = (intval($_POST['start']) == 0) ? 'w' : 'a';
				$dst = fopen(DATA_DIR.'/upload/'.$_SESSION[SESS_USR_KEY]->id.'_'.$_POST['source'], $mode);

				if($src === false || $dst === false){
					$result = ['success' => false, 'message' => 'Chunk fopen error!'];
				}else{
					while (!feof($src)) {
						fwrite($dst, fread($src, 8192));
					}
					fclose($src);
					fclose($dst);
					
					$result = ['success' => true, 'message' => 'Chunk uploaded!'];
				}
			}else if($action == 'url') {
				
				// https://www.php.net/manual/en/context.http.php
				$http_context = stream_context_create(
						['http' => array('method' => 'HEAD')]
				);
				
				$hdrs = get_headers($_POST['url'], true, $http_context);
				$hdrs = array_change_key_case($hdrs, CASE_LOWER); 
				
				if($hdrs === false){
					$result = ['success' => false, 'message' => 'Error: Download failed!'];
				}else{
					$name = $_SESSION[SESS_USR_KEY]->id.'_'.basename($_POST['url']);
					$pid_file = DATA_DIR.'/upload/'.$name.'.pid';
					shell_exec('wget -o /dev/null -O '.DATA_DIR.'/upload/'.$name.' '.$_POST['url']. '& echo $! > '.$pidfile);
					
					$file_size = empty($hdrs['content-length']) ? 0 : $hdrs['content-length'];
					
					$is_downloading = is_pid_running($pidfile);
					if(!$is_downloading){
						unlink($pidfile);
					}
					$result = ['success' => true, 'size' => $file_size, 'name' => $name, 'is_downloading' => $is_downloading];
				}
			}else if($action == 'status'){
				$filepath = DATA_DIR.'/upload/'.$_POST['name'];
				if(is_file($filepath)){
					sleep(1);
					$result = ['success' => true, 'size' => filesize($filepath)];
				}else{
					$result = ['success' => false, 'message' => 'Error: Not found!'];
				}
			}
  }

  echo json_encode($result);
?>
