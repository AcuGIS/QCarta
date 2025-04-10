<?php		
    class qgs_layer_Class extends layer_Class
    {		
			function __construct($dbconn, $owner_id) {
					parent::__construct($dbconn, $owner_id, 'layer', 'qgs');
			}

        function create($data)
        {		
					$row_id = parent::create($data);
					if($row_id == 0){
						return 0;
					}
					
            $sql = "INSERT INTO PUBLIC." .$this->table_ext.'_'.$this->table_name." (id,public,cached,proxyfied,customized,exposed,show_dt,layers) VALUES('".
							$row_id."','".
							$this->cleanData($data['public'])."','".
							$this->cleanData($data['cached'])."','".
							$this->cleanData($data['proxyfied'])."','".
							$this->cleanData($data['customized'])."','".
							$this->cleanData($data['exposed'])."','".
							$this->cleanData($data['show_dt'])."','".
							$this->cleanData($data['layers'])."') RETURNING id";
             
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
					"', cached='".$this->cleanData($data['cached']).
					"', proxyfied='".$this->cleanData($data['proxyfied']).
					"', customized='".$this->cleanData($data['customized']).
					"', exposed='".$this->cleanData($data['exposed']).
					"', show_dt='".$this->cleanData($data['show_dt']).
					"', layers='".$this->cleanData($data['layers']).
					"' where id = '".intval($data['id'])."'";
					
					$result = pg_query($this->dbconn, $sql);
					if($result) {
						$rv = (pg_affected_rows($result) > 0);
						pg_free_result($result);
	
						return $rv;
					}
					return false;
       }
			 
			 function getPublic(){
				 $sql = 'SELECT * from public.'.$this->table_name.' INNER JOIN public.'.$this->table_ext.'_'.$this->table_name.' ON '.$this->table_name.'.id = '.$this->table_ext.'_'.$this->table_name.'.id WHERE '.$this->table_ext.'_'.$this->table_name.'.public = true';
				 return pg_query($this->dbconn, $sql);
			 }
			 
			 function getCacheDir($row){
				 if($row->proxyfied){
					 return DATA_DIR.'/mapproxy/cache_data/'.$row->name.'_cache_EPSG3857';
				 }else if($row->cached){
					 return CACHE_DIR.'/layers/'.$row->id;
				 }else{
					 return null;
				 }
			 }
	}
?>
