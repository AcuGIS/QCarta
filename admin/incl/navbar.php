<div id="header">
	<h1>&nbsp;<img src="<?=TOP_PATH?>assets/images/quail.png" style="width:25%" alt="Quail QGIS Layer Server"><br></h1>
</div>

<ul id="nav">	
	<li><a href="<?=TOP_PATH?>index.php" 	<?php if(NAV_SEL == 'Home') { ?>class="active"<?php } ?>>Home</a></li>
	<li><a href="<?=TOP_PATH?>about.php" 	<?php if(NAV_SEL == 'About') { ?>class="active"<?php } ?>>About</a></li>
	<li><a href="<?=TOP_PATH?>public.php" <?php if(NAV_SEL == 'Layers') { ?>class="active"<?php } ?>>Public Layers</a></li>
	<?php if(isset($_SESSION[SESS_USR_KEY])) { ?>
		<li><a href="<?=TOP_PATH?>logout.php" 	<?php if(NAV_SEL == 'Login') { ?>class="active"<?php } ?>>Logout</a></li>
	<?php }else { ?>
		<li><a href="<?=TOP_PATH?>login.php" 		<?php if(NAV_SEL == 'Login') { ?>class="active"<?php } ?>>Login</a></li>
	<?php } ?>
</ul>
