<?php		
    class layer_Class extends table_ext_Class
    {				
				function __construct($dbconn, $owner_id, $tbl_name, $tbl_ext = null) {
					parent::__construct($dbconn, $owner_id, $tbl_name, $tbl_ext);
				}
				
				function create($data){
					$sql = "INSERT INTO PUBLIC.".$this->table_name." (name,description,type,public,store_id,owner_id) VALUES('".
						$this->cleanData($data['name'])."','".
						$this->cleanData($data['description'])."','".
						$this->table_ext."','".
						$this->cleanData($data['public'])."',".
						$this->cleanData($data['store_id']).",".
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
					"', description='".$this->cleanData($data['description']).
					"', public='".$this->cleanData($data['public']).
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
	}
?>
