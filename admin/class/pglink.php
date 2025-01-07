<?php
		const PG_SERVICE_CONF = '/var/www/data/qgis/pg_service.conf';
		
    class pglink_Class extends table_ext_Class
    {
			function __construct($dbconn, $owner_id) {
					parent::__construct($dbconn, $owner_id, 'store', 'pg');
			}

        function create($data)
        {	
					
					$row_id = parent::create($data);
					if($row_id == 0){
						return 0;
					}
					
					$host 		= $this->cleanData($data['host']);
					$port 		= $this->cleanData($data['port']);
					$username	= $this->cleanData($data['username']);
					$password = $this->cleanData($data['password']);
					$schema		= $this->cleanData($data['schema']);
					$dbname		= $this->cleanData($data['dbname']);
					$svc_name	= $this->cleanData($data['svc_name']);
					
            $sql = "INSERT INTO PUBLIC.".$this->table_ext.'_'.$this->table_name."
            (id,host,port,username,password,schema,dbname,svc_name) ".
						"VALUES('".$row_id."','".$host."',".$port.",'".$username."','".$password."','".$schema."','".$dbname."','".$svc_name."') RETURNING id";
						
						$result = pg_query($this->dbconn, $sql);
						if(!$result){
							$this->delete($row_id);
							return 0;
						}
						pg_free_result($result);
						return $row_id;
        }
				
				function getPassword($id){
				 $result = $this->getById($id);
				 if(!$result || (pg_num_rows($result) == 0)){
					 return false;
				 }
				 
				 $row = pg_fetch_object($result);
				 pg_free_result($result);
				 return $row->password;
			 }
			 
			 function getConnInfo($id){
				 $result = $this->getById($id);
				 if(!$result || (pg_num_rows($result) == 0)){
					 return false;
				 }
				 
				 $row = pg_fetch_object($result);
				 pg_free_result($result);
				 
				 $conn_info = 'host='.$row->host.' port='.$row->port.' dbname='.$row->dbname.' user='.$row->username.' password='.$row->password;
				 
				 return $conn_info;
			 }
			 
			 function getConnInfoAssoc($id){
				 $rv = [];
				 
				 $conn_info =  $this->getConnInfo($id);
				 if($conn_info === false){
					 return false;
				 }
				 
				 $tokens = explode(' ', $conn_info);
				 foreach($tokens as $t){
					 if(strlen($t) > 0){
						 $v = explode('=', $t);
						 $rv[$v[0]] = $v[1];
				 	 }
				 }
				 
				 return $rv;
			 }

       function update($data=array())
       {
				 
				 parent::update($data);
				 
          $sql = "update public.".$this->table_ext.'_'.$this->table_name." set "
					." host='".$this->cleanData($data['host'])
					."', port=".$this->cleanData($data['port'])
					.", username='".$this->cleanData($data['username'])
					."', password='".$this->cleanData($data['password'])
					."', schema='".$this->cleanData($data['schema'])
					."', dbname='".$this->cleanData($data['dbname'])
					."', svc_name='".$this->cleanData($data['svc_name'])
					."' where id = '".intval($data['id'])."'";
					
					$result = pg_query($this->dbconn, $sql);
					if($result) {
						$rv = (pg_affected_rows($result) > 0);
						pg_free_result($result);

						return $rv;
					}
					return false;
       }

			function pg_service_ctl($cmd, $va){
 		 		$ini_data = (is_file(PG_SERVICE_CONF)) ? parse_ini_file(PG_SERVICE_CONF, true) : array();
				
				if($cmd == 'del'){
					unset($ini_data[$va['svc_name']]);
				}else{	// add or edit
					$ini_data[$va['svc_name']] = array('host' => $va['host'], 'port' => $va['port'],
						'dbname' => $va['dbname'], 'user' => $va['username'], 'password' => $va['password']);
				}
				
				$content = '';
				foreach($ini_data as $svc_name => $kv){
					$content .= "\n".'['.$svc_name.']'."\n";
					foreach($kv as $k => $v){
						$content .= $k.'='.$v."\n";
					}
				}
				file_put_contents(PG_SERVICE_CONF, $content);

				return 0;
			}
			
			function getPublic(){
				$sql = 'SELECT * from public.'.$this->table_name.' INNER JOIN public.'.$this->table_ext.'_'.$this->table_name.' ON '.$this->table_name.'.id = '.$this->table_ext.'_'.$this->table_name.'.id WHERE '.$this->table_ext.'_'.$this->table_name.'.public = true';
				return pg_query($this->dbconn, $sql);
			}
	}
