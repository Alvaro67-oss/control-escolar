<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['plantel_id']) && !isset($_SESSION['idplantel'])) {
    die("Acceso no autorizado");
}

$plantel = (int) ($_SESSION['plantel_id'] ?? $_SESSION['idplantel']);
$mes = (int) date("n");
$semestres = ($mes >= 8 || $mes === 1) ? [1, 3, 5] : [2, 4, 6];

function obtener($conexion, $sem, $plantel)
{
    $dias = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
    $data = [];

    foreach ($dias as $dia) {
        $sql = "SELECT SUM(a.estado='Falta') as faltas
        FROM asistencias a
        JOIN alumno al ON a.alumno_id = al.idAlumno
        WHERE al.semestre = ?
        AND al.idplantel = ?
        AND DAYNAME(a.fecha) = ?";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iis", $sem, $plantel, $dia);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $data[] = (int) ($row['faltas'] ?? 0);
    }

    return $data;
}

echo json_encode([
    "sem1" => obtener($conexion, $semestres[0], $plantel),
    "sem2" => obtener($conexion, $semestres[1], $plantel),
    "sem3" => obtener($conexion, $semestres[2], $plantel),
]);
