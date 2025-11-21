<?php
// Cambia 'tu_clave_segura' por la contraseña que quieras usar.
$clave_plana = 'Hsistemas';
$hash = password_hash($clave_plana, PASSWORD_DEFAULT);

echo "Copia y pega este hash en tu base de datos: <br><br>";
echo "<textarea rows='3' cols='80' readonly>" . htmlspecialchars($hash) . "</textarea>";
?>
