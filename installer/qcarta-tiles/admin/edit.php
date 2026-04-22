<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = [
    'name' => '',
    'qgis_map_path' => '',
    'layers' => '',
    'rendering_mode' => 'tile',
    'quality_preset' => 'balanced',
    'image_format' => 'png',
    'transparent' => 1,
    'cache_enabled' => 1,
];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM maps WHERE id = ?');
    $stmt->execute([$id]);
    $loaded = $stmt->fetch();
    if (!$loaded) {
        http_response_code(404);
        exit('Map not found');
    }
    $row = $loaded;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'clear_cache' && $id > 0) {
    $token = $config['cache_purge_token'] ?? '';
    if ($token === '') {
        header('Location: edit.php?id=' . $id . '&purge_err=1');
        exit;
    }
    $stmt = $pdo->prepare('SELECT qgis_map_path FROM maps WHERE id = ?');
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if ($m) {
        $base = basename($m['qgis_map_path']);
        $scope = preg_replace('/\.(qgs|qgz)$/i', '', $base);
        $scope = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $scope);
        $url = tileServiceBase($config) . '/admin/cache/purge';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: text/plain'],
            CURLOPT_POSTFIELDS => $scope !== '' ? $scope : 'all',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        header('Location: edit.php?id=' . $id . ($code === 200 ? '&purged=1' : '&purge_http=' . $code));
        exit;
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'save') {
    $row['name'] = trim((string) ($_POST['name'] ?? ''));
    $row['qgis_map_path'] = trim((string) ($_POST['qgis_map_path'] ?? ''));
    $row['layers'] = trim((string) ($_POST['layers'] ?? ''));
    $row['rendering_mode'] = ($_POST['rendering_mode'] ?? 'tile') === 'wms' ? 'wms' : 'tile';
    $qp = $_POST['quality_preset'] ?? 'balanced';
    $row['quality_preset'] = in_array($qp, ['performance', 'balanced', 'quality'], true) ? $qp : 'balanced';
    $row['image_format'] = ($_POST['image_format'] ?? 'png') === 'jpeg' ? 'jpeg' : 'png';
    $row['transparent'] = isset($_POST['transparent']) ? 1 : 0;
    $row['cache_enabled'] = isset($_POST['cache_enabled']) ? 1 : 0;

    if ($row['name'] === '' || $row['qgis_map_path'] === '') {
        $errors[] = 'Name and QGIS map path are required.';
    }

    if (!$errors) {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE maps SET name=?, qgis_map_path=?, layers=?, rendering_mode=?, quality_preset=?, image_format=?, transparent=?, cache_enabled=? WHERE id=?'
            );
            $stmt->execute([
                $row['name'], $row['qgis_map_path'], $row['layers'] ?: null,
                $row['rendering_mode'], $row['quality_preset'], $row['image_format'],
                $row['transparent'], $row['cache_enabled'], $id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO maps (name, qgis_map_path, layers, rendering_mode, quality_preset, image_format, transparent, cache_enabled) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $row['name'], $row['qgis_map_path'], $row['layers'] ?: null,
                $row['rendering_mode'], $row['quality_preset'], $row['image_format'],
                $row['transparent'], $row['cache_enabled'],
            ]);
            $id = (int) $pdo->lastInsertId();
        }
        header('Location: edit.php?id=' . $id . '&saved=1');
        exit;
    }
}

$purgeMsg = '';
if (!empty($_GET['purged'])) {
    $purgeMsg = 'Cache purge completed.';
} elseif (!empty($_GET['purge_err'])) {
    $purgeMsg = 'Set cache_purge_token in config.php (same as QCARTA_CACHE_PURGE_TOKEN).';
} elseif (isset($_GET['purge_http'])) {
    $purgeMsg = 'Purge failed (HTTP ' . (int) $_GET['purge_http'] . ').';
}

$xyzUrl = buildXYZTemplate($config, $row);
$wmsUrl = buildWmsTemplate($config, $row);
$preset = getPresetConfig((string) ($row['quality_preset'] ?? 'balanced'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Edit' : 'Add' ?> map</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<nav class="blue darken-2">
    <div class="nav-wrapper container">
        <a href="index.php" class="brand-logo" style="padding-left:0;">← Maps</a>
    </div>
</nav>

<div class="container" style="margin-top:24px;">
    <?php if (!empty($_GET['saved'])): ?>
        <div class="card-panel green lighten-4">Saved.</div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
        <div class="card-panel red lighten-4"><?= h($e) ?></div>
    <?php endforeach; ?>
    <?php if ($purgeMsg !== ''): ?>
        <div class="card-panel blue lighten-4"><?= h($purgeMsg) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= h($id ? 'edit.php?id=' . $id : 'edit.php') ?>" class="col s12">
        <input type="hidden" name="form" value="save">

        <div class="card">
            <div class="card-content">
                <span class="card-title">Map info</span>
                <div class="row">
                    <div class="input-field col s12">
                        <input id="name" name="name" type="text" required value="<?= h($row['name']) ?>">
                        <label for="name">Name</label>
                    </div>
                    <div class="input-field col s12">
                        <input id="qgis_map_path" name="qgis_map_path" type="text" required value="<?= h($row['qgis_map_path']) ?>"
                               placeholder="/var/www/projects/demo.qgs">
                        <label for="qgis_map_path">QGIS map path</label>
                    </div>
                    <div class="input-field col s12">
                        <input id="layers" name="layers" type="text" value="<?= h((string) $row['layers']) ?>"
                               placeholder="layer1,layer2">
                        <label for="layers">Layers</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Rendering</span>
                <p>
                    <label>
                        <input name="rendering_mode" type="radio" value="tile" <?= $row['rendering_mode'] === 'tile' ? 'checked' : '' ?> />
                        <span>Tile (fast) — use XYZ URL below</span>
                    </label>
                </p>
                <p>
                    <label>
                        <input name="rendering_mode" type="radio" value="wms" <?= $row['rendering_mode'] === 'wms' ? 'checked' : '' ?> />
                        <span>WMS (dynamic) — use WMS URL below</span>
                    </label>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Quality preset</span>
                <div class="input-field">
                    <select name="quality_preset" id="quality_preset">
                        <option value="performance" <?= $row['quality_preset'] === 'performance' ? 'selected' : '' ?>>Performance (fast)</option>
                        <option value="balanced" <?= $row['quality_preset'] === 'balanced' ? 'selected' : '' ?>>Balanced (default)</option>
                        <option value="quality" <?= $row['quality_preset'] === 'quality' ? 'selected' : '' ?>>Quality (best)</option>
                    </select>
                    <label>Quality</label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Output</span>
                <div class="input-field">
                    <select name="image_format" id="image_format">
                        <option value="png" <?= $row['image_format'] === 'png' ? 'selected' : '' ?>>PNG</option>
                        <option value="jpeg" <?= $row['image_format'] === 'jpeg' ? 'selected' : '' ?>>JPEG</option>
                    </select>
                    <label>Format</label>
                </div>
                <div class="switch" style="margin-top:16px;">
                    <label>
                        Transparent
                        <input type="checkbox" name="transparent" <?= ((int) $row['transparent']) ? 'checked' : '' ?>>
                        <span class="lever"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Cache</span>
                <div class="switch">
                    <label>
                        Enable cache
                        <input type="checkbox" name="cache_enabled" <?= ((int) $row['cache_enabled']) ? 'checked' : '' ?>>
                        <span class="lever"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-content">
                <span class="card-title">Generated URLs</span>
                <p class="grey-text">Preset maps to query params <code>meta=<?= (int) $preset['metatile'] ?></code> and <code>buffer=<?= (int) $preset['buffer'] ?></code> (overrides service env defaults).</p>
                <div class="input-field">
                    <textarea id="out-xyz" class="materialize-textarea" readonly><?= h($xyzUrl) ?></textarea>
                    <label for="out-xyz">XYZ</label>
                </div>
                <div class="input-field">
                    <textarea id="out-wms" class="materialize-textarea" readonly><?= h($wmsUrl) ?></textarea>
                    <label for="out-wms">WMS</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-large blue darken-2 waves-effect waves-light">Save</button>
        <a href="index.php" class="btn-flat">Cancel</a>
    </form>

    <?php if ($id > 0): ?>
    <div class="card" style="margin-top:24px;">
        <div class="card-content">
            <span class="card-title">Clear cache</span>
            <p class="grey-text">POSTs to <code><?= h(tileServiceBase($config)) ?>/admin/cache/purge</code> with Bearer token; body = sanitized project basename (set <code>cache_purge_token</code> in <code>config.php</code>).</p>
            <form method="post" action="edit.php?id=<?= (int) $id ?>" onsubmit="return confirm('Purge cached tiles for this project on the tile service?');">
                <input type="hidden" name="form" value="clear_cache">
                <button type="submit" class="btn orange darken-2 waves-effect">Clear cache</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    M.FormSelect.init(document.querySelectorAll('select'));
    M.updateTextFields();
    const ta = document.querySelectorAll('.materialize-textarea');
    ta.forEach(function (el) { M.textareaAutoResize(el); });
});
</script>
</body>
</html>
