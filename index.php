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
require('admin/class/dashboard.php');
require('admin/class/access_group.php');

// --- Resource-type tab routing: ?type=all|maps|dashboards|geostories|documents|links ---
$type = $_GET['type'] ?? 'all';
$active = $type; // used by header tab classes

$show_maps       = ($type === 'all' || $type === 'maps');
$show_dashboards = ($type === 'all' || $type === 'dashboards');
$show_geostories = ($type === 'all' || $type === 'geostories');
$show_documents  = ($type === 'all' || $type === 'documents');
$show_links      = ($type === 'all' || $type === 'links');


$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
$conn = $database->getConn();
$user_id = isset($_SESSION[SESS_USR_KEY]) ? $_SESSION[SESS_USR_KEY]->id : SUPER_ADMIN_ID;

$topic_obj = new topic_Class($conn, SUPER_ADMIN_ID);
$gemet_obj = new topic_Class($conn, SUPER_ADMIN_ID, 'gemet');

$topics = $topic_obj->getRows();
$gemets = $gemet_obj->getRows();

if (empty($_SESSION[SESS_USR_KEY])) {
    $qgsLayer = new qgs_layer_Class($conn, $user_id);
    $geostory = new geostory_Class($conn, $user_id);
    $webLink  = new web_link_Class($conn, $user_id);
    $doc      = new doc_Class($conn, $user_id);
    $dash     = new dashboard_Class($conn, $user_id);
    $layers   = $qgsLayer->getPublic();
    $stories  = $geostory->getPublic();
    $links    = $webLink->getPublic();
    $docs     = $doc->getPublic();
    $dashs    = $dash->getPublic();
} else {
    $acc_obj = new access_group_Class($database->getConn(), $user_id);
    $usr_grps = ($user_id == SUPER_ADMIN_ID) ? $acc_obj->getArr() : $acc_obj->getByKV('user', $user_id);
    $usr_grps_ids = implode(',', array_keys($usr_grps));
    $layers  = $acc_obj->getGroupRows('layer', $usr_grps_ids);
    $stories = $acc_obj->getGroupRows('geostory', $usr_grps_ids);
    $links   = $acc_obj->getGroupRows('web_link', $usr_grps_ids);
    $docs    = $acc_obj->getGroupRows('doc', $usr_grps_ids);
    $dashs   = $acc_obj->getGroupRows('dashboard', $usr_grps_ids);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>QCarta</title>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="assets/dist/js/search.js" defer></script>
<script src="assets/dist/js/map-search.js" defer></script>
<style>
/* Sidebar separation & subtle app wide tweaks */
body { --qcarta-accent: #3B82F6; }
.qcarta-header { background:#003d4d; border-bottom:1px solid rgba(0,0,0,.5); }
.qcarta-sidebar { border-right:1px solid #e5e7eb; }


/* 
.with-sidebar-pad { padding-left: 21rem; }

@media (max-width: 1024px) {
  .with-sidebar-pad { padding-left: 1rem; }
}
 */

/* Card image zoom + quick actions */
.group .thumb-wrap { overflow:hidden; }
.hover-zoom { transform:scale(1); transition:transform .5s ease; }
.group:hover .hover-zoom { transform:scale(1.12); }

.card { border:1px solid #d1d5db; transition: box-shadow .2s ease, border-color .2s ease; }
.group:hover .card { border-color: var(--qcarta-accent); box-shadow: 0 6px 22px rgba(0,0,0,.08) }
.card-foot { border-top:1px solid #f1f5f9; }

/* Type badges for quick visual parsing */
.badge { font-size:.75rem; line-height:1rem; padding:.25rem .5rem; border-radius:.5rem; font-weight:600; }
.badge-map { background:#dbeafe; color:#1d4ed8; }
.badge-dash { background:#fee2e2; color:#b91c1c; }
.badge-pres { background:#dcfce7; color:#15803d; }
.badge-link { background:#fef3c7; color:#b45309; }
.badge-doc  { background:#f3e8ff; color:#6d28d9; }

/* Quick actions overlay */
.quick-actions { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.0); opacity:0; transition:opacity .2s ease, background .2s ease; }
.group:hover .quick-actions { background:rgba(0,0,0,.15); opacity:1; }

/* Filter chips */
.chips-wrap { display:flex; gap:.5rem; flex-wrap:wrap; }
.chip { background:#e5f0ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:.25rem .5rem; border-radius:9999px; font-size:.75rem; display:flex; align-items:center; gap:.25rem; }
.chip button { line-height:1; }

/* Small height tune on thumbs */
.h-32 { height:10rem !important; }
</style>
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-800">
    <!-- Map Search Modal -->
    <div id="mapSearchModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[9999]" aria-hidden="true">
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg w-full max-w-4xl h-[80vh] flex flex-col z-[10000]" role="dialog" aria-modal="true" aria-label="Map search">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Map Search</h3>
                    <button id="closeMapSearch" class="text-gray-500 hover:text-gray-700" aria-label="Close map search">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 relative">
                    <div id="searchMap" class="w-full h-full z-0"></div>
                    <div class="absolute top-4 right-4 bg-white p-2 rounded-lg shadow-lg z-10">
                        <button id="clearSelection" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700" aria-label="Clear map selection">Clear</button>
                    </div>
                </div>
                <div class="p-4 border-t">
                    <button id="searchInArea" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Search in Selected Area
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Top bar -->

<!-- Header -->
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/70 border-b">
  <!-- top row -->
  <div class="h-14 flex items-center px-4 md:px-6">
    <div class="flex items-center gap-2">
      <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 font-bold">Q</span>
      <span class="text-lg md:text-xl font-semibold tracking-tight text-gray-900">QCarta</span>
    </div>

    <div class="ml-auto flex items-center gap-2">
      
      <?php if(isset($_SESSION[SESS_USR_KEY])) { ?>
        <a href="logout.php" class="hidden md:inline text-sm text-gray-700 hover:text-emerald-700">Logout</a>
      <?php } else { ?>
        <a href="login.php" class="text-sm text-gray-700 hover:text-emerald-700">Login</a>
      <?php } ?>
      <?php if(isset($_SESSION[SESS_USR_KEY]) && ($_SESSION[SESS_USR_KEY]->accesslevel == 'Admin')) { ?>
                <a href="admin/index.php"  class="text-sm text-gray-700 hover:text-emerald-700">Administration</a>
            <?php } ?>
    </div>
  </div>

  <!-- sub row: resource-type tabs -->
  <div class="h-11 flex items-center px-4 md:px-6 border-t border-gray-100">
    <nav class="flex items-center gap-1">
      <?php
        function rtab($key,$label,$active){
          $is = ($active === $key) ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'text-gray-700 hover:bg-gray-50';
          $href = '?type=' . urlencode($key);
          echo "<a href=\"$href\" class=\"px-3 h-8 inline-flex items-center rounded-lg text-sm $is\">$label</a>";
        }
        rtab('all','All',$active);
        rtab('maps','Maps',$active);
        rtab('dashboards','Dashboards',$active);
        rtab('geostories','GeoStories',$active);
        rtab('documents','Documents',$active);
        rtab('links','Links',$active);
      ?>
    </nav>

    
  </div>

  <div class="h-[2px] bg-gradient-to-r from-emerald-500 via-emerald-400 to-emerald-600"></div>
</header>

    <div class="flex pt-20" style="padding-top: 1rem;">
        <!-- Sidebar -->
        <!-- Sidebar -->
<aside class="qcarta-sidebar w-80 fixed left-0 top-[55px] bottom-0 bg-white p-6 overflow-y-auto">
  <h2 class="text-xl font-semibold text-gray-900 mb-6">
    <i class="fa-solid fa-filter"></i> Filters
  </h2>

  <!-- Text (unchanged position) -->
  
<!-- Generic Text Search -->
<div class="mb-6">
  <label class="block text-sm font-medium text-gray-700 mb-2">Text</label>
  <div class="relative">
    <input type="text" 
           id="search"
           name="search"
           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
           placeholder="Search anything...">
    <button class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
    </button>
  </div>
</div>


  <!-- Topic (unchanged position) -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Topic</label>
    <select id="topic_id" multiple
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-600 focus:border-blue-600"
            style="height: 110px" aria-label="Topic filter">
      <?php while($row = pg_fetch_object($topics)) { ?>
        <option value="<?=$row->id?>"><?=$row->name?></option>
      <?php } ?>
    </select>
  </div>

  <!-- Keyword / GEMET (unchanged position) -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Keyword</label>
    <select id="gemet_id" multiple
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-600 focus:border-blue-600"
            style="height: 110px" aria-label="Keyword filter">
      <?php while($row = pg_fetch_object($gemets)) { ?>
        <option value="<?=$row->id?>"><?=$row->name?></option>
      <?php } ?>
    </select>
  </div>

  <!-- Map (unchanged position) -->
  <div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-2">Map</label>
    <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
      <p class="text-sm text-gray-600 mb-2">Select area on map to filter results.</p>
      <button id="openMapSearch"
              class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              aria-haspopup="dialog" aria-controls="mapSearchModal">
        Search on Map
      </button>
    </div>
  </div>



























































  <!-- Resource Type (moved back to bottom) -->
  <div class="pt-4 border-t border-gray-200 mb-6">
    <h3 class="text-sm font-medium text-gray-700 mb-3">Resource Type</h3>
    <div class="space-y-3" id="resourceType">
      <label class="flex items-center">
        <input type="checkbox" value="layers" <?php if($type==='maps') echo 'checked'; ?>>
        <span class="ml-2 text-sm text-gray-700">Maps</span>
      </label>
      <label class="flex items-center">
        <input type="checkbox" value="stories" <?php if($type==='geostories') echo 'checked'; ?>>
        <span class="ml-2 text-sm text-gray-700">GeoStories</span>
      </label>
      <label class="flex items-center">
        <input type="checkbox" value="docs" <?php if($type==='documents') echo 'checked'; ?>>
        <span class="ml-2 text-sm text-gray-700">Documents</span>
      </label>
      <label class="flex items-center">
        <input type="checkbox" value="links" <?php if($type==='links') echo 'checked'; ?>>

        <span class="ml-2 text-sm text-gray-700">Links</span>
      </label>
      <label class="flex items-center">
        <input type="checkbox" value="dashboards" <?php if($type==='dashboards') echo 'checked'; ?>>
        <span class="ml-2 text-sm text-gray-700">Dashboards</span>
      </label>
    </div>
  </div>

  <!-- Apply / Clear (kept, now at the bottom)
  <div class="flex gap-3">
    <button id="applyFilters" class="flex-1 px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Apply</button>
    <button id="clearFilters" class="px-3 py-2 border border-gray-300 rounded hover:bg-gray-50">Clear</button>
  </div>  -->
</aside>

        <!-- Main -->
        <main class="ml-80 flex-1 px-5 max-w-7xl mx-auto" style="margin-left:21rem; margin-top:.7rem; padding-bottom:25px">
            <!-- Active filter chips -->
            <div class="px-4">
                <div id="activeChips" class="chips-wrap mb-5"></div>
            </div>

            <h1 class="sr-only">QCarta Resources</h1>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 pl-10">




            <?php if ($show_maps && !empty($layers)) { ?>
                <?php while ($row = pg_fetch_object($layers)) {
                    $image = file_exists("assets/layers/".$row->id.".png") ? "assets/layers/".$row->id.".png" : "assets/layers/default.png";
                ?>
                <div class="group h-64 relative">
                    <a href="layers/<?=$row->id?>/index.php" class="card bg-white rounded-lg overflow-hidden h-full flex flex-col" target="_blank" aria-label="Open map: <?=htmlspecialchars(str_replace('_', ' ', $row->name))?>">
                        <div class="thumb-wrap relative">
                            <img loading="lazy" src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=htmlspecialchars(str_replace('_', ' ', $row->name))?> thumbnail"
                                 class="w-full h-32 object-cover hover-zoom">
                            <div class="quick-actions">
                                <span class="px-3 py-1 bg-white/90 rounded shadow text-sm font-medium">Open Map</span>
                            </div>
                        </div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 mb-1"><?= str_replace('_', ' ', $row->name) ?></h3>
                                <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= $row->description ? $row->description : 'View details' ?></p>
                            </div>
                        </div>
                        <div class="card-foot px-3 py-2 flex items-center justify-between">
                            <span class="badge badge-map">Map</span>
                            <span class="text-xs text-gray-500"><i class="fa-regular fa-eye mr-1"></i>Public</span>
                        </div>
                    </a>
                </div>
                <?php } pg_free_result($layers); ?>
            <?php } ?>

  



            <?php if ($show_dashboards && !empty($dashs)) { ?>

                <?php while ($row = pg_fetch_object($dashs)) {
                    $image = file_exists("assets/dashboards/".$row->id.".png") ? "assets/dashboards/".$row->id.".png" : "assets/dashboards/default.png";
                ?>
                <div class="group h-64 relative">
                    <a href="dashboard.php?id=<?=$row->id?>" class="card bg-white rounded-lg overflow-hidden h-full flex flex-col" target="_blank" aria-label="Open dashboard: <?=htmlspecialchars($row->name)?>">
                        <div class="thumb-wrap relative">
                            <img loading="lazy" src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=htmlspecialchars($row->name)?> thumbnail" class="w-full h-32 object-cover hover-zoom">
                            <div class="quick-actions"><span class="px-3 py-1 bg-white/90 rounded shadow text-sm font-medium">Open Dashboard</span></div>
                        </div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 mb-1"><?=$row->name?></h3>
                                <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= $row->description ? $row->description : 'View dashboard' ?></p>
                            </div>
                        </div>
                        <div class="card-foot px-3 py-2 flex items-center justify-between">
                            <span class="badge badge-dash">Dashboard</span>
                            <span class="text-xs text-gray-500"><i class="fa-regular fa-eye mr-1"></i>Public</span>
                        </div>
                    </a>
                </div>
                <?php } pg_free_result($dashs); ?>
            <?php } ?>

            <?php if ($show_geostories && !empty($stories)) { ?>

                <?php while ($row = pg_fetch_object($stories)) {
                    $image = file_exists("assets/geostories/".$row->id.".png") ? "assets/geostories/".$row->id.".png" : "assets/geostories/default.png";
                ?>
                <div class="group h-64 relative">
                    <a href="geostories/<?=$row->id?>/index.php" class="card bg-white rounded-lg overflow-hidden h-full flex flex-col" target="_blank" aria-label="Open geostory: <?=htmlspecialchars($row->name)?>">
                        <div class="thumb-wrap relative">
                            <img loading="lazy" src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=htmlspecialchars($row->name)?> thumbnail" class="w-full h-32 object-cover hover-zoom">
                            <div class="quick-actions"><span class="px-3 py-1 bg-white/90 rounded shadow text-sm font-medium">Open Story</span></div>
                        </div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 mb-1"><?=$row->name?></h3>
                                <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= $row->description ? $row->description : 'View details' ?></p>
                            </div>
                        </div>
                        <div class="card-foot px-3 py-2 flex items-center justify-between">
                            <span class="badge badge-pres">Presentation</span>
                            <span class="text-xs text-gray-500"><i class="fa-regular fa-eye mr-1"></i>Public</span>
                        </div>
                    </a>
                </div>
                <?php } pg_free_result($stories); ?>
            <?php } ?>

            <?php if ($show_links && !empty($links)) { ?>

                <?php while ($row = pg_fetch_object($links)) {
                    $image = file_exists("assets/links/".$row->id.".png") ? "assets/links/".$row->id.".png" : "assets/links/default.png";
                ?>
                <div class="group h-64 relative">
                    <a href="<?=$row->url?>" class="card bg-white rounded-lg overflow-hidden h-full flex flex-col" target="_blank" rel="noopener" aria-label="Open link: <?=htmlspecialchars($row->name)?>">
                        <div class="thumb-wrap relative">
                            <img loading="lazy" src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=htmlspecialchars($row->name)?> thumbnail" class="w-full h-32 object-cover hover-zoom">
                            <div class="quick-actions"><span class="px-3 py-1 bg-white/90 rounded shadow text-sm font-medium">Open Link</span></div>
                        </div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 mb-1"><?=$row->name?></h3>
                                <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= $row->description ? $row->description : 'View details' ?></p>
                            </div>
                        </div>
                        <div class="card-foot px-3 py-2 flex items-center justify-between">
                            <span class="badge badge-link">Link</span>
                            <span class="text-xs text-gray-500"><i class="fa-regular fa-eye mr-1"></i>Public</span>
                        </div>
                    </a>
                </div>
                <?php } pg_free_result($links); ?>
            <?php } ?>

            <?php if ($show_documents && !empty($docs)) { ?>

                <?php while ($row = pg_fetch_object($docs)) {
                    $image = "assets/docs/default.png";
                    if (file_exists("assets/docs/".$row->id.".png")) {
                        $image = "assets/docs/".$row->id.".png";
                    } else {
                        $ext = pathinfo($row->filename, PATHINFO_EXTENSION);
                        if (file_exists("assets/docs/".$ext.".png")) $image = "assets/docs/".$ext.".png";
                    }
                ?>
                <div class="group h-64 relative">
                    <a href="doc_file.php?id=<?=$row->id?>" class="card bg-white rounded-lg overflow-hidden h-full flex flex-col" target="_blank" aria-label="Open document: <?=htmlspecialchars($row->name)?>">
                        <div class="thumb-wrap relative">
                            <img loading="lazy" src="<?=$image?>?v=<?=filemtime($image)?>" alt="<?=htmlspecialchars($row->name)?> thumbnail" class="w-full h-32 object-cover hover-zoom">
                            <div class="quick-actions"><span class="px-3 py-1 bg-white/90 rounded shadow text-sm font-medium">Open Document</span></div>
                        </div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 mb-1"><?=$row->name?></h3>
                                <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= $row->description ? $row->description : 'View details' ?></p>
                            </div>
                        </div>
                        <div class="card-foot px-3 py-2 flex items-center justify-between">
                            <span class="badge badge-doc">Document</span>
                            <span class="text-xs text-gray-500"><i class="fa-regular fa-eye mr-1"></i>Public</span>
                        </div>
                    </a>
                </div>
                <?php } pg_free_result($docs); ?>
            <?php } ?>

            </div>
        </main>
    </div>

    <script>
    // Filter Chips (Topic + Keyword + Resource Type + Text)
    const chipsWrap = document.getElementById('activeChips');
    const topicSelect = document.getElementById('topic_id');
    const gemetSelect = document.getElementById('gemet_id');
    const textSearch = document.getElementById('textSearch');
    const resourceType = document.getElementById('resourceType');

    function chip(label, onRemove) {
        const c = document.createElement('span');
        c.className = 'chip';
        c.innerHTML = `<span>${label}</span><button type="button" aria-label="Remove filter">&times;</button>`;
        c.querySelector('button').addEventListener('click', onRemove);
        return c;
    }

    function renderChips() {
        chipsWrap.innerHTML = '';

        // Text
        if (textSearch.value.trim()) {
            chipsWrap.appendChild(chip(`Text: ${textSearch.value.trim()}`, () => { textSearch.value=''; renderChips(); }));
        }

        // Resource Type
        [...resourceType.querySelectorAll('input[type=checkbox]')].forEach(cb => {
            if (cb.checked) {
                chipsWrap.appendChild(chip(`Type: ${cb.nextElementSibling.textContent.trim()}`, () => { cb.checked=false; renderChips(); }));
            }
        });

        // Topic
        [...topicSelect.selectedOptions].forEach(opt => {
            chipsWrap.appendChild(chip(`Topic: ${opt.text}`, () => { opt.selected=false; renderChips(); }));
        });

        // GEMET
        [...gemetSelect.selectedOptions].forEach(opt => {
            chipsWrap.appendChild(chip(`Keyword: ${opt.text}`, () => { opt.selected=false; renderChips(); }));
        });
    }

    topicSelect.addEventListener('change', renderChips);
    gemetSelect.addEventListener('change', renderChips);
    textSearch.addEventListener('input', renderChips);
    resourceType.addEventListener('change', renderChips);
    renderChips();

    // Map Search modal open/close
    const openMapSearch = document.getElementById('openMapSearch');
    const mapSearchModal = document.getElementById('mapSearchModal');
    const closeMapSearch = document.getElementById('closeMapSearch');

    openMapSearch?.addEventListener('click', () => mapSearchModal.classList.remove('hidden'));
    closeMapSearch?.addEventListener('click', () => mapSearchModal.classList.add('hidden'));
    mapSearchModal?.addEventListener('click', (e) => { if (e.target === mapSearchModal) mapSearchModal.classList.add('hidden'); });

    // Apply / Clear 
    document.getElementById('clearFilters')?.addEventListener('click', () => {
        textSearch.value = '';
        [...resourceType.querySelectorAll('input[type=checkbox]')].forEach(cb => cb.checked=false);
        [...topicSelect.options].forEach(o => o.selected=false);
        [...gemetSelect.options].forEach(o => o.selected=false);
        renderChips();
        // TODO: hook into your existing search.js to actually clear results
    });

    document.getElementById('applyFilters')?.addEventListener('click', () => {
        // TODO: wire these values into your existing search pipeline
        // Example: window.applySearch({ text, types, topics, gemets, bbox })
        // Keeping UX visible while preserving your backend filtering logic.
        alert('Filters applied (wire this to your existing search).');
    });
    </script>
</body>
</html>
