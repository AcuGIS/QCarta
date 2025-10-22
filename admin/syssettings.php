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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">	
	
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<script src="assets/dist/js/settings.js"></script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --border-color: #e9ecef;
            --text-color: #2c3e50;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            background-color: var(--background-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        #container {
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }

        #content {
            position: relative;
            padding: 0.5rem 1rem 1rem 1rem;
            margin-left: 210px;
            width: calc(100% - 210px);
            height: auto;
            overflow: visible;
        }

        .page-title, h1 {
            color: var(--text-color);
            font-size: 1.75rem;
            font-weight: 400;
            margin: 0 0 1.5rem 0;
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

			<h1>Settings</h1>

			<div>
                <div class="row mt-4">
                    <div class="col-12">
                        <h4 class="mb-3">Quick Navigation</h4>
                        <div class="row g-3">
                            <div class="col-md-2 col-sm-4">
                                <a href="<?=ADMIN_PATH?>settings.php" class="btn btn-outline-primary d-flex flex-column align-items-center p-2 h-100 text-decoration-none">
                                    <i class="bi bi-gear-fill fs-2 mb-2"></i>
                                    <span class="fw-bold small">Layout</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="<?=ADMIN_PATH?>basemaps.php" class="btn btn-outline-success d-flex flex-column align-items-center p-2 h-100 text-decoration-none">
                                    <i class="bi bi-map fs-2 mb-2"></i>
                                    <span class="fw-bold small">Basemaps</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="<?=ADMIN_PATH?>topics.php" class="btn btn-outline-info d-flex flex-column align-items-center p-2 h-100 text-decoration-none">
                                    <i class="bi bi-collection fs-2 mb-2"></i>
                                    <span class="fw-bold small">Topics</span>
                                </a>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="<?=ADMIN_PATH?>gemets.php" class="btn btn-outline-warning d-flex flex-column align-items-center p-2 h-100 text-decoration-none">
                                    <i class="bi bi-tags-fill fs-2 mb-2"></i>
                                    <span class="fw-bold small">Keywords</span>
                                </a>
                            </div>
<div class="col-md-2 col-sm-4">
                                <a href="<?=ADMIN_PATH?>edit_theme.php" class="btn btn-outline-warning d-flex flex-column align-items-center p-2 h-100 text-decoration-none">
                                    <i class="bi bi-palette-fill fs-2 mb-2"></i>
                                    <span class="fw-bold small">Theme</span>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
			</div>
        </div>
    </div>
    <?php include("incl/footer.php"); ?>
</body>
</html>
