<?php
	include('../../admin/incl/index_prefix.php');
	include('../../admin/incl/qgis.php');

	$layers = null;
	$format = null;

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
	
	$qgis_url = 'http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.QGIS_FILENAME_ENCODED.'&'.$_SERVER['QUERY_STRING'];
	
	if(CACHE_ENABLED){
		// check for cached reply
		$cache_key = sha1($_SERVER['QUERY_STRING']);
		$cache_dir = CACHE_DIR.'/layers/'.LAYER_ID.'/'.$cache_key[0];
		
		$cache_file = $cache_dir.'/'.$cache_key;
		if(!is_file($cache_file)){
			
			$fin  = fopen($qgis_url, 'r');
			$fout = fopen($cache_file, 'w');
			
			while(($contents = fread($fin, 4096))){
				fwrite($fout, $contents);
			}
			fclose($fin);
			fclose($fout);
		}
		
		$st = stat($cache_file);
		header("Content-Length: ".$st['size']);
		
		readfile($cache_file);
	}else{
		readfile($qgis_url);
	}
?>
