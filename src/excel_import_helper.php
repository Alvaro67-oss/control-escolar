<?php

require_once __DIR__ . '/generaciones_helper.php';
require_once __DIR__ . '/estados_alumno_helper.php';

function normalizarEncabezadoImport(string $texto): string
{
    $texto = strtolower(trim($texto));
    $texto = str_replace(['#', ' ', '.', '-', '_'], '', $texto);
    $texto = preg_replace('/[^a-z0-9]/', '', $texto);

    return $texto;
}

function mapearFilaImport(array $headers, array $values): array
{
    $fila = [];
    foreach ($headers as $i => $header) {
        $fila[normalizarEncabezadoImport((string) $header)] = trim((string) ($values[$i] ?? ''));
    }

    return $fila;
}

function valorFilaImport(array $fila, array $claves): string
{
    foreach ($claves as $clave) {
        $k = normalizarEncabezadoImport($clave);
        if ($fila[$k] !== '') {
            return $fila[$k];
        }
    }

    return '';
}

function leerCsvImport(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return ['ok' => false, 'error' => 'No se pudo abrir el archivo CSV'];
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);

        return ['ok' => false, 'error' => 'El archivo CSV esta vacio'];
    }

    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        fclose($handle);

        return ['ok' => false, 'error' => 'No se encontraron encabezados en el CSV'];
    }

    $headers = array_map(static fn($h) => trim((string) $h), $headers);
    $rows = [];

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count(array_filter($data, static fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $rows[] = mapearFilaImport($headers, $data);
    }

    fclose($handle);

    return ['ok' => true, 'headers' => $headers, 'rows' => $rows];
}

function leerXlsxImport(string $path): array
{
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        return ['ok' => false, 'error' => 'La importacion XLSX requiere Windows con Microsoft Access Database Engine'];
    }

    $script = __DIR__ . DIRECTORY_SEPARATOR . 'leer_excel.ps1';
    if (!is_file($script)) {
        return ['ok' => false, 'error' => 'No se encontro el lector de Excel'];
    }

    $escapedPath = str_replace("'", "''", $path);
    $escapedScript = str_replace("'", "''", $script);
    $command = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($script) . ' -Path ' . escapeshellarg($path) . ' 2>&1';

    $output = shell_exec($command);
    if ($output === null || trim($output) === '') {
        return ['ok' => false, 'error' => 'No se pudo leer el archivo Excel'];
    }

    $json = json_decode(trim($output), true);
    if (!is_array($json) || empty($json['ok'])) {
        return [
            'ok' => false,
            'error' => is_array($json) ? ($json['error'] ?? 'Error al procesar Excel') : 'Respuesta invalida del lector Excel',
        ];
    }

    $rows = [];
    foreach ($json['rows'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $mapped = [];
        foreach ($row as $header => $value) {
            $mapped[normalizarEncabezadoImport((string) $header)] = trim((string) $value);
        }
        if (count(array_filter($mapped, static fn($v) => $v !== '')) === 0) {
            continue;
        }
        $rows[] = $mapped;
    }

    return [
        'ok' => true,
        'headers' => $json['headers'] ?? [],
        'rows' => $rows,
    ];
}

function leerArchivoImport(string $path, string $extension): array
{
    $extension = strtolower($extension);

    if ($extension === 'csv') {
        return leerCsvImport($path);
    }

    if (in_array($extension, ['xlsx', 'xls'], true)) {
        return leerXlsxImport($path);
    }

    return ['ok' => false, 'error' => 'Formato no soportado. Usa .xlsx o .csv'];
}

function resolverGrupoImport(mysqli $conexion, string $grupoTexto): ?array
{
    $grupoTexto = strtoupper(trim($grupoTexto));
    if ($grupoTexto === '') {
        return null;
    }

    $stmt = $conexion->prepare('SELECT idGrupo, nombre_grupo FROM grupo WHERE UPPER(nombre_grupo) = ? LIMIT 1');
    $stmt->bind_param('s', $grupoTexto);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function resolverEstadoImport(string $estadoTexto): int
{
    $estadoTexto = strtolower(trim($estadoTexto));
    if ($estadoTexto === '') {
        return ESTADO_REGISTRADO;
    }

    $mapa = [
        'registrado' => ESTADO_REGISTRADO,
        'reg' => ESTADO_REGISTRADO,
        'al' => ESTADO_REGISTRADO,
        'activo' => ESTADO_REGISTRADO,
        'preregistrado' => ESTADO_PREREGISTRADO,
        'pre' => ESTADO_PREREGISTRADO,
        'egresado' => ESTADO_EGRESADO,
        'baja' => ESTADO_BAJA,
    ];

    if (isset($mapa[$estadoTexto])) {
        return $mapa[$estadoTexto];
    }

    foreach (estadosAlumnoCrud() as $id => $label) {
        if (strtolower($label) === $estadoTexto) {
            return (int) $id;
        }
    }

    return ESTADO_REGISTRADO;
}

function parsearFilaAlumnoImport(array $fila, int $idGeneracionDefault, int $idEstadoDefault): array
{
    $cuenta = (int) preg_replace('/\D/', '', valorFilaImport($fila, [
        'NO#CUENTA', 'NOCUENTA', 'CUENTA', '#CUENTA', 'IDALUMNO', 'NUMCUENTA', 'NUMERO CUENTA',
    ]));
    $nombre = valorFilaImport($fila, [
        'NOMBRE DEL ALUMNO', 'NOMBRE', 'NOMBRE COMPLETO', 'NOM C', 'NOMC', 'ALUMNO',
    ]);
    $semestre = (int) valorFilaImport($fila, ['SEMESTRE', 'SEM', 'SEM.']);
    $grupo = valorFilaImport($fila, ['GRUPO', 'GRUPO LETRA', 'LETRA']);
    $generacionTexto = valorFilaImport($fila, ['GENERACION', 'GEN', 'IDGENERACION']);
    $estadoTexto = valorFilaImport($fila, ['STATUS', 'ESTADO', 'ESTATUS']);

    $idGeneracion = $idGeneracionDefault;
    if ($generacionTexto !== '') {
        if (ctype_digit($generacionTexto)) {
            $idGeneracion = (int) $generacionTexto;
        }
    }

    $idEstado = $estadoTexto !== '' ? resolverEstadoImport($estadoTexto) : $idEstadoDefault;

    return [
        'idAlumno' => $cuenta,
        'nombre' => $nombre,
        'semestre' => $semestre,
        'grupo' => $grupo,
        'idGeneracion' => $idGeneracion,
        'idEstado' => $idEstado,
    ];
}

function importarAlumnosDesdeFilas(
    mysqli $conexion,
    array $filas,
    int $idplantel,
    int $idGeneracionDefault,
    int $idEstadoDefault
): array {
    $insertar = $conexion->prepare(
        'INSERT INTO alumno (idAlumno, nombre, apellido1, apellido2, semestre, idGrupo, idplantel, idGeneracion, idEstado)
         VALUES (?, ?, \'\', \'\', ?, ?, ?, ?, ?)'
    );
    $actualizar = $conexion->prepare(
        'UPDATE alumno SET nombre=?, apellido1=\'\', apellido2=\'\', semestre=?, idGrupo=?, idGeneracion=?, idEstado=?
         WHERE idAlumno=? AND idplantel=?'
    );
    $verificar = $conexion->prepare(
        'SELECT idAlumno, idEstado FROM alumno WHERE idAlumno=? AND idplantel=?'
    );

    $insertados = 0;
    $actualizados = 0;
    $omitidos = 0;
    $errores = [];

    foreach ($filas as $index => $fila) {
        $linea = $index + 2;
        $datos = parsearFilaAlumnoImport($fila, $idGeneracionDefault, $idEstadoDefault);

        if ($datos['idAlumno'] <= 0) {
            $errores[] = "Fila {$linea}: numero de cuenta invalido";
            continue;
        }
        if ($datos['nombre'] === '') {
            $errores[] = "Fila {$linea}: falta nombre completo";
            continue;
        }
        if (!validarSemestre($datos['semestre'])) {
            $errores[] = "Fila {$linea}: semestre invalido (use 1-6)";
            continue;
        }

        $grupo = resolverGrupoImport($conexion, $datos['grupo']);
        if (!$grupo) {
            $errores[] = "Fila {$linea}: grupo invalido ({$datos['grupo']})";
            continue;
        }

        $generacion = obtenerGeneracionPorId($conexion, $datos['idGeneracion']);
        if (!$generacion) {
            $errores[] = "Fila {$linea}: generacion invalida";
            continue;
        }

        if (!validarEstadoCrud($datos['idEstado']) && (int) $datos['idEstado'] !== ESTADO_BAJA) {
            $errores[] = "Fila {$linea}: estado invalido";
            continue;
        }

        $idAlumno = (int) $datos['idAlumno'];
        $idGrupo = (int) $grupo['idGrupo'];
        $idGeneracion = (int) $generacion['idGeneracion'];
        $idEstado = (int) $datos['idEstado'];
        $nombre = $datos['nombre'];
        $semestre = (int) $datos['semestre'];

        $verificar->bind_param('ii', $idAlumno, $idplantel);
        $verificar->execute();
        $existente = $verificar->get_result()->fetch_assoc();

        if ($existente && esAlumnoOperativo((int) $existente['idEstado'])) {
            $actualizar->bind_param(
                'siiiiii',
                $nombre,
                $semestre,
                $idGrupo,
                $idGeneracion,
                $idEstado,
                $idAlumno,
                $idplantel
            );
            if ($actualizar->execute()) {
                $actualizados++;
            } else {
                $errores[] = "Fila {$linea}: no se pudo actualizar cuenta {$idAlumno}";
            }
            continue;
        }

        if ($existente && (int) $existente['idEstado'] === ESTADO_BAJA) {
            $actualizar->bind_param(
                'siiiiii',
                $nombre,
                $semestre,
                $idGrupo,
                $idGeneracion,
                $idEstado,
                $idAlumno,
                $idplantel
            );
            if ($actualizar->execute()) {
                $actualizados++;
            } else {
                $errores[] = "Fila {$linea}: no se pudo reactivar cuenta {$idAlumno}";
            }
            continue;
        }

        if ($existente) {
            $omitidos++;
            continue;
        }

        $insertar->bind_param(
            'isiiiii',
            $idAlumno,
            $nombre,
            $semestre,
            $idGrupo,
            $idplantel,
            $idGeneracion,
            $idEstado
        );
        if ($insertar->execute()) {
            $insertados++;
        } else {
            $errores[] = "Fila {$linea}: no se pudo insertar cuenta {$idAlumno}";
        }
    }

    return [
        'ok' => true,
        'insertados' => $insertados,
        'actualizados' => $actualizados,
        'omitidos' => $omitidos,
        'errores' => $errores,
        'total_filas' => count($filas),
    ];
}
