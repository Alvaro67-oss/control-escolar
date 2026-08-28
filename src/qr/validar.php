<?php
session_start();
include("conexion.php");

$usuario = $_POST['usuario'];
$password = $_POST['password'];

$query = "SELECT u.*, p.nombre 
          FROM usuarios u
          JOIN planteles p ON u.idplantel = p.id
          WHERE u.usuario = ? AND u.password = ?";

$stmt = $conexion->prepare($query);
$stmt->bind_param("ss", $usuario, $password);
$stmt->execute();

$resultado = $stmt->get_result();

if($row = $resultado->fetch_assoc()){

    $_SESSION['usuario'] = $row['usuario'];
    $_SESSION['plantel_id'] = $row['idplantel'];
    $_SESSION['idplantel'] = $row['idplantel'];
    $_SESSION['plantel_nombre'] = $row['nombre'];
    $_SESSION['rol'] = $row['rol'];

    require_once '../generaciones_helper.php';
    sincronizarGeneracionesAutomatico($conexion);

    header("Location: panelqr.php");
    exit();

}else{
    echo "Usuario o contraseña incorrectos";
}
?>