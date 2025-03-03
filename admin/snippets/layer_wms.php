<?php
	include('../../admin/incl/index_prefix.php');
	include('../../admin/incl/qgis.php');
	include('../../admin/class/table_ext.php');
	include('../../admin/class/layer.php');
	include('../../admin/class/qgs_layer.php');

	$_GET = array_change_key_case($_GET, CASE_LOWER); 
	
	$format = null;

	if(!empty($_GET['request']) && $_GET['request'] == 'GetCapabilities') {
	   $format = 'text/xml';
	}else{
	   http_response_code(400);	// Bad Request
	   die(400);
	}

	$user_id = (IS_PUBLIC || !empty($_GET['access_key'])) ? SUPER_ADMIN_ID : $_SESSION[SESS_USR_KEY]->id;

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$obj = new qgs_layer_Class($database->getConn(), $user_id);
	
	$result = $obj->getById(LAYER_ID);
	$row = pg_fetch_object($result);
	pg_free_result($result);
	
	header("Content-Type: text/xml");
	
	$content = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.0&map='.QGIS_FILENAME_ENCODED.'&SERVICE=WMS&REQUEST=GetCapabilities');
	$content = preg_replace('/MAP=[^&"]*(&amp;)?/i', '', $content);
	

	$out_proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
	
	//update layer names and URI
	$layer_uri = '/layers/'.$row->id.'/proxy_qgis.php?';
	if($row->proxyfied == 't'){
	    $layer_uri = '/mproxy/service?';
	    if($row->proxyfied == 't'){
			$content = str_replace('LAYER=', 'LAYERS='.$row->name.'.', $content);
		}
		if(isset($_GET['access_key'])){
            $layer_uri .= 'access_key='.$_GET['access_key'].'&amp;';
        }
	}else{
	   if(isset($_GET['access_key'])){
	       $layer_uri .= 'access_key='.$_GET['access_key'].'&amp;';
		}
	}

    $content = str_replace('http://localhost/cgi-bin/qgis_mapserv.fcgi?',  $out_proto.'://'.$_SERVER['HTTP_HOST'].$layer_uri, $content);

    # remove layers not enabled in app
    $rm_layers = [];
    
    $xml = simplexml_load_string($content);
    foreach($xml->Capability->Layer->Layer as $l){
        if(!str_contains($row->layers, (string)$l->Name)){
            array_push($rm_layers, $l);
        }else if($row->proxyfied == 't'){
            if($row->exposed == 't') {
                $l->Name = $row->name.'.'.(string)$l->Name;
            }
        }
    }

    foreach($rm_layers as $l){
        $dom = dom_import_simplexml($l);
        $dom->parentNode->removeChild($dom);
    }
    
    echo $xml->asXML();
?>
