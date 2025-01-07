<?php
  session_start();
	require('incl/const.php');

  if(empty($_SESSION[SESS_USR_KEY]) || ($_SESSION[SESS_USR_KEY]->accesslevel != 'Admin') ){
    header('Location: ../login.php');
    exit;
  }
?>





<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>Quail Layer Server</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	

	
	<?php include("incl/meta.php"); ?>
	<link href="dist/css/side_menu.css" rel="stylesheet">
	<link href="dist/css/table.css" rel="stylesheet">
<style type="text/css">
a {
	text-decoration:none!important;
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
      <a href="https://quail.docs.acugis.com/en/latest/sections/users/index.html" class="card-link" target="_blank">Documentation</a>
    </div>
  </div>


<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Layers</h4>
      <p class="card-text">QGIS and PostGIS Layers</p>
  <a href="layers.php" class="card-link">Manage</a>
      <a href="https://quail.docs.acugis.com/en/latest/sections/layers/index.html" class="card-link" target="_blank">Documentation</a>
    </div>
  </div>

<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Quick Start</h4>
      
      <a href="https://quail.docs.acugis.com/en/latest/quickstart.html" class="card-link" target="_blank">Quick Start Guide</a>
      
    </div>
  </div>




    </div>
    <div class="col">

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Stores</h4>
      <p class="card-text">QGIS and PostGIS Stores</p>
<a href="stores.php" class="card-link">Manage</a>
      <a href="https://quail.docs.acugis.com/en/latest/sections/stores/index.html" class="card-link" target="_blank">Documentation</a>

    </div>
  </div>

<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">MapProxy</h4>
      <p class="card-text">MapProxy, Cache, and Seeding</p>
<a href="services.php" class="card-link">Manage</a>
      <a href="https://quail.docs.acugis.com/en/latest/sections/mapproxy/index.html" class="card-link" target="_blank">Documentation</a>

    </div>
  </div>


<br>

<div class="card">
    <div class="card-body">
      <h4 class="card-title">Documentation</h4>
      <a href="https://quail.docs.acugis.com" class="card-link" target="_blank">Documentation</a>
      
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
