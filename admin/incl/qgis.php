<?php

//https://docs.qgis.org/3.34/en/docs/server_manual/services/wfs.html#wfs-getfeature-outputformat
//https://docs.qgis.org/3.34/en/docs/server_manual/services/wms.html#wms-getmap-format
//https://docs.qgis.org/3.34/en/docs/server_manual/services/wms.html#wms-getprint-format
function format2headers($format, $layer_id){
	switch($format){
		case 'jpg':
		case 'jpeg':
		case 'image/jpeg':
			header("Content-Type: image/jpeg");
			break;
		
		case 'png':
		case 'image/png':
			header("Content-Type: image/png");
			break;
		
		case 'webp':
		case 'image/webp':
			header("Content-Type: image/webp");
			break;
		
		case 'pdf':
		case 'application/pdf':
			header("Content-Type: application/pdf");
			header('Content-Disposition: attachment; filename="layer_'.$layer_id.'.pdf"');	//download as file
			break;
		
		case 'gml2':
		case 'gml3':
		case 'text/xml; subtype=gml/2.1.2':
		case 'text/xml; subtype=gml/3.1.1':
			header("Content-Type: text/xml");
			header('Content-Disposition: attachment; filename="layer_'.$layer_id.'.xml"');	//download as file
			break;

		case 'geojson':
		case 'application/json':
		case 'application/vnd.geo+json':
		case 'application/geo+json':
		case 'application/geo json':
			header("Content-Type: application/json");
			header('Content-Disposition: attachment; filename="layer_'.$layer_id.'.json"');	//download as file
			break;
			
		case 'svg':
		case 'image/svg':
			header("Content-Type: image/svg");
			break;
		
		case 'image/svg+xml':
			header("Content-Type: image/svg+xml");
			break;
		
		case 'application/openlayers':
			header("Content-Type: text/html");
			break;
			
		default:
			header("Content-Type: ".$format);
			break;
	}
}

function layer_get_capabilities($qgs_file){
	$xml_data = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.urlencode($qgs_file).'&SERVICE=WMS&REQUEST=GetCapabilities');
	$xml = simplexml_load_string($xml_data);
	return $xml;
}

function layer_get_features($qgs_file){
	$feats = array();
	
	$xml_data = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.urlencode($qgs_file).'&SERVICE=WFS&REQUEST=GetCapabilities');
	$xml = simplexml_load_string($xml_data);
	
	foreach($xml->FeatureTypeList->FeatureType as $ft){
	   array_push($feats, (string)$ft->Name);
	}
	return $feats;
}

function layer_get_bounding_box($xml, $layer_name){

	foreach($xml->Capability->Layer->Layer as $l){
		if($l->Name == $layer_name){
			foreach($l->BoundingBox as $bb){
				if($bb['SRS'] == 'EPSG:4326'){
					return $bb;
				}
			}
		}
	}
	return null;
}

// merge two bounding boxes to form one
function merge_bbox($a, $b){
	if($a == null){
		return $b;
	}
	
	if($a['minx'] > $b['minx']){ // min left
		$a['minx'] = $b['minx'];
	}
	
	if($a['maxx'] < $b['maxx']){ // max right
		$a['maxx'] = $b['maxx'];
	}
	
	if($a['miny'] > $b['miny']){ // min bottom
		$a['miny'] = $b['miny'];
	}
	
	if($a['maxy'] < $b['maxy']){	// max top
		$a['maxy'] = $b['maxy'];
	}
	
	return $a;
}

function layers_get_bbox($qgs_file, $layers){
	$xml = layer_get_capabilities($qgs_file);
	
	$bbox = null;
	$layers = explode(',', $layers);
	foreach($layers as $l){
		$b = layer_get_bounding_box($xml, $l);
		if($b){
		    $bbox = merge_bbox($bbox, $b);
		}
	}
	return $bbox;
}

?>
