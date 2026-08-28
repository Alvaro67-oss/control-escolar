<?php
session_start();
include("conexion.php");
require_once 'estados_alumno_helper.php';

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

$idplantel = (int) $_SESSION['plantel_id'];
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'mensaje' => 'ID no valido']);
    exit();
}

$verificar = $conexion->prepare(
    "SELECT idAlumno, nombre, apellido1, apellido2, idEstado
     FROM alumno
     WHERE idAlumno = ? AND idplantel = ?"
);
$verificar->bind_param("ii", $id, $idplantel);
$verificar->execute();
$alumno = $verificar->get_result()->fetch_assoc();

if (!$alumno) {
    echo json_encode(['ok' => false, 'mensaje' => 'Alumno no encontrado']);
    exit();
}

if ((int) $alumno['idEstado'] !== 1) {
    echo json_encode(['ok' => false, 'mensaje' => 'Este alumno ya fue dado de baja']);
    exit();
}

$nombre = nombreCompletoAlumno($alumno);

$baja = $conexion->prepare(
    "UPDATE alumno SET idEstado = 2 WHERE idAlumno = ? AND idplantel = ? AND idEstado IN (1, 3)"
);
$baja->bind_param("ii", $id, $idplantel);

if ($baja->execute() && $baja->affected_rows > 0) {
    echo json_encode([
        'ok' => true,
        'mensaje' => 'Alumno eliminado. Sus registros de asistencia se conservaron.',
        'nombre' => $nombre,
    ]);
    exit();
}

echo json_encode(['ok' => false, 'mensaje' => 'No se pudo eliminar el alumno']);
