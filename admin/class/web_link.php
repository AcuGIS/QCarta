<?php
    class web_link_Class extends table_Class {

        function __construct($dbconn, $owner_id) {
			parent::__construct($dbconn, $owner_id, 'web_link');
		}

        function create($data) {
             $sql = "INSERT INTO public.".$this->table_name." (name,description,public,url) "."VALUES('".
				$this->cleanData($data['name'])."','".
				$this->cleanData($data['description'])."','".
				$this->cleanData($data['public'])."','".
                $this->cleanData($data['url'])."') RETURNING id";
            
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
				$this->cleanData($data['public'])."', url='".
                $this->cleanData($data['url'])."' where id = '".intval($data['id'])."' ";

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
