<?php
require_once 'utils/dbutils.php';

function hasParticipated ($db, $participantid, $surveyid){
    $result = false;
    
    $query = $db->prepare ("SELECT 1 FROM {Responses} WHERE participantid = :pid AND surveyid = :sid");
    $query->bindParam (":pid", $participantid, PDO::PARAM_INT);
    $query->bindParam (":sid", $surveyid, PDO::PARAM_INT);
    $query->execute ();
    if ($query->rowCount () > 0)
        $result = true;
    $query->closeCursor ();
    return $result;
}

function hasCode ($db, $participantid, $surveyid){
    $result = false;
    
    $query = $db->prepare ("SELECT 1 FROM {Participation} WHERE participantid = :pid AND surveyid = :sid");
    $query->bindParam (":pid", $participantid, PDO::PARAM_INT);
    $query->bindParam (":sid", $surveyid, PDO::PARAM_INT);
    $query->execute ();
    if ($query->rowCount () > 0)
        $result = true;
    $query->closeCursor ();
    return $result;
}

function generateKeyPair (){
    $curve_name = 'secp256k1';

// Create a new EC key resource
    return openssl_pkey_new([
        'curve_name' => $curve_name,
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
}

function getSignAlgo (){
    return OPENSSL_ALGO_SHA256;
}
