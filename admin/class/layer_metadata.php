<?php
const CIT_ROLES = ['pointOfContact' => 'Point of Contact', 'originator' => 'Originator', 'publisher' => 'Publisher', 'author' => 'Author', 'custodian' => 'Custodian'];
const INSPIRE_CONFORMITY = ['unknown' => 'Unknown', 'conformant' => 'Conformant', 'nonconformant' => 'Non-conformant'];

const FREQUENCY_TABLE = ['001' => 'continual','002' => 'daily','003' => 'weekly','004' => 'fortnightly','005' => 'monthly','006' => 'quarterly','007' => 'biannually','008' => 'annually','009' => 'asNeeded','010' => 'irregular','011' => 'notPlanned','012' => 'unknown',];

class layer_metadata_Class extends table_Class
{
	function __construct($dbconn, $owner_id) {
		parent::__construct($dbconn, $owner_id, 'layer_metadata');
	}
	
	function create($data){
        $sql = "INSERT INTO public." .$this->table_name."(layer_id,title,abstract,purpose,keywords,language,character_set,maintenance_frequency,cit_date,cit_responsible_org,cit_responsible_person,cit_role,west,east,south,north,start_date,end_date,coordinate_system,spatial_resolution,lineage,scope,conformity_result,metadata_organization,metadata_email,metadata_role,use_constraints,use_limitation,access_constraints,inspire_point_of_contact,inspire_conformity,spatial_data_service_url,distribution_url,data_format,coupled_resource) "."VALUES('".
        $this->cleanData($data['layer_id'])."','".
        $this->cleanData($data['title'])."','".
        $this->cleanData($data['abstract'])."','".
        $this->cleanData($data['purpose'])."','".
        $this->cleanData($data['keywords'])."','".
        $this->cleanData($data['language'])."','".
        $this->cleanData($data['character_set'])."','".
        $this->cleanData($data['maintenance_frequency'])."','".
        $this->cleanData($data['cit_date'])."','".
        $this->cleanData($data['cit_responsible_org'])."','".
        $this->cleanData($data['cit_responsible_person'])."','".
        $this->cleanData($data['cit_role'])."','".
        $this->cleanData($data['west'])."','".
        $this->cleanData($data['east'])."','".
        $this->cleanData($data['south'])."','".
        $this->cleanData($data['north'])."','".
        $this->cleanData($data['start_date'])."','".
        $this->cleanData($data['end_date'])."','".
        $this->cleanData($data['coordinate_system'])."','".
        $this->cleanData($data['spatial_resolution'])."','".
        $this->cleanData($data['lineage'])."','".
        $this->cleanData($data['scope'])."','".
        $this->cleanData($data['conformity_result'])."','".
        $this->cleanData($data['metadata_organization'])."','".
        $this->cleanData($data['metadata_email'])."','".
        $this->cleanData($data['metadata_role'])."','".
        $this->cleanData($data['use_constraints'])."','".
        $this->cleanData($data['use_limitation'])."','".
        $this->cleanData($data['access_constraints'])."','".
        $this->cleanData($data['inspire_point_of_contact'])."','".
        $this->cleanData($data['inspire_conformity'])."','".
        $this->cleanData($data['spatial_data_service_url'])."','".
        $this->cleanData($data['distribution_url'])."','".
        $this->cleanData($data['data_format'])."','".
        $this->cleanData($data['coupled_resource'])."'".
        ") RETURNING id";
        
        $result = pg_query($this->dbconn, $sql);
        if(!$result){
           	return 0;
        }
        $row = pg_fetch_object($result);
        pg_free_result($result);
        
        return ($row) ? $row->id : 0;
    }
    
    function update($data=array()){
        $id = intval($data['id']);

        $sql = 'UPDATE public.'.$this->table_name." set title='".$this->cleanData($data['title']).
        "', abstract='".$this->cleanData($data['abstract']).
        "', purpose='".$this->cleanData($data['purpose']).
        "', keywords='".$this->cleanData($data['keywords']).
        "', language='".$this->cleanData($data['language']).
        "', character_set='".$this->cleanData($data['character_set']).
        "', maintenance_frequency='".$this->cleanData($data['maintenance_frequency']).
        "', cit_date='".$this->cleanData($data['cit_date']).
        "', cit_responsible_org='".$this->cleanData($data['cit_responsible_org']).
        "', cit_responsible_person='".$this->cleanData($data['cit_responsible_person']).
        "', cit_role='".$this->cleanData($data['cit_role']).
        "', west='".$this->cleanData($data['west']).
        "', east='".$this->cleanData($data['east']).
        "', south='".$this->cleanData($data['south']).
        "', north='".$this->cleanData($data['north']).
        "', start_date='".$this->cleanData($data['start_date']).
        "', end_date='".$this->cleanData($data['end_date']).
        "', coordinate_system='".$this->cleanData($data['coordinate_system']).
        "', spatial_resolution='".$this->cleanData($data['spatial_resolution']).
        "', lineage='".$this->cleanData($data['lineage']).
        "', scope='".$this->cleanData($data['scope']).
        "', conformity_result='".$this->cleanData($data['conformity_result']).
        "', metadata_organization='".$this->cleanData($data['metadata_organization']).
        "', metadata_email='".$this->cleanData($data['metadata_email']).
        "', metadata_role='".$this->cleanData($data['metadata_role']).
        "', use_constraints='".$this->cleanData($data['use_constraints']).
        "', use_limitation='".$this->cleanData($data['use_limitation']).
        "', access_constraints='".$this->cleanData($data['access_constraints']).
        "', inspire_point_of_contact='".$this->cleanData($data['inspire_point_of_contact']).
        "', inspire_conformity='".$this->cleanData($data['inspire_conformity']).
        "', spatial_data_service_url='".$this->cleanData($data['spatial_data_service_url']).        
        "', distribution_url='".$this->cleanData($data['distribution_url']).
        "', data_format='".$this->cleanData($data['data_format']).
        "', coupled_resource='".$this->cleanData($data['coupled_resource']).
				"' where id = '".$id."'";
		
		$result = pg_query($this->dbconn, $sql);
		if(!$result){
			return 0;
		}
					
		$rv = pg_affected_rows($result);
		pg_free_result($result);

		return $rv;
    }
    
    function getByLayerId($layer_id){
      $sql  = "select * from public." .$this->table_name;
      $sql .= ' WHERE layer_id='.$layer_id;
      if($this->owner_id != SUPER_ADMIN_ID){
       	$sql .= " AND owner_id = ".$this->owner_id;
      }
      return pg_query($this->dbconn, $sql);
    }
};
?>
