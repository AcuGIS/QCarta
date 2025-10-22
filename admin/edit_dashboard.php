<?php
    session_start();
    require('incl/const.php');
    require('class/database.php');
    require('class/table.php');
	require('class/table_ext.php');
	require('class/layer.php');
    require('class/qgs_layer.php');
    require('class/dashboard.php');
	require('incl/qgis.php');

    $id = empty($_GET['id']) ? 0 : intval($_GET['id']);

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$obj = new dashboard_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);

	if(($id > 0) && !$obj->isOwnedByUs($id)){   // if not owned by user
    	http_response_code(405);	//not allowed
    	die('Sorry, access not allowed!');
	}
	
	$result = $obj->getById($id);
	$dashboard = pg_fetch_object($result);
	pg_free_result($result);
	
	$ql_obj = new qgs_layer_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
	$result = $ql_obj->getById($dashboard->layer_id);
	$ql_row = pg_fetch_object($result);
	pg_free_result($result);
	
	$wms_url = '/layers/'.$dashboard->layer_id.'/proxy_qgis.php';
	$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
	$access_key = '';
	
	// Check if layer is private and needs authentication
	if($ql_row->public == 'f'){
	    if(empty($_GET['access_key'])){
			$content = file_get_contents($proto.'://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key='.$_SESSION[SESS_USR_KEY]->secret_key.'&ip='.$_SERVER['REMOTE_ADDR']);
  		    $auth = json_decode($content);
      		$access_key = $auth->access_key; // Store access key for JavaScript			
		}else{
		    $access_key = $_GET['access_key'];
		}
		$wms_url .= '?access_key='.$access_key;
	}
?>
<!doctype html>
<html lang="en">
<head>
<?php include("incl/meta.php"); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  :root{ --bg:#f6f7fb; --panel:#fff; --muted:#6b7280; --text:#1f2937; --accent:#2563eb;
         --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px; --cols:12; --g:8px; --row:120px; }
  *{box-sizing:border-box} html,body{height:100%} body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial}
  .topbar{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;background:#fff;box-shadow:var(--shadow);position:sticky;top:0;z-index:10}
  .btn{border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px;background:#fff;cursor:pointer}
  .btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
  .wrap{display:grid;grid-template-columns:320px 1fr;gap:16px;padding:16px}
  .sidebar{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:16px;height:calc(100vh - 88px);position:sticky;top:72px;overflow:auto}
  .picker{padding:12px;border:1px dashed #e5e7eb;border-radius:12px;background:#fafafa;cursor:pointer;margin-bottom:10px}
  .canvas{position:relative;min-height:calc(100vh - 88px);border-radius:12px;outline:1px dashed #eef0f4}
  .item{position:absolute;background:#fff;border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #eef0f4;cursor:move}
  .title{font-weight:600;padding:2px 4px;border-radius:4px;transition:background 0.2s}
  .title:hover{background:#f3f4f6}
  .title:focus{background:#fff;outline:2px solid var(--accent);outline-offset:1px}
  .tools{display:flex;gap:4px;align-items:center}
  .tbtn{border:none;background:#f3f4f6;width:30px;height:30px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:#6b7280;transition:all 0.2s}
  .tbtn:hover{background:#e5e7eb;color:#374151}
  .body{height:calc(100% - 52px);overflow:auto}
  .pad{padding:12px}
  .resize{position:absolute;right:6px;bottom:6px;width:16px;height:16px;cursor:nwse-resize;background:
    linear-gradient(135deg,transparent 50%,#cbd5e1 50%),linear-gradient(45deg,transparent 50%,#cbd5e1 50%);background-size:8px 8px;background-repeat:no-repeat;background-position:left bottom,right top;border-radius:4px}
  .leaflet-container{height:100%;width:100%}
  .leaflet-popup-content{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.4}
  .leaflet-popup-content-wrapper{border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
  .leaflet-popup-tip{background:#fff;border:1px solid #e5e7eb}
  #dbg{display:none;margin:8px 16px;padding:8px 12px;border-radius:8px;border:1px solid #ffeeba;border-left:4px solid #ffecb5;background:#fff3cd;color:#856404;font:12px/1.4 system-ui}
  /* modal */
  .backdrop{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:9999}
  .modal{width:min(820px,calc(100% - 24px));background:#fff;border-radius:14px;box-shadow:var(--shadow);overflow:hidden;position:relative;z-index:10000}
  .mh{padding:12px 16px;border-bottom:1px solid #eef0f4;font-weight:600}
  .mb{padding:14px 16px;max-height:70vh;overflow:auto}
  .mf{padding:12px 16px;border-top:1px solid #eef0f4;display:flex;gap:8px;justify-content:flex-end}
  .row{display:grid;grid-template-columns:200px 1fr;gap:10px;align-items:center;margin-bottom:10px}
  .row input,.row select,.row textarea{width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:8px;font:inherit}
  .help{font-size:12px;color:#6b7280}
  .column-item{display:flex;align-items:center;gap:8px;padding:6px 8px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:4px;cursor:move;transition:all 0.2s}
  .column-item:hover{background:#f3f4f6;border-color:#d1d5db}
  .column-item.dragging{opacity:0.5;transform:rotate(2deg)}
  .column-item .drag-handle{color:#9ca3af;font-size:14px;cursor:grab}
  .column-item .drag-handle:active{cursor:grabbing}
  .column-item .field-name{flex:1;font-size:12px;font-weight:500}
  .column-item .remove-btn{color:#ef4444;cursor:pointer;font-size:12px;padding:2px 4px;border-radius:4px;transition:background 0.2s}
  .column-item .remove-btn:hover{background:#fee2e2}
</style>
</head>
<body>
<div class="topbar">
  <div><strong>QCarta Dashboard</strong></div>
  <div>
    <button class="btn" id="clearBtn">Clear</button>
    <button class="btn" id="loadBtn">Load</button>
    <button class="btn btn-primary" id="saveBtn">Save</button>
  </div>
</div>
<div id="dbg"></div>

<div class="wrap">
  <aside class="sidebar">
    <div class="picker" data-kind="map"><i class="bi bi-geo-alt"></i> <strong>Map</strong><div class="help">Add Map</div></div>
    <div class="picker" data-kind="chart"><i class="bi bi-bar-chart"></i> <strong>Chart</strong><div class="help">Add Chart</div></div>
    <div class="picker" data-kind="table"><i class="bi bi-table"></i> <strong>Table</strong><div class="help">Add Table</div></div>
    <div class="picker" data-kind="legend"><i class="bi bi-list-ul"></i> <strong>Legend</strong><div class="help">Add Legend</div></div>
    <div class="picker" data-kind="counter"><i class="bi bi-123"></i> <strong>Counter</strong><div class="help">Count, Sum, or Average of a column</div></div>
    <div class="picker" data-kind="text"><i class="bi bi-type"></i> <strong>Text</strong><div class="help">Add Text Area</div></div>
    <hr style="margin:16px 0;border:none;border-top:1px solid #eef0f4">
    <div class="help"></div>
  </aside>
  <main class="canvas" id="canvas"></main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
<script>
// ------- constants with PHP authentication -------
const canvas = document.getElementById('canvas');
const DASHBOARD_EDITOR = true;
const DASHBOARD_ID = <?=$id?>;
const layerId = <?=$dashboard->layer_id?>;
const BASE = `/layers/${layerId}/proxy_qgis.php`;

// Store access key from PHP (same as index.php and analysis.php)
const accessKey = '<?=$access_key?>';
const WMS_SVC_URL = '<?=$wms_url?>';

// Use the correct store-based endpoints (same as index.php metadata section)
const storeId = '<?=$ql_row->store_id?>';
const WMS_BASE = `/stores/${storeId}/wms`;
const WFS_BASE = `/stores/${storeId}/wfs`;
const WMS_FORCE = new URL(location.href).searchParams.get('wmsLayer') || '';
const dashboard_config = <?php if(is_file(DATA_DIR.'/dashboards/'.$id.'/config.json')) { readfile(DATA_DIR.'/dashboards/'.$id.'/config.json'); }else{ echo('null'); }  ?>;
</script>
<script src="assets/dist/js/edit_dashboard.js"></script>
</body>
</html>
