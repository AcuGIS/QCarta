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
$dbPath = isset($_GET['db']) ? $_GET['db'] : '';
if ($dbPath && isset($_POST['export_csv']) && isset($_POST['sql'])) {
    // Re-run the query to get the results for export
    $query = $_POST['sql'];
    $results = null;
    try {
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (preg_match('/^\s*select\b/i', $query)) {
            $stmt = $pdo->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        
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
$gpkgs = [];
foreach($stores as $id => $name){
    $gps = find_gpkgs(DATA_DIR.'/stores/'.$id);
    $prefix_len = strlen(DATA_DIR.'/stores/'.$id) + 1;
    foreach($gps as $gp){
        $label = '['.$name.']/'.substr($gp, $prefix_len);
        $gpkgs[$label] = $gp;
    }
}

// Database connection and table fetching logic moved here
$error = null;
$tables = [];
$tableColumns = [];
$results = null;
$query = '';

if ($dbPath && file_exists($dbPath)) {
    try {
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Get list of tables
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // Filter out tables starting with 'gpkg_' or 'rtree_'
        $tables = array_filter($allTables, function($name) {
            return !preg_match('/^(gpkg_|rtree_|sqlite_)/i', $name);
        });
        // Get column information for each table
        foreach ($tables as $table) {
            $stmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
            $tableColumns[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // Handle SQL query execution
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sql'])) {
            $query = $_POST['sql'];
            // Only allow SELECT queries
            if (preg_match('/^\s*select\b/i', $query)) {
                try {
                    $stmt = $pdo->query($query);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $error = "Query Error: " . $e->getMessage();
                }
            } else {
                $error = "Only SELECT queries are allowed.";
            }
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
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
    font-size: 22px !important; letter-spacing: 1px; margin: 10px 0 20px 0; background-color: #1E90FF !important; color: #AFE1AF!important!important; padding: 25px 0 15px 10px; width: 80%; font-weight: 500;">GeoPackage SQL Workshop</h1>
        <div class="tab-bar">
            <button class="tab-btn active" id="tab-sql" onclick="showTab('sql')">SQL Terminal</button>
            <button class="tab-btn" id="tab-vqb" onclick="showTab('vqb')">Visual Query Builder</button>
        </div>
        <div class="tab-bar">
            <!-- Database Selection Form -->
            <form method="GET" style="margin-bottom: 20px;">
                <label for="db">GeoPackage File Path:</label>
                <select name="db" id="db" style="width: 300px;">
                    <?php foreach($gpkgs as $k => $v) { ?>
                        <option value="<?=htmlspecialchars($v)?>" <?php if(isset($_GET['db']) && ($_GET['db'] == $v)) {echo 'selected'; }?> ><?=$k?></option>
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
                <?php if ($dbPath && !$error): ?>
                    <h3>Tables</h3>
                    <input type="text" class="search-box" id="tableSearch" placeholder="Search tables..." onkeyup="filterTables()">
                    <?php foreach ($tables as $table): ?>
                        <div class="table-list-item" id="item-<?php echo htmlspecialchars($table); ?>">
                            <div class="table-header" onclick="toggleTable(this, '<?php echo htmlspecialchars($table); ?>')">
                                <span class="table-icon">📄</span>
                                <?php echo htmlspecialchars($table); ?>
                            </div>
                            <div class="column-list" id="columns-<?php echo htmlspecialchars($table); ?>">
                                <?php foreach ($tableColumns[$table] as $column): ?>
                                    <div class="column-item" onclick="insertColumnName('<?php echo htmlspecialchars($table); ?>', '<?php echo htmlspecialchars($column['name']); ?>')">
                                        <?php echo htmlspecialchars($column['name']); ?>
                                        <span class="column-type"><?php echo htmlspecialchars($column['type']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #aaa; padding: 2em 0;">No database selected.</div>
                <?php endif; ?>
            </div>
            <div class="main-content">
                <?php if ($dbPath && !$error): ?>
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
                    <div style="color: #aaa; padding: 2em 0;">Please select a GeoPackage to begin.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
      let vqbTableData = <?=json_encode($tableColumns)?>;
      let vqbTableNames = <?=json_encode(array_values($tables))?>;
    </script>
    <script src="assets/dist/js/sql_workshop_gpkg.js"></script>
    <?php include("incl/footer.php"); ?>
</body>
</html>
