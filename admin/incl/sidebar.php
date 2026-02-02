<link rel="stylesheet" href="<?=ADMIN_PATH?>assets/dist/css/sidebar.css">
<div id="sidebar" style="background: white; box-shadow: 2px 0 4px rgba(0,0,0,0.05); background-color:#f8f9fa; padding: 1rem; position: fixed; top: 80px; left: 0; bottom: 0; width: 250px; z-index: 900; margin-left: 0;">
<div class="sphinxsidebar" role="navigation" aria-label="main navigation">
  <div class="sphinxsidebarwrapper" style="padding: 0 1rem; height: calc(100vh - 100px); overflow-y: auto;">

<!-- Home Links -->
<?php if(isset($_SESSION[SESS_USR_KEY]) && $_SESSION[SESS_USR_KEY]->accesslevel == 'Admin'){ ?>
<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#636e72;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a href="<?=ADMIN_PATH?>index.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Administration</a>
  </div>
</div>
<?php } ?>

<!-- Home Links (About stays at top, Landing Page and Public Layers move below) -->



<!-- Secure/Admin Links -->

<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;position:relative;">
  <div style="width:8px;height:48px;background:#636e72;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>syssettings.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Settings</a>
  </div>
  <span style="cursor:pointer;padding:4px;" onclick="var d=this.parentNode.querySelector('.dropdown-settings');d.style.display=d.style.display==='block'?'none':'block';event.stopPropagation();">
    <svg height="20" width="20" viewBox="0 0 20 20" style="vertical-align:middle;"><circle cx="10" cy="4" r="1.5" fill="#888"/><circle cx="10" cy="10" r="1.5" fill="#888"/><circle cx="10" cy="16" r="1.5" fill="#888"/></svg>
  </span>
  <div class="dropdown-settings" style="display:none;position:absolute;right:16px;top:44px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);min-width:140px;z-index:1000;">
    <a href="<?=ADMIN_PATH?>settings.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Landing Page</a>
    <a href="<?=ADMIN_PATH?>basemaps.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Basemaps</a>
    <a href="<?=ADMIN_PATH?>topics.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Topics</a>
    <!-- <a href="<?=TOP_PATH?>topic_viewer.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Topic View</a> -->
    <a href="<?=ADMIN_PATH?>gemets.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Keywords</a>
    <!-- <a href="<?=TOP_PATH?>gemet_viewer.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">GEMET View</a> -->
    <a href="<?=ADMIN_PATH?>edit_theme.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Theme</a>
  </div>
</div>
<script>
document.addEventListener('click',function(){
  var d=document.querySelectorAll('.dropdown-settings');
  for(var i=0;i<d.length;i++){d[i].style.display='none';}
});
</script>



<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;position:relative;">
  <div style="width:8px;height:48px;background:#636e72;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>access.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Users</a>
  </div>
  <span style="cursor:pointer;padding:4px;" onclick="var d=this.parentNode.querySelector('.dropdown-users');d.style.display=d.style.display==='block'?'none':'block';event.stopPropagation();">
    <svg height="20" width="20" viewBox="0 0 20 20" style="vertical-align:middle;"><circle cx="10" cy="4" r="1.5" fill="#888"/><circle cx="10" cy="10" r="1.5" fill="#888"/><circle cx="10" cy="16" r="1.5" fill="#888"/></svg>
  </span>
  <div class="dropdown-users" style="display:none;position:absolute;right:16px;top:44px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);min-width:140px;z-index:1000;">
    <a href="<?=ADMIN_PATH?>access.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Users</a>
    <a href="<?=ADMIN_PATH?>access.php?tab=group" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Groups</a>
    <a href="<?=ADMIN_PATH?>access.php?tab=key" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Keys</a>
  </div>
</div>
<script>
document.addEventListener('click',function(){
  var d=document.querySelectorAll('.dropdown-users');
  for(var i=0;i<d.length;i++){d[i].style.display='none';}
});
</script>
<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;position:relative;">
  <div style="width:8px;height:48px;background:#00b894;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>stores.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Stores</a>
  </div>
  <span style="cursor:pointer;padding:4px;" onclick="var d=this.parentNode.querySelector('.dropdown-stores');d.style.display=d.style.display==='block'?'none':'block';event.stopPropagation();">
    <svg height="20" width="20" viewBox="0 0 20 20" style="vertical-align:middle;"><circle cx="10" cy="4" r="1.5" fill="#888"/><circle cx="10" cy="10" r="1.5" fill="#888"/><circle cx="10" cy="16" r="1.5" fill="#888"/></svg>
  </span>
  <div class="dropdown-stores" style="display:none;position:absolute;right:16px;top:44px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);min-width:140px;z-index:1000;">
    <a href="<?=ADMIN_PATH?>stores.php?tab=qgs" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">QGIS</a>
    <a href="<?=ADMIN_PATH?>stores.php?tab=pg" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">PostGIS</a>
  </div>
</div>
<script>
document.addEventListener('click',function(){
  var d=document.querySelectorAll('.dropdown-stores');
  for(var i=0;i<d.length;i++){d[i].style.display='none';}
});
</script>
<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;position:relative;">
  <div style="width:8px;height:48px;background:#00b894;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>layers.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Layers</a>
  </div>
  <span style="cursor:pointer;padding:4px;" onclick="var d=this.parentNode.querySelector('.dropdown-layers');d.style.display=d.style.display==='block'?'none':'block';event.stopPropagation();">
    <svg height="20" width="20" viewBox="0 0 20 20" style="vertical-align:middle;"><circle cx="10" cy="4" r="1.5" fill="#888"/><circle cx="10" cy="10" r="1.5" fill="#888"/><circle cx="10" cy="16" r="1.5" fill="#888"/></svg>
  </span>
  <div class="dropdown-layers" style="display:none;position:absolute;right:16px;top:44px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);min-width:140px;z-index:1000;">
    <a href="<?=ADMIN_PATH?>layers.php?tab=qgs" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">QGIS</a>
    <a href="<?=ADMIN_PATH?>layers.php?tab=pg" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">PostGIS</a>
  </div>
</div>
<script>
document.addEventListener('click',function(){
  var d=document.querySelectorAll('.dropdown-layers');
  for(var i=0;i<d.length;i++){d[i].style.display='none';}
});
</script>
<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#fdcb6e;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>geostories.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">GeoStories</a>
  </div>
</div>

<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#fdcb6e;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>dashboards.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Dashboards</a>
  </div>
</div>

<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;position:relative;">
  <div style="width:8px;height:48px;background:#fdcb6e;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>docs.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Documents</a>
  </div>
  <span style="cursor:pointer;padding:4px;" onclick="var d=this.parentNode.querySelector('.dropdown-documents');d.style.display=d.style.display==='block'?'none':'block';event.stopPropagation();">
    <svg height="20" width="20" viewBox="0 0 20 20" style="vertical-align:middle;"><circle cx="10" cy="4" r="1.5" fill="#888"/><circle cx="10" cy="10" r="1.5" fill="#888"/><circle cx="10" cy="16" r="1.5" fill="#888"/></svg>
  </span>
  <div class="dropdown-documents" style="display:none;position:absolute;right:16px;top:44px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);min-width:140px;z-index:1000;">
    <a href="<?=ADMIN_PATH?>docs.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Files</a>
    <a href="<?=ADMIN_PATH?>web_links.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">Links</a>
  </div>
</div>
<script>
document.addEventListener('click',function(){
  var d=document.querySelectorAll('.dropdown-documents');
  for(var i=0;i<d.length;i++){d[i].style.display='none';}
});
</script>

<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;position:relative;">
  <div style="width:8px;height:48px;background:#e17055;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>workshop.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">SQL Workshop</a>
  </div>
  <span style="cursor:pointer;padding:4px;" onclick="var d=this.parentNode.querySelector('.dropdown-sql-workshop');d.style.display=d.style.display==='block'?'none':'block';event.stopPropagation();">
    <svg height="20" width="20" viewBox="0 0 20 20" style="vertical-align:middle;"><circle cx="10" cy="4" r="1.5" fill="#888"/><circle cx="10" cy="10" r="1.5" fill="#888"/><circle cx="10" cy="16" r="1.5" fill="#888"/></svg>
  </span>
  <div class="dropdown-sql-workshop" style="display:none;position:absolute;right:16px;top:44px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.15);min-width:140px;z-index:1000;">
    <a href="<?=ADMIN_PATH?>sql_workshop_gpkg.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">GPKG</a>
    <a href="<?=ADMIN_PATH?>sql_workshop_gdb.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">GDB</a>
    <a href="<?=ADMIN_PATH?>sql_workshop_shp.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">SHP</a>
    <a href="<?=ADMIN_PATH?>sql_workshop_pg.php" style="display:block;padding:10px 16px;color:#2c3e50;text-decoration:none;font-size:14px;">PG</a>
  </div>
</div>
<script>
document.addEventListener('click',function(){
  var d=document.querySelectorAll('.dropdown-sql-workshop');
  for(var i=0;i<d.length;i++){d[i].style.display='none';}
});
</script>

<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#fdcb6e;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=ADMIN_PATH?>services.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">QCarta Cache</a>
  </div>
</div>

<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#8e44ad;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=TOP_PATH?>viewer.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Layer Portal</a>
  </div>
</div>

<!-- Plugins (Admin only) -->
<?php
if(isset($_SESSION[SESS_USR_KEY]) && ($_SESSION[SESS_USR_KEY]->accesslevel == 'Admin')){
  $plugins_ini = ADMIN_PATH.'plugins.ini';
  if (is_file($plugins_ini)) { 
    $plugins = parse_ini_file($plugins_ini, true);
    if (count($plugins)) {
      foreach ($plugins as $k => $plug) {
        if (is_file($plug['file'])) { ?>
<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#e67e22;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a href="<?=ADMIN_PATH.$plug['file']?>" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;"><?=$plug['title']?></a>
  </div>
</div>
<?php }
      }
    }
  }
}
?>

<!-- Access/Logout/Login -->
<?php if(isset($_SESSION[SESS_USR_KEY])){ ?>
<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#8e44ad;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a class="reference internal" href="<?=TOP_PATH?>index.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Landing Page</a>
  </div>
</div>

<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#c0392b;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a href="<?=TOP_PATH?>logout.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Log Out</a>
  </div>
</div>
<?php } else { ?>
<div style="display:flex;align-items:center;background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:0;margin-bottom:12px;">
  <div style="width:8px;height:48px;background:#00b894;"></div>
  <div style="padding:0 16px;flex:1;display:flex;align-items:center;min-height:48px;">
    <a href="<?=TOP_PATH?>login.php" style="font-size:15px;font-weight:400;color:#2c3e50;text-decoration:none;">Login</a>
  </div>
</div>
<?php } ?>

</div>
</div>
</div>
