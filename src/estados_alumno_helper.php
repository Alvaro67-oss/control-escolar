<?php

const ESTADO_REGISTRADO = 1;
const ESTADO_BAJA = 2;
const ESTADO_PREREGISTRADO = 3;
const ESTADO_EGRESADO = 4;

function estadosAlumnoCrud(): array
{
    return [
        ESTADO_REGISTRADO => 'Registrado',
        ESTADO_PREREGISTRADO => 'Preregistrado',
        ESTADO_EGRESADO => 'Egresado',
        ESTADO_BAJA => 'Baja',
    ];
}

function estadosAlumnoFiltro(): array
{
    return estadosAlumnoCrud();
}

function esAlumnoOperativo(int $idEstado): bool
{
    return in_array($idEstado, [ESTADO_REGISTRADO, ESTADO_PREREGISTRADO], true);
}

function validarEstadoCrud(int $idEstado): bool
{
    return array_key_exists($idEstado, estadosAlumnoCrud());
}

function nombreEstadoAlumno(int $idEstado): string
{
    $mapa = estadosAlumnoFiltro();

    return $mapa[$idEstado] ?? 'Desconocido';
}

function ensureEstadosAlumno(mysqli $conexion): void
{
    $conexion->query("UPDATE estado SET descripcion = 'Registrado' WHERE idEstado = 1");
    $conexion->query(
        "INSERT IGNORE INTO estado (idEstado, descripcion) VALUES (" . ESTADO_PREREGISTRADO . ", 'Preregistrado')"
    );
    $conexion->query(
        "INSERT IGNORE INTO estado (idEstado, descripcion) VALUES (" . ESTADO_EGRESADO . ", 'Egresado')"
    );
}

/** Nombre para mostrar: soporta registros viejos con apellidos en columnas separadas. */
function nombreCompletoAlumno(array $row): string
{
    $partes = array_filter([
        trim($row['nombre'] ?? ''),
        trim($row['apellido1'] ?? ''),
        trim($row['apellido2'] ?? ''),
    ], static fn(string $p): bool => $p !== '');

    return implode(' ', $partes);
}
