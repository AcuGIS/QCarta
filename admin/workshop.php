<?php
session_start();
require('incl/const.php');
require('incl/app.php');
require('class/database.php');
require('class/table.php');
require('class/table_ext.php');	
require('class/qgs.php');

if(!isset($_SESSION[SESS_USR_KEY])) {
    header('Location: ../login.php');
    exit(1);
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">

	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">

	
	
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
		
		.content-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
    margin-bottom: 1rem;
    height: 100%;
}		
		.page-header {
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			margin-bottom: 1.5rem;
			padding-bottom: 1rem;
			border-bottom: 1px solid var(--border-color);
		}
		
		.page-header .text-end {
			margin-top: 0.5rem;
			margin-left: 0;
			align-self: flex-start;
		}
		
		.page-title, h1 {
			color: var(--text-color);
			font-size: 1.75rem;
			font-weight: 400;
			margin: 0 0 1.5rem 0;
		}
		
		.nav-tabs {
			border-bottom: 2px solid var(--border-color);
			margin-bottom: 1.5rem;
		}
		
		.nav-tabs .nav-link {
			color: var(--secondary-color);
			border: none;
			padding: 0.75rem 1.25rem;
			transition: all 0.3s ease;
			font-weight: 500;
		}
		
		.nav-tabs .nav-link:hover {
			color: var(--primary-color);
			background: transparent;
		}
		
		.nav-tabs .nav-link.active {
			color: var(--primary-color);
			border-bottom: 2px solid var(--primary-color);
			background: transparent;
		}
		
		.btn {
			padding: 0.5rem 1rem;
			font-weight: 500;
			border-radius: 6px;
			transition: all 0.3s ease;
		}
		
		.btn-primary {
			background-color: var(--primary-color);
			border-color: var(--primary-color);
		}
		
		.btn-warning {
			background-color: #ffc107;
			border-color: #ffc107;
			color: #000;
		}
		
		.btn:hover {
			transform: translateY(-1px);
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}

		/* Form styles */
		.form-group {
			margin-bottom: 1rem;
		}

		.form-control {
			border-radius: 6px;
			border: 1px solid var(--border-color);
			padding: 0.5rem 0.75rem;
		}

		.form-control:focus {
			border-color: var(--primary-color);
			box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
		}

		/* Table styles */
		.table {
			width: 100%;
			margin-bottom: 1rem;
			background-color: white;
			border-radius: 6px;
			overflow: hidden;
		}

		.table th {
			background-color: #f8f9fa;
			border-bottom: 2px solid var(--border-color);
			padding: 0.75rem;
		}

		.table td {
			padding: 0.75rem;
			border-bottom: 1px solid var(--border-color);
		}

		.table tr:last-child td {
			border-bottom: none;
		}

		/* Modal styles */
		.modal-content {
			border: none;
			border-radius: 12px;
			box-shadow: 0 10px 30px rgba(0,0,0,0.2);
			background: #2d2d2d;
			color: #f8f9fa;
		}

		.modal-header {
			background-color: #2d2d2d;
			border-bottom: 1px solid #343a40;
			padding: 1.25rem 2rem 1.25rem 2rem;
			border-radius: 12px 12px 0 0;
		}

		.modal-title {
			color: #fff !important;
			font-size: 1.25rem;
			font-weight: 600;
		}

		.modal-body {
			padding: 1.25rem 2rem 1.25rem 2rem;
			background-color: #2d2d2d;
			max-height: none;
			overflow: visible;
		}

		.modal-body form {
			width: 100% !important;
			margin: 0 auto !important;
			padding: 0;
		}

		.modal .form-control {
			width: 100% !important;
			background: #343a40;
			color: #fff;
			border: 1px solid #495057;
			border-radius: 6px;
		}

		.modal-footer {
			display: flex;
			justify-content: flex-end;
			align-items: center;
			background-color: #2d2d2d;
			border-top: 1px solid #343a40;
			padding: 1.25rem 2rem 1.25rem 2rem;
			border-radius: 0 0 12px 12px;
		}

		.modal-backdrop.show {
			opacity: 0.7;
		}

		.modal-dialog {
			margin: 2.5rem auto;
			max-width: 600px;
			width: 95vw;
		}

		.modal .form-group label,
		.modal .form-label {
			color: #fff !important;
		}

		.modal .form-control:focus {
			border-color: #0d6efd;
			box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
			background: #23272b;
			color: #f8f9fa;
		}

		.modal .btn {
			padding: 0.5rem 1.5rem;
			border-radius: 6px;
			font-weight: 500;
		}

		.modal .btn-primary {
			background-color: #0d6efd;
			border-color: #0d6efd;
			color: #fff;
		}

		.modal .btn-secondary {
			background-color: #495057;
			border-color: #495057;
			color: #fff;
		}

		.modal .btn-secondary:hover {
			background-color: #343a40;
			border-color: #23272b;
		}

		.modal .close {
			font-size: 1.5rem;
			font-weight: 400;
			opacity: 0.7;
			transition: opacity 0.3s ease;
			color: #f8f9fa;
		}

		.modal .close:hover {
			opacity: 1;
		}

		.modal .form-control:disabled, .modal .form-control[readonly] {
			background: #444 !important;
			color: #fff !important;
			opacity: 1 !important;
		}

		.modal .form-group label[for][disabled],
		.modal .form-label[disabled] {
			color: #fff !important;
		}

		.btn-primary:hover, .btn-primary:focus {
			background-color: #0b5ed7;
			border-color: #0a58ca;
			color: #fff;
		}

		.btn-warning:hover, .btn-warning:focus {
			background-color: #e0a800 !important;
			border-color: #d39e00 !important;
			color: #212529 !important;
		}

		.btn-secondary:hover, .btn-secondary:focus {
			background-color: #495057;
			border-color: #495057;
			color: #fff;
		}

		.btn-warning.text-white:hover, .btn-warning.text-white:focus {
			background-color: #e0a800 !important;
			border-color: #d39e00 !important;
			color: #212529 !important;
		}

		/* Workshop Cards Styles */
		.workshop-container {
			display: flex;
			flex-direction: column;
			gap: 1rem;
			margin-top: 2rem;
		}

		.workshop-card {
			display: flex;
			align-items: center;
			padding: 1.5rem;
			background: white;
			border: 1px solid var(--border-color);
			border-radius: 12px;
			transition: all 0.3s ease;
			text-decoration: none;
			color: inherit;
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
			width: 80%;
		}

		.workshop-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
			border-color: var(--primary-color);
			text-decoration: none;
			color: inherit;
		}

		.workshop-card img {
			width: 60px;
			height: 60px;
			margin-right: 1.5rem;
			flex-shrink: 0;
		}

		.workshop-card-title {
			font-size: 1.5rem;
			font-weight: 600;
			color: #2c3e50 !important;
			margin: 0;
			transition: color 0.3s ease;
		}

		.workshop-card:hover .workshop-card-title {
			color: #1E90FF !important;
		}

		.workshop-card-description {
			font-size: 0.95rem;
			color: var(--secondary-color);
			margin-top: 0.25rem;
		}
	</style>
</head>
 
<body>

		<div id="container">
				<?php const NAV_SEL = 'Users'; const TOP_PATH='../'; const ADMIN_PATH='';
							include("incl/navbar.php"); ?>
				<br class="clear">
				<?php include("incl/sidebar.php"); ?>

				<div id="content">

					<h1>SQL Workshop</h1>
					
					<div class="workshop-container">
							<a href="sql_workshop_gpkg.php" class="workshop-card">
								<img src="assets/images/workshop.png" alt="GeoPackage Workshop">
								<div>
									<h3 class="workshop-card-title">GeoPackage</h3>
									<p class="workshop-card-description">SQL Workshop for GeoPackages</p>
								</div>
							</a>
							
							<a href="sql_workshop_gdb.php" class="workshop-card">
								<img src="assets/images/workshop.png" alt="Geodatabase Workshop">
								<div>
									<h3 class="workshop-card-title">Geodatabase</h3>
									<p class="workshop-card-description">SQL Workshop for Esri Geodatabases</p>
								</div>
							</a>
							
							<a href="sql_workshop_pg.php" class="workshop-card">
								<img src="assets/images/workshop.png" alt="PostGIS Workshop">
								<div>
									<h3 class="workshop-card-title">PostGIS</h3>
									<p class="workshop-card-description">SQL Workshop for PostGIS</p>
								</div>
							</a>
							
							<a href="sql_workshop_shp.php" class="workshop-card">
								<img src="assets/images/workshop.png" alt="Shapefile Workshop">
								<div>
									<h3 class="workshop-card-title">Shapefile</h3>
									<p class="workshop-card-description">SQL Workshop for Shapefiles</p>
								</div>
							</a>
					</div>
				</div>
		</div>
<?php include("incl/footer.php"); ?>
</body>

</html>
