<?php
session_start();
include 'conexion.php';
require_once '../estados_alumno_helper.php';

if (!isset($_SESSION['idplantel']) && !isset($_SESSION['plantel_id'])) {
    header('Location: login.php');
    exit();
}

$idplantel = (int) ($_SESSION['idplantel'] ?? $_SESSION['plantel_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - Alumnos</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: white; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: rgba(255,255,255,0.05); }
        th, td { padding: 12px; border: 1px solid #334155; text-align: left; }
        th { background: #1e293b; color: #22d3ee; }
        tr:hover { background: rgba(34, 211, 238, 0.1); }
    </style>
</head>
<body>
    <h2>Estudiantes Registrados</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre Completo</th>
                <th>Grupo</th>
                <th>Semestre</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT al.idAlumno, al.nombre, al.apellido1, al.apellido2, al.semestre, g.nombre_grupo
                    FROM alumno al
                    LEFT JOIN grupo g ON al.idGrupo = g.idGrupo
                    WHERE al.idplantel = ?
                    ORDER BY al.nombre ASC";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $idplantel);
            $stmt->execute();
            $resultado = $stmt->get_result();

            while ($fila = $resultado->fetch_assoc()) {
                $idAlumno = (int) $fila['idAlumno'];
                $semestre = (int) $fila['semestre'];
                $nombre = htmlspecialchars(nombreCompletoAlumno($fila), ENT_QUOTES, 'UTF-8');
                echo "<tr>
                        <td>{$idAlumno}</td>
                        <td>" . $nombre . "</td>
                        <td>" . htmlspecialchars($fila['nombre_grupo'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>
                        <td>{$semestre}</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>
    <br>
    <a href="panelqr.php" style="color: #22d3ee;">Volver al escaner</a>
</body>
</html>
