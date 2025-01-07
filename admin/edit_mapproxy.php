<?php
  session_start(['read_and_close' => true]);
	require('incl/const.php');
	require('incl/app.php');

	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
    header('Location: ../login.php');
    exit;
  }
	
	$yaml_file = 'mapproxy.yaml';
	$v = empty($_GET['v']) ? '' : $_GET['v'];
	if(is_file(DATA_DIR.'/mapproxy/'.$yaml_file.'_'.$v) ){
		$yaml_file .= '_'.$v;
	}
	
	$yaml_snapshots = find_yaml_snapshots(DATA_DIR.'/mapproxy', 'mapproxy.yaml_');
?>

<!DOCTYPE html>
<html dir="ltr" lang="en" >

<head>
	<title>Quail Layer Server</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/codemirror.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/show-hint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/lint/yaml-lint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/yaml/yaml.min.js"></script>
		
	<?php include("incl/meta.php"); ?>
	<link href="dist/css/side_menu.css" rel="stylesheet">
	<link href="dist/css/table.css" rel="stylesheet">
	<style>
	.CodeMirror {
	  border: 1px solid #eee;
	  height: auto;
	}
	</style>
	
	<script>
$(document).ready(function() {
	
	$('#yaml_snapshot').on("change", function() {
		let v = $(this).val();
		let url = 'edit_mapproxy.php';
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
		
				<h1>MapProxy Editor</h1>
			<div style="width: 99%">
				
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
						<div class="row align-items-center">
								<div class="col-6">
									<nav aria-label="breadcrumb"></nav><p>&nbsp;</p>
									<label for="yaml_snapshot" class="form-label">Version:</label>
									<select id="yaml_snapshot" name="yaml_snapshot">
										<option value="" <?php if($v == '') {?>selected<?php } ?>>Latest</option>
										<?php foreach($yaml_snapshots as $vv){ ?>
											<option value="<?=$vv?>" <?php if($vv == $v) {?>selected<?php } ?>><?=$vv?></option>
										<?php }?>
									</select>
								</div>
						</div>
				</div>
			
			<form method="post" action="action/edit_mapproxy.php">
				<textarea name="mapproxy_yaml" id="mapproxy_yaml" rows="60" cols="150"><?php readfile(DATA_DIR.'/mapproxy/'.$yaml_file); ?></textarea>
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
	var editor1 = CodeMirror.fromTextArea(document.getElementById("mapproxy_yaml"), {
		extraKeys: {"Ctrl-Space": "autocomplete"}
	});
</script>
</body>
</html>
