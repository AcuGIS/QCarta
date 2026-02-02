<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require('../admin/incl/const.php');
require('../admin/class/database.php');

try {
    $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
    $conn = $database->getConn();
    
    // Check what columns exist in geostory table
    $sql = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'geostory' AND table_schema = 'public' ORDER BY ordinal_position";
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
    
    // Try to get sample data
    $sample_sql = "SELECT * FROM public.geostory LIMIT 3";
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
        'geostory_table_columns' => $columns,
        'sample_data' => $sample_data
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
}
?>
