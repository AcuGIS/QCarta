<?php
/**
 * Project key for qcarta-tiles disk cache: cache_data/wms/<projectKey>/...
 * Must stay aligned with installer/qcarta-tiles/main.go projectKeyFromQuery.
 */

function qcarta_project_key_from_qgis_filename(string $path): string
{
	$base = basename($path);
	$base = preg_replace('/\.(qgs|qgz)$/i', '', $base);
	$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
	$base = trim($base, "._-");
	return $base !== '' ? $base : 'unknown';
}

/** Read env.php as text (no require) so it is safe to call in a list loop. */
function qcarta_read_qgis_path_from_env_file(string $envPath): string
{
	$src = @file_get_contents($envPath);
	if ($src === false || $src === '') {
		return '';
	}
	$src = preg_replace('/^\xEF\xBB\xBF/', '', $src);

	$tryPlain = function (string $v): string {
		$v = trim($v);
		if ($v === '' || strcasecmp($v, 'none') === 0) {
			return '';
		}
		return $v;
	};

	$patternsPlain = [
		"/const\\s+QGIS_FILENAME\\s*=\\s*'([^']*)'/",
		'/const\s+QGIS_FILENAME\s*=\s*"((?:\\\\.|[^"])*)"/',
		"/define\\s*\\(\\s*['\"]QGIS_FILENAME['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)/i",
	];
	foreach ($patternsPlain as $re) {
		if (preg_match($re, $src, $m)) {
			$v = $tryPlain(stripcslashes($m[1]));
			if ($v !== '') {
				return $v;
			}
		}
	}

	$patternsEnc = [
		"/const\\s+QGIS_FILENAME_ENCODED\\s*=\\s*'([^']*)'/",
		'/const\s+QGIS_FILENAME_ENCODED\s*=\s*"((?:\\\\.|[^"])*)"/',
		"/define\\s*\\(\\s*['\"]QGIS_FILENAME_ENCODED['\"]\\s*,\\s*['\"]([^'\"]*)['\"]\\s*\\)/i",
	];
	foreach ($patternsEnc as $re) {
		if (preg_match($re, $src, $m2)) {
			$v = $tryPlain(urldecode(stripcslashes($m2[1])));
			if ($v !== '') {
				return $v;
			}
		}
	}

	return '';
}

function qcarta_tile_cache_project_key_for_layer_id(int $layerId): string
{
	if ($layerId <= 0 || !defined('WWW_DIR')) {
		return '';
	}
	$envPath = WWW_DIR . '/layers/' . $layerId . '/env.php';
	if (!is_file($envPath) || !is_readable($envPath)) {
		return '';
	}
	$qgisPath = qcarta_read_qgis_path_from_env_file($envPath);
	if ($qgisPath === '') {
		return '';
	}
	return qcarta_project_key_from_qgis_filename($qgisPath);
}
