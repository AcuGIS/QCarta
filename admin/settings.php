<?php
    session_start(['read_and_close' => true]);
	require('incl/const.php');
		
	if(!isset($_SESSION[SESS_USR_KEY]) || $_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') {
        header('Location: ../login.php');
        exit(0);
    }
		
    $settings = json_decode(file_get_contents(DATA_DIR.'/settings.json'), true);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<script src="assets/dist/js/settings.js"></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        body {
            min-height: 100vh;
            overflow-y: auto;
        }
        .editor-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .preview-map {
            width: 100%;
            min-width: 100%;
            height: 300px;
            min-height: 300px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-top: 20px;
            position: relative;
        }
    </style>
</head>

<body>
  
    <div id="container" style="display:block">
		
		<?php const NAV_SEL = 'Administration'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
			
		<div id="content">
			<div class="content-wrapper">
				<div class="page-header">
					<h1 class="page-title">Settings</h1>
				</div>
			</div>
			
			<div>
                <form id="settings_form" class="border shadow p-3 rounded"
    				action=""
    				method="post"
    				enctype="multipart/form-data"
    				style="width: 450px;">
            
                    <input type="hidden" name="action" value="save"/>
            
                    <div class="form-group">
						<p>Page Redirected to after Login</p>
						<div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="login_redirect" id="loging_redirect_index" value="index.php" <?php if($settings['login_redirect'] == 'index.php') { echo 'checked';}?>>
                            <label class="form-check-label" for="login_redirect_index">Index</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="login_redirect" id="login_redirect_viewer" value="viewer.php" <?php if($settings['login_redirect'] == 'viewer.php') { echo 'checked';}?>>
                            <label class="form-check-label" for="login_redirect_viewer">Viewer</label>
                        </div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-primary" id="btn_update" data-dismiss="modal">Update</button>
					</div>
                </form>
			</div>
        </div>
    </div>
    <?php include("incl/footer.php"); ?>
</body>
</html>
