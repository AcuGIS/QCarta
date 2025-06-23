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

.page-title, h1 {
	color: var(--text-color);
	font-size: 1.75rem;
	font-weight: 400;
	margin: 0 0 1.5rem 0;
}

.card {
	border-radius: 12px;
	box-shadow: 0 4px 6px rgba(0,0,0,0.05);
	border: 1px solid var(--border-color);
	margin-bottom: 1.5rem;
	background: #fff;
}

.card-title {
	font-size: 1.2rem;
	font-weight: 600;
	color: #666!important;
}

.card-link {
	color: var(--primary-color);
	font-weight: 500;
	margin-right: 1rem;
	transition: color 0.2s;
}

.card-link:hover {
	color: #0b5ed7;
	text-decoration: underline;
}

.card-text {
	color: var(--secondary-color);
}
</style>




</head>
 
<body>

		<div id="container">
		
			<?php const NAV_SEL = 'Admin'; const TOP_PATH='../'; const ADMIN_PATH='';
						include("incl/navbar.php"); ?>
			<br class="clear">
			<?php include("incl/sidebar.php"); ?>
				
				<div id="content">







					<h1>Administration</strong></h1>



  <div class="row align-items-start">
    <div class="col">
      
<div class="card">
    <div class="card-body">
      <h4 class="card-title">Users</h4>
      <p class="card-text">Users, Groups, and Keys</p>
      <a href="access.php" class="card-link">Manage</a>
      <a href="https://QCarta.docs.acugis.com/en/latest/sections/users/index.html" class="card-link" target="_blank">Documentation</a>
    </div>
  </div>


<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Layers</h4>
      <p class="card-text">QGIS and PostGIS Layers</p>
  <a href="layers.php" class="card-link">Manage</a>
      <a href="https://QCarta.docs.acugis.com/en/latest/sections/layers/index.html" class="card-link" target="_blank">Documentation</a>
    </div>
  </div>

<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Quick Start</h4>
      
      <a href="https://QCarta.docs.acugis.com/en/latest/quickstart.html" class="card-link" target="_blank">Quick Start Guide</a>
      
    </div>
  </div>




    </div>
    <div class="col">

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Stores</h4>
      <p class="card-text">QGIS and PostGIS Stores</p>
<a href="stores.php" class="card-link">Manage</a>
      <a href="https://QCarta.docs.acugis.com/en/latest/sections/stores/index.html" class="card-link" target="_blank">Documentation</a>

    </div>
  </div>

<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">MapProxy</h4>
      <p class="card-text">MapProxy, Cache, and Seeding</p>
<a href="services.php" class="card-link">Manage</a>
      <a href="https://QCarta.docs.acugis.com/en/latest/sections/mapproxy/index.html" class="card-link" target="_blank">Documentation</a>

    </div>
  </div>


<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Documentation</h4>
      <a href="https://QCarta.docs.acugis.com" class="card-link" target="_blank">Documentation</a>
      
    </div>
  </div>
    
  <div class="card">
      <div class="card-body">
        <h4 class="card-title">Settings</h4>
        <a href="settings.php" class="card-link">QCarta settings</a>
        
      </div>
    </div>

    </div>
    <div class="col">




    </div>
  </div>				
						
				</div>
		</div>
<?php include("incl/footer.php"); ?>
</body>
</html>
