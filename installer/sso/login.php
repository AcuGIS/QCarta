<?php
    require('admin/incl/const.php');

    session_start(['read_and_close' => true]);
    if(isset($_SESSION[SESS_USR_KEY])) {
        $settings = json_decode(file_get_contents(DATA_DIR.'/settings.json'), true);
		header("Location: ../../".$settings['login_redirect']);
        exit(0);
    }
?>
<!DOCTYPE html>
<html>
<head>
<title>QCarta</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<style>
.separator {
  display: flex;
  align-items: center;
  text-align: center;
}

.separator::before,
.separator::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #000;
}

.separator:not(:empty)::before {
  margin-right: .25em;
}

.separator:not(:empty)::after {
  margin-left: .25em;
}
.auth-icon img {
    width: 48px;
    height: 48px;
    margin: auto;    
    display: block;
}
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</head>
<body class="min-h-screen bg-gray-100 font-sans">
    <!-- Map Search Modal (moved outside sidebar for stacking context) -->
    

    <div class="fixed top-0 left-0 right-0 h-[75px] bg-gree-500 text-white flex justify-between items-center px-5 z-50" style="background-color:#2e8b57!important; border-bottom: 1px solid black;">
        <div class="text-4xl font-bold" style="font-family: Century Gothic, 'Trebuchet MS', Tahoma, Verdana; padding-top: 2px!important"><img src="assets/images/qcarta-logo.png" alt="" 
style="display:inline-block; padding-right:5px; font-family: Century Gothic, 'Trebuchet MS', Tahoma, Verdana; font-size:2.75rem!important;"></div>

        <div class="space-x-5">
<div class="space-x-5">
                            <a href="index.php" class="text-white hover:text-gray-200 text-base">Home</a>
                                </div>
    </div>

            
        </div>
    </div>

    <div class="flex pt-20">
        <!-- Search Sidebar -->
        

            

           

            <!-- Search Filters -->
            
        </div>

        <!-- Main Content -->
        <div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-sm">
    <img class="mx-auto h-20 w-auto" src="assets/images/qcarta-login.png" alt="QCarta">
    <h2 class="mt-10 text-center text-2xl/9 font-bold tracking-tight text-gray-900">Sign in to QCarta</h2>
  </div>

  <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">

<?php if(isset($_GET['error'])) { ?>
                <div class="error-message">Invalid username or password</div>
            <?php } ?>


    <form method="post" action="admin/action/login.php" class="space-y-6">

<?php if(!empty($_GET['err'])){ ?>
<div class="alert alert-danger mb-4" role="alert"><?=$_GET['err']?></div>
<?php } else if(!empty($_GET['msg'])){ ?>
<div class="alert alert-success mb-4" role="alert"><?=$_GET['msg']?></div>
<?php } ?>


      <div>
        <label for="email" class="block text-sm/6 font-medium text-gray-900">Email address</label>
        <div class="mt-2">
           <!--<input type="email" class="form-control" name="email" id="email" placeholder="name@example.com" required>-->

          <input type="email" name="email" id="email" autocomplete="email" required class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between">
          <label for="password" class="block text-sm/6 font-medium text-gray-900">Password</label>
          <div class="text-sm">
            <!--<a href="#" class="font-semibold text-indigo-600 hover:text-indigo-500">Forgot password?</a>-->
          </div>
        </div>
        <div class="mt-2">
          <input type="password" name="pwd" id="pwd" autocomplete="current-password" required class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6">
        </div>
      </div>

      <div>
        <button type="submit" name="submit" value="1" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Sign in</button>
        
        <div class="row">
            <div class="separator">or</div>
        </div>
        
        <div class="row text-center">
            <?php if(!empty(GITHUB_CLIENT_ID)) { ?>
            <div class="col login-option">
                <a class="auth-icon" href="auth-github.php">
                    <img src="assets/images/auth-github.png" alt="Sign in with GitHub" title="Sign in with GitHub"/>
                </a>
            </div>
            <?php } ?>
            
            <?php if(!empty(MICROSOFT_CLIENT_ID)) { ?>
            <div class="col login-option">
                <a class="auth-icon" href="auth-microsoft.php">
                    <img src="assets/images/auth-microsoft.png" alt="Sign in with Microsoft" title="Sign in with Microsoft"/>
                </a>
            </div>
            <?php } ?>
            
            <?php if(!empty(GOOGLE_CLIENT_ID)) { ?>
            <div class="col login-option">
                    <a class="auth-icon" href="auth-google.php">
                        <img src="assets/images/auth-google.png" alt="Sign in with Google" title="Sign in with Google"/>
                    </a>
            </div>
            <?php } ?>
      </div>


      </div>
    </form>

    <p class="mt-10 text-center text-sm/6 text-gray-500">
      QCarta by 
      <a href="htps://www.acugis.com" target="_blank" class="font-semibold text-indigo-600 hover:text-indigo-500">AcuGIS</a>
    </p>
  </div>
</div>    </div>
</body>
</html>
