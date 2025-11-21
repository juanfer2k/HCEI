<?php
// test_apply_migration_post.php
// Simulate a POST to admin/contrato.php to trigger the aplicar_migracion handler.
// WARNING: this script should be run from CLI only for testing.
if (php_sapi_name() !== 'cli') { echo "Run from CLI only\n"; exit(1); }

// Start a session using filesystem storage same as PHP default
session_start();
$_SESSION['usuario_rol'] = 'Master';

// Emulate POST data
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['accion'] = 'aplicar_migracion';

// Include the target script (it will read $_POST and perform redirect)
ob_start();
include __DIR__ . '/contrato.php';
$body = ob_get_clean();

// The contrato.php will redirect using header('Location: ...'). We capture headers via xdebug? No; but we can dump $_SESSION message.
echo "SESSION MESSAGE:\n";
if (!empty($_SESSION['message'])) {
  echo strip_tags($_SESSION['message']) . "\n";
} else {
  echo "(none)\n";
}

echo "DONE\n";
