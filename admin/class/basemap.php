<?php		
    class basemap_Class extends table_Class
    {				
				function __construct($dbconn, $owner_id) {
					parent::__construct($dbconn, $owner_id, 'basemaps');
				}
				
				// Override getRows to use the correct table name
				function getRows($cols = '*') {
					$sql = "select ".$cols." from public." .$this->table_name;
					if($this->owner_id != SUPER_ADMIN_ID){
						$sql .= " WHERE owner_id = ".$this->owner_id. ' OR public = true';
					}
					$sql .= " ORDER BY id DESC";
					
					return pg_query($this->dbconn, $sql);
				}
				
				function create($data){
					$sql = "INSERT INTO PUBLIC.".$this->table_name." (name,description,url,type,attribution,min_zoom,max_zoom,public,owner_id,thumbnail) VALUES('".
						$this->cleanData($data['name'])."','".
						$this->cleanData($data['description'])."','".
						$this->cleanData($data['url'])."','".
						$this->cleanData($data['type'])."','".
						$this->cleanData($data['attribution'])."',".
						$this->cleanData($data['min_zoom']).",".
						$this->cleanData($data['max_zoom']).",'".
						$this->cleanData($data['public'])."',".
						$this->owner_id.",'".
						$this->cleanData($data['thumbnail'] ?? '')."') RETURNING id";
					 
					$result = pg_query($this->dbconn, $sql);
					if(!$result){
						return 0;
					}
					
					$row = pg_fetch_object($result);
					pg_free_result($result);
					
					// Create access control if groups are specified
					if (!empty($data['group_id']) && is_array($data['group_id'])) {
						$this->create_access($row->id, $data['group_id']);
					}
											
					return $row->id;
				}
				
				function update($data=array()){
					$sql = "update public.".$this->table_name." set ".
					"name='".$this->cleanData($data['name']).
					"', description='".$this->cleanData($data['description']).
					"', url='".$this->cleanData($data['url']).
					"', type='".$this->cleanData($data['type']).
					"', attribution='".$this->cleanData($data['attribution']).
					"', min_zoom=".intval($data['min_zoom']).
					", max_zoom=".intval($data['max_zoom']).
					", public='".$this->cleanData($data['public']).
					"', thumbnail='".$this->cleanData($data['thumbnail'] ?? '').
					"' where id = '".intval($data['id'])."'";
					
					$result = pg_query($this->dbconn, $sql);
					if($result) {
						$rv = (pg_affected_rows($result) > 0);
						pg_free_result($result);
						
						// Update access control if groups are specified
						if (!empty($data['group_id']) && is_array($data['group_id'])) {
						    $this->drop_access($data['id']);
							$this->create_access($data['id'], $data['group_id']);
						}
						
						return $rv;
					}
					return false;
				}
	}
?>
