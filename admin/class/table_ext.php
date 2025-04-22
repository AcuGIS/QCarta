<?php		
    class table_ext_Class extends table_Class
    {
				protected $table_ext = null;	// pg, qgis
				
				function __construct($dbconn, $owner_id, $tbl_name, $tbl_ext = null) {
					parent::__construct($dbconn, $owner_id, $tbl_name);
					$this->table_ext = $tbl_ext;
				}
				
				function create($data){
					$sql = "INSERT INTO PUBLIC.".$this->table_name." (name,type,owner_id) VALUES('".
						$this->cleanData($data['name'])."','".
						$this->table_ext."',".
						$this->owner_id.") RETURNING id";
					 
					$result = pg_query($this->dbconn, $sql);
					if(!$result){
						return 0;
					}
					
					$row = pg_fetch_object($result);
					pg_free_result($result);
					
					$this->create_access($row->id, $data['group_id']);
											
					return $row->id;
				}
				
				function update($data=array()){
					$sql = "update public.".$this->table_name." set ".
					"name='".$this->cleanData($data['name']).
					"' where id = '".intval($data['id'])."'";
					
					$result = pg_query($this->dbconn, $sql);
					if($result) {
						$rv = (pg_affected_rows($result) > 0);
						pg_free_result($result);
						
						$this->drop_access($data['id']);
						$this->create_access($data['id'], $data['group_id']);
						
						return $rv;
					}
					return false;
				}

        function getRows($cols = "*")
        {
          $sql  = "select * from public." .$this->table_name;
					if($this->table_ext){
						$sql .= ' INNER JOIN '.$this->table_ext.'_'.$this->table_name.' ON '.$this->table_name.'.id = '.$this->table_ext.'_'.$this->table_name.'.id';
					}
					
					if($this->owner_id != SUPER_ADMIN_ID){
						$sql .= " WHERE owner_id = ".$this->owner_id;
					}
					
					$sql .= " ORDER BY ".$this->table_name.".id DESC";
          return pg_query($this->dbconn, $sql);
        }

        function getById($id){
            $sql = "select * from public.".$this->table_name;
						if($this->table_ext){
							$sql .= ' INNER JOIN '.$this->table_ext.'_'.$this->table_name.' ON '.$this->table_name.'.id = '.$this->table_ext.'_'.$this->table_name.'.id';
						}
						$sql .= " WHERE ".$this->table_name.".id=".$id;
            $result = pg_query($this->dbconn, $sql);
						if(!$result){
							die(pg_last_error($this->dbconn));
						}
						return $result;
        }

       function delete($id){
				 $sql = '';

				 $this->drop_access($id);

				 if($this->table_ext){
					 $sql .= 'delete from public.'.$this->table_ext.'_'.$this->table_name.' where id='.$id.';';
				 }
				 $sql .= 'delete from public.'.$this->table_name." where id=".$id;
      	 $result = pg_query($this->dbconn, $sql);
				 if($result){
					 $rv = (pg_affected_rows($result) > 0);
					 pg_free_result($result);
					 return $rv;
				 }else{
					 return false;
				 }
       }
       
       function getByName($name){
           $sql = 'SELECT * from public.'.$this->table_name.' INNER JOIN public.'.$this->table_ext.'_'.$this->table_name.' ON '.$this->table_name.'.id = '.$this->table_ext.'_'.$this->table_name.'.id WHERE '.$this->table_name.'.name = \''.$name.'\'';
           return pg_query($this->dbconn, $sql);
       }
	}
?>
