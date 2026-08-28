<?php
/**
 * Asigna generacion activa a alumnos sin generacion.
 * Ejemplo: C:\xampp\php\php.exe C:\xampp\htdocs\control\control\cron_actualizar_semestres.php
 */
chdir(__DIR__);

require_once 'conexion.php';
require_once 'generaciones_helper.php';

$resultado = sincronizarGeneracionesAutomatico($conexion, false);

if (php_sapi_name() === 'cli') {
    echo $resultado['mensaje'] . PHP_EOL;
    exit($resultado['ejecutado'] ? 0 : 0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
