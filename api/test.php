<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'message' => 'API Test File Working',
    'server_vars' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT_SET',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT_SET',
        'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'NOT_SET',
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'NOT_SET',
        'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'NOT_SET',
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'NOT_SET',
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'NOT_SET'
    ],
    'current_file' => __FILE__,
    'current_dir' => __DIR__
]);
?>
