<?php
session_start();
include("conexion.php");
require_once("../generaciones_helper.php");
require_once("../estados_alumno_helper.php");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['idplantel']) && !isset($_SESSION['plantel_id'])) {
    echo json_encode(["status" => "error", "message" => "Sesion no valida"]);
    exit();
}

ensureEstadosAlumno($conexion);

function ensureAsistenciasPendientes(mysqli $conexion): void
{
    $conexion->query(
        "CREATE TABLE IF NOT EXISTS asistencias_pendientes (
            id INT(11) NOT NULL AUTO_INCREMENT,
            num_cuenta INT(11) NOT NULL,
            idplantel INT(11) NOT NULL,
            fecha DATE NOT NULL,
            hora_entrada TIME NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_pendiente_dia (num_cuenta, idplantel, fecha),
            KEY idplantel (idplantel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function registrarPendiente(mysqli $conexion, int $numCuenta, int $idplantel, string $fecha, string $hora): void
{
    ensureAsistenciasPendientes($conexion);

    $stmt = $conexion->prepare(
        "INSERT INTO asistencias_pendientes (num_cuenta, idplantel, fecha, hora_entrada)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE hora_entrada = VALUES(hora_entrada)"
    );
    $stmt->bind_param("iiss", $numCuenta, $idplantel, $fecha, $hora);
    $stmt->execute();
}

function obtenerGrupoDefault(mysqli $conexion): int
{
    $res = $conexion->query("SELECT idGrupo FROM grupo ORDER BY idGrupo ASC LIMIT 1");

    return $res && ($row = $res->fetch_assoc()) ? (int) $row['idGrupo'] : 1;
}

function asegurarAlumnoPreregistrado(mysqli $conexion, int $codigo, int $idplantel): array
{
    $stmt = $conexion->prepare(
        "SELECT idAlumno, idEstado, idplantel, nombre, apellido1, apellido2
         FROM alumno WHERE idAlumno = ?"
    );
    $stmt->bind_param("i", $codigo);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();

    if ($existente) {
        if ((int) $existente['idplantel'] !== $idplantel) {
            return [
                'ok' => false,
                'message' => 'La cuenta #' . $codigo . ' pertenece a otro plantel',
            ];
        }

        if (esAlumnoOperativo((int) $existente['idEstado'])) {
            return ['ok' => true, 'nuevo' => false, 'nombre' => nombreCompletoAlumno($existente)];
        }

        $generacion = obtenerGeneracionActiva($conexion);
        $idGeneracion = $generacion ? (int) $generacion['idGeneracion'] : null;
        $semestre = 1;
        $idGrupo = obtenerGrupoDefault($conexion);
        $nombre = 'Pendiente registro QR';

        $reactivar = $conexion->prepare(
            "UPDATE alumno SET nombre=?, apellido1='', apellido2='', semestre=?, idGrupo=?, idGeneracion=?, idEstado=?
             WHERE idAlumno=? AND idplantel=?"
        );
        $estado = ESTADO_PREREGISTRADO;
        $reactivar->bind_param("siiiiii", $nombre, $semestre, $idGrupo, $idGeneracion, $estado, $codigo, $idplantel);
        if (!$reactivar->execute()) {
            return ['ok' => false, 'message' => 'No se pudo reactivar la cuenta como preregistrado'];
        }

        return ['ok' => true, 'nuevo' => true, 'nombre' => $nombre];
    }

    $generacion = obtenerGeneracionActiva($conexion);
    $idGeneracion = $generacion ? (int) $generacion['idGeneracion'] : null;
    $semestre = 1;
    $idGrupo = obtenerGrupoDefault($conexion);
    $nombre = 'Pendiente registro QR';
    $estado = ESTADO_PREREGISTRADO;

    $insertar = $conexion->prepare(
        "INSERT INTO alumno (idAlumno, nombre, apellido1, apellido2, semestre, idGrupo, idplantel, idGeneracion, idEstado)
         VALUES (?, ?, '', '', ?, ?, ?, ?, ?)"
    );
    $insertar->bind_param("isiiiii", $codigo, $nombre, $semestre, $idGrupo, $idplantel, $idGeneracion, $estado);

    if (!$insertar->execute()) {
        return ['ok' => false, 'message' => 'No se pudo crear el alumno preregistrado: ' . $conexion->error];
    }

    return ['ok' => true, 'nuevo' => true, 'nombre' => $nombre];
}

const MIN_MINUTOS_SALIDA = 50;

function minutosDesdeEntrada(string $fecha, string $horaEntrada, string $horaActual): int
{
    $entrada = new DateTime($fecha . ' ' . $horaEntrada);
    $ahora = new DateTime($fecha . ' ' . $horaActual);

    return (int) floor(($ahora->getTimestamp() - $entrada->getTimestamp()) / 60);
}

function registrarAsistencia(mysqli $conexion, int $codigo, string $fecha, string $hora, string $nombre): array
{
    $stmt = $conexion->prepare("SELECT hora_entrada, hora_salida FROM asistencias WHERE alumno_id=? AND fecha=?");
    $stmt->bind_param("is", $codigo, $fecha);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        $insert = $conexion->prepare(
            "INSERT INTO asistencias (alumno_id, fecha, estado, hora_entrada) VALUES (?, ?, 'Asistencia', ?)"
        );
        $insert->bind_param("iss", $codigo, $fecha, $hora);
        $insert->execute();

        return [
            'status' => 'registrado',
            'tipo' => 'ENTRADA',
            'message' => 'Entrada: ' . $nombre . ' — ' . substr($hora, 0, 5),
        ];
    }

    if (empty($data['hora_entrada'])) {
        return [
            'status' => 'error',
            'message' => 'No hay hora de entrada registrada para hoy',
        ];
    }

    $minutos = minutosDesdeEntrada($fecha, $data['hora_entrada'], $hora);
    if ($minutos < MIN_MINUTOS_SALIDA) {
        $faltan = MIN_MINUTOS_SALIDA - $minutos;

        return [
            'status' => 'espera',
            'message' => 'Debes esperar al menos ' . MIN_MINUTOS_SALIDA . ' minutos despues de la entrada. Faltan ' . $faltan . ' min.',
            'minutos_restantes' => $faltan,
        ];
    }

    $upd = $conexion->prepare(
        "UPDATE asistencias SET hora_salida=? WHERE alumno_id=? AND fecha=?"
    );
    $upd->bind_param("sis", $hora, $codigo, $fecha);
    $upd->execute();

    $mensaje = $data['hora_salida']
        ? 'Salida actualizada: ' . $nombre . ' — ' . substr($hora, 0, 5)
        : 'Salida: ' . $nombre . ' — ' . substr($hora, 0, 5);

    return [
        'status' => 'registrado',
        'tipo' => 'SALIDA',
        'message' => $mensaje,
    ];
}

$idplantel = (int) ($_SESSION['idplantel'] ?? $_SESSION['plantel_id']);
$codigoRaw = trim($_POST['codigo'] ?? '');
$codigo = (int) preg_replace('/\D/', '', $codigoRaw);

date_default_timezone_set('America/Mexico_City');
$fecha = date("Y-m-d");
$hora = date("H:i:s");

if ($codigo <= 0) {
    echo json_encode(["status" => "error", "message" => "Codigo QR no valido"]);
    exit();
}

$stmt = $conexion->prepare(
    "SELECT nombre, apellido1, apellido2 FROM alumno WHERE idAlumno=? AND idplantel=? AND idEstado IN (1, 3)"
);
$stmt->bind_param("ii", $codigo, $idplantel);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $alta = asegurarAlumnoPreregistrado($conexion, $codigo, $idplantel);
    if (!$alta['ok']) {
        echo json_encode(["status" => "error", "message" => $alta['message']]);
        exit();
    }

    registrarPendiente($conexion, $codigo, $idplantel, $fecha, $hora);

    $nombre = $alta['nombre'] ?? ('Cuenta #' . $codigo);
    $asistencia = registrarAsistencia($conexion, $codigo, $fecha, $hora, $nombre);

    echo json_encode([
        'status' => 'pendiente',
        'num_cuenta' => $codigo,
        'hora_entrada' => substr($hora, 0, 5),
        'message' => 'Cuenta #' . $codigo . ' preregistrada. Debe pasar a direccion a completar registro. Entrada: ' . substr($hora, 0, 5),
        'tipo' => $asistencia['tipo'] ?? 'ENTRADA',
    ]);
    exit();
}

$alumno = $res->fetch_assoc();
$nombre = nombreCompletoAlumno($alumno);

echo json_encode(registrarAsistencia($conexion, $codigo, $fecha, $hora, $nombre));
