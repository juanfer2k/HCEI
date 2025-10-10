<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps ? true : false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Si ya hay una sesión activa, redirigir a la página principal
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
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
        $stmt = $conn->prepare("SELECT id, nombres, apellidos, clave, rol FROM tripulacion WHERE usuario = ?");
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
                $_SESSION['usuario_rol'] = $user['rol'];
                header('Location: ' . BASE_URL . 'index.php');
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

$pageTitle = "Iniciar Sesión - HCEI";
include 'header.php';
?>

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

<?php include 'footer.php'; ?>
