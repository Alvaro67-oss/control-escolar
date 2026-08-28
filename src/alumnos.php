<?php
session_start();
include("conexion.php");
require_once 'sincronizar_semestres_auto.php';

if (!isset($_SESSION['plantel_id'])) {
    header("Location: login.php");
    exit();
}

$idplantel = (int) $_SESSION['plantel_id'];

$consultaPlantel = $conexion->prepare("SELECT clave FROM planteles WHERE id = ?");
$consultaPlantel->bind_param("i", $idplantel);
$consultaPlantel->execute();
$plantelClave = $consultaPlantel->get_result()->fetch_assoc()['clave'] ?? '';

$grupos = [];
$resGrupos = $conexion->query("SELECT idGrupo, nombre_grupo FROM grupo ORDER BY idGrupo");
while ($g = $resGrupos->fetch_assoc()) {
    $grupos[] = $g;
}

require_once 'generaciones_helper.php';
require_once 'estados_alumno_helper.php';
ensureEstadosAlumno($conexion);
$generaciones = $conexion->query(
    'SELECT idGeneracion, nombre_generacion, fecha_inicio, fecha_fin FROM generaciones ORDER BY fecha_inicio DESC'
)->fetch_all(MYSQLI_ASSOC);
$estadosCrud = estadosAlumnoCrud();
$estadosFiltro = estadosAlumnoFiltro();
$generacionActiva = obtenerGeneracionActiva($conexion);
$idGeneracionActiva = $generacionActiva ? (int) $generacionActiva['idGeneracion'] : 0;

$stmtAlumnos = $conexion->prepare(
    "SELECT al.idAlumno, al.nombre, al.apellido1, al.apellido2, al.semestre, al.idGrupo, al.idGeneracion, al.idEstado,
            g.nombre_grupo, gen.nombre_generacion, gen.fecha_inicio, gen.fecha_fin, e.descripcion AS estado
     FROM alumno al
     LEFT JOIN grupo g ON al.idGrupo = g.idGrupo
     LEFT JOIN generaciones gen ON al.idGeneracion = gen.idGeneracion
     LEFT JOIN estado e ON al.idEstado = e.idEstado
     WHERE al.idplantel = ?
     ORDER BY al.nombre ASC, al.idAlumno ASC"
);
$stmtAlumnos->bind_param('i', $idplantel);
$stmtAlumnos->execute();
$alumnosLista = $stmtAlumnos->get_result()->fetch_all(MYSQLI_ASSOC);

$tmpCuenta = [];
$tmpNombre = [];

foreach ($alumnosLista as $al) {
    $idAl = (int) $al['idAlumno'];
    $nomAl = nombreCompletoAlumno($al);
    if ($nomAl === '') {
        $nomAl = 'Sin nombre';
    }

    $tmpCuenta[(string) $idAl] = (string) $idAl;
    $tmpNombre[strtolower($nomAl)] = $nomAl;
}

$toOptions = static function (array $map, bool $numericSort = false): array {
    $opts = [];
    foreach ($map as $value => $label) {
        $opts[] = ['value' => (string) $value, 'label' => (string) $label];
    }
    usort($opts, static function ($a, $b) use ($numericSort) {
        if ($numericSort) {
            return (int) $a['value'] <=> (int) $b['value'];
        }
        return strnatcasecmp($a['label'], $b['label']);
    });
    return $opts;
};

$opcionesFiltroLista = [
    'cuenta' => $toOptions($tmpCuenta, true),
    'nombre' => $toOptions($tmpNombre),
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lista de Alumnos</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

.crud-box{
    padding:0;
}

.modal-crud .modal-content{
    background:#0d1430;
    border:1px solid rgba(0,255,204,0.35);
    color:#fff;
}

.modal-crud .modal-header{
    border-bottom:1px solid rgba(0,255,204,0.15);
    padding:16px 20px;
}

.modal-crud .modal-title{
    color:var(--neon);
    font-size:1.05rem;
    font-weight:600;
}

.modal-crud .modal-body{
    padding:20px;
}

.modal-crud .modal-dialog{
    max-width:960px;
    width:calc(100% - 2rem);
}

.modal-crud .btn-close{
    filter:invert(1);
    opacity:0.85;
}

.btn-gestion{
    background:#00e5ff;
    color:#001a16;
}

.btn-gestion:hover{
    background:#00ffcc;
    color:#001a16;
}

.crud-mode-toggle{
    display:flex;
    width:100%;
    margin-bottom:18px;
    border:1px solid #00e5ff;
    border-radius:8px;
    overflow:hidden;
}

.crud-mode-toggle .btn-check{
    position:absolute;
    clip:rect(0,0,0,0);
    pointer-events:none;
}

.crud-mode-toggle label{
    flex:1;
    margin:0;
    padding:10px 8px;
    text-align:center;
    cursor:pointer;
    background:transparent;
    color:#00e5ff;
    border:none;
    font-size:0.76rem;
    font-weight:500;
    line-height:1.3;
    transition:background .2s, color .2s, box-shadow .2s;
}

.crud-mode-toggle label:not(:last-of-type){
    border-right:1px solid rgba(0,229,255,0.35);
}

.crud-mode-toggle .btn-check:checked + label{
    background:#00e5ff;
    color:#001a16;
    box-shadow:inset 0 0 0 1px rgba(0,255,204,0.4);
}

.crud-panel.hidden{
    display:none;
}

.crud-import{
    margin-top:0;
    padding-top:0;
    border-top:none;
}

.crud-register-manual{
    margin-bottom:0;
    padding-bottom:0;
    border-bottom:none;
}

.crud-register-manual h6,
.crud-import h6{
    margin:0 0 10px 0;
    color:var(--neon);
    font-size:0.95rem;
}

.crud-register-help{
    font-size:0.8rem;
    color:#9aa4c7;
    margin:0 0 12px 0;
    line-height:1.45;
}

.crud-import-help{
    font-size:0.8rem;
    color:#9aa4c7;
    margin:0 0 12px 0;
    line-height:1.45;
}

.crud-import-grid{
    display:grid;
    grid-template-columns:1fr 180px 180px auto;
    gap:12px;
    align-items:end;
}

@media (max-width:900px){
    .crud-import-grid{ grid-template-columns:1fr 1fr; }
}

@media (max-width:520px){
    .crud-import-grid{ grid-template-columns:1fr; }
}

.btn-importar{
    background:#7c4dff;
    color:#fff;
}

.btn-importar:hover{
    background:#651fff;
    color:#fff;
}

.import-msg{
    margin-top:12px;
    padding:10px 12px;
    border-radius:8px;
    font-size:0.85rem;
    display:none;
}

.import-msg.ok{
    display:block;
    background:rgba(0,255,204,0.15);
    color:var(--neon);
    border:1px solid var(--neon);
}

.import-msg.error{
    display:block;
    background:rgba(255,23,68,0.15);
    color:#ff8a80;
    border:1px solid #ff1744;
}

.crud-form-row-selects{
    grid-column:1 / -1;
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:12px;
}

@media (max-width:900px){
    .crud-form-row-selects{ grid-template-columns:repeat(2, 1fr); }
}

@media (max-width:520px){
    .crud-form-row-selects{ grid-template-columns:1fr; }
}

.filtro-input[readonly]{
    opacity:0.85;
    cursor:not-allowed;
    background:rgba(10,15,44,0.6);
}

.crud-buscar{
    display:flex;
    gap:10px;
    align-items:flex-end;
    margin-bottom:14px;
    padding-bottom:14px;
    border-bottom:1px solid rgba(0,255,204,0.15);
}

.crud-buscar .filtro-field{ flex:1; max-width:280px; }

.crud-form-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:12px;
    margin-bottom:14px;
}

.crud-form-row-cuenta{
    grid-column:1 / -1;
    display:flex;
    gap:12px;
    align-items:flex-end;
}

.crud-field-cuenta{
    flex:0 0 200px;
    max-width:240px;
}

.crud-field-nombre{
    flex:1;
    min-width:0;
}

@media (max-width:520px){
    .crud-field-cuenta{
        flex:0 0 140px;
        max-width:160px;
    }
}

@media (max-width:900px){
    .crud-form-grid{ grid-template-columns:repeat(2, 1fr); }
}

@media (max-width:520px){
    .crud-form-grid{ grid-template-columns:1fr; }
}

.crud-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}

.btn-crud{
    border:none;
    padding:10px 18px;
    border-radius:8px;
    font-weight:600;
    font-size:0.85rem;
    cursor:pointer;
    transition:0.2s;
}

.btn-buscar{ background:var(--neon); color:#001a16; }
.btn-alta{ background:#00e5ff; color:#001a16; }
.btn-actualizar{ background:#ffc107; color:#001a16; }
.btn-limpiar{ background:#0a0f2c; color:#9aa4c7; border:1px solid rgba(0,255,204,0.2); }

.btn-crud:hover{ opacity:0.9; transform:scale(1.02); }

.crud-msg{
    margin-top:12px;
    padding:10px 12px;
    border-radius:8px;
    font-size:0.85rem;
    display:none;
}

.crud-msg.ok{ display:block; background:rgba(0,255,204,0.15); color:var(--neon); border:1px solid var(--neon); }
.crud-msg.error{ display:block; background:rgba(255,23,68,0.15); color:#ff8a80; border:1px solid #ff1744; }

.filtro-input[readonly]{
    background:#061018;
    color:var(--neon);
    cursor:not-allowed;
}

.lista-box{
    border:1px solid rgba(0,255,204,0.35);
    border-radius:8px;
    padding:16px;
    background:#09122b;
}

.lista-box h6{
    margin:0 0 14px 0;
    color:var(--neon);
    font-size:1rem;
    padding-bottom:14px;
    border-bottom:1px solid rgba(0,255,204,0.15);
}

.lista-busqueda{
    border:2px solid rgba(0,255,204,0.4);
    border-radius:10px;
    padding:12px 14px;
    margin-bottom:14px;
    background:rgba(6,9,18,0.45);
}

.lista-busqueda-campos{
    display:grid;
    grid-template-columns:repeat(6, minmax(0, 1fr)) auto;
    align-items:end;
    gap:12px;
}

.busqueda-campo{
    display:flex;
    flex-direction:column;
    gap:4px;
    min-width:0;
}

.busqueda-campo label{
    font-size:0.72rem;
    font-weight:600;
    color:#9aa4c7;
    white-space:nowrap;
}

.busqueda-campo .filtro-input{
    width:100%;
    padding:8px 10px;
    font-size:0.82rem;
    box-sizing:border-box;
}

.lista-busqueda-acciones{
    display:flex;
    align-items:flex-end;
    gap:8px;
    flex-shrink:0;
}

.lista-busqueda-acciones .btn-crud{
    padding:8px 12px;
    font-size:0.82rem;
    white-space:nowrap;
}

@media (max-width:1100px){
    .lista-busqueda-campos{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }

    .lista-busqueda-acciones{
        grid-column:1 / -1;
        justify-content:flex-end;
    }
}

@media (max-width:640px){
    .lista-busqueda-campos{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }
}

.lista-vacia{
    padding:24px;
    text-align:center;
    color:#9aa4c7;
    border:1px dashed rgba(0,255,204,0.2);
    border-radius:8px;
}

.lista-vacia.hidden{ display:none; }

.table-wrap{
    width:100%;
    overflow-x:auto;
}

.data-table{
    width:100%;
    border-collapse:collapse;
    min-width:880px;
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

.data-table tbody td{
    border:1px solid rgba(0,255,204,0.15);
    padding:10px 8px;
    text-align:center;
    background:rgba(9,18,43,0.85);
    font-size:0.88rem;
    vertical-align:middle;
}

.data-table tbody tr:nth-child(even) td{
    background:rgba(0,255,204,0.04);
}

.data-table tbody tr:hover td{
    background:rgba(0,255,204,0.1);
}

.data-table tbody tr.hidden{
    display:none;
}

.col-nombre{
    text-align:left !important;
}

.col-cuenta{
    color:var(--neon);
    font-weight:700;
    white-space:nowrap;
}

.estado-badge{
    display:inline-block;
    font-size:0.72rem;
    padding:3px 8px;
    border-radius:999px;
    background:rgba(255,255,255,0.06);
    white-space:nowrap;
}

.estado-badge.estado-registrado{ color:#00ffcc; border:1px solid rgba(0,255,204,0.35); }
.estado-badge.estado-preregistrado{ color:#ffc107; border:1px solid rgba(255,193,7,0.35); }
.estado-badge.estado-baja{ color:#ff8a80; border:1px solid rgba(255,138,128,0.35); }
.estado-badge.estado-egresado{ color:#b388ff; border:1px solid rgba(179,136,255,0.35); }

.btn-cargar-alumno{
    background:#0a0f2c;
    color:var(--neon);
    border:1px solid rgba(0,255,204,0.35);
    padding:6px 10px;
    border-radius:8px;
    font-size:0.78rem;
    font-weight:600;
    cursor:pointer;
    white-space:nowrap;
}

.btn-cargar-alumno:hover{
    background:rgba(0,255,204,0.12);
}
</style>
<link rel="stylesheet" href="date-inputs.css">
</head>

<body class="has-nav-sticky">

<?php
$navActivo = 'alumnos';
$tituloNav = $_SESSION['plantel_nombre'] ?? 'EduControl';
include 'nav_secundario.php';
?>

<div class="page-wrap">
<div class="main-card">

<h5 class="page-title">Lista de Alumnos — <?php echo htmlspecialchars($plantelClave, ENT_QUOTES, 'UTF-8'); ?></h5>

<div class="lista-box">
    <h6>Lista de alumnos</h6>

    <div class="lista-busqueda">
        <div class="lista-busqueda-campos">
            <div class="busqueda-campo" data-campo="cuenta">
                <label for="filtroCuenta"># Cuenta</label>
                <input type="text" id="filtroCuenta" class="filtro-input filtro-lista-valor" data-campo="cuenta"
                       list="listaCuentas" placeholder="—" autocomplete="off">
            </div>
            <div class="busqueda-campo" data-campo="nombre">
                <label for="filtroNombre">Nombre</label>
                <input type="text" id="filtroNombre" class="filtro-input filtro-lista-valor" data-campo="nombre"
                       list="listaNombres" placeholder="—" autocomplete="off">
            </div>
            <div class="busqueda-campo" data-campo="grupo">
                <label for="filtroGrupo">Grupo</label>
                <select id="filtroGrupo" class="filtro-input filtro-lista-valor" data-campo="grupo">
                    <option value="">—</option>
                    <?php foreach ($grupos as $g): ?>
                    <option value="<?php echo (int) $g['idGrupo']; ?>">
                        <?php echo htmlspecialchars($g['nombre_grupo'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="busqueda-campo" data-campo="semestre">
                <label for="filtroSemestre">Semestre</label>
                <select id="filtroSemestre" class="filtro-input filtro-lista-valor" data-campo="semestre">
                    <option value="">—</option>
                    <?php for ($s = 1; $s <= 6; $s++): ?>
                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="busqueda-campo" data-campo="generacion">
                <label for="filtroGeneracion">Generacion</label>
                <select id="filtroGeneracion" class="filtro-input filtro-lista-valor" data-campo="generacion">
                    <option value="">—</option>
                    <?php foreach ($generaciones as $gen): ?>
                    <option value="<?php echo (int) $gen['idGeneracion']; ?>">
                        <?php echo htmlspecialchars($gen['nombre_generacion'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="busqueda-campo" data-campo="estado">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado" class="filtro-input filtro-lista-valor" data-campo="estado">
                    <option value="">—</option>
                    <?php foreach ($estadosFiltro as $idEst => $label): ?>
                    <option value="<?php echo (int) $idEst; ?>">
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lista-busqueda-acciones">
                <button type="button" id="btnFiltrarLista" class="btn-crud btn-buscar" disabled>
                    <i class="bi bi-search"></i> Buscar
                </button>
                <button type="button" id="btnLimpiarFiltrosLista" class="btn-crud btn-limpiar">
                    <i class="bi bi-x-circle"></i> Limpiar
                </button>
                <button type="button" id="btnAbrirGestionAlumnos" class="btn-crud btn-gestion"
                        data-bs-toggle="modal" data-bs-target="#modalGestionAlumnos">
                    <i class="bi bi-person-gear"></i> Gestion
                </button>
            </div>
        </div>
        <datalist id="listaCuentas">
            <?php foreach ($opcionesFiltroLista['cuenta'] as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <datalist id="listaNombres">
            <?php foreach ($opcionesFiltroLista['nombre'] as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>

    <?php if (empty($alumnosLista)): ?>
    <div class="lista-vacia">No hay alumnos registrados en este plantel.</div>
    <?php else: ?>
    <div id="listaSinBusqueda" class="lista-vacia">Rellena los campos que quieras y pulsa Buscar para ver alumnos.</div>
    <div id="listaSinResultados" class="lista-vacia hidden">No se encontro ningun alumno con esos criterios.</div>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th># Cuenta</th>
                <th>Nombre completo</th>
                <th>Grupo</th>
                <th>Generacion</th>
                <th>Sem.</th>
                <th>Estado</th>
                <th>Accion</th>
            </tr>
        </thead>
        <tbody id="listaAlumnos">
        <?php foreach ($alumnosLista as $al):
            $idAl = (int) $al['idAlumno'];
            $idEst = (int) ($al['idEstado'] ?? 0);
            $nomAl = nombreCompletoAlumno($al);
            if ($nomAl === '') {
                $nomAl = 'Sin nombre';
            }
            $estadoTxt = $al['estado'] ?? nombreEstadoAlumno($idEst);
            $estadoClass = 'estado-registrado';
            if ($idEst === ESTADO_PREREGISTRADO) {
                $estadoClass = 'estado-preregistrado';
            } elseif ($idEst === ESTADO_BAJA) {
                $estadoClass = 'estado-baja';
            } elseif ($idEst === ESTADO_EGRESADO) {
                $estadoClass = 'estado-egresado';
            }
            $genTxt = $al['nombre_generacion'] ?? '—';
            if (!empty($al['fecha_inicio']) && !empty($al['fecha_fin'])) {
                $genTxt .= ' (' . (int) $al['fecha_inicio'] . '-' . (int) $al['fecha_fin'] . ')';
            }
        ?>
        <tr class="alumno-item hidden"
            data-id="<?php echo $idAl; ?>"
            data-grupo="<?php echo (int) ($al['idGrupo'] ?? 0); ?>"
            data-generacion="<?php echo (int) ($al['idGeneracion'] ?? 0); ?>"
            data-generacion-nombre="<?php echo htmlspecialchars(strtolower($al['nombre_generacion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            data-semestre="<?php echo (int) ($al['semestre'] ?? 0); ?>"
            data-grupo-nombre="<?php echo htmlspecialchars(strtolower($al['nombre_grupo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            data-estado="<?php echo $idEst; ?>"
            data-nombre="<?php echo htmlspecialchars(strtolower($nomAl), ENT_QUOTES, 'UTF-8'); ?>">
            <td class="col-cuenta"><?php echo $idAl; ?></td>
            <td class="col-nombre"><?php echo htmlspecialchars($nomAl, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($al['nombre_grupo'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($genTxt, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int) ($al['semestre'] ?? 0) ?: '—'; ?></td>
            <td><span class="estado-badge <?php echo $estadoClass; ?>"><?php echo htmlspecialchars($estadoTxt, ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td>
                <button type="button" class="btn-cargar-alumno" data-id="<?php echo $idAl; ?>">
                    <i class="bi bi-pencil-square"></i> Editar
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

</div>
</div>

<div class="modal fade modal-crud" id="modalGestionAlumnos" tabindex="-1" aria-labelledby="modalGestionAlumnosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalGestionAlumnosLabel">Gestion de alumnos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="crud-box">
                    <div class="crud-mode-toggle" role="group" aria-label="Modo de gestion">
                        <input type="radio" class="btn-check" name="crudModo" id="crudModoActualizar" autocomplete="off" checked>
                        <label for="crudModoActualizar"><i class="bi bi-search"></i> Buscar y actualizar</label>
                        <input type="radio" class="btn-check" name="crudModo" id="crudModoRegistrar" autocomplete="off">
                        <label for="crudModoRegistrar"><i class="bi bi-person-plus"></i> Registrar alumnos</label>
                        <input type="radio" class="btn-check" name="crudModo" id="crudModoMasiva" autocomplete="off">
                        <label for="crudModoMasiva"><i class="bi bi-file-earmark-spreadsheet"></i> Carga masiva</label>
                    </div>

                    <div id="crudPanelActualizar" class="crud-panel">
                    <div class="crud-buscar">
                        <div class="filtro-field">
                            <label for="buscarCuenta">Buscar por # Cuenta</label>
                            <input type="text" id="buscarCuenta" class="filtro-input" placeholder="Ej. 20231201">
                        </div>
                        <button type="button" id="btnBuscar" class="btn-crud btn-buscar">
                            <i class="bi bi-search"></i> Buscar
                        </button>
                    </div>

                    <div class="crud-form-grid">
                        <div class="crud-form-row-cuenta">
                            <div class="filtro-field crud-field-cuenta">
                                <label for="crudCuenta"># Cuenta</label>
                                <input type="number" id="crudCuenta" class="filtro-input" placeholder="Numero de cuenta" readonly>
                            </div>
                            <div class="filtro-field crud-field-nombre">
                                <label for="crudNombre">Nombre completo</label>
                                <input type="text" id="crudNombre" class="filtro-input" placeholder="Ej. Juan Pérez García">
                            </div>
                        </div>
                        <div class="crud-form-row-selects">
                            <div class="filtro-field">
                                <label for="crudGrupo">Grupo</label>
                                <select id="crudGrupo" class="filtro-input">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($grupos as $g): ?>
                                    <option value="<?php echo (int) $g['idGrupo']; ?>">
                                        <?php echo htmlspecialchars($g['nombre_grupo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filtro-field">
                                <label for="crudGeneracion">Generacion</label>
                                <select id="crudGeneracion" class="filtro-input">
                                    <option value="">Seleccionar generacion</option>
                                    <?php foreach ($generaciones as $gen): ?>
                                    <option value="<?php echo (int) $gen['idGeneracion']; ?>">
                                        <?php echo htmlspecialchars($gen['nombre_generacion'], ENT_QUOTES, 'UTF-8'); ?>
                                        (<?php echo (int) $gen['fecha_inicio']; ?>-<?php echo (int) $gen['fecha_fin']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filtro-field">
                                <label for="crudSemestre">Semestre</label>
                                <select id="crudSemestre" class="filtro-input">
                                    <option value="">Seleccionar</option>
                                    <?php for ($s = 1; $s <= 6; $s++): ?>
                                    <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="filtro-field">
                                <label for="crudEstado">Estado</label>
                                <select id="crudEstado" class="filtro-input">
                                    <?php foreach ($estadosCrud as $idEst => $label): ?>
                                    <option value="<?php echo (int) $idEst; ?>" <?php echo (int) $idEst === ESTADO_REGISTRADO ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="crud-actions">
                        <button type="button" id="btnActualizar" class="btn-crud btn-actualizar"><i class="bi bi-pencil"></i> Actualizar</button>
                        <button type="button" id="btnLimpiar" class="btn-crud btn-limpiar"><i class="bi bi-x-circle"></i> Limpiar</button>
                    </div>

                    <div id="crudMsg" class="crud-msg"></div>
                    </div>

                    <div id="crudPanelRegistrar" class="crud-panel hidden">
                    <div class="crud-register-manual">
                        <h6>Registro manual</h6>
                        <p class="crud-register-help">Completa los datos para dar de alta un alumno nuevo.</p>
                        <div class="crud-form-grid">
                            <div class="crud-form-row-cuenta">
                                <div class="filtro-field crud-field-cuenta">
                                    <label for="regCuenta"># Cuenta</label>
                                    <input type="number" id="regCuenta" class="filtro-input" placeholder="Numero de cuenta">
                                </div>
                                <div class="filtro-field crud-field-nombre">
                                    <label for="regNombre">Nombre completo</label>
                                    <input type="text" id="regNombre" class="filtro-input" placeholder="Ej. Juan Pérez García">
                                </div>
                            </div>
                            <div class="crud-form-row-selects">
                                <div class="filtro-field">
                                    <label for="regGrupo">Grupo</label>
                                    <select id="regGrupo" class="filtro-input">
                                        <option value="">Seleccionar</option>
                                        <?php foreach ($grupos as $g): ?>
                                        <option value="<?php echo (int) $g['idGrupo']; ?>">
                                            <?php echo htmlspecialchars($g['nombre_grupo'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filtro-field">
                                    <label for="regGeneracion">Generacion</label>
                                    <select id="regGeneracion" class="filtro-input">
                                        <option value="">Seleccionar generacion</option>
                                        <?php foreach ($generaciones as $gen): ?>
                                        <option value="<?php echo (int) $gen['idGeneracion']; ?>"
                                            <?php echo (int) $gen['idGeneracion'] === $idGeneracionActiva ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($gen['nombre_generacion'], ENT_QUOTES, 'UTF-8'); ?>
                                            (<?php echo (int) $gen['fecha_inicio']; ?>-<?php echo (int) $gen['fecha_fin']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filtro-field">
                                    <label for="regSemestre">Semestre</label>
                                    <select id="regSemestre" class="filtro-input">
                                        <option value="">Seleccionar</option>
                                        <?php for ($s = 1; $s <= 6; $s++): ?>
                                        <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="filtro-field">
                                    <label for="regEstado">Estado</label>
                                    <select id="regEstado" class="filtro-input">
                                        <?php foreach ($estadosCrud as $idEst => $label): ?>
                                        <option value="<?php echo (int) $idEst; ?>" <?php echo (int) $idEst === ESTADO_REGISTRADO ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="crud-actions">
                            <button type="button" id="btnRegistrar" class="btn-crud btn-alta"><i class="bi bi-person-plus"></i> Registrar</button>
                            <button type="button" id="btnLimpiarRegistro" class="btn-crud btn-limpiar"><i class="bi bi-x-circle"></i> Limpiar</button>
                        </div>
                        <div id="regMsg" class="crud-msg"></div>
                    </div>
                    </div>

                    <div id="crudPanelMasiva" class="crud-panel hidden">
                    <div class="crud-import">
                        <h6>Carga masiva desde Excel</h6>
                        <p class="crud-import-help">
                            Usa archivos <strong>.xlsx</strong> o <strong>.csv</strong> con columnas como:
                            <strong>NO#CUENTA</strong>, <strong>NOMBRE DEL ALUMNO</strong>, <strong>SEMESTRE</strong>, <strong>GRUPO</strong>
                            y opcional <strong>STATUS</strong>.
                        </p>
                        <div class="crud-import-grid">
                            <div class="filtro-field">
                                <label for="archivoImportAlumnos">Archivo</label>
                                <input type="file" id="archivoImportAlumnos" class="filtro-input" accept=".xlsx,.xls,.csv">
                            </div>
                            <div class="filtro-field">
                                <label for="importGeneracion">Generacion</label>
                                <select id="importGeneracion" class="filtro-input">
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($generaciones as $gen): ?>
                                    <option value="<?php echo (int) $gen['idGeneracion']; ?>"
                                        <?php echo (int) $gen['idGeneracion'] === $idGeneracionActiva ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($gen['nombre_generacion'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filtro-field">
                                <label for="importEstado">Estado default</label>
                                <select id="importEstado" class="filtro-input">
                                    <?php foreach ($estadosCrud as $idEst => $label): ?>
                                    <option value="<?php echo (int) $idEst; ?>" <?php echo (int) $idEst === ESTADO_REGISTRADO ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" id="btnImportarAlumnos" class="btn-crud btn-importar">
                                <i class="bi bi-file-earmark-spreadsheet"></i> Importar
                            </button>
                        </div>
                        <div id="importMsg" class="import-msg"></div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const crudMsg = document.getElementById("crudMsg");
const buscarCuenta = document.getElementById("buscarCuenta");
const crudCuenta = document.getElementById("crudCuenta");
const btnFiltrarLista = document.getElementById("btnFiltrarLista");
const btnLimpiarFiltrosLista = document.getElementById("btnLimpiarFiltrosLista");
const filtrosListaValores = document.querySelectorAll(".filtro-lista-valor");
const modalGestionAlumnos = document.getElementById("modalGestionAlumnos");
const crudModoActualizar = document.getElementById("crudModoActualizar");
const crudModoRegistrar = document.getElementById("crudModoRegistrar");
const crudModoMasiva = document.getElementById("crudModoMasiva");
const crudPanelActualizar = document.getElementById("crudPanelActualizar");
const crudPanelRegistrar = document.getElementById("crudPanelRegistrar");
const crudPanelMasiva = document.getElementById("crudPanelMasiva");
const regMsg = document.getElementById("regMsg");

function cambiarModoCrud() {
    const modo = crudModoActualizar?.checked ? "actualizar"
        : crudModoRegistrar?.checked ? "registrar"
        : "masiva";
    crudPanelActualizar?.classList.toggle("hidden", modo !== "actualizar");
    crudPanelRegistrar?.classList.toggle("hidden", modo !== "registrar");
    crudPanelMasiva?.classList.toggle("hidden", modo !== "masiva");
}

crudModoActualizar?.addEventListener("change", cambiarModoCrud);
crudModoRegistrar?.addEventListener("change", cambiarModoCrud);
crudModoMasiva?.addEventListener("change", cambiarModoCrud);

function abrirModalGestion(opciones = {}) {
    if (!modalGestionAlumnos) return;
    if (opciones.modo === "registrar" && crudModoRegistrar) {
        crudModoRegistrar.checked = true;
    } else if (opciones.modo === "masiva" && crudModoMasiva) {
        crudModoMasiva.checked = true;
    } else if (crudModoActualizar) {
        crudModoActualizar.checked = true;
    }
    cambiarModoCrud();
    bootstrap.Modal.getOrCreateInstance(modalGestionAlumnos).show();
}

function mostrarRegMsg(texto, ok) {
    if (!regMsg) return;
    regMsg.textContent = texto;
    regMsg.className = "crud-msg " + (ok ? "ok" : "error");
}

function limpiarRegistro() {
    document.getElementById("regCuenta").value = "";
    document.getElementById("regNombre").value = "";
    document.getElementById("regGrupo").value = "";
    document.getElementById("regGeneracion").value = "<?php echo (int) $idGeneracionActiva; ?>";
    document.getElementById("regSemestre").value = "";
    document.getElementById("regEstado").value = "<?php echo ESTADO_REGISTRADO; ?>";
    if (regMsg) {
        regMsg.className = "crud-msg";
        regMsg.textContent = "";
    }
}

function datosRegistro() {
    return {
        idAlumno: document.getElementById("regCuenta").value.trim(),
        nombre: document.getElementById("regNombre").value.trim(),
        idGrupo: document.getElementById("regGrupo").value,
        idGeneracion: document.getElementById("regGeneracion").value,
        semestre: document.getElementById("regSemestre").value,
        idEstado: document.getElementById("regEstado").value
    };
}

function enviarRegistro() {
    const fd = new FormData();
    fd.append("accion", "crear");
    Object.entries(datosRegistro()).forEach(([k, v]) => fd.append(k, v));
    return fetch("crud_alumno.php", { method: "POST", body: fd }).then(res => res.json());
}

function mostrarCrudMsg(texto, ok) {
    crudMsg.textContent = texto;
    crudMsg.className = "crud-msg " + (ok ? "ok" : "error");
}

function limpiarCrud() {
    buscarCuenta.value = "";
    crudCuenta.value = "";
    document.getElementById("crudNombre").value = "";
    document.getElementById("crudGrupo").value = "";
    document.getElementById("crudGeneracion").value = "";
    document.getElementById("crudSemestre").value = "";
    document.getElementById("crudEstado").value = "<?php echo ESTADO_REGISTRADO; ?>";
    crudMsg.className = "crud-msg";
    crudMsg.textContent = "";
    ocultarListaAlumnos();
}

function datosCrud() {
    return {
        idAlumno: crudCuenta.value.trim(),
        nombre: document.getElementById("crudNombre").value.trim(),
        idGrupo: document.getElementById("crudGrupo").value,
        idGeneracion: document.getElementById("crudGeneracion").value,
        semestre: document.getElementById("crudSemestre").value,
        idEstado: document.getElementById("crudEstado").value
    };
}

function llenarFormulario(alumno) {
    crudCuenta.value = alumno.idAlumno || "";
    document.getElementById("crudNombre").value = alumno.nombre || "";
    document.getElementById("crudGrupo").value = alumno.idGrupo ? String(alumno.idGrupo) : "";
    document.getElementById("crudGeneracion").value = alumno.idGeneracion ? String(alumno.idGeneracion) : "";
    document.getElementById("crudSemestre").value = alumno.semestre ? String(alumno.semestre) : "";
    document.getElementById("crudEstado").value = String(alumno.idEstado || "<?php echo ESTADO_REGISTRADO; ?>");
    buscarCuenta.value = alumno.idAlumno || "";
}

function enviarCrud(accion, extra = {}) {
    const fd = new FormData();
    fd.append("accion", accion);
    const datos = datosCrud();
    Object.entries({ ...datos, ...extra }).forEach(([k, v]) => fd.append(k, v));

    return fetch("crud_alumno.php", { method: "POST", body: fd }).then(res => res.json());
}

function buscarAlumno() {
    const id = buscarCuenta.value.trim() || crudCuenta.value.trim();
    if (!id) {
        mostrarCrudMsg("Ingresa un numero de cuenta para buscar", false);
        return;
    }

    crudCuenta.value = id;

    const fd = new FormData();
    fd.append("accion", "buscar");
    fd.append("idAlumno", id);

    fetch("crud_alumno.php", { method: "POST", body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                llenarFormulario(data.alumno);
                mostrarAlumnoListaPorId(data.alumno.idAlumno);
                mostrarCrudMsg("Alumno encontrado — Estado: " + (data.alumno.estado || ""), true);
            } else {
                limpiarCrud();
                buscarCuenta.value = id;
                ocultarListaAlumnos();
                mostrarCrudMsg(data.mensaje, false);
            }
        })
        .catch(() => mostrarCrudMsg("Error al buscar el alumno", false));
}

document.getElementById("btnBuscar")?.addEventListener("click", buscarAlumno);
buscarCuenta?.addEventListener("keydown", e => { if (e.key === "Enter") buscarAlumno(); });

const importMsg = document.getElementById("importMsg");
const archivoImportAlumnos = document.getElementById("archivoImportAlumnos");

function mostrarImportMsg(texto, ok) {
    if (!importMsg) return;
    importMsg.textContent = texto;
    importMsg.className = "import-msg " + (ok ? "ok" : "error");
}

document.getElementById("btnImportarAlumnos")?.addEventListener("click", () => {
    const archivo = archivoImportAlumnos?.files?.[0];
    const idGeneracion = document.getElementById("importGeneracion")?.value || "";
    const idEstado = document.getElementById("importEstado")?.value || "<?php echo ESTADO_REGISTRADO; ?>";

    if (!archivo) {
        mostrarImportMsg("Selecciona un archivo Excel (.xlsx) o CSV", false);
        return;
    }
    if (!idGeneracion) {
        mostrarImportMsg("Selecciona la generacion para los alumnos importados", false);
        return;
    }

    const fd = new FormData();
    fd.append("archivo", archivo);
    fd.append("idGeneracion", idGeneracion);
    fd.append("idEstado", idEstado);

    mostrarImportMsg("Importando alumnos, espera un momento...", true);

    fetch("importar_alumnos_excel.php", { method: "POST", body: fd })
        .then(res => res.json())
        .then(data => {
            mostrarImportMsg(data.mensaje || "Importacion finalizada", !!data.ok);
            if (data.ok) {
                setTimeout(() => location.reload(), 1800);
            }
        })
        .catch(() => mostrarImportMsg("Error al importar el archivo", false));
});

function normalizarTexto(txt) {
    return (txt || "").trim().toLowerCase().replace(/\s+/g, " ");
}

function valorFiltroLista(campo) {
    const input = document.querySelector(`.filtro-lista-valor[data-campo="${campo}"]`);
    return (input?.value || "").trim();
}

function hayFiltrosActivos() {
    return ["cuenta", "nombre", "grupo", "generacion", "semestre", "estado"]
        .some(campo => valorFiltroLista(campo) !== "");
}

function actualizarEstadoFiltrosLista() {
    if (btnFiltrarLista) {
        btnFiltrarLista.disabled = !hayFiltrosActivos();
    }
}

function alumnoCoincideFiltros(item) {
    const cuenta = valorFiltroLista("cuenta");
    if (cuenta && String(item.dataset.id) !== cuenta) {
        return false;
    }

    const nombre = valorFiltroLista("nombre");
    if (nombre) {
        const busqueda = normalizarTexto(nombre);
        const nombreAlumno = normalizarTexto(item.dataset.nombre);
        if (!nombreAlumno.includes(busqueda)) {
            return false;
        }
    }

    const grupo = valorFiltroLista("grupo");
    if (grupo && item.dataset.grupo !== grupo) {
        return false;
    }

    const generacion = valorFiltroLista("generacion");
    if (generacion && item.dataset.generacion !== generacion) {
        return false;
    }

    const semestre = valorFiltroLista("semestre");
    if (semestre && item.dataset.semestre !== semestre) {
        return false;
    }

    const estado = valorFiltroLista("estado");
    if (estado && item.dataset.estado !== estado) {
        return false;
    }

    return true;
}

function ocultarListaAlumnos() {
    document.querySelectorAll("#listaAlumnos .alumno-item").forEach(item => {
        item.classList.add("hidden");
    });
    document.getElementById("listaSinBusqueda")?.classList.remove("hidden");
    document.getElementById("listaSinResultados")?.classList.add("hidden");
}

function mostrarAlumnoListaPorId(id) {
    if (!id) return;
    document.querySelectorAll("#listaAlumnos .alumno-item").forEach(item => {
        item.classList.add("hidden");
    });
    const item = document.querySelector(`#listaAlumnos .alumno-item[data-id="${id}"]`);
    if (item) {
        item.classList.remove("hidden");
        document.getElementById("listaSinBusqueda")?.classList.add("hidden");
        document.getElementById("listaSinResultados")?.classList.add("hidden");
    } else {
        document.getElementById("listaSinBusqueda")?.classList.add("hidden");
        document.getElementById("listaSinResultados")?.classList.remove("hidden");
    }
}

function limpiarFiltrosLista() {
    filtrosListaValores.forEach(input => {
        input.value = "";
    });
    if (btnFiltrarLista) btnFiltrarLista.disabled = true;
    ocultarListaAlumnos();
}

filtrosListaValores.forEach(input => {
    input.addEventListener("input", actualizarEstadoFiltrosLista);
    input.addEventListener("change", actualizarEstadoFiltrosLista);
    input.addEventListener("keydown", e => {
        if (e.key === "Enter" && !btnFiltrarLista?.disabled) {
            filtrarListaAlumnos();
        }
    });
});

ocultarListaAlumnos();
actualizarEstadoFiltrosLista();

function filtrarListaAlumnos() {
    if (!hayFiltrosActivos()) {
        ocultarListaAlumnos();
        return;
    }

    let visibles = 0;

    document.querySelectorAll("#listaAlumnos .alumno-item").forEach(item => {
        const mostrar = alumnoCoincideFiltros(item);
        item.classList.toggle("hidden", !mostrar);
        if (mostrar) visibles++;
    });

    document.getElementById("listaSinBusqueda")?.classList.add("hidden");
    document.getElementById("listaSinResultados")?.classList.toggle("hidden", visibles > 0);
}

btnFiltrarLista?.addEventListener("click", filtrarListaAlumnos);
btnLimpiarFiltrosLista?.addEventListener("click", limpiarFiltrosLista);

document.querySelectorAll(".btn-cargar-alumno").forEach(btn => {
    btn.addEventListener("click", () => {
        const id = btn.dataset.id;
        if (!id) return;
        buscarCuenta.value = id;
        abrirModalGestion();
        buscarAlumno();
    });
});

document.getElementById("btnRegistrar")?.addEventListener("click", () => {
    enviarRegistro().then(data => {
        mostrarRegMsg(data.mensaje, data.ok);
        if (data.ok) {
            setTimeout(() => location.reload(), 1200);
        }
    }).catch(() => mostrarRegMsg("Error al registrar el alumno", false));
});

document.getElementById("btnLimpiarRegistro")?.addEventListener("click", limpiarRegistro);

document.getElementById("btnActualizar")?.addEventListener("click", () => {
    if (!crudCuenta.value.trim()) {
        mostrarCrudMsg("Busca un alumno existente antes de actualizar", false);
        return;
    }
    enviarCrud("actualizar").then(data => {
        mostrarCrudMsg(data.mensaje, data.ok);
        if (data.ok) setTimeout(() => location.reload(), 1200);
    }).catch(() => mostrarCrudMsg("Error al actualizar el alumno", false));
});

document.getElementById("btnLimpiar")?.addEventListener("click", limpiarCrud);
</script>

</body>
</html>
