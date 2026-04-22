<?php

require_once __DIR__ . '/../admin/incl/const.php';
require_once __DIR__ . '/../admin/incl/qcarta_tile_project_key.php';

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

// Same sources as admin/action/clear_project_cache.php (const.php and/or environment).
$expectedToken = defined('QCARTA_CACHE_PURGE_TOKEN') ? QCARTA_CACHE_PURGE_TOKEN : getenv('QCARTA_CACHE_PURGE_TOKEN');
$expectedToken = is_string($expectedToken) ? $expectedToken : '';
if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
    http_response_code(401);
    echo "Unauthorized: Invalid token";
    exit;
}

// Prefer layer_id: resolves the same folder name as qcarta-tiles (basename of .qgs), not the admin map title.
$layer = '';
$usedLayerId = false;
$layerIdRaw = $_GET['layer_id'] ?? '';
if ($layerIdRaw !== '' && $layerIdRaw !== null && ctype_digit((string) $layerIdRaw)) {
    $layerId = (int) $layerIdRaw;
    if ($layerId > 0) {
        $usedLayerId = true;
        $layer = qcarta_tile_cache_project_key_for_layer_id($layerId);
    }
}

if ($layer === '') {
    if ($usedLayerId) {
        http_response_code(400);
        echo "Could not resolve tile cache folder from layers/" . (int) $layerIdRaw . "/env.php (QGIS path missing or unreadable).";
        exit;
    }
    $raw = $_GET['layer'] ?? '';
    if (!is_string($raw)) {
        http_response_code(400);
        echo "Bad request: provide layer_id or layer";
        exit;
    }
    $layer = trim($raw);
}

if ($layer === '' || strpos($layer, '..') !== false || preg_match('/^[a-zA-Z0-9._-]+$/', $layer) !== 1) {
    http_response_code(400);
    echo "Bad request: invalid or missing layer (use layer_id from the admin map row, or a valid cache folder name)";
    exit;
}

error_log('purge.php: handling purge for layer=' . $layer . ' via=' . ($usedLayerId ? 'layer_id' : 'layer_query'));

$baseCacheDir = '/var/www/data/qcarta-tiles/cache_data/wms';
$cacheDir = $baseCacheDir . '/' . $layer;

if (!is_dir($cacheDir)) {
    http_response_code(404);
    echo "Cache directory not found for layer";
    exit;
}

// Recursive delete
function deleteDir($dir) {
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;

        $path = $dir . '/' . $item;

        if (is_dir($path)) {
            deleteDir($path);
            rmdir($path); // <-- THIS is what you're missing
        } else {
            unlink($path);
        }
    }
    return true;
}
deleteDir($cacheDir);

echo "Cache purged successfully";
