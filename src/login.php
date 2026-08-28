<?php  
session_start();  
include("conexion.php");  

$error = "";  

if(isset($_POST['usuario']) && isset($_POST['password'])){  

    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $consulta = $conexion->prepare("
        SELECT u.*, p.nombre AS plantel_nombre 
        FROM usuarios u
        INNER JOIN planteles p ON u.idplantel = p.id
        WHERE u.usuario = ? AND u.password = ?
    ");

    $consulta->bind_param("ss", $usuario, $password);
    $consulta->execute();
    $resultado = $consulta->get_result();  

    if($resultado->num_rows > 0){  
        $datos = $resultado->fetch_assoc();  
        
        $_SESSION['usuario'] = $datos['usuario'];  
        $_SESSION['rol'] = $datos['rol'];  
        $_SESSION['plantel_id'] = $datos['idplantel'];  
        $_SESSION['idplantel'] = $datos['idplantel'];
        $_SESSION['plantel_nombre'] = $datos['plantel_nombre'];  

        require_once 'generaciones_helper.php';
        $sync = sincronizarGeneracionesAutomatico($conexion);
        if ($sync['ejecutado']) {
            $_SESSION['generaciones_sync_aviso'] = $sync['mensaje'];
        }

        header("Location: panel.php");  
        exit();  
    } else {  
        $error = "Usuario o contraseña incorrectos";  
    }  
}  
?> 

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>

:root{
    --bg1:#060912;
    --bg2:#0a0f2c;
    --card:#0d1430;
    --neon:#00ffcc;
}

body {
    height: 100vh;
    margin: 0;
    background: radial-gradient(circle at top, var(--bg2), var(--bg1) 80%);
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Segoe UI', sans-serif;
    overflow: hidden;
    opacity: 0;
    transition: 0.8s;
}

body::before {
    content: "";
    position: absolute;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(0,255,204,0.08) 1px, transparent 1px);
    background-size: 40px 40px;
    animation: moveBg 20s linear infinite;
}

@keyframes moveBg {
    from { transform: translate(0,0); }
    to { transform: translate(-200px, -200px); }
}

.card-login {
    backdrop-filter: blur(20px);
    background: rgba(13,20,48,0.85);
    border-radius: 25px;
    padding: 40px;
    width: 350px;
    text-align: center;
    border: 1px solid rgba(0,255,204,0.2);
    box-shadow: 0 0 20px rgba(0,255,204,0.15);
    transition: 0.3s;
}

.card-login:hover {
    transform: scale(1.02);
    box-shadow: 0 0 25px var(--neon), 0 0 50px rgba(0,255,204,0.2);
}

.title {
    color: #ffffff;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 20px;
}

.input-group {
    margin-bottom: 15px;
}

.form-control {
    background-color: #0a0f2c;
    border: 1px solid rgba(0,255,204,0.2);
    color: white;
    border-radius: 12px;
    padding: 10px;
}

.form-control:focus {
    border-color: var(--neon);
    box-shadow: 0 0 10px var(--neon);
    background-color:#0a0f2c;
    color:white;
}

.btn-login {
    background: linear-gradient(135deg, #00ffcc, #00e5ff);
    border: none;
    padding: 12px;
    border-radius: 25px;
    width: 100%;
    color: #001a16;
    font-weight: bold;
    transition: 0.3s;
}

.btn-login:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px #00ffcc, 0 0 40px rgba(0,255,204,0.3);
}

.error {
    color: #ff4d4d;
    margin-top: 10px;
}

</style>
</head>

<body>

<div class="card-login">

    <div class="title">Administrador</div>

    <form method="POST">

        <div class="input-group">
            <span class="input-group-text bg-dark text-light border-0">
                <i class="bi bi-person"></i>
            </span>
            <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
        </div>

        <div class="input-group">
            <span class="input-group-text bg-dark text-light border-0">
                <i class="bi bi-lock"></i>
            </span>
            <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Entrar
        </button>

        <?php if($error != ""): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

    </form>

</div>

<script>
window.onload = () => {
    document.body.style.opacity = "1";
};
</script>

</body>
</html>