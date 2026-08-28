<?php
session_start();
include("conexion.php");
require_once '../estados_alumno_helper.php';

if (!isset($_SESSION['idplantel']) && !isset($_SESSION['plantel_id'])) {
    header("Location: login.php");
    exit();
}

$idplantel = (int) ($_SESSION['idplantel'] ?? $_SESSION['plantel_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Asistencias</title>
    <style>
        body { font-family: sans-serif; background: #050a0f; color: #cbd5e1; padding: 20px; }
        .tabla-asistencia { width: 100%; border-collapse: collapse; border-radius: 10px; overflow: hidden; }
        th { background: #22d3ee; color: #000; padding: 10px; }
        td { padding: 10px; border-bottom: 1px solid #1e293b; text-align: center; }
        .entrada { color: #4ade80; font-weight: bold; }
        .salida { color: #f87171; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Registro de Entradas y Salidas (QR)</h2>
    <table class="tabla-asistencia">
        <thead>
            <tr>
                <th>ID Alumno</th>
                <th>Alumno</th>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT a.alumno_id, a.fecha, a.hora_entrada, a.hora_salida,
                           al.nombre, al.apellido1, al.apellido2
                    FROM asistencias a
                    INNER JOIN alumno al ON a.alumno_id = al.idAlumno
                    WHERE al.idplantel = ?
                    ORDER BY a.fecha DESC, a.hora_entrada DESC";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $idplantel);
            $stmt->execute();
            $resultado = $stmt->get_result();

            while ($f = $resultado->fetch_assoc()) {
                $nombre = htmlspecialchars(nombreCompletoAlumno($f), ENT_QUOTES, 'UTF-8');
                echo "<tr>
                        <td>" . (int) $f['alumno_id'] . "</td>
                        <td>" . $nombre . "</td>
                        <td>" . htmlspecialchars($f['fecha'], ENT_QUOTES, 'UTF-8') . "</td>
                        <td class='entrada'>" . htmlspecialchars($f['hora_entrada'] ?? '--:--', ENT_QUOTES, 'UTF-8') . "</td>
                        <td class='salida'>" . htmlspecialchars($f['hora_salida'] ?? '--:--', ENT_QUOTES, 'UTF-8') . "</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>
    <br>
    <a href="panelqr.php" style="color: #22d3ee;">Volver al escaner</a>
</body>
</html>
