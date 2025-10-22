<?php
  session_start(['read_and_close' => true]);
  require('incl/const.php');
  require('incl/app.php');

  if (!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
    header('Location: ../login.php');
    exit(0);
  }

  // Base filename
  $yaml_file = 'map_index.css';

  // Sanitize the version tag (YYYY-mm-dd-HH-ii-ss)
  $v = isset($_GET['v']) ? (string)$_GET['v'] : '';
  if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $v)) {
    $v = ''; // ignore unexpected format
  }

  // If a version tag was chosen, point to that snapshot file
  if ($v !== '' && is_file(WWW_DIR.'/assets/dist/css/'.$yaml_file.'_'.$v)) {
    $yaml_file .= '_'.$v;
  }

  // Build the list of available snapshot *tags* (strings like 2025-09-10-14-22-31)
  // We derive tags from filenames map_index.css_<tag>
  function list_theme_snapshots(string $dir, string $prefix): array {
    $dir = rtrim($dir, '/').'/';
    $len = strlen($prefix);
    $tags = [];
    foreach (glob($dir.$prefix.'*') as $path) {
      $base = basename($path);
      if (strncmp($base, $prefix, $len) === 0) {
        $tag = substr($base, $len);
        if ($tag !== '') $tags[] = $tag;
      }
    }
    // newest first (works with YYYY-mm-dd-HH-ii-ss)
    rsort($tags);
    return $tags;
  }

  // Use our function (keeps your existing paths/prefix)
  $css_snapshots = list_theme_snapshots(WWW_DIR.'/assets/dist/css', 'map_index.css_');
?>

<!DOCTYPE html>
<html dir="ltr" lang="en" >

<head>
	<title>QCarta</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/lint/yaml-lint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/yaml/yaml.min.js"></script>
		
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">
	<style>
	.CodeMirror {
	  border: 1px solid #eee;
	  height: auto;
	}
	</style>
	
	<script>
$(document).ready(function() {
	
	$('#css_snapshot').on("change", function() {
		let v = $(this).val();
		let url = 'edit_theme.php';
		if(v != ''){
			url += '?v=' + v;
		}
		window.location.href = url;
	});
});
	</script>
</head>

<body>
  
	<div id="container">
		
		<?php const NAV_SEL = 'Services'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
		
		<div id="content">
		
				<h1>Theme Editor</h1>
			<div style="width: 99%">
				
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
						<div class="row align-items-center">
								<div class="col-6">
									<label for="css_snapshot" class="form-label">Version:</label>
                                    <select id="css_snapshot" name="css_snapshot">
                                    <option value="" <?= $v === '' ? 'selected' : '' ?>>Latest</option>
                                    <?php foreach ($css_snapshots as $tag): ?>
                                        <option value="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"
                                        <?= $tag === $v ? 'selected' : '' ?>
                                        >
                                        <?= htmlspecialchars("map_index.css_{$tag}", ENT_QUOTES) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </select>
								</div>
						</div>
				</div>
			
			<form method="post" action="action/edit_theme.php">
				<textarea name="map_index_css" id="map_index_css" rows="60" cols="150"><?php
                    $path = WWW_DIR.'/assets/dist/css/'.$yaml_file;
                    $css  = @file_get_contents($path);
                    echo htmlspecialchars($css === false ? '' : $css, ENT_QUOTES);
              ?></textarea>

				<?php if(empty($_GET['v'])){ ?>
					<input type="submit" name="action" class="btn btn-primary" value="Submit">
				<?php } else { ?>
					<input type="submit" name="action" class="btn btn-primary" value="Restore">
					<input type="hidden" name="v" value="<?=$_GET['v']?>"/>
					<input type="submit" name="action" class="btn btn-danger" value="Delete">
				<?php } ?>
			</form>
			</div>
		</div>
</div>
<script>	
	var editor1 = CodeMirror.fromTextArea(document.getElementById("map_index_css"), {
		extraKeys: {"Ctrl-Space": "autocomplete"}
	});
</script>
</body>
</html>
