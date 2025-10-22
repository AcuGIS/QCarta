<?php
	require('../../admin/incl/index_prefix.php');
	require('../../admin/class/table_ext.php');
	require('../../admin/incl/qgis.php');
	require('../../admin/class/layer.php');
    require('../../admin/class/qgs_layer.php');

	$user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		
	$ql_obj = new qgs_layer_Class($database->getConn(), $user_id);
	$result = $ql_obj->getById(LAYER_ID);
	$ql_row = pg_fetch_object($result);
	pg_free_result($result);
	
	$wms_url = '/mproxy/service';
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
	
	$qgis_file = urldecode(QGIS_FILENAME_ENCODED);
	$xml = simplexml_load_file($qgis_file);
	$proj_meta = $xml->xpath('/qgis/projectMetadata');
	if(is_array($proj_meta)){
	   $proj_meta = $proj_meta[0];
	   $qgs_title = (string)$proj_meta->title;
	   $qgs_abstract = (string)$proj_meta->abstract;
	}else{
       $qgs_title = "";
       $qgs_abstract = "";
	}

	$chart_configs = json_decode(file_get_contents(DATA_DIR.'/stores/'.$ql_row->store_id.'/charts.json'), true);
	$plotly_defaults_file = DATA_DIR.'/stores/'.$ql_row->store_id.'/plotly_defaults.json';
	$wms_layers = explode(',', 'Bee_Map.Fields,Bee_Map.Apiary,Bee_Map.Tracks');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=implode(',', QGIS_LAYERS)?> - Data Analysis</title>
  <!-- Load CSS first -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
  <link rel="stylesheet" href="../../assets/dist/css/analysis.css">
  
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
  
  <!-- Chart Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
  <script>
    // Configuration
    const layerId = <?=LAYER_ID?>;
    const accessKey = '<?=$access_key?>';
    const WMS_SVC_URL = '/mproxy/service?' + ((accessKey.length === 0) ? '' : 'access_key=' + accessKey);
    const layers = <?= json_encode($wms_layers) ?>;
    
    // DataTables instances
    const dataTables = {};
    
    // Store layer data
    const layerData = {};
    
    // Chart instances
    const charts = {};
    const plotlyCharts = {};
    
    // Pivot configuration
    const pivotConfig = {};

    // Layer switching function
    function switchLayer(layerIndex) {
      // Hide all layer contents
      const allContents = document.querySelectorAll('.layer-content');
      allContents.forEach(content => {
        content.style.display = 'none';
        content.classList.remove('active');
      });
      
      // Show selected layer content
      const selectedContent = document.getElementById('content-' + layerIndex);
      if (selectedContent) {
        selectedContent.style.display = 'block';
        selectedContent.classList.add('active');
      }
      
      // Get the layer name and trigger data loading
      const layerName = selectedContent ? selectedContent.getAttribute('data-layer') : null;
      if (layerName && typeof refreshData === 'function') {
        // Trigger data refresh for the new layer
        refreshData(layerName);
      }
      
      // Reset view buttons to Table View when switching layers
      showTableView(layerIndex);
    }

    // Get current layer index from dropdown
    function getCurrentLayerIndex() {
      const dropdown = document.getElementById('layerSelect');
      return dropdown ? parseInt(dropdown.value) : 0;
    }

    // Refresh data for current layer
    function refreshDataForCurrentLayer() {
      const layerIndex = getCurrentLayerIndex();
      const selectedContent = document.getElementById('content-' + layerIndex);
      const layerName = selectedContent ? selectedContent.getAttribute('data-layer') : null;
      
      if (layerName && typeof refreshData === 'function') {
        refreshData(layerName);
      }
    }

    // Export data function
    function exportData(format) {
      const layerIndex = getCurrentLayerIndex();
      const layerName = document.getElementById('content-' + layerIndex)?.getAttribute('data-layer');
      
      console.log('Export requested:', format, 'for layer:', layerName, 'index:', layerIndex);
      console.log('Available dataTables:', Object.keys(dataTables));
      
      // Try to find the DataTable by layer name first (as used in analysis.js)
      if (layerName && dataTables[layerName]) {
        console.log('Found DataTable for layer name:', layerName);
        const dt = dataTables[layerName];
        
        // Try clicking the actual button elements directly
        const buttons = document.querySelectorAll(`#dataTable-${layerIndex}_wrapper .dt-buttons button`);
        console.log('Available button elements:', Array.from(buttons).map(btn => btn.textContent.trim()));
        
        // Find the button by text content and click it
        const buttonTexts = ['Copy', 'CSV', 'Excel', 'PDF', 'Print'];
        const formatMap = {
          'copy': 'Copy',
          'csv': 'CSV', 
          'excel': 'Excel',
          'pdf': 'PDF',
          'print': 'Print'
        };
        const formatIndex = buttonTexts.indexOf(formatMap[format.toLowerCase()]);
        
        if (formatIndex >= 0 && buttons[formatIndex]) {
          console.log(`Clicking ${format} button (index ${formatIndex})`);
          buttons[formatIndex].click();
          console.log('Button clicked successfully');
        } else {
          console.log(`Could not find button for format: ${format}`);
        }
      } else if (dataTables[layerIndex]) {
        console.log('Found DataTable for layer index:', layerIndex);
        const dt = dataTables[layerIndex];
        
        try {
          switch(format) {
            case 'copy':
              dt.button('copy').trigger();
              break;
            case 'csv':
              dt.button('csv').trigger();
              break;
            case 'excel':
              dt.button('excel').trigger();
              break;
            case 'pdf':
              dt.button('pdf').trigger();
              break;
            case 'print':
              dt.button('print').trigger();
              break;
          }
          console.log('Button triggered successfully for', format);
        } catch (error) {
          console.error('Error triggering button:', error);
        }
      } else {
        console.log('DataTable not found for layer', layerIndex, 'or layer name', layerName);
        console.log('Available dataTables:', dataTables);
      }
    }

    // Hide original DataTable buttons after initialization
    function hideOriginalButtons() {
      // Hide the original DataTable buttons that appear below the table
      const originalButtons = document.querySelectorAll('.dt-buttons');
      originalButtons.forEach(buttonGroup => {
        buttonGroup.style.display = 'none';
      });
    }

    // Monitor for new DataTable buttons and hide them
    function monitorAndHideButtons() {
      const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(function(node) {
              if (node.nodeType === 1) { // Element node
                if (node.classList && node.classList.contains('dt-buttons')) {
                  node.style.display = 'none';
                }
                // Also check for buttons added to existing elements
                const buttons = node.querySelectorAll && node.querySelectorAll('.dt-buttons');
                if (buttons) {
                  buttons.forEach(buttonGroup => {
                    buttonGroup.style.display = 'none';
                  });
                }
              }
            });
          }
        });
      });
      
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    }

    // Override the existing view functions to work with centralized buttons
    const originalShowTableView = window.showTableView;
    const originalShowChartView = window.showChartView;
    const originalShowPivotView = window.showPivotView;

    function showTableView(layerIndex) {
      // Call original function if it exists
      if (originalShowTableView) {
        originalShowTableView(layerIndex);
      }
      
      // Hide original DataTable buttons
      hideOriginalButtons();
      
      // Update button classes
      const tableBtn = document.querySelector('#viewButtons button[onclick*="showTableView"]');
      const chartBtn = document.querySelector('#viewButtons button[onclick*="showChartView"]');
      const pivotBtn = document.querySelector('#viewButtons button[onclick*="showPivotView"]');
      
      if (tableBtn) tableBtn.className = 'btn btn-primary active';
      if (chartBtn) chartBtn.className = 'btn btn-outline-primary';
      if (pivotBtn) pivotBtn.className = 'btn btn-outline-primary';
    }

    function showChartView(layerIndex) {
      // Call original function if it exists
      if (originalShowChartView) {
        originalShowChartView(layerIndex);
      }
      
      // Update button classes
      const tableBtn = document.querySelector('#viewButtons button[onclick*="showTableView"]');
      const chartBtn = document.querySelector('#viewButtons button[onclick*="showChartView"]');
      const pivotBtn = document.querySelector('#viewButtons button[onclick*="showPivotView"]');
      
      if (tableBtn) tableBtn.className = 'btn btn-outline-primary';
      if (chartBtn) chartBtn.className = 'btn btn-primary active';
      if (pivotBtn) pivotBtn.className = 'btn btn-outline-primary';
    }

    function showPivotView(layerIndex) {
      // Call original function if it exists
      if (originalShowPivotView) {
        originalShowPivotView(layerIndex);
      }
      
      // Update button classes
      const tableBtn = document.querySelector('#viewButtons button[onclick*="showTableView"]');
      const chartBtn = document.querySelector('#viewButtons button[onclick*="showChartView"]');
      const pivotBtn = document.querySelector('#viewButtons button[onclick*="showPivotView"]');
      
      if (tableBtn) tableBtn.className = 'btn btn-outline-primary';
      if (chartBtn) chartBtn.className = 'btn btn-outline-primary';
      if (pivotBtn) pivotBtn.className = 'btn btn-primary active';
    }

    // Hide original buttons on page load and start monitoring
    document.addEventListener('DOMContentLoaded', function() {
      hideOriginalButtons();
      monitorAndHideButtons();
    });
  </script>
  
</head>
<body>
  <div class="table-container">
    <div class="table-header">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1><i class="fas fa-table-cells me-2"></i><?= htmlspecialchars($qgs_title ?: 'Data Analysis') ?></h1>
          <?php if($qgs_abstract): ?>
            <p class="mb-0 opacity-75"><?= htmlspecialchars($qgs_abstract) ?></p>
          <?php endif; ?>
        </div>
                 <div class="col-md-4 text-end">
           <a href="index.php" class="btn btn-outline-secondary">
             <i class="fas fa-map me-1"></i>Back to Map
           </a>
         </div>
      </div>
    </div>

    <!-- Layer Selection and View Controls -->
    <div class="layer-controls mb-3">
      <div class="d-flex align-items-center flex-wrap gap-2">
        <!-- Layer Selection -->
        <div class="d-flex align-items-center">
          <label for="layerSelect" class="form-label mb-0 me-2">
            <i class="fas fa-layer-group me-1"></i>Layer:
          </label>
          <select id="layerSelect" class="form-select form-select-sm" style="width: 180px;" onchange="switchLayer(this.value)">
            <?php 
            foreach($wms_layers as $index => $layer): 
            ?>
            <option value="<?= $index ?>" <?= $index === 0 ? 'selected' : '' ?>>
              <?= htmlspecialchars($layer) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <!-- View Buttons -->
        <div class="btn-group btn-group-sm" role="group" id="viewButtons">
          <button type="button" class="btn btn-primary active" onclick="showTableView(getCurrentLayerIndex())">
            <i class="fas fa-table me-1"></i>Table
          </button>
          <button type="button" class="btn btn-outline-primary" onclick="showChartView(getCurrentLayerIndex())">
            <i class="fas fa-chart-bar me-1"></i>Chart
          </button>
          <button type="button" class="btn btn-outline-primary" onclick="showPivotView(getCurrentLayerIndex())">
            <i class="fas fa-table-cells me-1"></i>Pivot
          </button>
        </div>
        
        <!-- Refresh Button -->
        <button class="btn btn-warning btn-sm" onclick="refreshDataForCurrentLayer()">
          <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
        
        <!-- Export Buttons -->
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-secondary" onclick="exportData('copy')">
            <i class="fas fa-copy me-1"></i>Copy
          </button>
          <button type="button" class="btn btn-success" onclick="exportData('csv')">
            <i class="fas fa-file-csv me-1"></i>CSV
          </button>
          <button type="button" class="btn btn-info" onclick="exportData('excel')">
            <i class="fas fa-file-excel me-1"></i>Excel
          </button>
          <button type="button" class="btn btn-danger" onclick="exportData('pdf')">
            <i class="fas fa-file-pdf me-1"></i>PDF
          </button>
          <button type="button" class="btn btn-warning" onclick="exportData('print')">
            <i class="fas fa-print me-1"></i>Print
          </button>
        </div>
      </div>
    </div>

    <!-- Layer Content -->
    <div id="layerContent">
      <?php foreach($wms_layers as $index => $layer): ?>
      <div class="layer-content <?= $index === 0 ? 'active' : '' ?>" 
           id="content-<?= $index ?>" 
           data-layer="<?= $layer ?>"
           style="<?= $index === 0 ? '' : 'display: none;' ?>">

        <!-- Table View -->
        <div id="table-view-<?= $index ?>" class="table-view">

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

        <!-- Chart View -->
        <div id="chart-view-<?= $index ?>" class="chart-view" style="display: none;">
          <div class="chart-controls">
            <h5><i class="fas fa-chart-bar me-2"></i>Chart Configuration</h5>
            <div class="row">
              <div class="col-md-3">
                <label class="form-label">Chart Type:</label>
                <select id="chart-type-<?= $index ?>" class="form-select">
                  <option value="bar">Bar Chart</option>
                  <option value="line">Line Chart</option>
                  <option value="pie">Pie Chart</option>
                  <option value="scatter">Scatter Plot</option>
                  <option value="histogram">Histogram</option>
                  <option value="box">Box Plot</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">X-Axis:</label>
                <select id="x-column-<?= $index ?>" class="form-select">
                  <option value="">Select X Column</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Y-Axis:</label>
                <select id="y-column-<?= $index ?>" class="form-select">
                  <option value="">Select Y Column</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Color By:</label>
                <select id="color-column-<?= $index ?>" class="form-select">
                  <option value="">Select Color Column</option>
                </select>
              </div>
            </div>
            <div class="mt-3">
              <button class="btn btn-primary" onclick="generateChart(<?= $index ?>)">
                <i class="fas fa-chart-bar me-1"></i>Generate Chart
              </button>
              <button class="btn btn-success" onclick="exportChart(<?= $index ?>)">
                <i class="fas fa-download me-1"></i>Export Chart
              </button>
              <button class="btn btn-info" onclick="showChartData(<?= $index ?>)">
                <i class="fas fa-table me-1"></i>Show Data
              </button>
            </div>
          </div>

          <div class="chart-container">
            <canvas id="chart-<?= $index ?>" class="chart-canvas"></canvas>
            <div id="chart-placeholder-<?= $index ?>" class="chart-placeholder">
              <i class="fas fa-chart-bar fa-3x mb-3"></i>
              <p>Configure chart settings above and click "Generate Chart"</p>
            </div>
          </div>
        </div>

        <!-- Pivot View -->
        <div id="pivot-view-<?= $index ?>" class="pivot-view" style="display: none;">
          <!-- Pivot Controls -->
          <div class="pivot-controls">
            <h5><i class="fas fa-cogs me-2"></i>Pivot Table Controls</h5>
            <p class="text-muted">Click on fields to add them to rows, columns, or values. Enable subtotals to group data hierarchically when using multiple row fields.</p>
            
            <div class="row">
              <div class="col-md-4">
                <h6>Available Fields:</h6>
                <div id="field-list-<?= $index ?>" class="field-list">
                  <!-- Fields will be populated here -->
                </div>
              </div>
              <div class="col-md-8">
                <div class="row">
                  <div class="col-md-4">
                    <h6>Rows:</h6>
                    <div id="rows-zone-<?= $index ?>" class="drop-zone">
                      <small class="text-muted">Drag fields here for rows</small>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <h6>Columns:</h6>
                    <div id="cols-zone-<?= $index ?>" class="drop-zone">
                      <small class="text-muted">Drag fields here for columns</small>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <h6>Values:</h6>
                    <div id="vals-zone-<?= $index ?>" class="drop-zone">
                      <small class="text-muted">Drag fields here for values</small>
                    </div>
                  </div>
                </div>
                <div class="mt-3">
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="subtotals-<?= $index ?>" checked>
                    <label class="form-check-label" for="subtotals-<?= $index ?>">
                      <i class="fas fa-calculator me-1"></i>Show Subtotals
                    </label>
                  </div>
                  <button class="btn btn-primary" onclick="generatePivot(<?= $index ?>)">
                    <i class="fas fa-table me-1"></i>Generate Pivot Table
                  </button>
                  <button class="btn btn-secondary" onclick="clearPivot(<?= $index ?>)">
                    <i class="fas fa-undo me-1"></i>Clear
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pivot Table Output -->
          <div id="pivot-output-<?= $index ?>" class="pivot-table" style="display: none;">
            <!-- Pivot table will be generated here -->
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

<script src="../../assets/dist/js/analysis.js"></script>

</body>
</html>
