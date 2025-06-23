<div id="header" style="background: #1E90FF; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 1rem 2rem; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; display: flex; justify-content: space-between; align-items: center;">
	<div style="display: flex; align-items: center;">
		<!-- <img src="<?=TOP_PATH?>assets/images/quail.png" style="height: 40px; margin-right: 1rem;" alt="QCarta QGIS Layer Server">-->
		<h1 style="margin: 0; font-size: 2.3rem; color: #fff!important; border-right:0px!important">QCarta</h1>
	</div>
	
	<ul id="nav" style="list-style: none; margin: 0; padding: 0; display: flex; gap: 1.5rem;">	
		<li><a href="<?=TOP_PATH?>index.php" style="text-decoration: none; color: #fff; font-weight: 500; transition: color 0.3s ease; <?php if(NAV_SEL == 'Home') { ?>color: #0d6efd;<?php } ?>" <?php if(NAV_SEL == 'Home') { ?>class="active"<?php } ?>>Home</a></li>
		<li><a href="<?=TOP_PATH?>about.php" style="text-decoration: none; color: #fff; font-weight: 500; transition: color 0.3s ease; <?php if(NAV_SEL == 'About') { ?>color: #0d6efd;<?php } ?>" <?php if(NAV_SEL == 'About') { ?>class="active"<?php } ?>>About</a></li>
		<?php if(isset($_SESSION[SESS_USR_KEY])) { ?>
			<li><a href="<?=TOP_PATH?>logout.php" style="text-decoration: none; color: #fff; font-weight: 500; transition: color 0.3s ease; <?php if(NAV_SEL == 'Login') { ?>color: #0d6efd;<?php } ?>" <?php if(NAV_SEL == 'Login') { ?>class="active"<?php } ?>>Logout</a></li>
		<?php }else { ?>
			<li><a href="<?=TOP_PATH?>login.php" style="text-decoration: none; color: #fff; font-weight: 500; transition: color 0.3s ease; <?php if(NAV_SEL == 'Login') { ?>color: #0d6efd;<?php } ?>" <?php if(NAV_SEL == 'Login') { ?>class="active"<?php } ?>>Login</a></li>
		<?php } ?>
	</ul>
</div>
<div style="height: 80px;"></div>
