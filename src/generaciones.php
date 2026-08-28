<?php

session_start();

include('conexion.php');

require_once 'generaciones_helper.php';

require_once 'sincronizar_semestres_auto.php';



if (!isset($_SESSION['plantel_id'])) {

    header('Location: login.php');

    exit();

}



$mensaje = '';

$tipo = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['sincronizar'])) {

        $resultado = sincronizarGeneracionesAutomatico($conexion, true);

        $mensaje = $resultado['mensaje'];

        $tipo = 'ok';

    }



    if (isset($_POST['agregar'])) {

        $nombre = trim($_POST['nombre_generacion'] ?? '');

        $inicio = (int) ($_POST['fecha_inicio'] ?? 0);

        $fin = (int) ($_POST['fecha_fin'] ?? 0);



        if ($nombre === '' || $inicio <= 0 || $fin <= $inicio) {

            $mensaje = 'Datos de generacion invalidos. El anio fin debe ser mayor al inicio.';

            $tipo = 'error';

        } else {

            $stmt = $conexion->prepare(

                'INSERT INTO generaciones (nombre_generacion, fecha_inicio, fecha_fin) VALUES (?, ?, ?)'

            );

            $stmt->bind_param('sii', $nombre, $inicio, $fin);

            if ($stmt->execute()) {

                $mensaje = 'Generacion registrada correctamente.';

                $tipo = 'ok';

            } else {

                $mensaje = 'No se pudo registrar la generacion.';

                $tipo = 'error';

            }

        }

    }

}



$generaciones = $conexion->query(

    'SELECT * FROM generaciones ORDER BY fecha_inicio DESC'

)->fetch_all(MYSQLI_ASSOC);



$activa = obtenerGeneracionActiva($conexion);

$anioActual = (int) date('Y');

$ultimaSync = obtenerConfig($conexion, 'ultima_sincronizacion_generaciones');

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<title>Generaciones</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{ --card:#0d1430; --neon:#00ffcc; }

body{ margin:0; font-family:'Segoe UI',sans-serif; background:#060912; color:#fff; }

.wrap{ width:100%; padding:10px; box-sizing:border-box; }

.card{
    background:var(--card);
    border:1px solid rgba(0,255,204,.25);
    border-radius:10px;
    padding:20px;
    color:#fff;
}

.card h5{
    color:#fff;
    font-size:1.05rem;
    font-weight:600;
    margin:0 0 14px 0;
}

.seccion-generaciones{
    padding-bottom:20px;
    margin-bottom:20px;
    border-bottom:1px solid rgba(0,255,204,.15);
}

.seccion-generaciones:last-child{
    padding-bottom:0;
    margin-bottom:0;
    border-bottom:none;
}

.form-control{
    background:#0a0f2c;
    border:1px solid rgba(0,255,204,.2);
    color:#fff;
}

.form-control::placeholder{ color:#9aa4c7; }

table{ width:100%; border-collapse:collapse; }

th,td{
    border:1px solid rgba(0,255,204,.15);
    padding:10px;
    text-align:center;
    color:#fff;
}

th{ background:var(--neon); color:#001a16; font-weight:700; }

tbody tr{ background:#0a0f2c; }

tbody tr:hover{ background:#111a3a; }

.msg-ok{ color:var(--neon); margin-bottom:12px; }

.msg-error{ color:#ff8a80; margin-bottom:12px; }

.info-box{
    background:#09122b;
    border:1px solid rgba(0,255,204,.2);
    border-radius:8px;
    padding:14px;
    font-size:.9rem;
    color:#fff;
    line-height:1.55;
}

.info-box p,
.info-box li,
.info-box strong{
    color:#fff;
}

.info-box ul{ margin-bottom:0; padding-left:1.2rem; }

.texto-secundario{
    color:#c5cee8;
    font-size:.85rem;
}

.badge-vigente{ color:#00ffcc; font-weight:600; }

.btn-agregar{
    background:#00e5ff;
    border:none;
    color:#001a16;
    font-weight:600;
}

.btn-agregar:hover{
    background:#00ffcc;
    color:#001a16;
}

.btn-sync{
    background:#00ffcc;
    border:none;
    color:#001a16;
    font-weight:600;
}

.btn-sync:hover{
    background:#00e5ff;
    color:#001a16;
}
</style>
<link rel="stylesheet" href="date-inputs.css">
</head>

<body class="has-nav-sticky">



<?php

$navActivo = 'generaciones';

$tituloNav = $_SESSION['plantel_nombre'] ?? 'EduControl';

include 'nav_secundario.php';

?>



<div class="wrap">

<?php if ($mensaje): ?>

<p class="<?php echo $tipo === 'ok' ? 'msg-ok' : 'msg-error'; ?>"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>

<?php endif; ?>



<div class="card">
    <div class="seccion-generaciones">
        <h5>Generaciones registradas</h5>
        <table>
            <thead>
                <tr><th>ID</th><th>Nombre</th><th>Anio inicio</th><th>Anio fin</th><th>Vigente hoy</th></tr>
            </thead>
            <tbody>
            <?php foreach ($generaciones as $g): ?>
            <tr>
                <td><?php echo (int) $g['idGeneracion']; ?></td>
                <td><?php echo htmlspecialchars($g['nombre_generacion'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo (int) $g['fecha_inicio']; ?></td>
                <td><?php echo (int) $g['fecha_fin']; ?></td>
                <td><?php echo anioDentroDeGeneracion($g) ? '<span class="badge-vigente">Si</span>' : 'No'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="seccion-generaciones">
        <h5>Agregar generacion</h5>
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="nombre_generacion" class="form-control" placeholder="Ej. 2026-2029" required>
            </div>
            <div class="col-md-3">
                <input type="number" name="fecha_inicio" class="form-control" placeholder="Anio inicio" required>
            </div>
            <div class="col-md-3">
                <input type="number" name="fecha_fin" class="form-control" placeholder="Anio fin" required>
            </div>
            <div class="col-md-2">
                <button type="submit" name="agregar" value="1" class="btn btn-agregar w-100">Agregar</button>
            </div>
        </form>
    </div>

    <div class="seccion-generaciones">
        <h5>Como funciona</h5>
        <div class="info-box">
            <p>Cada alumno se asigna a una <strong>generacion</strong> por su rango de anos, por ejemplo <strong>2023-2026</strong>.</p>
            <p>La generacion se define solo con:</p>
            <ul>
                <li><strong>Anio de inicio</strong> (cuando entra al bachillerato)</li>
                <li><strong>Anio de fin</strong> (cuando termina el ciclo)</li>
            </ul>
            <p class="mt-2 mb-0">La <strong>generacion activa</strong> es la que contiene el <strong>anio actual</strong> (<?php echo $anioActual; ?>).
            Se usa al registrar alumnos por QR sin generacion y para asignar la generacion faltante automaticamente.</p>
            <?php if ($activa): ?>
            <p class="mt-2 mb-0">Generacion activa: <strong><?php echo htmlspecialchars(etiquetaGeneracion($activa), ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <?php endif; ?>
            <p class="mt-2 mb-0">Ultima asignacion automatica: <strong><?php echo $ultimaSync !== '' ? htmlspecialchars($ultimaSync, ENT_QUOTES, 'UTF-8') : 'Nunca'; ?></strong></p>
            <p class="mt-2 mb-0">El <strong>semestre</strong> del alumno (1-6) se captura aparte en Alumnos o registro QR; ya no se calcula desde la generacion.</p>
        </div>

        <form method="POST" class="mt-3 mb-0">
            <button type="submit" name="sincronizar" value="1" class="btn btn-sync">
                Asignar generacion activa a alumnos sin generacion
            </button>
            <small class="texto-secundario d-block mt-2">
                Solo asigna idGeneracion a quienes no la tienen. No modifica el semestre.
            </small>
        </form>
    </div>
</div>

</div>

</body>

</html>


