<?php

function getURL (){
    include 'config/config.php';
    return (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
        $_SERVER['HTTP_HOST'] . $proxy_path;
}