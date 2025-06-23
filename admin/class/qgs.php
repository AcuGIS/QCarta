<?php		
    class qgs_Class extends table_ext_Class
    {		
				function __construct($dbconn, $owner_id) {
						parent::__construct($dbconn, $owner_id, 'store', 'qgs');
				}

        function create($data)
        {	

					$row_id = parent::create($data);
					if($row_id == 0){
						return 0;
					}
					
          $sql = "INSERT INTO PUBLIC.".$this->table_ext.'_'.$this->table_name." (id,public) VALUES('".
						$row_id."','".
						$this->cleanData($data['public'])."')";
           
					$result = pg_query($this->dbconn, $sql);
					if(!$result){
						$this->delete($row_id);
						return 0;
					}
					pg_free_result($result);
					return $row_id;
        }

       function update($data=array())
       {
				 parent::update($data);
				 
          $sql = "update public.".$this->table_ext.'_'.$this->table_name." set ".
					" public='".$this->cleanData($data['public']).
					"' where id = '".intval($data['id'])."'";
					
					$result = pg_query($this->dbconn, $sql);
					if($result) {
						$rv = (pg_affected_rows($result) > 0);
						pg_free_result($result);
						
						return $rv;
					}
					return false;
       }
	}
?>
