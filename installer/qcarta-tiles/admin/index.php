<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$stmt = $pdo->query('SELECT * FROM maps ORDER BY name');
$maps = $stmt->fetchAll();

$baseUrl = tileServiceBase($config);

$urlsJson = [];
foreach ($maps as $m) {
    $urlsJson[(int) $m['id']] = [
        'xyz' => buildXYZTemplate($config, $m),
        'wms' => buildWmsTemplate($config, $m),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maps — Tile/WMS admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<nav class="blue darken-2">
    <div class="nav-wrapper container">
        <span class="brand-logo">Map registry</span>
    </div>
</nav>

<div class="container" style="margin-top:24px;">
    <div class="row valign-wrapper">
        <div class="col s12 m8">
            <h5>Maps</h5>
            <p class="grey-text">Tile service: <code><?= h($baseUrl) ?></code></p>
        </div>
        <div class="col s12 m4 right-align">
            <a href="edit.php" class="btn waves-effect waves-light blue darken-2">
                <i class="material-icons left">add</i>Add Map
            </a>
        </div>
    </div>

    <table class="striped responsive-table">
        <thead>
        <tr>
            <th>Name</th>
            <th>Rendering</th>
            <th>Quality</th>
            <th>Updated</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($maps as $m): ?>
            <tr>
                <td><?= h($m['name']) ?></td>
                <td><?= h($m['rendering_mode']) ?></td>
                <td><?= h($m['quality_preset']) ?></td>
                <td><?= h((string) $m['updated_at']) ?></td>
                <td>
                    <a href="edit.php?id=<?= (int) $m['id'] ?>" class="btn-small blue lighten-1">Edit</a>
                    <button type="button" class="btn-small grey lighten-1 urls-btn" data-id="<?= (int) $m['id'] ?>">URLs</button>
                    <form action="delete.php" method="post" style="display:inline;" onsubmit="return confirm('Delete this map?');">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button type="submit" class="btn-small red lighten-2">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$maps): ?>
            <tr><td colspan="5">No maps yet. Click <strong>Add Map</strong>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="modal-urls" class="modal modal-fixed-footer">
    <div class="modal-content">
        <h5>Generated URLs</h5>
        <p class="grey-text">Copy into Leaflet, OpenLayers, or your app.</p>
        <div class="input-field">
            <textarea id="url-xyz" class="materialize-textarea" readonly></textarea>
            <label for="url-xyz">XYZ (tiles)</label>
        </div>
        <div class="input-field">
            <textarea id="url-wms" class="materialize-textarea" readonly></textarea>
            <label for="url-wms">WMS base (add BBOX, WIDTH, HEIGHT)</label>
        </div>
    </div>
    <div class="modal-footer">
        <a href="#!" class="modal-close waves-effect waves-green btn-flat">Close</a>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
const URLS = <?= json_encode($urlsJson, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modal-urls');
    const modal = M.Modal.init(modalEl, {});
    document.querySelectorAll('.urls-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-id');
            const u = URLS[id];
            if (!u) return;
            const taX = document.getElementById('url-xyz');
            const taW = document.getElementById('url-wms');
            taX.value = u.xyz;
            taW.value = u.wms;
            M.textareaAutoResize(taX);
            M.textareaAutoResize(taW);
            modal.open();
        });
    });
});
</script>
</body>
</html>
