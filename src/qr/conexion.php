<?php
$host = getenv('DB_HOST') ?: 'mysql';
$user = getenv('DB_USER') ?: 'appuser';
$pass = getenv('DB_PASSWORD') ?: '1234';
$db   = getenv('DB_NAME') ?: 'control_escolar';

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    header('Content-Type: application/json');
    die(json_encode(["status" => "error", "message" => "Fallo de conexion: " . $conexion->connect_error]));
}

$conexion->set_charset("utf8");
