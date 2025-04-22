<?php
		require('admin/incl/const.php');

		session_start(['read_and_close' => true]);
		if(isset($_SESSION[SESS_USR_KEY])) {
				header("Location: viewer.php");
		}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>Quail Layer Server</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
<link href="admin/dist/css/side_menu.css" rel="stylesheet">
</head>
 
<body>

		<div id="container">
			<?php const NAV_SEL = 'Login'; const TOP_PATH=''; const ADMIN_PATH='admin/';
						include("admin/incl/navbar.php"); ?>
			<br class="clear">
			<?php include("admin/incl/sidebar.php"); ?>

				<div id="content">
						<h1>Login</h1>
						<div style="width: 50%">
						
							<form method="post" action="admin/action/login.php">
										<?php if(!empty($_GET['err'])){ ?>
											<div class="alert alert-danger" role="alert" style="width: 80%"><?=$_GET['err']?></div>
										<?php } else if(!empty($_GET['msg'])){ ?>
											<div class="alert alert-success" role="alert" style="width: 80%"><?=$_GET['msg']?></div>
										<?php } ?>
                    <div class="row gy-3 overflow-hidden">
                      <div class="col-12">
                        <div class="form-floating mb-3">
                          <input type="email" class="form-control" name="email" id="email" placeholder="name@example.com" required>
                          <label for="email" class="form-label">Email</label>
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="form-floating mb-3">
                          <input type="password" class="form-control" name="pwd" id="pwd" value="" placeholder="Password" required>
                          <label for="password" class="form-label">Password</label>
                        </div>
                      </div>
                      <div class="col-12">
                        
                      </div>
                      <div class="col-12">
                        <div class="d-grid">
                          <button class="btn btn-dark btn-lg" type="submit" value="Login" name="submit">Log in</button>
                        </div>
                      </div>
                    </div>
                  </form>
						</div>
			</div>
		</div>
<?php include("admin/incl/footer.php"); ?>
</body>
</html>
