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

?>