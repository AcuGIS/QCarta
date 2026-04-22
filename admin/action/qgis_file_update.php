<?php
declare(strict_types=1);

session_start(['read_and_close' => true]);
require('../incl/const.php');
require('../class/database.php');
require('../class/table.php');
require('../class/table_ext.php');
require('../class/qgs.php');
require('../incl/qgis.php');

header('Content-Type: application/json; charset=utf-8');

/**
 * Attribute updates for file-backed QGIS layers (GeoPackage, Shapefile, OGR).
 * PostGIS / postgres layers should use oapif_update.php instead.
 */

function qfu_fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function qfu_ok(): void
{
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

function qfu_norm_key(string $k): string
{
    return preg_replace('/\s+/', '', strtolower($k));
}

function qfu_read_project_xml(string $mapFile): ?string
{
    if (!is_readable($mapFile)) {
        return null;
    }
    if (strtolower(pathinfo($mapFile, PATHINFO_EXTENSION)) === 'qgz') {
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($mapFile) !== true) {
            return null;
        }
        $qgs = $zip->getFromName('project.qgs');
        if ($qgs === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $nm = $st['name'] ?? '';
                if (is_string($nm) && preg_match('/\.qgs$/i', $nm)) {
                    $qgs = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();
        return is_string($qgs) ? $qgs : null;
    }
    $xml = @file_get_contents($mapFile);
    return is_string($xml) ? $xml : null;
}

/**
 * @return array<string,string> normalized alias => real field name
 */
function qfu_alias_map_from_project(string $mapFile, string $layerName): array
{
    $xml = qfu_read_project_xml($mapFile);
    if (!$xml) {
        return [];
    }
    $doc = @simplexml_load_string($xml);
    if (!$doc) {
        return [];
    }
    $xpath = sprintf("//maplayer[layername='%s']/aliases/alias", str_replace("'", "&apos;", $layerName));
    $nodes = $doc->xpath($xpath);
    if ($nodes === false) {
        return [];
    }
    $out = [];
    foreach ($nodes as $a) {
        $real = (string) ($a['field'] ?? '');
        $alias = (string) ($a['name'] ?? '');
        if ($real !== '' && $alias !== '') {
            $out[qfu_norm_key($alias)] = $real;
        }
    }
    return $out;
}

/**
 * @return array<int,string>
 */
function qfu_sqlite_table_columns(string $path, string $table): array
{
    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $safeTable = str_replace('"', '""', $table);
        $stmt = $pdo->query('PRAGMA table_info("' . $safeTable . '")');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $cols = [];
        foreach ($rows as $r) {
            if (isset($r['name']) && is_string($r['name'])) {
                $cols[] = strtolower($r['name']);
            }
        }
        return $cols;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * @return array<string,string> normalized col => actual col
 */
function qfu_sqlite_table_column_map(string $path, string $table): array
{
    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $safeTable = str_replace('"', '""', $table);
        $stmt = $pdo->query('PRAGMA table_info("' . $safeTable . '")');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $out = [];
        foreach ($rows as $r) {
            $name = $r['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $out[qfu_norm_key($name)] = $name;
            }
        }
        return $out;
    } catch (Exception $e) {
        return [];
    }
}

function qfu_where_clause_for_feature(array $cols, string $idRaw, string $idEscaped): ?string
{
    $has = array_flip($cols);
    $isInt = preg_match('/^-?\d+$/', $idRaw) === 1;
    // Numeric feature ids coming from QGIS are typically fid/id-based.
    // Prefer integer keys before uuid to avoid silent 0-row updates.
    if ($isInt && isset($has['fid'])) {
        return '"fid" = ' . (string) intval($idRaw);
    }
    if ($isInt && isset($has['id'])) {
        return '"id" = ' . (string) intval($idRaw);
    }
    if ($isInt && isset($has['ogc_fid'])) {
        return '"ogc_fid" = ' . (string) intval($idRaw);
    }
    if (isset($has['fid'])) {
        return '"fid" = \'' . $idEscaped . '\'';
    }
    if (isset($has['id'])) {
        return '"id" = \'' . $idEscaped . '\'';
    }
    if (isset($has['ogc_fid'])) {
        return '"ogc_fid" = \'' . $idEscaped . '\'';
    }
    if (isset($has['uuid'])) {
        return '"uuid" = \'' . $idEscaped . '\'';
    }
    return null;
}

function qfu_sqlite_row_exists(string $path, string $table, string $whereClause): bool
{
    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $safeTable = '"' . str_replace('"', '""', $table) . '"';
        $sql = 'SELECT COUNT(1) AS c FROM ' . $safeTable . ' WHERE ' . $whereClause;
        $stmt = $pdo->query($sql);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        return (int) ($row['c'] ?? 0) > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * @param mixed $g
 * @return array{0: float, 1: float}|null [lon, lat]
 */
function qfu_normalize_point_geometry($g): ?array
{
    if (!is_array($g)) {
        return null;
    }
    if (($g['type'] ?? '') !== 'Point') {
        return null;
    }
    $c = $g['coordinates'] ?? null;
    if (!is_array($c) || count($c) < 2) {
        return null;
    }
    $lon = (float) $c[0];
    $lat = (float) $c[1];
    if (!is_finite($lon) || !is_finite($lat)) {
        return null;
    }
    if ($lon < -180.0 || $lon > 180.0 || $lat < -90.0 || $lat > 90.0) {
        return null;
    }
    return [$lon, $lat];
}

if (!isset($_SESSION[SESS_USR_KEY])) {
    qfu_fail(401, 'Not authorized');
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    qfu_fail(400, 'Invalid JSON');
}

$layer_id = isset($data['layer_id']) ? (int) $data['layer_id'] : 0;
$featureId = $data['featureId'] ?? '';
$layer = isset($data['layer']) ? (string) $data['layer'] : '';
$updates = $data['updates'] ?? [];
if (!is_array($updates)) {
    qfu_fail(400, 'Invalid updates');
}
$geomPt = qfu_normalize_point_geometry($data['geometry'] ?? null);

if ($layer_id <= 0 || $layer === '' || ($updates === [] && $geomPt === null)) {
    qfu_fail(400, 'Missing layer_id, layer, featureId, or updates/geometry');
}

if (isset($updates['geometry']) || isset($updates['coordinates']) || isset($updates['type'])) {
    qfu_fail(400, 'Geometry edits are not allowed');
}

require(WWW_DIR . '/layers/' . $layer_id . '/env.php');
$map = urldecode(QGIS_FILENAME_ENCODED);
if ($map === '' || $map === 'none' || !file_exists($map)) {
    qfu_fail(500, 'QGIS project not found');
}

$store_id = basename(dirname($map));
$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$obj = new qgs_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
if ($store_id !== '' && !$obj->isOwnedByUs((int) $store_id)) {
    qfu_fail(403, 'Update forbidden');
}

$rawFeatureId = (string) $featureId;

// Extract after last dot (Apiary.xxx)
$fidPart = strpos($rawFeatureId, '.') !== false
    ? substr($rawFeatureId, strrpos($rawFeatureId, '.') + 1)
    : $rawFeatureId;

// DO NOT strip braces — your DB stores them
$uuid = $fidPart;

if ($uuid === '') {
    qfu_fail(400, 'Invalid feature id');
}
$qUuid = str_replace("'", "''", $uuid);

if (qgis_classify_layer_provider($map, $layer) === 'postgres') {
    qfu_fail(400, 'Use OAPIF update for PostGIS layers');
}

$resolved = qgis_resolve_vector_file_datasource($map, $layer);
if ($resolved === null) {
    qfu_fail(400, 'Could not resolve file datasource for layer');
}

$path = $resolved['path'];
if (!is_readable($path)) {
    qfu_fail(500, 'Data file is not readable');
}

$table = $resolved['table'] ?? null;
if (!is_string($table) || $table === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
    qfu_fail(400, 'Invalid table / layer name');
}
$qTable = '"' . str_replace('"', '""', $table) . '"';

$forbidden = ['fid', 'geom', 'geometry', 'uuid', 'id'];
$assignOgr = [];
$aliasMap = qfu_alias_map_from_project($map, $layer);
$colMap = $resolved['kind'] === 'gpkg' ? qfu_sqlite_table_column_map($path, $table) : [];

foreach ($updates as $k => $v) {
    if (!is_string($k) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $k)) {
        continue;
    }
    $keyForWrite = $k;
    $aliasKey = qfu_norm_key($k);
    if (isset($aliasMap[$aliasKey])) {
        $keyForWrite = $aliasMap[$aliasKey];
    }
    if ($resolved['kind'] === 'gpkg') {
        $colKey = qfu_norm_key($keyForWrite);
        if (!isset($colMap[$colKey])) {
            continue;
        }
        $keyForWrite = $colMap[$colKey];
    }
    if (in_array(strtolower($keyForWrite), $forbidden, true)) {
        continue;
    }
    $qk = '"' . str_replace('"', '""', $keyForWrite) . '"';
    if ($v === null) {
        $assignOgr[] = $qk . ' = NULL';
    } elseif (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v) && preg_match('/^-?\d+(\.\d+)?$/', (string) $v))) {
        $assignOgr[] = $qk . ' = ' . (string) $v;
    } else {
        $assignOgr[] = $qk . " = '" . str_replace("'", "''", (string) $v) . "'";
    }
}

$whereClause = '"uuid" = \'' . $qUuid . '\'';
if ($resolved['kind'] === 'gpkg') {
    $cols = qfu_sqlite_table_columns($path, $table);
    $wc = qfu_where_clause_for_feature($cols, $uuid, $qUuid);
    if ($wc === null) {
        qfu_fail(400, 'Could not determine feature key column (uuid/fid/id)');
    }
    $whereClause = $wc;
    if (!qfu_sqlite_row_exists($path, $table, $whereClause)) {
        qfu_fail(404, 'Feature row not found for id "' . $uuid . '"');
    }
}

$geomSet = null;
if ($geomPt !== null && $resolved['kind'] !== 'gpkg') {
    [$lon, $lat] = $geomPt;
    $geomSet = '"geom" = GeomFromText(\'POINT(' . $lon . ' ' . $lat . ')\')';
}

$setParts = $assignOgr;
if ($geomSet !== null) {
    $setParts[] = $geomSet;
}

if ($setParts === [] && $geomPt === null) {
    qfu_fail(400, 'No valid editable fields found');
}

$setClause = $setParts === [] ? '' : implode(', ', $setParts);

if ($resolved['kind'] === 'gpkg') {
    if ($geomPt !== null) {
        $lon = floatval($data['geometry']['coordinates'][0]);
        $lat = floatval($data['geometry']['coordinates'][1]);

        $x = $lon * 20037508.34 / 180;
        $y = log(tan((90 + $lat) * M_PI / 360)) / (M_PI / 180);
        $y = $y * 20037508.34 / 180;

        $sql = 'UPDATE "' . str_replace('"', '""', $table) . '" SET geom = GeomFromText(\'POINT(' . $x . ' ' . $y . ')\') WHERE ' . $whereClause;

        $cmd = 'ogr2ogr -f GPKG ' . escapeshellarg($path) . ' '
            . escapeshellarg($path) . ' '
            . '-dialect SQLite '
            . '-sql ' . escapeshellarg($sql) . ' '
            . '-update';

        exec($cmd, $out, $code);

        if ($code !== 0) {
            qfu_fail(500, 'ogr2ogr failed: ' . implode("\n", $out));
        }
    }

    if ($assignOgr !== []) {
        $sqlAttr = 'UPDATE ' . $qTable . ' SET ' . implode(', ', $assignOgr) . ' WHERE ' . $whereClause;
        $cmdAttr = 'ogrinfo -q -dialect SQLite -update '
            . escapeshellarg($path)
            . ' -sql ' . escapeshellarg($sqlAttr)
            . ' 2>&1';
        exec($cmdAttr, $outAttr, $codeAttr);
        if ($codeAttr !== 0) {
            qfu_fail(500, 'ogrinfo failed: ' . implode("\n", array_slice($outAttr, 0, 12)));
        }
    }
} else {
    $sql = 'UPDATE ' . $qTable . ' SET ' . $setClause . ' WHERE ' . $whereClause;
    $cmd = 'ogrinfo -q -update ' . escapeshellarg($path) . ' -sql ' . escapeshellarg($sql) . ' 2>&1';
    exec($cmd, $out, $code);
    if ($code !== 0) {
        qfu_fail(500, 'ogrinfo failed: ' . implode("\n", array_slice($out, 0, 12)));
    }
}

if (file_exists($map)) {
    @touch($map);
}

qfu_ok();
