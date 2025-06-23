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
					
            $sql = "INSERT INTO PUBLIC." .$this->table_ext.'_'.$this->table_name." (id,cached,proxyfied,customized,exposed,show_charts,show_dt,show_query,print_layout,layers) VALUES('".
							$row_id."','".
							$this->cleanData($data['cached'])."','".
							$this->cleanData($data['proxyfied'])."','".
							$this->cleanData($data['customized'])."','".
							$this->cleanData($data['exposed'])."','".
							$this->cleanData($data['show_charts'])."','".
							$this->cleanData($data['show_dt'])."','".
							$this->cleanData($data['show_query'])."','".
							$this->cleanData($data['print_layout'])."','".
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
					" cached='".$this->cleanData($data['cached']).
					"', proxyfied='".$this->cleanData($data['proxyfied']).
					"', customized='".$this->cleanData($data['customized']).
					"', exposed='".$this->cleanData($data['exposed']).
					"', show_charts='".$this->cleanData($data['show_charts']).
					"', show_dt='".$this->cleanData($data['show_dt']).
					"', show_query='".$this->cleanData($data['show_query']).
					"', print_layout='".$this->cleanData($data['print_layout']).
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

			 function getCacheDir($row){
				 if($row->proxyfied){
					 return DATA_DIR.'/mapproxy/cache_data/'.$row->name.'_cache_EPSG3857';
				 }else if($row->cached){
					 return CACHE_DIR.'/layers/'.$row->id;
				 }else{
					 return null;
				 }
			 }
				
		private function getLayerExtent($id) {    
            $content = @file_get_contents(WWW_DIR.'/layers/'.$id.'/index.php');
            if ($content === false) {
                return null;
            }
    
            // Look for the bbox definition in the JavaScript code
            if (preg_match('/const\s+bbox\s*=\s*\{([^}]+)\};/s', $content, $matches)) {
                $bboxStr = $matches[1];    
                // Parse the unquoted JavaScript object format
                $bbox = [];
                if (preg_match('/minx:\s*([-\d.]+)/', $bboxStr, $m)) $bbox['minx'] = floatval($m[1]);
                if (preg_match('/miny:\s*([-\d.]+)/', $bboxStr, $m)) $bbox['miny'] = floatval($m[1]);
                if (preg_match('/maxx:\s*([-\d.]+)/', $bboxStr, $m)) $bbox['maxx'] = floatval($m[1]);
                if (preg_match('/maxy:\s*([-\d.]+)/', $bboxStr, $m)) $bbox['maxy'] = floatval($m[1]);
    
                if (isset($bbox['minx']) && isset($bbox['miny']) && isset($bbox['maxx']) && isset($bbox['maxy'])) {
                    $extent = [$bbox['minx'], $bbox['miny'], $bbox['maxx'], $bbox['maxy']];
                    return $extent;
                }
            }
            return null;
        }
    
        function search($acc_cond, $text = '', $topic = '', $gemet = '', $keywords = [], $bbox = null) {
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
                $conditions[] = "l.id IN (SELECT ".$this->table_name."_id from topic_".$this->table_name." WHERE topic_id IN (" . $topic.'))';
            }
            
            if (!empty($gemet)) {
                $conditions[] = 'l.id IN (SELECT '.$this->table_name.'_id from gemet_'.$this->table_name.' WHERE gemet_id IN ('.$gemet.'))';
            }
            
            if (!empty($keywords)) {
                $keywordConditions = [];
                foreach ($keywords as $keyword) {
                    $keywordConditions[] = "keywords ILIKE $" . $paramCount;
                    $params[] = "%$keyword%";
                    $paramCount++;
                }
                if (!empty($keywordConditions)) {
                    $conditions[] = "l.id IN (SELECT layer_id FROM public.layer_metadata WHERE " . implode(" OR ", $keywordConditions) . ")";
                }
            }
            
            // Add spatial search if bbox is provided
            if (!empty($bbox) && count($bbox) === 4) { 
                // Get all public layers first
                $sql = "SELECT DISTINCT q.*, l.name, l.description, l.type, l.store_id, l.owner_id, l.last_updated
                        FROM ".$this->table_ext.'_'.$this->table_name." q 
                        JOIN ".$this->table_name." l ON q.id = l.id 
                        WHERE " . implode(" AND ", $conditions) . " 
                        ORDER BY l.name ASC";
                
                $result = pg_query_params($this->dbconn, $sql, $params);
                if (!$result) {
                    return pg_query($this->dbconn, "SELECT 1 WHERE false"); // Return empty result
                }

                // Filter layers by bbox intersection
                $filteredLayers = [];
                while ($row = pg_fetch_assoc($result)) {
                    $layerExtent = $this->getLayerExtent($row['id']);
                    if ($layerExtent) {
                        // Check if the layer's extent intersects with the search bbox
                        if ($layerExtent[0] <= $bbox[2] && $layerExtent[2] >= $bbox[0] && // x overlap
                            $layerExtent[1] <= $bbox[3] && $layerExtent[3] >= $bbox[1]) { // y overlap
                            $filteredLayers[] = $row['id'];
                        }
                    }
                }
                pg_free_result($result);

                // Create a new result set with the filtered layers
                if (empty($filteredLayers)) {
                    return pg_query($this->dbconn, "SELECT 1 WHERE false"); // Return empty result
                }

                // Format the array for PostgreSQL
                $arrayStr = '{' . implode(',', $filteredLayers) . '}';
                $sql = "SELECT DISTINCT q.*, l.name, l.description, l.type, l.store_id, l.owner_id, l.last_updated
                        FROM ".$this->table_ext.'_'.$this->table_name." q 
                        JOIN ".$this->table_name." l ON q.id = l.id 
                        WHERE l.id = ANY($" . $paramCount . "::integer[]) 
                        ORDER BY l.name ASC";
                $params[] = $arrayStr;

                $result = pg_query_params($this->dbconn, $sql, $params);
                if (!$result) {
                    return pg_query($this->dbconn, "SELECT 1 WHERE false"); // Return empty result
                }
                return $result;
            }
    
            // If no bbox search, use the original query
            $sql = "SELECT DISTINCT q.*, l.name, l.description, l.type, l.store_id, l.owner_id, l.last_updated
                    FROM ".$this->table_ext.'_'.$this->table_name." q 
                    JOIN ".$this->table_name." l ON q.id = l.id  
                    WHERE " . implode(" AND ", $conditions) . " 
                    ORDER BY l.name ASC";

            $result = pg_query_params($this->dbconn, $sql, $params);
            if (!$result) {
                return pg_query($this->dbconn, "SELECT 1 WHERE false"); // Return empty result
            }
            return $result;
        }
	}
?>
