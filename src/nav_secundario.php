<?php

$navActivo = $navActivo ?? '';
$tituloNav = $tituloNav ?? ($_SESSION['plantel_nombre'] ?? 'EduControl');
$navAccionesExtra = $navAccionesExtra ?? '';

$links = [
    'inicio' => ['href' => 'panel.php', 'label' => 'Inicio'],
    'alumnos' => ['href' => 'alumnos.php', 'label' => 'Lista De Alumnos'],
    'generaciones' => ['href' => 'generaciones.php', 'label' => 'Generaciones'],
    'asistencias' => ['href' => 'asistencias.php', 'label' => 'Asistencias'],
];
?>
<link rel="stylesheet" href="nav_secundario.css">

<header class="nav-sticky">
    <div class="nav-sticky-inner">
        <div class="nav-sticky-brand">
            <span class="nav-sticky-logo-wrap">
                <img src="logo-udec.png" alt="Universidad de Colima" class="nav-sticky-logo">
            </span>
            <span class="nav-sticky-title"><?php echo htmlspecialchars($tituloNav, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="nav-sticky-right">
            <nav class="nav-sticky-links">
                <?php foreach ($links as $clave => $link): ?>
                <a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>"
                   <?php echo $navActivo === $clave ? 'class="active"' : ''; ?>>
                    <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($navAccionesExtra !== ''): ?>
            <div class="nav-sticky-actions">
                <?php echo $navAccionesExtra; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>
