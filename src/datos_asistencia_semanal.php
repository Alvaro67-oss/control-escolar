<?php
session_start();
include("conexion.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['plantel_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit();
}

function validarFecha(?string $fecha): ?string
{
    if ($fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return $fecha;
    }

    return null;
}

$plantel_id = (int) $_SESSION['plantel_id'];
$fechaInicio = validarFecha($_GET['fecha_inicio'] ?? null);
$fechaFin = validarFecha($_GET['fecha_fin'] ?? null);

if (!$fechaInicio || !$fechaFin) {
    $hoy = new DateTime();
    $dia = (int) $hoy->format('N');
    $hoy->modify('-' . ($dia - 1) . ' days');
    $fechaInicio = $hoy->format('Y-m-d');
    $hoy->modify('+4 days');
    $fechaFin = $hoy->format('Y-m-d');
} elseif ($fechaInicio > $fechaFin) {
    [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
}

$sql = "
SELECT
    DAYOFWEEK(a.fecha) AS dia,
    SUM(a.estado = 'Asistencia') AS asistencias,
    SUM(a.estado = 'Falta') AS faltas
FROM asistencias a
INNER JOIN alumno al ON a.alumno_id = al.idAlumno
WHERE al.idplantel = ?
  AND al.idEstado IN (1, 3)
  AND DATE(a.fecha) BETWEEN ? AND ?
  AND DAYOFWEEK(a.fecha) BETWEEN 2 AND 6
GROUP BY dia
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param('iss', $plantel_id, $fechaInicio, $fechaFin);
$stmt->execute();
$result = $stmt->get_result();

$asistencias = array_fill(0, 5, 0);
$faltas = array_fill(0, 5, 0);

while ($row = $result->fetch_assoc()) {
    $index = (int) $row['dia'] - 2;
    if ($index >= 0 && $index < 5) {
        $asistencias[$index] = (int) $row['asistencias'];
        $faltas[$index] = (int) $row['faltas'];
    }
}

echo json_encode([
    'ok' => true,
    'fecha_inicio' => $fechaInicio,
    'fecha_fin' => $fechaFin,
    'labels' => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'],
    'asistencias' => $asistencias,
    'faltas' => $faltas,
]);
