<?php 
session_start();

if (!isset($_SESSION['idplantel']) && !isset($_SESSION['plantel_id'])) {
    header("Location: login.php"); 
    exit();
}

$idplantel = (int) ($_SESSION['idplantel'] ?? $_SESSION['plantel_id']);
$plantel_actual = $idplantel;

date_default_timezone_set('America/Mexico_City'); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner P<?php echo $plantel_actual; ?> - CONTROL ESCOLAR</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        :root {
            --bg-gradient: radial-gradient(circle at center, #0a2e2f 0%, #050a0f 100%);
            --panel-bg: rgba(13, 25, 35, 0.85);
            --neon-cyan: #22d3ee;
            --text-silver: #cbd5e1;
            --success-green: #4ade80;
            --error-red: #f87171;
            --warning-yellow: #facc15;
        }
        body { 
            font-family: 'Segoe UI', Roboto, sans-serif; 
            background: var(--bg-gradient); 
            display: flex; justify-content: center; align-items: center; 
            height: 100vh; margin: 0; color: var(--text-silver);
            overflow: hidden;
        }
        .scanner-container { 
            background: var(--panel-bg); 
            backdrop-filter: blur(15px);
            padding: 30px; border-radius: 24px; 
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center; width: 90%; max-width: 420px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }
        h2 { 
            margin: 0 0 20px 0; 
            letter-spacing: 2px; 
            color: #fff; 
            text-shadow: 0 0 10px var(--neon-cyan); 
        }
        #reader { 
            width: 100%; 
            border-radius: 15px; 
            overflow: hidden; 
            border: 2px solid var(--neon-cyan) !important; 
            background: #000; 
        }
        .status-msg { 
            margin-top: 25px; 
            padding: 15px; 
            border-radius: 12px; 
            font-weight: 700; 
            background: rgba(0,0,0,0.5); 
            min-height: 24px; 
            transition: all 0.4s ease;
            text-transform: uppercase;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .status-msg.normal-case {
            text-transform: none;
        }
        .info-header { 
            display: flex; 
            justify-content: space-between; 
            font-size: 0.85rem; 
            color: var(--neon-cyan); 
            margin-bottom: 20px; 
            font-family: monospace; 
            opacity: 0.8;
        }
        .btn-salir {
            margin-top: 15px;
            display: inline-block;
            color: var(--error-red);
            text-decoration: none;
            font-size: 0.7rem;
            border: 1px solid var(--error-red);
            padding: 5px 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="scanner-container">
    <div class="info-header">
        <span>📅 <?php echo date('Y-m-d'); ?></span>
        <span>🆔 PLANTEL: <?php echo $plantel_actual; ?></span>
    </div>
    <h2>ASISTENCIA P<?php echo $plantel_actual; ?></h2>
    
    <div id="reader"></div>

    <div id="status" class="status-msg">INICIALIZANDO...</div>
    
    <a href="logout.php" class="btn-salir">CERRAR SESIÓN</a>
</div>

<script>
let html5QrCode;
const statusDiv = document.getElementById('status');

function encenderCamara() {
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
    }
    const config = { 
        fps: 20, 
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };
    html5QrCode.start(
        { facingMode: "environment" },
        config,
        onScanSuccess
    ).then(() => {
        statusDiv.innerText = "ESPERANDO QR / BARRA";
        statusDiv.style.color = "var(--text-silver)";
    }).catch(err => {
        statusDiv.innerText = "⚠️ ERROR: NO HAY CÁMARA";
        statusDiv.style.color = "var(--error-red)";
    });
}

function onScanSuccess(decodedText) {
    html5QrCode.stop().then(() => {
        statusDiv.innerText = "🔍 BUSCANDO: " + decodedText;
        statusDiv.style.color = "var(--neon-cyan)";

        let fd = new FormData();
        fd.append('codigo', decodedText);

        fetch('procesar.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            statusDiv.classList.remove('normal-case');

            if (data.status === 'registrado') {
                statusDiv.classList.remove('normal-case');
                statusDiv.innerText = "✅ " + data.message;
                statusDiv.style.color = (data.tipo === 'ENTRADA') ? "var(--success-green)" : "var(--neon-cyan)";
                setTimeout(encenderCamara, 2000);
            } else if (data.status === 'pendiente') {
                statusDiv.classList.add('normal-case');
                statusDiv.innerText = "⚠️ " + data.message;
                statusDiv.style.color = "var(--warning-yellow)";
                setTimeout(encenderCamara, 5000);
            } else if (data.status === 'espera') {
                statusDiv.classList.add('normal-case');
                statusDiv.innerText = "⏳ " + data.message;
                statusDiv.style.color = "var(--warning-yellow)";
                setTimeout(encenderCamara, 4000);
            } else {
                statusDiv.innerText = "❌ " + data.message;
                statusDiv.style.color = "var(--error-red)";
                setTimeout(encenderCamara, 2000);
            }
        })
        .catch(err => {
            console.error(err);
            statusDiv.innerText = "⚠️ ERROR DE SERVIDOR";
            statusDiv.style.color = "var(--error-red)";
            setTimeout(encenderCamara, 3000);
        });
    });
}

window.onload = encenderCamara;
</script>
</body>
</html>