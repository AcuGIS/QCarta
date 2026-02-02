<?php
    const SECTION_TYPES = ['wms', 'html', 'upload', 'pg'];
    
    class geostory_Class extends table_Class
    {

				function __construct($dbconn, $owner_id) {
					parent::__construct($dbconn, $owner_id, 'geostory');
				}

		function save_sections($id, $data){
	        $section_order = 0;
            foreach ($data['sections'] as $index => $section) {
                $content = $this->cleanData($section['content']);
                if ($section['type'] === 'html') {
                    $sql = 'INSERT INTO public.geostory_html (story_id,section_order,title,content) VALUES ('.$id.','.$section_order.',\''.$section['title'].'\', \''.$content.'\')';
                } else if ($section['type'] === 'wms') {

                    if(empty($section['map_center'])){ $section['map_center'] = '0.0, 0.0'; }
                    if(empty($section['map_zoom'])){ $section['map_zoom'] = 4; }

                    $sql = 'INSERT INTO public.geostory_wms (story_id,layer_id,section_order,title,layers,basemap_id,content,map_center,map_zoom) '.
                            'VALUES ('.$id.','.$section['layer_id'].','.$section_order.',\''.$section['title'].'\',\''.$section['layers'].'\',\''.($section['basemap_id'] ?? '').'\',\''.$content.'\',\''.$section['map_center'].'\', '.$section['map_zoom'].')';
                } else if ($section['type'] === 'upload') {
                    $style = $section['style'];
                    $sql = 'INSERT INTO public.geostory_upload (story_id,section_order,title,fillColor,strokeColor,strokeWidth,fillOpacity,pointRadius,content) '.
                            'VALUES ('.$id.','.$section_order.',\''.$section['title'].'\',\''.$style['fillColor'].'\',\''.$style['strokeColor'].'\','.$style['strokeWidth'].','.$style['fillOpacity'].','.$style['pointRadius'].', \''.$content.'\')';
                } else if ($section['type'] === 'pg') {
                    $style = $section['style'];
                    $sql = 'INSERT INTO public.geostory_pg (story_id,pg_layer_id,section_order,title,fillColor,strokeColor,strokeWidth,fillOpacity,pointRadius,content) '.
                            'VALUES ('.$id.','.$section['pg_layer_id'].','.$section_order.',\''.$section['title'].'\',\''.$style['fillColor'].'\',\''.$style['strokeColor'].'\','.$style['strokeWidth'].','.$style['fillOpacity'].','.$style['pointRadius'].', \''.$content.'\')';
                }
                $result = pg_query($this->dbconn, $sql);
     			if(!$result){
          		    return false;
     			}
     			pg_free_result($result);
        
                $section_order = $section_order + 1;
            }
            return true;
		}
		
		function drop_sections($id){
    		$success = true;
    		foreach(SECTION_TYPES as $s){
                $sql = 'DELETE FROM public.'.$this->table_name.'_'.$s.' where story_id='.$id;
                $result = pg_query($this->dbconn, $sql);
                if(!$result){
                    $success = false;
                }
                pg_free_result($result);
    		}
    		return $success;
		}
			
        function create($data){		

            $sql = "INSERT INTO public." .$this->table_name."(name,description,public,export_type,owner_id) "."VALUES('".
                $this->cleanData($data['name'])."','".
                $this->cleanData($data['description'])."','".
                $this->cleanData($data['public'])."','".
                $this->cleanData($data['export_type'])."',".
                $this->owner_id.") RETURNING id";

			$result = pg_query($this->dbconn, $sql);
			if(!$result){
		 	    return 0;
			}
            $row = pg_fetch_object($result);
			pg_free_result($result);

            if($row) {
                $this->save_sections($row->id, $data);
                $this->create_access($row->id, $data['group_id']);

                return $row->id;
            }
            return 0;
        }

       function update($data=array()) {

          $id = intval($data['id']);

          $sql = "update public.".$this->table_name." set name='".$this->cleanData($data['name'])."'".
                 ", description='".$this->cleanData($data['description'])."'".
                 ", public='".$this->cleanData($data['public'])."'".
                 ", export_type='".$this->cleanData($data['export_type'])."'".
                 " where id = '".$id."'";
					
			$result = pg_query($this->dbconn, $sql);
			if(!$result){
				return 0;
			}
			
			$rv = pg_affected_rows($result);
			pg_free_result($result);
			
			if($rv > 0){
			    $this->drop_sections($data['id']);
				$this->drop_access($data['id']);
				
				$this->save_sections($data['id'], $data);
				$this->create_access($data['id'], $data['group_id']);
			}
			return $rv;
       }

       function getStorySectionById($id, $type){
            $sql = 'select * from public.'.$this->table_name.'_'.$type.' WHERE story_id='.$id;
            $result = pg_query($this->dbconn, $sql);
    		if(!$result){
    			return null;
    		}
    		return $result;
       }
	}
