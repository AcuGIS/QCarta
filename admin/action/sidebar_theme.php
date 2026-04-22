<?php
session_start(['read_and_close' => true]);
require('../incl/const.php');

header('Content-Type: application/json; charset=utf-8');

$result = ['success' => false, 'message' => 'Unauthorized'];

if (isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin') {
	$allowed = ['default', 'business'];
	$theme = isset($_POST['sidebar_theme']) ? trim((string) $_POST['sidebar_theme']) : '';

	if (!in_array($theme, $allowed, true)) {
		$result = ['success' => false, 'message' => 'Invalid theme.'];
	} else {
		$path = DATA_DIR . '/settings.json';
		if (!is_readable($path)) {
			$result = ['success' => false, 'message' => 'settings.json not found or not readable.'];
		} else {
			$raw = file_get_contents($path);
			$settings = json_decode($raw, true);
			if (!is_array($settings)) {
				$settings = [];
			}
			$settings['sidebar_theme'] = $theme;
			$written = file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			if ($written === false) {
				$result = ['success' => false, 'message' => 'Could not write settings.json.'];
			} else {
				$result = ['success' => true, 'message' => 'Sidebar theme saved.', 'sidebar_theme' => $theme];
			}
		}
	}
}

echo json_encode($result);
