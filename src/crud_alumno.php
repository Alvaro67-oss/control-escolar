<?php

session_start();

include("conexion.php");

require_once("generaciones_helper.php");

require_once("estados_alumno_helper.php");



header('Content-Type: application/json; charset=utf-8');



if (!isset($_SESSION['plantel_id'])) {

    http_response_code(403);

    echo json_encode(['ok' => false, 'mensaje' => 'Sesion no valida']);

    exit();

}



ensureEstadosAlumno($conexion);



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['ok' => false, 'mensaje' => 'Metodo no permitido']);

    exit();

}



$idplantel = (int) $_SESSION['plantel_id'];

$accion = $_POST['accion'] ?? '';

$id = (int) ($_POST['idAlumno'] ?? 0);



function alumnoJson(array $row): array

{

    $idEstado = (int) ($row['idEstado'] ?? 0);



    return [

        'idAlumno' => (int) $row['idAlumno'],

        'nombre' => nombreCompletoAlumno($row),

        'semestre' => (int) $row['semestre'],

        'idGrupo' => (int) $row['idGrupo'],

        'nombre_grupo' => $row['nombre_grupo'] ?? '',

        'idGeneracion' => (int) ($row['idGeneracion'] ?? 0),

        'nombre_generacion' => $row['nombre_generacion'] ?? '',

        'idEstado' => $idEstado,

        'estado' => $row['estado'] ?? nombreEstadoAlumno($idEstado),

    ];

}



function validarSemestre(int $semestre): bool

{

    return $semestre >= 1 && $semestre <= 6;

}



if ($accion === 'buscar') {

    if ($id <= 0) {

        echo json_encode(['ok' => false, 'mensaje' => 'Ingresa un numero de cuenta valido']);

        exit();

    }



    $stmt = $conexion->prepare(

        "SELECT al.*, g.nombre_grupo, gen.nombre_generacion, e.descripcion AS estado

         FROM alumno al

         LEFT JOIN grupo g ON al.idGrupo = g.idGrupo

         LEFT JOIN generaciones gen ON al.idGeneracion = gen.idGeneracion

         LEFT JOIN estado e ON al.idEstado = e.idEstado

         WHERE al.idAlumno = ? AND al.idplantel = ?"

    );

    $stmt->bind_param("ii", $id, $idplantel);

    $stmt->execute();

    $alumno = $stmt->get_result()->fetch_assoc();



    if (!$alumno) {

        echo json_encode(['ok' => false, 'mensaje' => 'No se encontro alumno con ese numero de cuenta']);

        exit();

    }



    echo json_encode(['ok' => true, 'alumno' => alumnoJson($alumno)]);

    exit();

}



$nombre = trim($_POST['nombre'] ?? '');
$apellido1 = '';
$apellido2 = '';

$idGrupo = (int) ($_POST['idGrupo'] ?? 0);

$idGeneracion = (int) ($_POST['idGeneracion'] ?? 0);

$semestre = (int) ($_POST['semestre'] ?? 0);

$idEstado = (int) ($_POST['idEstado'] ?? ESTADO_REGISTRADO);



$generacion = obtenerGeneracionPorId($conexion, $idGeneracion);

if (!$generacion && in_array($accion, ['crear', 'actualizar'], true)) {

    echo json_encode(['ok' => false, 'mensaje' => 'Selecciona una generacion valida']);

    exit();

}



if (in_array($accion, ['crear', 'actualizar'], true) && !validarEstadoCrud($idEstado)) {

    echo json_encode(['ok' => false, 'mensaje' => 'Selecciona un estado valido']);

    exit();

}



if ($accion === 'crear') {

    if ($id <= 0 || $nombre === '' || $idGrupo <= 0 || $idGeneracion <= 0 || !validarSemestre($semestre)) {

        echo json_encode(['ok' => false, 'mensaje' => 'Completa cuenta, nombre completo, grupo, generacion y semestre (1-6)']);

        exit();

    }



    $verificar = $conexion->prepare(

        "SELECT idAlumno, idEstado FROM alumno WHERE idAlumno = ? AND idplantel = ?"

    );

    $verificar->bind_param("ii", $id, $idplantel);

    $verificar->execute();

    $existente = $verificar->get_result()->fetch_assoc();



    if ($existente && esAlumnoOperativo((int) $existente['idEstado'])) {

        echo json_encode(['ok' => false, 'mensaje' => 'Ese numero de cuenta ya esta registrado']);

        exit();

    }



    if ($existente && (int) $existente['idEstado'] === ESTADO_BAJA) {

        $reactivar = $conexion->prepare(

            "UPDATE alumno SET nombre=?, apellido1=?, apellido2=?, semestre=?, idGrupo=?, idGeneracion=?, idEstado=?

             WHERE idAlumno=? AND idplantel=?"

        );

        $reactivar->bind_param("sssiiiiii", $nombre, $apellido1, $apellido2, $semestre, $idGrupo, $idGeneracion, $idEstado, $id, $idplantel);

        $ok = $reactivar->execute();

        if (!$ok) {

            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo actualizar el alumno: ' . $conexion->error]);

            exit();

        }

    } else {

        $insertar = $conexion->prepare(

            "INSERT INTO alumno (idAlumno, nombre, apellido1, apellido2, semestre, idGrupo, idplantel, idGeneracion, idEstado)

             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"

        );

        $insertar->bind_param("isssiiiii", $id, $nombre, $apellido1, $apellido2, $semestre, $idGrupo, $idplantel, $idGeneracion, $idEstado);

        $ok = $insertar->execute();

    }



    echo json_encode([

        'ok' => (bool) $ok,

        'mensaje' => $ok

            ? 'Alumno registrado como ' . nombreEstadoAlumno($idEstado) . ". Generacion: " . etiquetaGeneracion($generacion)

            : 'No se pudo registrar el alumno',

    ]);

    exit();

}



if ($accion === 'actualizar') {

    if ($id <= 0 || $nombre === '' || $idGrupo <= 0 || $idGeneracion <= 0 || !validarSemestre($semestre)) {

        echo json_encode(['ok' => false, 'mensaje' => 'Completa todos los campos correctamente (semestre 1-6)']);

        exit();

    }



    $actualizar = $conexion->prepare(

        "UPDATE alumno SET nombre=?, apellido1=?, apellido2=?, semestre=?, idGrupo=?, idGeneracion=?, idEstado=?

         WHERE idAlumno=? AND idplantel=?"

    );

    $actualizar->bind_param("sssiiiiii", $nombre, $apellido1, $apellido2, $semestre, $idGrupo, $idGeneracion, $idEstado, $id, $idplantel);



    if (!$actualizar->execute()) {

        echo json_encode(['ok' => false, 'mensaje' => 'No se pudo actualizar el alumno: ' . $conexion->error]);

        exit();

    }



    if ($actualizar->affected_rows === 0) {

        $existe = $conexion->prepare("SELECT idAlumno FROM alumno WHERE idAlumno=? AND idplantel=?");

        $existe->bind_param("ii", $id, $idplantel);

        $existe->execute();

        if ($existe->get_result()->num_rows === 0) {

            echo json_encode(['ok' => false, 'mensaje' => 'Alumno no encontrado. Usa Buscar primero.']);

            exit();

        }

    }



    echo json_encode([

        'ok' => true,

        'mensaje' => 'Datos actualizados. Generacion: ' . etiquetaGeneracion($generacion),

    ]);

    exit();

}



echo json_encode(['ok' => false, 'mensaje' => 'Accion no valida']);


