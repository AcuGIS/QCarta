<?php
/**
 * Central helper for building /mproxy/service URLs with QGIS project file paths
 * 
 * This function retrieves the QGIS project file path from QCarta metadata
 * (database or config) and returns the base URL for /mproxy/service with
 * the map parameter included.
 * 
 * @param int|string $storeIdOrLayerId Either a store_id or layer_id
 * @return string Base URL: /mproxy/service?map=/FULL/PATH/TO/PROJECT.qgs
 * @throws Exception If map path cannot be determined or is missing
 */
function getMproxyBaseUrl($storeIdOrLayerId) {
	require_once(__DIR__ . '/../admin/incl/const.php');
	require_once(__DIR__ . '/../admin/incl/app.php');
	require_once(__DIR__ . '/../admin/class/database.php');
	require_once(__DIR__ . '/../admin/class/qgs_layer.php');
	
	$store_id = null;
	$qgs_file = null;
	
	// Determine if input is store_id or layer_id
	// Try to get store_id from layer_id if needed
	if (is_numeric($storeIdOrLayerId)) {
		$id = intval($storeIdOrLayerId);
		
		// First, try to find QGS file directly using store_id
		$qgs_file = find_qgs(DATA_DIR . '/stores/' . $id);
		
		// If not found, try as layer_id and get store_id from database
		if ($qgs_file === false) {
			try {
				$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
				$user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;
				$ql_obj = new qgs_layer_Class($database->getConn(), $user_id);
				$result = $ql_obj->getById($id);
				
				if ($result && pg_num_rows($result) > 0) {
					$ql_row = pg_fetch_object($result);
					$store_id = $ql_row->store_id;
					pg_free_result($result);
					
					// Now find QGS file using store_id
					$qgs_file = find_qgs(DATA_DIR . '/stores/' . $store_id);
				} else {
					if ($result) {
						pg_free_result($result);
					}
				}
			} catch (Exception $e) {
				// Log error but continue to check if it's a store_id
				error_log("getMproxyBaseUrl: Error querying layer $id: " . $e->getMessage());
			}
		} else {
			// Found as store_id
			$store_id = $id;
		}
	} else {
		// Invalid input
		throw new Exception("getMproxyBaseUrl: Invalid store_id or layer_id: " . var_export($storeIdOrLayerId, true));
	}
	
	// Validate that we found a QGS file
	if ($qgs_file === false || empty($qgs_file)) {
		$identifier = $store_id ?? $storeIdOrLayerId;
		$error_msg = "getMproxyBaseUrl: QGIS project file not found for store_id/layer_id: $identifier";
		error_log($error_msg);
		throw new Exception($error_msg);
	}
	
	// Validate that the file exists
	if (!file_exists($qgs_file)) {
		$identifier = $store_id ?? $storeIdOrLayerId;
		$error_msg = "getMproxyBaseUrl: QGIS project file does not exist: $qgs_file (store_id/layer_id: $identifier)";
		error_log($error_msg);
		throw new Exception($error_msg);
	}
	
	// Build and return the base URL
	$base_url = '/mproxy/service?map=' . urlencode($qgs_file);
	return $base_url;
}
?>
