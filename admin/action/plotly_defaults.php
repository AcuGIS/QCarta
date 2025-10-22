<?php
    session_start();
	require('../incl/const.php');
    require('../class/database.php');
    require('../class/table.php');
	require('../class/table_ext.php');
	require('../class/qgs.php');
    require('../incl/qgis.php');
    
    $result = ['success' => false, 'message' => 'Error while processing your request!'];

    if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
		$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		$obj = new qgs_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		$id = isset($_POST['id']) ? intval($_POST['id']) : -1;
		$feature = isset($_POST['feature']) ? $_POST['feature'] : '';
		$action = empty($_POST['action']) ? 0 : $_POST['action'];
		
		$plotly_defaults_file = DATA_DIR.'/stores/'.$id.'/plotly_defaults.json';
		if(is_file($plotly_defaults_file)){
		    $plotlyDefaults = json_decode(file_get_contents($plotly_defaults_file), true);
		}else{
		    $plotlyDefaults = ['defaults' => [], 'layerDefaults' => [], 'chartTypes' => [], 'fieldMappings' => []];
		}
		
		if(($id > 0) && !$obj->isOwnedByUs($id)){
			$result = ['success' => false, 'message' => 'Action not allowed!'];
        }else if($action == 'save') {
            
            $plotlyDefaults['layerDefaults'][$feature] = [
              "chartType" => $_POST['chartType'],
              "xField" => $_POST['xField'],
              "yField" => $_POST['yField'],
              "chartConfig" => "fields_chart.json"
            ];
            
            $plotlyDefaults['chartTypes'][$feature] = $_POST['chartTypes'];

            if(isset($_POST['is_default'])){
                $plotlyDefaults['defaults'] = [
                  "layer" => $feature,
                  "chartType" => $_POST['chartType'],
                  "xField" => $_POST['xField'],
                  "yField" => $_POST['yField'],
                  "chartConfig" => "default_chart.json"
                ];  
            }
            
			if(file_put_contents($plotly_defaults_file, json_encode($plotlyDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false){
				$result = ['success' => false, 'message' => 'Failed to save defaults!'];
			}else{
				$result = ['success' => true, 'message' => 'Feature defaults were successfully saved!'];
			}
		} else if($action == 'delete') {
		    
		    unset($plotlyDefaults['layerDefaults'][$feature]);
			unset($plotlyDefaults['chartTypes'][$feature]);
			
			if(file_put_contents($plotly_defaults_file, json_encode($plotlyDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) == false){
   	            $result = ['success' => true, 'message' => 'Feature defaults successfully deleted!'];
			}else{
				$result = ['success' => false, 'message' => 'Failed to delete Feature defaults!'];
			}
		}
    }
    echo json_encode($result);
?>
