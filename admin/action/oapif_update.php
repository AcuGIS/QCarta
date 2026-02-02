<?php
declare(strict_types=1);
    
session_start(['read_and_close' => true]);
require('../incl/const.php');
require('../class/database.php');
require('../class/table.php');
require('../class/table_ext.php');
require('../class/qgs.php');
	
header('Content-Type: application/json');

/**
 * QCarta OAPIF attribute editor
 * - Auto-resolves collection when UI sends collection:"auto"
 * - Accepts aliases from project & canonical names
 * - Tries OAPIF PATCH, then PUT, then WFS-T Update
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
  if ($code < 200 || $code >= 300 || !$xsd) return ['canon'=>[], 'types'=>[]];

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
  return ['canon'=>$canon, 'types'=>$types];
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

/** WFS-T Update */
function wfstUpdate(string $mapFile, string $collection, string $fid, array $updates): array {
  // Build <wfs:Property> list
  $props = '';
  foreach ($updates as $k=>$v) {
    $kl = strtolower($k);
    if (in_array($kl, ['fid','geom','geometry'], true)) continue;
    $name = htmlspecialchars($k, ENT_XML1|ENT_COMPAT, 'UTF-8');
    $val  = is_null($v) ? '' : htmlspecialchars((string)$v, ENT_XML1|ENT_COMPAT, 'UTF-8');
    $props .= "<wfs:Property><wfs:Name>{$name}</wfs:Name><wfs:Value>{$val}</wfs:Value></wfs:Property>";
  }
  $xml = <<<XML
<wfs:Transaction service="WFS" version="1.1.0"
  xmlns:wfs="http://www.opengis.net/wfs"
  xmlns:ogc="http://www.opengis.net/ogc">
  <wfs:Update typeName="{$collection}">
    {$props}
    <ogc:Filter><ogc:FeatureId fid="{$collection}.{$fid}"/></ogc:Filter>
  </wfs:Update>
</wfs:Transaction>
XML;

  $url = WFS_BASE.'?MAP='.rawurlencode($mapFile).'&SERVICE=WFS&REQUEST=Transaction&VERSION=1.1.1';
  return http('POST', $url, ['Content-Type'=>'text/xml'], $xml);
}

// ---------------------- Main ----------------------
try {    
  $in = json_decode(file_get_contents('php://input') ?: 'null', true, 512, JSON_THROW_ON_ERROR);

  $collection = $in['collection'] ?? null;         // "Apiary" | "auto"
  $id         = $in['id'] ?? null;                 // e.g., "Apiary.2" | "Area.56" | "56"
  $layer_id   = $in['layer_id'];                    // e.g. 1
  $updatesIn  = $in['updates'] ?? null;            // object of updates
  $layerHint  = $in['layerHint'] ?? null;          // optional hint (e.g., "Area")

  if(!isset($_SESSION[SESS_USR_KEY])) { // if not logged in
      http_response_code(401); echo json_encode(['error'=>'Not authorized']); exit;
  }
  
  if (!isset($id))                      { http_response_code(400); echo json_encode(['error'=>'Missing "id"']); exit; }
  if (!is_array($updatesIn) || !$updatesIn) { http_response_code(400); echo json_encode(['error'=>'"updates" must be a non-empty object']); exit; }
  if (isset($updatesIn['geometry'])||isset($updatesIn['coordinates'])||isset($updatesIn['type'])) {
    http_response_code(400); echo json_encode(['error'=>'Geometry edits are not allowed']); exit;
  }
  
  require(WWW_DIR.'/layers/'.$layer_id.'/env.php');
  $map = urldecode(QGIS_FILENAME_ENCODED);
  $fid = fidFromId($id);
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

  if (!$updates) {
    http_response_code(400);
    echo json_encode(['error'=>'No valid editable fields found','unknown_keys'=>$unknown]);
    exit;
  }

  // Never allow fid/geometry edits
//  unset($updates['fid'],$updates['geom'],$updates['geometry']);
$never = ['fid','geom','geometry','uuid','id']; // extend per your schema if needed
foreach ($never as $bad) { unset($updates[$bad]); }


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
  [$wc,$wb,$werr] = wfstUpdate($map, $collection, $fid, $updates);
  if ($wc>=200 && $wc<300 && stripos($wb, '<totalUpdated>') !== false) {
    // Return fresh feature via OAPIF
    [$rc,$rj,$rerr] = http('GET', $itemUrl, ['Accept: application/geo+json']);
    http_response_code($rc ?: 200);
    echo $rj ?: json_encode(['ok'=>true]);
    exit;
  }

  // Bubble up best error we have
  http_response_code($wc ?: ($uc ?? 0) ?: ($pc ?: 502));
  echo $wb ?: ($ub ?? '') ?: ($pb ?? json_encode(['error'=>'All update strategies failed']));

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>$e->getMessage()]);
}
