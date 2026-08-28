<?php

function obtenerGeneracionPorId(mysqli $conexion, int $idGeneracion): ?array
{
    $stmt = $conexion->prepare(
        'SELECT idGeneracion, nombre_generacion, fecha_inicio, fecha_fin
         FROM generaciones WHERE idGeneracion = ?'
    );
    $stmt->bind_param('i', $idGeneracion);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function obtenerGeneracionActiva(mysqli $conexion, ?int $anio = null): ?array
{
    $anio = $anio ?? (int) date('Y');
    $stmt = $conexion->prepare(
        'SELECT idGeneracion, nombre_generacion, fecha_inicio, fecha_fin
         FROM generaciones
         WHERE ? BETWEEN fecha_inicio AND fecha_fin
         ORDER BY idGeneracion DESC
         LIMIT 1'
    );
    $stmt->bind_param('i', $anio);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        return $row;
    }

    $res = $conexion->query(
        'SELECT idGeneracion, nombre_generacion, fecha_inicio, fecha_fin
         FROM generaciones ORDER BY idGeneracion DESC LIMIT 1'
    );

    return $res ? $res->fetch_assoc() : null;
}

function anioDentroDeGeneracion(array $generacion, ?int $anio = null): bool
{
    $anio = $anio ?? (int) date('Y');
    $inicio = (int) $generacion['fecha_inicio'];
    $fin = (int) $generacion['fecha_fin'];

    return $anio >= $inicio && $anio <= $fin;
}

function etiquetaGeneracion(array $generacion): string
{
    return $generacion['nombre_generacion']
        . ' (' . (int) $generacion['fecha_inicio'] . '-' . (int) $generacion['fecha_fin'] . ')';
}

function ensureConfigSistema(mysqli $conexion): void
{
    $conexion->query(
        "CREATE TABLE IF NOT EXISTS config_sistema (
            clave VARCHAR(50) NOT NULL PRIMARY KEY,
            valor VARCHAR(255) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function obtenerConfig(mysqli $conexion, string $clave): string
{
    ensureConfigSistema($conexion);
    $stmt = $conexion->prepare('SELECT valor FROM config_sistema WHERE clave = ?');
    $stmt->bind_param('s', $clave);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row['valor'] ?? '';
}

function guardarConfig(mysqli $conexion, string $clave, string $valor): void
{
    ensureConfigSistema($conexion);
    $stmt = $conexion->prepare(
        'INSERT INTO config_sistema (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );
    $stmt->bind_param('ss', $clave, $valor);
    $stmt->execute();
}

function asignarGeneracionFaltante(mysqli $conexion): int
{
    $activa = obtenerGeneracionActiva($conexion);
    if (!$activa) {
        return 0;
    }

    $id = (int) $activa['idGeneracion'];
    $stmt = $conexion->prepare(
        'UPDATE alumno SET idGeneracion = ?
         WHERE idEstado IN (1, 3) AND (idGeneracion IS NULL OR idGeneracion = 0)'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();

    return $stmt->affected_rows;
}

function sincronizarGeneracionesAutomatico(mysqli $conexion, bool $forzar = false): array
{
    $anio = (int) date('Y');
    $asignados = asignarGeneracionFaltante($conexion);

    if ($asignados > 0 || $forzar) {
        guardarConfig($conexion, 'ultima_sincronizacion_generaciones', (string) $anio);
    }

    if ($asignados === 0 && !$forzar) {
        return [
            'ejecutado' => false,
            'anio' => $anio,
            'generaciones_asignadas' => 0,
            'mensaje' => 'Todos los alumnos ya tienen generacion asignada.',
        ];
    }

    return [
        'ejecutado' => true,
        'anio' => $anio,
        'generaciones_asignadas' => $asignados,
        'mensaje' => $asignados > 0
            ? "Generacion asignada a {$asignados} alumno(s) segun el anio {$anio}."
            : 'Revision de generaciones completada.',
    ];
}

/** @deprecated Usar sincronizarGeneracionesAutomatico */
function sincronizarSemestresAutomatico(mysqli $conexion, bool $forzar = false): array
{
    return sincronizarGeneracionesAutomatico($conexion, $forzar);
}
