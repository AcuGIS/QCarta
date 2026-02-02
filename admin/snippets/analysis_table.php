<?php
	require('../../admin/incl/index_prefix.php');
	require('../../admin/incl/qgis.php');
	require('../../admin/class/table_ext.php');
	require('../../admin/class/layer.php');
    require('../../admin/class/qgs_layer.php');
    require('../../admin/class/layer_query.php');
    require('../../admin/class/layer_report.php');
    require('../../admin/class/layer_metadata.php');
    require('../../admin/class/property_filter.php');

	$user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$lq_obj = new layer_query_Class($database->getConn(), $user_id);
	$layer_queries = $lq_obj->getLayerRows(LAYER_ID);
	
	$lr_obj = new layer_report_Class($database->getConn(), $user_id);
	$layer_reports = $lr_obj->getLayerRows(LAYER_ID);
	
	$pf_obj = new property_filter_Class($database->getConn(), $user_id);
	$prop_filters = $pf_obj->getLayerRows(LAYER_ID);
	
	$ql_obj = new qgs_layer_Class($database->getConn(), $user_id);
	$result = $ql_obj->getById(LAYER_ID);
	$ql_row = pg_fetch_object($result);
	pg_free_result($result);

	$meta_obj = new layer_metadata_Class($database->getConn(), $user_id);
    $result = $meta_obj->getByLayerId(LAYER_ID);
	if($result && (pg_num_rows($result) == 1)){
    	$lm = pg_fetch_assoc($result);
        pg_free_result($result);
	}else{
        $lm = ['id' => 0, 'layer_id' => 0, 'title' =>'', 'abstract' => '', 'purpose' => '', 'keywords' => '', 'cit_date' => '',  'cit_responsible_org' => '', 'cit_responsible_person' => '',
            'cit_role' => '', 'west' => '', 'east' => '', 'south' => '', 'north' => '', 'start_date' => '', 'end_date' => '', 'coordinate_system' => '',
            'use_constraints' => '', 'inspire_point_of_contact' => '', 'inspire_conformity' => '', 'spatial_data_service_url' => '' 
        ];
	}
	
	$wms_url = 'WMS_URL';
	$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
	$access_key = '';
	
	if(str_starts_with($wms_url, '/mproxy/') && ($ql_row->public == 'f')){
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=implode(',', QGIS_LAYERS)?> - Table View</title>
  <!-- Load CSS first -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
  
  <!-- Load JavaScript in correct order -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

  <style>
    .table-container {
      padding: 20px;
      background-color: #f8f9fa;
      min-height: 100vh;
    }
    .table-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      margin-bottom: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .data-table {
      background: white;
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    .loading-spinner {
      text-align: center;
      padding: 50px;
      color: #6c757d;
    }
    .error-message {
      background-color: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 5px;
      margin: 20px 0;
      border: 1px solid #f5c6cb;
    }
    .layer-tabs {
      margin-bottom: 20px;
    }
    .nav-tabs .nav-link {
      border: none;
      color: #6c757d;
      font-weight: 500;
    }
    .nav-tabs .nav-link.active {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
    }
    .export-buttons {
      margin-bottom: 20px;
    }
    .btn-export {
      margin-right: 10px;
      margin-bottom: 10px;
    }
    
    /* DataTables Buttons Styling */
    .dt-buttons {
      margin-bottom: 15px;
    }
    
    .dt-buttons .btn {
      margin-right: 5px;
      margin-bottom: 5px;
    }
    
    .dataTables_wrapper .dt-buttons {
      float: left;
    }
    
    .dataTables_wrapper .dataTables_filter {
      float: right;
    }
  </style>
</head>
<body>
  <div class="table-container">
    <div class="table-header">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1><i class="fas fa-table me-2"></i><?= htmlspecialchars($qgs_title ?: 'Map Data Table') ?></h1>
          <?php if($qgs_abstract): ?>
            <p class="mb-0 opacity-75"><?= htmlspecialchars($qgs_abstract) ?></p>
          <?php endif; ?>
        </div>
        <div class="col-md-4 text-end">
          <a href="index.php" class="btn btn-outline-light">
            <i class="fas fa-map me-1"></i>Back to Map
          </a>
        </div>
      </div>
    </div>

    <!-- Layer Tabs -->
    <div class="layer-tabs">
      <ul class="nav nav-tabs" id="layerTabs" role="tablist">
        <?php 
        $layers = explode(',', QGIS_LAYERS);
        foreach($layers as $index => $layer): 
        ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" 
                  id="tab-<?= $index ?>" 
                  data-bs-toggle="tab" 
                  data-bs-target="#content-<?= $index ?>" 
                  type="button" 
                  role="tab" 
                  data-layer="<?= $layer ?>">
            <i class="fas fa-layer-group me-1"></i><?= htmlspecialchars($layer) ?>
          </button>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="layerTabContent">
      <?php foreach($layers as $index => $layer): ?>
      <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
           id="content-<?= $index ?>" 
           role="tabpanel" 
           data-layer="<?= $layer ?>">
        
                 <!-- Refresh Button -->
         <div class="export-buttons">
           <button class="btn btn-warning btn-export" onclick="refreshData('<?= $layer ?>')">
             <i class="fas fa-sync-alt me-1"></i>Refresh Data
           </button>
         </div>

        <!-- Loading Spinner -->
        <div id="loading-<?= $index ?>" class="loading-spinner">
          <i class="fas fa-spinner fa-spin fa-2x me-2"></i>
          Loading data for <?= htmlspecialchars($layer) ?>...
        </div>

        <!-- Error Message -->
        <div id="error-<?= $index ?>" class="error-message" style="display: none;">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <span id="error-text-<?= $index ?>">Error loading data</span>
        </div>

        <!-- Data Table -->
        <div class="data-table">
          <table id="dataTable-<?= $index ?>" class="table table-striped table-hover" style="width:100%">
            <thead></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    // Configuration
    const layerId = <?=LAYER_ID?>;
    const accessKey = '<?=$access_key?>';
    const WMS_SVC_URL = 'WMS_URL?' + ((accessKey.length === 0) ? '' : 'access_key=' + accessKey);
    const layers = <?= json_encode($layers) ?>;
    
    // DataTables instances
    const dataTables = {};
    
    // Initialize tables when page loads
    $(document).ready(function() {
      // Load data for the first tab
      loadLayerData(layers[0], 0);
      
      // Handle tab changes
      $('#layerTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const layer = $(e.target).data('layer');
        const index = layers.indexOf(layer);
        if (!dataTables[layer]) {
          loadLayerData(layer, index);
        }
      });
    });
    
    function loadLayerData(layer, index) {
      const loadingDiv = $(`#loading-${index}`);
      const errorDiv = $(`#error-${index}`);
      const table = $(`#dataTable-${index}`);
      
      // Show loading
      loadingDiv.show();
      errorDiv.hide();
      
      // Build WFS request URL
      const wfsUrl = `${WMS_SVC_URL}&SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME=${layer}&OUTPUTFORMAT=application/json`;
      
      $.ajax({
        url: wfsUrl,
        method: 'GET',
        dataType: 'json',
        timeout: 30000,
        success: function(data) {
          loadingDiv.hide();
          
          if (data.features && data.features.length > 0) {
            // Extract properties from first feature to get column headers
            const firstFeature = data.features[0];
            const properties = firstFeature.properties;
            const columns = Object.keys(properties);
            
            // Create table headers
            let headerHtml = '<tr>';
            columns.forEach(col => {
              headerHtml += `<th>${col}</th>`;
            });
            headerHtml += '</tr>';
            table.find('thead').html(headerHtml);
            
            // Create table rows
            let bodyHtml = '';
            data.features.forEach(feature => {
              bodyHtml += '<tr>';
              columns.forEach(col => {
                const value = feature.properties[col];
                bodyHtml += `<td>${value !== null && value !== undefined ? htmlEscape(value.toString()) : ''}</td>`;
              });
              bodyHtml += '</tr>';
            });
            table.find('tbody').html(bodyHtml);
            
            // Initialize DataTable
            if (dataTables[layer]) {
              dataTables[layer].destroy();
            }
            
            dataTables[layer] = table.DataTable({
              pageLength: 25,
              responsive: true,
              dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rtip',
              buttons: [
                {
                  extend: 'copy',
                  text: '<i class="fas fa-copy"></i> Copy',
                  className: 'btn btn-secondary btn-sm'
                },
                {
                  extend: 'csv',
                  text: '<i class="fas fa-file-csv"></i> CSV',
                  className: 'btn btn-success btn-sm',
                  title: layer + '_data'
                },
                {
                  extend: 'excel',
                  text: '<i class="fas fa-file-excel"></i> Excel',
                  className: 'btn btn-info btn-sm',
                  title: layer + '_data'
                },
                {
                  extend: 'pdf',
                  text: '<i class="fas fa-file-pdf"></i> PDF',
                  className: 'btn btn-danger btn-sm',
                  title: layer + '_data'
                },
                {
                  extend: 'print',
                  text: '<i class="fas fa-print"></i> Print',
                  className: 'btn btn-warning btn-sm'
                }
              ],
              language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries per page",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)"
              }
            });
            
          } else {
            errorDiv.find('#error-text-' + index).text('No data found for this layer');
            errorDiv.show();
          }
        },
        error: function(xhr, status, error) {
          loadingDiv.hide();
          errorDiv.find('#error-text-' + index).text(`Error loading data: ${error}`);
          errorDiv.show();
          console.error('Error loading layer data:', error);
        }
      });
    }
    
    function refreshData(layer) {
      const index = layers.indexOf(layer);
      if (dataTables[layer]) {
        dataTables[layer].destroy();
        delete dataTables[layer];
      }
      loadLayerData(layer, index);
    }
    

    
    function htmlEscape(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }
  </script>
</body>
</html>
