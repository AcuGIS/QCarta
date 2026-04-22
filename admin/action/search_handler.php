<?php
session_start();
require('../incl/const.php');
require('../class/database.php');
require('../class/table.php');
require('../class/table_ext.php');
require('../class/layer.php');
require('../class/qgs_layer.php');
require('../class/geostory.php');
require('../class/web_link.php');
require('../class/doc.php');
require('../class/dashboard.php');
require('../class/access_group.php');
require_once('../incl/report_builder_helpers.php');

// Perform searches based on filters
$results = ['layers' => [], 'stories' => [], 'links' => [], 'docs' => [], 'dashboards' => [], 'reports' => []];

// Get search parameters
$text = $_GET['text'] ?? '';
$topic = $_GET['topic'] ?? '';
$gemet = $_GET['gemet'] ?? '';
$keywords = !empty($_GET['keywords']) ? explode(',', $_GET['keywords']) : [];
$filters = !empty($_GET['filters']) ? explode(',', $_GET['filters']) : array_keys($results);
$bbox = !empty($_GET['bbox']) ? explode(',', $_GET['bbox']) : null;

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$conn = $database->getConn();

$user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;

$qgsLayer   = new qgs_layer_Class($conn, $user_id);
$geostory   = new geostory_Class($conn, $user_id);
$webLink    = new web_link_Class($conn, $user_id);
$doc        = new doc_Class($conn, $user_id);
$dash        = new dashboard_Class($conn, $user_id);

if(empty($_SESSION[SESS_USR_KEY])){
    $acc_cond = [
        'layers'  => 'l.public = true',
        'stories' => 'public = true',
        'links'   => 'public = true',
        'docs'    => 'public = true',
        'dashboards' => 'public = true'
    ];
}else{
    
    $acc_obj	= new access_group_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
	
	// super admin sees everything, other admins only owned
	$usr_grps = ($_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID) ? $acc_obj->getArr()
															    : $acc_obj->getByKV('user', $_SESSION[SESS_USR_KEY]->id);	
	$usr_grps_ids = implode(',', array_keys($usr_grps));
	// Empty IN () breaks PostgreSQL; use a sentinel that matches no real group id.
	if ($usr_grps_ids === '') {
		$usr_grps_ids = '-1';
	}
	
	// q.id IN (layers that user group(s) have access to)
    $acc_cond = [
        'layers'  => '(l.public = true OR l.id IN ('.$acc_obj->getGroupRowIds('layer', $usr_grps_ids).'))',
        'stories' => '(public = true OR id IN ('.$acc_obj->getGroupRowIds('geostory', $usr_grps_ids).'))',
        'links'   => '(public = true OR id IN ('.$acc_obj->getGroupRowIds('web_link', $usr_grps_ids).'))',
        'docs'    => '(public = true OR id IN ('.$acc_obj->getGroupRowIds('doc', $usr_grps_ids).'))',
        'dashboards'    => '(public = true OR id IN ('.$acc_obj->getGroupRowIds('dashboard', $usr_grps_ids).'))',
    ];
}

// Search only selected types
foreach ($filters as $filter) {
    switch ($filter) {
        case 'layers':
            $result = $qgsLayer->search($acc_cond[$filter], $text, $topic, $gemet, $keywords, $bbox);
            if ($result) {
                while ($row = pg_fetch_assoc($result)) {
                    $image = file_exists("../../assets/layers/".$row['id'].".png") ? 
                        "assets/layers/".$row['id'].".png" : 
                        "assets/layers/default.png";
                    $center = $qgsLayer->getLayerCenter($row['id']);
                    $results['layers'][] = [
                        'id' => $row['id'],
                        'type' => 'map',
                        'name' => str_replace('_', ' ', $row['name']),
                        'description' => $row['description'],
                        'url' => "layers/{$row['id']}/index.php",
                        'last_updated' => substr($row['last_updated'], 0, -7),
                        'image' => $image.'?v='.filemtime('../../'.$image),
                        'lat' => $center ? $center['lat'] : null,
                        'lng' => $center ? $center['lng'] : null
                    ];
                }
            }
            if ($result) {
                pg_free_result($result);
            }
            break;

        case 'stories':
            $result = $geostory->search($acc_cond[$filter], $text, $topic, $gemet, $keywords);
            if ($result) {
                while ($row = pg_fetch_assoc($result)) {
                    $image = file_exists("../../assets/geostories/".$row['id'].".png") ? 
                        "assets/geostories/".$row['id'].".png" : 
                        "assets/geostories/default.png";
                    $results['stories'][] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'url' => "geostories/{$row['id']}/index.php",
                        'last_updated' => substr($row['last_updated'], 0, -7),
                        'image' => $image.'?v='.filemtime('../../'.$image)
                    ];
                }
            }
            if ($result) {
                pg_free_result($result);
            }
            break;

        case 'links':
            $result = $webLink->search($acc_cond[$filter], $text, $topic, $gemet, $keywords);
            if ($result) {
                while ($row = pg_fetch_assoc($result)) {
                    $image = file_exists("../../assets/links/".$row['id'].".png") ? 
                        "assets/links/".$row['id'].".png" : 
                        "assets/links/default.png";
                    $results['links'][] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'url' => $row['url'],
                        'last_updated' => substr($row['last_updated'], 0, -7),
                        'image' => $image.'?v='.filemtime('../../'.$image)
                    ];
                }
            }
            if ($result) {
                pg_free_result($result);
            }
            break;
        
        case 'docs':
            $result = $doc->search($acc_cond[$filter], $text, $topic, $gemet, $keywords);
            if ($result) {
                while ($row = pg_fetch_assoc($result)) {
                    $image = "assets/docs/default.png";
                    if(file_exists("../../assets/docs/".$row['id'].".png")) {
                        $image = "assets/docs/".$row['id'].".png";
                    } else {
                        $ext = pathinfo($row['filename'], PATHINFO_EXTENSION);
                        if(file_exists("../../assets/docs/".$ext.".png")) {
                            $image = "assets/docs/".$ext.".png";
                        }
                    }
                    $results['docs'][] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'url' => "doc_file.php?id={$row['id']}",
                        'last_updated' => substr($row['last_updated'], 0, -7),
                        'image' => $image.'?v='.filemtime('../../'.$image)
                    ];
                }
                pg_free_result($result);
            }
            break;
        case 'dashboards':
            $result = $dash->search($acc_cond[$filter], $text, $topic, $gemet, $keywords);
            if ($result) {
                while ($row = pg_fetch_assoc($result)) {
                    $image = "assets/dashboards/default.png";
                    if(file_exists("../../assets/dashboards/".$row['id'].".png")) {
                        $image = "assets/dashboards/".$row['id'].".png";
                    }
                    
                    $results['dashboards'][] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'url' => "dashboard.php?id={$row['id']}",
                        'last_updated' => substr($row['last_updated'], 0, -7),
                        'image' => $image.'?v='.filemtime('../../'.$image)
                    ];
                }
                pg_free_result($result);
            }
            break;
        case 'reports':
            // Reports currently don't carry Topic/GEMET taxonomy links.
            // When those filters are active, do not return all reports as false positives.
            if (trim((string)$topic) !== '' || trim((string)$gemet) !== '') {
                break;
            }
            $whereClauses = ["(r.is_internal IS NULL OR r.is_internal = false)"];
            $params = [];
            if ($text !== '') {
                $params[] = '%' . $text . '%';
                $whereClauses[] = "(r.title ILIKE $" . count($params) . " OR COALESCE(r.description,'') ILIKE $" . count($params) . ")";
            }
            if (!empty($keywords)) {
                foreach ($keywords as $kw) {
                    $kw = trim((string)$kw);
                    if ($kw === '') {
                        continue;
                    }
                    $params[] = '%' . $kw . '%';
                    $whereClauses[] = "(r.title ILIKE $" . count($params) . " OR COALESCE(r.description,'') ILIKE $" . count($params) . ")";
                }
            }

            $sql = "SELECT r.id, r.title, r.description, r.updated_at
                    FROM reports r
                    WHERE " . implode(' AND ', $whereClauses) . "
                    ORDER BY r.updated_at DESC NULLS LAST, r.id DESC";
            $rres = !empty($params) ? pg_query_params($conn, $sql, $params) : pg_query($conn, $sql);
            if ($rres) {
                while ($row = pg_fetch_assoc($rres)) {
                    $rid = intval($row['id']);
                    if ($rid <= 0 || !canViewReport($rid, $conn)) {
                        continue;
                    }
                    $image = "assets/docs/default.png";
                    if (file_exists("../../assets/reports/" . $rid . ".png")) {
                        $image = "assets/reports/" . $rid . ".png";
                    }
                    $results['reports'][] = [
                        'id' => $rid,
                        'name' => $row['title'] ?? ('Report ' . $rid),
                        'description' => $row['description'] ?? '',
                        'url' => "admin/report_builder/view_report.php?id={$rid}",
                        'last_updated' => !empty($row['updated_at']) ? substr($row['updated_at'], 0, -7) : null,
                        'image' => $image . '?v=' . filemtime('../../' . $image)
                    ];
                }
                pg_free_result($rres);
            }
            break;
    }
}

header('Content-Type: application/json');
echo json_encode($results);
?>
