<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduControl</title>

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

.card-main {
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

.card-main:hover {
    transform: scale(1.02);
    box-shadow: 0 0 25px var(--neon), 0 0 50px rgba(0,255,204,0.2);
}


.card-main img {
    width: 180px;
    margin-bottom: 20px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.title {
    color: #ffffff;
    font-size: 28px;
    font-weight: 700;
}

.subtitle {
    color: #9aa4c7;
    font-size: 14px;
    margin-bottom: 25px;
}

.btn-custom {
    background: linear-gradient(135deg, #00ffcc, #00e5ff);
    border: none;
    padding: 12px;
    border-radius: 25px;
    width: 100%;
    color: #001a16;
    font-weight: bold;
    transition: 0.3s;
}

.btn-custom:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px #00ffcc, 0 0 40px rgba(0,255,204,0.3);
}

.btn-custom:active {
    transform: scale(0.95);
}
</style>
</head>

<body>

<div class="card-main">

    <img src="control.png" alt="Control Escolar">

    <div class="title">
        Sistema de <br> Control Escolar
    </div>

    <div class="subtitle">
        Accede de forma rápida y segura
    </div>

    <button class="btn-custom" onclick="irSeleccion()">
        <i class="bi bi-box-arrow-in-right"></i> Ingresar
    </button>

</div>

<script>
window.onload = () => {
    document.body.style.opacity = "1";
};

function irSeleccion() {
    document.body.style.opacity = "0";
    setTimeout(() => {
        window.location.href = "login.php";
    }, 500);
}
</script>

</body>
</html>