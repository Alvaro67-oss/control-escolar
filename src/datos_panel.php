<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['plantel_id']) && !isset($_SESSION['idplantel'])) {
    exit();
}

$plantel_id = (int) ($_SESSION['plantel_id'] ?? $_SESSION['idplantel']);

$dias = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
$asistencias = [];
$faltas = [];

foreach($dias as $dia){
    $sql = "SELECT 
        SUM(a.estado='Asistencia') as asistencias,
        SUM(a.estado='Falta') as faltas
        FROM asistencias a
        JOIN alumno al ON a.alumno_id = al.idAlumno
        WHERE DAYNAME(a.fecha)=?
        AND al.idplantel = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $dia, $plantel_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $asistencias[] = (int)($res['asistencias'] ?? 0);
    $faltas[] = (int)($res['faltas'] ?? 0);
}

function faltasSem($conexion,$sem,$plantel_id){
    $dias = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
    $data = [];

    foreach($dias as $dia){
        $sql = "SELECT SUM(a.estado='Falta') as faltas
        FROM asistencias a
        JOIN alumno al ON a.alumno_id = al.idAlumno
        WHERE al.semestre = ?
        AND al.idplantel = ?
        AND DAYNAME(a.fecha) = ?";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("iis",$sem,$plantel_id,$dia);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        $data[] = (int)($res['faltas'] ?? 0);
    }

    return $data;
}

$mes = date("n");
$semestres = ($mes >= 8 || $mes == 1) ? [1,3,5] : [2,4,6];

echo json_encode([
    "asistencias"=>$asistencias,
    "faltas"=>$faltas,
    "sem1"=>faltasSem($conexion,$semestres[0],$plantel_id),
    "sem2"=>faltasSem($conexion,$semestres[1],$plantel_id),
    "sem3"=>faltasSem($conexion,$semestres[2],$plantel_id)
]);