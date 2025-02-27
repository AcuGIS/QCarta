<?php
  session_start(['read_and_close' => true]);
	require('incl/const.php');
  require('class/database.php');
	require('class/table.php');
	require('class/table_ext.php');

	require('class/qgs.php');

	require('class/layer.php');
	require('class/qgs_layer.php');

	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
    header('Location: ../login.php');
    exit;
  }

	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$dbconn = $database->getConn();	

	$id = (empty($_GET['id'])) ? -1 : intval($_GET['id']);
	
	$obj = new qgs_layer_Class($dbconn, $_SESSION[SESS_USR_KEY]->id);
	if(($id <= 0) && !$obj->isOwnedByUs($id)){
		http_response_code(405);	//not allowed
		die(405);
	}
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
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/addon/hint/html-hint.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/mode/xml/xml.min.js"></script>
		
	<?php include("incl/meta.php"); ?>
	<link href="dist/css/side_menu.css" rel="stylesheet">
	<link href="dist/css/table.css" rel="stylesheet">

	<script type="text/javascript">
	$(document).ready(function() {
		$('[data-toggle="tooltip"]').tooltip();
	});
	</script>
	<style>
	.CodeMirror {
	  border: 1px solid #eee;
	  height: auto;
	}
	</style>
</head>

<body>
  
	<div id="container">
		
		<?php const NAV_SEL = 'Preview'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
		
		<div id="content">
		
				<h1>Edit Preview</h1>
			<div style="width: 99%">
			
				<div class="page-breadcrumb" style="padding-left:30px; padding-right: 30px; padding-top:0px; padding-bottom: 0px">
						<div class="row align-items-center">
								<div class="col-6">
									<nav aria-label="breadcrumb"></nav><p>&nbsp;</p>
								</div>
						</div>
				</div>
			
			<form method="post" action="action/edit_preview.php">
				<input type="hidden" name="action" value="save"/>
				<input type="hidden" name="id" id="id" value="<?=$id?>"/>
				<textarea name="preview_html" id="preview_html" rows="60" cols="150"><?php readfile('../layers/'.$_GET['id'].'/index.php'); ?></textarea>
				<input type="submit" name="submit" class="btn btn-primary" value="Submit">
			</form>
			</div>
		</div>
</div>
<script>	
	var editor1 = CodeMirror.fromTextArea(document.getElementById("preview_html"), {
		extraKeys: {"Ctrl-Space": "autocomplete"}
	});
</script>
</body>
</html>
