<?php
session_start(['read_and_close' => true]);
require('incl/const.php');

if (!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
	header('Location: ../login.php');
	exit(0);
}

$settingsPath = DATA_DIR . '/settings.json';
$settings = [];
if (is_readable($settingsPath)) {
	$decoded = json_decode(file_get_contents($settingsPath), true);
	if (is_array($decoded)) {
		$settings = $decoded;
	}
}
$current = $settings['sidebar_theme'] ?? 'default';
if (!in_array($current, ['default', 'business'], true)) {
	$current = 'default';
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta — Switch themes</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<?php include('incl/meta.php'); ?>
<link href="assets/dist/css/side_menu.css" rel="stylesheet">
<style>
	html, body { height: 100%; margin: 0; }
	body { background-color: #f8f9fa; min-height: 100vh; }
	#content { position: relative; padding: 0.5rem 1rem 1rem 1rem; margin-left: 210px; width: calc(100% - 210px); }
	.page-title { font-size: 1.75rem; font-weight: 400; margin: 0 0 1rem 0; }
	.theme-card { max-width: 560px; background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
</style>
</head>
<body>
<div id="container" style="display:block">
	<?php const NAV_SEL = 'Administration'; const TOP_PATH = '../'; const ADMIN_PATH = '';
	include('incl/navbar.php'); ?>
	<br class="clear">
	<?php include('incl/sidebar.php'); ?>

	<div id="content">
		<p class="mb-2"><a href="<?= ADMIN_PATH ?>syssettings.php" class="text-decoration-none">&larr; Back to Settings</a></p>
		<h1 class="page-title">Switch themes</h1>
		<p class="text-muted">Controls the map sidebar appearance via <code>sidebar_theme</code> in <code>settings.json</code> (Default or Business).</p>

		<div class="theme-card border">
			<form id="theme_form">
				<div class="mb-3">
					<div class="form-check mb-2">
						<input class="form-check-input" type="radio" name="sidebar_theme" id="theme_default" value="default" <?= $current === 'default' ? 'checked' : '' ?>>
						<label class="form-check-label" for="theme_default"><strong>Default</strong> — standard sidebar styling</label>
					</div>
					<div class="form-check">
						<input class="form-check-input" type="radio" name="sidebar_theme" id="theme_business" value="business" <?= $current === 'business' ? 'checked' : '' ?>>
						<label class="form-check-label" for="theme_business"><strong>Business</strong> — flat sidebar (<code>theme-business</code>)</label>
					</div>
				</div>
				<button type="button" class="btn btn-primary" id="btn_save_theme">Save</button>
			</form>
		</div>
	</div>
</div>
<?php include('incl/footer.php'); ?>
<script>
$(function () {
	$('#btn_save_theme').on('click', function () {
		var btn = $(this);
		btn.prop('disabled', true);
		var fd = new FormData();
		fd.append('sidebar_theme', $('#theme_form input[name="sidebar_theme"]:checked').val());
		$.ajax({
			type: 'POST',
			url: 'action/sidebar_theme.php',
			data: fd,
			processData: false,
			contentType: false,
			dataType: 'json'
		}).done(function (r) {
			alert(r.success ? r.message : r.message);
		}).fail(function () {
			alert('Request failed.');
		}).always(function () {
			btn.prop('disabled', false);
		});
	});
});
</script>
</body>
</html>
