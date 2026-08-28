<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['idplantel']) && !isset($_SESSION['plantel_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesion no valida']);
    exit();
}

$idplantel = (int) ($_SESSION['idplantel'] ?? $_SESSION['plantel_id']);
$num_cuenta = (int) ($_POST['num_cuenta'] ?? 0);

$stmt = $conexion->prepare("SELECT idAlumno FROM alumno WHERE idAlumno = ? AND idplantel = ? LIMIT 1");
$stmt->bind_param("ii", $num_cuenta, $idplantel);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Alumno encontrado']);
} else {
    echo json_encode(['status' => 'not_found', 'message' => 'Alumno no encontrado. Registre primero.']);
}
