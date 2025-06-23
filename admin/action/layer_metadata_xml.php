<?php
session_start(['read_and_close' => true]);
require('../incl/const.php');
require('../class/database.php');
require('../class/table.php');
require('../class/table_ext.php');
require('../class/layer.php');
require('../class/qgs_layer.php');
require('../class/layer_metadata.php');

if(empty($_GET['id']) || !is_numeric($_GET['id']) ){
   	http_response_code(400); // Bad Request
   	exit(0);
}

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$obj = new layer_metadata_Class($database->getConn(), SUPER_ADMIN_ID);
$ql_obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);
	
$id     = empty($_GET['id']) ? 0 : intval($_GET['id']);
$lm = [];

$result = $obj->getByLayerId($id);
if($result && (pg_num_rows($result) == 1)){
   	$lm = pg_fetch_assoc($result);
    pg_free_result($result);
}else{
    http_response_code(404); // Not Found
   	exit(0);
}

$result = $ql_obj->getById($id);
$layer = pg_fetch_assoc($result);
pg_free_result($result);

$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
$svc_url = $proto.'://'.$_SERVER['HTTP_HOST'];
$svc_url .= ($layer['proxyfied'] == 't') ? '/mproxy/service' : '/layers/'.$id.'/proxy_qgis.php';

$gmd_pass = ($lm['inspire_conformity'] === 'conformant') ? 'true' : 'false';

header('Content-Type: text/xml');
//header('Content-disposition: attachment; filename="layer'.$id.'.xml"');
// TODO: 
// metadata_organization, metadata_email, metadata_role
// lineage, scope, conformity_result
// coupled_resource
?>
<?xml version="1.0" encoding="UTF-8"?>
<gmd:MD_Metadata xmlns:gmd="http://www.isotc211.org/2005/gmd"
             xmlns:gco="http://www.isotc211.org/2005/gco"
             xmlns:gml="http://www.opengis.net/gml"
             xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
             xsi:schemaLocation="http://www.isotc211.org/2005/gmd
               http://standards.iso.org/iso/19139/20070417/gmd/gmd.xsd">
<gmd:fileIdentifier>
<gco:CharacterString><?=$lm['resource_identifier']?></gco:CharacterString>
</gmd:fileIdentifier>
<gmd:language>
<gco:CharacterString><?=$lm['language']?></gco:CharacterString>
</gmd:language>
<gmd:characterSet>
<gmd:MD_CharacterSetCode codeListValue="<?=$lm['character_set']?>" codeList="http://www.isotc211.org/2005/resources/codeList.xml#MD_CharacterSetCode"/>
</gmd:characterSet>
<gmd:hierarchyLevel>
<gmd:MD_ScopeCode codeListValue="dataset" codeList="http://www.isotc211.org/2005/resources/codeList.xml#MD_ScopeCode"/>
</gmd:hierarchyLevel>
<gmd:contact>
<gmd:CI_ResponsibleParty>
  <gmd:organisationName>
    <gco:CharacterString><?=$lm['cit_responsible_org']?></gco:CharacterString>
  </gmd:organisationName>
  <?php if($lm['cit_responsible_person']){ ?>
      <gmd:individualName><gco:CharacterString><?=$lm['cit_responsible_person']?></gco:CharacterString></gmd:individualName>
  <?php } ?>
  <gmd:role>
    <gmd:CI_RoleCode codeListValue="<?=$lm['cit_role']?>" codeList="http://www.isotc211.org/2005/resources/codeList.xml#CI_RoleCode"/>
  </gmd:role>
</gmd:CI_ResponsibleParty>
</gmd:contact>
<gmd:dateStamp>
<gco:Date><?=$lm['cit_date']?></gco:Date>
</gmd:dateStamp>
<gmd:identificationInfo>
<gmd:MD_DataIdentification>
  <gmd:citation>
    <gmd:CI_Citation>
      <gmd:title>
        <gco:CharacterString><?=$lm['title']?></gco:CharacterString>
      </gmd:title>
      <gmd:date>
        <gmd:CI_Date>
          <gmd:date>
            <gco:Date><?=$lm['cit_date']?></gco:Date>
          </gmd:date>
          <gmd:dateType>
            <gmd:CI_DateTypeCode codeListValue="creation" codeList="http://www.isotc211.org/2005/resources/codeList.xml#CI_DateTypeCode"/>
          </gmd:dateType>
        </gmd:CI_Date>
      </gmd:date>
    </gmd:CI_Citation>
  </gmd:citation>
  <gmd:abstract>
    <gco:CharacterString><?=$lm['abstract']?></gco:CharacterString>
  </gmd:abstract>
  <gmd:resourceMaintenance>
    <gmd:MD_MaintenanceInformation>
        <gmd:maintenanceAndUpdateFrequency>
            <gmd:MD_MaintenanceFrequencyCode
            codeList="http://www.isotc211.org/2005/resources/Codelist/gmxCodelists.xml#MD_MaintenanceFrequencyCode" codeListValue="<?=FREQUENCY_TABLE[$lm['maintenance_frequency']]?>" codeSpace="<?=$lm['maintenance_frequency']?>"><?=FREQUENCY_TABLE[$lm['maintenance_frequency']]?></gmd:MD_MaintenanceFrequencyCode>
        </gmd:maintenanceAndUpdateFrequency>
    </gmd:MD_MaintenanceInformation>
  </gmd:resourceMaintenance>
  <?php if ($lm['keywords']) { ?>
  <gmd:descriptiveKeywords>
      <gmd:MD_Keywords>
          <?php foreach(explode(',', $lm['keywords']) as $kw){ ?>
              <gmd:keyword><gco:CharacterString><?=$kw?></gco:CharacterString></gmd:keyword>
          <?php } ?>
      </gmd:MD_Keywords>
    </gmd:descriptiveKeywords>
  <?php } ?>
  <gmd:purpose>
      <gco:CharacterString><?=$lm['purpose']?></gco:CharacterString>
  </gmd:purpose>

  <?php if ($lm['start_date'] || $lm['end_date']) { ?>
    <gmd:temporalElement>
        <gmd:EX_TemporalExtent>
            <gmd:extent>
            <gml:TimePeriod gml:id="tp1">';
                <?php if($lm['start_date']) { ?> <gml:beginPosition><?=$lm['start_date']?></gml:beginPosition> <?php } ?>
                <?php if($lm['end_date'])   { ?> <gml:endPosition><?=$lm['end_date']?></gml:endPosition> <?php } ?>
            </gml:TimePeriod>
            </gmd:extent>
        </gmd:EX_TemporalExtent>
    </gmd:temporalElement>
  <?php } ?>

  <gmd:extent>
    <gmd:EX_Extent>
      <gmd:geographicElement>
        <gmd:EX_GeographicBoundingBox>
          <gmd:westBoundLongitude>
            <gco:Decimal><?=$lm['west']?></gco:Decimal>
          </gmd:westBoundLongitude>
          <gmd:eastBoundLongitude>
            <gco:Decimal><?=$lm['east']?></gco:Decimal>
          </gmd:eastBoundLongitude>
          <gmd:southBoundLatitude>
            <gco:Decimal><?=$lm['south']?></gco:Decimal>
          </gmd:southBoundLatitude>
          <gmd:northBoundLatitude>
            <gco:Decimal><?=$lm['north']?></gco:Decimal>
          </gmd:northBoundLatitude>
        </gmd:EX_GeographicBoundingBox>
      </gmd:geographicElement>
    </gmd:EX_Extent>
  </gmd:extent>
  <gmd:resourceConstraints>
    <gmd:MD_Constraints>
      <gmd:useLimitation>
        <gco:CharacterString><?=$lm['use_constraints']?></gco:CharacterString>
      </gmd:useLimitation>
    </gmd:MD_Constraints>
  </gmd:resourceConstraints>
  <gmd:spatialRepresentationType>
    <gmd:MD_SpatialRepresentationTypeCode codeListValue="vector" codeList="http://www.isotc211.org/2005/resources/codeList.xml#MD_SpatialRepresentationTypeCode"/>
  </gmd:spatialRepresentationType>
  <gmd:referenceSystemInfo>
    <gmd:MD_ReferenceSystem>
      <gmd:referenceSystemIdentifier>
        <gmd:RS_Identifier>
          <gmd:code>
            <gco:CharacterString>EPSG:<?=$lm['coordinate_system']?></gco:CharacterString>
          </gmd:code>
          <gmd:codeSpace>
            <gco:CharacterString>EPSG</gco:CharacterString>
          </gmd:codeSpace>
        </gmd:RS_Identifier>
      </gmd:referenceSystemIdentifier>
    </gmd:MD_ReferenceSystem>
    <MD_Resolution>
        <distance>
            <gco:Distance uom="meters"><?=$lm['spatial_resolution']?></gco:Distance>
        </distance>
    </MD_Resolution>
  </gmd:referenceSystemInfo>
</gmd:MD_DataIdentification>
</gmd:identificationInfo>
<?php if ($lm['inspire_conformity']) { ?>
  <gmd:metadataConstraints>
    <gmd:MD_LegalConstraints>
    <gmd:accessConstraints>
        <gmd:MD_RestrictionCode codeList="http://standards.iso.org/iso/19139/resources/codelist/gmxCodelists.xml#MD_RestrictionCode" codeListValue="<?=$lm['access_constraints']?>"/>
    </gmd:accessConstraints>
    <gmd:useConstraints>
        <gmd:MD_RestrictionCode codeList="http://standards.iso.org/iso/19139/resources/codelist/gmxCodelists.xml#MD_RestrictionCode" codeListValue="<?=$lm['inspire_conformity']?>"/>
    </gmd:useConstraints>
    <gmd:useLimitation>
        <gmd:CharacterString><?=$lm['use_limitation']?></gmd:CharacterString>
    </gmd:useLimitation>
    </gmd:MD_LegalConstraints>
  </gmd:metadataConstraints>
<?php } ?>
<gmd:dataQualityInfo>
<gmd:DQ_DataQuality>
  <gmd:scope>
    <gmd:DQ_Scope>
      <gmd:level>
        <gmd:MD_ScopeCode codeListValue="dataset" codeList="http://www.isotc211.org/2005/resources/codeList.xml#MD_ScopeCode"/>
      </gmd:level>
    </gmd:DQ_Scope>
  </gmd:scope>
  <gmd:report>
    <gmd:DQ_DomainConsistency>
      <gmd:result>
        <gmd:DQ_ConformanceResult>
          <gmd:specification>
            <gmd:CI_Citation>
              <gmd:title>
                <gco:CharacterString>INSPIRE Data Specification</gco:CharacterString>
              </gmd:title>
            </gmd:CI_Citation>
          </gmd:specification>
          <gmd:explanation>
            <gco:CharacterString>Conformity assessed per INSPIRE Directive.</gco:CharacterString>
          </gmd:explanation>
          <gmd:pass><?=$gmd_pass?></gmd:pass>
        </gmd:DQ_ConformanceResult>
      </gmd:result>
    </gmd:DQ_DomainConsistency>
  </gmd:report>
</gmd:DQ_DataQuality>
</gmd:dataQualityInfo>
<gmd:distributionInfo>
<gmd:MD_Distribution>
  <gmd:transferOptions>
    <gmd:MD_DigitalTransferOptions>
      <gmd:onLine>
        <gmd:CI_OnlineResource>
          <gmd:linkage>
              <gmd:URL><?=$lm['distribution_url']?></gmd:URL>
          </gmd:linkage>
          <gmd:name>
            <gco:CharacterString>Download or access URL</gco:CharacterString>
          </gmd:name>
        </gmd:CI_OnlineResource>
      </gmd:onLine>
    </gmd:MD_DigitalTransferOptions>
  </gmd:transferOptions>
  <gmd:distributionFormat>
    <gmd:MD_Format>
        <gmd:name>
            <gmd:CharacterString><?=$lm['data_format']?></gmd:CharacterString>
        </gmd:name>
    </gmd:MD_Format>
  </gmd:distributionFormat>
</gmd:MD_Distribution>
</gmd:distributionInfo>
</gmd:MD_Metadata>
