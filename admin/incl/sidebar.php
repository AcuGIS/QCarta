<link rel="stylesheet" href="<?=ADMIN_PATH?>dist/css/sidebar.css">
<div id="sidebar" style="background-color:#eee; padding: 0px 0px 60% 0px; 150vh; z-index:99">

<div class="sphinxsidebar" role="navigation" aria-label="main navigation">
        <div class="sphinxsidebarwrapper">

<div>
    <h3>Home</h3>
<ul>
<li><a class="reference internal" href="<?=TOP_PATH?>index.php">Welcome</a></li>
<li><a class="reference internal" href="<?=TOP_PATH?>about.php">About</a></li>
<li><a class="reference internal" href="<?=TOP_PATH?>public.php">Public Layers</a></li>
</ul>
  </div>

  <div>
    <h3>Secure</h3>
<ul>
<li><a class="reference internal" href="<?=ADMIN_PATH?>access.php">Users</a></li>
<li><a class="reference internal" href="<?=ADMIN_PATH?>stores.php">Stores</a></li>
<li><a class="reference internal" href="<?=ADMIN_PATH?>layers.php">Layers</a></li>
<li><a class="reference internal" href="<?=ADMIN_PATH?>services.php">MapProxy</a></li>
<li><a class="reference internal" href="<?=TOP_PATH?>viewer.php">Layer Portal</a></li>
</ul>
  </div>  
  <div>

<?php

if(isset($_SESSION[SESS_USR_KEY]) && ($_SESSION[SESS_USR_KEY]->accesslevel == 'Admin')){

	$plugins_ini = ADMIN_PATH.'plugins.ini';

	if (is_file($plugins_ini)) { 
	  $plugins = parse_ini_file($plugins_ini, true);
	  
	  if (count($plugins)) { ?>
      <h3>Plugins</h3>
      <ul>
      <?php foreach ($plugins as $k => $plug) {
        if (is_file($plug['file'])) { ?>
        	<li><a href="<?=ADMIN_PATH.$plug['file']?>"><?=$plug['title']?></a></li>
        <?php }
      	} ?>
	    </ul>
<?php }
	}
}
?>

<ul>
  
	
</ul>
  </div>

<div>
    <h3>Access</h3>
<ul>
<li>


<?php
	if(isset($_SESSION[SESS_USR_KEY])){
		if($_SESSION[SESS_USR_KEY]->accesslevel == 'Admin'){
			?><a href="<?=ADMIN_PATH?>index.php" style="text-decoration:none; color: #333!important; font-size: 14px;">Administration</a><?php
		} ?>
		<li class="quail-link">	
			<a href="<?=TOP_PATH?>logout.php" style="text-decoration:none; color: #333!important; font-size: 14px;">Log Out</a>
		</li>
	<?php
	}else{
		?><a href="<?=TOP_PATH?>login.php" style="text-decoration:none; color: #333!important; font-size: 14px;">Login</a><?php
	}
	?>
</li>
</ul>
  </div>


  <div>
    <h3>Documentation</h3>
<ul>
<li><a href="https://quail.docs.acugis.com/en/latest/quickstart.html" target="_blank">Quick Start</a></li>
<li><a href="https://quail.docs.acugis.com/" target="_blank">Full Docs</a></li>

<li>
	<a class="reference internal" href="#"></a>
	<a href='https://quail.docs.acugis.com/en/latest/?badge=latest'>
  	<img src='https://readthedocs.org/projects/quailserver/badge/?version=latest' alt='Documentation Status' />
	</a>
</li>
</ul>
  </div>
</div>
</div>
</div>
