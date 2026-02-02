<?php
    session_start();
    require('incl/const.php');
    require('class/database.php');
    require('class/table.php');
    require('class/table_ext.php');	
    require('class/layer.php');
    require('class/qgs_layer.php');
    require('class/access_group.php');
    require('class/geostory.php');
	
    if(!isset($_SESSION[SESS_USR_KEY])) {
        header('Location: ../login.php');
        exit(1);
    }

    // Initialize content array in session if it doesn't exist
    if (!isset($_SESSION['content'])) {
        $_SESSION['content'] = [];
    }
    
    // Handle content removal
    if (isset($_GET['remove'])) {
        $id = $_GET['remove'];
        if (isset($_SESSION['content'][$id])) {
            unset($_SESSION['content'][$id]);
        }
        header('Location: geostory-editor.php');
        exit(0);
    }
    
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
    $grp_obj = new access_group_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
	$groups = $grp_obj->getArr();
	
	$story = ['id' => 0, 'name' => '', 'description' => '', 'public' => 'f', 'group_id' => [] ];
	
	if (!empty($_GET['id'])) {    // called from edit
        $id = intval($_GET['id']);
        
        $obj = new geostory_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
        
        $result = $obj->getById($id);
        if(!$result || (pg_num_rows($result) == 0)){
            die("Error: No such story");
        }
        $story = pg_fetch_assoc($result);
		pg_free_result($result);
		
		$story_grps = $grp_obj->getByKV('layer', $story['id']);
		$story['group_id'] = array_keys($story_grps);
		
		$_SESSION['story'] = $story;
		
		$qgs_obj = new qgs_layer_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
		
		$rows = $obj->getStorySectionById($story['id'], 'wms');
		while($row = pg_fetch_object($rows)) {
		    
		    $qgs_res = $qgs_obj->getById($row->layer_id);
			$qgs_row = pg_fetch_object($qgs_res);
			pg_free_result($qgs_res);
		    
			if($qgs_row->proxyfied == "t") {
				require_once(__DIR__ . '/../inc/mproxy.php');
				try {
					$wmsUrl = getMproxyBaseUrl($qgs_row->store_id);
				} catch (Exception $e) {
					error_log("admin/geostory-editor.php: Failed to get mproxy base URL for store_id {$qgs_row->store_id}: " . $e->getMessage());
					$wmsUrl = '/mproxy/service'; // Fallback
				}
			} else {
				$wmsUrl = '/layers/'.$qgs_row->id.'/proxy_qgis.php';
			}
						
            $_SESSION['content']['content-'.$row->section_order] = [
                'type' => 'wms',
                'title' => $row->title,
                'layer_id' => $row->layer_id,
                'wmsUrl' => $wmsUrl,
                'layers' => $row->layers,
                'basemap_id' => $row->basemap_id,
                'content' => $row->content,
                'map_center' => $row->map_center,
                'map_zoom' => $row->map_zoom
            ];
		}
		pg_free_result($rows);
		
		
		$rows = $obj->getStorySectionById($story['id'], 'html');
		while($row = pg_fetch_object($rows)) {
            $_SESSION['content']['content-'.$row->section_order] = [
                'type' => 'html',
                'title' => $row->title,
                'content' => $row->content
            ];
		}
		pg_free_result($rows);
		
		
		$rows = $obj->getStorySectionById($story['id'], 'upload');
		while($row = pg_fetch_object($rows)) {
            $_SESSION['content']['content-'.$row->section_order] = [
                'type' => 'upload',
                'title' => $row->title,
                'content' => $row->content,
                'geojson' => 'file',
                'style' => [
                    'fillColor'   => $row->fillcolor,
                    'strokeColor' => $row->strokecolor,
                    'strokeWidth' => $row->strokewidth,
                    'fillOpacity' => $row->fillopacity,
                    'pointRadius' => $row->pointradius
                    ]
                ];
		}
		pg_free_result($rows);
		
		$rows = $obj->getStorySectionById($story['id'], 'pg');
		while($row = pg_fetch_object($rows)) {
            $_SESSION['content']['content-'.$row->section_order] = [
                'type' => 'pg',
                'title' => $row->title,
                'content' => $row->content,
                'pg_layer_id' => $row->pg_layer_id,
                'style' => [
                    'fillColor'   => $row->fillcolor,
                    'strokeColor' => $row->strokecolor,
                    'strokeWidth' => $row->strokewidth,
                    'fillOpacity' => $row->fillopacity,
                    'pointRadius' => $row->pointradius
                    ]
                ];
		}
		pg_free_result($rows);
		
		ksort($_SESSION['content'], SORT_NATURAL);

	}else if(!empty($_GET['is_new'])){
	    unset($_SESSION['story']);
		unset($_SESSION['content']);
	}else if(!empty($_SESSION['story'])){
	   $story = $_SESSION['story'];
	}

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
        
	<script type="text/javascript">

	$(document).ready(function() {
		
	    $('#geostory_form').submit(false);
		
		// Delete row on delete button click
		$(document).on("click", ".export", function() {
			let obj = $(this);
			let data = new FormData($('#geostory_form')[0]);
			data.append('export_type', obj.attr('data-export_type'));
			
			if(!$('#name').val()){
			  alert('Enter name for story!');
			}else{
         	  $.ajax({
  				type: "POST",
  				url: 'action/geostory.php',
  				data: data,
				processData: false,
				contentType: false,
  				dataType:"json",
    		      success: function(response){
          		    if(response.success) { // means, new record is added
                        window.location.href='geostories.php';
            		}
                      alert(response.message);
      		      }
              });
			}
		});
	});
	</script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            min-height: 100vh;
        }
        .main-container {
            display: flex;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .content-types {
            flex: 0 0 300px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .selected-area {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-height: 600px;
        }
        .content-type-card {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .content-type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        .content-type-card h3 {
            color: #333;
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
        }
        .content-type-card p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        .selected-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        .selected-item .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .selected-item .item-type {
            font-size: 0.8rem;
            color: #666;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .selected-item .item-actions {
            display: flex;
            gap: 10px;
        }
        .btn-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .empty-state {
            text-align: center;
            color: #666;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div id="container" style="display:block">
        <?php const NAV_SEL = 'Geostories'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
		
        <div class="main-container">
            <div class="content-types">
                <h2 style="color:black!important">Content Types</h2>
                <div class="content-type-card" onclick="addContent('wms')">
                    <h3 style="color:black!important">WMS Content</h3>
                    <p>Add Web Map Service layers</p>
                </div>
                <div class="content-type-card" onclick="addContent('html')">
                    <h3 style="color:black!important">HTML Content</h3>
                    <p>Add custom HTML content</p>
                </div>
                <div class="content-type-card" onclick="addContent('upload')">
                    <h3 style="color:black!important">Upload Content</h3>
                    <p>Upload and display shapefiles</p>
                </div>
                <div class="content-type-card" onclick="addContent('pg')">
                    <h3 style="color:black!important">PG Content</h3>
                    <p>Load GeoJSON from PG layer</p>
                </div>
            </div>
            
            <form id="geostory_form" class="border shadow p-3 rounded selected-area"
									action=""
									method="post"
									enctype="multipart/form-data">
            <div>
                <h2 style="color:black!important">Selected Content</h2>
                <div id="selectedContent">
                    <input type="hidden" name="action" value="save"/>
                    <input type="hidden" class="form-control" id="id" name="id" value="<?=$story['id']?>"/>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?=$story['name']?>" required />
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description" value="<?=$story['description']?>" required />
                    </div>
                    
                    <div class="form-group">
         				<label for="file" class="form-label">Thumbnail</label>
         				<input type="file" class="form-control" name="image" id="image" accept=".jpeg,.jpg,.png,.webp"/>
    				</div>
							
                    <div class="mb-3">
						<input type="checkbox" name="public" id="public" value="t" <?php if($story['public'] == 't') { echo 'checked'; }?>/>
						<label for="public" class="form-label">Public</label>
                    </div>
                    
                    <div class="form-group">
						<div class="input-group">
							<select name="group_id[]" id="group_id" multiple required>
								<?php $first = count($story['group_id']) ? false : true;
								foreach($groups as $k => $v){ ?>
									<option value="<?=$k?>" <?php if($first || in_array($k, $story['group_id'])) { echo 'selected'; } ?> ><?=$v?></option>
								<?php $first = false; } ?>
							</select>
							<span class="input-group-text"><i class="bi bi-shield-lock">Access Groups</i></span>
						</div>
					</div>
                    
                    <?php if (empty($_SESSION['content'])): ?>
                        <div class="empty-state">
                            <p>Click on content types to add them here</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($_SESSION['content'] as $id => $content): ?>
                            <div class="selected-item" id="<?php echo htmlspecialchars($id); ?>">
                                <div class="item-header">
                                    <div>
                                        <h3 style="margin: 0;"><?php echo htmlspecialchars($content['title']); ?></h3>
                                        <span class="item-type"><?php echo strtoupper($content['type']); ?></span>
                                    </div>
                                    <div class="item-actions">
                                        <a href="<?php echo $content['type']; ?>-editor.php?id=<?php echo urlencode($id); ?>" 
                                        class="btn btn-primary btn-sm">Edit</a>
                                        <a href="?remove=<?php echo urlencode($id); ?>" 
                                        class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Are you sure you want to remove this content?')">Remove</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($_SESSION['content'])): ?>
                    <div class="btn-group">
                        <button type="button" class="activate btn btn-success export" data-dismiss="modal" data-export_type="horizontal">Export Horizontal Story</button>
                        <button type="button" class="activate btn btn-info export" data-dismiss="modal" data-export_type="vertical">Export Vertical Story</button>
                    </div>
                <?php endif; ?>
            </div>
            </form>
        </div>
        <script>
            function addContent(type) {
                const id = 'content-' + Date.now();
                window.location.href = type + '-editor.php?id=' + id;
            }
        </script>
    </div>
<?php include("incl/footer.php"); ?>
</body>
</html>
