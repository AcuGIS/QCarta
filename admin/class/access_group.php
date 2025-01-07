<?php
    class access_group_Class extends table_Class
    {
				function __construct($dbconn, $owner_id) {
					parent::__construct($dbconn, $owner_id, 'access_group');
				}
				
        function create($data)
        {
            $sql = "INSERT INTO PUBLIC." .$this->table_name." (name,owner_id) ".
							"VALUES('".$this->cleanData($data['name'])."',".$this->owner_id.") RETURNING id";
						$result = pg_query($this->dbconn, $sql);
						if(!$result){
							return 0;
						}
						
            $row = pg_fetch_object($result);
						pg_free_result($result);
						
            if($row) {
              return $row->id;
            }
            return 0;
        }

				function getGroupUsers($gids){
						$rv = array();

						$sql = "select id,name from public.user WHERE id in (select user_id from public.user_access where access_group_id in (".implode(',', $gids)."))";
						$result = pg_query($this->dbconn, $sql);

						while ($row = pg_fetch_assoc($result)) {
							$rv[$row['id']] = $row['name'];
						}
						pg_free_result($result);
            return $rv;
        }
				
				function getGroupRows($k, $gids){
						$sql = "select id,name from public.".$k." WHERE id in (SELECT ".$k."_id from public.".$k."_access where access_group_id IN (".$gids."))";
						return pg_query($this->dbconn, $sql);
				}
				
				function getByKV($k,$v){
						$rv = array();

						$sql ="select id,name from public.access_group WHERE id in (SELECT access_group_id from public.".$k."_access where ".$k."_id='".intval($v)."')";
						$result = pg_query($this->dbconn, $sql);

						while ($row = pg_fetch_assoc($result)) {
							$rv[$row['id']] = $row['name'];
						}
						pg_free_result($result);
            return $rv;
        }
				
        function getGroupById($id){
            $sql ="select * from public." .$this->table_name . " where id='".intval($id)."'";
            return pg_query($this->dbconn, $sql);
        }
				
				function getGroupByName($name){
            $sql ="select * from public." .$this->table_name . " where name='".$name."'";
            $result = pg_query($this->dbconn, $sql);
						if(!$result){
							return false;
						}
						$row = pg_fetch_assoc($result);
						pg_free_result($result);
						return $row;
        }

       function update($data=array()) {
          $sql = "update public.access_group set name='".$this->cleanData($data['name'])."' where id = '".intval($data['id'])."' ";
					$result = pg_query($this->dbconn, $sql);
					if(!$result){
						return false;
					}
          $rv = pg_affected_rows($result);
					pg_free_result($result);
					return ($rv > 0);
       }
	}
