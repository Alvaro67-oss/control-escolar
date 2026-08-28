<?php
session_start();
include("conexion.php");
require_once 'sincronizar_semestres_auto.php';
require_once 'estados_alumno_helper.php';

if (!isset($_SESSION['plantel_id'])) {
    header("Location: login.php");
    exit();
}

$idplantel = (int) $_SESSION['plantel_id'];
ensureEstadosAlumno($conexion);
$estadosCrud = estadosAlumnoCrud();

$consultaPlantel = $conexion->prepare("SELECT clave FROM planteles WHERE id = ?");
$consultaPlantel->bind_param("i", $idplantel);
$consultaPlantel->execute();
$plantelClave = $consultaPlantel->get_result()->fetch_assoc()['clave'] ?? '';

$meses = [
    1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
    5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
    9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
];

$dias = [
    'Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes',
    'Wednesday' => 'Miercoles', 'Thursday' => 'Jueves',
    'Friday' => 'Viernes', 'Saturday' => 'Sabado'
];

$query = "
SELECT
    a.fecha,
    YEAR(a.fecha) AS anio,
    MONTH(a.fecha) AS mes,
    DAY(a.fecha) AS dia,
    DAYNAME(a.fecha) AS dia_nombre,
    a.hora_entrada,
    a.hora_salida,
    al.idAlumno,
    al.nombre,
    al.apellido1,
    al.apellido2,
    al.semestre,
    al.idEstado,
    g.nombre_grupo,
    e.descripcion AS estado_alumno
FROM asistencias a
INNER JOIN alumno al ON a.alumno_id = al.idAlumno
LEFT JOIN grupo g ON al.idGrupo = g.idGrupo
LEFT JOIN estado e ON al.idEstado = e.idEstado
WHERE al.idplantel = ?
ORDER BY a.fecha DESC, a.hora_entrada DESC
";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $idplantel);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asistencias</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
    --bg1:#060912;
    --bg2:#0a0f2c;
    --card:#0d1430;
    --neon:#00ffcc;
}

body{
    margin:0;
    font-family:'Segoe UI', sans-serif;
    background: radial-gradient(circle at top, var(--bg2), var(--bg1) 80%);
    color:white;
    opacity:0;
    transition:0.5s;
}

.page-wrap{
    width:100%;
    padding:10px;
    box-sizing:border-box;
}

.main-card{
    background:var(--card);
    border:2px solid rgba(0,255,204,0.2);
    border-radius:10px;
    padding:18px;
    box-shadow:0 0 18px rgba(0,255,204,0.08);
}

.page-title{
    margin:0 0 16px 0;
    font-size:1.15rem;
    font-weight:600;
}

.filtros-box{
    border:1px solid rgba(0,255,204,0.25);
    border-radius:8px;
    padding:14px;
    margin-bottom:16px;
    background:#09122b;
}

.filtros-box h6{
    margin:0 0 12px 0;
    color:var(--neon);
    font-size:0.95rem;
}

.filtros-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:12px;
}

@media (max-width:900px){
    .filtros-grid{ grid-template-columns:repeat(2, 1fr); }
}

@media (max-width:520px){
    .filtros-grid{ grid-template-columns:1fr; }
}

.filtro-field label{
    display:block;
    font-size:0.8rem;
    color:#9aa4c7;
    margin-bottom:4px;
}

.filtro-input{
    width:100%;
    height:42px;
    background:#0a0f2c;
    color:white;
    border:1px solid rgba(0,255,204,0.2);
    border-radius:8px;
    padding:8px 10px;
    box-sizing:border-box;
}

.filtro-input:focus{
    outline:none;
    border-color:var(--neon);
    box-shadow:0 0 8px rgba(0,255,204,0.25);
}

.table-wrap{
    width:100%;
    overflow-x:auto;
}

.data-table{
    width:100%;
    border-collapse:collapse;
    min-width:920px;
}

.data-table thead th{
    background:var(--neon);
    color:#001a16;
    border:1px solid rgba(0,255,204,0.5);
    padding:10px 8px;
    text-align:center;
    font-size:0.85rem;
    font-weight:700;
}

.data-table thead tr:first-child th{
    font-size:0.95rem;
    padding:12px 8px;
}

.data-table tbody td{
    border:1px solid rgba(0,255,204,0.15);
    padding:12px 10px;
    text-align:center;
    background:rgba(9,18,43,0.85);
    font-size:0.9rem;
}

.data-table tbody tr:nth-child(even) td{
    background:rgba(0,255,204,0.04);
}

.data-table tbody tr:hover td{
    background:rgba(0,255,204,0.12);
}

.col-nombre{
    text-align:left !important;
}

.text-neon{ color:var(--neon); }
.text-salida{ color:#ff1744; }

.estado-badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:999px;
    font-size:0.78rem;
    font-weight:600;
    white-space:nowrap;
}

.estado-registrado{
    color:#00ffcc;
    background:rgba(0,255,204,0.12);
    border:1px solid rgba(0,255,204,0.35);
}

.estado-preregistrado{
    color:#ffc107;
    background:rgba(255,193,7,0.12);
    border:1px solid rgba(255,193,7,0.35);
}

.estado-baja{
    color:#ff8a80;
    background:rgba(255,23,68,0.12);
    border:1px solid rgba(255,23,68,0.35);
}
</style>
<link rel="stylesheet" href="date-inputs.css">
</head>

<body class="has-nav-sticky">

<?php
$navActivo = 'asistencias';
$tituloNav = $_SESSION['plantel_nombre'] ?? 'EduControl';
include 'nav_secundario.php';
?>

<div class="page-wrap">
<div class="main-card">

<h5 class="page-title">Asistencias — <?php echo htmlspecialchars($plantelClave, ENT_QUOTES, 'UTF-8'); ?></h5>

<div class="filtros-box">
    <h6>Filtros</h6>
    <div class="filtros-grid">
        <div class="filtro-field">
            <label for="filtroCuenta"># Cuenta</label>
            <input type="text" id="filtroCuenta" class="filtro-input" placeholder="Ej. 20231201">
        </div>
        <div class="filtro-field">
            <label for="filtroNombre">Nombres</label>
            <input type="text" id="filtroNombre" class="filtro-input" placeholder="Nombre del alumno">
        </div>
        <div class="filtro-field">
            <label for="filtroEstado">Estado</label>
            <select id="filtroEstado" class="filtro-input">
                <option value="">Todos</option>
                <?php foreach ($estadosCrud as $idEst => $label): ?>
                <option value="<?php echo (int) $idEst; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-field">
            <label for="filtroInicio">Inicio Asis.</label>
            <input type="date" id="filtroInicio" class="filtro-input">
        </div>
        <div class="filtro-field">
            <label for="filtroFin">Fin Asist.</label>
            <input type="date" id="filtroFin" class="filtro-input">
        </div>
    </div>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
<tr>
    <th colspan="3">Asistencia</th>
    <th colspan="5">Alumno</th>
    <th colspan="2">Horas</th>
</tr>
<tr>
    <th>Año</th>
    <th>mes</th>
    <th>día</th>
    <th># C.</th>
    <th>Nom C.</th>
    <th>Sem.</th>
    <th>Grupo</th>
    <th>Estado</th>
    <th>Ent.</th>
    <th>Salida</th>
</tr>
</thead>
<tbody id="tabla-body">
<?php
while ($fila = $resultado->fetch_assoc()) {
    $idAlumno = (int) $fila['idAlumno'];
    $idEstado = (int) ($fila['idEstado'] ?? 0);
    $estadoTxt = htmlspecialchars($fila['estado_alumno'] ?? nombreEstadoAlumno($idEstado), ENT_QUOTES, 'UTF-8');
    $estadoClass = 'estado-registrado';
    if ($idEstado === ESTADO_PREREGISTRADO) {
        $estadoClass = 'estado-preregistrado';
    } elseif ($idEstado === ESTADO_BAJA) {
        $estadoClass = 'estado-baja';
    }
    $nombreComp = htmlspecialchars(nombreCompletoAlumno($fila), ENT_QUOTES, 'UTF-8');
    $mesTxt = (int) $fila['mes'] . ' ' . ($meses[(int) $fila['mes']] ?? '');
    $diaTxt = (int) $fila['dia'] . ' ' . ($dias[$fila['dia_nombre']] ?? $fila['dia_nombre']);
    $fecha = htmlspecialchars($fila['fecha'], ENT_QUOTES, 'UTF-8');
    $entrada = htmlspecialchars($fila['hora_entrada'] ?: '--:--', ENT_QUOTES, 'UTF-8');
    $salida = htmlspecialchars($fila['hora_salida'] ?: '--:--', ENT_QUOTES, 'UTF-8');
    $nombreBusqueda = strtolower(nombreCompletoAlumno($fila));
    $semestre = (int) ($fila['semestre'] ?? 0);
    $grupo = htmlspecialchars($fila['nombre_grupo'] ?? '—', ENT_QUOTES, 'UTF-8');

    echo "<tr data-id='{$idAlumno}' data-nombre='" . htmlspecialchars($nombreBusqueda, ENT_QUOTES, 'UTF-8') . "' data-fecha='{$fecha}' data-estado='{$idEstado}'>";
    echo "<td>" . (int) $fila['anio'] . "</td>";
    echo "<td>" . htmlspecialchars($mesTxt, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($diaTxt, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . $idAlumno . "</td>";
    echo "<td class='col-nombre'>" . $nombreComp . "</td>";
    echo "<td>" . ($semestre > 0 ? $semestre : '—') . "</td>";
    echo "<td>" . $grupo . "</td>";
    echo "<td><span class='estado-badge {$estadoClass}'>" . $estadoTxt . "</span></td>";
    echo "<td class='text-neon'>" . $entrada . "</td>";
    echo "<td class='text-salida'>" . $salida . "</td>";
    echo "</tr>";
}
?>
</tbody>
</table>
</div>

</div>
</div>

<script>
window.onload = () => document.body.style.opacity = "1";

const filtroCuenta = document.getElementById("filtroCuenta");
const filtroNombre = document.getElementById("filtroNombre");
const filtroEstado = document.getElementById("filtroEstado");
const filtroInicio = document.getElementById("filtroInicio");
const filtroFin = document.getElementById("filtroFin");

function filtrar() {
    const cuenta = filtroCuenta.value.trim().toLowerCase();
    const nombre = filtroNombre.value.trim().toLowerCase();
    const estado = filtroEstado.value;
    const inicio = filtroInicio.value;
    const fin = filtroFin.value;

    document.querySelectorAll("#tabla-body tr").forEach(fila => {
        let mostrar = true;

        if (cuenta && !fila.dataset.id.includes(cuenta)) {
            mostrar = false;
        }
        if (nombre && !fila.dataset.nombre.includes(nombre)) {
            mostrar = false;
        }
        if (estado && fila.dataset.estado !== estado) {
            mostrar = false;
        }
        if (inicio && fila.dataset.fecha < inicio) {
            mostrar = false;
        }
        if (fin && fila.dataset.fecha > fin) {
            mostrar = false;
        }

        fila.style.display = mostrar ? "" : "none";
    });
}

[filtroCuenta, filtroNombre, filtroEstado, filtroInicio, filtroFin].forEach(el => {
    el.addEventListener("input", filtrar);
    el.addEventListener("change", filtrar);
});

const params = new URLSearchParams(window.location.search);
if (params.get("cuenta")) {
    filtroCuenta.value = params.get("cuenta");
    filtrar();
}
</script>

</body>
</html>
