<?php  
session_start();  
include("conexion.php");  
require_once 'sincronizar_semestres_auto.php';

if(!isset($_SESSION['plantel_id'])){  
    header("Location: login.php");  
    exit();  
}  

$plantel_id = $_SESSION['plantel_id'];

$consulta = $conexion->prepare("SELECT * FROM planteles WHERE id = ?");  
$consulta->bind_param("i", $plantel_id);  
$consulta->execute();  
$plantel = $consulta->get_result()->fetch_assoc();  

$plantel_nombre = $plantel['clave'];  

$syncAviso = $_SESSION['generaciones_sync_aviso'] ?? $_SESSION['semestres_sync_aviso'] ?? '';
unset($_SESSION['generaciones_sync_aviso'], $_SESSION['semestres_sync_aviso']);

$hoyPanel = new DateTime();
$diaSemana = (int) $hoyPanel->format('N');
$lunesPanel = clone $hoyPanel;
$lunesPanel->modify('-' . ($diaSemana - 1) . ' days');
$viernesPanel = clone $lunesPanel;
$viernesPanel->modify('+4 days');
$semanaInicioDefault = $lunesPanel->format('Y-m-d');
$semanaFinDefault = $viernesPanel->format('Y-m-d');
$fechaHoy = date('Y-m-d');

/* =========================
   GRAFICA DIARIA (por AJAX)
========================= */
?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<title>Panel</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

:root{
  --bg:#060912;
  --card:#0d1430;
  --neon:#00ffcc;
  --neon2:#00e5ff;
  --danger:#ff1744;
}

body{
  margin:0;
  font-family:'Segoe UI', sans-serif;
  background: radial-gradient(circle at top, #0a0f2c, #030611 80%);
  color:white;
  opacity:0;
  transition:0.6s;
}

/* =========================
   HEADER
========================= */

.custom-header{
  background:#0d1430;
  border:1px solid #00ffcc55;
  border-top:none;
  border-left:none;
  border-right:none;
  margin:0;
  padding:14px 24px;
  border-radius:0;
  display:flex;
  justify-content:space-between;
  align-items:center;
  box-shadow:0 0 20px #00ffcc22;
  width:100%;
  box-sizing:border-box;
}

.logo-circle{
  width:40px;
  height:40px;
  border:2px solid var(--neon);
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
}

.plantel-title{
  font-size:1.35rem;
  font-weight:600;
  color:#fff;
}

.header-brand{
  display:flex;
  align-items:center;
  gap:16px;
}

.header-logo-wrap{
  background:#0d1430;
  line-height:0;
  border-radius:4px;
}

.header-logo{
  height:48px;
  width:auto;
  display:block;
  object-fit:contain;
  mix-blend-mode:lighten;
}

.header-actions{
  display:flex;
  gap:12px;
  align-items:stretch;
}

.btn-header{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:110px;
  min-height:40px;
  padding:8px 20px;
  border-radius:8px;
  font-weight:500;
  font-size:1rem;
  text-decoration:none;
  box-sizing:border-box;
  transition:0.3s;
  border:1px solid transparent;
}

.btn-header-alumnos{
  border-color:var(--neon);
  color:#fff;
  background:transparent;
}

.btn-header-alumnos:hover{
  background:rgba(0,255,204,0.2);
  color:#fff;
}

.btn-header-salir{
  border-color:#ff1744;
  color:#fff;
  background:#ff1744;
}

.btn-header-salir:hover{
  background:#d50032;
  border-color:#d50032;
  color:#fff;
}

/* =========================
   DASHBOARD (layout libreta)
========================= */

.dashboard{
  width:100%;
  max-width:none;
  margin:0;
  padding:10px;
  box-sizing:border-box;
  display:flex;
  flex-direction:column;
  gap:14px;
}

.panel-row{
  display:flex;
  align-items:stretch;
  background:var(--card);
  border:2px solid rgba(0,255,204,0.25);
  border-radius:10px;
  overflow:hidden;
  min-height:340px;
  box-shadow:0 0 18px rgba(0,255,204,0.08);
  width:100%;
}

.panel-row:first-child{
  min-height:360px;
}

.panel-chart{
  flex:1;
  padding:18px 22px;
  display:flex;
  flex-direction:column;
  border-right:2px solid rgba(0,255,204,0.2);
  background:#09122b;
}

.panel-chart h5{
  margin:0 0 12px 0;
  font-size:1.1rem;
  font-weight:600;
  color:#fff;
}

.chart-wrap{
  flex:1;
  position:relative;
  min-height:220px;
}

.chart-wrap canvas{
  width:100% !important;
  height:100% !important;
}

.panel-sidebar{
  width:260px;
  min-width:240px;
  padding:22px 20px;
  display:flex;
  flex-direction:column;
  justify-content:center;
  background:rgba(13,20,48,0.95);
}

.sidebar-form{
  display:flex;
  flex-direction:column;
  gap:14px;
  width:100%;
}

.sidebar-form .form-control,
.sidebar-form .reporte-btn{
  width:100%;
  height:46px;
  border-radius:10px;
  font-size:14px;
}

.sidebar-form .reporte-btn{
  font-weight:600;
  border:none;
}

.sidebar-label{
  display:block;
  font-size:0.78rem;
  color:#9aa4c7;
  margin-bottom:4px;
  text-align:left;
}

.sidebar-divider{
  border-top:1px solid rgba(0,255,204,0.15);
  margin:6px 0 10px;
  padding-top:8px;
  font-size:0.75rem;
  color:#9aa4c7;
  text-align:left;
}

@media (max-width:768px){
  .panel-row{
    flex-direction:column;
    min-height:auto;
  }

  .panel-chart{
    border-right:none;
    border-bottom:2px solid rgba(0,255,204,0.2);
  }

  .panel-sidebar{
    width:100%;
  }
}

h5{
  margin-bottom:20px;
}

.form-control{
    background:#09122b;
    border:1px solid rgba(0,255,204,0.2);
    color:white;
}

.form-control:focus{
    background:#09122b;
    color:white;
    border-color:#00ffcc;
    box-shadow:0 0 10px #00ffcc55;
}

label{
    color:white;
}

.reporte-input{
    background:#09122b;
    border:1px solid rgba(0,255,204,0.2);
    color:white;
}

.reporte-input:focus{
    box-shadow:none;
    border-color:#00ffcc;
    background:#09122b;
    color:white;
}

</style>
<link rel="stylesheet" href="date-inputs.css">

</head>

<body>

<header class="custom-header">

<span class="header-brand">
    <span class="header-logo-wrap">
        <img src="logo-udec.png" alt="Universidad de Colima" class="header-logo">
    </span>
    <span class="plantel-title">Plantel <?php echo htmlspecialchars($plantel_nombre, ENT_QUOTES, 'UTF-8'); ?></span>
</span>

<div class="header-actions">

<a href="alumnos.php" class="btn-header btn-header-alumnos">
Alumnos
</a>

<a href="logout.php" class="btn-header btn-header-salir">
Salir
</a>

</div>

</header>

<?php if ($syncAviso !== ''): ?>
<div class="alert alert-success mx-3 mt-2 mb-0 py-2" role="alert" style="background:rgba(0,255,204,0.12);border:1px solid rgba(0,255,204,0.35);color:#00ffcc;">
    <?php echo htmlspecialchars($syncAviso, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<!-- =========================
     DASHBOARD
========================= -->

<div class="dashboard">

    <!-- FILA 1: Asistencia diaria -->
    <section class="panel-row">

        <div class="panel-chart">
            <h5>Asistencia diaria</h5>
            <div class="chart-wrap">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <aside class="panel-sidebar">
            <form action="excel.php" method="GET" class="sidebar-form" id="formDiario">
                <label class="sidebar-label">Grupo</label>
                <select name="grupo" id="diarioGrupo" class="form-control reporte-input">
                    <option value="">Todos los grupos</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select>

                <label class="sidebar-label">Semestre</label>
                <select name="semestre" id="diarioSemestre" class="form-control reporte-input">
                    <option value="">Todos los semestres</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                </select>

                <label class="sidebar-label">Fecha (dia)</label>
                <input type="date" name="fecha" id="diarioFecha" class="form-control reporte-input" value="<?php echo $fechaHoy; ?>">

                <div class="sidebar-divider">Rango de fechas</div>

                <label class="sidebar-label">Desde</label>
                <input type="date" id="diarioInicio" name="fecha_inicio" class="form-control reporte-input">

                <label class="sidebar-label">Hasta</label>
                <input type="date" id="diarioFin" name="fecha_fin" class="form-control reporte-input">

                <button type="button" id="btnDiarioRango" class="btn btn-outline-light reporte-btn" style="border-color:rgba(0,255,204,0.4);color:#00ffcc;">
                    Aplicar rango
                </button>

                <button type="button" id="btnDiarioDia" class="btn btn-outline-secondary reporte-btn" style="display:none;">
                    Ver por dia
                </button>

                <button type="submit" class="btn btn-success reporte-btn">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
            </form>
        </aside>

    </section>

    <!-- FILA 2: Asistencia semanal -->
    <section class="panel-row">

        <div class="panel-chart">
            <h5>Asistencia semanal</h5>
            <div class="chart-wrap">
                <canvas id="mainChart"></canvas>
            </div>
        </div>

        <aside class="panel-sidebar">
            <form action="exportar_excel.php" method="GET" class="sidebar-form" id="formSemanal">
                <label class="sidebar-label">Grupo</label>
                <select name="grupo" class="form-control reporte-input">
                    <option value="">Todos los grupos</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select>

                <label class="sidebar-label">Semestre</label>
                <select name="semestre" class="form-control reporte-input">
                    <option value="">Todos los semestres</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                </select>

                <div class="sidebar-divider">Rango de fechas</div>

                <label class="sidebar-label">Desde</label>
                <input type="date" name="inicio" id="semanalInicio" class="form-control reporte-input" value="<?php echo $semanaInicioDefault; ?>">

                <label class="sidebar-label">Hasta</label>
                <input type="date" name="fin" id="semanalFin" class="form-control reporte-input" value="<?php echo $semanaFinDefault; ?>">

                <button type="button" id="btnSemanalRango" class="btn btn-outline-light reporte-btn" style="border-color:rgba(0,255,204,0.4);color:#00ffcc;">
                    Aplicar rango
                </button>

                <button type="button" id="btnSemanalSemana" class="btn btn-outline-secondary reporte-btn">
                    Semana actual
                </button>

                <button type="submit" class="btn btn-success reporte-btn">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
            </form>
        </aside>

    </section>

</div>

<script>

/* ANIMACION */

window.onload = () => {
    document.body.style.opacity = "1";
};

/* OPCIONES */

const options = {

responsive:true,
maintainAspectRatio:false,

plugins:{
legend:{
labels:{
color:'#fff'
}
}
},

scales:{

x:{
ticks:{
color:'#ccc'
}
},

y:{
ticks:{
color:'#ccc',
stepSize:50
},
min:0,
max:500,
beginAtZero:true
}

}

};

const optionsDiaria = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: { color: '#fff' }
        }
    },
    scales: {
        x: {
            ticks: { color: '#ccc' },
            grid: { color: 'rgba(255,255,255,0.05)' }
        },
        y: {
            ticks: { color: '#ccc', stepSize: 10 },
            min: 0,
            max: 50,
            beginAtZero: true,
            grid: { color: 'rgba(255,255,255,0.05)' }
        }
    }
};

/* =========================
   GRAFICA DIARIA
========================= */

let chartDiario = null;
let chartSemanal = null;
let diarioModoRango = false;

const semanaInicioDefault = <?php echo json_encode($semanaInicioDefault); ?>;
const semanaFinDefault = <?php echo json_encode($semanaFinDefault); ?>;

function actualizarBotonesDiario() {
    document.getElementById('btnDiarioDia').style.display = diarioModoRango ? 'block' : 'none';
}

function paramsDiarios() {
    const inicio = document.getElementById('diarioInicio').value;
    const fin = document.getElementById('diarioFin').value;

    if (diarioModoRango && inicio && fin) {
        return 'fecha_inicio=' + encodeURIComponent(inicio) + '&fecha_fin=' + encodeURIComponent(fin);
    }

    const fecha = document.getElementById('diarioFecha').value;
    return 'fecha=' + encodeURIComponent(fecha);
}

function cargarGraficaDiaria() {
    fetch('datos_asistencia_diaria.php?' + paramsDiarios())
        .then(res => res.json())
        .then(data => {
            if (!data.ok) return;

            if (chartDiario) chartDiario.destroy();

            const opciones = { ...optionsDiaria };
            if (data.max_eje_y) {
                opciones.scales.y.max = data.max_eje_y;
                opciones.scales.y.ticks.stepSize = Math.max(1, Math.ceil(data.max_eje_y / 10));
            }

            chartDiario = new Chart(document.getElementById('dailyChart'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: data.datasets
                },
                options: opciones
            });
        });
}

document.getElementById('diarioFecha').addEventListener('change', () => {
    diarioModoRango = false;
    actualizarBotonesDiario();
    cargarGraficaDiaria();
});

document.getElementById('btnDiarioRango').addEventListener('click', () => {
    const inicio = document.getElementById('diarioInicio').value;
    const fin = document.getElementById('diarioFin').value;

    if (!inicio || !fin) {
        alert('Selecciona fecha de inicio y fin para el rango.');
        return;
    }

    diarioModoRango = true;
    actualizarBotonesDiario();
    cargarGraficaDiaria();
});

document.getElementById('btnDiarioDia').addEventListener('click', () => {
    document.getElementById('diarioInicio').value = '';
    document.getElementById('diarioFin').value = '';
    diarioModoRango = false;
    actualizarBotonesDiario();
    cargarGraficaDiaria();
});

document.getElementById('formDiario').addEventListener('submit', function () {
    const inicio = document.getElementById('diarioInicio').value;
    const fin = document.getElementById('diarioFin').value;

    if (diarioModoRango && inicio && fin) {
        document.getElementById('diarioFecha').removeAttribute('name');
    } else {
        document.getElementById('diarioInicio').removeAttribute('name');
        document.getElementById('diarioFin').removeAttribute('name');
    }
});

cargarGraficaDiaria();

/* =========================
   GRAFICA SEMANAL
========================= */

function cargarGraficaSemanal() {
    const inicio = document.getElementById('semanalInicio').value;
    const fin = document.getElementById('semanalFin').value;

    if (!inicio || !fin) {
        alert('Selecciona fecha de inicio y fin para el rango.');
        return;
    }

    fetch('datos_asistencia_semanal.php?fecha_inicio=' + encodeURIComponent(inicio) + '&fecha_fin=' + encodeURIComponent(fin))
        .then(res => res.json())
        .then(data => {
            if (!data.ok) return;

            if (chartSemanal) chartSemanal.destroy();

            chartSemanal = new Chart(document.getElementById('mainChart'), {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Asistencias',
                            data: data.asistencias,
                            backgroundColor: '#00ffcc'
                        },
                        {
                            label: 'Faltas',
                            data: data.faltas,
                            backgroundColor: '#ff1744'
                        }
                    ]
                },
                options: options
            });
        });
}

document.getElementById('btnSemanalRango').addEventListener('click', cargarGraficaSemanal);
['semanalInicio', 'semanalFin'].forEach(id => {
    document.getElementById(id).addEventListener('change', cargarGraficaSemanal);
});

document.getElementById('btnSemanalSemana').addEventListener('click', () => {
    document.getElementById('semanalInicio').value = semanaInicioDefault;
    document.getElementById('semanalFin').value = semanaFinDefault;
    cargarGraficaSemanal();
});

cargarGraficaSemanal();
actualizarBotonesDiario();

</script>

</body>
</html>