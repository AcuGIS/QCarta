<?php
session_start();
require('../incl/const.php');
require('../class/database.php');
require('../class/table.php');
require('../class/table_ext.php');
require('../class/basemap.php');
require('../class/access_group.php');

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$dbconn = $database->getConn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
}else{
    $action = 'list';
}

switch($action) {
    case 'create':
    case 'update':
        $data = [
            'id' => intval($_POST['id'] ?? 0),
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'url' => $_POST['url'] ?? '',
            'type' => $_POST['type'] ?? 'xyz',
            'attribution' => $_POST['attribution'] ?? '',
            'thumbnail' => $_POST['thumbnail'] ?? '',
            'min_zoom' => intval($_POST['min_zoom'] ?? 0),
            'max_zoom' => intval($_POST['max_zoom'] ?? 18),
            'public' => $_POST['public'] ?? 'f',
            'group_id' => $_POST['group_id'] ?? []
        ];
        
        if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Validation
        if (empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit;
        }
        
        if (empty($data['url'])) {
            echo json_encode(['success' => false, 'message' => 'URL is required']);
            exit;
        }
        
        if ($data['min_zoom'] > $data['max_zoom']) {
            echo json_encode(['success' => false, 'message' => 'Minimum zoom cannot be greater than maximum zoom']);
            exit;
        }
        
        // Validate URL format
        if (!filter_var($data['url'], FILTER_VALIDATE_URL) && !preg_match('/^https?:\/\/.*\{[xyz]\}.*/', $data['url'])) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid URL or tile template']);
            exit;
        }
        
        // Validate thumbnail filename (if provided)
        if (!empty($data['thumbnail'])) {
            // Try multiple possible paths for the images folder
            $possible_paths = [
                '/var/www/html/assets/images/' . $data['thumbnail'],
                __DIR__ . '/../../assets/images/' . $data['thumbnail'],
                __DIR__ . '/../../../assets/images/' . $data['thumbnail'],
                dirname(__DIR__) . '/../assets/images/' . $data['thumbnail']
            ];
            
            $file_found = false;
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $file_found = true;
                    break;
                }
            }
            
            if (!$file_found) {
                echo json_encode(['success' => false, 'message' => 'Thumbnail file "' . $data['thumbnail'] . '" not found in assets/images folder. Please check the filename and ensure the image exists.']);
                exit;
            }
        }
        
        try {
            if ($data['id'] > 0) {
                $basemap = new basemap_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);

                // Update existing basemap
                if (!$basemap->isOwnedByUs($data['id'])) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this basemap']);
                    exit;
                }
                
                $result = $basemap->update($data);
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Basemap updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update basemap']);
                }
            } else {
                // Create new basemap
                $result = $basemap->create($data);
                if ($result > 0) {
                    echo json_encode(['success' => true, 'message' => 'Basemap created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create basemap']);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid basemap ID']);
            exit;
        }
        
        $basemap = new basemap_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);

        if (!$basemap->isOwnedByUs($id)) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this basemap']);
            exit;
        }
        
        try {
            $result = $basemap->drop_access($id) && $basemap->delete($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Basemap deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete basemap']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
        
    case 'get':
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid basemap ID']);
            exit;
        }
        
        try {
            $user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;
            $basemap = new basemap_Class($dbconn, $user_id);
            $result = $basemap->getById($id);

            if ($result && pg_num_rows($result) > 0) {
                
                $row = pg_fetch_assoc($result);
                
                if (($row['public'] != 't') && !$basemap->isOwnedByUs($id)) {
                    echo json_encode(['success' => false, 'message' => 'You do not have permission to view this basemap']);
                    exit;
                }
                
                pg_free_result($result);
                
                // Get access groups for this basemap
                $access_query = "SELECT access_group_id FROM basemaps_access WHERE basemaps_id = " . $id;
                $access_result = pg_query($dbconn, $access_query);
                $group_ids = [];
                if ($access_result) {
                    while ($access_row = pg_fetch_assoc($access_result)) {
                        $group_ids[] = $access_row['access_group_id'];
                    }
                    pg_free_result($access_result);
                }
                
                $row['group_ids'] = $group_ids;
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Basemap not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
    case 'list':
        $user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;
        $basemap = new basemap_Class($dbconn, $user_id);
        if(empty($_SESSION[SESS_USR_KEY])){
            $result = $basemap->getPublic();
        }else{
            $acc_obj    = new access_group_Class($database->getConn(), $user_id);
            // super admin sees everything, other admins only owned
            $usr_grps = ($user_id == SUPER_ADMIN_ID) ? $acc_obj->getArr() : $acc_obj->getByKV('user', $user_id);
            $usr_grps_ids = implode(',', array_keys($usr_grps));
            
            $result     = $acc_obj->getGroupRows('basemaps', $usr_grps_ids);
        }

        $basemaps = [];
        while ($row = pg_fetch_assoc($result)) {
            $basemaps[] = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'description' => $row['description'] ?: '',
                'url' => $row['url'],
                'type' => $row['type'],
                'attribution' => $row['attribution'] ?: '',
                'min_zoom' => intval($row['min_zoom']),
                'max_zoom' => intval($row['max_zoom']),
                'public' => $row['public'] === 't',
                'owner_id' => intval($row['owner_id']),
                'thumbnail' => $row['thumbnail'] ? '/assets/images/' . $row['thumbnail'] : ''
            ];
        }
        
        pg_free_result($result);
        
        echo json_encode([
            'success' => true,
            'basemaps' => $basemaps
        ]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
