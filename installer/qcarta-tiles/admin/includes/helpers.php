<?php
declare(strict_types=1);

function getPresetConfig(string $preset): array
{
    switch ($preset) {
        case 'performance':
            return ['metatile' => 2, 'buffer' => 32];
        case 'quality':
            return ['metatile' => 4, 'buffer' => 64];
        default:
            return ['metatile' => 4, 'buffer' => 64];
    }
}

function tileServiceBase(array $config): string
{
    return rtrim($config['tile_service_base'] ?? 'http://127.0.0.1:8011', '/');
}

function buildTileQueryParams(array $row): array
{
    $preset = getPresetConfig($row['quality_preset'] ?? 'balanced');
    $q = [
        'map' => $row['qgis_map_path'],
        'meta' => (string) $preset['metatile'],
        'buffer' => (string) $preset['buffer'],
    ];
    if (!empty($row['layers'])) {
        $q['layers'] = $row['layers'];
    }
    if (($row['transparent'] ?? 1) && ($row['image_format'] ?? 'png') === 'png') {
        $q['TRANSPARENT'] = 'true';
    }
    if (($row['cache_enabled'] ?? 1) == 0) {
        $q['CACHE'] = '0';
    }
    return $q;
}

function buildXYZTemplate(array $config, array $row): string
{
    $base = tileServiceBase($config);
    $q = buildTileQueryParams($row);
    return $base . '/api/tiles/{z}/{x}/{y}.png?' . http_build_query($q);
}

/** Base WMS GetMap URL — clients must add BBOX, WIDTH, HEIGHT (and optional STYLES). */
function buildWmsTemplate(array $config, array $row): string
{
    $base = tileServiceBase($config);
    $q = [
        'SERVICE' => 'WMS',
        'REQUEST' => 'GetMap',
        'VERSION' => '1.3.0',
        'CRS' => 'EPSG:3857',
        'map' => $row['qgis_map_path'],
    ];
    if (!empty($row['layers'])) {
        $q['LAYERS'] = $row['layers'];
    }
    $fmt = ($row['image_format'] ?? 'png') === 'jpeg' ? 'image/jpeg' : 'image/png';
    $q['FORMAT'] = $fmt;
    if (($row['transparent'] ?? 1) && $fmt === 'image/png') {
        $q['TRANSPARENT'] = 'true';
    }
    if (($row['cache_enabled'] ?? 1) == 0) {
        $q['CACHE'] = '0';
    }
    return $base . '/api/wms?' . http_build_query($q);
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
