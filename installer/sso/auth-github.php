<?php
session_start();
require('admin/incl/const.php');
require('admin/class/database.php');
require('admin/class/table.php');
require('admin/class/user.php');

require 'vendor/autoload.php';

$provider = new League\OAuth2\Client\Provider\Github([
    'clientId'          => GITHUB_CLIENT_ID,
    'clientSecret'      => GITHUB_CLIENT_SECRET,
    'redirectUri'       => 'https://yourdomain.com/qcarta/auth-github.php',
]);

if (!isset($_GET['code'])) {
    $authUrl = $provider->getAuthorizationUrl(['scope' => ['user:email']]);
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    header('Location: login.php?err='.urlencode('Error: Invalid OAuth 2.0 state'));
    exit(0);

} else {
    try {
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        $userData = $provider->getResourceOwner($token);
        
        $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		$usr_obj = new user_Class($database->getConn(), SUPER_ADMIN_ID);
				
        $usr_row = $usr_obj->getByEmail($userData->getEmail());
        if($usr_row !== false){
            $_SESSION[SESS_USR_KEY] = $usr_row;
            header('Location: viewer.php');
            exit(0);
        }else if(DISABLE_OAUTH_USER_CREATION){
            header('Location: login.php?err='.urlencode('Error: User creation is disabled'));
            exit(0);
        }else{
            //TODO: create special group SSO ?
            $usr_row = $usr_obj->create_sso($database, $userData->getEmail(), $userData->getName());
			if($usr_row !== false){			
				$_SESSION[SESS_USR_KEY] = $usr_row;
                header('Location: viewer.php');
			}else{
			    header('Location: login.php?err='.urlencode('Qcarta failed to create user with email: '.$userData['mail']));
                exit(0);
			}
        }

    } catch (Exception $e) {
        header('Location: login.php?err='.urlencode('Microsoft Sign-in failed: ' . $e->getMessage()));
        exit(0);
    }
}
