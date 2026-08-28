<?php
session_start();
include("conexion.php");
require_once 'estados_alumno_helper.php';

if (!isset($_SESSION['plantel_id'])) {
    header("Location: login.php");
    exit();
}

$plantel_id = (int) $_SESSION['plantel_id'];
$grupo = $_GET['grupo'] ?? '';
$semestre = $_GET['semestre'] ?? '';
$fecha = $_GET['fecha'] ?? '';
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';

$query = "
SELECT
    a.fecha,
    a.hora_entrada,
    a.hora_salida,
    al.nombre,
    al.apellido1,
    al.apellido2,
    g.nombre_grupo,
    al.semestre
FROM asistencias a
JOIN alumno al ON a.alumno_id = al.idAlumno
LEFT JOIN grupo g ON al.idGrupo = g.idGrupo
WHERE al.idplantel = ?
";

$params = [$plantel_id];
$types = "i";

if ($grupo !== "") {
    $query .= " AND g.nombre_grupo = ?";
    $params[] = $grupo;
    $types .= "s";
}

if ($semestre !== "") {
    $query .= " AND al.semestre = ?";
    $params[] = (int) $semestre;
    $types .= "i";
}

if ($fechaInicio !== "" && $fechaFin !== "") {
    if ($fechaInicio > $fechaFin) {
        [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
    }
    $query .= " AND DATE(a.fecha) BETWEEN ? AND ?";
    $params[] = $fechaInicio;
    $params[] = $fechaFin;
    $types .= "ss";
} elseif ($fecha !== "") {
    $query .= " AND DATE(a.fecha) = ?";
    $params[] = $fecha;
    $types .= "s";
}

$query .= " ORDER BY a.fecha DESC";

$stmt = $conexion->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=reporte_asistencias.xls");

echo "<table border='1'>";
echo "<tr><th>Fecha</th><th>Alumno</th><th>Grupo</th><th>Semestre</th><th>Entrada</th><th>Salida</th></tr>";

while ($fila = $resultado->fetch_assoc()) {
    $nombre = htmlspecialchars(nombreCompletoAlumno($fila), ENT_QUOTES, 'UTF-8');
    $grupoNom = htmlspecialchars($fila['nombre_grupo'] ?? '', ENT_QUOTES, 'UTF-8');

    echo "<tr>";
    echo "<td>" . htmlspecialchars($fila['fecha'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . $nombre . "</td>";
    echo "<td>" . $grupoNom . "</td>";
    echo "<td>" . (int) $fila['semestre'] . "</td>";
    echo "<td>" . htmlspecialchars($fila['hora_entrada'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($fila['hora_salida'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "</tr>";
}

echo "</table>";
