<?php
class store_relation_Class {
  private $dbconn;
  private $owner_id;
  private $table = 'public.store_relation';
  
  function __construct($dbconn, $owner_id){
      $this->dbconn=$dbconn;
      $this->owner_id = intval($owner_id);
  }
  
  private function clean($s){
     return pg_escape_string($this->dbconn,(string)$s);
  }
  
  function create($id, $rows){
      foreach($rows as $r){
        $sql = "INSERT INTO {$this->table} (store_id,name,parent_layer,parent_field,child_layer,child_field,child_list_fields,owner_id)
                VALUES ($id,'".
                $this->clean($r['name'])."','".
                $this->clean($r['parent_layer'])."','".
                $this->clean($r['parent_field'])."','".
                $this->clean($r['child_layer'])."','".
                $this->clean($r['child_field'])."','".
                $this->clean($r['child_list_fields'] ?? '')."',".
                $this->owner_id.")";
          pg_query($this->dbconn, $sql);
      }
  }
  
  function replaceAllForLayer($id, $rows){
    $this->delete($id);
    $this->create($id, $rows);
  }
  
  function delete($id){
    $result = pg_query($this->dbconn, "DELETE FROM {$this->table} WHERE store_id={$id}");  
    if(!$result){
		return 0;
	}

	$rv = pg_affected_rows($result) > 0;
	pg_free_result($result);

	return $rv;
  }

  function getByStoreId($store_id){
    return pg_query($this->dbconn, "SELECT * FROM {$this->table} WHERE store_id=".intval($store_id)." ORDER BY id ASC");
  }
}
