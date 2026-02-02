<?php
	include('../../admin/incl/index_prefix.php');
	include('../../admin/incl/qgis.php');

	$layers = null;
	$format = null;

	// Enforce qcarta-tiles cache bypass per layer setting
	// Do this BEFORE converting to lowercase so we can check case-insensitively
	if (defined('CACHE_ENABLED') && CACHE_ENABLED === false) {
		$hasCache = false;
		foreach ($_GET as $k => $v) {
			if (strcasecmp($k, 'CACHE') === 0) { $hasCache = true; break; }
		}
		if (!$hasCache) {
			$_GET['CACHE'] = '0';
		}
	}

	$_GET = array_change_key_case($_GET, CASE_LOWER); 
	
	if($_GET['request'] == 'GetFeature'){
		if(!empty($_GET['outputformat'])){
			$format = urldecode($_GET['outputformat']);
		}else{
			http_response_code(400);	// Bad Request
			die(400);
		}
    }else if($_GET['request'] == 'GetPrint'){
                if(!empty($_GET['format'])){
                        $format = urldecode($_GET['format']);
                }else{
                        http_response_code(400);        // Bad Request
                        die(400);
                }
	}else if($_GET['request'] == 'GetFeatureInfo'){
			if(!empty($_GET['info_format'])){
				$format = urldecode($_GET['info_format']);
			}else{
				http_response_code(400);	// Bad Request
				die(400);
			}
	}else{
		if(!empty($_GET['layers'])){
			$layers = urldecode($_GET['layers']);
		}else{
			http_response_code(400);	// Bad Request
			die(400);
		}

		// check queried layers are allowed
		foreach(explode(',',  $layers) as $l){
			if(!in_array($l, QGIS_LAYERS, true)){
				http_response_code(405);	//not allowed
				die(405);
			}
		}
		
		if(!empty($_GET['format'])){
			$format = urldecode($_GET['format']);
		}else{
			http_response_code(400);	// Bad Request
			die(400);
		}
	}

	format2headers($format, LAYER_ID);
	
	// Use qcarta-tiles for caching (Go service at 127.0.0.1:8011)
	// Build query string from $_GET to ensure CACHE=0 is included if injected
	$base_url = 'http://127.0.0.1:8011/mproxy/service?map='.QGIS_FILENAME_ENCODED;
	$query = http_build_query($_GET);
	$qgis_url = $base_url . (strpos($base_url, '?') === false ? '?' : '&') . $query;
	
	// Temporary debug log (remove after testing)
	// error_log("proxy_qgis.php: Forwarding to qcarta-tiles - CACHE_ENABLED=" . (defined('CACHE_ENABLED') && CACHE_ENABLED ? 'true' : 'false') . ", URL=" . $qgis_url);

	readfile($qgis_url);
?>
