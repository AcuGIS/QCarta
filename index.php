<?php
session_start();
require('admin/incl/const.php');
require('admin/class/database.php');
require('admin/class/table.php');
require('admin/class/table_ext.php');
require('admin/class/layer.php');
require('admin/class/qgs_layer.php');
require('admin/class/geostory.php');
require('admin/class/web_link.php');
require('admin/class/doc.php');
require('admin/class/topic.php');
require('admin/class/access_group.php');
	
$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$conn = $database->getConn();
$user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;

$topic_obj	= new topic_Class($conn, SUPER_ADMIN_ID);
$gemet_obj	= new topic_Class($conn, SUPER_ADMIN_ID, 'gemet');

$topics  = $topic_obj->getRows();
$gemets  = $gemet_obj->getRows();

if(empty($_SESSION[SESS_USR_KEY])){
    $qgsLayer   = new qgs_layer_Class($conn, $user_id);
    $geostory   = new geostory_Class($conn, $user_id);
    $webLink    = new web_link_Class($conn, $user_id);
    $doc        = new doc_Class($conn, $user_id);
    
    $layers  = $qgsLayer->getPublic();
    $stories = $geostory->getPublic();
    $links   = $webLink->getPublic();
    $docs    = $doc->getPublic();
}else{
    
    $acc_obj	= new access_group_Class($database->getConn(), $user_id);
    // super admin sees everything, other admins only owned
    $usr_grps = ($user_id == SUPER_ADMIN_ID) ? $acc_obj->getArr() : $acc_obj->getByKV('user', $user_id);
    $usr_grps_ids = implode(',', array_keys($usr_grps));
    	
    $layers = $acc_obj->getGroupRows('layer', $usr_grps_ids);
    $stories = $acc_obj->getGroupRows('geostory', $usr_grps_ids);
    $links   = $acc_obj->getGroupRows('web_link', $usr_grps_ids);
    $docs    = $acc_obj->getGroupRows('doc', $usr_grps_ids);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>QCarta</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="assets/dist/js/search.js" defer></script>
<script src="assets/dist/js/map-search.js" defer></script>
</head>
<body class="min-h-screen bg-gray-100 font-sans">
    <!-- Map Search Modal (moved outside sidebar for stacking context) -->
    <div id="mapSearchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[9999]">
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg w-full max-w-4xl h-[80vh] flex flex-col z-[10000]">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Map Search</h3>
                    <button id="closeMapSearch" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 relative">
                    <div id="searchMap" class="w-full h-full z-0"></div>
                    <div class="absolute top-4 right-4 bg-white p-2 rounded-lg shadow-lg z-10">
                        <button id="clearSelection" class="px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600">Clear</button>
                    </div>
                </div>
                <div class="p-4 border-t">
                    <button id="searchInArea" class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                        Search in Selected Area
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="fixed top-0 left-0 right-0 h-[75px] bg-gree-500 text-white flex justify-between items-center px-5 z-50" style="background-color:#2e8b57!important; border-bottom: 1px solid black;">
        <div class="text-4xl font-bold" style="font-family: Century Gothic, 'Trebuchet MS', Tahoma, Verdana; padding-top: 2px!important"><img src="assets/images/qcarta-logo.png" alt="" 
style="display:inline-block; padding-right:5px; font-family: Century Gothic, 'Trebuchet MS', Tahoma, Verdana; font-size:2.75rem!important;"></div>
        <div class="space-x-5">
            <?php if(isset($_SESSION[SESS_USR_KEY])) { ?>
                <a href="logout.php" class="text-white hover:text-gray-200 text-base">Logout</a>
            <?php } else { ?>
                <a href="login.php" class="text-white hover:text-gray-200 text-base">Login</a>
            <?php } ?>
            <?php if(isset($_SESSION[SESS_USR_KEY]) && ($_SESSION[SESS_USR_KEY]->accesslevel == 'Admin')) { ?>
                <a href="admin/index.php" class="text-white hover:text-gray-200 text-base">Administration</a>
            <?php } ?>
        </div>
    </div>

    <div class="flex pt-20">
        <!-- Search Sidebar -->
        <div class="w-80 fixed left-0 top-[75px] bottom-0 bg-white shadow-lg p-6 overflow-y-auto" style="margin-top: 0rem!important">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Search</h2>
            
            <!-- Generic Text Search -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Generic Text Search</label>
                <div class="relative">
                    <input type="text" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Search anything...">
                    <button class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Topic Search -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search by Topics</label>
                <select id="topic_id" multiple class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" style="height: 120px;">
                    <?php while($row = pg_fetch_object($topics)) { ?>
                        <option value="<?=$row->id?>"><?=$row->name?></option>    
                    <?php } ?>
                </select>
            </div>
            
            <!-- GEMET Search -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search by GEMET</label>
                <select id="gemet_id" multiple class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" style="height: 120px;">
                    <?php while($row = pg_fetch_object($gemets)) { ?>
                        <option value="<?=$row->id?>"><?=$row->name?></option>    
                    <?php } ?>
                </select>
            </div>

            <!-- Keyword Search -->
           <!-- <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search by Keywords</label>
                <div class="space-y-2">
                    <div id="keywordTags" class="flex flex-wrap gap-2">
                    </div>
                    <input type="text" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Add keywords...">
                </div>
            </div> -->

            <!-- Map Search -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search on Map</label>
                <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                    <p class="text-sm text-gray-600 mb-2">Click on the map to search in this area</p>
                    <button id="openMapSearch" class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                        Open Map Search
                    </button>
                </div>
            </div>

            <!-- Search Filters -->
            <div class="pt-4 border-t border-gray-200">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Resource Type</h3>
                <div class="space-y-3">
                    <label class="flex items-center">
                        <input type="checkbox" class="rounded text-blue-500 focus:ring-blue-500" value="layers">
                        <span class="ml-2 text-sm text-gray-600">Maps</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="rounded text-blue-500 focus:ring-blue-500" value="stories">
                        <span class="ml-2 text-sm text-gray-600">GeoStories</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="rounded text-blue-500 focus:ring-blue-500" value="docs">
                        <span class="ml-2 text-sm text-gray-600">Documents</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="rounded text-blue-500 focus:ring-blue-500" value="links">
                        <span class="ml-2 text-sm text-gray-600">Links</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="ml-80 flex-1 px-5 max-w-7xl mx-auto" style="margin-left: 21rem!important; margin-top: .7rem!important; padding-bottom:25px!important">
        <!--<div class="bg-white rounded-lg shadow-md p-6 mb-8 flex-1 px-5 max-w-7xl mx-auto">-->
            <h1 class="mb-8 text-2xl text-gray-700">&nbsp;</h1>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" style="padding-left:40px!important">
            <?php if (!empty($layers)) { ?>
                    <?php while ($row = pg_fetch_object($layers)) {
                        $image = file_exists("assets/layers/".$row->id.".png") ? "assets/layers/".$row->id.".png" : "assets/layers/default.png";
                    ?>
                        <div class="group">
                            <a href="layers/<?=$row->id?>/index.php" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                                <div class="relative">
                                    <img src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?= str_replace('_', ' ', $row->name) ?>" 
                                         class="w-full h-36 object-cover">
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                                </div>
                                <div class="p-4">
                                    <h3 class="text-base font-medium text-gray-900 mb-1">
                                        <?= str_replace('_', ' ', $row->name) ?>
                                    </h3>
                                    <?php if($row->description) { ?>
                                        <p class="text-sm text-gray-500 line-clamp-2"><?=$row->description?></p>
                                        <p>&nbsp;</p>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"  d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                                            </svg>
                                            Map
                                        </div>
                                        <div class="mt-auto flex items-center justify-between pt-4">
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Last updated: <?=substr($row->last_updated, 0, -7)?>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <p class="text-sm text-gray-500">View Details</p>
                                    <?php } ?>
                                </div>
                            </a>
                        </div>
                    <?php }
                        pg_free_result($layers);
                    ?>
            <?php } ?>
            
            <?php if (!empty($stories)) { ?>
                    <?php while ($row = pg_fetch_object($stories)) {
                        $image = file_exists("assets/geostories/".$row->id.".png") ? "assets/geostories/".$row->id.".png" : "assets/geostories/default.png";
                    ?>
                        <div class="group">
                            <a href="geostories/<?=$row->id?>/index.php" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                                <div class="relative">
                                    <img src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=$row->name?>" 
                                         class="w-full h-36 object-cover">
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                                </div>
                                <div class="p-4">
                                    <h3 class="text-base font-medium text-gray-900 mb-1">
                                        <?=$row->name?>
                                    </h3>
                                    <?php if($row->description) { ?>
                                        <p class="text-sm text-gray-500 line-clamp-2"><?=$row->description?></p>
                                        <p>&nbsp;</p>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"></path>
                                            </svg>
                                            Presentation
                                        </div>
                                        <div class="mt-auto flex items-center justify-between pt-4">
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Last updated: <?=substr($row->last_updated, 0, -7)?>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <p class="text-sm text-gray-500">View Details</p>
                                    <?php } ?>
                                </div>
                            </a>
                        </div>
                    <?php }
                        pg_free_result($stories);
                    ?>
            <?php } ?>
            
            <?php if (!empty($links)) { ?>
                    <?php while ($row = pg_fetch_object($links)) {
                        $image = file_exists("assets/links/".$row->id.".png") ? "assets/links/".$row->id.".png" : "assets/links/default.png";
                    ?>
                        <div class="group">
                            <a href="<?=$row->url?>" class="block bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 transition-colors duration-200" target="_blank">
                                <div class="relative">
                                    <img src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=$row->name?>" 
                                         class="w-full h-36 object-cover">
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                                </div>
                                <div class="p-4">
                                    <h3 class="text-base font-medium text-gray-900 mb-1">
                                        <?=$row->name?>
                                    </h3>
                                    <?php if($row->description) { ?>
                                        <p class="text-sm text-gray-500 line-clamp-2"><?=$row->description?></p>
                                        <p>&nbsp;</p>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"></path>
                                            </svg>
                                            Link
                                        </div>
                                        <div class="mt-auto flex items-center justify-between pt-4">
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Last updated: <?=substr($row->last_updated, 0, -7)?>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <p class="text-sm text-gray-500">View Details</p>
                                    <?php } ?>
                                </div>
                            </a>
                        </div>
                    <?php }
                        pg_free_result($links);
                    ?>
            <?php } ?>
            
            <?php if (!empty($docs)) { ?>
                    <?php while ($row = pg_fetch_object($docs)) {
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
                                         class="w-full h-36 object-cover">
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-200"></div>
                                </div>
                                <div class="p-4">
                                    <h3 class="text-base font-medium text-gray-900 mb-1">
                                        <?=$row->name?>
                                    </h3>
                                    <?php if($row->description) { ?>
                                        <p class="text-sm text-gray-500 line-clamp-2"><?=$row->description?></p>
                                        <p>&nbsp;</p>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"></path>
                                            </svg>
                                            Document
                                        </div>
                                        <div class="mt-auto flex items-center justify-between pt-4">
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Last updated: <?=substr($row->last_updated, 0, -7)?>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <p class="text-sm text-gray-500">View Details</p>
                                    <?php } ?>
                                </div>
                            </a>
                        </div>
                    <?php }
                        pg_free_result($docs);
                    ?>
            <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>
