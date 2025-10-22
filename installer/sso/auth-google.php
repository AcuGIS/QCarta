<?php
session_start();
require('admin/incl/const.php');
require('admin/class/database.php');
require('admin/class/table.php');
require('admin/class/user.php');

require 'vendor/autoload.php';


use League\OAuth2\Client\Provider\GenericProvider;

$provider = new GenericProvider([
    'clientId'                => GOOGLE_CLIENT_ID,
    'clientSecret'            => GOOGLE_CLIENT_SECRET,
    'redirectUri'             => 'https://yourdomain.com/qcarta/auth-google.php',
    'urlAuthorize'            => 'https://accounts.google.com/o/oauth2/v2/auth',
    'urlAccessToken'          => 'https://oauth2.googleapis.com/token',
    'urlResourceOwnerDetails' => 'https://www.googleapis.com/oauth2/v3/userinfo',
    'scopes'                  => 'https://www.googleapis.com/auth/userinfo.profile openid https://www.googleapis.com/auth/userinfo.email',
]);

if (!isset($_GET['code'])) {
    $authUrl = $provider->getAuthorizationUrl();
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

        $request = $provider->getAuthenticatedRequest(
            'GET',
            'https://www.googleapis.com/oauth2/v3/userinfo',
            $token
        );

        $response = $provider->getResponse($request);
        $userData = json_decode($response->getBody()->getContents(), true);
        
        $database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_SCMA);
		$usr_obj = new user_Class($database->getConn(), SUPER_ADMIN_ID);
				
        $usr_row = $usr_obj->getByEmail($userData['email']);
        if($usr_row !== false){
            $_SESSION[SESS_USR_KEY] = $usr_row;
            header('Location: viewer.php');
            exit(0);
        }else if(DISABLE_OAUTH_USER_CREATION){
            header('Location: login.php?err='.urlencode('Error: User creation is disabled'));
            exit(0);
        }else{
            //TODO: create special group SSO ?
            $usr_row = $usr_obj->create_sso($database, $userData['email'], $userData['name']);
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
