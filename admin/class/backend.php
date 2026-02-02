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

		exec('/usr/local/bin/qcarta-tiles_ctl.sh status', $output, $retval);

		return $this->parseSystemd($output);
	}
	
	function systemd_ctl($name, $action){
		if($name == 'qcarta-tiles'){
			shell_exec('sudo /usr/local/bin/qcarta-tiles_ctl.sh '. $action);
		}
	}
}
?>
