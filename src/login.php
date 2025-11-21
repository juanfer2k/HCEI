<?php
ob_start();
require_once __DIR__ . '/bootstrap.php';

// Si ya hay una sesión activa, redirigir según rol
if (isset($_SESSION['usuario_id'])) {
    $rolActual = $_SESSION['usuario_rol'] ?? '';
    if ($rolActual === 'Master' || $rolActual === 'Administrativo') {
        header('Location: ' . BASE_URL . 'admin/panel.php');
    } else {
        header('Location: ' . BASE_URL . 'index.php');
    }
    exit;
}

require 'conn.php';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $clave = $_POST['clave'] ?? '';

    if (empty($usuario) || empty($clave)) {
        $error_message = "Por favor, ingrese usuario y contraseña.";
    } else {
        $stmt = $conn->prepare("SELECT id, nombres, apellidos, id_cc, id_registro, clave, rol FROM tripulacion WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $is_password_correct = password_verify($clave, $user['clave']);

            // Compatibilidad con hashes antiguos (función PASSWORD() de MySQL)
            if (!$is_password_correct && strpos($user['clave'], '*') === 0) {
                $stmt_old_pass = $conn->prepare("SELECT PASSWORD(?) as old_hash");
                $stmt_old_pass->bind_param("s", $clave);
                $stmt_old_pass->execute();
                $res_old_pass = $stmt_old_pass->get_result()->fetch_assoc();
                if ($res_old_pass['old_hash'] === $user['clave']) {
                    $is_password_correct = true;
                    $new_hash = password_hash($clave, PASSWORD_DEFAULT);
                    $conn->query("UPDATE tripulacion SET clave = '{$new_hash}' WHERE id = {$user['id']}");
                }
            }

            if ($is_password_correct) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombres'];
                $_SESSION['usuario_apellidos'] = $user['apellidos'];
                $_SESSION['usuario_cc'] = $user['id_cc'];
                $_SESSION['usuario_registro'] = $user['id_registro'];
                // Normalizar rol a las formas canónicas usadas en el sistema
                $rawRol = is_string($user['rol'] ?? '') ? trim($user['rol']) : '';
                $rolMap = [
                    'tripulante' => 'Tripulacion',
                    'tripulacion' => 'Tripulacion',
                    'administrativo' => 'Administrativo',
                    'administrativa' => 'Administrativo',
                    'secretaria' => 'Administrativo',
                    'master' => 'Master',
                    'dev' => 'Master'
                ];
                $key = strtolower($rawRol);
                $_SESSION['usuario_rol'] = $rolMap[$key] ?? $rawRol;

                // Redirigir según rol normalizado
                if ($_SESSION['usuario_rol'] === 'Master' || $_SESSION['usuario_rol'] === 'Administrativo') {
                    header('Location: ' . BASE_URL . 'admin/panel.php');
                } else {
                    header('Location: ' . BASE_URL . 'index.php');
                }
                exit;
            } else {
                $error_message = "Contraseña incorrecta.";
            }
        } else {
            $error_message = "Usuario no encontrado.";
        }
        $stmt->close();
    }
    $conn->close();
}

// Cargar configuración de empresa
require_once __DIR__ . '/titulos.php';

$pageTitle = "Registro Digital de Atención";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>favicon.ico">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom CSS -->
  <link href="<?= BASE_URL ?>css/style-dark.css" rel="stylesheet" />
  <link href="<?= BASE_URL ?>css/app.css" rel="stylesheet" />

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Theme Script -->
  <script src="<?= BASE_URL ?>js/theme.js" defer></script>

  <style>
    :root {
      --color-tab: #0d6efd;
      --color-tam: #d80f17;
      --color-aph: #d9d900;
    }
    .site-header { 
      position: sticky; 
      top: 0; 
      z-index: 1040; 
      background-color: var(--bs-body-bg, #fff); 
      transition: box-shadow .25s ease, background-color .25s ease; 
    }
    .site-header .navbar { 
      padding: .2rem 0; 
      transition: padding .25s ease; 
    }
    .site-header .site-logo { 
      max-width: 120px; 
      transition: max-width .25s ease; 
    }
    .site-header .navbar-nav .nav-link { 
      padding: .45rem .6rem; 
    }
/* Efecto de partículas concéntricas */
#particle-field {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 9999;
  overflow: hidden;
}

.particle {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  transition: transform 0.6s cubic-bezier(0.23, 1, 0.32, 1);
  will-change: transform;
  filter: blur(0.3px);
}

.particle-tiny { 
  width: 1px; height: 1px; 
  background: rgba(100, 200, 255, 0.4);
}
.particle-small { 
  width: 2px; height: 2px; 
  background: rgba(120, 180, 255, 0.5);
}
.particle-medium { 
  width: 3px; height: 3px; 
  background: rgba(140, 160, 255, 0.6);
}
.particle-large { 
  width: 4px; height: 4px; 
  background: rgba(160, 140, 255, 0.7);
}  </style>
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
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
            <button id="theme-switcher" type="button" class="btn btn-outline-secondary ms-2">
              <i class="bi bi-sun-fill theme-icon-active"></i>
              <span class="visually-hidden">Cambiar tema</span>
            </button>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Ingresar</a></li>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <div class="form-container">
    <h2 class="text-center form-title"><?= htmlspecialchars($pageTitle) ?></h2>
  </div>
</div>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg">
                <div class="card-header text-center">
                    <h3>Iniciar Sesión</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" value="" required>
                        </div>
                        <div class="mb-3">
                            <label for="clave" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="clave" name="clave" value="" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<footer id="footer" class="bg-light mt-4 pt-4" role="contentinfo" style="border-top:1px solid #e6e6e6;">
    <div class="container">
        <div class="row mt-2">
            <div class="col-12 py-2">
                <p class="text-muted small text-center mb-0">• Versión 4.0 — Hecho con ❤️ para Fundación Ambulancias Lentas por [el] © 2025 •</p>
            </div>
        </div>
    </div>
</footer>
<div id="particle-field"></div>
<div id="cursor-trail"></div>
<script src="cursor-trail.js"></script>
</body>
</html>
