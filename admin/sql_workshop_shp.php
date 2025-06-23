<?php
session_start();
require('incl/const.php');
require('incl/app.php');
require('class/database.php');
require('class/table.php');
require('class/table_ext.php');    
require('class/qgs.php');

if(!isset($_SESSION[SESS_USR_KEY])) {
    header('Location: ../login.php');
    exit(1);
}

// Export to CSV if requested
$shpPath = isset($_GET['shp']) ? $_GET['shp'] : '';
if ($shpPath && isset($_POST['export_csv']) && isset($_POST['sql'])) {
    // Re-run the query to get the results for export
    $query = $_POST['sql'];
    $results = null;
    try {
        // Use OGR to execute the query
        $command = "ogrinfo -sql \"$query\" \"$shpPath\" -geom=NO";
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            // Process the output into a CSV-friendly format
            $results = processOgrOutput($output);
        }
    } catch (Exception $e) {
        // Handle error
    }
    
    if ($results && !empty($results)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="query_results.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($results[0]));
        foreach ($results as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit(0);
    }
}

$stores = [];
$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$obj = new qgs_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);

$stores = $obj->getArr();
$shapefiles = [];
foreach($stores as $id => $name){
    $shps = find_shapefiles(DATA_DIR.'/stores/'.$id);
    $prefix_len = strlen(DATA_DIR.'/stores/'.$id) + 1;
    foreach($shps as $shp){
        $label = '['.$name.']/'.substr($shp, $prefix_len);
        $shapefiles[$label] = $shp;
    }
}

// Helper function to find shapefiles
function find_shapefiles($dir) {
    $shapefiles = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $shapefiles = array_merge($shapefiles, find_shapefiles($path));
            } else if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'shp') {
                $shapefiles[] = $path;
            }
        }
    }
    return $shapefiles;
}

// Helper function to process OGR output into a structured array
function processOgrOutput($output) {
    $results = [];
    $currentRow = [];
    $headers = [];
    $inData = false;
    
    foreach ($output as $line) {
        
        if (strpos($line, 'OGRFeature') === 0) {
            if (!empty($currentRow)) {
                $results[] = $currentRow;
            }
            $currentRow = [];
            $inData = true;

        } elseif ($inData && strpos($line, '  ') === 0) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (!in_array($key, $headers)) {
                    $headers[] = $key;
                }
                $currentRow[$key] = $value;

            }
        }
    }
    
    if (!empty($currentRow)) {
        $results[] = $currentRow;
    }

    return $results;
}

// Get shapefile information and handle query execution
$error = null;
$fields = [];
$results = null;
$query = '';

if ($shpPath && file_exists($shpPath)) {
    
        // Ensure we're using absolute path
        $shpPath = realpath($shpPath);
        if (!$shpPath) {
            throw new Exception("Could not resolve absolute path for shapefile");
        }
        
        // Fix: Don't use escapeshellarg on the path
        $command = "ogrinfo -al -so " . $shpPath . " 2>&1";

        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $fields = [];
            $geometryType = '';
            foreach ($output as $line) {

                // Extract geometry type
                if (preg_match('/^\s*Geometry:\s*(\w+)/i', $line, $matches)) {
                    $geometryType = $matches[1];
                    $fields[] = [
                        'name' => 'geometry',
                        'type' => $geometryType,
                        'is_geometry' => true
                    ];
                }
                // Match lines like: field_name: Type (size)
                if (preg_match('/^\s*([a-zA-Z0-9_]+):\s+([a-zA-Z0-9_]+)\s*\(([^)]*)\)/', $line, $matches)) {
                    $fields[] = [
                        'name' => $matches[1],
                        'type' => $matches[2],
                        'is_geometry' => false
                    ];
                }
            }
            
            // If no attribute fields were found, add an informational message
            $hasAttributeFields = false;
            foreach ($fields as $field) {
                if (!isset($field['is_geometry']) || !$field['is_geometry']) {
                    $hasAttributeFields = true;
                    break;
                }
            }
            
            if (!$hasAttributeFields) {
                $fields[] = [
                    'name' => 'No attribute fields found',
                    'type' => 'Note: This shapefile only contains geometry data',
                    'is_info' => true
                ];
            }
        }

        // Handle SQL query execution only if it's a POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sql'])) {
            $query = $_POST['sql'];
            
            // Only allow SELECT queries
            if (preg_match('/^\s*select\b/i', $query)) {
                // For geometry queries, we need to include the geometry
                $geom_param = stripos($query, 'geometry') !== false ? '' : '-geom=NO';
                
                // Fix: Don't use escapeshellarg on the path since it's already safe
                $command = "ogrinfo -sql " . escapeshellarg($query) . " " . $shpPath . " " . $geom_param . " 2>&1";

                exec($command, $output, $return_var);
                
                if ($return_var === 0) {
                    $results = processOgrOutput($output);
                }
            }
        }
}

// After extracting $fields, filter out the geometry field for display
$displayFields = array_filter($fields, function($field) {
    return !isset($field['is_geometry']) || !$field['is_geometry'];
});

// Set the table name based on the shapefile
$tableName = isset($shpPath) ? basename($shpPath, '.shp') : 'shapefile';
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
    <link href="assets/dist/css/sql_workshop.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsPlumb/2.15.6/js/jsplumb.min.js"></script>
</head>
<body>
    <div id="container" style="display:block">
        <?php const NAV_SEL = 'Layers'; const TOP_PATH='../'; const ADMIN_PATH='';
                    include("incl/navbar.php"); ?>
        <br class="clear">
        <?php include("incl/sidebar.php"); ?>

    <div class="main-page-content" style="padding: 0rem 0rem 0rem 0rem;">
        <h1 style="color:#fff!important; background:dodgerblue!important; font-weight:400!important; important; font-family: Century Gothic!important;
    font-size: 22px !important; letter-spacing: 1px; margin: 10px 0 20px 0; background-color: #1E90FF !important; color: #AFE1AF!important!important; padding: 25px 0 15px 10px; width: 80%; font-weight: 500;">Shapefile SQL Workshop</h1>
        <div class="tab-bar">
            <button class="tab-btn active" id="tab-sql" onclick="showTab('sql')">SQL Terminal</button>
            <button class="tab-btn" id="tab-vqb" onclick="showTab('vqb')">Visual Query Builder</button>
        </div>
        <div class="tab-bar">
            <!-- Shapefile Selection Form -->
            <form method="GET" style="margin-bottom: 20px;">
                <label for="shp">Shapefile Path:</label>
                <select name="shp" id="shp" style="width: 300px;">
                    <?php foreach($shapefiles as $k => $v) { ?>
                        <option value="<?=htmlspecialchars($v)?>" <?php if(isset($_GET['shp']) && ($_GET['shp'] == $v)) {echo 'selected'; }?> ><?=$k?></option>
                    <?php } ?>
                </select>
                <button type="submit">Connect</button>
            </form>
        </div>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="sql-container" style="width:100%!important">
            <div class="tables-list">
                <?php if ($shpPath && !$error): ?>
                    <h3>Fields</h3>
                    <input type="text" class="search-box" id="tableSearch" placeholder="Search fields..." onkeyup="filterTables()">
                    <div class="table-list-item" id="item-shapefile">
                        <div class="table-header expanded" onclick="toggleTable(this, 'shapefile')">
                            <span class="table-icon">📄</span>
                            <span class="table-name"><?php echo htmlspecialchars(basename($shpPath, '.shp')); ?></span>
                        </div>
                        <div class="column-list" id="columns-shapefile">
                            <?php if (!empty($displayFields)): ?>
                                <?php foreach ($displayFields as $field): ?>
                                    <?php if (isset($field['is_info'])): ?>
                                        <div class="column-item info-message">
                                            <span class="column-name"><?php echo htmlspecialchars($field['name']); ?></span>
                                            <span class="column-type">(<?php echo htmlspecialchars($field['type']); ?>)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="column-item" draggable="true" onclick="insertColumnName('shapefile', '<?php echo htmlspecialchars($field['name']); ?>')">
                                            <span class="column-name"><?php echo htmlspecialchars($field['name']); ?></span>
                                            <?php if (isset($field['type'])): ?>
                                                <span class="column-type">(<?php echo htmlspecialchars($field['type']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-columns">No fields found in shapefile</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="color: #aaa; padding: 2em 0;">No shapefile selected.</div>
                <?php endif; ?>
            </div>
            <div class="main-content">
                <?php if ($shpPath && !$error): ?>
                    <!-- SQL Terminal Tab -->
                    <div class="tab-content active" id="content-sql">
                        <form method="POST">
                            <label class="sql-label" for="sql">SQL Terminal</label>
                            <textarea id="sql" name="sql" class="sql-input" placeholder="Enter your SQL query here..."><?php echo htmlspecialchars($query); ?></textarea>
                            <button type="submit">Execute Query</button>
                        </form>
                        <?php if ($results !== null && !empty($results)): ?>
                            <form method="post" action="" style="display:inline">
                                <input type="hidden" name="sql" value="<?php echo htmlspecialchars($query); ?>">
                                <input type="hidden" name="export_csv" value="1">
                                <button type="submit" style="margin-left:10px;">Export Results</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($results !== null): ?>
                            <div class="results">
                                <h3>Results</h3>
                                <?php if (empty($results)): ?>
                                    <p>Query executed successfully. No results returned.</p>
                                <?php else: ?>
                                    <table>
                                        <thead>
                                            <tr>
                                                <?php foreach (array_keys($results[0]) as $column): ?>
                                                    <th><?php echo htmlspecialchars($column); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?php echo htmlspecialchars($value); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Visual Query Builder Tab -->
                    <div class="tab-content" id="content-vqb">
                        <div style="margin-bottom:10px;">
                            <button class="vqb-btn" onclick="addVqbTable(event)">Add Table</button>
                            <button class="vqb-btn" onclick="generateVqbSql(event)">Generate SQL</button>
                            <button class="vqb-btn" onclick="copyVqbSql(event)">Copy SQL to Terminal</button>
                        </div>
                        <div id="vqb-canvas"></div>
                        <textarea id="vqb-sql" readonly placeholder="Generated SQL will appear here..."></textarea>
                    </div>
                <?php else: ?>
                    <div style="color: #aaa; padding: 2em 0;">Please select a shapefile to begin.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <script>
      let vqbTableName = <?=json_encode($tableName)?>;
      let vqbTableData[vqbTableName] = <?=json_encode(array_values($displayFields))?>;
    </script>
    <script src="assets/dist/js/sql_workshop_shp.js"></script>

    <style>
    .tables-list {
        width: 250px;
        padding: 10px;
        background: #fff;
        border-right: 1px solid #ddd;
        overflow-y: auto;
    }

    .table-list-item {
        margin-bottom: 10px;
    }

    .table-header {
        cursor: pointer;
        padding: 8px 10px;
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 3px;
        display: flex;
        align-items: center;
        user-select: none;
    }

    .table-header.expanded {
        background-color: #e9ecef;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }

    .table-icon {
        margin-right: 8px;
    }

    .table-name {
        font-weight: 500;
    }

    .column-list {
        padding: 5px 0;
        border: 1px solid #ddd;
        border-top: none;
        border-bottom-left-radius: 3px;
        border-bottom-right-radius: 3px;
        background-color: #fff;
        margin-top: -1px;
    }

    .column-item {
        padding: 6px 15px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f0f0f0;
    }

    .column-item:last-child {
        border-bottom: none;
    }

    .column-item:hover {
        background-color: #f8f9fa;
    }

    .column-name {
        font-family: monospace;
        color: #333;
    }

    .column-type {
        color: #666;
        font-size: 0.85em;
        margin-left: 8px;
    }

    .no-columns {
        padding: 10px;
        color: #666;
        font-style: italic;
        text-align: center;
    }

    .search-box {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 3px;
        box-sizing: border-box;
    }

    .geometry-field {
        background-color: #f0f8ff;
        border-left: 3px solid #1E90FF;
    }

    .info-message {
        background-color: #fff3cd;
        border-left: 3px solid #ffc107;
        font-style: italic;
    }

    .info-message:hover {
        background-color: #fff3cd;
    }

    .geometry-field:hover {
        background-color: #e6f3ff;
    }
    </style>
</body>
</html>
