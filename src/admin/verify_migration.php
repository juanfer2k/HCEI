<?php
require_once __DIR__ . '/../conn.php';
$checks = [
  "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atenciones_sig' AND COLUMN_NAME = 'firma_receptor_ips'",
  "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atenciones' AND COLUMN_NAME = 'placa_vehiculo_involucrado'"
];
foreach ($checks as $q) {
  $res = $conn->query($q);
  if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) { echo "FOUND: " . $r['COLUMN_NAME'] . "\n"; }
  } else {
    echo "MISSING for query: $q\n";
  }
}

echo "Done.\n";
