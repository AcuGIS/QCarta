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
	require('../../admin/class/basemap.php');
	require('../../admin/class/store_relation.php');

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
	
	// Load relations for this map (LAYER_ID)
	$rel_obj = new store_relation_Class($database->getConn(), $user_id);
	$rel_res = $rel_obj->getByStoreId($ql_row->store_id);
	$RELATIONS = [];
	if ($rel_res) { 
		while ($r = pg_fetch_assoc($rel_res)) $RELATIONS[] = $r; 
		pg_free_result($rel_res); 
	}
	
	// Get default basemap info if set
	$default_basemap_id = $ql_row->basemap_id ?? '';
	$default_basemap_data = null;
	if (!empty($default_basemap_id)) {
		$basemap_obj = new basemap_Class($database->getConn(), $user_id);
		$basemap_result = $basemap_obj->getById($default_basemap_id);
		if ($basemap_result && pg_num_rows($basemap_result) > 0) {
			$default_basemap_data = pg_fetch_assoc($basemap_result);
			pg_free_result($basemap_result);
		}
	}

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
	list($projection) = $xml->xpath('/qgis/ProjectViewSettings/DefaultViewExtent/spatialrefsys/authid');

	$chart_configs = json_decode(file_get_contents(DATA_DIR.'/stores/'.$ql_row->store_id.'/charts.json'), true);
	$plotly_defaults_file = DATA_DIR.'/stores/'.$ql_row->store_id.'/plotly_defaults.json';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=implode(',', QGIS_LAYERS)?></title>
  <!-- Load CSS first -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-measure@3.1.0/dist/leaflet-measure.min.css">
  <link rel="stylesheet" href="../../assets/dist/locationfilter/locationfilter.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- Load JavaScript in correct order -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/leaflet-measure@3.1.0/dist/leaflet-measure.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
  <script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
  <script src="../../assets/dist/js/leaflet.browser.print.min.js"></script>
  <script src="../../assets/dist/locationfilter/locationfilter.js"></script>
  <script src="../../assets/dist/js/proj.js"></script>
  <link rel="stylesheet" href="../../assets/dist/css/map_index.css">
  <link rel="stylesheet" href="../../assets/dist/css/datatable_style.css">
  <link rel="stylesheet" href="../../assets/dist/css/popup-scroll-fix.css">

  <script>
    // Store access key from PHP
    const layerId = <?=LAYER_ID?>; // Get the correct store_id
    const accessKey = '<?=$access_key?>';
    const WMS_SVC_URL = '/mproxy/service?' + ((accessKey.length === 0) ? '' : 'access_key=' + accessKey);
    const PRINT_URL = '<?= $proto."://".$_SERVER['HTTP_HOST'] ?>/stores/<?=$ql_row->store_id?>/print.php';
    const bbox = {
      minx: 9.25242,
      miny: 46.804286,
      maxx: 9.267365,
      maxy: 46.814982
    };
    const url_layers = '<?=urlencode(implode(',', QGIS_LAYERS))?>';
    
    let layerConfigs = <?= json_encode(array_map(function($name) use ($wms_url) {
      $typename = $name;
      $label = $name;
      if(str_starts_with($wms_url, '/mproxy/')){
        /*  if layers are exposed */
        if(str_contains($name, '.')){
          $pos = strpos($name, '.');
          $typename = substr($name, $pos +1);
          $label = $typename;
        }else{
          $typename = implode(',', QGIS_LAYERS);
        }
      }
      return ['name' => $name, 'color' => '#000', 'typename' => $typename, 'label' => $label, 'filter' => null];
    }, explode(',', 'Bee_Map.Apiary,Bee_Map.Tracks,Bee_Map.Fields'))) ?>;
    const qgsTitle = '<?= $qgs_title ?>';
    const printLayout = '<?=$ql_row->print_layout?>';
    const RELATIONS = <?= json_encode($RELATIONS ?? []) ?>;
    
    // Default basemap configuration
    const defaultBasemap = <?= json_encode($default_basemap_data) ?>;
    console.log('Default basemap data:', defaultBasemap);
    console.log('Default basemap ID from layer:', '<?= $default_basemap_id ?>');
    if (defaultBasemap) {
        console.log('Default basemap details:', {
            id: defaultBasemap.id,
            name: defaultBasemap.name,
            url: defaultBasemap.url,
            type: defaultBasemap.type
        });
    }

    <?php if (file_exists($plotly_defaults_file)) { ?>
      // Plotly default configurations
      const plotlyDefaults = <?=file_get_contents($plotly_defaults_file)?>;
	<?php } ?>

    // Search functionality
    function searchFeatures(searchTerm) {
      const selectedLayer = document.getElementById('search-layer-select').value;
      const searchResults = document.querySelector('.search-results');
      
      if (!searchTerm.trim()) {
        searchResults.innerHTML = '';
        return;
      }
      
      // Clear previous results
      searchResults.innerHTML = '<div class="text-muted">Searching...</div>';
      
      // Get the current map bounds
      const bounds = map.getBounds();
      const searchUrl = new URL(WMS_SVC_URL, window.location.origin);
      searchUrl.searchParams.set('SERVICE', 'WFS');
      searchUrl.searchParams.set('REQUEST', 'GetFeature');
      searchUrl.searchParams.set('VERSION', '1.0.0');
      searchUrl.searchParams.set('OUTPUTFORMAT', 'application/json');
      searchUrl.searchParams.set('BBOX', `${bounds.getWest()},${bounds.getSouth()},${bounds.getEast()},${bounds.getNorth()}`);
      searchUrl.searchParams.set('SRSNAME', 'EPSG:4326');
      
      // Set layers based on selection
      if (selectedLayer === 'all') {
        searchUrl.searchParams.set('TYPENAME', '<?= implode(',', QGIS_LAYERS) ?>');
      } else {
        searchUrl.searchParams.set('TYPENAME', selectedLayer);
      }
      
      // Add access key if needed
      if (accessKey) {
        searchUrl.searchParams.set('access_key', accessKey);
      }
      
      fetch(searchUrl.toString())
        .then(response => response.json())
        .then(data => {
          if (data.features && data.features.length > 0) {
            // Filter features based on search term
            const filteredFeatures = data.features.filter(feature => {
              if (!feature.properties) return false;
              
              // Search in all property values
              return Object.values(feature.properties).some(value => 
                String(value).toLowerCase().includes(searchTerm.toLowerCase())
              );
            });
            
            if (filteredFeatures.length > 0) {
              // Display results
              const resultsHtml = filteredFeatures.map(feature => {
                const layerName = feature.id.split('.')[0];
                const properties = feature.properties;
                const displayName = properties.name || properties.Name || properties.NAME || feature.id;
                
                return `
                  <div class="search-result-item p-2 border-bottom" onclick="zoomToFeature(${feature.geometry.coordinates[1]}, ${feature.geometry.coordinates[0]})" style="cursor: pointer;">
                    <div class="fw-bold">${displayName}</div>
                    <div class="text-muted small">Layer: ${layerName}</div>
                    <div class="text-muted small">
                      ${Object.entries(properties).slice(0, 3).map(([key, value]) => `${key}: ${value}`).join(', ')}
                    </div>
                  </div>
                `;
              }).join('');
              
              searchResults.innerHTML = `
                <div class="mt-2">
                  <div class="text-muted small mb-2">Found ${filteredFeatures.length} result(s)</div>
                  ${resultsHtml}
                </div>
              `;
            } else {
              searchResults.innerHTML = '<div class="text-muted mt-2">No features found matching your search.</div>';
            }
          } else {
            searchResults.innerHTML = '<div class="text-muted mt-2">No features found in the current view.</div>';
          }
        })
        .catch(error => {
          console.error('Search error:', error);
          searchResults.innerHTML = '<div class="text-danger mt-2">Error performing search. Please try again.</div>';
        });
    }
    
    // Function to zoom to a specific feature
    function zoomToFeature(lat, lng) {
      map.setView([lat, lng], 16);
      
      // Add a temporary marker to highlight the feature
      const tempMarker = L.marker([lat, lng], {
        icon: L.divIcon({
          className: 'temp-search-marker',
          html: '<div style="background-color: #ff4444; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3);"></div>',
          iconSize: [20, 20],
          iconAnchor: [10, 10]
        })
      }).addTo(map);
      
      // Remove the marker after 3 seconds
      setTimeout(() => {
        map.removeLayer(tempMarker);
      }, 3000);
    }
  </script>
</head>
<body>
<!-- Remove the old theme switcher div -->
<!-- <div class="theme-switch" onclick="toggleTheme()">
  <span class="theme-text">Theme</span>
</div> -->

<!-- Add Layer Modal -->
<div class="modal fade" id="addLayerModal" tabindex="-1" aria-labelledby="addLayerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addLayerModalLabel">Add WMS Layer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="wmsUrl" class="form-label">WMS URL</label>
          <input type="text" class="form-control" id="wmsUrl" placeholder="Enter WMS URL">
        </div>
        <div class="mb-3">
          <label for="wmsLayers" class="form-label">Layer Name</label>
          <select class="form-control" id="wmsLayers" placeholder="Select layer name" multiple disabled>
          </select>
        </div>
        <button class="btn btn-secondary" onclick="parseWmsLayers()">Load Layers</button>
        <button class="btn btn-primary" onclick="addWmsLayer()">Add WMS Layer</button>
      </div>
    </div>
  </div>
</div>

<div id="sidebar">
  <div class="sidebar-container">
    <!-- Vertical Tab Navigation -->
    <div class="sidebar-tabs">
      <ul class="nav nav-tabs flex-column" id="sidebarTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="layers-tab" data-bs-toggle="tab" data-bs-target="#layers" type="button" role="tab" aria-controls="layers" aria-selected="true" title="Layers">
            <i class="fas fa-layer-group"></i>
          </button>
        </li>
        <?php if($ql_row->show_charts == 't'){ ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts" type="button" role="tab" aria-controls="charts" aria-selected="false" title="Charts">
            <i class="fas fa-chart-pie"></i>
          </button>
        </li>
        <?php }
          if (!empty($chart_configs)){ ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="plotly-tab" data-bs-toggle="tab" data-bs-target="#plotly" type="button" role="tab" title="Plotly">
            <i class="fas fa-chart-line"></i>
          </button>
        </li>
        <?php } ?>
        
        <?php if($ql_row->show_query == 't'){ ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="sql-tab" data-bs-toggle="tab" data-bs-target="#sql" type="button" role="tab" aria-controls="sql" aria-selected="false" title="SQL Query">
            <i class="fas fa-database"></i>
          </button>
        </li>
        <?php } ?>
        
        <?php if(pg_num_rows($layer_reports)) { ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="report-tab" data-bs-toggle="tab" data-bs-target="#report" type="button" role="tab" aria-controls="report" aria-selected="false" title="Reports">
            <i class="fas fa-book"></i>
          </button>
        </li>
        <?php } ?>
        
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="bookmarks-tab" data-bs-toggle="tab" data-bs-target="#bookmarks" type="button" role="tab" title="Bookmarks">
            <i class="fas fa-bookmark"></i>
          </button>
        </li>
        
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" type="button" role="tab" title="Search">
            <i class="fas fa-search"></i>
          </button>
        </li>
        
        <?php 
        // Check if prop_filters has any rows and is a valid result
        if($prop_filters && pg_num_rows($prop_filters) > 0) { ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="filters-tab" data-bs-toggle="tab" data-bs-target="#filters" type="button" role="tab" title="Property Filters">
            <i class="fas fa-filter"></i>
          </button>
        </li>
        <?php } ?>
        
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="meta-tab" data-bs-toggle="tab" data-bs-target="#meta" type="button" role="tab" title="Metadata">
            <i class="fas fa-circle-info"></i>
          </button>
        </li>
        
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="basemaps-tab" data-bs-toggle="tab" data-bs-target="#basemaps" type="button" role="tab" title="Basemaps" onclick="if(window.basemapManager) window.basemapManager.refreshBasemapList();">
            <i class="fas fa-map"></i>
          </button>
        </li>
        
        <!-- Print, QGIS Print, and WMS Loader tabs -->
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="export-tab" type="button" role="tab" title="Export">
            <i class="fas fa-download"></i>
          </button>
        </li>        
       
        
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="wms-loader-tab" type="button" role="tab" title="WMS Loader">
            <i class="fas fa-globe"></i>
          </button>
        </li>
      </ul>
    </div>
    
    <!-- Tab Content Area -->
    <div class="sidebar-content">
      <div class="tab-content" id="sidebarTabsContent">
  <div class="tab-pane fade show active p-3" id="layers" role="tabpanel" aria-labelledby="layers-tab">
    <h6><?= htmlspecialchars($qgs_title) ?></h6><br>
    <div id="layerSelector">
      <ul class="list-group" id="layerToggleList" style="background-color: white;"></ul>
    </div>
    
    
  </div>
  
  <?php if(pg_num_rows($layer_reports)) { ?>
  <div class="tab-pane fade p-3" id="report" role="tabpanel" aria-labelledby="report-tab">
    <div class="report-query-container p-3">
        <div id="savedReportsList" class="list-group">
            <?php while($row = pg_fetch_object($layer_reports)) { ?>
                <button class="saved_layer_report list-group-item list-group-item-action" data-name="<?=$row->name?>" data-database_type="<?=$row->database_type?>" data-sql="<?=base64_encode($row->sql_query)?>" onclick="onSavedReportClick(this)">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong  style="font-size: 0.75rem !important;"><?=$row->name?></strong>
                        <small class="d-block text-muted" style="font-size: 0.7rem !important;"><?=$row->description?></small>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?=$row->badge?></span>
                </div>
            </button>
            <?php } ?>
        </div>
    </div>
  </div>
  <?php } ?>

  <?php if($ql_row->show_query == 't'){ ?>
  <div class="tab-pane fade p-3" id="sql" role="tabpanel" aria-labelledby="sql-tab">
    <div class="sql-query-container p-3">
      <div class="mb-3">
        <label for="databaseType" class="form-label">Database Type</label>
        <select class="form-select" id="databaseType">
          <option value="gpkg">GeoPackage</option>
          <option value="postgres">PostgreSQL</option>
          <option value="shp">ShapeFile</option>
          <option value="gdb">ESRI Geodatabase</option>
        </select>
      </div>
      <div class="mb-3">
        <label for="sqlQuery" class="form-label">SQL Query</label>
        <textarea class="form-control" id="sqlQuery" rows="4" placeholder="Enter your SQL query here..."></textarea>
      </div>
      <button class="btn btn-primary mb-3" id="executeQuery">Execute Query</button>
      <div id="queryResults" class="table-responsive">
        <table class="table table-striped table-hover">
          <thead id="queryResultsHeader"></thead>
          <tbody id="queryResultsBody"></tbody>
        </table>
      </div>
      <div id="queryError" class="alert alert-danger mt-3" style="display: none;"></div>
      <div class="d-flex gap-2 mt-3">
          <button class="btn btn-secondary" id="openInModal" style="display: none;" title="Open in Modal"><i id="openInModalIcon" class="fa-solid fa-up-right-and-down-left-from-center"></i></button>
          <button class="btn btn-success" id="exportResults" style="display: none;" title="Export Results"><i id="exportResultsIcon" class="fa-solid fa-file-export"></i></button>
        <button class="btn btn-info" id="limitMapToResults" style="display: none;" title="Limit Map to Results"><i id="limitMapToResultsIcon" class="fa-solid fa-expand"></i></button>
        <button class="btn btn-danger" id="clearMapFromResults" style="display: none;" title="Clear Results from Map"><i id="clearMapFromResultsIcon" class="fa-solid fa-xmark"></i></button>
      </div>
    </div>
  </div>
  <?php } ?>
  
  <?php if($ql_row->show_charts == 't'){ ?>
  <div class="tab-pane fade p-3" id="charts" role="tabpanel" aria-labelledby="charts-tab">
    <div class="mb-2">
      <label for="layerSelect">Layer:</label>
      <select id="layerSelect" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label for="groupBy">Group by:</label>
      <select id="groupBy" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label for="valueField">Value:</label>
      <select id="valueField" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label for="chartType">Chart type:</label>
      <select id="chartType" class="form-select form-select-sm">
        <option value="bar">Bar</option>
        <option value="pie">Pie</option>
        <option value="line">Line</option>
      </select>
    </div>
    <div id="multiChartContainer" class="mt-3 w-100"></div>
  </div>
  <?php } ?>
  
  <!-- Basemaps Tab Content -->
  <div class="tab-pane fade p-3" id="basemaps" role="tabpanel" aria-labelledby="basemaps-tab">
    <div class="basemaps-container">
      <div class="mb-3">
        <h6 class="fw-bold mb-2">
          <i class="fas fa-map me-2"></i>Select Basemap
        </h6>
        <p class="text-muted small">Click on a basemap to switch to it.</p>
      </div>
      
      <!-- Basemap List -->
      <div id="basemapList" class="mt-3">
        <!-- Basemaps will be loaded here dynamically as a grid -->
        <div class="alert alert-info">
          <strong>Loading basemaps...</strong> Please wait while we load the available basemaps.
        </div>
      </div>
      
      <!-- Attribution Info -->
      <div id="basemapAttribution" class="mt-3 p-2 bg-light rounded small text-muted" style="display: none;">
        <strong>Attribution:</strong> <span id="attributionText"></span>
      </div>
    </div>
  </div>

  <?php if (!empty($chart_configs)): ?>
  <div class="tab-pane fade p-3" id="plotly" role="tabpanel" aria-labelledby="plotly-tab">
    <div class="mb-2">
      <label for="plotlyLayerSelect">Layer:</label>
      <select id="plotlyLayerSelect" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label for="plotlyConfig">Chart Configuration:</label>
      <select id="plotlyConfig" class="form-select form-select-sm">
        <?php foreach ($chart_configs as $config): ?>
        <option value="<?= htmlspecialchars($config['filename']) ?>"><?= htmlspecialchars($config['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-2">
      <label for="plotlyChartType">Chart type:</label>
      <select id="plotlyChartType" class="form-select form-select-sm">
        <option value="scatter">Scatter</option>
        <option value="bar">Bar</option>
        <option value="line">Line</option>
        <option value="box">Box Plot</option>
        <option value="violin">Violin Plot</option>
        <option value="histogram">Histogram</option>
        <option value="pie">Pie Chart</option>
        <option value="sunburst">Sunburst</option>
        <option value="treemap">Treemap</option>
        <option value="funnel">Funnel</option>
        <option value="waterfall">Waterfall</option>
        <option value="candlestick">Candlestick</option>
        <option value="ohlc">OHLC</option>
        <option value="contour">Contour</option>
        <option value="heatmap">Heatmap</option>
        <option value="surface">3D Surface</option>
        <option value="scatter3d">3D Scatter</option>
        <option value="mesh3d">3D Mesh</option>
      </select>
    </div>
    <div class="mb-2">
      <label for="plotlyXField">X-axis:</label>
      <select id="plotlyXField" class="form-select form-select-sm"></select>
    </div>
    <div class="mb-2">
      <label for="plotlyYField">Y-axis:</label>
      <select id="plotlyYField" class="form-select form-select-sm"></select>
    </div>
    <div id="plotlyChart" style="width: 100%; height: 400px; max-width: 380px;"></div>
  </div>
  <?php endif; ?>
  
  <!-- Bookmarks Tab -->
  <div class="tab-pane fade p-3" id="bookmarks" role="tabpanel" aria-labelledby="bookmarks-tab">
    <!-- Bookmark control -->
    <div class="bookmark-control">
      <div class="bookmark-header">
        <button onclick="toggleBookmarkList()" class="bookmark-button secondary">
          <i class="fas fa-bookmark"></i>
          Bookmarks
        </button>
        <button class="bookmark-button secondary" onclick="addBookmark()">
          <i class="fas fa-save"></i>
          Save Current View
        </button>
      </div>
      <div class="bookmark-list"></div>
    </div>

  </div>
  
  <!-- Search Tab -->
  <div class="tab-pane fade p-3" id="search" role="tabpanel" aria-labelledby="search-tab">
    <!-- Search control -->
    <div class="search-control">
      <label for="search-layer-select">Select Layer:</label>
      <select id="search-layer-select" class="form-select form-select-sm mb-2">
        <option value="all">All Layers</option>
        <?php foreach(QGIS_LAYERS as $layer): ?>
        <option value="<?= $layer ?>"><?= $layer ?></option>
        <?php endforeach; ?>
      </select>
      <label for="search-control">Search Features:</label>
      <input type="text" id="search-input" placeholder="Search Features..." oninput="searchFeatures(this.value)">
      <div class="search-results"></div>
    </div>
  </div>
  
  <!-- Property Filters Tab -->
  <div class="tab-pane fade p-3" id="filters" role="tabpanel" aria-labelledby="filters-tab">
    <!-- Add Saved Filters section -->
    <?php if(pg_num_rows($prop_filters)) { ?>
    <div class="search-control">
        <div class="saved-queries-header">
        <h6 class="mb-2">Property Filters</h6>
        </div>
        <div id="savedFiltersList" class="list-group">
            <?php while($row = pg_fetch_object($prop_filters)) { ?>
                <button class="saved_prop_filter list-group-item list-group-item-action" data-id="<?=$row->id?>" data-name="<?=$row->name?>" data-feature="<?=$row->feature?>" data-property="<?=$row->property?>" onclick="onSavedPropFilterClick(this)">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?=$row->name?></strong>
                        <small class="d-block text-muted"><?=$row->feature?></small>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?=$row->property?></span>
                </div>
            </button>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
  </div>
  
  <div class="tab-pane fade p-3" id="meta" role="tabpanel" aria-labelledby="meta-tab">
    <div class="metadata-container">
      <?php if($lm['id'] == 0){ ?>
        <div class="card mb-3">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>QGIS Info</h5>
          </div>
          <div class="card-body">
            <div class="metadata-item">
              <i class="fas fa-heading me-2 text-muted"></i>
              <strong>Title:</strong> <?= htmlspecialchars($qgs_title) ?>
            </div>
            <div class="metadata-item">
              <i class="fas fa-align-left me-2 text-muted"></i>
              <strong>Abstract:</strong> <?= htmlspecialchars($qgs_abstract) ?>
            </div>
            <div class="metadata-item">
              <i class="fas fa-globe me-2 text-muted"></i>
              <strong>Projection:</strong> <?= htmlspecialchars($projection) ?>
            </div>
            <div class="metadata-item">
              <i class="fas fa-border-all me-2 text-muted"></i>
              <strong>Bounding Box:</strong> 46.804286,9.25242,46.814982,9.267365
            </div>
            <div class="metadata-item">
              <i class="fas fa-server me-2 text-muted"></i>
              <strong>OGC Web Services:</strong>
              <div class="ms-4 mt-2">
                <a href="<?= $proto . '://' . $_SERVER['HTTP_HOST'] ?>/stores/<?=$ql_row->store_id?>/wms?REQUEST=GetCapabilities" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                  <i class="fas fa-map me-1"></i>WMS
                </a>
                <a href="<?= $proto . '://' . $_SERVER['HTTP_HOST'] ?>/stores/<?=$ql_row->store_id?>/wfs?REQUEST=GetCapabilities" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                  <i class="fas fa-database me-1"></i>WFS
                </a>
                <a href="<?= $proto . '://' . $_SERVER['HTTP_HOST'] ?>/stores/<?=$ql_row->store_id?>/wmts?REQUEST=GetCapabilities" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-layer-group me-1"></i>WMTS
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php } else { ?>
        <div class="accordion" id="metadataAccordion">
          <!-- Identification Info -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identificationInfo">
                <i class="fas fa-info-circle me-2"></i>Identification Info
              </button>
            </h2>
            <div id="identificationInfo" class="accordion-collapse collapse show" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-heading me-2 text-muted"></i>
                  <strong>Title:</strong> <?= htmlspecialchars($lm['title']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-align-left me-2 text-muted"></i>
                  <strong>Abstract:</strong> <?= htmlspecialchars($lm['abstract']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-bullseye me-2 text-muted"></i>
                  <strong>Purpose:</strong> <?= htmlspecialchars($lm['purpose']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-tags me-2 text-muted"></i>
                  <strong>Keywords:</strong> <?= htmlspecialchars($lm['keywords']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-tags me-2 text-muted"></i>
                  <strong>Language:</strong> <?= htmlspecialchars($lm['language']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-tags me-2 text-muted"></i>
                  <strong>Character Set:</strong> <?= htmlspecialchars($lm['character_set']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-tags me-2 text-muted"></i>
                  <strong>Maintenance Frequency:</strong> <?= htmlspecialchars(FREQUENCY_TABLE[$lm['maintenance_frequency']]) ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Citation -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#citationInfo">
                <i class="fas fa-book me-2"></i>Citation
              </button>
            </h2>
            <div id="citationInfo" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-calendar me-2 text-muted"></i>
                  <strong>Citation Date:</strong> <?= htmlspecialchars($lm['cit_date']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-building me-2 text-muted"></i>
                  <strong>Responsible Organization:</strong> <?= htmlspecialchars($lm['cit_responsible_org']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-user me-2 text-muted"></i>
                  <strong>Responsible Person:</strong> <?= htmlspecialchars($lm['cit_responsible_person']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-user-tag me-2 text-muted"></i>
                  <strong>Role:</strong> <?= htmlspecialchars($lm['cit_role']) ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Geographic Extent -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#geographicExtent">
                <i class="fas fa-map-marked-alt me-2"></i>Geographic Extent
              </button>
            </h2>
            <div id="geographicExtent" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-arrow-left me-2 text-muted"></i>
                  <strong>West Longitude:</strong> <?= htmlspecialchars($lm['west']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-arrow-right me-2 text-muted"></i>
                  <strong>East Longitude:</strong> <?= htmlspecialchars($lm['east']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-arrow-down me-2 text-muted"></i>
                  <strong>South Latitude:</strong> <?= htmlspecialchars($lm['south']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-arrow-up me-2 text-muted"></i>
                  <strong>North Latitude:</strong> <?= htmlspecialchars($lm['north']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-map-marked me-2 text-muted"></i>
                  <strong>Coordinate System (EPSG Code):</strong> <?= htmlspecialchars($lm['coordinate_system']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-map-marked me-2 text-muted"></i>
                  <strong>Spatial Resolution:</strong> <?= htmlspecialchars($lm['spatial_resolution']) ?>
                </div>
                
              </div>
            </div>
          </div>

          <!-- Temporal Extent -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#temporalExtent">
                <i class="fas fa-clock me-2"></i>Temporal Extent
              </button>
            </h2>
            <div id="temporalExtent" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-calendar-plus me-2 text-muted"></i>
                  <strong>Start Date:</strong> <?= htmlspecialchars($lm['start_date']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-calendar-minus me-2 text-muted"></i>
                  <strong>End Date:</strong> <?= htmlspecialchars($lm['end_date']) ?>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Responsible Parties -->
          <div class="accordion-item" style="font-size:0.8rem!important">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#responsibleParties">
                <i class="fas fa-clock me-2"></i>Responsible Parties
              </button>
            </h2>
            <div id="responsibleParties" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item"  style="font-size:0.8rem!important">
                  <i class="fas fa-calendar-plus me-2 text-muted"></i>
                  <strong>Metadata Organization:</strong> <?= htmlspecialchars($lm['metadata_organization']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-calendar-plus me-2 text-muted"></i>
                  <strong>Metadata Email:</strong> <?= htmlspecialchars($lm['metadata_email']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-calendar-plus me-2 text-muted"></i>
                  <strong>Metadata Role:</strong> <?= htmlspecialchars($lm['metadata_role']) ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Data Quality -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dataQuality">
                <i class="fas fa-clock me-2"></i>Data Quality
              </button>
            </h2>
            <div id="dataQuality" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-calendar-plus me-2 text-muted"></i>
                  <strong>Lineage:</strong> <?= htmlspecialchars($lm['lineage']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-calendar-plus me-2 text-muted"></i>
                  <strong>Scope:</strong> <?= htmlspecialchars($lm['scope']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-calendar-plus me-2 text-muted"></i>
                  <strong>Conformity Result:</strong> <?= htmlspecialchars($lm['conformity_result']) ?>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Constraints -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#constraints">
                <i class="fas fa-lock me-2"></i>Constraints
              </button>
            </h2>
            <div id="constraints" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-exclamation-circle me-2 text-muted"></i>
                  <strong>Use Constraints:</strong> <?= htmlspecialchars($lm['use_constraints']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-exclamation-circle me-2 text-muted"></i>
                  <strong>Use Limitation:</strong> <?= htmlspecialchars($lm['use_limitation']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-exclamation-circle me-2 text-muted"></i>
                  <strong>Access Constraints:</strong> <?= htmlspecialchars($lm['access_constraints']) ?>
                </div>
              </div>
            </div>
          </div>

          <!-- INSPIRE Metadata -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#inspireMetadata">
                <i class="fas fa-certificate me-2"></i>INSPIRE Metadata
              </button>
            </h2>
            <div id="inspireMetadata" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-building me-2 text-muted"></i>
                  <strong>INSPIRE Point of Contact Organization:</strong> <?= htmlspecialchars($lm['inspire_point_of_contact']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-check-circle me-2 text-muted"></i>
                  <strong>Conformity Result:</strong> <?= htmlspecialchars($lm['inspire_conformity']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-link me-2 text-muted"></i>
                  <strong>Spatial Data Service URL:</strong> <?=$proto."://".$_SERVER['HTTP_HOST']."/mproxy/service"?>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Distribution -->
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#distribution">
                <i class="fas fa-lock me-2"></i>Distribution
              </button>
            </h2>
            <div id="distribution" class="accordion-collapse collapse" data-bs-parent="#metadataAccordion">
              <div class="accordion-body">
                <div class="metadata-item">
                  <i class="fas fa-exclamation-circle me-2 text-muted"></i>
                  <strong>Distribution URL:</strong> <?= htmlspecialchars($lm['distribution_url']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-exclamation-circle me-2 text-muted"></i>
                  <strong>Data Format(s):</strong> <?= htmlspecialchars($lm['data_format']) ?>
                </div>
                <div class="metadata-item">
                  <i class="fas fa-exclamation-circle me-2 text-muted"></i>
                  <strong>Coupled Resource:</strong> <?= htmlspecialchars($lm['coupled_resource']) ?>
                </div>
              </div>
            </div>
          </div>
          
        </div>

        <div class="mt-3 text-end">
          <a href="../../admin/action/layer_metadata_xml.php?id=<?=LAYER_ID?>" target="_blank" class="btn btn-outline-secondary">
            <i class="fas fa-code me-1"></i>View as XML
          </a>
        </div>
      <?php } ?>
    </div>
  </div>
      </div>
    </div>
  </div>
</div>
<div id="sidebarToggle"><i id="sidebarToggleI" class="fas fa-chevron-left"></i></div>
<div id="map"></div>

    <!-- Right-side inspector (docked popup) -->
    <div id="inspectPanel" class="inspect-panel collapsed" aria-hidden="true">
      <div class="inspect-header">
        <div class="inspect-title">Feature</div>
        <button class="btn-close" type="button" id="inspectClose" aria-label="Close"></button>
      </div>
      <div class="inspect-body" id="inspectBody">
        <!-- Filled dynamically -->
      </div>
    </div>

<script src="../../assets/dist/js/map_index_lib.js"></script>
<script src="../../assets/dist/js/map_index.js"></script>
<script src="../../assets/dist/js/map_index_controls.js"></script>
<script src="../../assets/dist/js/basemap_manager.js"></script>
<?php if (file_exists($plotly_defaults_file)) { ?>
<script src="../../assets/dist/js/plotly_defaults.js"></script>
<?php } ?>

<?php if(isset($_SESSION[SESS_USR_KEY]) && ($_SESSION[SESS_USR_KEY]->id == $ql_row->owner_id) && ($ql_row->show_fi_edit == 't')){ ?>
<script src="../../assets/dist/js/popup-fix.js?v=2025-03-91"></script>
<?php } ?>

<?php if($ql_row->show_dt == 't'){ ?>
<div id="dataTablePanel" class="position-fixed bottom-0 w-100 bg-white border-top shadow" style="max-height: 300px; display: none; overflow-y: auto; z-index: 1050;">
  <!-- Table Handle -->
  <div class="table-handle" onclick="toggleDataTable()">
    <div class="handle-grip">
      <span></span>
      <span></span>
      <span></span>
    </div>
    <span class="handle-text">Table</span>
  </div>
  <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
    <span class="fw-bold">Data Table</span>
    <button class="btn btn-sm btn-outline-secondary" onclick="toggleDataTable()">Close</button>
  </div>
        <ul class="nav nav-tabs" role="tablist">
                <?php $first = ' active'; $li_first = 'class="nav-item" role="presentation"';
                        for($i=0; $i < count(QGIS_LAYERS); $i++){
                                $tabname = QGIS_LAYERS[$i];  ?>
                        <li <?=$li_first?>>
                                <button class="nav-link<?=$first?>" data-bs-toggle="tab" data-bs-target="#tab-table<?=$i?>" role="tab" aria-controls="tab-table<?=$i?>" aria-selected="true" onclick="updateDataTable(<?=$i?>)"><?=$tabname?></button>
                        </li>
                <?php $first = ''; $li_first = ''; } ?>
        </ul>

        <div class="tab-content pt-2">
                <?php $first = ' show active';
                        for($i=0; $i < count(QGIS_LAYERS); $i++){
                                $tabname = QGIS_LAYERS[$i]; ?>
                        <div class="tab-pane<?=$first?>" id="tab-table<?=$i?>" role="tabpanel" aria-labelledby="<?=$tabname?>-tab">
                                <table id="dataTable<?=$i?>" class="table table-striped table-bordered" cellspacing="0" width="100%">
                                <thead></thead>
                                <tbody></tbody>
                                </table>
                        </div>
                <?php $first = ''; } ?>
        </div>
</div>
<?php } ?>

<!-- Table Toggle Button - Centered handle style -->
<?php if($ql_row->show_dt == 't'){ ?>
<button class="btn btn-primary position-fixed bottom-0" style="left: 50%; transform: translateX(-50%); z-index: 1055; background: linear-gradient(135deg, #6c757d 0%, #495057 100%); border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.15); font-size: 13px; padding: 8px 24px; border-radius: 8px 8px 0 0; margin-bottom: 0; opacity: 0.8; min-width: 120px;" onclick="toggleDataTable()" title="Toggle Data Table">
  <i class="fas fa-table me-1"></i>Data
</button>
<?php } ?>

<!-- Add Modal for SQL Results -->
<div class="modal fade" id="sqlResultsModal" tabindex="-1" aria-labelledby="sqlResultsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sqlResultsModalLabel">SQL Query Results</h5>
        <div class="ms-auto">
            <button class="btn btn-success" id="exportResultsModal" title="Export Results"><i id="exportResultsModalIcon" class="fa-solid fa-file-export"></i></button>
            <button class="btn btn-info" id="limitMapToResultsModal" title="Limit Map to Results"><i id="limitMapToResultsModalIcon" class="fa-solid fa-expand"></i></button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead id="modalResultsHeader"></thead>
            <tbody id="modalResultsBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="filter_modal" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 style="color:black!important" class="modal-title">Property Filters</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			
			<div class="modal-body" id="addnew_modal_body">
  <div class="md-filters">
    <input type="hidden" name="filter_feature" id="filter_feature" value=""/>
    <input type="hidden" name="filter_property" id="filter_property" value=""/>

    <div class="md-field">
      <div class="md-label">Property</div>
      <div id="filter_property_p" class="md-pill" aria-live="polite"></div>
    </div>

    <div class="md-field">
      <label class="md-label" for="filter_op">Operator</label>
      <select class="form-select md-input" id="filter_op" name="filter_op">
        <option value="IN" selected>IN</option>
        <option value="NOT IN">NOT IN</option>
        <option value="=">=</option>
        <option value="!=">≠</option>
        <option value="<">&lt;</option>
        <option value="<=">&le;</option>
        <option value=">">&gt;</option>
        <option value=">=">&ge;</option>
      </select>
    </div>

    <div class="md-field">
      <label class="md-label" for="filter_values_search">Values</label>
      <input type="text" id="filter_values_search" class="form-control md-input" placeholder="Search values"/>
      <div id="filter_chips" class="md-chip-set" role="listbox" aria-label="Filter values"></div>

      <!-- Keep original multi-select for app compatibility; strongly hidden and kept in sync -->
      <select class="form-select" id="filter_values" name="filter_values[]" multiple style="display:none !important; visibility:hidden !important; height:0 !important;"></select>
    </div>
  </div>
</div><div class="modal-footer">
			    <button type="button" class="activate btn btn-secondary" id="btn_clear_filter" data-dismiss="modal">Clear</button>
				<button type="button" class="activate btn btn-primary" id="btn_update_filter" data-dismiss="modal">Update</button>
			</div>
<script>
  filterValuesSetup();
  replaceModalOpener();
  
  // Initialize basemap manager after all scripts are loaded
  const basemapManager = new BasemapManager();
</script>
</body>
</html>
