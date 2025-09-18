<?php
	const MAPPROXY_YAML = DATA_DIR.'/mapproxy/mapproxy.yaml';
	const YAML_LOCKFILE = MAPPROXY_YAML.'.lock';
	
class mapproxy_Class
{	
	public static function mapproxy_add_source($name, $qgs_file, $layers){
		
		$lp = fopen(YAML_LOCKFILE, "w");
		if (flock($lp, LOCK_EX)) {  // acquire an exclusive lock
			
			$yml = yaml_parse_file(MAPPROXY_YAML);
			
			if(!is_array($yml['sources'])){
				$yml['sources'] = array();
			}
			
			$yml['sources'][$name] = [
				'type' => 'wms',
				'req' => [
					'url' 		=> 'http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.urlencode($qgs_file),
					'layers'	=> $layers,
					'transparent' => true
				]
			];
			
			$cache_name = $name.'_cache';
			$lyr = ['name' => $name, 'title' => 'Layer '.$name, 'sources' => array($cache_name)];
			if(!is_array($yml['layers'])){
				$yml['layers'] = array();
			}
			array_push($yml['layers'], $lyr);

			if(!is_array($yml['caches'])){
				$yml['caches'] = array();
			}
			$yml['caches'][$cache_name] = [ 'grids' => ['webmercator'], 'request_format' => 'image/png', 'format' => 'image/png', 'sources' => [$name] ];
			
			yaml_emit_file(MAPPROXY_YAML, $yml);
			
			flock($lp, LOCK_UN);    // release the lock
		} else {
			echo "Error: Couldn't get the lock!";
		}
		fclose($lp);
	}
	
	public static function mapproxy_delete_source($name){
		$rv = false;

		$lp = fopen(YAML_LOCKFILE, "w");
		if (flock($lp, LOCK_EX)) {  // acquire an exclusive lock
			
			$yml = yaml_parse_file(MAPPROXY_YAML);
			if(isset($yml['sources'][$name])){
				unset($yml['sources'][$name]);
				
				$cache_name = $name.'_cache';
				unset($yml['caches'][$cache_name]);
				
				for($i=0; $i < count($yml['layers']); $i++){
					$lyr = $yml['layers'][$i];
					if($lyr['name'] == $name){
						unset($yml['layers'][$i]);
						$yml['layers'] = array_values($yml['layers']);	//reindex array
						break;
					}
				}
				
				// set dict sections to null, to avoid saving them as empty arrays
				if(empty($yml['sources'])){ $yml['sources'] = null;}
				if(empty($yml['caches'])){ $yml['caches'] = null;}
				if(empty($yml['layers'])){ $yml['layers'] = null;}

				yaml_emit_file(MAPPROXY_YAML, $yml);
				$rv = true;
			}
			$rv = false;
			
			flock($lp, LOCK_UN);    // release the lock
		} else {
			echo "Error: Couldn't get the lock!";
			$rv = false;
		}
		fclose($lp);

		return $rv;
	}
	
	public static function mapproxy_add_seed($names, $id){
        $cache_names = array();
    	foreach($names as $name){
    	    array_push($cache_names, $name.'_cache');
    	}
    	$seed_label = implode(',', $cache_names);

    	$seed_yaml = file_get_contents('../snippets/seed.yaml');
    	$seed_yaml = str_replace('[osm_cache]', '['.$seed_label.']', $seed_yaml);
    	file_put_contents(DATA_DIR.'/layers/'.$id.'/seed.yaml', $seed_yaml);
        
        shell_exec('mapproxy_seed_ctl.sh enable '.$id);
	}
	
	public static function mapproxy_seed_progress($prog_file){
		if(is_file($prog_file)){
			$log = file_get_contents($prog_file);
			if(preg_match_all('/\[\d+:\d+:\d+\]\s+(\d+)\s+([0-9\.]+)%\s+[0-9\.,+\-\s]+\s+\(\d+\s+tiles\)/', $log, $matches)){
				$last = array($matches[1][count($matches[0])-1], $matches[2][count($matches[0])-1]);
				return $last;
			}
		}
		return [0,0];
	}
}
