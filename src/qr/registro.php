<?php

session_start();

include 'conexion.php';

require_once '../generaciones_helper.php';



if (!isset($_SESSION['idplantel']) && !isset($_SESSION['plantel_id'])) {

    header('Location: login.php');

    exit();

}



$cuenta = htmlspecialchars($_GET['num_cuenta'] ?? '', ENT_QUOTES, 'UTF-8');

$generaciones = $conexion->query(

    'SELECT idGeneracion, nombre_generacion, fecha_inicio, fecha_fin FROM generaciones ORDER BY fecha_inicio DESC'

)->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Registro - CONTROL ESCOLAR</title>

    <style>

        body { background: #050a0f; color: white; font-family: sans-serif; padding: 50px; text-align: center; }

        form { background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; display: inline-block; min-width: 320px; }

        input, select { display: block; margin: 10px auto; padding: 10px; width: 80%; box-sizing: border-box; }

        button { background: #22d3ee; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; }

    </style>

</head>

<body>

    <form action="guardar_alumno.php" method="POST">

        <h2>Alta de Alumno</h2>

        <input type="text" name="num_cuenta" value="<?php echo $cuenta; ?>" readonly>

        <input type="text" name="nombre" placeholder="Nombre completo" required>

        <select name="idGeneracion" required>

            <option value="">Seleccionar generacion</option>

            <?php foreach ($generaciones as $gen): ?>

            <option value="<?php echo (int) $gen['idGeneracion']; ?>">

                <?php echo htmlspecialchars($gen['nombre_generacion'], ENT_QUOTES, 'UTF-8'); ?>

                (<?php echo (int) $gen['fecha_inicio']; ?>-<?php echo (int) $gen['fecha_fin']; ?>)

            </option>

            <?php endforeach; ?>

        </select>

        <select name="semestre" required>

            <option value="">Semestre</option>

            <?php for ($s = 1; $s <= 6; $s++): ?>

            <option value="<?php echo $s; ?>"><?php echo $s; ?></option>

            <?php endfor; ?>

        </select>

        <input type="text" name="grupo" placeholder="Grupo (A, B o C)" required>

        <button type="submit">Guardar y Regresar al Escaner</button>

    </form>

</body>

</html>


