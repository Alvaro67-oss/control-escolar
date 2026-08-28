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
$inicio = $_GET['inicio'] ?? '';
$fin = $_GET['fin'] ?? '';

$query = "SELECT a.*, al.*, g.nombre_grupo, p.clave AS plantel
FROM asistencias a
INNER JOIN alumno al ON a.alumno_id = al.idAlumno
LEFT JOIN grupo g ON al.idGrupo = g.idGrupo
LEFT JOIN planteles p ON al.idplantel = p.id
WHERE al.idplantel = ?";

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

if ($inicio !== "" && $fin !== "") {
    $query .= " AND a.fecha BETWEEN ? AND ?";
    $params[] = $inicio;
    $params[] = $fin;
    $types .= "ss";
}

$query .= " ORDER BY a.fecha DESC";

$stmt = $conexion->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=reporte_asistencias.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Alumno</th><th>Semestre</th><th>Grupo</th><th>Plantel</th><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr>";

while ($fila = $resultado->fetch_assoc()) {
    $nombre = htmlspecialchars(nombreCompletoAlumno($fila), ENT_QUOTES, 'UTF-8');

    echo "<tr>";
    echo "<td>" . (int) $fila['idAlumno'] . "</td>";
    echo "<td>" . $nombre . "</td>";
    echo "<td>" . (int) $fila['semestre'] . "</td>";
    echo "<td>" . htmlspecialchars($fila['nombre_grupo'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($fila['plantel'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($fila['fecha'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($fila['hora_entrada'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($fila['hora_salida'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($fila['estado'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "</tr>";
}

echo "</table>";
