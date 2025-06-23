<?php
    session_start(['read_and_close' => true]);
	require('../incl/const.php');

    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID) {

		$action = empty($_POST['action']) ? 0 : $_POST['action'];
					
        if($action == 'save') {
            $settings = json_decode(file_get_contents(DATA_DIR.'/settings.json'), true);
            
            foreach($settings as $k => $v){
                if(isset($_POST[$k])){
                    $settings[$k] = $_POST[$k];
                }
            }
            
            $success = file_put_contents(DATA_DIR.'/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
        
    		if($success === false){
    			$result = ['success' => false, 'message' => 'Error: Settings were not saved.'];
    		}else{
    			$result = ['success' => true, 'message' => 'Settings saved.'];
    		}
        }
    }

    echo json_encode($result);
?>
