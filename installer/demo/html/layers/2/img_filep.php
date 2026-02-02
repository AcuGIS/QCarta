<?php
include('../../admin/incl/index_prefix.php');

const IMG_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$f = str_replace('..', '', $_GET['f']);
$fpath = '/var/www/data/stores/2/'.$f;

if(!empty($_GET['f']) && is_file($fpath)){
    $ext = pathinfo($f, PATHINFO_EXTENSION);
    if(in_array(strtolower($ext),IMG_EXTENSIONS)){
        header('Content-Type: '.mime_content_type($fpath));
        //header('Content-disposition: attachment; filename="'.$f.'"');
        readfile($fpath);
    }else{
        header("HTTP/1.1 414 Unsupported Media Type");
    }
}else{
	header("HTTP/1.1 400 Bad Request");
}
?>
