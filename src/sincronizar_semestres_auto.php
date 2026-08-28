<?php

if (!isset($conexion) || !($conexion instanceof mysqli)) {
    return;
}

require_once __DIR__ . '/generaciones_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$resultadoSync = sincronizarGeneracionesAutomatico($conexion);

if ($resultadoSync['ejecutado']) {
    $_SESSION['generaciones_sync_aviso'] = $resultadoSync['mensaje'];
}
