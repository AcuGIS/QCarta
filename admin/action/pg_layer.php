<?php
  session_start(['read_and_close' => true]);
	require('../incl/const.php');
	require('../incl/app.php');
  require('../class/database.php');
	require('../class/table.php');
	require('../class/table_ext.php');
	require('../class/layer.php');
  require('../class/pg_layer.php');

	function install_layer($id, $post){

		$html_dir = WWW_DIR.'/layers/'.$id;
		$data_dir = DATA_DIR.'/layers/'.$id;
		
		mkdir($html_dir);
		mkdir($data_dir);
		
		if(!is_dir($html_dir) || !is_dir($data_dir)){
			return false;
		}

		$post['id'] = $id;
		
		//create .env
		$is_public = $post['public'] == 't' ? 'true' : 'false';
		$vars = [ 'LAYER_ID' => $post['id'], 'IS_PUBLIC' => $is_public	];
		copy('../snippets/layer_env.php', $html_dir.'/env.php');
		update_env($html_dir.'/env.php', $vars);

		// create geojson url
		copy(WWW_DIR.'/admin/snippets/geojson.php', $html_dir.'/geojson.php');

		//create index.php
		copy(WWW_DIR.'/admin/snippets/pg_index.php', $html_dir.'/index.php');

		return true;
	}
	
	function update_layer($id, $post){
		$html_dir = WWW_DIR.'/layers/'.$id;
		$data_dir = DATA_DIR.'/layers/'.$id;

		$post['id'] = $id;
		
		//update .env
		$is_public = $post['public'] == 't' ? 'true' : 'false';
		$vars = [ 'IS_PUBLIC' => $is_public	];

		update_env($html_dir.'/env.php', $vars);
		return true;
	}
	
	function delete_layer($id){
		
		$html_dir = WWW_DIR.'/layers/'.$id;
		$data_dir = DATA_DIR.'/layers/'.$id;
		
		if($html_dir.'/data.php'){
			rrmdir($html_dir);
		}

		rrmdir($data_dir);
	}

  $result = ['success' => false, 'message' => 'Error while processing your request!'];

  if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
			
			$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
			$obj = new pg_layer_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
			
			$id = empty($_POST['id']) ? -1 : intval($_POST['id']);
			$action = empty($_POST['action']) ? '' : $_POST['action'];
			
			if(($id > 0) && !$obj->isOwnedByUs($id)){
				$result = ['success' => false, 'message' => 'Action not allowed!'];
			
      }else if($action == 'save') {
          $newId = 0;

					if(empty($_POST['public'])){
						$_POST['public'] = 'false';
					}

				  if($id >= 0) { // update
            $newId = $obj->update($_POST) ? $id : 0;
						update_layer($newId, $_POST);
          } else { // insert
            $newId = $obj->create($_POST);
						if($newId){

							if(!install_layer($newId, $_POST)){
								$obj->delete($newId);
								$newId = 0;
							}
						}
          }
					
					if($newId > 0){
						$result = ['success' => true, 'message' => 'Layer successfully created!', 'id' => $newId];
					}else{
						$result = ['success' => false, 'message' => 'Failed to save layer!'];
					}
      
			}else if($action == 'delete') {
				if($obj->delete($id)){
        	$result = ['success' => true, 'message' => 'Layer successfully deleted!'];

					delete_layer($id);
					
				}else{
					$result = ['success' => true, 'message' => 'Failed to delete layer!'];
				}

			}else if($action == 'info') {
				$result = $obj->getById($id);
				if($result){
					$row = pg_fetch_object($result);
					pg_free_result($result);
					
					$kv = array();
					
					$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
					$kv['GeoJSON URL'] = $proto.'://'.$_SERVER['HTTP_HOST'].'/layers/'.$row->id.'/geojson.php';
					
					$result = ['success' => true, 'message' => $kv];
				}else{
					$result = ['success' => false, 'message' => $kv];
				}
			}
  }

  echo json_encode($result);
?>
