<?php
    const TOPIC_RESOURCES = ['layer', 'geostory', 'web_link', 'doc'];

    class topic_Class extends table_Class {
        
        // topic and gemet have same logic and table structure
        function __construct($dbconn, $owner_id, $topic_type = 'topic') {
			parent::__construct($dbconn, $owner_id, $topic_type);
		}

        function create($data) {
             $sql = "INSERT INTO public.".$this->table_name." (name,description) "."VALUES('".
				$this->cleanData($data['name'])."','".
				$this->cleanData($data['description'])."') RETURNING id";
            
            $result = pg_query($this->dbconn, $sql);
            if(!$result){
		 		return 0;
		 	}
            $row = pg_fetch_object($result);
            pg_free_result($result);

            if($row) {
                // insert resources
                foreach(TOPIC_RESOURCES as $type){
                    if(count($data[$type.'_id'])){
                        $values = [];
                        foreach($data[$type.'_id'] as $type_id){
                            $values[] = '('.$row->id.','.$type_id.')';
                        }
                        $sql = 'INSERT INTO public.'.$this->table_name.'_'.$type.' (topic_id, '.$type.'_id) VALUES '.join(',',$values);
                        $result = pg_query($this->dbconn, $sql);
                        pg_free_result($result);
                    }
                }
                return $row->id;
            }
            return 0;
        }

       function update($data=array()){

           $sql = "update public.".$this->table_name." set name='".
				$this->cleanData($data['name'])."', description='".
				$this->cleanData($data['description'])."'";
            $sql .= " WHERE id = '".intval($data['id'])."' ";

            $result = pg_query($this->dbconn, $sql);
            if(!$result){
				return 0;
			}
			$rv = pg_affected_rows($result);

			if($rv > 0){
			    // insert resources
                foreach(TOPIC_RESOURCES as $type){
                    // delete old list of resources
                    $sql = 'DELETE FROM public.'.$this->table_name.'_'.$type.' WHERE '.$this->table_name.'_id = '.$data['id'];
                    $result = pg_query($this->dbconn, $sql);
                    pg_free_result($result);
                    
                    // insert updated list of resources
                    if(count($data[$type.'_id'])){
                        $values = [];
                        foreach($data[$type.'_id'] as $type_id){
                            $values[] = '('.$data['id'].','.$type_id.')';
                        }
                        $sql = 'INSERT INTO public.'.$this->table_name.'_'.$type.' ('.$this->table_name.'_id, '.$type.'_id) VALUES '.join(',',$values);
                        $result = pg_query($this->dbconn, $sql);
                        pg_free_result($result);
                    }
                }
			}
			return $rv;
       }

		function getTypeIds($id){
		    $rv = [];
			
			foreach(TOPIC_RESOURCES as $type){
                $rv[$type] = [];
                
                $sql = 'SELECT '.$type.'_id from public.'.$this->table_name.'_'.$type.' WHERE '.$this->table_name.'_id = '.$id;
                $result = pg_query($this->dbconn, $sql);
                if(!$result || pg_num_rows($result) == 0){
                    continue;
                }
                
                $ids = [];
                while ($row = pg_fetch_assoc($result)) {
                    $ids[] = $row[$type.'_id'];
                }
                pg_free_result($result);
                
                $rv[$type] = $ids;
			}
			return $rv;
		}
	}
