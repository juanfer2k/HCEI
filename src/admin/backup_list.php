<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ajustar la ruta para acceder a los archivos del directorio raiz
require_once __DIR__ . '/../access_control.php';

// Solo los roles 'Administrativo' y 'Master' pueden acceder
if (!in_array($_SESSION['usuario_rol'], ['Administrativo', 'Master'])) {
    $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para ver la lista de respaldos.</div>';
    // Usar BASE_URL para la redireccion
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
require_once __DIR__ . '/../header.php';
require_once __DIR__ . '/backup_manager.php';

// La funcion listarBackups() ahora esta en backup_manager.php
$backups = listarBackups(); 
?>

<div class="container mt-2">
    <div class="card">
        <?php
        // Mostrar mensaje de la sesion si existe (por ejemplo, despues de eliminar)
        if (isset($_SESSION['message'])) {
            echo $_SESSION['message'];
            unset($_SESSION['message']);
        }
        ?>
        <div class="card-header">
            <h3><i class="fas fa-database"></i> Lista de Respaldos de la Base de Datos</h3>
        </div>
        <div class="card-body">
            <ul class="list-group">
                <?php if (empty($backups)): ?>
                    <li class="list-group-item">No hay respaldos disponibles.</li>
                <?php else: ?>
                    <?php foreach ($backups as $backup): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars($backup) ?>
                            <div>
                                <a href="<?= BASE_URL ?>admin/descargar_backup.php?file=<?= urlencode($backup) ?>" class="btn btn-sm btn-primary">Descargar</a>
                                <a href="<?= BASE_URL ?>admin/backup_handler.php?accion=eliminar&archivo=<?= urlencode($backup) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Â¿Estas seguro de que deseas eliminar este respaldo?');">Eliminar</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
