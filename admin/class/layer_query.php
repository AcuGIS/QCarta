<?php
class layer_query_Class extends table_Class
{

	function __construct($dbconn, $owner_id) {
		parent::__construct($dbconn, $owner_id, 'layer_query');
	}
	
	function create($data, $isHashed = false){		
        $sql = "INSERT INTO public." .$this->table_name."(name,description,badge,database_type,sql_query,layer_id,owner_id) "."VALUES('".
            $this->cleanData($data['name'])."','".
            $this->cleanData($data['description'])."','".
            $this->cleanData($data['badge'])."','".
            $this->cleanData($data['database_type'])."','".
            $this->cleanData($data['sql_query'])."',".
            $this->cleanData($data['layer_id']).",".
            $this->owner_id.") RETURNING id";
        
        $result = pg_query($this->dbconn, $sql);
        if(!$result){
           	return 0;
        }
        $row = pg_fetch_object($result);
        pg_free_result($result);
        
        return ($row) ? $row->id : 0;
    }
    
    function update($data=array()){
        $id = intval($data['id']);

        $sql = 'UPDATE public.'.$this->table_name." set name='".$this->cleanData($data['name']).
                "', description='".$this->cleanData($data['description']).
                "', badge='".$this->cleanData($data['badge']).
                "', database_type='".$this->cleanData($data['database_type']).
                "', sql_query='".$this->cleanData($data['sql_query']).
				"' where id = '".$id."'";
					
		$result = pg_query($this->dbconn, $sql);
		if(!$result){
			return 0;
		}
					
		$rv = pg_affected_rows($result);
		pg_free_result($result);

		return $rv;
    }
    
    function getLayerRows($layer_id){
        $sql  = "select * from public." .$this->table_name;
        $sql .= ' WHERE layer_id='.$layer_id;
        if($this->owner_id != SUPER_ADMIN_ID){
       	$sql .= " AND owner_id = ".$this->owner_id;
        }
		$sql .= " ORDER BY id DESC";
      return pg_query($this->dbconn, $sql);
    }
}
?>
