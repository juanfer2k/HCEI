<?php
// Cargar configuración de empresa si no está disponible aún
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

// --- Soporte para carga de Assets con Fallback a CDN ---
function load_asset($localPath, $cdnUrl, $integrity = null, $crossorigin = 'anonymous') {
    $absoluteLocalPath = $_SERVER['DOCUMENT_ROOT'] . $localPath;
    $assetUrl = file_exists($absoluteLocalPath) ? BASE_URL . ltrim($localPath, '/') : $cdnUrl;
    
    $integrityAttr = $integrity ? "integrity='$integrity'" : '';
    $crossoriginAttr = $crossorigin ? "crossorigin='$crossorigin'" : '';

    if (pathinfo($assetUrl, PATHINFO_EXTENSION) === 'css') {
        echo "<link href='$assetUrl' rel='stylesheet' $integrityAttr $crossoriginAttr>";
    } else {
        echo "<script src='$assetUrl' $integrityAttr $crossoriginAttr></script>";
    }
}
// --------------------------------------------------------

// Estado de autenticación y rol normalizado
$esAutenticado = isset($_SESSION['usuario_id']);
$rolBruto = $_SESSION['usuario_rol'] ?? '';
$rolClave = strtolower(is_string($rolBruto) ? trim($rolBruto) : '');
$mapRoles = [
  'tripulante' => 'Tripulacion',
  'tripulacion' => 'Tripulacion',
  'administrativo' => 'Administrativo',
  'administrativa' => 'Administrativo',
  'secretaria' => 'Administrativo',
  'master' => 'Master',
  'dev' => 'Master'
];
$rol = $mapRoles[$rolClave] ?? null;

// Evitar que un tripulante abra un nuevo registro si ya tiene una atención activa sin finalizar
$tieneAtencionActiva = false;
if ($esAutenticado && $rol === 'Tripulacion' && isset($conn) && $conn instanceof mysqli) {
  $ccUsuario = $_SESSION['usuario_cc'] ?? ($_SESSION['usuario_identificacion'] ?? null);
  if ($ccUsuario) {
    if ($stmtAct = $conn->prepare("SELECT id FROM atenciones WHERE cc_tripulante = ? AND (hora_final IS NULL OR hora_final = '00:00:00') AND (estado_registro IS NULL OR estado_registro <> 'ANULADA') LIMIT 1")) {
      $stmtAct->bind_param('s', $ccUsuario);
      $stmtAct->execute();
      $stmtAct->store_result();
      $tieneAtencionActiva = $stmtAct->num_rows > 0;
      $stmtAct->close();
    }
  }
}

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

  <!-- Estilos principales -->
  <?php load_asset('js/libs/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="<?= BASE_URL ?>css/style-dark.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>css/app.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>css/form.css" rel="stylesheet" />

  <!-- Librerías adicionales -->
  <?php load_asset('js/libs/flatpickr.min.css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css'); ?>
  <?php load_asset('js/libs/select2.min.css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'); ?>
  <?php load_asset('js/libs/jquery.min.js', 'https://code.jquery.com/jquery-3.7.1.min.js', 'sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo='); ?>
  <?php load_asset('js/libs/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'); ?>
  <?php load_asset('js/libs/flatpickr.min.js', 'https://cdn.jsdelivr.net/npm/flatpickr'); ?>
  <script src="<?= BASE_URL ?>js/libs/flatpickr.es.js"></script>
  <?php load_asset('js/libs/select2.min.js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js'); ?>
  <?php load_asset('js/libs/moment-with-locales.min.js', 'https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment-with-locales.min.js'); ?>
  <?php load_asset('js/libs/signature_pad.umd.min.js', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js'); ?>

  <!-- Configuración global -->
  <script>
    window.AppConfig = {
      baseUrl: '<?= rtrim(BASE_URL, "/") . "/" ?>',
      roles: {
        isTripulacion: <?= json_encode($rol === 'Tripulacion') ?>,
        isMaster: <?= json_encode($esMaster) ?>,
        isAdmin: <?= json_encode($rol === 'Administrativo') ?>
      }
    };
    window.AppBaseUrl = window.AppConfig.baseUrl;
  </script>

  <!-- Scripts base -->
  <?php
    $themePathFs = __DIR__ . '/js/theme.js';
    $themeVer = file_exists($themePathFs) ? filemtime($themePathFs) : time();
  ?>
  <script src="<?= BASE_URL ?>js/theme.js?v=<?= $themeVer ?>" defer></script>
  <script>
    // Botón para limpiar datos locales del wizard de atención (solo UI)
    document.addEventListener('DOMContentLoaded', function() {
      const btn = document.getElementById('clearWizardStorageBtn');
      if (!btn || !window.localStorage) return;

      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        if (!window.confirm('Esto eliminará los datos locales del asistente de atención (pasos guardados y borradores). ¿Continuar?')) {
          return;
        }
        try {
          const keysFijas = ['atencion_id', 'form_autosave_data'];
          const prefijos = ['wizard_current_step_', 'section_'];

          for (let i = 0; i < localStorage.length; ) {
            const key = localStorage.key(i);
            if (!key) { i++; continue; }
            const matchFija = keysFijas.includes(key);
            const matchPref = prefijos.some(p => key.indexOf(p) === 0);
            if (matchFija || matchPref) {
              localStorage.removeItem(key);
              // No incrementamos i porque la longitud cambia
              continue;
            }
            i++;
          }
          alert('Datos locales del asistente de atención limpiados.');
        } catch (e) {
          console.error('No se pudo limpiar localStorage del wizard', e);
          alert('No se pudo limpiar los datos locales. Revise la consola para más detalles.');
        }
      });
    });
  </script>

  <?php if (in_array(basename($_SERVER['PHP_SELF']), ['index.php', 'index_v4.php'])): ?>
  <!-- Scripts del formulario principal -->
  <script>
    window.addEventListener('load', function() {
      const deps = {
        'jQuery': window.jQuery, 'SignaturePad': window.SignaturePad,
        'flatpickr': window.flatpickr, 'moment': window.moment,
        'select2': (window.jQuery && window.jQuery.fn.select2)
      };
      for (const [name, check] of Object.entries(deps)) {
        if (!check) console.error(`${name} no está disponible. El formulario puede no funcionar.`);
      }
    });
  </script>
  <script src="<?= BASE_URL ?>js/form-signatures.js?v=<?= time() ?>" defer></script>
  <script src="<?= BASE_URL ?>js/debug.js?v=<?= time() ?>" defer></script>
  <?php endif; ?>

  <!-- JSON-LD para SEO -->
  <script type="application/ld+json">
  <?= json_encode([
      "@context" => "https://schema.org",
      "@type" => "Organization",
      "name" => $empresa['nombre'] ?? '',
      "url" => (isset($empresa['web']) && !empty($empresa['web'])) ? (strpos($empresa['web'], 'http') === 0 ? $empresa['web'] : 'https://' . $empresa['web']) : BASE_URL,
      "logo" => (isset($empresa['logo_principal']) ? rtrim(BASE_URL, '/') . '/' . $empresa['logo_principal'] : ''),
      "address" => ["@type" => "PostalAddress", "streetAddress" => $empresa['direccion'] ?? ''],
      "contactPoint" => [["@type" => "ContactPoint", "telephone" => $empresa['telefono'] ?? '', "contactType" => "customer service", "areaServed" => "CO"]]
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
  </script>

  <style>
    :root {
      /* Base service colors aligned with v3 */
      --color-tab: #0d6efd;      /* Azul TAB */
      --color-tam: #d80f17;      /* Rojo TAM (rgb(216,15,23)) */
      --color-aph: #d9d900;      /* Amarillo APH */
    }
    .site-header { position: sticky; top: 0; z-index: 1040; background-color: var(--bs-body-bg, #fff); transition: box-shadow .25s ease, background-color .25s ease; }
    .site-header .navbar { padding: .2rem 0; transition: padding .25s ease; }
    .site-header .site-logo { max-width: 120px; transition: max-width .25s ease; }
    .site-header .navbar-nav .nav-link { padding: .45rem .6rem; }
    .site-header.is-compact { box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, 0.12); background-color: rgba(255,255,255,0.92); }
    [data-bs-theme="dark"] .site-header.is-compact { background-color: rgba(4,7,12,0.9); box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, 0.6); }
    .site-header.is-compact .navbar { padding: .2rem 0; }
    .site-header.is-compact .site-logo { max-width: 60px; }
    .site-header.is-compact .navbar-nav .nav-link { padding: .25rem .45rem; }
  </style>
</head>
<body>
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
            <li class="nav-item">
              <?php if ($tieneAtencionActiva && $rol === 'Tripulacion'): ?>
                <a class="nav-link disabled text-muted" href="#" tabindex="-1" aria-disabled="true" title="Ya tienes una atención en curso. Finalízala antes de iniciar una nueva.">
                  <i class="bi bi-file-earmark-plus-fill"></i> Nuevo
                </a>
              <?php else: ?>
                <a class="nav-link" href="<?= BASE_URL ?>index.php"><i class="bi bi-file-earmark-plus-fill"></i> Nuevo</a>
              <?php endif; ?>
            </li>
            <?php endif; ?>
            <?php if ($puedeConsultar): ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>consulta_atenciones.php"><i class="bi bi-search"></i> Consultar</a></li>
            <?php endif; ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-safe-fill"></i> Inventario</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>inventario_ambulancias.php"><i class="bi bi-clipboard-data-fill"></i> Registrar en Inventario</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>consultar_inventario.php"><i class="bi bi-bag-plus-fill"></i> Consultar Inventario</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-question-circle-fill"></i> Contacto</a></li>
              </ul>
            </li>
          </ul>

          <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
            <button id="theme-switcher" type="button" class="btn btn-outline-secondary ms-2">
              <i class="bi bi-sun-fill theme-icon-active"></i>
              <span class="visually-hidden">Cambiar tema</span>
            </button>
            <?php if (!empty($_SESSION['usuario_nombre'])): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>mi_perfil.php"><i class="bi bi-person-circle"></i> Hola, <?= htmlspecialchars(explode(' ', $_SESSION['usuario_nombre'])[0]) ?></a>
            </li>
            <?php if ($rol === 'Tripulacion'): ?>
            <li class="nav-item">
              <button id="clearWizardStorageBtn" type="button" class="btn btn-link nav-link px-0 small text-decoration-underline text-muted">
                <i class="bi bi-trash3"></i> Limpiar datos locales
              </button>
            </li>
            <?php endif; ?>
            <?php if ($puedeMenuAdmin): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>admin/panel.php"><i class="bi bi-gear-fill"></i> Admin</a>
            </li>
            <?php endif; ?>
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
        </div>
      </div>
    </nav>
  </header>

  <div class="form-container">
    <h2 class="text-center form-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : htmlspecialchars($empresa['titulo_general']) ?></h2>
