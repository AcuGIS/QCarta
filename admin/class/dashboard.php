<?php
    class dashboard_Class extends table_Class {

        function __construct($dbconn, $owner_id) {
			parent::__construct($dbconn, $owner_id, 'dashboard');
		}

        function create($data) {
            if(empty($data['filename'])){		$data['filename'] = '';	}
            
             $sql = "INSERT INTO public.".$this->table_name." (name,description,public,layer_id,owner_id,filename) "."VALUES('".
				$this->cleanData($data['name'])."','".
				$this->cleanData($data['description'])."','".
				$this->cleanData($data['public'])."',".
				$this->cleanData($data['layer_id']).",".
				$this->owner_id.",'".
                $this->cleanData($data['filename'])."') RETURNING id";
            
            $result = pg_query($this->dbconn, $sql);
            if(!$result){
		 		return 0;
		 	}
            $row = pg_fetch_object($result);
            pg_free_result($result);

            if($row) {
                $this->create_access($row->id, $data['group_id']);
                return $row->id;
            }
            return 0;
        }

       function update($data=array()){

           $sql = "update public.".$this->table_name." set name='".
				$this->cleanData($data['name'])."', description='".
				$this->cleanData($data['description'])."', public='".
				$this->cleanData($data['public'])."', layer_id=".
				$this->cleanData($data['layer_id'])."";
			if(isset($data['filename'])){
				$sql .=  ", filename='".$this->cleanData($data['filename'])."'";
			}
            $sql .= " WHERE id = '".intval($data['id'])."' ";

            $result = pg_query($this->dbconn, $sql);
            if(!$result){
				return 0;
			}
			$rv = pg_affected_rows($result);

			if($rv > 0){
				$this->drop_access($data['id']);
				$this->create_access($data['id'], $data['group_id']);
			}
			return $rv;
       }
	}
