<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Test different ways to access the endpoint
$endpoint = $_GET['endpoint'] ?? '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

echo json_encode([
    'message' => 'Endpoint Test',
    'get_params' => [
        'endpoint' => $endpoint,
        'action' => $action,
        'id' => $id
    ],
    'server_vars' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT_SET',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT_SET',
        'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'NOT_SET',
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'NOT_SET',
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'NOT_SET',
        'ORIG_PATH_INFO' => $_SERVER['ORIG_PATH_INFO'] ?? 'NOT_SET'
    ],
    'current_file' => __FILE__,
    'current_dir' => __DIR__
]);
?>
