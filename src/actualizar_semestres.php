<?php

session_start();

include("conexion.php");

require_once("generaciones_helper.php");



header('Content-Type: application/json; charset=utf-8');



if (!isset($_SESSION['plantel_id'])) {

    http_response_code(403);

    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']);

    exit();

}



$resultado = sincronizarGeneracionesAutomatico($conexion, true);



echo json_encode([

    'ok' => true,

    'mensaje' => $resultado['mensaje'],

    'generaciones_asignadas' => $resultado['generaciones_asignadas'],

    'anio' => $resultado['anio'],

]);


