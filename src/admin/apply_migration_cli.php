<?php
// apply_migration_cli.php
// Run from CLI: php admin/apply_migration_cli.php
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/migration_lib.php';

$dbres = $conn->query('SELECT DATABASE() as db');
$dbname = ($dbres && ($row=$dbres->fetch_assoc())) ? $row['db'] : '';
echo "Applying DB migration on database: $dbname\n";
$res = run_schema_migration($conn);
foreach (($res['messages'] ?? []) as $m) { echo $m . "\n"; }
if (!empty($res['results'])) {
    echo "\nMigration summary:\n";
    foreach ($res['results'] as $r) { echo " - $r\n"; }
} else {
    echo "\nNo changes were necessary.\n";
}
exit(0);
