<?php
session_start();
include 'conexion.php';
require_once '../generaciones_helper.php';
require_once '../estados_alumno_helper.php';

if (!isset($_SESSION['idplantel']) && !isset($_SESSION['plantel_id'])) {
    header('Location: login.php');
    exit();
}

$idplantel = (int) ($_SESSION['idplantel'] ?? $_SESSION['plantel_id']);
$id = (int) ($_POST['num_cuenta'] ?? 0);
$nombreCompleto = trim($_POST['nombre'] ?? '');
$idGeneracion = (int) ($_POST['idGeneracion'] ?? 0);
$gru = trim($_POST['grupo'] ?? '');
$semestre = (int) ($_POST['semestre'] ?? 0);

if ($id <= 0 || $nombreCompleto === '' || $idGeneracion <= 0 || $gru === '' || $semestre < 1 || $semestre > 6) {
    die('Datos incompletos');
}

$generacion = obtenerGeneracionPorId($conexion, $idGeneracion);
if (!$generacion) {
    die('Generacion no valida');
}

$stmtGrupo = $conexion->prepare("SELECT idGrupo FROM grupo WHERE nombre_grupo = ?");
$stmtGrupo->bind_param("s", $gru);
$stmtGrupo->execute();
$grupoRow = $stmtGrupo->get_result()->fetch_assoc();

if (!$grupoRow) {
    die('Grupo no valido. Use A, B o C.');
}

$idGrupo = (int) $grupoRow['idGrupo'];

$verificar = $conexion->prepare("SELECT idAlumno FROM alumno WHERE idAlumno = ? AND idplantel = ?");
$verificar->bind_param("ii", $id, $idplantel);
$verificar->execute();

if ($verificar->get_result()->num_rows > 0) {
    die('El alumno ya existe en este plantel');
}

$insertar = $conexion->prepare("INSERT INTO alumno
    (idAlumno, nombre, apellido1, apellido2, semestre, idGrupo, idplantel, idGeneracion, idEstado)
    VALUES (?, ?, '', '', ?, ?, ?, ?, ?)");
$idEstado = ESTADO_PREREGISTRADO;
$insertar->bind_param("isiiiii", $id, $nombreCompleto, $semestre, $idGrupo, $idplantel, $idGeneracion, $idEstado);

if ($insertar->execute()) {
    header("Location: panelqr.php");
    exit();
}

echo "Error: " . htmlspecialchars($conexion->error, ENT_QUOTES, 'UTF-8');
