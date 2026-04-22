<?php

//https://docs.qgis.org/3.34/en/docs/server_manual/services/wfs.html#wfs-getfeature-outputformat
//https://docs.qgis.org/3.34/en/docs/server_manual/services/wms.html#wms-getmap-format
//https://docs.qgis.org/3.34/en/docs/server_manual/services/wms.html#wms-getprint-format
function format2headers($format, $layer_id){
	switch($format){
		case 'jpg':
		case 'jpeg':
		case 'image/jpeg':
			header("Content-Type: image/jpeg");
			break;
		
		case 'png':
		case 'image/png':
			header("Content-Type: image/png");
			break;
		
		case 'webp':
		case 'image/webp':
			header("Content-Type: image/webp");
			break;
		
		case 'pdf':
		case 'application/pdf':
			header("Content-Type: application/pdf");
			header('Content-Disposition: attachment; filename="layer_'.$layer_id.'.pdf"');	//download as file
			break;
		
		case 'gml2':
		case 'gml3':
		case 'text/xml; subtype=gml/2.1.2':
		case 'text/xml; subtype=gml/3.1.1':
			header("Content-Type: text/xml");
			header('Content-Disposition: attachment; filename="layer_'.$layer_id.'.xml"');	//download as file
			break;

		case 'geojson':
		case 'application/json':
		case 'application/vnd.geo+json':
		case 'application/geo+json':
		case 'application/geo json':
			header("Content-Type: application/json");
			header('Content-Disposition: attachment; filename="layer_'.$layer_id.'.json"');	//download as file
			break;
			
		case 'svg':
		case 'image/svg':
			header("Content-Type: image/svg");
			break;
		
		case 'image/svg+xml':
			header("Content-Type: image/svg+xml");
			break;
		
		case 'application/openlayers':
			header("Content-Type: text/html");
			break;
			
		default:
			header("Content-Type: ".$format);
			break;
	}
}

function layer_get_capabilities($qgs_file){
	$xml_data = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.1&map='.urlencode($qgs_file).'&SERVICE=WMS&REQUEST=GetCapabilities');
	$xml = simplexml_load_string($xml_data);
	return $xml;
}

function layer_get_features($qgs_file){
	$feats = array();
	
	$xml_data = file_get_contents('http://localhost/cgi-bin/qgis_mapserv.fcgi?VERSION=1.1.1&map='.urlencode($qgs_file).'&SERVICE=WFS&REQUEST=GetCapabilities');
	$xml = simplexml_load_string($xml_data);
	
	foreach($xml->FeatureTypeList->FeatureType as $ft){
	   array_push($feats, (string)$ft->Name);
	}
	return $feats;
}

function layer_get_bounding_box($xml, $layer_name){
    $layers = $xml->xpath('//Layer');
	foreach($layers as $l){
		if($l->Name == $layer_name){
			foreach($l->BoundingBox as $bb){
				if($bb['SRS'] == 'EPSG:4326'){
					return $bb;
				}
			}
		}
	}
	return null;
}

// merge two bounding boxes to form one
function merge_bbox($a, $b){
	if($a == null){
		return $b;
	}
	
	if($a['minx'] > $b['minx']){ // min left
		$a['minx'] = $b['minx'];
	}
	
	if($a['maxx'] < $b['maxx']){ // max right
		$a['maxx'] = $b['maxx'];
	}
	
	if($a['miny'] > $b['miny']){ // min bottom
		$a['miny'] = $b['miny'];
	}
	
	if($a['maxy'] < $b['maxy']){	// max top
		$a['maxy'] = $b['maxy'];
	}
	
	return $a;
}

function layers_get_bbox($qgs_file, $layers){
	$xml = layer_get_capabilities($qgs_file);
	
	$bbox = null;
	$layers = explode(',', $layers);
	foreach($layers as $l){
		$b = layer_get_bounding_box($xml, $l);
		if($b){
		    $bbox = merge_bbox($bbox, $b);
		}
	}
	return $bbox;
}

/**
 * WMS Layer BoundingBox for a given SRS/CRS (GetCapabilities).
 */
function layer_get_bounding_box_srs(SimpleXMLElement $capabilitiesXml, string $layer_name, string $srs_target): ?SimpleXMLElement {
	$want = strtoupper(trim($srs_target));
	foreach ($capabilitiesXml->xpath('//Layer') as $l) {
		if ((string) $l->Name !== $layer_name) {
			continue;
		}
		foreach ($l->BoundingBox as $bb) {
			$srs = '';
			if (isset($bb['SRS'])) {
				$srs = strtoupper((string) $bb['SRS']);
			} elseif (isset($bb['CRS'])) {
				$srs = strtoupper((string) $bb['CRS']);
			}
			if ($srs === $want) {
				return $bb;
			}
		}
	}
	return null;
}

function layers_get_bbox_srs($qgs_file, string $layers_csv, string $srs): ?SimpleXMLElement {
	$xml = layer_get_capabilities($qgs_file);
	$bbox = null;
	foreach (explode(',', $layers_csv) as $l) {
		$l = trim($l);
		if ($l === '') {
			continue;
		}
		$b = layer_get_bounding_box_srs($xml, $l, $srs);
		if ($b) {
			$bbox = merge_bbox($bbox, $b);
		}
	}
	return $bbox;
}

/** Web Mercator half-extent (meters), matches qcarta-tiles tileToBBox. */
function qc_tile_seed_mercator_half_extent(): float {
	return 20037508.342789244;
}

/**
 * Convert WGS84 lon/lat bounds to axis-aligned Web Mercator (EPSG:3857) rectangle (union of four corners).
 *
 * @return array{minx: float, miny: float, maxx: float, maxy: float}
 */
function qc_wgs84_bounds_to_epsg3857(float $minLon, float $minLat, float $maxLon, float $maxLat): array {
	$lim = 85.05112878;
	$minLat = max(-$lim, min($lim, $minLat));
	$maxLat = max(-$lim, min($lim, $maxLat));
	$merc = qc_tile_seed_mercator_half_extent();
	$toX = static function (float $lon) use ($merc): float {
		return ($lon + 180.0) * ($merc / 180.0);
	};
	$toY = static function (float $lat) use ($merc): float {
		$latRad = deg2rad($lat);
		$y = log(tan(M_PI / 4.0 + $latRad / 2.0));
		return $y * $merc / M_PI;
	};
	$corners = [
		[$toX($minLon), $toY($minLat)],
		[$toX($maxLon), $toY($minLat)],
		[$toX($minLon), $toY($maxLat)],
		[$toX($maxLon), $toY($maxLat)],
	];
	$minx = $corners[0][0];
	$maxx = $corners[0][0];
	$miny = $corners[0][1];
	$maxy = $corners[0][1];
	foreach ($corners as $c) {
		$minx = min($minx, $c[0]);
		$maxx = max($maxx, $c[0]);
		$miny = min($miny, $c[1]);
		$maxy = max($maxy, $c[1]);
	}
	return ['minx' => $minx, 'miny' => $miny, 'maxx' => $maxx, 'maxy' => $maxy];
}

/**
 * True when maplayer &lt;extent&gt; xmin/xmax/ymin/ymax look like WGS84 degrees, not Web Mercator meters.
 * QGIS projects often declare EPSG:3857 while still serializing geographic numbers in &lt;extent&gt;.
 */
function qc_maplayer_extent_looks_like_wgs84_degrees(float $xmin, float $ymin, float $xmax, float $ymax): bool {
	if ($xmax <= $xmin || $ymax <= $ymin) {
		return false;
	}
	$limLon = 181.0;
	$limLat = 90.0001;
	if (abs($xmin) > $limLon || abs($xmax) > $limLon || abs($ymin) > $limLat || abs($ymax) > $limLat) {
		return false;
	}
	return true;
}

/**
 * Axis-aligned union of EPSG:3857 bounding boxes (one entry per contributing layer).
 *
 * @param list<array{minx: float, miny: float, maxx: float, maxy: float}> $boxes
 * @return array{minx: float, miny: float, maxx: float, maxy: float}|null
 */
function qc_union_extent_boxes_3857(array $boxes): ?array {
	if ($boxes === []) {
		return null;
	}
	$minx = null;
	$miny = null;
	$maxx = null;
	$maxy = null;
	foreach ($boxes as $b) {
		if (!isset($b['minx'], $b['miny'], $b['maxx'], $b['maxy'])) {
			continue;
		}
		$bx0 = $b['minx'];
		$by0 = $b['miny'];
		$bx1 = $b['maxx'];
		$by1 = $b['maxy'];
		if (!is_finite($bx0) || !is_finite($by0) || !is_finite($bx1) || !is_finite($by1) || $bx1 <= $bx0 || $by1 <= $by0) {
			continue;
		}
		if ($minx === null) {
			$minx = $bx0;
			$miny = $by0;
			$maxx = $bx1;
			$maxy = $by1;
		} else {
			$minx = min($minx, $bx0);
			$miny = min($miny, $by0);
			$maxx = max($maxx, $bx1);
			$maxy = max($maxy, $by1);
		}
	}
	if ($minx === null) {
		return null;
	}
	return ['minx' => $minx, 'miny' => $miny, 'maxx' => $maxx, 'maxy' => $maxy];
}

function qc_gpkg_sql_identifier_safe(?string $s): bool {
	return is_string($s) && $s !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $s);
}

/**
 * @return array{path: string, table: string}|null
 */
function qc_gpkg_maplayer_location_from_xml(string $qgs_file, SimpleXMLElement $ml): ?array {
	if ((string) $ml->provider === 'postgres') {
		return null;
	}
	$ds = (string) $ml->datasource;
	if (stripos($ds, '.gpkg') === false) {
		return null;
	}
	$projDir = dirname($qgs_file);
	$pathOnly = trim((string) preg_replace('/\|.*$/s', '', $ds));
	if ($pathOnly === '') {
		return null;
	}
	$abs = $pathOnly;
	if ($pathOnly[0] !== '/' && !(PHP_OS_FAMILY === 'Windows' && preg_match('#^[a-zA-Z]:[/\\\\]#', $pathOnly))) {
		$abs = $projDir . '/' . ltrim(str_replace('\\', '/', $pathOnly), './');
	}
	$abs = str_replace('\\', '/', $abs);
	if (strtolower((string) pathinfo($abs, PATHINFO_EXTENSION)) !== 'gpkg') {
		return null;
	}
	$table = trim((string) $ml->layername);
	if (preg_match('/layername=([^|]+)/i', $ds, $m)) {
		$table = trim($m[1]);
	}
	if ($table === '' || !qc_gpkg_sql_identifier_safe($table)) {
		return null;
	}
	return ['path' => $abs, 'table' => $table];
}

function qc_gpkg_auth_from_wkt_definition(string $def): ?string {
	if ($def === '') {
		return null;
	}
	if (preg_match('/AUTHORITY\s*\[\s*"EPSG"\s*,\s*"?(\d+)"?\s*\]/i', $def, $m)) {
		return 'EPSG:' . (int) $m[1];
	}
	if (preg_match('/\bEPSG\s*:\s*(\d+)/i', $def, $m)) {
		return 'EPSG:' . (int) $m[1];
	}
	return null;
}

/**
 * @return string Uppercase auth id e.g. EPSG:4326
 */
function qc_gpkg_srs_auth_from_id(SQLite3 $db, ?int $srsId, array &$warnings): string {
	if ($srsId === null) {
		$warnings[] = 'GeoPackage gpkg_contents.srs_id is NULL; assuming EPSG:4326 for extent.';
		return 'EPSG:4326';
	}
	$stmt = $db->prepare('SELECT organization, organization_coordsys_id, definition FROM gpkg_spatial_ref_sys WHERE srs_id = :sid LIMIT 1');
	if ($stmt === false) {
		$warnings[] = 'GeoPackage: could not prepare srs lookup; assuming EPSG:4326.';
		return 'EPSG:4326';
	}
	$stmt->bindValue(':sid', $srsId, SQLITE3_INTEGER);
	$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
	if (!$row) {
		$warnings[] = 'GeoPackage srs_id ' . $srsId . ' missing from gpkg_spatial_ref_sys; assuming EPSG:4326.';
		return 'EPSG:4326';
	}
	$org = isset($row['organization']) ? strtoupper(trim((string) $row['organization'])) : '';
	$cid = $row['organization_coordsys_id'] ?? null;
	if ($org !== '' && $cid !== null && (string) $cid !== '') {
		if ($org === 'EPSG' || $org === 'ESRI' || $org === 'OGC') {
			return $org . ':' . (int) $cid;
		}
		return $org . ':' . trim((string) $cid);
	}
	$def = isset($row['definition']) ? (string) $row['definition'] : '';
	$fromWkt = qc_gpkg_auth_from_wkt_definition($def);
	if ($fromWkt !== null) {
		return $fromWkt;
	}
	$warnings[] = 'GeoPackage srs_id ' . $srsId . ' could not be interpreted; assuming EPSG:4326.';
	return 'EPSG:4326';
}

/**
 * @param array{minx: float, miny: float, maxx: float, maxy: float, srs_auth: string} $native
 * @return array{minx: float, miny: float, maxx: float, maxy: float}|null
 */
function qc_gpkg_native_extent_to_3857(array $native, array &$warnings): ?array {
	$minx = $native['minx'];
	$miny = $native['miny'];
	$maxx = $native['maxx'];
	$maxy = $native['maxy'];
	if (!is_finite($minx) || !is_finite($miny) || !is_finite($maxx) || !is_finite($maxy) || $maxx <= $minx || $maxy <= $miny) {
		return null;
	}
	$auth = strtoupper($native['srs_auth']);
	if ($auth === 'EPSG:4326' || strpos($auth, '4326') !== false) {
		return qc_wgs84_bounds_to_epsg3857($minx, $miny, $maxx, $maxy);
	}
	if ($auth === 'EPSG:3857' || $auth === 'EPSG:900913' || $auth === 'EPSG:102100' || strpos($auth, '3857') !== false) {
		if (qc_maplayer_extent_looks_like_wgs84_degrees($minx, $miny, $maxx, $maxy)) {
			$warnings[] = 'GeoPackage extent looks like WGS84 degrees but srs is Web Mercator; projecting to EPSG:3857.';
			return qc_wgs84_bounds_to_epsg3857($minx, $miny, $maxx, $maxy);
		}
		return ['minx' => $minx, 'miny' => $miny, 'maxx' => $maxx, 'maxy' => $maxy];
	}
	if (preg_match('/EPSG:(\d+)/', $auth, $m)) {
		$code = (int) $m[1];
		if (in_array($code, [3857, 900913, 102100], true)) {
			return ['minx' => $minx, 'miny' => $miny, 'maxx' => $maxx, 'maxy' => $maxy];
		}
		if ($code === 4326) {
			return qc_wgs84_bounds_to_epsg3857($minx, $miny, $maxx, $maxy);
		}
	}
	$warnings[] = 'GeoPackage extent SRS "' . $native['srs_auth'] . '" is not EPSG:4326 or Web Mercator; cannot convert to EPSG:3857 for seeding.';
	return null;
}

/**
 * @return array{minx: float, miny: float, maxx: float, maxy: float, srs_auth: string}|null
 */
function qc_gpkg_read_feature_extent(string $gpkgPath, string $tableName, array &$warnings): ?array {
	if (!class_exists('SQLite3')) {
		$warnings[] = 'PHP SQLite3 extension is not available; cannot read GeoPackage extent.';
		return null;
	}
	if (!is_readable($gpkgPath)) {
		$warnings[] = 'GeoPackage file is not readable: ' . $gpkgPath;
		return null;
	}
	try {
		$db = new SQLite3($gpkgPath, SQLITE3_OPEN_READONLY);
	} catch (Throwable $e) {
		$warnings[] = 'Could not open GeoPackage: ' . $gpkgPath;
		return null;
	}
	$db->busyTimeout(2000);
	$stmt = $db->prepare(
		'SELECT min_x, min_y, max_x, max_y, srs_id FROM gpkg_contents WHERE table_name = :t AND (data_type IS NULL OR LOWER(data_type) = \'features\') LIMIT 1'
	);
	if ($stmt === false) {
		$db->close();
		$warnings[] = 'GeoPackage gpkg_contents query failed for table "' . $tableName . '".';
		return null;
	}
	$stmt->bindValue(':t', $tableName, SQLITE3_TEXT);
	$row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
	$srsIdFromContents = null;
	if ($row && isset($row['srs_id']) && $row['srs_id'] !== null && $row['srs_id'] !== '') {
		$srsIdFromContents = (int) $row['srs_id'];
	}
	if ($row) {
		$allNums = true;
		foreach (['min_x', 'min_y', 'max_x', 'max_y'] as $k) {
			if (!array_key_exists($k, $row) || $row[$k] === null || $row[$k] === '') {
				$allNums = false;
				break;
			}
		}
		if ($allNums) {
			$minx = floatval($row['min_x']);
			$miny = floatval($row['min_y']);
			$maxx = floatval($row['max_x']);
			$maxy = floatval($row['max_y']);
			if (is_finite($minx) && is_finite($miny) && is_finite($maxx) && is_finite($maxy) && $maxx > $minx && $maxy > $miny) {
				$auth = qc_gpkg_srs_auth_from_id($db, $srsIdFromContents, $warnings);
				$db->close();
				return ['minx' => $minx, 'miny' => $miny, 'maxx' => $maxx, 'maxy' => $maxy, 'srs_auth' => $auth];
			}
		}
	}
	$geomCol = null;
	$srsFromGeom = null;
	$gstmt = $db->prepare('SELECT column_name, srs_id FROM gpkg_geometry_columns WHERE table_name = :t LIMIT 1');
	if ($gstmt !== false) {
		$gstmt->bindValue(':t', $tableName, SQLITE3_TEXT);
		$grow = $gstmt->execute()->fetchArray(SQLITE3_ASSOC);
		if ($grow && !empty($grow['column_name']) && qc_gpkg_sql_identifier_safe($grow['column_name'])) {
			$geomCol = (string) $grow['column_name'];
			if (isset($grow['srs_id']) && $grow['srs_id'] !== null && $grow['srs_id'] !== '') {
				$srsFromGeom = (int) $grow['srs_id'];
			}
		}
	}
	if ($geomCol === null || !qc_gpkg_sql_identifier_safe($tableName)) {
		$db->close();
		$warnings[] = 'GeoPackage table "' . $tableName . '": no valid extent in gpkg_contents and no gpkg_geometry_columns row.';
		return null;
	}
	$rtreeName = 'rtree_' . $tableName . '_' . $geomCol;
	$bounds = null;
	if (qc_gpkg_sql_identifier_safe($rtreeName)) {
		$chkStmt = $db->prepare('SELECT 1 FROM sqlite_master WHERE type = ? AND name = ? LIMIT 1');
		$rtreeExists = false;
		if ($chkStmt !== false) {
			$chkStmt->bindValue(1, 'table', SQLITE3_TEXT);
			$chkStmt->bindValue(2, $rtreeName, SQLITE3_TEXT);
			$chkRow = $chkStmt->execute()->fetchArray(SQLITE3_ASSOC);
			$rtreeExists = !empty($chkRow);
		}
		if ($rtreeExists) {
			$q = sprintf(
				'SELECT MIN(minx) AS mn_x, MIN(miny) AS mn_y, MAX(maxx) AS mx_x, MAX(maxy) AS mx_y FROM "%s"',
				str_replace('"', '""', $rtreeName)
			);
			$rw = @$db->querySingle($q, true);
			if (is_array($rw) && isset($rw['mn_x'], $rw['mn_y'], $rw['mx_x'], $rw['mx_y'])) {
				$bx0 = floatval($rw['mn_x']);
				$by0 = floatval($rw['mn_y']);
				$bx1 = floatval($rw['mx_x']);
				$by1 = floatval($rw['mx_y']);
				if (is_finite($bx0) && is_finite($by0) && is_finite($bx1) && is_finite($by1) && $bx1 > $bx0 && $by1 > $by0) {
					$bounds = [$bx0, $by0, $bx1, $by1];
				}
			}
		}
	}
	if ($bounds === null) {
		$extLoaded = false;
		$candidates = ['mod_spatialite', 'mod_spatialite.so', 'mod_spatialite.dylib'];
		$dir = function_exists('ini_get') ? (string) @ini_get('sqlite3.extension_dir') : '';
		if ($dir !== '') {
			$candidates[] = rtrim($dir, "/\\") . '/mod_spatialite.so';
			$candidates[] = rtrim($dir, "/\\") . '/mod_spatialite.dylib';
		}
		$candidates[] = '/usr/lib/x86_64-linux-gnu/mod_spatialite.so';
		foreach ($candidates as $cand) {
			if ($cand === '') {
				continue;
			}
			@$db->loadExtension($cand);
			$t = @$db->querySingle('SELECT spatialite_version()');
			if ($t !== null && $t !== false && $t !== '') {
				$extLoaded = true;
				break;
			}
		}
		if (!$extLoaded) {
			$db->close();
			$warnings[] = 'GeoPackage table "' . $tableName . '": gpkg_contents extent empty; no R-tree table rtree_' . $tableName . '_' . $geomCol . ' and SpatiaLite could not be loaded for geometry bounds.';
			return null;
		}
		$sql = sprintf(
			'SELECT MIN(ST_MinX("%s")) AS mn_x, MIN(ST_MinY("%s")) AS mn_y, MAX(ST_MaxX("%s")) AS mx_x, MAX(ST_MaxY("%s")) AS mx_y FROM "%s"',
			str_replace('"', '""', $geomCol),
			str_replace('"', '""', $geomCol),
			str_replace('"', '""', $geomCol),
			str_replace('"', '""', $geomCol),
			str_replace('"', '""', $tableName)
		);
		$rw = @$db->querySingle($sql, true);
		if (!is_array($rw) || !isset($rw['mn_x'], $rw['mn_y'], $rw['mx_x'], $rw['mx_y'])) {
			$db->close();
			$warnings[] = 'GeoPackage table "' . $tableName . '": geometry extent query returned no data.';
			return null;
		}
		$bx0 = floatval($rw['mn_x']);
		$by0 = floatval($rw['mn_y']);
		$bx1 = floatval($rw['mx_x']);
		$by1 = floatval($rw['mx_y']);
		if (!is_finite($bx0) || !is_finite($by0) || !is_finite($bx1) || !is_finite($by1) || $bx1 <= $bx0 || $by1 <= $by0) {
			$db->close();
			$warnings[] = 'GeoPackage table "' . $tableName . '": computed geometry extent is invalid.';
			return null;
		}
		$bounds = [$bx0, $by0, $bx1, $by1];
	}
	$auth = qc_gpkg_srs_auth_from_id($db, $srsFromGeom ?? $srsIdFromContents, $warnings);
	$db->close();
	return ['minx' => $bounds[0], 'miny' => $bounds[1], 'maxx' => $bounds[2], 'maxy' => $bounds[3], 'srs_auth' => $auth];
}

/**
 * Resolve tile-seed extent in EPSG:3857 (meters) for /api/tiles seeding.
 *
 * Priority: GeoPackage file (gpkg_contents / rtree / SpatiaLite) → merged QGIS maplayer &lt;extent&gt; → WMS EPSG:3857 → WMS 4326 projected.
 *
 * @return array Success: ok=true, extent3857, source, warnings, seed_debug_extent, seed_debug_gpkg, seed_debug_combined. Failure: ok=false, message, warnings.
 */
function qc_tile_seed_resolve_extent(string $qgs_file, string $layers_csv): array {
	$warnings = [];
	$seedGpkgLines = [];
	$seedUsedGpkg = false;
	$seedUsedXml = false;
	$layerBoxes3857 = [];
	$seedLayerNameCount = count(array_filter(array_map('trim', explode(',', $layers_csv))));

	$xmlStr = qgis_read_project_xml($qgs_file);
	$qgsMerged = null;
	$qgsSourceDetail = '';

	if ($xmlStr) {
		$pxml = @simplexml_load_string($xmlStr);
		if ($pxml) {
			$names = array_map('trim', explode(',', $layers_csv));
			foreach ($names as $wantName) {
				if ($wantName === '') {
					continue;
				}
				$ml = null;
				foreach ($pxml->xpath('//maplayer') as $cand) {
					if ((string) $cand->layername === $wantName) {
						$ml = $cand;
						break;
					}
				}
				if ($ml === null) {
					$short = $wantName;
					if (preg_match('/\.([^.]+)$/', $wantName, $m)) {
						$short = $m[1];
					}
					foreach ($pxml->xpath('//maplayer') as $cand) {
						if ((string) $cand->layername === $short) {
							$ml = $cand;
							break;
						}
					}
				}
				if ($ml === null) {
					continue;
				}
				$wkb = (string) $ml['wkbType'];
				$geom = (string) $ml['geometry'];
				if (preg_match('/NoGeometry/i', $wkb) || preg_match('/No geometry/i', $geom)) {
					continue;
				}

				$gpkgLoc = qc_gpkg_maplayer_location_from_xml($qgs_file, $ml);
				if ($gpkgLoc !== null) {
					if (!is_readable($gpkgLoc['path'])) {
						$warnings[] = 'Layer "' . $wantName . '": GeoPackage path not readable (' . $gpkgLoc['path'] . '); skipping QGIS &lt;extent&gt; for this layer.';
						continue;
					}
					$native = qc_gpkg_read_feature_extent($gpkgLoc['path'], $gpkgLoc['table'], $warnings);
					$box3857 = null;
					if ($native !== null) {
						$box3857 = qc_gpkg_native_extent_to_3857($native, $warnings);
					}
					if ($box3857 !== null) {
						$seedUsedGpkg = true;
						$extStr = sprintf(
							'%s,%s,%s,%s',
							$native['minx'],
							$native['miny'],
							$native['maxx'],
							$native['maxy']
						);
						$dbg = 'source=gpkg table=' . $gpkgLoc['table'] . ' extent=' . $extStr . ' crs=' . $native['srs_auth'];
						error_log('[SEED DEBUG] ' . $dbg);
						$seedGpkgLines[] = $dbg;
						$layerBoxes3857[] = $box3857;
					} else {
						$warnings[] = 'Layer "' . $wantName . '": GeoPackage extent could not be used for seeding; skipping QGIS &lt;extent&gt; for this layer.';
					}
					continue;
				}

				$xmin = null;
				$xpathExtent = $ml->xpath('./extent');
				if (is_array($xpathExtent) && isset($xpathExtent[0])) {
					$ex = $xpathExtent[0];
					$xmin = floatval((string) $ex->xmin);
					$ymin = floatval((string) $ex->ymin);
					$xmax = floatval((string) $ex->xmax);
					$ymax = floatval((string) $ex->ymax);
				}
				if ($xmin === null || ($xmax <= $xmin && $ymax <= $ymin)) {
					continue;
				}
				if (abs($xmax - $xmin) < 1e-9 && abs($ymax - $ymin) < 1e-9) {
					continue;
				}

				$auth = '';
				$authNodes = $ml->xpath('./srs/spatialrefsys/authid');
				if (is_array($authNodes) && isset($authNodes[0])) {
					$auth = strtoupper((string) $authNodes[0]);
				}
				$box3857 = null;
				if ($auth === 'EPSG:3857' || $auth === 'EPSG:900913' || $auth === 'EPSG:102100' || strpos($auth, '3857') !== false) {
					if (qc_maplayer_extent_looks_like_wgs84_degrees($xmin, $ymin, $xmax, $ymax)) {
						$warnings[] = 'Layer "' . $wantName . '": authid is Web Mercator but &lt;extent&gt; values look like WGS84 degrees; using projected bounds for seeding.';
						$box3857 = qc_wgs84_bounds_to_epsg3857($xmin, $ymin, $xmax, $ymax);
					} else {
						$box3857 = ['minx' => $xmin, 'miny' => $ymin, 'maxx' => $xmax, 'maxy' => $ymax];
					}
				} elseif ($auth === 'EPSG:4326' || strpos($auth, '4326') !== false || $auth === '') {
					$wxmin = $xmin;
					$wymin = $ymin;
					$wxmax = $xmax;
					$wymax = $ymax;
					$wgs = $ml->xpath('./wgs84extent');
					if (is_array($wgs) && isset($wgs[0])) {
						$wg = $wgs[0];
						$wxmin = floatval((string) $wg->xmin);
						$wymin = floatval((string) $wg->ymin);
						$wxmax = floatval((string) $wg->xmax);
						$wymax = floatval((string) $wg->ymax);
					}
					if ($wxmax > $wxmin && $wymax > $wymin) {
						$box3857 = qc_wgs84_bounds_to_epsg3857($wxmin, $wymin, $wxmax, $wymax);
					}
				} else {
					$warnings[] = 'Layer "' . $wantName . '" uses CRS ' . $auth . '; skipped for QGIS extent merge (use WMS 3857/4326 fallback).';
					continue;
				}
				if ($box3857 === null) {
					continue;
				}
				$seedUsedXml = true;
				$layerBoxes3857[] = $box3857;
			}
		}
	}

	$qgsMerged = qc_union_extent_boxes_3857($layerBoxes3857);

	if ($qgsMerged !== null) {
		$w = $qgsMerged;
		if (($w['maxx'] - $w['minx']) * ($w['maxy'] - $w['miny']) < 100.0) {
			$warnings[] = 'QGIS maplayer merged extent is very small (<100 m²); tile count may be low.';
		}
		if ($w['maxx'] <= $w['minx'] || $w['maxy'] <= $w['miny']) {
			$warnings[] = 'QGIS maplayer merged extent is degenerate; falling back to WMS.';
			$qgsMerged = null;
		} else {
			if ($seedUsedGpkg && $seedUsedXml) {
				$qgsSourceDetail = 'qgs_gpkg_maplayer';
			} elseif ($seedUsedGpkg) {
				$qgsSourceDetail = 'qgs_gpkg';
			} else {
				$qgsSourceDetail = 'qgs_maplayer';
			}
			$nLayers = count($layerBoxes3857);
			$combinedDbg = sprintf(
				'combined extent: %s,%s,%s,%s (layers=%d)',
				$w['minx'],
				$w['miny'],
				$w['maxx'],
				$w['maxy'],
				$nLayers
			);
			error_log('[SEED DEBUG] ' . $combinedDbg);
			$line = sprintf(
				'%s,%s,%s,%s,EPSG:3857 (source=%s)',
				$w['minx'],
				$w['miny'],
				$w['maxx'],
				$w['maxy'],
				$qgsSourceDetail
			);
			return [
				'ok' => true,
				'extent3857' => $w,
				'source' => $qgsSourceDetail,
				'warnings' => $warnings,
				'seed_debug_extent' => $line,
				'seed_debug_gpkg' => $seedGpkgLines,
				'seed_debug_combined' => $combinedDbg,
			];
		}
	}

	$bb3857 = layers_get_bbox_srs($qgs_file, $layers_csv, 'EPSG:3857');
	if ($bb3857 === null) {
		$bb3857 = layers_get_bbox_srs($qgs_file, $layers_csv, 'EPSG:900913');
	}
	if ($bb3857 !== null) {
		$w = [
			'minx' => floatval((string) $bb3857['minx']),
			'miny' => floatval((string) $bb3857['miny']),
			'maxx' => floatval((string) $bb3857['maxx']),
			'maxy' => floatval((string) $bb3857['maxy']),
		];
		if ($w['maxx'] > $w['minx'] && $w['maxy'] > $w['miny']) {
			if (($w['maxx'] - $w['minx']) * ($w['maxy'] - $w['miny']) < 100.0) {
				$warnings[] = 'WMS EPSG:3857 bounding box is very small (<100 m²).';
			}
			$line = sprintf('%s,%s,%s,%s,EPSG:3857 (source=wms_epsg3857)', $w['minx'], $w['miny'], $w['maxx'], $w['maxy']);
			$combinedDbg = sprintf(
				'combined extent: %s,%s,%s,%s (layers=%d)',
				$w['minx'],
				$w['miny'],
				$w['maxx'],
				$w['maxy'],
				$seedLayerNameCount
			);
			error_log('[SEED DEBUG] ' . $combinedDbg);
			return [
				'ok' => true,
				'extent3857' => $w,
				'source' => 'wms_epsg3857',
				'warnings' => $warnings,
				'seed_debug_extent' => $line,
				'seed_debug_gpkg' => $seedGpkgLines,
				'seed_debug_combined' => $combinedDbg,
			];
		}
	}

	$bb4326 = layers_get_bbox($qgs_file, $layers_csv);
	if ($bb4326 === null) {
		return [
			'ok' => false,
			'message' => 'Could not resolve extent from QGIS project layers or WMS GetCapabilities.',
			'warnings' => $warnings,
			'seed_debug_gpkg' => $seedGpkgLines,
			'seed_debug_combined' => null,
		];
	}
	$minLon = floatval((string) $bb4326['minx']);
	$minLat = floatval((string) $bb4326['miny']);
	$maxLon = floatval((string) $bb4326['maxx']);
	$maxLat = floatval((string) $bb4326['maxy']);
	$w = qc_wgs84_bounds_to_epsg3857($minLon, $minLat, $maxLon, $maxLat);
	$warnings[] = 'Used WMS EPSG:4326 BoundingBox projected to EPSG:3857 for tile math (metadata may underestimate vs QGIS layer extent).';
	if (($w['maxx'] - $w['minx']) * ($w['maxy'] - $w['miny']) < 100.0) {
		$warnings[] = 'Projected WMS 4326 extent is very small (<100 m²).';
	}
	if ($w['maxx'] <= $w['minx'] || $w['maxy'] <= $w['miny']) {
		return [
			'ok' => false,
			'message' => 'Projected extent from WMS EPSG:4326 is invalid.',
			'warnings' => $warnings,
			'seed_debug_gpkg' => $seedGpkgLines,
		];
	}
	$line = sprintf(
		'%s,%s,%s,%s,EPSG:3857 (source=wms_epsg4326_projected; wgs84 %s,%s,%s,%s)',
		$w['minx'],
		$w['miny'],
		$w['maxx'],
		$w['maxy'],
		$minLon,
		$minLat,
		$maxLon,
		$maxLat
	);
	return [
		'ok' => true,
		'extent3857' => $w,
		'source' => 'wms_epsg4326_projected',
		'warnings' => $warnings,
		'seed_debug_extent' => $line,
		'seed_debug_gpkg' => $seedGpkgLines,
	];
}

function qgs_ordered_layers($xml){
	$layers = $xml->xpath('/qgis/layer-tree-group//layer-tree-layer');
	$layer_by_id = array();
	foreach($layers as $l){
		$layer_by_id[(string)$l->attributes()->id] = (string)$l->attributes()->name;
	}

	$layer_names = array();
	$layers = $xml->xpath('/qgis/layerorder//layer');
	foreach($layers as $l){
		array_push($layer_names, $layer_by_id[(string)$l->attributes()->id]);
	}
	return $layer_names;
}

/**
 * Parse relations directly from a QGIS project file (.qgs or .qgz).
 * Returns an array of rows:
 *  [
 *    'name' => string,
 *    'parent_layer' => string,
 *    'parent_field' => string,
 *    'child_layer' => string,
 *    'child_field' => string,
 *    'child_list_fields' => ''   // optional, blank by default
 *  ]
 */
function qgis_relations_from_project($pathToQgsOrQgz) {
    // 1) Read XML from .qgs or from project.qgs inside .qgz
    $xmlStr = null;
    if (preg_match('/\.qgz$/i', $pathToQgsOrQgz)) {
        $zip = new ZipArchive();
        if ($zip->open($pathToQgsOrQgz) === true) {
            $xmlStr = $zip->getFromName('project.qgs');
            $zip->close();
        }
    } else {
        $xmlStr = @file_get_contents($pathToQgsOrQgz);
    }
    if (!$xmlStr) return [];

    // 2) Parse XML
    $xml = @simplexml_load_string($xmlStr);
    if (!$xml) return [];

    // Map: layer-id -> layer-name (prefer <shortname>, fallback to <layername>)
    $id2name = [];
    foreach ($xml->xpath('/qgis/projectlayers/maplayer') as $ml) {
        $id           = (string)$ml->id;
        $id2name[$id] = (string)$ml->layername;
    }

    $rels = [];
    foreach ($xml->xpath('/qgis/relations/relation') as $rel) {
        $name  = (string)$rel['name'];
        $refd  = (string)$rel['referencedLayer'];   // parent (id)
        $refg  = (string)$rel['referencingLayer'];  // child  (id)
        
        // Use viewer-friendly names if available, fallback to smart mapping, then QGIS names
        $pname = $id2name[$refd];
        $cname = $id2name[$refg];

        foreach ($rel->fieldRef as $fr) {
            $rels[] = [
                'name'              => $name,
                'parent_layer'      => $pname,
                'parent_field'      => (string)$fr['referencedField'],
                'child_layer'       => $cname,
                'child_field'       => (string)$fr['referencingField'],
                'child_list_fields' => '' // leave empty; can be customized later
            ];
        }
    }
    return $rels;
}

/**
 * Read raw project XML from .qgs or .qgz (project.qgs inside archive).
 */
function qgis_read_project_xml(string $pathToQgsOrQgz): ?string {
    if (preg_match('/\.qgz$/i', $pathToQgsOrQgz)) {
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($pathToQgsOrQgz) !== true) {
            return null;
        }
        $qgs = $zip->getFromName('project.qgs');
        if ($qgs === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                if (!empty($st['name']) && preg_match('/\.qgs$/i', $st['name'])) {
                    $qgs = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();
        return is_string($qgs) ? $qgs : null;
    }
    $c = @file_get_contents($pathToQgsOrQgz);
    return $c !== false ? $c : null;
}

/**
 * @return array{provider: string, datasource: string}|null
 */
function qgis_maplayer_info_for_name(string $projectPath, string $layerName): ?array {
    $xmlStr = qgis_read_project_xml($projectPath);
    if (!$xmlStr) {
        return null;
    }
    $xml = @simplexml_load_string($xmlStr);
    if (!$xml) {
        return null;
    }
    foreach ($xml->xpath('//maplayer') as $ml) {
        if ((string) $ml->layername === $layerName) {
            return [
                'provider' => (string) $ml->provider,
                'datasource' => (string) $ml->datasource,
            ];
        }
    }
    return null;
}

/**
 * Parse QGIS postgres provider datasource for direct SQL updates (service + table + PK + geom).
 *
 * Typical shape:
 *   service='mydb' key='gid' srid=4326 type=Point table="public"."crimes" (geom)
 *
 * @return array{service: string, pk: string, srid: int, schema: string, table: string, geom: string}|null
 */
function qgis_parse_postgres_datasource(string $datasource): ?array {
    if (!preg_match("/service='([^']+)'/", $datasource, $m)) {
        return null;
    }
    $service = $m[1];
    if (!preg_match("/key='([^']+)'/", $datasource, $m) && !preg_match('/key="([^"]+)"/', $datasource, $m)) {
        return null;
    }
    $pk = trim($m[1], '"');
    $srid = 4326;
    if (preg_match('/\bsrid=(\d+)\b/', $datasource, $m)) {
        $srid = (int) $m[1];
    }
    if (!preg_match('/table="([^"]+)"\."([^"]+)"\s+\(([^)]+)\)/', $datasource, $m)) {
        return null;
    }
    $schema = trim($m[1], '"');
    $table = trim($m[2], '"');
    $geom = trim(trim($m[3]), '"');
    foreach ([$schema, $table, $geom, $pk] as $ident) {
        if ($ident === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $ident)) {
            return null;
        }
    }
    return [
        'service' => $service,
        'pk' => $pk,
        'srid' => $srid,
        'schema' => $schema,
        'table' => $table,
        'geom' => $geom,
    ];
}

function qgis_classify_layer_provider(string $projectPath, string $layerName): string {
    $info = qgis_maplayer_info_for_name($projectPath, $layerName);
    if (!$info) {
        return 'ogr';
    }
    $prov = $info['provider'];
    $ds = $info['datasource'];
    if ($prov === 'postgres') {
        return 'postgres';
    }
    if (stripos($ds, '.gpkg') !== false) {
        return 'gpkg';
    }
    if (preg_match('/\.shp\b/i', $ds)) {
        return 'shp';
    }
    if ($prov === 'ogr') {
        return 'ogr';
    }
    return 'ogr';
}

/**
 * Resolve on-disk vector file for a QGIS layer (non-Postgres).
 *
 * @return array{kind: string, path: string, table: string}|null
 */
function qgis_resolve_vector_file_datasource(string $projectPath, string $layerName): ?array {
    $info = qgis_maplayer_info_for_name($projectPath, $layerName);
    if (!$info || $info['provider'] === 'postgres') {
        return null;
    }
    $ds = $info['datasource'];
    $projDir = dirname($projectPath);

    $pathOnly = preg_replace('/\|.*$/', '', $ds);
    $pathOnly = trim($pathOnly);

    $abs = $pathOnly;
    if ($pathOnly !== '' && $pathOnly[0] !== '/' && !(PHP_OS_FAMILY === 'Windows' && preg_match('#^[a-zA-Z]:[/\\\\]#', $pathOnly))) {
        $abs = $projDir . '/' . ltrim(str_replace('\\', '/', $pathOnly), './');
    }
    $abs = str_replace('\\', '/', $abs);

    $table = $layerName;
    if (preg_match('/layername=([^|]+)/i', $ds, $m)) {
        $table = trim($m[1]);
    }

    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    if ($ext === 'gpkg') {
        return ['kind' => 'gpkg', 'path' => $abs, 'table' => $table];
    }
    if (preg_match('/\.shp$/i', $abs)) {
        return ['kind' => 'shp', 'path' => $abs, 'table' => basename($abs, '.shp')];
    }
    if ($info['provider'] === 'ogr' && $ext !== '') {
        return ['kind' => 'ogr', 'path' => $abs, 'table' => $table];
    }
    return null;
}

