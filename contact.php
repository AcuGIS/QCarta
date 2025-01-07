<?php
  session_start();
	require('admin/incl/const.php');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>Quail Layer Server</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
<link href="admin/dist/css/side_menu.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">


<style>

</style>

</head>
<body>

		<div id="container">
		
			<?php const NAV_SEL = 'About'; const TOP_PATH=''; const ADMIN_PATH='admin/';
						include("admin/incl/navbar.php"); ?>
			<br class="clear">
			<?php include("admin/incl/sidebar.php"); ?>
				
				<div id="content">
						<h1>Contact</h1>
						<p>This installation belongs to:</p>

						<p>Administrator: YourName</p>

						<p>Email: you@yourdomain.com </p>
						<p>Web: <a href="#">https://YourDomain.com</a></p> 


						<p>You can edit this file or simply replace it with your own contact.php or contact.html.</p>
						<p><a href="https://quail.docs.acugis.com/en/latest/sections/branding/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>


				</div>
		</div>
		
<?php include("admin/incl/footer.php"); ?>
</body>
</html>
