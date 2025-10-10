<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Cargar configuracion de empresa si no esta disponible aun
if (!isset($empresa)) {
    require_once __DIR__ . '/titulos.php';
}

// URL base unificada
require_once __DIR__ . '/bootstrap.php';
if (!defined('BASE_URL')) {
  define('BASE_URL', '/');
}

// Forzar UTF-8 para evitar caracteres corruptos
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
// Estado de autenticacion y rol normalizado
$esAutenticado = isset($_SESSION['usuario_id']);
$rolBruto = $_SESSION['usuario_rol'] ?? '';
$rolClave = strtolower(is_string($rolBruto) ? trim($rolBruto) : '');
$mapRoles = [
  'tripulante' => 'Tripulacion',
  'tripulacion' => 'Tripulacion',
  'administrativo' => 'Administrativo',
  'administrativa' => 'Administrativo',
  'secretaria' => 'Administrativo',
  'master' => 'Master'
];
$rol = $mapRoles[$rolClave] ?? null;
$puedeCrearPrincipal = $esAutenticado && in_array($rol, ['Tripulacion','Master'], true);
$puedeConsultar = $esAutenticado;
$puedeMenuAdmin = $esAutenticado && in_array($rol, ['Administrativo','Master'], true);
$esMaster = ($rol === 'Master');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : htmlspecialchars($empresa['titulo_general']) ?></title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>favicon.ico">
  <!-- Bootstrap / Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <!-- Tema oscuro -->
  <link href="<?= BASE_URL ?>style-dark.css" rel="stylesheet" />
  <!-- Estilos minimos propios (limpios, no reescriben Bootstrap innecesariamente) -->
  <link href="<?= BASE_URL ?>app.css" rel="stylesheet" />

  <!-- Libs -->
  <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet" />
  <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <!-- Select2 (usado para CIE-10 e IPS) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/signature_pad/4.1.7/signature_pad.umd.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

  <style>
    .site-header{
      position: sticky;
      top: 0;
      z-index: 1040;
      margin-top: 0;
      background-color: var(--bs-body-bg, #fff);
      transition: box-shadow .25s ease, background-color .25s ease;
    }
    /* compacted */ .site-header .navbar{
      padding: .6rem 0;
      transition: padding .25s ease;
    }
    /* logo size */ .site-header .site-logo{
      max-width: 120px;
      transition: max-width .25s ease;
    }
    /* nav link */ .site-header .navbar-nav .nav-link{
      padding: .45rem .6rem;
      transition: padding .25s ease;
    }
    .site-header.is-compact{
      box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, 0.12);
      background-color: rgba(255,255,255,0.92);
    }
    [data-bs-theme="dark"] .site-header.is-compact{
      background-color: rgba(4,7,12,0.9);
      box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, 0.6);
    }
    /* more compact */ .site-header.is-compact .navbar{
      padding: .35rem 0;
    }
    /* logo compact */ .site-header.is-compact .site-logo{
      max-width: 90px;
    }
    /* link compact */ .site-header.is-compact .navbar-nav .nav-link{
      padding: .35rem .5rem;
    }
  </style>
  <script>
    // Pasamos la URL base de PHP a JavaScript
    const AppBaseUrl = '<?= rtrim(BASE_URL, "/") . "/" ?>';

    // Init de tema (usa localStorage)
    (function() {
      const getStoredTheme = () => localStorage.getItem('theme');
      const setStoredTheme = t => localStorage.setItem('theme', t);
      const getPreferredTheme = () => getStoredTheme() || 'light';
      const setTheme = t => document.documentElement.setAttribute('data-bs-theme', t);
      setTheme(getPreferredTheme());
      window.__setTheme = (t) => { setTheme(t); setStoredTheme(t); };
    })();
  </script>
</head>
<div class="container-fluid px-0">
  <header class="site-header text-center mb-4">
    <nav class="navbar navbar-expand-lg">
      <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>index.php">
          <img class="img-fluid mb-2 site-logo" src="<?= BASE_URL . htmlspecialchars($empresa['logo_principal']) ?>" alt="<?= htmlspecialchars($empresa['nombre']) ?>" title="<?= htmlspecialchars($empresa['nombre']) ?> 2025">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <?php if ($esAutenticado): ?>
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <?php if ($puedeCrearPrincipal): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php"><i class="bi bi-file-earmark-text"></i> Nuevo</a></li>
            <?php endif; ?>
            <?php if ($puedeConsultar): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>consulta_atenciones.php"><i class="bi bi-search"></i> Consultar</a></li>
            <?php endif; ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-globe2"></i></a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="inventario_ambulancias.php">Inventario</a></li>
                <li><a class="dropdown-item" href="consultar_inventario.php">Consultar Inventario</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Contacto</a></li>
              </ul>
            </li>
            <?php if ($puedeMenuAdmin): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-gear"></i> Admin</a>
              <ul class="dropdown-menu">
                <?php if ($esMaster): ?>
                  <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/contrato.php">Contrato (Empresa/FURTRAN)</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/gestion_usuarios.php">Gestionar Usuarios</a></li>
                <?php if ($esMaster): ?>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/backup_handler.php?accion=generar" onclick="return confirm('Â¿Generar un nuevo backup de la base de datos?');">Generar Backup</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/backup_list.php">Ver Backups</a></li>
              </ul>
            </li>
            <?php endif; ?>
          </ul>

          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
            <?php if (!empty($_SESSION['usuario_nombre'])): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>mi_perfil.php">Hola, <?= htmlspecialchars(explode(' ', $_SESSION['usuario_nombre'])[0]) ?></a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>
            </li>
            <?php endif; ?>
          </ul>
          <?php else: ?>
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Ingresar</a></li>
          </ul>
          <?php endif; ?>


          <button id="theme-switcher" type="button" class="btn btn-outline-secondary ms-2">
            <i class="bi bi-sun-fill theme-icon-active" aria-hidden="true"></i>
          </button>
          <img alt="Vigilado Supersalud" class="img-fluid ms-2" src="<?= BASE_URL . htmlspecialchars($empresa['logo_vigilado']) ?>" style="max-width:100px;">
        </div>
      </div>
    </nav>
  </header>
  <div class="form-container">
    <h2 class="text-center form-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : htmlspecialchars($empresa['titulo_general']) ?></h2>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const themeSwitcher = document.getElementById('theme-switcher');
  const themeIcon = themeSwitcher ? themeSwitcher.querySelector('.theme-icon-active') : null;
  const getStoredTheme = () => localStorage.getItem('theme');
  const applyIcon = (theme) => {
    if (!themeIcon) return;
    themeIcon.classList.remove('bi-sun-fill', 'bi-moon-stars-fill');
    themeIcon.classList.add(theme === 'dark' ? 'bi-moon-stars-fill' : 'bi-sun-fill');
  };
  applyIcon(getStoredTheme() === 'dark' ? 'dark' : 'light');
  if (themeSwitcher) {
    themeSwitcher.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-bs-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      applyIcon(newTheme);
    });
  }
  const siteHeader = document.querySelector('.site-header');
  if (siteHeader) {
    const compactThreshold = 40;
    const updateHeader = () => {
      if ((window.scrollY || 0) > compactThreshold) {
        siteHeader.classList.add('is-compact');
      } else {
        siteHeader.classList.remove('is-compact');
      }
    };
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
  }
});
</script>
<script>
    // Configuración de la URL base del proyecto
    window.AppBaseUrl = '<?= rtrim(BASE_URL, "/") . "/" ?>';
</script>











