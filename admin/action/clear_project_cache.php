<?php
session_start(['read_and_close' => true]);
require('../incl/const.php');

// Admin-only access check
if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required']);
	exit(1);
}

header('Content-Type: application/json');

/**
 * Extract and sanitize project key from QGIS filename path
 * @param string $path QGIS project filename path (decoded)
 * @return string Sanitized project key
 */
function qcarta_project_key_from_qgis_filename(string $path): string {
	$base = basename($path);
	$base = preg_replace('/\.(qgs|qgz)$/i', '', $base);
	$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
	$base = trim($base, "._-");
	return $base !== '' ? $base : 'unknown';
}

// Get layer_id from POST
$layerId = intval($_POST['layer_id'] ?? 0);
if ($layerId <= 0) {
	echo json_encode(['success' => false, 'message' => 'Invalid layer_id']);
	exit(1);
}

// Load layer's env.php
$envPath = WWW_DIR . "/layers/$layerId/env.php";
if (!file_exists($envPath)) {
	echo json_encode(['success' => false, 'message' => "Layer env.php not found: $envPath"]);
	exit(1);
}

require $envPath;

// Determine QGIS filename path
$qgisPath = '';
if (defined('QGIS_FILENAME') && QGIS_FILENAME !== 'none' && QGIS_FILENAME !== '') {
	$qgisPath = QGIS_FILENAME;
} elseif (defined('QGIS_FILENAME_ENCODED') && QGIS_FILENAME_ENCODED !== '' && QGIS_FILENAME_ENCODED !== 'none') {
	$qgisPath = urldecode(QGIS_FILENAME_ENCODED);
}

if ($qgisPath === '' || $qgisPath === 'none') {
	echo json_encode(['success' => false, 'message' => 'No QGIS project file found for this layer']);
	exit(1);
}

// Compute projectKey
$projectKey = qcarta_project_key_from_qgis_filename($qgisPath);

// Get purge token from constant (defined in const.php) or environment
$token = defined('QCARTA_CACHE_PURGE_TOKEN') ? QCARTA_CACHE_PURGE_TOKEN : getenv('QCARTA_CACHE_PURGE_TOKEN');
if (!$token || $token === 'CHANGE_THIS_TOKEN_IN_PRODUCTION') {
	echo json_encode(['success' => false, 'message' => 'QCARTA_CACHE_PURGE_TOKEN not configured. Please set it in admin/incl/const.php or as an environment variable.']);
	exit(1);
}

// Call qcarta-tiles purge endpoint
$ch = curl_init("http://127.0.0.1:8011/admin/cache/purge");
curl_setopt_array($ch, [
	CURLOPT_POST => true,
	CURLOPT_POSTFIELDS => $projectKey,
	CURLOPT_HTTPHEADER => [
		"Authorization: Bearer $token",
		"Content-Type: text/plain"
	],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT => 10,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($resp === false) {
	echo json_encode(['success' => false, 'message' => "cURL error: $err"]);
	exit(1);
}

if ($http < 200 || $http >= 300) {
	echo json_encode(['success' => false, 'message' => "HTTP error $http: $resp"]);
	exit(1);
}

// qcarta-tiles already returns JSON, so just pass it through
echo $resp;
?>
