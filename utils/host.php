<?php

function getURL (){
    include 'config/config.php';
    $server = rtrim ($_SERVER['HTTP_HOST'], "/");
    if (empty ($proxy_port)){
         $server .= ":" . $proxy_port . "/";
    }
    else{
         $server .= "/";
    }
    return (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
         $server . $proxy_path;
}
