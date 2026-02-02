<?php
  session_start();
  require('incl/const.php');

  if(empty($_SESSION[SESS_USR_KEY]) || ($_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') ){
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
	--card-shadow: 0 2px 8px rgba(0,0,0,0.08);
	--card-shadow-hover: 0 8px 25px rgba(0,0,0,0.15);
	--transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

.admin-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
	gap: 2rem;
	max-width: calc(3 * 350px + 2 * 2rem);
}

.card {
	border-radius: 16px;
	box-shadow: var(--card-shadow);
	border: none;
	background: #fff;
	transition: var(--transition-smooth);
	overflow: hidden;
	position: relative;
}

.card::before {
	content: '';
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	height: 4px;
	background: var(--primary-color);
	opacity: 0;
	transition: var(--transition-smooth);
}

.card:hover {
	transform: translateY(-8px);
	box-shadow: var(--card-shadow-hover);
}

.card:hover::before {
	opacity: 1;
}

.card-body {
	padding: 2rem;
	position: relative;
}

.card-title {
	font-size: 1.4rem;
	font-weight: 600;
	color: #2c3e50 !important;
	margin-bottom: 0.75rem;
	display: flex;
	align-items: center;
}

.card-title i {
	font-size: 1.6rem;
	margin-right: 0.75rem;
	color: var(--primary-color);
}

.card-text {
	color: var(--secondary-color);
	font-size: 0.95rem;
	line-height: 1.5;
	margin-bottom: 1.5rem;
}

.card-links {
	display: flex;
	gap: 1rem;
	flex-wrap: nowrap;
}

.card-link {
	color: var(--primary-color);
	font-weight: 500;
	text-decoration: none;
	padding: 0.5rem 1rem;
	border-radius: 8px;
	background: rgba(13, 110, 253, 0.1);
	border: 1px solid rgba(13, 110, 253, 0.2);
	transition: var(--transition-smooth);
	font-size: 0.9rem;
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
}

.card-link:hover {
	color: #fff;
	background: var(--primary-color);
	border-color: var(--primary-color);
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
}

.card-link i {
	font-size: 0.8rem;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
	.admin-grid {
		max-width: calc(2 * 350px + 1 * 2rem);
	}
}

@media (max-width: 768px) {
	#content {
		margin-left: 0;
		width: 100%;
		padding: 1rem;
	}
	
	.admin-grid {
		grid-template-columns: 1fr;
		gap: 1.5rem;
		max-width: none;
	}
	
	.card-body {
		padding: 1.5rem;
	}
	
	h1 {
		font-size: 1.5rem;
	}
}

@media (max-width: 480px) {
	.card-links {
		flex-direction: column;
		gap: 0.75rem;
	}
	
	.card-link {
		justify-content: center;
	}
}
</style>




</head>
 
<body>

<?php const NAV_SEL = 'Admin'; const TOP_PATH='../'; const ADMIN_PATH='';
						include("incl/navbar.php"); ?>

		<div id="container">
		
			
			<br class="clear">
			<?php include("incl/sidebar.php"); ?>
				
				<div id="content">







					<h1>Administration</h1>

					<div class="admin-grid">
						<div class="card">
							<div class="card-body">
								<h4 class="card-title">
									<i class="bi bi-people-fill"></i>
									Users
								</h4>
								<p class="card-text">Manage users, groups, and access keys for your QCarta instance.</p>
								<div class="card-links">
									<a href="access.php" class="card-link">
										<i class="bi bi-gear"></i>
										Manage
									</a>
									<a href="https://QCarta.docs.acugis.com/en/latest/sections/users/index.html" class="card-link" target="_blank">
										<i class="bi bi-book"></i>
										Documentation
									</a>
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-body">
								<h4 class="card-title">
									<i class="bi bi-layers-fill"></i>
									Layers
								</h4>
								<p class="card-text">Manage QGIS and PostGIS layers for your applications.</p>
								<div class="card-links">
									<a href="layers.php" class="card-link">
										<i class="bi bi-gear"></i>
										Manage
									</a>
									<a href="https://QCarta.docs.acugis.com/en/latest/sections/layers/index.html" class="card-link" target="_blank">
										<i class="bi bi-book"></i>
										Documentation
									</a>
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-body">
								<h4 class="card-title">
									<i class="bi bi-database-fill"></i>
									Stores
								</h4>
								<p class="card-text">Set up and manage QGIS and PostGIS data stores for your projects.</p>
								<div class="card-links">
									<a href="stores.php" class="card-link">
										<i class="bi bi-gear"></i>
										Manage
									</a>
									<a href="https://QCarta.docs.acugis.com/en/latest/sections/stores/index.html" class="card-link" target="_blank">
										<i class="bi bi-book"></i>
										Documentation
									</a>
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-body">
								<h4 class="card-title">
									<i class="bi bi-geo-alt-fill"></i>
									QCarta Cache
								</h4>
								<p class="card-text">Control QCarta Cache service</p>
								<div class="card-links">
									<a href="services.php" class="card-link">
										<i class="bi bi-gear"></i>
										Manage
									</a>
									<a href="https://QCarta.docs.acugis.com/en/latest/sections/qcarta-cache/index.html" class="card-link" target="_blank">
										<i class="bi bi-book"></i>
										Documentation
									</a>
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-body">
								<h4 class="card-title">
									<i class="bi bi-gear-fill"></i>
									Settings
								</h4>
								<p class="card-text">Configure global settings and preferences for your QCarta instance.</p>
								<div class="card-links">
									<a href="syssettings.php" class="card-link">
										<i class="bi bi-sliders"></i>
										QCarta Settings
									</a>
								</div>
							</div>
						</div>

						<div class="card">
							<div class="card-body">
								<h4 class="card-title">
									<i class="bi bi-lightning-fill"></i>
									Quick Start
								</h4>
								<p class="card-text">Get up and running quickly with our comprehensive quick start guide.</p>
								<div class="card-links">
									<a href="https://QCarta.docs.acugis.com/en/latest/quickstart.html" class="card-link" target="_blank">
										<i class="bi bi-rocket-takeoff"></i>
										Quick Start Guide
									</a>
								</div>
							</div>
						</div>
					</div>				
						
				</div>
		</div>
<?php include("incl/footer.php"); ?>
</body>
</html>
