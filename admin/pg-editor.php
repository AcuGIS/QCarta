<?php

session_start();
require('incl/const.php');
require('class/database.php');
require('class/table.php');
require('class/table_ext.php');	
require('class/layer.php');
require('class/pg_layer.php');

if(!isset($_SESSION[SESS_USR_KEY])) {
    header('Location: ../login.php');
    exit(1);
}

if (!isset($_GET['id'])) {
    header('Location: geostory-editor.php');
    exit(0);
}

$id = $_GET['id'];
$content = $_SESSION['content'][$id] ?? [
    'type' => 'pg',
    'title' => 'New PG GeoJSON',
    'content' => '',
    'pg_layer_id' => '',
    'style' => [
        'fillColor' => '#3388ff',
        'strokeColor' => '#000000',
        'strokeWidth' => 1,
        'fillOpacity' => 0.4,
        'pointRadius' => 5
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content['title'] = $_POST['title'] ?? 'Untitled PG GeoJSON';
    $content['content'] = $_POST['content'] ?? '';
    $content['pg_layer_id'] = $_POST['pg_layer_id'];
    $content['style'] = [
        'fillColor' => $_POST['fillColor'] ?? '#3388ff',
        'strokeColor' => $_POST['strokeColor'] ?? '#000000',
        'strokeWidth' => floatval($_POST['strokeWidth'] ?? 1),
        'fillOpacity' => floatval($_POST['fillOpacity'] ?? 0.4),
        'pointRadius' => floatval($_POST['pointRadius'] ?? 5)
    ];
    
    // Debug logging
    // error_log('Saving content: ' . print_r($content, true));
    
    $_SESSION['content'][$id] = $content;
    header('Location: geostory-editor.php');
    exit(0);
}

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$obj = new pg_layer_Class($database->getConn(),    $_SESSION[SESS_USR_KEY]->id);
$layers = $obj->getArr();

$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
$auth_content = file_get_contents($proto.'://'.$_SERVER['HTTP_HOST'].'/admin/action/authorize.php?secret_key='.$_SESSION[SESS_USR_KEY]->secret_key.'&ip='.$_SERVER['REMOTE_ADDR']);
$auth = json_decode($auth_content);
$access_key = $auth->access_key; // Store access key for JavaScript

// Debug logging
// error_log('Current content: ' . print_r($content, true));
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
    <title>QCarta</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .editor-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .preview-container {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        #map {
            height: 400px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
    <script>
    const access_key = '<?=$access_key?>';
    </script>
</head>
<body>
    <div id="container" style="display:block">
        <?php const NAV_SEL = 'Geostories'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
        <div class="editor-container">
            <h2>PG GeoJSON Editor</h2>
            <form method="POST">
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                        value="<?php echo htmlspecialchars($content['title']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="pg_layer_id" class="form-label">PG Layer</label>
                    <select id="pg_layer_id" name="pg_layer_id">
                        <option value="">Select</option>
                    <?php foreach($layers as $id => $name){ ?>
                        <option value="<?=$id?>" <?php if($content['pg_layer_id'] == $id) { ?>selected<?php } ?>><?=$name?></option>
                    <?php } ?>
                    </select>
                </div>
    
                <div class="mb-3">
                    <h4>Layer Styling</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <label for="fillColor" class="form-label">Fill Color</label>
                            <input type="color" class="form-control form-control-color" id="fillColor" name="fillColor" 
                                value="<?php echo htmlspecialchars($content['style']['fillColor'] ?? '#3388ff'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="strokeColor" class="form-label">Stroke Color</label>
                            <input type="color" class="form-control form-control-color" id="strokeColor" name="strokeColor" 
                                value="<?php echo htmlspecialchars($content['style']['strokeColor'] ?? '#000000'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="strokeWidth" class="form-label">Stroke Width</label>
                            <input type="number" class="form-control" id="strokeWidth" name="strokeWidth" 
                                value="<?php echo htmlspecialchars($content['style']['strokeWidth'] ?? 1); ?>" min="0" max="10" step="0.5">
                        </div>
                        <div class="col-md-3">
                            <label for="fillOpacity" class="form-label">Fill Opacity</label>
                            <input type="range" class="form-range" id="fillOpacity" name="fillOpacity" 
                                value="<?php echo htmlspecialchars($content['style']['fillOpacity'] ?? 0.4); ?>" min="0" max="1" step="0.1">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <label for="pointRadius" class="form-label">Point Radius</label>
                            <input type="number" class="form-control" id="pointRadius" name="pointRadius" 
                                value="<?php echo htmlspecialchars($content['style']['pointRadius'] ?? 5); ?>" min="1" max="20" step="1">
                        </div>
                    </div>
                </div>
    
                <div class="mb-3">
                    <label for="content" class="form-label">Content</label>
                    <textarea id="content" name="content"><?php echo htmlspecialchars($content['content']); ?></textarea>
                    <div class="form-text">Add descriptive text that will appear next to the map</div>
                </div>
    
                <div class="preview-container">
                    <h4>Preview</h4>
                    <div id="map"></div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="geostory-editor.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/ol@v7.3.0/dist/ol.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v7.3.0/ol.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script src="assets/dist/js/pg_editor.js"></script>
</body>
</html>
