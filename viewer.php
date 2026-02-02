<?php
  session_start();
	require('admin/incl/const.php');
	require('admin/class/database.php');
	require('admin/class/table.php');
	require('admin/class/access_group.php');
	require('admin/class/web_link.php');
	require('admin/class/doc.php');

  if(!isset($_SESSION[SESS_USR_KEY])) {
    header('Location: login.php');
    exit(0);
  }
	
	$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
	$acc_obj	= new access_group_Class($database->getConn(), $_SESSION[SESS_USR_KEY]->id);
	
	// super admin sees everything, other admins only owned
	$usr_grps = ($_SESSION[SESS_USR_KEY]->id == SUPER_ADMIN_ID) ? $acc_obj->getArr()
																															: $acc_obj->getByKV('user', $_SESSION[SESS_USR_KEY]->id);	
	$usr_grps_ids = implode(',', array_keys($usr_grps));	
	$layers = $acc_obj->getGroupRows('layer', $usr_grps_ids);
	$stories = $acc_obj->getGroupRows('geostory', $usr_grps_ids);
	$links   = $acc_obj->getGroupRows('web_link', $usr_grps_ids);
	$docs    = $acc_obj->getGroupRows('doc', $usr_grps_ids);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <title>QGIS App</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="bg-[#edf0f2]">
    <header>
      <div class="bg-[#50667f] shadow-sm">
        <div class="container mx-auto px-4">
          <div class="flex justify-between items-center py-4">
            <a href="#" class="text-white font-semibold text-xl">
              <strong>&nbsp;QCarta</strong>
            </a>
            <div class="space-x-4">
              <?php if($_SESSION[SESS_USR_KEY]->accesslevel == 'Admin'){ ?>
                <a href="admin/index.php" class="text-white hover:text-gray-200 text-lg font-light no-underline">Administration</a>
              <?php } ?>
              <a href="logout.php" class="text-white hover:text-gray-200 text-lg font-light no-underline">Log Out</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main>
      <div class="py-8">
        <div class="container mx-auto px-5 max-w-7xl">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Layers -->
            <?php while($row = pg_fetch_object($layers)) {
              $image = file_exists("assets/layers/".$row->id.".png") ? "assets/layers/".$row->id.".png" : "assets/layers/default.png"; ?>
              <div class="group">
                <a href="layers/<?=$row->id?>/index.php" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                  <div class="relative">
                    <img src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?= str_replace('_', ' ', $row->name) ?>" 
                         class="w-full h-40 object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                  </div>
                  <div class="p-3">
                    <div class="flex justify-between items-start">
                      <h3 class="text-sm font-medium text-gray-900 mb-1">
                        <?= str_replace('_', ' ', $row->name) ?>
                      </h3>
                      <?php if(is_file('qwc2/'.$row->id.'/.on_portal')) { ?>
                        <a href="qwc2/<?=$row->id?>/index.php" target="_blank" class="ml-2">
                          <img src="assets/images/qwc-logo.svg" alt="QWC Preview" class="w-5 h-5">
                        </a>
                      <?php } ?>
                    </div>
                    <?php if($row->description) { ?>
                      <p class="text-xs text-gray-500 line-clamp-2"><?=$row->description?></p>
                    <?php } else { ?>
                      <p class="text-xs text-gray-500">View Details</p>
                    <?php } ?>
                  </div>
                </a>
              </div>
            <?php }
              pg_free_result($layers);
            ?>
          
            <!-- Stories -->
            <?php while($row = pg_fetch_object($stories)) {
              $image = file_exists("assets/geostories/".$row->id.".png") ? "assets/geostories/".$row->id.".png" : "assets/geostories/default.png"; ?>
              <div class="group">
                <a href="geostories/<?=$row->id?>/index.php" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                  <div class="relative">
                    <img src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=$row->name?>" 
                         class="w-full h-40 object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                  </div>
                  <div class="p-3">
                    <h3 class="text-sm font-medium text-gray-900 mb-1">
                      <?=$row->name?>
                    </h3>
                    <?php if($row->description) { ?>
                      <p class="text-xs text-gray-500 line-clamp-2"><?=$row->description?></p>
                    <?php } else { ?>
                      <p class="text-xs text-gray-500">View Details</p>
                    <?php } ?>
                  </div>
                </a>
              </div>
            <?php }
              pg_free_result($stories);
            ?>
          
            <!-- Links -->
            <?php while($row = pg_fetch_object($links)) {
              $image = file_exists("assets/links/".$row->id.".png") ? "assets/links/".$row->id.".png" : "assets/links/default.png"; ?>
              <div class="group">
                <a href="<?=$row->url?>" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                  <div class="relative">
                    <img src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=$row->name?>" 
                         class="w-full h-40 object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                  </div>
                  <div class="p-3">
                    <h3 class="text-sm font-medium text-gray-900 mb-1">
                      <?=$row->name?>
                    </h3>
                    <?php if($row->description) { ?>
                      <p class="text-xs text-gray-500 line-clamp-2"><?=$row->description?></p>
                    <?php } else { ?>
                      <p class="text-xs text-gray-500">View Details</p>
                    <?php } ?>
                  </div>
                </a>
              </div>
            <?php }
              pg_free_result($links);
            ?>

            <!-- Docs -->
            <?php while($row = pg_fetch_object($docs)) {
              $image = "assets/docs/default.png";
              if(file_exists("assets/docs/".$row->id.".png")){
                $image = "assets/docs/".$row->id.".png";
              }else{
                $ext = pathinfo($row->filename, PATHINFO_EXTENSION);
                if(file_exists("assets/docs/".$ext.".png")){
                  $image = "assets/docs/".$ext.".png";
                }
              }
            ?>
              <div class="group">
                <a href="doc_file.php?id=<?=$row->id?>" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                  <div class="relative">
                    <img src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=$row->name?>" 
                         class="w-full h-40 object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                  </div>
                  <div class="p-3">
                    <h3 class="text-sm font-medium text-gray-900 mb-1">
                      <?=$row->name?>
                    </h3>
                    <?php if($row->description) { ?>
                      <p class="text-xs text-gray-500 line-clamp-2"><?=$row->description?></p>
                    <?php } else { ?>
                      <p class="text-xs text-gray-500">View Details</p>
                    <?php } ?>
                  </div>
                </a>
              </div>
            <?php }
              pg_free_result($docs);
            ?>
          </div>
        </div>
      </div>
    </main>

    <footer class="py-5 text-gray-600">
      <div class="container mx-auto px-4">
        <p class="float-end mb-1">
          <a href="#" class="text-gray-600 hover:text-gray-800 text-lg font-light no-underline">Back to top</a>
        </p>
      </div>
    </footer>
  </body>
</html>
