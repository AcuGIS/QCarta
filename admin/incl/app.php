<?php
	function rrmdir($dir) {
	 if (is_dir($dir)) {
		 $objects = scandir($dir);
		 foreach ($objects as $object) {
			 if ($object != "." && $object != "..") {
				 if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
					 rrmdir($dir. DIRECTORY_SEPARATOR .$object);
				 else
					 unlink($dir. DIRECTORY_SEPARATOR .$object);
			 }
		 }
		 rmdir($dir);
	 }
	}

	function update_template($src, $dest, $vars){
		
		$lines  = file($src);
		$fp = fopen($dest, 'w');
		
		foreach($lines as $ln => $line){
			foreach($vars as $k => $v){
				if(str_contains($line, $k)){
					$line = str_replace($k, $v, $line);
				}
			}
			fwrite($fp, $line);
		}
		
		fclose($fp);
	}
	
	function update_env($src, $vars){
		
		$lines  = file($src);
		$fp = fopen($src, 'w');
		
		foreach($lines as $ln => $line){
			foreach($vars as $k => $v){
				if(str_starts_with($line, 'const '.$k.' =')){
					$line = 'const '.$k.' = '.$v.';'."\n";
					break;
				}
			}
			fwrite($fp, $line);
		}
		
		fclose($fp);
	}

	function find_qgs($dirname){
		$geo_ext = array('qgs');
		
		$entries = scandir($dirname);
		foreach($entries as $e){
			$filename = $dirname.'/'.$e;
			if(is_file($filename)){
				$ext = pathinfo($filename, PATHINFO_EXTENSION);
				if(in_array($ext, $geo_ext)){
					return $filename;
				}
			}
		}
		return false;
	}
	
	function find_dumps($dirname){
		$rv = array();
		$geo_ext = array('dump');
		
		$entries = scandir($dirname);
		foreach($entries as $e){
			$filename = $dirname.'/'.$e;
			if(is_file($filename)){
				$ext = pathinfo($filename, PATHINFO_EXTENSION);
				if(in_array($ext, $geo_ext)){
					$e_no_ext = substr($e, 0, -5);
					array_push($rv, $e_no_ext);
				}
			}
		}
		return $rv;
	}
	
	function find_gpkgs($dirname){
		$rv = array();
		$geo_ext = array('gpkg');
		
		$directory = new \RecursiveDirectoryIterator($dirname);
        $iterator  = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $info) {
            $fp = $info->getPathname();
            if (is_file($fp)) {
                $ext = pathinfo($fp, PATHINFO_EXTENSION);
				if(in_array($ext, $geo_ext)){
					array_push($rv, $fp);
				}
            }
        }
		return $rv;
	}
	
	function find_gdbs($dirname){
		$rv = array();
		
		$directory = new \RecursiveDirectoryIterator($dirname);
        $iterator  = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $info) {
            $fp = $info->getPathname();
            if (is_dir($fp) && str_ends_with($fp, '.gdb/.')) {
                array_push($rv, substr($fp, 0, -2));
            }
        }
		return $rv;
	}
	
	function find_yaml_snapshots($dirname, $prefix){		
		$rv = array();
		$entries = scandir($dirname);
		foreach($entries as $e){
			$filename = $dirname.'/'.$e;
			if(is_file($filename) && str_starts_with($e, $prefix)){
				list ($name, $v) = explode('_', $e);
				array_push($rv, $v);
			}
		}
		return $rv;
	}

	function dir_size($dirname){
		$size = 0;
		
		if (is_dir($dirname)) {
 		 $objects = scandir($dirname);
 		 foreach ($objects as $object) {
 			 if ($object != "." && $object != "..") {
 				 if (is_dir($dirname. DIRECTORY_SEPARATOR .$object) && !is_link($dirname."/".$object)){
 					 $size += dir_size($dirname. DIRECTORY_SEPARATOR .$object);
 				 }else{
					 $st = stat($dirname.'/'.$object);
 					 $size += $st['size']; 
				 }
 					 
 			 }
 		 }
 	 }
	 return $size;
	}
	
	function human_size($bytes){
		if ($bytes == 0){
			return "0.00";
		}
		$labels = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
		$e = floor(log($bytes, 1024));
		return round($bytes / pow(1024, $e), 2).' '.$labels[$e];
	}
	
	function return_bytes($val){
	    $val = trim($val);
	    $num = (int) rtrim($val, 'KMG');
	    $last = strtolower($val[strlen($val) - 1]);

	    switch ($last) {
	        // The 'G' modifier is available
	        case 'g':
	            $num = $num * 1024 * 1024 * 1024;
	            break;
	        case 'm':
	            $num = $num * 1024 * 1024;
	            break;
	        case 'k':
	            $num *= 1024;
	            break;
	    }

	    return $num;
	}
	
	function is_pid_running($pid_file){
		
		if(!is_file($pid_file)){
			return 0;
		}
		
		$pid = file_get_contents($pid_file);
		$pid = intval($pid);

		if(is_dir('/proc/'.$pid)){
			return $pid;
		}else{
			unlink($pid_file);
		}
		return 0;
	}
	
	function escape_filename($name){
		$name = str_replace('..', '', $name);
		$name = basename($name);
		return $name;
	}
?>
