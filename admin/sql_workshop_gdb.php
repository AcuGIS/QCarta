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

$stores = [];
$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$obj = new qgs_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);

$stores = $obj->getArr();
$gdbs = [];
foreach($stores as $id => $name){
    $gps = find_gdbs(DATA_DIR.'/stores/'.$id);
    $prefix_len = strlen(DATA_DIR.'/stores/'.$id) + 1;
    foreach($gps as $gp){
        $label = '['.$name.']/'.substr($gp, $prefix_len);
        $gdbs[$label] = $gp;
    }
}

// Initialize variables
$layers = [];
$layerFields = [];
$error = null;
$results = null;
$query = '';

$gdbPath = isset($_GET['gdb']) ? $_GET['gdb'] : '';

// List layers and fields
if ($gdbPath && is_dir($gdbPath)) {
    // First try a simple list of layers
    $cmd = 'ogrinfo -ro -q "' . $gdbPath . '"';

    $ogrinfoLayers = shell_exec($cmd);
 
    if ($ogrinfoLayers === null) {
        $error = "Could not read geodatabase. Check path and permissions.";
    } else {
        // Try to extract layer names - ogrinfo typically outputs "Layer: layername"
        $lines = explode("\n", $ogrinfoLayers);
        $layers = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Try different patterns that ogrinfo might use
            if (preg_match('/^Layer:\s*([^\s]+)/', $line, $matches) || 
                preg_match('/^([^\s]+)\s*\([^)]*\)$/', $line, $matches)) {
                $layerName = trim($matches[1]);
                if (!empty($layerName) && !in_array($layerName, $layers)) {
                    $layers[] = $layerName;

                }
            }
        }
        

        
        // If no layers found with -q, try without it
        if (empty($layers)) {
            $cmd = 'ogrinfo "' . $gdbPath . '"';

            $ogrinfoLayers = shell_exec($cmd);

            
            $lines = explode("\n", $ogrinfoLayers);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (preg_match('/^Layer:\s*([^\s]+)/', $line, $matches) || 
                    preg_match('/^([^\s]+)\s*\([^)]*\)$/', $line, $matches)) {
                    $layerName = trim($matches[1]);
                    if (!empty($layerName) && !in_array($layerName, $layers)) {
                        $layers[] = $layerName;

                    }
                }
            }
        }
        
        // Get fields for each layer
        foreach ($layers as $layer) {
            $cmd = 'ogrinfo -so "' . $gdbPath . '" "' . $layer . '"';

            $layerInfo = shell_exec($cmd);

            
            $fields = [];
            if ($layerInfo !== null) {
                $lines = explode("\n", $layerInfo);
                $startParsing = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (strpos($line, 'FID Column =') !== false) {
                        $startParsing = true;
                        continue;
                    }
                    
                    // Try different field patterns
                    if ($startParsing) {
                        if (preg_match('/^([A-Za-z0-9_]+):\s+([^(]+)(?:\([^)]+\))?$/', $line, $fieldMatch) ||
                            preg_match('/^([A-Za-z0-9_]+)\s+\(([^)]+)\)\s*=\s*.*$/', $line, $fieldMatch)) {
                            $fieldName = trim($fieldMatch[1]);
                            $fieldType = isset($fieldMatch[2]) ? trim($fieldMatch[2]) : 'Unknown';
                            
                            // Skip FID and Geometry columns
                            if (!in_array($fieldName, ['FID Column', 'Geometry Column', 'fid', 'SHAPE'])) {
                                $fields[] = [
                                    'name' => $fieldName,
                                    'type' => $fieldType
                                ];

                            }
                        }
                    }
                }
            }
            $layerFields[$layer] = $fields;

        }
    }
}

// Handle SQL query execution
if ($gdbPath && is_dir($gdbPath) && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sql'])) {
    $query = $_POST['sql'];
    
    if (preg_match('/^\s*select\b/i', $query)) {
        // Ensure we're using the correct path from the form
        $gdbPath = realpath($gdbPath);
        
        if (!$gdbPath || !is_dir($gdbPath)) {
            $error = "Invalid geodatabase path: " . $gdbPath;
            return;
        }

        // Try different ogrinfo command variations
        $commands = [
            // Try with SQLite dialect and explicit path
            'ogrinfo -ro -q -dialect SQLite -sql ' . escapeshellarg($query) . ' "' . $gdbPath . '"',
            // Try without dialect
            'ogrinfo -ro -q -sql ' . escapeshellarg($query) . ' "' . $gdbPath . '"',
            // Try with -so flag
            'ogrinfo -ro -so -sql ' . escapeshellarg($query) . ' "' . $gdbPath . '"'
        ];
        
        $ogrinfoSql = null;
        $lastError = null;
        
        foreach ($commands as $cmd) {
            $output = shell_exec($cmd . ' 2>&1'); // Capture both stdout and stderr
            if ($output !== null) {
                // Check if the output contains an error message
                if (strpos($output, 'ERROR') !== false || strpos($output, 'error') !== false) {
                    $lastError = $output;
                    continue; // Try next command
                }
                
                // If we got here, the command worked
                $ogrinfoSql = $output;
                break;
            } else {
                $lastError = "Command returned null output";
            }
        }
        
        if ($ogrinfoSql === null) {
            $error = "Could not execute query. Last error: " . ($lastError ?? "Unknown error");
        } else {
            // Parse the output
            $lines = explode("\n", $ogrinfoSql);
            $data = [];
            $columns = [];
            $inFeature = false;
            $row = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                

                
                // Look for feature start - try different patterns
                if (preg_match('/^OGRFeature\(([^)]+)\):(\d+)$/', $line, $m) || 
                    preg_match('/^Feature\s+(\d+):$/', $line, $m)) {
                    if ($row) {
                        $data[] = $row;
                    }
                    $row = [];
                    $inFeature = true;
                    continue;
                }
                
                // Look for field values - try different patterns
                if ($inFeature) {
                    if (preg_match('/^\s*([A-Za-z0-9_]+)\s+\(([^)]+)\)\s*=\s*(.*)$/', $line, $m) ||
                        preg_match('/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
                        $fieldName = trim($m[1]);
                        $fieldValue = isset($m[3]) ? trim($m[3]) : trim($m[2]);
                        $fieldType = isset($m[2]) && isset($m[3]) ? trim($m[2]) : 'String';
                        
                        // Skip geometry fields unless specifically requested
                        if ($fieldType !== 'MULTIPOLYGON' || strpos($query, 'SHAPE') !== false) {
                            $row[$fieldName] = $fieldValue;
                            if (!in_array($fieldName, $columns)) {
                                $columns[] = $fieldName;
                            }

                        }
                    }
                }
            }
            
            // Add the last row if exists
            if ($row) {
                $data[] = $row;
            }
            
            if (!empty($data)) {
                $results = [
                    'columns' => $columns,
                    'rows' => $data
                ];

            } else {

                $results = [
                    'columns' => [],
                    'rows' => []
                ];
            }
        }
    } else {
        $error = "Only SELECT queries are allowed.";

    }
}

// Handle CSV export
if ($gdbPath && isset($_POST['export_csv']) && isset($_POST['sql'])) {
    if ($results && !empty($results['rows'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="query_results.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $results['columns']);
        foreach ($results['rows'] as $row) {
            $line = [];
            foreach ($results['columns'] as $col) {
                $line[] = isset($row[$col]) ? $row[$col] : '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit(0);
    }
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
	<link href="assets/dist/css/sql_workshop_gdb.css" rel="stylesheet">
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
    font-size: 22px !important; letter-spacing: 1px; margin: 10px 0 20px 0; background-color: #1E90FF !important; color: #AFE1AF!important!important; padding: 25px 0 15px 10px; width: 80%; font-weight: 500;">GeoPackage SQL Workshop</h1>
        <form method="GET" style="margin-bottom: 20px;">
            <label for="gdb">File Geodatabase Directory Path:</label>
            <select name="gdb" id="gdb" style="width: 300px;">
                <?php foreach($gdbs as $k => $v) { ?>
                    <option value="<?=htmlspecialchars($v)?>" <?php if(isset($_GET['gdb']) && ($_GET['gdb'] == $v)) {echo 'selected'; }?> ><?=$k?></option>
                <?php } ?>
            </select>
            <button type="submit">Connect</button>
        </form>

        <?php if ($error){ ?>
            <div class="error"><?=htmlspecialchars($error)?></div>
        <?php } ?>

        <?php if ($gdbPath && !$error && is_dir($gdbPath)): ?>
            <div class="tab-bar">
                <button class="tab-btn active" id="tab-sql" onclick="showTab('sql')">SQL Terminal</button>
                <button class="tab-btn" id="tab-vqb" onclick="showTab('vqb')">Visual Query Builder</button>
            </div>
            <div style="display: flex; width: 100%; align-items: flex-start; gap: 32px;">
                <div class="tables-list">
                    <h3>Layers</h3>
                    <input type="text" class="search-box" id="tableSearch" placeholder="Search layers..." onkeyup="filterTables()">
                    <?php 
                    if (!empty($layers)) {
                        foreach ($layers as $layer): 
                            $layerId = htmlspecialchars($layer);
                    ?>
                        <div class="table-list-item" id="item-<?=$layerId?>">
                            <div class="table-header" onclick="toggleTable(this, '<?=$layerId?>')">
                                <span class="table-icon">📄</span>
                                <?php echo $layerId; ?>
                            </div>
                            <div class="column-list" id="columns-<?=$layerId?>">
                                <?php 
                                if (isset($layerFields[$layer])) {
                                    foreach ($layerFields[$layer] as $field): 
                                        $fieldName = htmlspecialchars($field['name']);
                                        $fieldType = htmlspecialchars($field['type']);
                                ?>
                                    <div class="column-item" onclick="insertColumnName('<?=$layerId?>', '<?=$fieldName?>')">
                                        <?=$fieldName?>
                                        <span class="column-type"><?=$fieldType?></span>
                                    </div>
                                <?php 
                                    endforeach;
                                }
                                ?>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    } else {
                        echo '<div class="no-layers">No layers found in the geodatabase.</div>';
                    }
                    ?>
                </div>
                <div class="main-content">
                    <!-- SQL Terminal Tab -->
                    <div class="tab-content active" id="content-sql">
                        <div class="sql-container">
                            <form method="POST" id="sqlForm">
                                <input type="hidden" name="gdb" value="<?=htmlspecialchars($gdbPath)?>">
                                <label class="sql-label" for="sql">SQL Terminal</label>
                                <textarea id="sql" name="sql" class="sql-input" placeholder="Enter your SELECT SQL query here..."><?=htmlspecialchars($query)?></textarea>
                                <div class="button-group">
                                    <button type="submit">Execute Query</button>
                                    <?php if ($results !== null && !empty($results['rows'])): ?>
                                        <button type="submit" name="export_csv" value="1">Export Results</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                            <?php if ($results !== null): ?>
                                <div class="results">
                                    <h3>Results</h3>
                                    <?php if (empty($results['rows'])): ?>
                                        <p>Query executed successfully. No results returned.</p>
                                    <?php else: ?>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <?php foreach ($results['columns'] as $column): ?>
                                                        <th><?php echo htmlspecialchars($column); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($results['rows'] as $row): ?>
                                                    <tr>
                                                        <?php foreach ($results['columns'] as $col): ?>
                                                            <td><?php echo htmlspecialchars(isset($row[$col]) ? $row[$col] : ''); ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
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
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
      let vqbTableData = <?=json_encode($layerFields)?>;
      let vqbTableNames = <?=json_encode(array_values($layers))?>;
    </script>
    <script src="assets/dist/js/sql_workshop_gdb.js"></script>
</body>
</html>
