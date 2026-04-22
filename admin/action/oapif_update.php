<?php
declare(strict_types=1);
    
session_start(['read_and_close' => true]);
require('../incl/const.php');
require('../class/database.php');
require('../class/table.php');
require('../class/table_ext.php');
require('../class/qgs.php');
require_once(__DIR__ . '/../class/pglink.php');
require_once(__DIR__ . '/../incl/qgis.php');
require_once __DIR__ . '/../incl/qcarta_tile_project_key.php';
	
header('Content-Type: application/json');

/**
 * QCarta OAPIF attribute editor
 * - Auto-resolves collection when UI sends collection:"auto"
 * - Accepts aliases from project & canonical names
 * - Geometry updates: single GeoJSON "geometry" or multi-part "features" (merged to Multi* / GeometryCollection, or NULL if features is []). Prefer PostGIS direct UPDATE; else WFS-T 1.1.x Transaction
 * - Attribute-only updates: OAPIF PATCH, then PUT, then WFS-T Update
 * - WFS-T: full request XML and raw response are error_log’d; JSON includes wfs_t_request_xml, wfs_t_response_raw, wfs_t_http_code (wfs_t_curl_error if any)
 *
 * Works when this script is located at: /var/www/html/layers/<ID>/api/oapif_update.php
 * and the QGIS project lives at:        /var/www/data/stores/<ID>/*.qgs|*.qgz
 */

// ---------------------- Config ----------------------
const WFS_BASE   = 'http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi';
const OAPIF_BASE = WFS_BASE.'/wfs3';

// ---------------------- Helpers ----------------------
function str_ends_with_ci(string $hay, string $needle): bool {
  $n = strlen($needle);
  return $n === 0 ? true : (substr_compare($hay, $needle, -$n, $n, true) === 0);
}

function http(string $method, string $url, array $headers=[], ?string $body=null): array {
  $ch = curl_init($url);
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => $headers,
  ];
  if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
  curl_setopt_array($ch, $opts);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
  $err  = curl_error($ch);
  curl_close($ch);
  return [$code, $resp, $err];
}

/** Tile cache purge after a successful geometry write (PostGIS direct or WFS-T). */
function oapifPurgeTileCacheAfterGeometry(string $layer, string $persistLabel): void {
  $purgeToken = defined('QCARTA_CACHE_PURGE_TOKEN') ? QCARTA_CACHE_PURGE_TOKEN : getenv('QCARTA_CACHE_PURGE_TOKEN');
  $purgeToken = is_string($purgeToken) ? $purgeToken : '';
  $url = 'http://localhost/qcarta-cache/purge.php?layer=' . rawurlencode($layer)
    . '&token=' . rawurlencode($purgeToken);
  error_log('oapif_update: calling purge.php after geometry update (' . $persistLabel . ') layer=' . $layer);
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $body = curl_exec($ch);
  $errno = curl_errno($ch);
  $cerr = curl_error($ch);
  $http = (int) (curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0);
  curl_close($ch);
  $snippet = is_string($body) ? preg_replace('/\s+/', ' ', substr($body, 0, 200)) : '';
  error_log(
    'oapif_update: purge.php finished http=' . $http
    . ' curl_errno=' . $errno . ($cerr !== '' ? (' curl_error=' . $cerr) : '')
    . ' body_preview=' . $snippet
  );
}

function fidFromId($id): string {
  $s = trim((string)$id);
  if ($s === '') return $s;
  // Strip prefix "LayerName." if present
  if (strpos($s, '.') !== false) return substr($s, strrpos($s, '.') + 1);
  return $s;
}

function oapifItemUrl(string $mapFile, string $collection, string $fid): string {
  return rtrim(OAPIF_BASE,'/')
       . '/collections/' . rawurlencode($collection)
       . '/items/' . rawurlencode($fid)
       . '?MAP=' . rawurlencode($mapFile);
}

function normKey(string $k): string {
  return preg_replace('/\s+/', '', mb_strtolower($k));
}

function normalizePointGeometry($g): ?array {
  if (!is_array($g)) return null;
  if (($g['type'] ?? '') !== 'Point') return null;
  $c = $g['coordinates'] ?? null;
  if (!is_array($c) || count($c) < 2) return null;
  $lon = (float) $c[0];
  $lat = (float) $c[1];
  if (!is_finite($lon) || !is_finite($lat)) return null;
  if ($lon < -180.0 || $lon > 180.0 || $lat < -90.0 || $lat > 90.0) return null;
  return ['type' => 'Point', 'coordinates' => [$lon, $lat]];
}

/** Validate coordinate tree for GeoJSON (lon/lat pairs at leaves). */
function geoJsonCoordsValid($node, int $depth = 0): bool {
  if ($depth > 24) {
    return false;
  }
  if (!is_array($node)) {
    return false;
  }
  if ($node === []) {
    return false;
  }
  // Leaf: [lon, lat, ...]
  if (isset($node[0]) && is_numeric($node[0])) {
    if (count($node) < 2 || !is_numeric($node[1] ?? null)) {
      return false;
    }
    $lon = (float) $node[0];
    $lat = (float) $node[1];
    if (!is_finite($lon) || !is_finite($lat)) {
      return false;
    }
    if ($lon < -180.0 || $lon > 180.0 || $lat < -90.0 || $lat > 90.0) {
      return false;
    }
    return true;
  }
  foreach ($node as $child) {
    if (!geoJsonCoordsValid($child, $depth + 1)) {
      return false;
    }
  }
  return true;
}

/**
 * Normalize common OGC geometry types for PUT (PostGIS / QGIS server).
 * Returns null if unsupported or invalid.
 */
function normalizeGeoJsonGeometry($g): ?array {
  if (!is_array($g)) {
    return null;
  }
  $type = $g['type'] ?? '';
  $coords = $g['coordinates'] ?? null;
  $allowed = ['Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon'];
  if (!in_array($type, $allowed, true) || !is_array($coords)) {
    return null;
  }
  if (!geoJsonCoordsValid($coords)) {
    return null;
  }
  return ['type' => $type, 'coordinates' => $coords];
}

/**
 * Merge several normalized GeoJSON geometries into one (Multi* or GeometryCollection).
 */
function mergeNormalizedGeoJsonGeometries(array $geoms): ?array {
  $n = count($geoms);
  if ($n === 0) {
    return null;
  }
  if ($n === 1) {
    return $geoms[0];
  }
  $types = [];
  foreach ($geoms as $g) {
    $types[] = $g['type'] ?? '';
  }
  $unique = array_unique($types);
  if (count($unique) === 1) {
    $t = $types[0];
    if ($t === 'Point') {
      $coords = [];
      foreach ($geoms as $g) {
        $coords[] = $g['coordinates'];
      }
      return ['type' => 'MultiPoint', 'coordinates' => $coords];
    }
    if ($t === 'LineString') {
      $coords = [];
      foreach ($geoms as $g) {
        $coords[] = $g['coordinates'];
      }
      return ['type' => 'MultiLineString', 'coordinates' => $coords];
    }
    if ($t === 'Polygon') {
      $coords = [];
      foreach ($geoms as $g) {
        $coords[] = $g['coordinates'];
      }
      return ['type' => 'MultiPolygon', 'coordinates' => $coords];
    }
  }
  return ['type' => 'GeometryCollection', 'geometries' => $geoms];
}

/**
 * Build one geometry from an array of GeoJSON Feature objects (or raw geometry objects).
 */
function geometriesMergedFromGeoJsonFeatures(array $features): ?array {
  $geoms = [];
  foreach ($features as $f) {
    if (!is_array($f)) {
      continue;
    }
    $g = null;
    if (($f['type'] ?? '') === 'Feature') {
      $g = $f['geometry'] ?? null;
    } else {
      $g = $f;
    }
    if (!is_array($g)) {
      continue;
    }
    $norm = normalizePointGeometry($g) ?? normalizeGeoJsonGeometry($g);
    if ($norm !== null) {
      $geoms[] = $norm;
    }
  }
  return mergeNormalizedGeoJsonGeometries($geoms);
}

/** GML posList / pos from GeoJSON (lon lat), EPSG:4326 — aligned with WFS-T 1.0.0 axis order. */
function gmlPosListFromLineCoords(array $coords): string {
  $pts = [];
  foreach ($coords as $pt) {
    if (!is_array($pt) || count($pt) < 2) {
      continue;
    }
    $pts[] = ((float) $pt[0]) . ' ' . ((float) $pt[1]);
  }
  return implode(' ', $pts);
}

function gmlPosListFromRingCoords(array $ring): string {
  $pts = [];
  foreach ($ring as $pt) {
    if (!is_array($pt) || count($pt) < 2) {
      continue;
    }
    $pts[] = (string) ((float) $pt[0]) . ' ' . (string) ((float) $pt[1]);
  }
  if (count($pts) < 3) {
    return '';
  }
  $first = $ring[0];
  $last = $ring[count($ring) - 1];
  if (!is_array($first) || !is_array($last) || count($first) < 2 || count($last) < 2) {
    return implode(' ', $pts);
  }
  if ((float) $first[0] !== (float) $last[0] || (float) $first[1] !== (float) $last[1]) {
    $pts[] = (string) ((float) $first[0]) . ' ' . (string) ((float) $first[1]);
  }
  return implode(' ', $pts);
}

/**
 * GML 3.1.1 geometry fragment for wfs:Value (namespace gml on Transaction root).
 */
function geoJsonToGml311(array $g, string $srs = 'EPSG:4326'): string {
  $srsEsc = htmlspecialchars($srs, ENT_XML1 | ENT_COMPAT, 'UTF-8');
  $srsAttr = ' srsName="' . $srsEsc . '"';
  $type = $g['type'] ?? '';
  if ($type === 'GeometryCollection') {
    $members = $g['geometries'] ?? null;
    if (!is_array($members) || $members === []) {
      return '';
    }
    $parts = [];
    foreach ($members as $sub) {
      if (!is_array($sub)) {
        continue;
      }
      $frag = geoJsonToGml311($sub, $srs);
      if ($frag !== '') {
        $parts[] = '<gml:geometryMember>' . $frag . '</gml:geometryMember>';
      }
    }
    return $parts === [] ? '' : '<gml:GeometryCollection' . $srsAttr . '>' . implode('', $parts) . '</gml:GeometryCollection>';
  }
  $c = $g['coordinates'] ?? null;
  if (!is_array($c)) {
    return '';
  }
  switch ($type) {
    case 'Point':
      if (count($c) < 2) {
        return '';
      }
      $lon = (float) $c[0];
      $lat = (float) $c[1];
      $coords = $lon . ' ' . $lat;
      return '<gml:Point' . $srsAttr . '><gml:pos srsDimension="2">' . $coords . '</gml:pos></gml:Point>';
    case 'LineString':
      $pl = gmlPosListFromLineCoords($c);
      return $pl === '' ? '' : '<gml:LineString' . $srsAttr . '><gml:posList srsDimension="2">' . $pl . '</gml:posList></gml:LineString>';
    case 'Polygon':
      if ($c === []) {
        return '';
      }
      $rings = $c;
      $ext = array_shift($rings);
      if (!is_array($ext)) {
        return '';
      }
      $extPl = gmlPosListFromRingCoords($ext);
      if ($extPl === '') {
        return '';
      }
      $xml = '<gml:Polygon' . $srsAttr . '><gml:exterior><gml:LinearRing><gml:posList srsDimension="2">' . $extPl . '</gml:posList></gml:LinearRing></gml:exterior>';
      foreach ($rings as $hole) {
        if (!is_array($hole)) {
          continue;
        }
        $hPl = gmlPosListFromRingCoords($hole);
        if ($hPl === '') {
          continue;
        }
        $xml .= '<gml:interior><gml:LinearRing><gml:posList srsDimension="2">' . $hPl . '</gml:posList></gml:LinearRing></gml:interior>';
      }
      return $xml . '</gml:Polygon>';
    case 'MultiPoint':
      $mem = [];
      foreach ($c as $pt) {
        if (!is_array($pt) || count($pt) < 2) {
          continue;
        }
        $lon = (float) $pt[0];
        $lat = (float) $pt[1];
        $mem[] = '<gml:pointMember><gml:Point' . $srsAttr . '><gml:pos srsDimension="2">' . $lon . ' ' . $lat . '</gml:pos></gml:Point></gml:pointMember>';
      }
      return $mem === [] ? '' : '<gml:MultiPoint' . $srsAttr . '>' . implode('', $mem) . '</gml:MultiPoint>';
    case 'MultiLineString':
      $mem = [];
      foreach ($c as $line) {
        if (!is_array($line)) {
          continue;
        }
        $pl = gmlPosListFromLineCoords($line);
        if ($pl === '') {
          continue;
        }
        $mem[] = '<gml:lineStringMember><gml:LineString' . $srsAttr . '><gml:posList srsDimension="2">' . $pl . '</gml:posList></gml:LineString></gml:lineStringMember>';
      }
      return $mem === [] ? '' : '<gml:MultiLineString' . $srsAttr . '>' . implode('', $mem) . '</gml:MultiLineString>';
    case 'MultiPolygon':
      $mem = [];
      foreach ($c as $poly) {
        if (!is_array($poly) || $poly === []) {
          continue;
        }
        $rings = $poly;
        $ext = array_shift($rings);
        if (!is_array($ext)) {
          continue;
        }
        $extPl = gmlPosListFromRingCoords($ext);
        if ($extPl === '') {
          continue;
        }
        $p = '<gml:Polygon' . $srsAttr . '><gml:exterior><gml:LinearRing><gml:posList srsDimension="2">' . $extPl . '</gml:posList></gml:LinearRing></gml:exterior>';
        foreach ($rings as $hole) {
          if (!is_array($hole)) {
            continue;
          }
          $hPl = gmlPosListFromRingCoords($hole);
          if ($hPl === '') {
            continue;
          }
          $p .= '<gml:interior><gml:LinearRing><gml:posList srsDimension="2">' . $hPl . '</gml:posList></gml:LinearRing></gml:interior>';
        }
        $mem[] = '<gml:polygonMember>' . $p . '</gml:Polygon></gml:polygonMember>';
      }
      return $mem === [] ? '' : '<gml:MultiPolygon' . $srsAttr . '>' . implode('', $mem) . '</gml:MultiPolygon>';
    default:
      return '';
  }
}

/**
 * GML fragment for WFS-T wfs:Property/wfs:Value.
 * Point: GML 2 &lt;gml:coordinates&gt;lon,lat&lt;/gml:coordinates&gt; — QGIS Server WFS-T often ignores GML3 &lt;gml:pos&gt; while still returning SUCCESS.
 * Other types: keep geoJsonToGml311 (GML3 posList).
 */
function geoJsonToGmlForWfsTransaction(?array $g, string $srs = 'EPSG:4326'): string {
  if ($g === null || !is_array($g)) {
    return '';
  }
  $type = $g['type'] ?? '';
  if ($type === 'Point') {
    $c = $g['coordinates'] ?? null;
    if (!is_array($c) || count($c) < 2) {
      return '';
    }
    $lon = (float) $c[0];
    $lat = (float) $c[1];
    $srsEsc = htmlspecialchars($srs, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return '<gml:Point srsName="' . $srsEsc . '"><gml:coordinates>'
      . $lon . ',' . $lat
      . '</gml:coordinates></gml:Point>';
  }
  return geoJsonToGml311($g, $srs);
}

function wfsGeometryFieldNameFromXsd(string $xsd): string {
  if ($xsd === '') {
    return 'geom';
  }
  if (preg_match('/<xsd:element[^>]*\sname="(geom|geometry)"[^>]*>/i', $xsd, $m)) {
    return $m[1];
  }
  if (preg_match_all('/<xsd:element[^>]*\sname="([^"]+)"[^>]*\s(?:type|typeName)="([^"]+)"/i', $xsd, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $el) {
      if (stripos($el[2], 'gml:') !== false) {
        return $el[1];
      }
    }
  }
  return 'geom';
}

function coerce($val, ?string $type) {
  if ($type === 'boolean') {
    if (is_bool($val)) return $val;
    $s = is_string($val) ? strtolower(trim($val)) : $val;
    if ($s==='true'||$s==='1'||$s===1) return true;
    if ($s==='false'||$s==='0'||$s===0) return false;
    return $val;
  }
  if ($type === 'integer') {
    if ($val === '' || $val === null) return null;
    if (is_numeric($val)) return (int)$val;
    return $val;
  }
  if ($type === 'number') {
    if ($val === '' || $val === null) return null;
    if (is_numeric($val)) return (float)$val;
    return $val;
  }
  // strings or unknown
  if ($val === '') return null; // treat empty input as NULL
  return $val;
}

/**
 * DescribeFeatureType ? canonical names & rough types
 * Returns ['canon' => [norm => real], 'types' => [real => type]]
 */
function wfsDescribe(string $mapFile, string $collection): array {
  $url = WFS_BASE
       . '?MAP=' . rawurlencode($mapFile)
       . '&SERVICE=WFS&VERSION=1.1.1&REQUEST=DescribeFeatureType&TYPENAME='
       . rawurlencode($collection);
  [$code, $xsd] = http('GET', $url, ['Accept: application/xml']);
  if ($code < 200 || $code >= 300 || !$xsd) {
    return ['canon' => [], 'types' => [], 'geomField' => 'geom'];
  }

  $canon = []; $types = [];
  if (preg_match_all('/<xsd:element[^>]*\sname="([^"]+)"[^>]*\s(?:type|typeName)="?([^"\s>]+)"?/i', $xsd, $m, PREG_SET_ORDER)) {
    foreach ($m as $el) {
      $name = $el[1];
      $xsdType = strtolower($el[2] ?? '');
      if (in_array($name, ['geom','geometry'], true)) continue;
      $canon[normKey($name)] = $name;
      $types[$name] = mapXsdType($xsdType);
    }
  } else {
    // Fallback: just grab element names
    if (preg_match_all('/<xsd:element[^>]*\sname="([^"]+)"/i', $xsd, $mm)) {
      foreach ($mm[1] as $name) {
        if (in_array(strtolower($name), ['geom','geometry'], true)) continue;
        $canon[normKey($name)] = $name;
      }
    }
  }
  return ['canon' => $canon, 'types' => $types, 'geomField' => wfsGeometryFieldNameFromXsd($xsd)];
}

function mapXsdType(string $x): ?string {
  if ($x === '') return null;
  if (strpos($x, ':') !== false) $x = substr($x, strpos($x, ':')+1); // strip namespace
  if (in_array($x, ['int','integer','long','short','nonNegativeInteger','positiveInteger'], true)) return 'integer';
  if (in_array($x, ['float','double','decimal'], true)) return 'number';
  if ($x === 'boolean') return 'boolean';
  return 'string';
}

// Read QGS XML (from .qgz or .qgs)
function readProjectXml(string $mapFile): ?string {
  if (str_ends_with_ci($mapFile, '.qgz')) {
    if (!class_exists('ZipArchive')) return null;
    $zip = new ZipArchive();
    if ($zip->open($mapFile) !== true) return null;
    $qgs = $zip->getFromName('project.qgs');
    if ($qgs === false) {
      // fallback: first .qgs entry
      for ($i=0; $i<$zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        if (!empty($st['name']) && str_ends_with_ci($st['name'], '.qgs')) {
          $qgs = $zip->getFromIndex($i);
          break;
        }
      }
    }
    $zip->close();
    return is_string($qgs) ? $qgs : null;
  }
  return @file_get_contents($mapFile) ?: null;
}

/**
 * Project aliases ? canonical (for one layer name)
 * Returns [ norm(alias) => realName ]
 */
function aliasMapFromProject(string $mapFile, string $layerName): array {
  $xml = readProjectXml($mapFile);
  if (!$xml) return [];
  // Suppress errors in case of XML quirks
  $doc = @simplexml_load_string($xml);
  if (!$doc) return [];
  $aliasToReal = [];

  // Find <maplayer> for this layer name and read <aliases><alias field="real" name="Alias">
  $xpath = sprintf("//maplayer[layername='%s']/aliases/alias", str_replace("'", "&apos;", $layerName));
  $nodes = $doc->xpath($xpath);
  if ($nodes === false) return [];

  foreach ($nodes as $a) {
    $real  = (string)$a['field'];
    $alias = (string)$a['name'];
    if ($real !== '' && $alias !== '') {
      $aliasToReal[normKey($alias)] = $real;
    }
  }
  return $aliasToReal;
}

/** OAPIF collection list */
function listCollections(string $mapFile): array {
  $url = rtrim(OAPIF_BASE,'/').'/collections?MAP='.rawurlencode($mapFile);
  [$code,$body] = http('GET',$url,['Accept'=>'application/json']);
  if ($code < 200 || $code >= 300 || !$body) return [];
  $j = json_decode($body, true);
  $ids = [];
  foreach (($j['collections'] ?? []) as $c) {
    if (isset($c['id'])) $ids[] = (string)$c['id'];
  }
  return $ids;
}

/** Try to find which collection actually contains this feature id */
function resolveCollection(string $mapFile, string $fid, ?string $hint = null): ?string {
  $candidates = [];
  if ($hint) $candidates[] = $hint;
  foreach (listCollections($mapFile) as $cid) {
    if (!in_array($cid, $candidates, true)) $candidates[] = $cid;
  }
  foreach ($candidates as $cand) {
    $url = oapifItemUrl($mapFile, $cand, $fid);
    [$cc,$cb] = http('GET',$url,['Accept'=>'application/geo+json']);
    if ($cc>=200 && $cc<300 && $cb) {
      $j = json_decode($cb, true);
      if (($j['type'] ?? '') === 'Feature') return $cand;
    }
  }
  return null;
}

/**
 * WFS-T Update (optional GeoJSON geometry as GML in one wfs:Update).
 * Filter: ogc:FeatureId fid="typeName.numericId". WFS 1.1.x + GML2 Point coordinates improves QGIS persist rate vs 1.0 + gml:pos.
 *
 * @return array{0:int,1:string,2:string,3:string} HTTP code, response body, curl error message, exact request XML (empty if not built)
 */
function wfstUpdate(
  string $mapFile,
  string $collection,
  string $fid,
  array $updates,
  ?array $geometryGeoJson = null,
  ?string $geometryPropertyName = null
): array {
  $typeNameXml = htmlspecialchars($collection, ENT_XML1 | ENT_COMPAT, 'UTF-8');
  $featureIdFilterXml = htmlspecialchars($collection . '.' . $fid, ENT_XML1 | ENT_COMPAT, 'UTF-8');

  $props = '';
  foreach ($updates as $k => $v) {
    $kl = strtolower((string) $k);
    if (in_array($kl, ['fid', 'geom', 'geometry'], true)) {
      continue;
    }
    $name = htmlspecialchars((string) $k, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $val = is_null($v) ? '' : htmlspecialchars((string) $v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $props .= "<wfs:Property><wfs:Name>{$name}</wfs:Name><wfs:Value>{$val}</wfs:Value></wfs:Property>";
  }

  if ($geometryGeoJson !== null) {
    $gml = geoJsonToGmlForWfsTransaction($geometryGeoJson);
    if ($gml === '') {
      error_log('oapif_update WFS-T: aborted before Transaction XML (GeoJSON to GML failed)');
      return [400, '', 'gml_encode_failed', ''];
    }
    $gField = $geometryPropertyName ?? 'geom';
    $gNameXml = htmlspecialchars($gField, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $props .= "<wfs:Property><wfs:Name>{$gNameXml}</wfs:Name><wfs:Value>{$gml}</wfs:Value></wfs:Property>";
  }

  $xml = <<<XML
<wfs:Transaction service="WFS" version="1.1.0"
  xmlns:wfs="http://www.opengis.net/wfs"
  xmlns:ogc="http://www.opengis.net/ogc"
  xmlns:gml="http://www.opengis.net/gml">
  <wfs:Update typeName="{$typeNameXml}">
    {$props}
    <ogc:Filter><ogc:FeatureId fid="{$featureIdFilterXml}"/></ogc:Filter>
  </wfs:Update>
</wfs:Transaction>
XML;

  $url = WFS_BASE . '?MAP=' . rawurlencode($mapFile) . '&SERVICE=WFS&REQUEST=Transaction&VERSION=1.1.1';
  error_log('oapif_update WFS-T POST URL: ' . $url);
  error_log('oapif_update WFS-T request XML (exact): ' . $xml);
  [$code, $resp, $err] = http('POST', $url, ['Content-Type: text/xml'], $xml);
  error_log('oapif_update WFS-T response HTTP ' . $code . ' (raw): ' . ($resp ?? ''));
  if ($err !== '') {
    error_log('oapif_update WFS-T curl error: ' . $err);
  }
  return [$code, (string) $resp, (string) $err, $xml];
}

/**
 * WFS GetFeature (application/json) as a GeoJSON Feature — same path as the map uses after edits.
 * OAPIF items can lag behind WFS-T; use this to return fresh geometry to the client.
 */
function wfsGetFeatureAsGeoJsonFeature(string $mapFile, string $typeName, string $numericFid): ?array {
  $url = WFS_BASE . '?MAP=' . rawurlencode($mapFile)
    . '&SERVICE=WFS&VERSION=1.1.0&REQUEST=GetFeature'
    . '&TYPENAME=' . rawurlencode($typeName)
    . '&OUTPUTFORMAT=application/json'
    . '&FEATUREID=' . rawurlencode($typeName . '.' . $numericFid);
  [$code, $body, $err] = http('GET', $url, ['Accept: application/json']);
  if ($code < 200 || $code >= 300 || !is_string($body) || $body === '') {
    error_log('oapif_update wfsGetFeatureAsGeoJsonFeature HTTP ' . $code . ' curl=' . $err);
    return null;
  }
  $j = json_decode($body, true);
  if (!is_array($j) || empty($j['features'][0]) || !is_array($j['features'][0])) {
    error_log('oapif_update wfsGetFeatureAsGeoJsonFeature: empty features');
    return null;
  }
  $f = $j['features'][0];
  return [
    'type' => 'Feature',
    'id' => $typeName . '.' . $numericFid,
    'geometry' => $f['geometry'] ?? null,
    'properties' => $f['properties'] ?? [],
  ];
}

/** Fields appended to JSON so clients can inspect WFS-T traffic (mirrors error_log). */
function oapifWfsTDebugFields(string $requestXml, string $responseRaw, int $httpCode, string $curlErr = ''): array {
  $o = [
    'wfs_t_request_xml' => $requestXml,
    'wfs_t_response_raw' => $responseRaw,
    'wfs_t_http_code' => $httpCode,
  ];
  if ($curlErr !== '') {
    $o['wfs_t_curl_error'] = $curlErr;
  }
  return $o;
}

/** Bump project mtime so QGIS Server reloads after a successful WFS-T update. */
function oapifTouchQgisProjectAfterWfsT(): void {
  if (!defined('QGIS_FILENAME_ENCODED')) {
    return;
  }
  $map = urldecode(QGIS_FILENAME_ENCODED);
  if (file_exists($map)) {
    touch($map);
  }
}

/**
 * True if WFS Transaction response indicates success and no OGC exception.
 * WFS 1.0: <SUCCESS/> inside TransactionResponse; WFS 1.1: <totalUpdated>N</totalUpdated> with N > 0.
 */
function wfstTransactionSucceeded(string $body): bool {
  if ($body === '' || stripos($body, 'ServiceException') !== false || stripos($body, 'ows:Exception') !== false) {
    return false;
  }
  if (
    preg_match('/<(?:[\w-]+:)?SUCCESS\s*\/>/i', $body)
    || preg_match('/<(?:[\w-]+:)?SUCCESS\s*>\s*<\/(?:[\w-]+:)?SUCCESS>/i', $body)
  ) {
    return true;
  }
  if (!preg_match('/<[^>]*totalUpdated[^>]*>\s*(\d+)\s*</i', $body, $m)) {
    return false;
  }
  return (int) $m[1] > 0;
}

/**
 * UPDATE layer row geometry in PostGIS using the same pg_service.conf as the admin UI.
 * GeoJSON from the client is treated as EPSG:4326 (lon/lat), then transformed to the layer srid= when needed.
 *
 * @param array{service: string, pk: string, srid: int, schema: string, table: string, geom: string} $parsed
 * @return array{0: bool, 1: string}
 */
function oapifPostgresDirectUpdateGeometry(array $parsed, string $numericFid, ?array $geoNorm): array {
  try {
    if (!defined('PG_SERVICE_CONF') || !is_string(PG_SERVICE_CONF) || !is_file(PG_SERVICE_CONF)) {
      return [false, 'PG_SERVICE_CONF missing or file not found'];
    }
    putenv('PGSERVICEFILE=' . PG_SERVICE_CONF);
    $pdo = new PDO('pgsql:service=' . $parsed['service']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $quoteIdent = static function (string $ident): string {
      return '"' . str_replace('"', '""', $ident) . '"';
    };
    $qsch = $quoteIdent($parsed['schema']);
    $qtbl = $quoteIdent($parsed['table']);
    $qg = $quoteIdent($parsed['geom']);
    $qpk = $quoteIdent($parsed['pk']);

    $targetSrid = $parsed['srid'];
    if ($geoNorm === null) {
      $sql = "UPDATE {$qsch}.{$qtbl} SET {$qg} = NULL WHERE {$qpk} = ?";
      $st = $pdo->prepare($sql);
      $st->execute([$numericFid]);
    } else {
      $gj = json_encode($geoNorm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if ($gj === false) {
        return [false, 'json_encode(geometry) failed'];
      }
      if ($targetSrid === 4326) {
        $sql = "UPDATE {$qsch}.{$qtbl} SET {$qg} = ST_SetSRID(ST_GeomFromGeoJSON(?::text), 4326) WHERE {$qpk} = ?";
        $st = $pdo->prepare($sql);
        $st->execute([$gj, $numericFid]);
      } else {
        $sql = "UPDATE {$qsch}.{$qtbl} SET {$qg} = ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?::text), 4326), ?) WHERE {$qpk} = ?";
        $st = $pdo->prepare($sql);
        $st->execute([$gj, $targetSrid, $numericFid]);
      }
    }
    $n = $st->rowCount();
    if ($n !== 1) {
      return [false, 'UPDATE rowCount=' . $n . ' (expected 1) for pk=' . $numericFid];
    }
    return [true, 'ok'];
  } catch (Throwable $e) {
    error_log('oapifPostgresDirectUpdateGeometry: ' . $e->getMessage());
    return [false, $e->getMessage()];
  }
}

// ---------------------- Main ----------------------
try {    
  $in = json_decode(file_get_contents('php://input') ?: 'null', true, 512, JSON_THROW_ON_ERROR);

  $collection = $in['collection'] ?? null;         // "Apiary" | "auto"
  $id         = $in['id'] ?? null;                 // e.g., "Apiary.2" | "Area.56" | "56"
  $layer_id   = $in['layer_id'];                    // e.g. 1
  $updatesIn  = $in['updates'] ?? [];              // object of updates (may be empty when geometry is sent)
  $layerHint  = $in['layerHint'] ?? null;          // optional hint (e.g., "Area")
  $geoRaw     = $in['geometry'] ?? null;
  $featuresIn = isset($in['features']) && is_array($in['features']) ? $in['features'] : null;

  if ($featuresIn !== null) {
    $geoNorm = geometriesMergedFromGeoJsonFeatures($featuresIn);
    $nonEmptyFeatures = count($featuresIn) > 0;
    if ($nonEmptyFeatures && $geoNorm === null) {
      http_response_code(400);
      echo json_encode(['error' => 'Invalid or unsupported GeoJSON in "features"']);
      exit;
    }
  } else {
    $geoNorm = normalizePointGeometry($geoRaw) ?? normalizeGeoJsonGeometry($geoRaw);
  }

  $wantsGeometryChange = ($geoRaw !== null) || ($featuresIn !== null);

  if(!isset($_SESSION[SESS_USR_KEY])) { // if not logged in
      http_response_code(401); echo json_encode(['error'=>'Not authorized']); exit;
  }
  
  if (!isset($id))                      { http_response_code(400); echo json_encode(['error'=>'Missing "id"']); exit; }
  if (!is_array($updatesIn)) { http_response_code(400); echo json_encode(['error'=>'"updates" must be an object']); exit; }
  if ($featuresIn === null && $geoRaw !== null && $geoNorm === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or unsupported GeoJSON "geometry"']);
    exit;
  }
  $clearedByEmptyFeatures = ($featuresIn !== null && count($featuresIn) === 0);
  if (!$geoNorm && $updatesIn === [] && !$clearedByEmptyFeatures) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide non-empty "updates" and/or valid "geometry" or "features"']);
    exit;
  }
  if (isset($updatesIn['geometry']) || isset($updatesIn['coordinates']) || isset($updatesIn['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geometry fields are not allowed inside "updates"']);
    exit;
  }

  if (!isset($layer_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing "layer_id"']);
    exit;
  }

  // Allow geometry ONLY if provider is PostGIS
  require WWW_DIR . '/layers/' . $layer_id . '/env.php';
  $map = urldecode(QGIS_FILENAME_ENCODED);
  $fid = fidFromId($id);

  if ($wantsGeometryChange) {
    $layerName = null;
    if (is_string($collection) && $collection !== '' && strtolower($collection) !== 'auto') {
      $layerName = $collection;
    } else {
      $layerName = resolveCollection($map, $fid, is_string($layerHint) ? $layerHint : null);
    }
    if (!is_string($layerName) || $layerName === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Could not resolve collection for geometry edit']);
      exit;
    }
    if (qgis_classify_layer_provider($map, $layerName) !== 'postgres') {
      http_response_code(400);
      echo json_encode(['error' => 'Geometry editing allowed only for PostGIS layers']);
      exit;
    }
  }
  $store_id = basename(dirname($map));

  // check user is owner
  $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
  $obj = new qgs_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
  if($store_id && !$obj->isOwnedByUs($store_id)){
      http_response_code(403);	echo json_encode(['error'=>'Update Forbidden']); exit;
  }
  
  // Auto-resolve collection when needed
  if (!is_string($collection) || $collection === '' || strtolower($collection) === 'auto') {
    $resolved = resolveCollection($map, $fid, is_string($layerHint) ? $layerHint : null);
    if ($resolved) $collection = $resolved;
  }
  if (!is_string($collection) || $collection === '') {
    http_response_code(400);
    echo json_encode(['error'=>'Could not resolve collection for feature id', 'id'=>$id, 'hint'=>$layerHint]);
    exit;
  }

  $itemUrl = oapifItemUrl($map, $collection, $fid);

  // Canonical names & types from DescribeFeatureType
  $desc     = wfsDescribe($map, $collection);
  $canonMap = $desc['canon'] ?? [];
  $types    = $desc['types'] ?? [];

  // Aliases from project
  $aliasMap = aliasMapFromProject($map, $collection);  // norm(alias) => real

  // Translate incoming updates
  $updates = [];
  $unknown = [];
  foreach ($updatesIn as $k => $v) {
    $nk = normKey((string)$k);
    if (isset($canonMap[$nk])) {
      $real = $canonMap[$nk];
    } elseif (isset($aliasMap[$nk])) {
      $real = $aliasMap[$nk];
    } else {
      $unknown[] = $k;
      continue;
    }
    $type = $types[$real] ?? (preg_match('/_id$|^nbr_|^nr_/', $real) ? 'integer' : (preg_match('/^(is_|has_)/',$real) ? 'boolean' : null));
    $updates[$real] = coerce($v, $type);
  }

  // If DescribeFeatureType failed entirely, trust caller's keys (best-effort)
  if (!$canonMap && !$updates) {
    foreach ($updatesIn as $k=>$v) {
      $kl = strtolower((string)$k);
      if (in_array($kl, ['fid','geom','geometry'], true)) continue;
      $updates[(string)$k] = $v;
    }
  }

  if (!$updates && !$geoNorm && !$wantsGeometryChange) {
    http_response_code(400);
    echo json_encode(['error'=>'No valid editable fields found','unknown_keys'=>$unknown]);
    exit;
  }

  // Never allow fid/geometry edits
//  unset($updates['fid'],$updates['geom'],$updates['geometry']);
$never = ['fid','geom','geometry','uuid','id']; // extend per your schema if needed
foreach ($never as $bad) { unset($updates[$bad]); }

  // Geometry: prefer direct PostGIS UPDATE when datasource is parseable (avoids false WFS-T SUCCESS with no DB change)
  $geometryWriteRequested = ($geoNorm !== null) || $clearedByEmptyFeatures;
  if ($geometryWriteRequested) {
    $geomField = $desc['geomField'] ?? 'geom';
    $wfsXml = '';
    $wc = 0;
    $wb = '';
    $werr = '';

    $layerDs = qgis_maplayer_info_for_name($map, $collection);
    $pgParsed = ($layerDs && ($layerDs['provider'] ?? '') === 'postgres')
      ? qgis_parse_postgres_datasource($layerDs['datasource'])
      : null;
    if ($pgParsed !== null && strcasecmp($pgParsed['geom'], $geomField) !== 0) {
      error_log('oapif_update: datasource geom "' . $pgParsed['geom'] . '" vs DescribeFeatureType "' . $geomField . '" (using datasource column)');
    }

    $usedPostgresDirect = false;
    if ($pgParsed !== null) {
      [$pgOk, $pgDetail] = oapifPostgresDirectUpdateGeometry($pgParsed, $fid, $geoNorm);
      if ($pgOk) {
        $usedPostgresDirect = true;
        error_log('oapif_update: geometry saved via postgres direct (' . $pgParsed['schema'] . '.' . $pgParsed['table'] . '.' . $pgParsed['geom'] . ')');
      } else {
        error_log('oapif_update: postgres direct geometry failed, using WFS-T: ' . $pgDetail);
      }
    }

    if ($usedPostgresDirect) {
      if ($updates !== []) {
        [$wc, $wb, $werr, $wfsXml] = wfstUpdate($map, $collection, $fid, $updates, null, null);
        if (!($wc >= 200 && $wc < 300 && wfstTransactionSucceeded($wb))) {
          http_response_code($wc ?: 502);
          echo json_encode(array_merge(
            [
              'error' => 'Geometry saved to PostGIS but attribute update (WFS-T) failed',
              'postgres_direct_detail' => $pgDetail ?? '',
            ],
            oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr)
          ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          exit;
        }
      }
      oapifTouchQgisProjectAfterWfsT();
      $out = wfsGetFeatureAsGeoJsonFeature($map, $collection, $fid);
      if (!is_array($out)) {
        [$rc, $rj, $rerr] = http('GET', $itemUrl, ['Accept: application/geo+json']);
        $out = json_decode($rj ?: 'null', true);
        if (is_array($out)) {
          $out['_geometry_source'] = 'oapif';
        } else {
          $out = [
            'type' => 'Feature',
            'id' => $collection . '.' . $fid,
            'geometry' => null,
            'properties' => [],
            '_geometry_source' => 'none',
            '_oapif_body' => is_string($rj) ? substr($rj, 0, 500) : '',
          ];
        }
      } else {
        $out['_geometry_source'] = 'wfs_getfeature';
      }
      $out['_geometry_persist'] = 'postgres_direct';
      $layer_name = qcarta_project_key_from_qgis_filename($map);
      $layer = $layer_name;
      oapifPurgeTileCacheAfterGeometry($layer, 'postgres_direct');
      http_response_code(200);
      $debug = $wfsXml !== ''
        ? oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr)
        : [
          'wfs_t_request_xml' => '',
          'wfs_t_response_raw' => '(geometry via postgres direct; no WFS-T for geometry)',
          'wfs_t_http_code' => 0,
        ];
      echo json_encode(array_merge($out, $debug), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    if ($geoNorm === null && $clearedByEmptyFeatures) {
      http_response_code(400);
      echo json_encode(['error' => 'Clearing geometry requires direct PostGIS write (layer datasource not available).']);
      exit;
    }

    [$wc, $wb, $werr, $wfsXml] = wfstUpdate($map, $collection, $fid, $updates, $geoNorm, $geomField);
    if ($wc === 400 && $wb === '') {
      http_response_code(400);
      echo json_encode(array_merge(
        ['error' => 'Could not encode geometry as GML for WFS-T'],
        oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr)
      ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }
    if ($wc >= 200 && $wc < 300 && wfstTransactionSucceeded($wb)) {
      oapifTouchQgisProjectAfterWfsT();
      $out = wfsGetFeatureAsGeoJsonFeature($map, $collection, $fid);
      if (!is_array($out)) {
        [$rc, $rj, $rerr] = http('GET', $itemUrl, ['Accept: application/geo+json']);
        $out = json_decode($rj ?: 'null', true);
        if (is_array($out)) {
          $out['_geometry_source'] = 'oapif';
        } else {
          $out = [
            'type' => 'Feature',
            'id' => $collection . '.' . $fid,
            'geometry' => null,
            'properties' => [],
            '_geometry_source' => 'none',
            '_oapif_body' => is_string($rj) ? substr($rj, 0, 500) : '',
          ];
        }
      } else {
        $out['_geometry_source'] = 'wfs_getfeature';
      }
      $out['_geometry_persist'] = 'wfs_t';
      $layer_name = qcarta_project_key_from_qgis_filename($map);
      $layer = $layer_name;
      oapifPurgeTileCacheAfterGeometry($layer, 'wfs_t');
      http_response_code(200);
      $out = array_merge($out, oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr));
      echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }
    http_response_code($wc ?: 502);
    echo json_encode(array_merge(
      ['error' => 'Geometry update (WFS-T) failed'],
      oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr)
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  // 1) Try OAPIF PATCH (merge-patch)
  $payload = json_encode(['properties'=>$updates], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  [$pc,$pb,$perr] = http('PATCH', $itemUrl, [
    'Accept: application/json',
    'Content-Type: application/merge-patch+json'
  ], $payload);

  $badProps = ($pc===400 && is_string($pb) && str_contains($pb, 'Feature properties are not valid'));
  if ($pc>=200 && $pc<300 && !$badProps) { http_response_code($pc); echo $pb; exit; }

  // 2) Try OAPIF PUT (full feature)
  [$gc,$gj,$gerr] = http('GET', $itemUrl, ['Accept: application/geo+json']);
  if ($gc>=200 && $gc<300 && $gj) {
    $feat = json_decode($gj, true);
    if (is_array($feat) && ($feat['type'] ?? '') === 'Feature') {
      $feat['properties'] = array_merge($feat['properties'] ?? [], $updates);
      [$uc,$ub,$uerr] = http('PUT', $itemUrl, [
        'Accept: application/json',
        'Content-Type: application/geo+json'
      ], json_encode($feat, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      if ($uc>=200 && $uc<300) { http_response_code($uc); echo $ub; exit; }
    }
  }

  // 3) WFS-T Update (proven path)
  [$wc, $wb, $werr, $wfsXml] = wfstUpdate($map, $collection, $fid, $updates);
  if ($wc >= 200 && $wc < 300 && wfstTransactionSucceeded($wb)) {
    oapifTouchQgisProjectAfterWfsT();
    [$rc, $rj, $rerr] = http('GET', $itemUrl, ['Accept: application/geo+json']);
    http_response_code($rc ?: 200);
    $out = json_decode($rj ?: 'null', true);
    if (is_array($out)) {
      $out = array_merge($out, oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr));
      echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
      echo json_encode(array_merge(
        ['ok' => true, 'feature_json' => $rj],
        oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr)
      ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
  }

  http_response_code($wc ?: (int) ($uc ?? 0) ?: ($pc ?: 502));
  echo json_encode(array_merge(
    ['error' => 'All update strategies failed'],
    oapifWfsTDebugFields($wfsXml, $wb, $wc, $werr)
  ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()]);
}
