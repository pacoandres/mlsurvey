<?php
require_once 'include/mlpdo.php';
function dbConn (){
    include 'config/config.php';
    try {

        $dbconn = new MLPDO ('mysql:dbname=' . $db_name . ';host=' . $db_host . ';port=' . $db_port,
        $db_user, $db_pass, array(MLPDO::ATTR_ERRMODE => MLPDO::ERRMODE_EXCEPTION));
    }
    catch (PDOException $e){
        throw $e;
        return null;
    }

    $dbconn->setPrefix ($db_prefix);
    return $dbconn;
}
