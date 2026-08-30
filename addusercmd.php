<?php
require_once 'utils/user.php';

if ($argc < 3){
    echo "Sintaxis: addusercmd.php usuario clave\n";
    return 1;
}
$user = $argv[1];
$pass = $argv[2];

try {
  echo "creating admin {$user}\n";
  adduser ($user, $pass, 'A');
} catch (Exception $e) {
  echo $e;
  return 1;
}
echo "Usuario creado con éxito\n";
return 0;