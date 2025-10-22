<header class="qc-fullbleed fixed top-0 left-0 right-0 z-40 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/70 border-b">
  <div class="h-14 flex items-center px-4 md:px-6">
    <div class="flex items-center gap-2">
      <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 font-bold">Q</span>
      <span class="text-lg md:text-xl font-semibold tracking-tight text-gray-900">QCarta</span>
    </div>

    <div class="ml-auto flex items-center gap-0">
      <!-- your links (full-height) -->
      <a href="<?=TOP_PATH?>index.php"
         class="flex items-center h-14 px-4 text-sm text-gray-700 hover:text-emerald-700 hover:bg-emerald-50 transition <?=(NAV_SEL=='Home'?'font-semibold text-emerald-700 bg-emerald-50':'')?>">Home</a>
      
      <?php if(isset($_SESSION[SESS_USR_KEY])) { ?>
        <a href="<?=TOP_PATH?>logout.php" class="flex items-center h-14 px-4 text-sm text-gray-700 hover:text-emerald-700 hover:bg-emerald-50 transition">Logout</a>
      <?php } else { ?>
        <a href="<?=TOP_PATH?>login.php" class="flex items-center h-14 px-4 text-sm text-gray-700 hover:text-emerald-700 hover:bg-emerald-50 transition">Login</a>
      <?php } ?>
    </div>
  </div>

  <div class="h-[2px] bg-gradient-to-r from-emerald-500 via-emerald-400 to-emerald-600"></div>
</header>

<!-- spacer for fixed header -->
<div class="h-14"></div>
