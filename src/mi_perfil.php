<?php
require_once 'access_control.php'; // Asegura que el usuario haya iniciado sesión
require_once 'conn.php';

$pageTitle = "Mi Perfil";

// Lógica para cambiar la contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clave_actual = $_POST['clave_actual'] ?? '';
    $nueva_clave = $_POST['nueva_clave'] ?? '';
    $confirmar_nueva_clave = $_POST['confirmar_nueva_clave'] ?? '';

    if (empty($clave_actual) || empty($nueva_clave) || empty($confirmar_nueva_clave)) {
        $_SESSION['message'] = '<div class="alert alert-danger">Todos los campos son obligatorios.</div>';
    } elseif ($nueva_clave !== $confirmar_nueva_clave) {
        $_SESSION['message'] = '<div class="alert alert-danger">La nueva contraseña y su confirmación no coinciden.</div>';
    } else {
        // Obtener la contraseña actual del usuario desde la BD
        $stmt = $conn->prepare("SELECT clave FROM tripulacion WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Verificar si la contraseña actual es correcta
        if (password_verify($clave_actual, $user['clave'])) {
            // La contraseña actual es correcta, proceder a actualizar
            $nueva_clave_hasheada = password_hash($nueva_clave, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE tripulacion SET clave = ? WHERE id = ?");
            $update_stmt->bind_param("si", $nueva_clave_hasheada, $_SESSION['usuario_id']);

            if ($update_stmt->execute()) {
                $_SESSION['message'] = '<div class="alert alert-success">Contraseña actualizada con éxito.</div>';
            } else {
                $_SESSION['message'] = '<div class="alert alert-danger">Error al actualizar la contraseña.</div>';
            }
            $update_stmt->close();
        } else {
            $_SESSION['message'] = '<div class="alert alert-danger">La contraseña actual es incorrecta.</div>';
        }
    }
    header('Location: mi_perfil.php');
    exit;
}

include 'header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4>Cambiar mi Contraseña</h4>
                </div>
                <div class="card-body">
                    <?php
                    if (isset($_SESSION['message'])) {
                        echo $_SESSION['message'];
                        unset($_SESSION['message']);
                    }
                    ?>
                    <form method="POST" action="mi_perfil.php">
                        <div class="mb-3">
                            <label for="clave_actual" class="form-label">Contraseña Actual</label>
                            <input type="password" class="form-control" id="clave_actual" name="clave_actual" required>
                        </div>
                        <div class="mb-3">
                            <label for="nueva_clave" class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="nueva_clave" name="nueva_clave" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmar_nueva_clave" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirmar_nueva_clave" name="confirmar_nueva_clave" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>