<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require('../admin/incl/const.php');
require('../admin/class/database.php');

try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
    $conn = $database->getConn();
    
    // Test 1: Check if we can connect
    if (!$conn) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    // Test 2: Check what columns exist in layer table
    $sql = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'layer' AND table_schema = 'public' ORDER BY ordinal_position";
    $result = pg_query($conn, $sql);
    
    if (!$result) {
        echo json_encode(['error' => 'Schema query failed: ' . pg_last_error($conn)]);
        exit;
    }
    
    $columns = [];
    while ($row = pg_fetch_assoc($result)) {
        $columns[] = $row;
    }
    pg_free_result($result);
    
    // Test 3: Try a simple count query
    $count_sql = "SELECT COUNT(*) as total FROM public.layer";
    $count_result = pg_query($conn, $count_sql);
    
    if (!$count_result) {
        echo json_encode(['error' => 'Count query failed: ' . pg_last_error($conn)]);
        exit;
    }
    
    $count_row = pg_fetch_assoc($count_result);
    $total_count = $count_row['total'];
    pg_free_result($count_result);
    
    // Test 4: Try to get first few rows
    $sample_sql = "SELECT * FROM public.layer LIMIT 3";
    $sample_result = pg_query($conn, $sample_sql);
    
    if (!$sample_result) {
        echo json_encode(['error' => 'Sample query failed: ' . pg_last_error($conn)]);
        exit;
    }
    
    $sample_data = [];
    while ($row = pg_fetch_assoc($sample_result)) {
        $sample_data[] = $row;
    }
    pg_free_result($sample_result);
    
    echo json_encode([
        'success' => true,
        'database_connected' => true,
        'layer_table_columns' => $columns,
        'total_layers' => $total_count,
        'sample_data' => $sample_data
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
}
?>
