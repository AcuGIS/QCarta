<?php
require('../admin/incl/const.php');
require('../admin/class/database.php');
require('../admin/class/table.php');
require('../admin/class/table_ext.php');
require('../admin/class/layer.php');
require('../admin/class/qgs_layer.php');
require('../admin/class/geostory.php');
require('../admin/class/web_link.php');
require('../admin/class/doc.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Debug logging
/*
error_log("=== QCarta API Debug ===");
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME']);
error_log("PHP_SELF: " . $_SERVER['PHP_SELF']);
error_log("DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT']);
error_log("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'NOT_SET'));
error_log("PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'NOT_SET'));
error_log("ORIG_PATH_INFO: " . ($_SERVER['ORIG_PATH_INFO'] ?? 'NOT_SET'));
error_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'NOT_SET'));
*/

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$conn = $database->getConn();

$request_method = $_SERVER['REQUEST_METHOD'];

// Get endpoint directly from query parameters (most reliable method)
$endpoint = $_GET['endpoint'] ?? '';
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// If no endpoint in query params, try to extract from path (fallback)
if (empty($endpoint)) {
    $path_param = $_GET['path'] ?? '';
    
    // Method 1: Try query parameter (from .htaccess)
    if (!empty($path_param)) {
        $path_parts = explode('/', trim($path_param, '/'));
        $endpoint = isset($path_parts[0]) ? $path_parts[0] : '';
        $action = isset($path_parts[1]) ? $path_parts[1] : '';
        $id = isset($path_parts[2]) ? intval($path_parts[2]) : null;
        //error_log("Method 1 - Path from query param: " . $path_param);
    }

    // Method 2: Try to extract from REQUEST_URI if it contains more than just /api/
    if (empty($endpoint) && !empty($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
        if (preg_match('/^\/api\/([^\/\?]+)(?:\/([^\/\?]+))?(?:\/(\d+))?/', $uri, $matches)) {
            $endpoint = $matches[1] ?? '';
            $action = $matches[2] ?? '';
            $id = isset($matches[3]) ? intval($matches[3]) : null;
            //error_log("Method 2 - Extracted from REQUEST_URI: endpoint=$endpoint, action=$action, id=$id");
        }
    }

    // Method 3: Try to get from HTTP_REFERER if available
    if (empty($endpoint) && !empty($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        if (preg_match('/\/api\/([^\/\?]+)(?:\/([^\/\?]+))?(?:\/(\d+))?/', $referer, $matches)) {
            $endpoint = $matches[1] ?? '';
            $action = $matches[2] ?? '';
            $id = isset($matches[3]) ? intval($matches[3]) : null;
            //error_log("Method 3 - Extracted from HTTP_REFERER: endpoint=$endpoint, action=$action, id=$id");
        }
    }
}

// Debug logging for final values
//error_log("Final endpoint: " . $endpoint);
//error_log("Final action: " . $action);
//error_log("Final id: " . $id);

try {
    switch ($endpoint) {
        case 'maps':
            handleMaps($conn, $action, $id);
            break;
        case 'layers':
            handleLayers($conn, $action, $id);
            break;
        case 'geostories':
            handleGeostories($conn, $action, $id);
            break;
        default:
            //error_log("No endpoint matched: '" . $endpoint . "'");
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found', 'debug' => [
                'endpoint' => $endpoint,
                'action' => $action,
                'id' => $id,
                'path_param' => $_GET['path'] ?? '',
                'query_string' => $_SERVER['QUERY_STRING'] ?? 'NOT_SET',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NOT_SET',
                'http_referer' => $_SERVER['HTTP_REFERER'] ?? 'NOT_SET',
                'get_params' => $_GET
            ]]);
    }
} catch (Exception $e) {
    //error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleMaps($conn, $action, $id) {
    global $database;
    
    if ($action === 'list') {
        // Query the actual layer table to get maps
        $sql = "SELECT l.id, l.name, l.description, l.type, l.public, l.last_updated 
                FROM public.layer l 
                WHERE l.type IN ('qgs', 'pg') 
                ORDER BY l.id DESC";
        
        $result = pg_query($conn, $sql);
        if (!$result) {
            //error_log("Maps query error: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed']);
            return;
        }
        
        $maps = [];
        while ($row = pg_fetch_assoc($result)) {
            $maps[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'type' => $row['type'],
                'public' => $row['public'] === 't',
                'last_updated' => $row['last_updated']
            ];
        }
        pg_free_result($result);
        
        echo json_encode(['success' => true, 'data' => $maps]);
    } elseif ($action === 'get' && $id) {
        $sql = "SELECT l.id, l.name, l.description, l.type, l.public, l.last_updated 
                FROM public.layer l 
                WHERE l.id = $1 AND l.type IN ('qgs', 'pg')";
        
        $result = pg_query_params($conn, $sql, [$id]);
        if (!$result) {
            //error_log("Map get error: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed']);
            return;
        }
        
        $map = pg_fetch_assoc($result);
        pg_free_result($result);
        
        if ($map) {
            echo json_encode(['success' => true, 'data' => $map]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Map not found']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action or missing ID']);
    }
}

function handleLayers($conn, $action, $id) {
    global $database;
    
    if ($action === 'list') {
        // Query the actual layer table that exists
        $sql = "SELECT l.id, l.name, l.description, l.type, l.public, l.last_updated 
                FROM public.layer l 
                ORDER BY l.id DESC";
        
        $result = pg_query($conn, $sql);
        if (!$result) {
            //error_log("Layer query error: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed']);
            return;
        }
        
        $layers = [];
        while ($row = pg_fetch_assoc($result)) {
            $layers[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'type' => $row['type'],
                'public' => $row['public'] === 't',
                'last_updated' => $row['last_updated']
            ];
        }
        pg_free_result($result);
        
        echo json_encode(['success' => true, 'data' => $layers]);
    } elseif ($action === 'get' && $id) {
        $sql = "SELECT l.id, l.name, l.description, l.type, l.public, l.last_updated 
                FROM public.layer l 
                WHERE l.id = $1";
        
        $result = pg_query_params($conn, $sql, [$id]);
        if (!$result) {
            //error_log("Layer get error: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed']);
            return;
        }
        
        $layer = pg_fetch_assoc($result);
        pg_free_result($result);
        
        if ($layer) {
            echo json_encode(['success' => true, 'data' => $layer]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Layer not found']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action or missing ID']);
    }
}

function handleGeostories($conn, $action, $id) {
    global $database;
    
    if ($action === 'list') {
        // Query the actual geostory table that exists
        $sql = "SELECT g.id, g.title, g.description, g.created_at, g.updated_at 
                FROM public.geostory g 
                ORDER BY g.id DESC";
        
        $result = pg_query($conn, $sql);
        if (!$result) {
            //error_log("Geostory query error: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed']);
            return;
        }
        
        $geostories = [];
        while ($row = pg_fetch_assoc($result)) {
            $geostories[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        pg_free_result($result);
        
        echo json_encode(['success' => true, 'data' => $geostories]);
    } elseif ($action === 'get' && $id) {
        $sql = "SELECT g.id, g.title, g.description, g.created_at, g.updated_at 
                FROM public.geostory g 
                WHERE g.id = $1";
        
        $result = pg_query_params($conn, $sql, [$id]);
        if (!$result) {
            //error_log("Geostory get error: " . pg_last_error($conn));
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed']);
            return;
        }
        
        $geostory = pg_fetch_assoc($result);
        pg_free_result($result);
        
        if ($geostory) {
            echo json_encode(['success' => true, 'data' => $geostory]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Geostory not found']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action or missing ID']);
    }
}
?>
