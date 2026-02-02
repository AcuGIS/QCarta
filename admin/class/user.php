<?php
    class user_Class extends table_Class
    {

				function __construct($dbconn, $owner_id) {
					parent::__construct($dbconn, $owner_id, 'user');
				}
				
				public static function randomPassword() {
				    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
				    $pass = array(); //remember to declare $pass as an array
				    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
				    for ($i = 0; $i < 10; $i++) {
				        $n = rand(0, $alphaLength);
				        $pass[] = $alphabet[$n];
				    }
				    return implode($pass); //turn the array into a string
				}
				
				public static function uniqueName($username){
				    $uid = 1;
					while(posix_getpwnam($username)){
					   $username .= $uid;
					   $uid = $uid + 1;
					}
					return $username;
				}


        function create($data, $isHashed = false)
        {		
						 if(!$isHashed){
						 	$data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
						 }
						
             $sql = "INSERT INTO PUBLIC." .$this->table_name."
             (name,email,password,ftp_user,pg_password,accesslevel,owner_id) "."VALUES('".
             $this->cleanData($data['name'])."','".
             $this->cleanData($data['email'])."','".
             									$data['password']."','".
						 $this->cleanData($data['ftp_user'])."','".
						 $this->cleanData($data['pg_password'])."','".
             $this->cleanData($data['accesslevel'])."',".
						 $this->owner_id.") RETURNING id";
						 
						 $result = pg_query($this->dbconn, $sql);
						 if(!$result){
		 					return 0;
		 				 }
            $row = pg_fetch_object($result);
						pg_free_result($result);

            if($row) {
							$this->create_access($row->id, $data['group_id']);
              return $row->id;
            }
            return 0;
        }

        function create_sso($db_obj, $email, $name){
            $post = ['name' => $name, 'email' => $email, 'password' => $this->randomPassword(),
                'accesslevel' => 'User', 'group_id' => [1] ];
            
            $email_user = explode('@', $post['email'])[0];
			$post['ftp_user'] = $this->uniqueName($email_user);
			$post['pg_password'] = $this->randomPassword();
								
	        $newId = $this->create($post);
			if($newId > 0){
			    $usr_row = $this->getByEmail($post['email']);
			    $db_obj->create_user($post['ftp_user'], $post['pg_password']);
				return $usr_row;
			}else{
			    return false;
			}
        }
        
				function loginCheck($pwd, $email){

	        $sql ="select * from public.user where email = '".$this->cleanData($email)."'";
	        $result = pg_query($this->dbconn,$sql);
					if(!$result || pg_num_rows($result) == 0){
 					 return null;
 				 }
				 $row = pg_fetch_object($result);
					pg_free_result($result);
					
					if (password_verify($pwd, $row->password)) {
						return $row;
					}
	        return null;
				}
				
				function secretCheck($secret_key){

	        $sql ="select * from public.user where secret_key='".$this->cleanData($secret_key)."'";
	        $result = pg_query($this->dbconn,$sql);
					if(!$result || pg_num_rows($result) == 0){
 					 return null;
 				 	}
					$row = pg_fetch_object($result);
					pg_free_result($result);
					
	        return $row;
				}

				function getByEmail($email){

            $sql ="select * from public.".$this->table_name." where email='".$email."'";
            $result = pg_query($this->dbconn, $sql);
						if(!$result){
							return false;
						}
						
						$row = pg_fetch_object($result);
						pg_free_result($result);
            return $row;
        }
			
				function secret_reset($id){

            $sql ="update public.".$this->table_name." set secret_key = gen_random_uuid() where id='".$id."'";
            $result = pg_query($this->dbconn, $sql);
						if(!$result){
							return false;
						}
						$rv = (pg_affected_rows($result) > 0);
						pg_free_result($result);
            return $rv;
        }

       function update($data=array())
       {

          $id = intval($data['id']);
					$result = $this->getById($id);
				 	$row = pg_fetch_object($result);
					pg_free_result($result);
					
          $sql = "update public.user set name='".$this->cleanData($data['name'])."'";
          $sql .= ", email='".$this->cleanData($data['email'])."'";
					
					if($row->password != $data['password']){	# if password is changed
						$hashpassword = password_hash($data['password'], PASSWORD_DEFAULT);
          	$sql .= ", password='".$hashpassword."'";
					}
          $sql .= ", accesslevel='".$this->cleanData($data['accesslevel']).
								 	"' where id = '".$id."'";
					
					$result = pg_query($this->dbconn, $sql);
					if(!$result){
						return 0;
					}
					
					$rv = pg_affected_rows($result);
					pg_free_result($result);
					
					if($rv > 0){
						$this->drop_access($data['id']);
						$this->create_access($data['id'], $data['group_id']);
					}

					return $rv;
       }
			 
			 function admin_self_own($id){
				 $sql ="update public.".$this->table_name." set owner_id = ".$id." where id='".$id."'";
				 $result = pg_query($this->dbconn, $sql);
				 if(!$result){
					 return false;
				 }
				 pg_free_result($result);
				 return true;
			 }
	}
