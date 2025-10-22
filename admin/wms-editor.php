<?php
    session_start();
    require('incl/const.php');
    require('class/database.php');
    require('class/table.php');
    require('class/table_ext.php');	
    require('class/layer.php');
    require('class/qgs_layer.php');
    require('class/basemap.php');

    if(!isset($_SESSION[SESS_USR_KEY])) {
        header('Location: ../login.php');
        exit(1);
    }

    // Initialize content array in session if it doesn't exist
    if (!isset($_SESSION['content'])) {
        $_SESSION['content'] = [];
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? '';
        $title = $_POST['title'] ?? '';
        $layer_id = $_POST['layer_id'] ?? '';
        $wmsUrl = $_POST['wmsUrl'] ?? '';
        $layers = isset($_POST['layers']) && is_array($_POST['layers']) ? implode(',', $_POST['layers']) : '';
        $basemap_id = $_POST['basemap_id'] ?? '';
        $content = $_POST['content'] ?? '';
        $map_center = $_POST['map_center'] ?? '';
        $map_zoom = $_POST['map_zoom'] ?? '';
        
        if ($id) {
            $_SESSION['content'][$id] = [
                'type' => 'wms',
                'title' => $title,
                'layer_id' => $layer_id,
                'wmsUrl' => $wmsUrl,
                'layers' => $layers,
                'basemap_id' => $basemap_id,
                'content' => $content,
                'map_center' => $map_center,
                'map_zoom' => $map_zoom
            ];
        }
        
        // Redirect back to index
        header('Location: geostory-editor.php');
        exit(0);
    }
    
    // Get content ID from URL
    $id = $_GET['id'] ?? '';
    $content = $_SESSION['content'][$id] ?? [
        'type' => 'wms',
        'title' => 'New WMS Layer',
        'layer_id' => 0,
        'wmsUrl' => '',
        'layers' => '',
        'basemap_id' => '',
        'content' => ''
    ];
    
    $layers = [];
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
    $obj = new qgs_layer_Class($database->getConn(),    $_SESSION[SESS_USR_KEY]->id);
	$layer_rows = $obj->getRows();
	
	// Load basemaps
	$basemap_obj = new basemap_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
	$basemap_rows = $basemap_obj->getRows();

    $proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
    $auth_content = file_get_contents($proto.'://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key='.$_SESSION[SESS_USR_KEY]->secret_key.'&ip='.$_SERVER['REMOTE_ADDR']);
    $auth = json_decode($auth_content);
    $access_key = $auth->access_key; // Store access key for JavaScript
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        body {
            min-height: 100vh;
            overflow-y: auto;
        }
        .editor-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .preview-map {
            width: 100%;
            min-width: 100%;
            height: 300px;
            min-height: 300px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-top: 20px;
            position: relative;
        }
    </style>
    
    <script>
    const access_key = '<?=$access_key?>';
	var layerConfigs = {};
	var basemapConfigs = {};
	
	<?php while($row = pg_fetch_assoc($layer_rows)) {
	  $layers[$row['id']] = $row['name'];
	?>
	layerConfigs[<?=$row['id']?>] = {
	  id: <?=$row['id']?>,
	  name: '<?=$row["name"]?>',
	  layers: '<?php if($row["proxyfied"] == "t") {
			if($row["exposed"] == "t") {
	          echo $row["name"].".".str_replace(",", ",".$row['name'].".", $row["layers"]);
			} else {
		      echo $row["name"];
	        }
	    } else {
			// Check if layers is already a string or needs to be converted from array
			if (is_array($row["layers"])) {
				echo implode(",", $row["layers"]);
			} else {
				echo $row["layers"];
			}
	    } 
	    ?>',
	  public: <?=$row["public"] == "t" ? 'true':'false'?>,
	  url: '<?php if($row["proxyfied"] == "t") { ?>/mproxy/service<?php }else{ ?>/layers/<?=$row["id"]?>/proxy_qgis.php<?php } ?>'
	};
	<?php } ?>
	
	<?php 
	// Reset the basemap result pointer
	pg_result_seek($basemap_rows, 0);
	while($basemap_row = pg_fetch_assoc($basemap_rows)) { ?>
	basemapConfigs[<?=$basemap_row['id']?>] = {
		id: <?=$basemap_row['id']?>,
		name: '<?=$basemap_row["name"]?>',
		url: '<?=$basemap_row["url"]?>',
		type: '<?=$basemap_row["type"]?>',
		attribution: '<?=$basemap_row["attribution"]?>',
		min_zoom: <?=$basemap_row["min_zoom"]?>,
		max_zoom: <?=$basemap_row["max_zoom"]?>
	};
	<?php } ?>
    </script>
</head>
<body>
    <div id="container" style="display:block">
        <?php const NAV_SEL = 'Geostories'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
        
		<div class="editor-container">
            <h1 class="mb-4">WMS Content Editor</h1>
            
            <form method="POST" id="contentForm">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                        value="<?php echo htmlspecialchars($content['title']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="layer_id" class="form-label">WMS URL</label>
                    <select id="layer_id" name="layer_id">
                    <?php foreach($layers as $id => $name){ ?>
                        <option value="<?=$id?>" <?php if($content['layer_id'] == $id) { ?>selected<?php } ?>><?=$name?></option>
                    <?php } ?>
                    </select>

                    <input type="hidden" class="form-control" id="wmsUrl" name="wmsUrl" 
                        value="<?php echo htmlspecialchars($content['wmsUrl']); ?>" required
                        placeholder="https://example.com/geoserver/wms">
                </div>
                
                <div class="mb-3">
                    <label for="layers" class="form-label">Layers (comma-separated)</label>
                    <select class="form-control" id="layers" name="layers[]" multiple required>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="basemap_id" class="form-label">Basemap</label>
                    <select class="form-control" id="basemap_id" name="basemap_id">
                        <option value="">Select a basemap...</option>
                        <?php 
                        pg_result_seek($basemap_rows, 0);
                        while($basemap_row = pg_fetch_assoc($basemap_rows)){ ?>
                            <option value="<?=$basemap_row['id']?>" <?php if($content['basemap_id'] == $basemap_row['id']) { ?>selected<?php } ?>><?=$basemap_row['name']?></option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="content" class="form-label">Additional Content</label>
                    <textarea id="content" name="content"><?php echo htmlspecialchars($content['content']); ?></textarea>
                </div>
                
                <div class="preview-map" id="previewMap"></div>
                <div class="d-flex justify-content-end mt-2">
                    <button type="button" class="btn btn-outline-primary" id="setMapViewBtn">Set Map View</button>
                </div>
                <input type="hidden" name="map_center" id="mapCenter" value="">
                <input type="hidden" name="map_zoom" id="mapZoom" value="">
                
                <div class="btn-group">
                    <a href="geostory-editor.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Content</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include("incl/footer.php"); ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script>
        function updateMapPreview() {
            const wmsUrl = $('#wmsUrl').val();
            const layers = $('#layers').val();
            let bounds = null;
            if (window.previewWmsBounds) {
                bounds = window.previewWmsBounds;
            }
            if (wmsUrl && layers) {
                // Remove existing WMS layers
                map.eachLayer((layer) => {
                    if (layer instanceof L.TileLayer.WMS) {
                        map.removeLayer(layer);
                    }
                });
                
                // Ensure there's a basemap layer
                let hasBasemap = false;
                map.eachLayer((layer) => {
                    if (layer instanceof L.TileLayer && !(layer instanceof L.TileLayer.WMS)) {
                        hasBasemap = true;
                    }
                });
                
                // Add basemap if none exists
                if (!hasBasemap) {
                    updateBasemap();
                }
                
                // Add WMS layer
                L.tileLayer.wms(wmsUrl + '?access_key='+access_key, {
                    layers: layers,
                    format: 'image/png',
                    transparent: true
                }).addTo(map);
                
                map.invalidateSize();
                setTimeout(() => {
                    map.invalidateSize();
                    if (bounds) {
                        map.fitBounds(bounds);
                    } else {
                        map.setView([20, 0], 3);
                    }
                }, 300);
            }
        }

        // Ensure the previewMap container is sized before initializing the map
        const mapElement = document.getElementById('previewMap');
        if (mapElement) {
            mapElement.style.height = '300px';
            mapElement.style.width = '100%';
        }
        const map = L.map('previewMap').setView([0, 0], 2);
        // Don't add a default basemap - let updateBasemap() handle it
  
        setTimeout(() => { map.invalidateSize(); }, 300);
        $(window).on('resize', () => { map.invalidateSize(); });
    
        $(document).on("change", '#layer_id', function() {
          let obj = $(this);
          let layer_id = obj.find('option:selected').val();
          // update wmsUrl and layer list
          $('#wmsUrl').val(layerConfigs[layer_id].url);
          $('#layers').empty();
          
          let ll = layerConfigs[layer_id].layers.split(',').forEach((element) => {
            let newOption = $('<option value="' + element + '">' + element + '</option>');
            $('#layers').append(newOption);
          });

          $('#layers').val('<?=$content["layers"]?>'.split(','));
          $('#layers').trigger('change');
       	});
       	
       	$(document).on("change", '#layers', function() {
       	  updateMapPreview();
       	});
       	
       	$(document).on("change", '#basemap_id', function() {
       	  updateBasemap();
       	});

        function updateBasemap() {
            const basemapId = $('#basemap_id').val();
            
            // Store existing WMS layers to re-add them later
            const existingWmsLayers = [];
            map.eachLayer((layer) => {
                if (layer instanceof L.TileLayer.WMS) {
                    existingWmsLayers.push({
                        url: layer._url,
                        options: layer.options
                    });
                }
            });
            
            // Remove all layers
            map.eachLayer((layer) => {
                map.removeLayer(layer);
            });
            
            // Add basemap first
            if (basemapId && basemapConfigs[basemapId]) {
                const basemap = basemapConfigs[basemapId];
                
                if (basemap.type === 'xyz') {
                    L.tileLayer(basemap.url, {
                        attribution: basemap.attribution,
                        minZoom: basemap.min_zoom,
                        maxZoom: basemap.max_zoom
                    }).addTo(map);
                }
            } else {
                // Add default basemap if none selected
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
            }
            
            // Re-add WMS layers on top
            existingWmsLayers.forEach(wmsLayer => {
                L.tileLayer.wms(wmsLayer.url, wmsLayer.options).addTo(map);
            });
            
            map.invalidateSize();
        }

        $(document).ready(function() {
            
            $('#layer_id').trigger('change');
            
            // Initialize basemap - this will apply selected basemap or default
            updateBasemap();
            
            $('#content').summernote({
                height: 200,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });

            // Add Set Map View button logic
            $('#setMapViewBtn').on('click', function() {
                const center = map.getCenter();
                const zoom = map.getZoom();
                $('#mapCenter').val(center.lat + ',' + center.lng);
                $('#mapZoom').val(zoom);
                $(this).text('Map View Set!').removeClass('btn-outline-primary').addClass('btn-success');
                setTimeout(() => {
                    $('#setMapViewBtn').text('Set Map View').removeClass('btn-success').addClass('btn-outline-primary');
                }, 1500);
            });
        });
    </script>
</body>
</html>
