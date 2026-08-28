<?php

session_start();

include 'conexion.php';
require_once 'generaciones_helper.php';
require_once 'estados_alumno_helper.php';
require_once 'excel_import_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['plantel_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Sesion no valida']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Metodo no permitido']);
    exit();
}

ensureEstadosAlumno($conexion);

$idplantel = (int) $_SESSION['plantel_id'];
$idGeneracionDefault = (int) ($_POST['idGeneracion'] ?? 0);
$idEstadoDefault = (int) ($_POST['idEstado'] ?? ESTADO_REGISTRADO);

if ($idGeneracionDefault <= 0) {
    echo json_encode(['ok' => false, 'mensaje' => 'Selecciona una generacion para la importacion']);
    exit();
}

if (!obtenerGeneracionPorId($conexion, $idGeneracionDefault)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Generacion no valida']);
    exit();
}

if (!validarEstadoCrud($idEstadoDefault) && $idEstadoDefault !== ESTADO_BAJA) {
    echo json_encode(['ok' => false, 'mensaje' => 'Estado default no valido']);
    exit();
}

if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Selecciona un archivo Excel (.xlsx) o CSV']);
    exit();
}

$archivo = $_FILES['archivo'];
$nombreOriginal = (string) ($archivo['name'] ?? '');
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
$tamanoMax = 5 * 1024 * 1024;

if ($archivo['size'] > $tamanoMax) {
    echo json_encode(['ok' => false, 'mensaje' => 'El archivo supera el limite de 5 MB']);
    exit();
}

$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_alumnos_' . uniqid('', true) . '.' . $extension;
if (!move_uploaded_file($archivo['tmp_name'], $tempPath)) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo procesar el archivo subido']);
    exit();
}

$lectura = leerArchivoImport($tempPath, $extension);
@unlink($tempPath);

if (empty($lectura['ok'])) {
    echo json_encode(['ok' => false, 'mensaje' => $lectura['error'] ?? 'No se pudo leer el archivo']);
    exit();
}

if (empty($lectura['rows'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'El archivo no contiene alumnos para importar']);
    exit();
}

$resultado = importarAlumnosDesdeFilas(
    $conexion,
    $lectura['rows'],
    $idplantel,
    $idGeneracionDefault,
    $idEstadoDefault
);

$errores = $resultado['errores'];
$detalleErrores = array_slice($errores, 0, 8);
$mensaje = 'Importacion completada. '
    . 'Insertados: ' . (int) $resultado['insertados']
    . ', Actualizados: ' . (int) $resultado['actualizados']
    . ', Omitidos: ' . (int) $resultado['omitidos']
    . ', Errores: ' . count($errores) . '.';

if ($detalleErrores) {
    $mensaje .= ' ' . implode(' | ', $detalleErrores);
}

echo json_encode([
    'ok' => count($errores) === 0 || ($resultado['insertados'] + $resultado['actualizados']) > 0,
    'mensaje' => $mensaje,
    'resumen' => [
        'total_filas' => (int) $resultado['total_filas'],
        'insertados' => (int) $resultado['insertados'],
        'actualizados' => (int) $resultado['actualizados'],
        'omitidos' => (int) $resultado['omitidos'],
        'errores' => count($errores),
    ],
    'detalle_errores' => $detalleErrores,
]);
