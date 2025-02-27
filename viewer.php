<?php
  session_start();
	require('admin/incl/const.php');
	require('admin/class/database.php');
	require('admin/class/table.php');
	require('admin/class/user.php');
	require('admin/class/access_group.php');

  if(!isset($_SESSION[SESS_USR_KEY])) {
    header('Location: login.php');
    exit;
  }
	
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$acc_obj	= new access_group_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
	
	// super admin sees everything, other admins only owned
	$usr_grps = ($_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID) ? $acc_obj->getArr()
																															: $acc_obj->getByKV('user', $_SESSION[SESS_USR_KEY]->id);	
	$usr_grps_ids = implode(',', array_keys($usr_grps));	
	$layers = $acc_obj->getGroupRows('layer', $usr_grps_ids);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>QGIS App</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }

      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }

      .card {
          box-shadow: 0 0.15rem 0.55rem rgba(0, 0, 0, 0.1);
          transition: box-shadow 0.3s ease-in-out;
        }

        .card:hover {
          box-shadow: 0 0.35rem 0.85rem rgba(0, 0, 0, 0.3);
        }
        .col {
            padding-right: calc(var(--bs-gutter-x) * .75);
            padding-left: calc(var(--bs-gutter-x) * .75);
        }
    </style>

  </head>
  <body>

<header>

  <div class="navbar navbar-dark bg-dark shadow-sm" style="background-color:#50667f!important">
    <div class="container">
      <a href="#" class="navbar-brand d-flex align-items-center">
        <strong> &nbsp;Quail</strong>
      </a>

<?php
if($_SESSION[SESS_USR_KEY]->accesslevel == 'Admin'){
  echo '<a href="admin/index.php" style="text-decoration:none; color: #fff!important; font-size: 1.25rem; font-weight: 300;">Administration</a>';
}
?>


      <a href="logout.php" style="text-decoration:none; color: #fff!important; font-size: 1.25rem; font-weight: 300;">Log Out</a>


    </div>
  </div>
</header>


<main style="background-color:#edf0f2">

  <div class="album py-5 bg-light">
		<div class="container">
      
			<!-- Layers -->
			<div class="row row-cols-1 row-cols-md-4 g-4">
			<?php
				$preview = ($_SESSION[SESS_USR_KEY]->preview_type == 'openlayers') ? 'ol_' : '';
				while($row = pg_fetch_object($layers)) {
					
					$image = file_exists("assets/layers/".$row->id.".png") ? "assets/layers/".$row->id.".png" : "assets/layers/default.png"; ?>
					<div class="col">
						<div class="card">
							<div class="card-body">
								<h5 class="card-title" style="font-size: 15px; font-weight: 800;">
								<a class="card-link" href="layers/<?=$row->id?>/<?=$preview?>index.php" target="_blank" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;"><?=$row->name?></a>
									<?php if(is_file('qwc2/'.$row->id.'/.on_portal')) { ?>
									<a class="card-link" href="qwc2/<?=$row->id?>/index.php" target="_blank"><img src="assets/images/qwc-logo.svg" alt="QWC Preview" width="32" height="32"></a>
									<?php } ?>
								</h5>
								<a class="card-link" href="layers/<?=$row->id?>/<?=$preview?>index.php" target="_blank" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;">
									<img class="card-img-bottom" src="<?=$image?>" style="height: 150px; width: 100%" alt="thumbnail">
								</a>
							</div>
						</div>
					</div>
				<?php }
					pg_free_result($layers);
				?>
			</div>
			
			
		</div>
  </div>

</main>

	<footer class="text-muted py-5">
	  <div class="container">
	    <p class="float-end mb-1">
	<a href="#" style="text-decoration:none; color: #6c757d!important; font-size: 1.25rem; font-weight: 300;">Back to top</a>    </p>
	  </div>
	</footer>
  
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

  </body>
</html>
