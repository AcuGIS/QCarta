<?php
session_start();
require('admin/incl/const.php');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
<link href="admin/dist/css/side_menu.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">


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
	font-weight: 600;
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
	color: var(--primary-color);
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
						<p><a href="https://QCarta.docs.acugis.com/en/latest/sections/branding/index.html" target="_blank">Learn More&nbsp;<i class="fa fa-external-link"></i> </a></p>


				</div>
		</div>
		
<?php include("admin/incl/footer.php"); ?>
</body>
</html>
