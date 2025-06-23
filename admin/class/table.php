<?php		
    abstract class table_Class
    {
        protected $dbconn = null;
				protected $owner_id = null;
				public $table_name = null;
								
				//abstract private $table_name = 'table_name';
				abstract function create($data);
				abstract function update($data=array());
				
				protected function cleanData($val){
          return pg_escape_string($this->dbconn, $val);
        }
				
        function __construct($dbconn, $owner_id, $tbl_name) {
            $this->dbconn = $dbconn;
						$this->owner_id = intval($owner_id);
						$this->table_name = $tbl_name;
        }

        function getRows($cols = '*')
        {
          $sql  = "select ".$cols." from public." .$this->table_name;
					if($this->owner_id != SUPER_ADMIN_ID){
						$sql .= " WHERE owner_id = ".$this->owner_id;
					}
					$sql .= " ORDER BY id DESC";
          return pg_query($this->dbconn, $sql);
        }
				
				function getArr(){
						$rv = array();

						$result = $this->getRows('id,name');

						while ($row = pg_fetch_assoc($result)) {
							$rv[$row['id']] = $row['name'];
						}
						pg_free_result($result);
            return $rv;
        }

        function getById($id){
            $sql = "select * from public.".$this->table_name." where id=".$id;
            $result = pg_query($this->dbconn, $sql);
						if(!$result){
							die(pg_last_error($this->dbconn));
						}
						return $result;
        }
        
        function getByIds($ids){
            if(empty($ids)){
                $ids[] = -1;
            }
            $sql = "select * from public.".$this->table_name." where id IN (".join(',', $ids).")";
            $result = pg_query($this->dbconn, $sql);
			if(!$result){
				die(pg_last_error($this->dbconn));
			}
			return $result;
        }

       function delete($id){

				 $sql ="delete from public." .$this->table_name . " where id=".$id;
      	 $result = pg_query($this->dbconn, $sql);
				 if($result){
					 $rv = (pg_affected_rows($result) > 0);
					 pg_free_result($result);
					 return $rv;
				 }else{
					 return false;
				 }
       }
			 
			 function isOwnedByUs($id){
 				
 				if($this->owner_id == SUPER_ADMIN_ID){	// if Super Admin
 					return true;
 				}
 				
 				$sql = "select * from public.".$this->table_name." where id=".$id." and owner_id=".$this->owner_id;
				$result = pg_query($this->dbconn, $sql);
				if(!$result){
					return false;
				}
				$rv = (pg_num_rows($result) > 0);
				pg_free_result($result);
				return $rv;
 			}
			
			function create_access($id, $group_ids){
				# insert user groups
				$values = array();
				foreach($group_ids as $gid){
					array_push($values, "(".$id.",".$gid.")");
				}

				$sql = "insert into public.".$this->table_name."_access (".$this->table_name."_id,access_group_id) values ".implode(',', $values);
				$result = pg_query($this->dbconn, $sql);
				if(!$result){
					return false;
				}
				$rv = (pg_num_rows($result) > 0);
				pg_free_result($result);
				return $rv;
			}
			
			function remove_access($prefix, $id){
				$sql = "delete from public.".$prefix."_access where ".$this->table_name."_id=".$id;
				$result = pg_query($this->dbconn, $sql);
				if(!$result){
					return false;
				}
				$rv = (pg_num_rows($result) >= 0);
				pg_free_result($result);
				return $rv;
			}
			
			function drop_access($id){
				return $this->remove_access($this->table_name, $id);
			}

		function getPublic(){
            $sql = 'SELECT * from public.'.$this->table_name.' WHERE public = true';
            return pg_query($this->dbconn, $sql);
        }
			
		function search($acc_cond, $text = '', $topic = '', $gemet = '', $keywords = []) {
            // Build search conditions
            $conditions = [$acc_cond];
            $params = [];
            $paramCount = 1;

            if (!empty($text)) {
                $conditions[] = "(name ILIKE $" . $paramCount . " OR description ILIKE $" . $paramCount . ")";
                $params[] = "%$text%";
                $paramCount++;
            }

            if (!empty($topic)) {
                $conditions[] = 'id IN (SELECT '.$this->table_name.'_id from topic_'.$this->table_name.' WHERE topic_id IN ('.$topic.'))';
            }
            
            if (!empty($gemet)) {
                $conditions[] = 'id IN (SELECT '.$this->table_name.'_id from gemet_'.$this->table_name.' WHERE gemet_id IN ('.$gemet.'))';
            }

            /*if (!empty($keywords)) {
                $keywordConditions = [];
                foreach ($keywords as $keyword) {
                    $keywordConditions[] = "keywords ILIKE $" . $paramCount;
                    $params[] = "%$keyword%";
                    $paramCount++;
                }
                if (!empty($keywordConditions)) {
                    $conditions[] = "(" . implode(" OR ", $keywordConditions) . ")";
                }
            }*/

            $sql = "SELECT * FROM {$this->table_name} WHERE " . implode(" AND ", $conditions) . " ORDER BY name ASC";
            return pg_query_params($this->dbconn, $sql, $params);
        }
	}
?>
