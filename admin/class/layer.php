<?php		
    class layer_Class extends table_ext_Class
    {				
				function __construct($dbconn, $owner_id, $tbl_name, $tbl_ext = null) {
					parent::__construct($dbconn, $owner_id, $tbl_name, $tbl_ext);
				}
				
				function create($data){
					$sql = "INSERT INTO PUBLIC.".$this->table_name." (name,type,store_id,owner_id) VALUES('".
						$this->cleanData($data['name'])."','".
						$this->table_ext."',".
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
	}
?>