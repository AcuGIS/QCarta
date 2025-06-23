<?php
class backend_Class
{			
	function parseSystemd($output){
		$rv = [];
		foreach($output as $l){
			if(preg_match('/^\s+([\w\s]+):\s(.*)/is', $l, $m) === 1){
				$k = strtolower($m[1]);
				if($k == 'loaded'){
					$t = explode(';', $m[2]);
					$rv['enabled'] = trim($t[1]);
				}
				$rv[$k] = $m[2];
			}
		}
		return $rv;
	}
	
	function service_status($name){

		if(str_starts_with($name, 'qf-sync@')){
			$id = explode('@', $name)[1];
			exec('/usr/local/bin/qfield_ctl.sh status '.$id, $output, $retval);
		}else if(str_starts_with($name, 'mapproxy-seed@')){
			$id = explode('@', $name)[1];
			exec('/usr/local/bin/mapproxy_seed_ctl.sh status '.$id, $output, $retval);
		}else{
			exec('/usr/local/bin/mapproxy_ctl.sh status', $output, $retval);
		}

		return $this->parseSystemd($output);
	}
	
	function systemd_ctl($name, $action){
		if(str_starts_with($name, 'qf-sync@')){
			$id = explode('@', $name)[1];
			shell_exec('sudo /usr/local/bin/qfield_ctl.sh '. $action.' '.$id);
		}else if(str_starts_with($name, 'mapproxy-seed@')){
			$id = explode('@', $name)[1];
			shell_exec('sudo /usr/local/bin/mapproxy_seed_ctl.sh '. $action.' '.$id);
		}else if($name == 'mapproxy'){
			shell_exec('sudo /usr/local/bin/mapproxy_ctl.sh '. $action);
		}
	}
}
?>