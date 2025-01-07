<?php
    class access_key_Class extends table_Class
    {
				function __construct($dbconn, $owner_id) {
					parent::__construct($dbconn, $owner_id, 'access_key');
				}
				
				function insert_ips($id, $allow_from){
					$ips = explode(',', $allow_from);
					foreach($ips as $ip){
						$sql = 'INSERT INTO public.'.$this->table_name.'_ips (access_key_id,addr) VALUES('.$id.",'".$ip."')";
						$result = pg_query($this->dbconn, $sql);
						if(!$result){
							return false;
						}
						pg_free_result($result);
					}
					return true;
				}
				
        function create($data)
        {						
            $sql = "INSERT INTO PUBLIC." .$this->table_name." (valid_until,ip_restricted,owner_id) ".
							"VALUES('".$this->cleanData($data['valid_until']).
								"','".$this->cleanData($data['ip_restricted']).
								"',".$this->owner_id.") RETURNING id";
						$result = pg_query($this->dbconn, $sql);
						if(!$result){
							return 0;
						}
						
            $row = pg_fetch_object($result);
						pg_free_result($result);
						
            if($row) {
							if(empty($data['allow_from'])){
								$data['allow_from'] = '0.0.0.0';
							}
							if(!$this->insert_ips($row->id, $data['allow_from'])){
								$this->delete($row->id);
								return 0;
							}
              return $row->id;
            }
            return 0;
        }

       function update($data=array()) {

          $sql = "update public.".$this->table_name." set ".
						"valid_until='".$this->cleanData($data['valid_until']).
						"', ip_restricted='".$this->cleanData($data['ip_restricted']).
						"' where id = '".intval($data['id'])."' ";
					$result = pg_query($this->dbconn, $sql);
					if(!$result){
						return false;
					}
          $rv = pg_affected_rows($result);
					pg_free_result($result);
					
					if($rv > 0){
						 
						 $sql ="delete from public." .$this->table_name."_ips where access_key_id=".intval($data['id']);
		       	 $result = pg_query($this->dbconn, $sql);
		 				 pg_free_result($result);
						 
						 if(empty($data['allow_from'])){
							 $data['allow_from'] = '0.0.0.0';
						 }

						 if(!$this->insert_ips($data['id'], $data['allow_from'])){
 							return false;
 						 }
						return true;
					}
					
					return false;
       }
			 
			 function delete($id){

				 $sql ="delete from public." .$this->table_name."_ips where access_key_id=".$id;
      	 $result = pg_query($this->dbconn, $sql);
				 if($result){
					 pg_free_result($result);
					 parent::delete($id);
					 return true;
				 }else{
					 return false;
				 }
       }
			 
			function check_key($access_key){
				$sql = "select * from public.".$this->table_name." where access_key='".$access_key."' AND valid_until >= now()";
				$result = pg_query($this->dbconn, $sql);
				if(!$result){
					return null;
				}
				$row = pg_fetch_object($result);
				pg_free_result($result);
				
				return $row;
			}
			
			function refresh_key($id){
				$sql = "UPDATE public.".$this->table_name." SET valid_until = valid_until + interval '15 minutes' where id=".$id;
				$result = pg_query($this->dbconn, $sql);
				pg_free_result($result);
			}
			
			function getByIP($user_id, $ip){
				$sql = "SELECT * from ".$this->table_name." WHERE valid_until >= now() AND owner_id=".$user_id." AND id IN (select access_key_id from public.".$this->table_name."_ips WHERE addr='".$ip."')";
				$result = pg_query($this->dbconn, $sql);
				if(!$result){
					return null;
				}
				$row = pg_fetch_object($result);
				pg_free_result($result);
				return $row;
			}
			
			function check_addr($access_key_id, $addr){
				$sql = "select * from public.".$this->table_name."_ips where access_key_id=".$access_key_id." AND addr='".$addr."'";

				$result = pg_query($this->dbconn, $sql);
				if(!$result){
					return false;
				}
				$rv = (pg_num_rows($result) > 0);
				pg_free_result($result);
				return $rv;
			}
			
			function clear_expired(){

				$sql ="delete from public." .$this->table_name." WHERE valid_until < now()";
				$result = pg_query($this->dbconn, $sql);
				if($result){
					$rv = (pg_affected_rows($result) > 0);
					pg_free_result($result);
					return $rv;
				}else{
					return false;
				}
			}
	}
