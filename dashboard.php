<?php
    session_start();
    require('admin/incl/const.php');
    require('admin/class/database.php');
    require('admin/class/table.php');
	require('admin/class/table_ext.php');
	require('admin/class/layer.php');
    require('admin/class/qgs_layer.php');
    require('admin/class/qgs.php');
    require('admin/class/dashboard.php');
	require('admin/incl/qgis.php');

    $id = empty($_GET['id']) ? 0 : intval($_GET['id']);

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;
	$obj = new dashboard_Class($database->getConn(), $user_id);

	if($id == 0){
	    http_response_code(404);	//missing id
    	die('Bad request!');
	}
	
	$result = $obj->getById($id);
	if(!$result || (pg_num_rows($result) == 0)){
	    http_response_code(404);	//missing id
    	die('No such dashboard!');
	}
	$dashboard = pg_fetch_object($result);
	pg_free_result($result);
	
	if($dashboard->public == 'f'){
	    if(isset($_SESSION[SESS_USR_KEY])) { 	// local access with login
    	    $allow = $database->check_user_tbl_access('dashboard', $id, $_SESSION[SESS_USR_KEY]->id);
    		if(!$allow){
    			http_response_code(405);	//not allowed
    			die('Sorry, access not allowed!');
    		}
		}else{
    		header('Location: login.php');
    		exit(0);
		}
	}
	
	$ql_obj = new qgs_layer_Class($database->getConn(), $user_id);
	$result = $ql_obj->getById($dashboard->layer_id);
	if(!$result || (pg_num_rows($result) == 0)){
	    http_response_code(404);	//missing id
    	die('No such dashboard layer!');
	}
	$ql_row = pg_fetch_object($result);
	pg_free_result($result);
	
	// check if store is private
    $qgs_obj = new qgs_Class($database->getConn(), $user_id);
    
    $result = $qgs_obj->getById($ql_row->store_id);
    if(!$result || (pg_num_rows($result) == 0)){
        http_response_code(404);	//missing id
        die('No such dashboard layer!');
    }
    $store_row = pg_fetch_object($result);
    pg_free_result($result);
	
	$wms_url = '/layers/'.$dashboard->layer_id.'/proxy_qgis.php';
	$access_key = '';
	
	// Check if layer is private and needs authentication
	if(($ql_row->public == 'f') || ($store_row->public == 'f')){
	    if(empty($_GET['access_key'])){
			// if dashboard is public, but layer is not, use owner or logged user secret_key to generate access key
			if(empty($_SESSION[SESS_USR_KEY])){
			    $user_row = $database->get('public.user', 'id='.$dashboard->owner_id);
				$secret_key = $user_row['secret_key'];
			}else{
			    $secret_key = $_SESSION[SESS_USR_KEY]->secret_key;
			}

			$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
			$content = file_get_contents($proto.'://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key='.$secret_key.'&ip='.$_SERVER['REMOTE_ADDR']);
  		    $auth = json_decode($content);
      		$access_key = $auth->access_key; // Store access key for JavaScript			
		}else{
		    $access_key = $_GET['access_key'];
		}
		if($ql_row->public == 'f'){
		    $wms_url .= '?access_key='.$access_key;
	    }
	}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<meta name="robots" content="noindex,nofollow">
<title>QCarta Dashboard</title>

<link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon.ico">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  :root{ --bg:#f6f7fb; --panel:#fff; --muted:#6b7280; --text:#1f2937; --accent:#2563eb;
         --shadow:0 10px 24px rgba(0,0,0,.08); --radius:14px; --cols:12; --g:8px; --row:120px; }
  *{box-sizing:border-box} html,body{height:100%} body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial}
  .topbar{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;background:#fff;box-shadow:var(--shadow);position:sticky;top:0;z-index:10}
  .btn{border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px;background:#fff;cursor:pointer}
  .btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
  .wrap{display:grid;grid-template-columns:1fr;gap:16px;padding:16px}
  .sidebar{background:#fff;border-radius:14px;box-shadow:var(--shadow);padding:16px;height:calc(100vh - 88px);position:sticky;top:72px;overflow:auto}
  .picker{padding:12px;border:1px dashed #e5e7eb;border-radius:12px;background:#fafafa;cursor:pointer;margin-bottom:10px}
  .canvas{position:relative;min-height:calc(100vh - 88px);border-radius:12px;outline:1px dashed #eef0f4}
  .item{position:absolute;background:#fff;border-radius:0px;box-shadow:0 4px 12px rgba(0,0,0,0.15);overflow:hidden;border-top:2px solid #d97706}
  .item[data-kind="map"]{overflow:visible}
  .card-header{display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-bottom:1px solid #eef0f4;cursor:move}
  .title{font-weight:600;padding:2px 4px;border-radius:4px;transition:background 0.2s;font-size:12px}
  .title:hover{background:#f3f4f6}
  .title:focus{background:#fff;outline:2px solid var(--accent);outline-offset:1px}
  .tools{display:flex;gap:4px;align-items:center}
  .tbtn{border:none;background:#f3f4f6;width:30px;height:30px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;color:#6b7280;transition:all 0.2s}
  .tbtn.maximize{position:relative}
  .tbtn.maximize::before{content:'⛶';font-size:16px}
  .tbtn.maximize.maximized::before{content:'⛷';font-size:16px}
  .item.maximized{position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:1000;border-radius:0}
  .item.maximized .card-header{background:#fff;border-bottom:1px solid #eef0f4}
  .tbtn:hover{background:#e5e7eb;color:#374151}
  .body{height:calc(100% - 40px);overflow:auto}
  .item[data-kind="map"] .body{overflow:visible}
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
	<div><strong><?=$dashboard->name?></strong><br><?=$dashboard->description?></div>
        

	<div>
		<button class="btn btn-primary" id="exportPdfBtn" title="Export Dashboard to PDF">
			📄 Export PDF
		</button>
	</div>
</div>
<div class="wrap">
  <main class="canvas" id="canvas"></main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
// ------- constants with PHP authentication -------
const canvas = document.getElementById('canvas');
const DASHBOARD_EDITOR = false;
const DASHBOARD_ID = <?=$id?>;
const layerId = <?=$dashboard->layer_id?>;
const BASE = '<?=$wms_url?>';

// Store access key from PHP (same as index.php and analysis.php)
const accessKey = '<?=$access_key?>';
const accessKeyParam = (accessKey == '') ? '' : '?access_key=<?=$access_key?>';
const WMS_SVC_URL = '<?=$wms_url?>';

// Use the correct store-based endpoints (same as index.php metadata section)
const storeId = '<?=$ql_row->store_id?>';
const WMS_BASE = `/stores/${storeId}/wms${accessKeyParam}`;
const WFS_BASE = `/stores/${storeId}/wfs${accessKeyParam}`;
const WMS_FORCE = new URL(location.href).searchParams.get('wmsLayer') || '';
const dashboard_config = <?php if(is_file(DATA_DIR.'/dashboards/'.$id.'/config.json')) { readfile(DATA_DIR.'/dashboards/'.$id.'/config.json'); }else{ echo('null'); }  ?>;

// ------- PDF Export Function -------
function exportToPDF() {
  // Show loading state
  const exportBtn = document.getElementById('exportPdfBtn');
  const originalText = exportBtn.innerHTML;
  exportBtn.innerHTML = '⏳ Taking Screenshot...';
  exportBtn.disabled = true;
  
  // Hide the PDF button temporarily
  exportBtn.style.display = 'none';
  
  // Wait a moment for any animations to settle
  setTimeout(() => {
    // Capture the entire dashboard area
    const dashboardArea = document.querySelector('.wrap');
    
    // Configure html2pdf options for screenshot
    const opt = {
      margin: [0, 0, 0, 0],
      filename: `dashboard-<?=$dashboard->name?>-${new Date().toISOString().split('T')[0]}.pdf`,
      image: { type: 'jpeg', quality: 0.95 },
      html2canvas: { 
        scale: 0.8,
        useCORS: true,
        letterRendering: true,
        allowTaint: true,
        backgroundColor: '#f6f7fb',
        scrollX: 0,
        scrollY: 0,
        width: dashboardArea.scrollWidth,
        height: dashboardArea.scrollHeight
      },
      jsPDF: { 
        unit: 'mm', 
        format: 'a4', 
        orientation: 'landscape' 
      }
    };
    
    // Generate PDF from the dashboard area
    html2pdf().set(opt).from(dashboardArea).save().then(() => {
      // Restore button state
      exportBtn.style.display = 'block';
      exportBtn.innerHTML = originalText;
      exportBtn.disabled = false;
    }).catch((error) => {
      console.error('PDF generation failed:', error);
      alert('Failed to generate PDF. Please try again.');
      
      // Restore button state
      exportBtn.style.display = 'block';
      exportBtn.innerHTML = originalText;
      exportBtn.disabled = false;
    });
  }, 500); // Wait 0.5 seconds for any animations
}

// Add event listener for PDF export button
document.getElementById('exportPdfBtn').addEventListener('click', exportToPDF);

// Maximize functionality
function toggleMaximize(item) {
  const isMaximized = item.classList.contains('maximized');
  const maximizeBtn = item.querySelector('.tbtn.maximize');
  
  if (isMaximized) {
    // Restore original position and size
    item.classList.remove('maximized');
    maximizeBtn.classList.remove('maximized');
    
    // Restore original styles
    item.style.position = item.dataset.originalPosition || 'absolute';
    item.style.top = item.dataset.originalTop || '0px';
    item.style.left = item.dataset.originalLeft || '0px';
    item.style.width = item.dataset.originalWidth || '300px';
    item.style.height = item.dataset.originalHeight || '200px';
    item.style.zIndex = item.dataset.originalZIndex || '1';
  } else {
    // Get computed styles to store original values
    const computedStyle = window.getComputedStyle(item);
    
    // Store original position and size
    item.dataset.originalPosition = computedStyle.position;
    item.dataset.originalTop = computedStyle.top;
    item.dataset.originalLeft = computedStyle.left;
    item.dataset.originalWidth = computedStyle.width;
    item.dataset.originalHeight = computedStyle.height;
    item.dataset.originalZIndex = computedStyle.zIndex;
    
    // Maximize to fullscreen
    item.classList.add('maximized');
    maximizeBtn.classList.add('maximized');
  }
}

// Add maximize button to existing items and handle clicks
function addMaximizeButtons() {
  const items = document.querySelectorAll('.item');
  items.forEach(item => {
    const tools = item.querySelector('.tools');
    if (tools && !tools.querySelector('.maximize')) {
      const maximizeBtn = document.createElement('button');
      maximizeBtn.className = 'tbtn maximize';
      maximizeBtn.title = 'Maximize';
      tools.appendChild(maximizeBtn);
    }
  });
}

// Monitor for new items and add maximize buttons
const observer = new MutationObserver(function(mutations) {
  mutations.forEach(function(mutation) {
    if (mutation.type === 'childList') {
      mutation.addedNodes.forEach(function(node) {
        if (node.nodeType === 1) { // Element node
          if (node.classList && node.classList.contains('item')) {
            addMaximizeButtons();
          } else if (node.querySelector && node.querySelector('.item')) {
            addMaximizeButtons();
          }
        }
      });
    }
  });
});

document.addEventListener('DOMContentLoaded', function() {
  // Add maximize buttons to existing items
  addMaximizeButtons();
  
  // Start observing for new items
  observer.observe(document.body, {
    childList: true,
    subtree: true
  });
  
  // Handle maximize button clicks
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('maximize') || e.target.closest('.maximize')) {
      e.preventDefault();
      e.stopPropagation();
      const item = e.target.closest('.item');
      if (item) {
        toggleMaximize(item);
      }
    }
  });
});
</script>
<script src="admin/assets/dist/js/edit_dashboard.js"></script>
</body>
</html>
