<?php
date_default_timezone_set('America/Mexico_City');

$db_host = getenv('DB_HOST') ?: 'mysql';
$db_user = getenv('DB_USER') ?: 'appuser';
$db_pass = getenv('DB_PASSWORD') ?: '1234';
$db_name = getenv('DB_NAME') ?: 'control_escolar';

$conexion = new mysqli($db_host, $db_user, $db_pass, $db_name);

if($conexion->connect_error){
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8");
?>