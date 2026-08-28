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
$fecha = validarFecha($_GET['fecha'] ?? null);
$fechaInicio = validarFecha($_GET['fecha_inicio'] ?? null);
$fechaFin = validarFecha($_GET['fecha_fin'] ?? null);

$modoRango = false;
if ($fechaInicio && $fechaFin) {
    if ($fechaInicio > $fechaFin) {
        [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
    }
    $modoRango = true;
} else {
    $fecha = $fecha ?: date('Y-m-d');
}

$horasRango = range(6, 22);
$labels = array_map(function ($h) {
    return $h . ':00';
}, $horasRango);

function mapaHoras(array $horasRango): array
{
    $mapa = [];
    foreach ($horasRango as $h) {
        $mapa[$h] = 0;
    }
    return $mapa;
}

function consultaTotalDiaria(
    mysqli $conexion,
    int $plantel_id,
    ?string $fecha,
    ?string $fechaInicio,
    ?string $fechaFin
): array {
    $sql = "
        SELECT HOUR(a.hora_entrada) AS hora, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN alumno al ON a.alumno_id = al.idAlumno
        WHERE al.idplantel = ?
          AND al.idEstado IN (1, 3)
          AND a.hora_entrada IS NOT NULL
    ";

    if ($fechaInicio && $fechaFin) {
        $sql .= " AND DATE(a.fecha) BETWEEN ? AND ?";
    } else {
        $sql .= " AND DATE(a.fecha) = ?";
    }

    $sql .= " GROUP BY hora ORDER BY hora";

    $stmt = $conexion->prepare($sql);

    if ($fechaInicio && $fechaFin) {
        $stmt->bind_param('iss', $plantel_id, $fechaInicio, $fechaFin);
    } else {
        $stmt->bind_param('is', $plantel_id, $fecha);
    }

    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function consultaDiaria(
    mysqli $conexion,
    int $plantel_id,
    ?string $fecha,
    ?string $fechaInicio,
    ?string $fechaFin,
    string $campo
): array {
    $sql = "
        SELECT HOUR(a.hora_entrada) AS hora, {$campo} AS clave, COUNT(*) AS total
        FROM asistencias a
        INNER JOIN alumno al ON a.alumno_id = al.idAlumno
        LEFT JOIN grupo g ON al.idGrupo = g.idGrupo
        WHERE al.idplantel = ?
          AND al.idEstado IN (1, 3)
          AND a.hora_entrada IS NOT NULL
    ";

    if ($fechaInicio && $fechaFin) {
        $sql .= " AND DATE(a.fecha) BETWEEN ? AND ?";
    } else {
        $sql .= " AND DATE(a.fecha) = ?";
    }

    $sql .= " GROUP BY hora, clave ORDER BY hora";

    $stmt = $conexion->prepare($sql);

    if ($fechaInicio && $fechaFin) {
        $stmt->bind_param('iss', $plantel_id, $fechaInicio, $fechaFin);
    } else {
        $stmt->bind_param('is', $plantel_id, $fecha);
    }

    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function normalizarGrupo(?string $grupo): ?string
{
    if ($grupo === null || $grupo === '') {
        return null;
    }

    $grupo = strtoupper(trim($grupo));

    return in_array($grupo, ['A', 'B', 'C'], true) ? $grupo : null;
}

$coloresGrupo = [
    'A' => '#00e5ff',
    'B' => '#00ffcc',
    'C' => '#7c4dff',
];

$coloresSemestre = [
    1 => '#00e5ff',
    2 => '#ffc107',
    3 => '#00ffcc',
    4 => '#ff9800',
    5 => '#22d3ee',
    6 => '#ff1744',
];

$totalEntradas = mapaHoras($horasRango);
foreach (consultaTotalDiaria($conexion, $plantel_id, $fecha, $fechaInicio, $fechaFin) as $fila) {
    $hora = (int) $fila['hora'];
    if (isset($totalEntradas[$hora])) {
        $totalEntradas[$hora] = (int) $fila['total'];
    }
}

$filasGrupo = consultaDiaria($conexion, $plantel_id, $fecha, $fechaInicio, $fechaFin, 'g.nombre_grupo');
$filasSemestre = consultaDiaria($conexion, $plantel_id, $fecha, $fechaInicio, $fechaFin, 'al.semestre');

$gruposData = [
    'A' => mapaHoras($horasRango),
    'B' => mapaHoras($horasRango),
    'C' => mapaHoras($horasRango),
];

foreach ($filasGrupo as $fila) {
    $grupo = normalizarGrupo($fila['clave'] ?? null);
    $hora = (int) $fila['hora'];
    if ($grupo && isset($gruposData[$grupo][$hora])) {
        $gruposData[$grupo][$hora] += (int) $fila['total'];
    }
}

$semestresData = [];
for ($s = 1; $s <= 6; $s++) {
    $semestresData[$s] = mapaHoras($horasRango);
}

foreach ($filasSemestre as $fila) {
    $sem = (int) $fila['clave'];
    $hora = (int) $fila['hora'];
    if ($sem >= 1 && $sem <= 6 && isset($semestresData[$sem][$hora])) {
        $semestresData[$sem][$hora] += (int) $fila['total'];
    }
}

$datasets = [
    [
        'label' => 'Total entradas',
        'data' => array_values($totalEntradas),
        'backgroundColor' => '#ffffff',
        'borderColor' => '#ffffff',
        'borderWidth' => 1,
    ],
];

foreach (['A', 'B', 'C'] as $grupo) {
    $datasets[] = [
        'label' => 'Grupo ' . $grupo,
        'data' => array_values($gruposData[$grupo]),
        'backgroundColor' => $coloresGrupo[$grupo],
    ];
}

for ($s = 1; $s <= 6; $s++) {
    $datasets[] = [
        'label' => 'Semestre ' . $s,
        'data' => array_values($semestresData[$s]),
        'backgroundColor' => $coloresSemestre[$s],
    ];
}

$maxValor = max(array_merge(...array_map(static function ($ds) {
    return $ds['data'];
}, $datasets)));
$maxEjeY = max(10, (int) (ceil($maxValor / 5) * 5));

echo json_encode([
    'ok' => true,
    'modo' => $modoRango ? 'rango' : 'dia',
    'fecha' => $fecha,
    'fecha_inicio' => $fechaInicio,
    'fecha_fin' => $fechaFin,
    'labels' => $labels,
    'datasets' => $datasets,
    'total_entradas' => array_sum($totalEntradas),
    'max_eje_y' => $maxEjeY,
]);
