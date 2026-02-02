<?php
session_start(['read_and_close' => true]);
require('../incl/const.php');
require('../incl/app.php');
require('../incl/qgis.php');
require('../class/database.php');
require('../class/table.php');
require('../class/table_ext.php');
require('../class/qgs.php');
require('../class/layer.php');
require('../class/qgs_layer.php');
require('../class/pglink.php');
require('../class/access_key.php');

// Function to send JSON response
function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    
    // Ensure all data is JSON-serializable
    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    if ($json_data === false) {
        $error = json_last_error_msg();
        $data = ['error' => 'Error encoding response: ' . $error];
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    echo $json_data;
    exit(0);
}

// Function to resolve relative path
function resolvePath($relativePath, $basePath) {
    // Remove any leading ./ or ../
    $relativePath = preg_replace('/^\.\//', '', $relativePath);
    
    // If path starts with /, it's already absolute
    if (strpos($relativePath, '/') === 0) {
        return $relativePath;
    }
    
    // Otherwise, combine with base path
    return rtrim($basePath, '/') . '/' . $relativePath;
}

// Set error handler to return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    sendJsonResponse(['error' => "PHP Error: $errstr"], 500);
    return true;
});

// Set exception handler to return JSON
set_exception_handler(function($e) {
    sendJsonResponse(['error' => $e->getMessage()], 500);
});

try {
    // Check if query and database type are provided
    if (empty($_POST['id']) || empty($_POST['query']) || empty($_POST['databaseType'])) {
        sendJsonResponse(['error' => 'No id, query or database type provided'], 400);
    }

    $id = intval($_POST['id']); // qgs layer id
    $query = trim($_POST['query']);
    $databaseType = $_POST['databaseType'];

    if(!preg_match('/^SELECT /i', $query) || preg_match('/(CREATE|INSERT|DELETE|UPDATE|DROP) /i', $query)){
        sendJsonResponse(['error' => 'Only SELECT query is allowed'], 400);
    }
    
    // authentication checks
    $tbl = 'layer';
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
    $obj = new qgs_layer_Class($database->getConn(), SUPER_ADMIN_ID);
    
    $pg_res = $obj->getById($id);
    if(!$pg_res || (pg_num_rows($pg_res) == 0)){
        sendJsonResponse(['error' => 'Layer not found'], 404);
    }
	$pgr = pg_fetch_object($pg_res);
	pg_free_result($pg_res);
    
	$allow = false;
	if($pgr->public == 't'){
	    $allow = true;
	}else if(!empty($_POST['access_key'])){
		$allow = $database->check_key_tbl_access($tbl, $_POST['access_key'], $_SERVER['REMOTE_ADDR'], $id);
	}else if(isset($_SESSION[SESS_USR_KEY])) { 	// local access with login
		$allow = $database->check_user_tbl_access($tbl, $id, $_SESSION[SESS_USR_KEY]->id);
	}
	
	if(!$allow){
	    sendJsonResponse(['error' => 'Access not allowed'], 405);
	}
	
    $qgis_file = find_qgs(DATA_DIR.'/stores/'.$pgr->store_id);
    
    if (!file_exists($qgis_file)) {
        sendJsonResponse(['error' => 'QGIS project file not found'], 500);
    }

    if ($databaseType === 'gpkg') {

        // Get the base directory of the QGIS project
        $qgis_dir = dirname($qgis_file);

        // Load QGIS project XML
        $xml = simplexml_load_file($qgis_file);
        if (!$xml) {
            sendJsonResponse(['error' => 'Failed to load QGIS project'], 500);
        }

        // Find all datasource elements
        $datasources = $xml->xpath('//datasource');
        
        $gpkg_path = null;
        foreach ($datasources as $ds) {
            $path = (string)$ds;
            
            // Remove layer name if present
            $path = preg_replace('/\|.*$/', '', $path);
            
            // Check if it's a GeoPackage file
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'gpkg') {
                // Resolve relative path
                $absolute_path = resolvePath($path, $qgis_dir);
                
                if (file_exists($absolute_path)) {
                    $gpkg_path = $absolute_path;
                    break;
                } else {
                }
            }
        }

        if (!$gpkg_path) {
            sendJsonResponse(['error' => 'No accessible GeoPackage file found'], 500);
        }

        // Connect to the GeoPackage
        $db = new PDO('sqlite:' . $gpkg_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // List available tables
        $tables_query = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'gpkg_%' AND name NOT LIKE 'rtree_%'";
        $tables_stmt = $db->query($tables_query);
        $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    } else if ($databaseType === 'postgres') {

        // Set the PGSERVICEFILE environment variable
        if (!file_exists(PG_SERVICE_CONF)) {
            sendJsonResponse(['error' => 'pg_service.conf not found'], 500);
        }
        putenv("PGSERVICEFILE=".PG_SERVICE_CONF);

        // Load QGIS project XML
        $xml = simplexml_load_file($qgis_file);
        if (!$xml) {
            sendJsonResponse(['error' => 'Failed to load QGIS project'], 500);
        }

        // Find PostgreSQL layers by provider type
        $postgres_layers = $xml->xpath('//maplayer[provider="postgres"]');

        $postgres_conn = null;
        foreach ($postgres_layers as $layer) {
            $datasource = (string)$layer->datasource;
            
            // Check if it's a PostgreSQL connection string with service parameter
            if (preg_match("/service='([^']+)'/", $datasource, $matches)) {
                $service_name = $matches[1];
                
                // Connect using the service name
                try {
                    $db = new PDO("pgsql:service=$service_name");
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // List available tables
                    $tables_query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
                    $tables_stmt = $db->query($tables_query);
                    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                } catch (PDOException $e) {
                    continue;
                }
            } else {
            }
        }

        if (!isset($db)) {
            sendJsonResponse(['error' => 'No valid PostgreSQL connection found in QGIS project'], 500);
        }
    } else if ($databaseType === 'shp') {

        // Load QGIS project XML
        $xml = simplexml_load_file($qgis_file);
        if (!$xml) {
            sendJsonResponse(['error' => 'Failed to load QGIS project'], 500);
        }

        // Find ShapeFile layers
        $shp_layers = $xml->xpath('//maplayer[provider="ogr" and contains(datasource, ".shp")]');
        
        if (empty($shp_layers)) {
            sendJsonResponse(['error' => 'No ShapeFile layers found in QGIS project'], 500);
        }

        // Extract table name from query
        if (preg_match('/from\s+(\w+)/i', $query, $matches)) {
            $requested_table = strtolower($matches[1]);
        } else {
            sendJsonResponse(['error' => 'Invalid query format. Please specify a table name using FROM clause.'], 400);
        }
        
        // Find the matching ShapeFile
        $shp_path = null;
        $layer_name = null;
        $available_layers = [];
        
        foreach ($shp_layers as $layer) {
            $datasource = (string)$layer->datasource;
            
            // Extract the ShapeFile path
            if (preg_match('/\.shp/i', $datasource)) {
                // Resolve relative path
                $absolute_path = resolvePath($datasource, dirname($qgis_file));
                
                if (file_exists($absolute_path)) {
                    $current_layer = basename($absolute_path, '.shp');
                    $available_layers[] = $current_layer;
                    
                    if (strtolower($current_layer) === $requested_table) {
                        $shp_path = $absolute_path;
                        $layer_name = $current_layer;
                        break;
                    }
                } else {
                }
            }
        }

        if (!$shp_path) {
            sendJsonResponse([
                'error' => "Table '$requested_table' not found. Available tables: " . implode(', ', $available_layers)
            ], 404);
        }


        // Check if ogrinfo is available
        exec("which ogrinfo", $output, $return_var);
        if ($return_var !== 0) {
            sendJsonResponse(['error' => 'GDAL/OGR is not installed or not in PATH'], 500);
        }

        // Use GDAL/OGR to read the ShapeFile
        $command = "ogrinfo -so " . escapeshellarg($shp_path);
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            sendJsonResponse(['error' => 'Failed to read ShapeFile. Please check if the file is valid.'], 500);
        }

        // Parse the output to get field information
        $fields = [];
        $current_field = null;
        foreach ($output as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $matches)) {
                $current_field = $matches[1];
                $fields[$current_field] = $matches[2];
            }
        }

        // Validate the SQL query
        if (empty($query)) {
            sendJsonResponse(['error' => 'SQL query cannot be empty'], 400);
        }

        // Execute the query using ogrinfo
        // For ShapeFiles, we need to use a slightly different syntax
        $modified_query = str_replace(
            ['select * from ' . $layer_name . ';', 'SELECT * FROM ' . $layer_name . ';', 'select * from ' . $layer_name, 'SELECT * FROM ' . $layer_name],
            'SELECT * FROM ' . basename($shp_path, '.shp'),
            $query
        );
        
        // Remove any trailing semicolon as it's not needed for OGR SQL
        $modified_query = rtrim($modified_query, ';');

        // Now execute the query with -al flag to show all features
        $command = "ogrinfo -al -q " . escapeshellarg($shp_path) . " -sql " . escapeshellarg($modified_query);

        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        // Check if the command was successful
        if ($return_var !== 0) {
            sendJsonResponse(['error' => 'Failed to execute query. Please check your SQL syntax.'], 500);
        }

        // Check if we got any results
        if (empty($output)) {
            sendJsonResponse([
                'columns' => [],
                'rows' => [],
                'database_type' => $databaseType,
                'fields' => $fields,
                'layer_name' => $layer_name,
                'available_layers' => $available_layers,
                'message' => 'Query executed successfully but returned no results'
            ]);
        }

        // Parse the output to get results
        $rows = [];
        $current_row = [];
        $in_feature = false;
        $feature_count = 0;
        
        foreach ($output as $line) {
            $line = trim($line);
            
            // Skip empty lines and layer info lines
            if (empty($line) || strpos($line, 'Layer name:') === 0 || strpos($line, 'INFO:') === 0 || strpos($line, 'Metadata:') === 0) {
                continue;
            }
            
            // Start of a new feature
            if (preg_match('/^OGRFeature\(neighborhoods\):(\d+)$/', $line, $matches)) {
                if (!empty($current_row)) {
                    $rows[] = $current_row;
                }
                $current_row = [];
                $in_feature = true;
                $feature_count++;
                continue;
            }
            
            // Parse feature attributes
            if ($in_feature && preg_match('/^\s*(\w+)\s*\(([^)]+)\)\s*=\s*(.*)$/', $line, $matches)) {
                $field_name = $matches[1];
                $field_type = $matches[2];
                $field_value = trim($matches[3]);
                
                // Convert field value based on type
                switch (strtolower($field_type)) {
                    case 'real':
                        $field_value = (float)$field_value;
                        break;
                    case 'integer':
                        $field_value = (int)$field_value;
                        break;
                    case 'string':
                        // Remove quotes if present
                        $field_value = trim($field_value, "'\"");
                        break;
                }
                
                $current_row[$field_name] = $field_value;
            }
        }
        
        // Add the last row if it exists
        if (!empty($current_row)) {
            $rows[] = $current_row;
        }

        if (!empty($rows)) {
        }

        // Get column names from the first row
        $columns = !empty($rows) ? array_keys($rows[0]) : [];

        // If no rows were found, try a simpler query to verify the layer
        if (empty($rows)) {
            $simple_command = "ogrinfo -al -q " . escapeshellarg($shp_path);
            $simple_output = [];
            $simple_return_var = 0;
            exec($simple_command, $simple_output, $simple_return_var);
            
            if ($simple_return_var === 0) {
                sendJsonResponse([
                    'columns' => [],
                    'rows' => [],
                    'database_type' => $databaseType,
                    'fields' => $fields,
                    'layer_name' => $layer_name,
                    'available_layers' => $available_layers,
                    'message' => 'Query executed successfully but returned no results. Layer exists and is accessible.'
                ]);
            } else {
                sendJsonResponse(['error' => 'Failed to verify layer accessibility'], 500);
            }
        }

        // Send the response
        sendJsonResponse([
            'columns' => $columns,
            'rows' => $rows,
            'database_type' => $databaseType,
            'fields' => $fields,
            'layer_name' => $layer_name,
            'available_layers' => $available_layers,
            'feature_count' => $feature_count
        ]);
    } else if ($databaseType === 'gdb') {
     
        // Get the base directory of the QGIS project
        $qgis_dir = dirname($qgis_file);

        // Load QGIS project XML
        $xml = simplexml_load_file($qgis_file);
        if (!$xml) {
            sendJsonResponse(['error' => 'Failed to load QGIS project'], 500);
        }

        // Find all datasource elements
        $datasources = $xml->xpath('//datasource');
        
        if (empty($datasources)) {
            sendJsonResponse(['error' => 'No datasources found in QGIS project'], 500);
        }
        
        $gdb_path = null;
        $layer_name = null;
        $available_layers = [];
        
        error_log("Searching for Geodatabase in datasources...");
        foreach ($datasources as $ds) {
            $path = (string)$ds;
            
            // Check if it's a Geodatabase file
            if (strpos(strtolower($path), '.gdb') !== false) {
                
                // Extract the path before any parameters
                $path = preg_replace('/\|.*$/', '', $path);
                
                // Try different path resolution methods
                $possible_paths = [
                    $path, // Original path
                    resolvePath($path, $qgis_dir), // Relative to QGIS project
                    realpath($path), // Absolute path
                    realpath(resolvePath($path, $qgis_dir)) // Absolute path from QGIS project
                ];
                
                foreach ($possible_paths as $try_path) {
                    if ($try_path && file_exists($try_path)) {
                        $gdb_path = $try_path;
                        
                        // Try to get feature classes using different ogrinfo commands
                        $commands = [
                            "ogrinfo -so " . escapeshellarg($gdb_path),
                            "ogrinfo " . escapeshellarg($gdb_path),
                            "ogrinfo -ro " . escapeshellarg($gdb_path)
                        ];
                        
                        foreach ($commands as $cmd) {

                            $output = [];
                            $return_var = 0;
                            exec($cmd, $output, $return_var);
                            
                            if ($return_var === 0) {
                                foreach ($output as $line) {

                                    // Try different patterns to match feature class names
                                    if (preg_match('/^Layer:\s+(.+)\s+\(/', $line, $matches)) {
                                        $layer_name = $matches[1];
                                        $available_layers[] = $layer_name;
                                    }
                                    else if (preg_match('/^Layer name:\s+(.+)$/', $line, $matches)) {
                                        $layer_name = $matches[1];
                                        $available_layers[] = $layer_name;
                                    }
                                    else if (preg_match('/^Name:\s+(.+)$/', $line, $matches)) {
                                        $layer_name = $matches[1];
                                        $available_layers[] = $layer_name;
                                    }
                                }
                                
                                if (!empty($available_layers)) {
                                    break; // Stop if we found layers
                                }
                            }
                        }
                        
                        // If still no layers found, try listing the directory
                        if (empty($available_layers)) {
                            $command = "ls -la " . escapeshellarg($gdb_path);
                            $output = [];
                            $return_var = 0;
                            exec($command, $output, $return_var);
                        }
                        break; // Stop if we found a valid path
                    }
                }
                
                if ($gdb_path) {
                    break; // Stop if we found a valid Geodatabase
                }
            }
        }

        if (!$gdb_path) {
            sendJsonResponse(['error' => 'No valid Geodatabase path found in the QGIS project. Please check the datasource paths.'], 500);
        }

        if (empty($available_layers)) {
            sendJsonResponse([
                'error' => 'No feature classes found in the Geodatabase. Please check if the Geodatabase is valid and contains feature classes.',
                'gdb_path' => $gdb_path,
                'debug_info' => 'Check server logs for detailed information about the Geodatabase access attempt.'
            ], 500);
        }

        // Extract table name from query
        if (preg_match('/from\s+(\w+)/i', $query, $matches)) {
            $requested_table = strtolower($matches[1]);
        } else {
            sendJsonResponse(['error' => 'Invalid query format. Please specify a table name using FROM clause.'], 400);
        }

        // Check if the requested table exists
        $available_layers_lower = array_map('strtolower', $available_layers);
        if (!in_array($requested_table, $available_layers_lower)) {
            sendJsonResponse([
                'error' => "Table '$requested_table' not found. Available tables: " . implode(', ', $available_layers)
            ], 404);
        }

        // Get the actual layer name (case-sensitive) for the requested table
        $layer_index = array_search($requested_table, $available_layers_lower);
        $actual_layer_name = $available_layers[$layer_index];

        // Check if ogrinfo is available
        exec("which ogrinfo", $output, $return_var);
        if ($return_var !== 0) {
            sendJsonResponse(['error' => 'GDAL/OGR is not installed or not in PATH'], 500);
        }

        // Execute the query using ogrinfo
        $modified_query = str_replace(
            ['select * from ' . $requested_table . ';', 'SELECT * FROM ' . $requested_table . ';', 'select * from ' . $requested_table, 'SELECT * FROM ' . $requested_table],
            'SELECT * FROM ' . $actual_layer_name,
            $query
        );
        
        // Remove any trailing semicolon as it's not needed for OGR SQL
        $modified_query = rtrim($modified_query, ';');

        // Now execute the query with -al flag to show all features
        $command = "ogrinfo -al -q " . escapeshellarg($gdb_path) . " -sql " . escapeshellarg($modified_query);

        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        // Check if the command was successful
        if ($return_var !== 0) {
            sendJsonResponse(['error' => 'Failed to execute query. Please check your SQL syntax.'], 500);
        }

        // Check if we got any results
        if (empty($output)) {
            sendJsonResponse([
                'columns' => [],
                'rows' => [],
                'database_type' => $databaseType,
                'layer_name' => $requested_table,
                'available_layers' => $available_layers,
                'message' => 'Query executed successfully but returned no results'
            ]);
        }

        // Parse the output to get results
        $rows = [];
        $current_row = [];
        $in_feature = false;
        $feature_count = 0;
        
        foreach ($output as $line) {
            $line = trim($line);
            
            // Skip empty lines and layer info lines
            if (empty($line) || strpos($line, 'Layer name:') === 0 || strpos($line, 'INFO:') === 0 || strpos($line, 'Metadata:') === 0) {
                continue;
            }
            
            // Start of a new feature
            if (preg_match('/^OGRFeature\([^)]+\):(\d+)$/', $line, $matches)) {
                if (!empty($current_row)) {
                    $rows[] = $current_row;
                }
                $current_row = [];
                $in_feature = true;
                $feature_count++;
                continue;
            }
            
            // Parse feature attributes
            if ($in_feature && preg_match('/^\s*(\w+)\s*\(([^)]+)\)\s*=\s*(.*)$/', $line, $matches)) {
                $field_name = $matches[1];
                $field_type = $matches[2];
                $field_value = trim($matches[3]);
                
                // Convert field value based on type
                switch (strtolower($field_type)) {
                    case 'real':
                        $field_value = (float)$field_value;
                        break;
                    case 'integer':
                        $field_value = (int)$field_value;
                        break;
                    case 'string':
                        // Remove quotes if present
                        $field_value = trim($field_value, "'\"");
                        break;
                }
                
                $current_row[$field_name] = $field_value;
            }
        }
        
        // Add the last row if it exists
        if (!empty($current_row)) {
            $rows[] = $current_row;
        }

        // Get column names from the first row
        $columns = !empty($rows) ? array_keys($rows[0]) : [];

        // Send the response
        sendJsonResponse([
            'columns' => $columns,
            'rows' => $rows,
            'database_type' => $databaseType,
            'layer_name' => $requested_table,
            'available_layers' => $available_layers,
            'feature_count' => $feature_count
        ]);
    } else {
        sendJsonResponse(['error' => 'Invalid database type'], 400);
    }

    // Execute the user's query
    $stmt = $db->query($query);

    // Get column names
    $columns = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $columns[] = $stmt->getColumnMeta($i)['name'];
    }

    // Fetch all rows and ensure they're JSON-serializable
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        foreach ($row as $key => $value) {
            // Convert any non-serializable values to strings
            if (is_resource($value) || is_object($value)) {
                $row[$key] = (string)$value;
            }
        }
    }

    // Return results
    $response = [
        'columns' => $columns,
        'rows' => $rows,
        'available_tables' => $tables,
        'database_type' => $databaseType
    ];

    if ($databaseType === 'gpkg') {
        $response['gpkg_path'] = $gpkg_path;
    }

    sendJsonResponse($response);

} catch (PDOException $e) {
    sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    sendJsonResponse(['error' => $e->getMessage()], 500);
}
?>
