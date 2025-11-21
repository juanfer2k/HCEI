<?php
// migration_lib.php
// Provides run_schema_migration($conn) which returns an array with summary of actions
function run_schema_migration($conn) {
    $out = ['results' => [], 'messages' => []];
    try {
        $dbres = $conn->query('SELECT DATABASE() as db');
        $dbname = ($dbres && ($row=$dbres->fetch_assoc())) ? $row['db'] : '';
        if (empty($dbname)) {
            $out['messages'][] = 'Could not determine database name.';
            return $out;
        }

        // Columns to ensure on `atenciones`
        $accidente_cols = [
            'conductor_accidente' => 'VARCHAR(255) NULL',
            'documento_conductor_accidente' => 'VARCHAR(100) NULL',
            'tarjeta_propiedad_accidente' => 'VARCHAR(100) NULL',
            'placa_vehiculo_involucrado' => 'VARCHAR(50) NULL'
        ];

        $other_cols = [
          'nombre_empresa_transportador' => 'VARCHAR(255) NULL',
          'codigo_habilitacion_empresa' => 'VARCHAR(50) NULL',
          'direccion_transportador' => 'VARCHAR(255) NULL',
          'telefono_transportador' => 'VARCHAR(50) NULL',
          'placa_vehiculo' => 'VARCHAR(20) NULL',
          'no_radicado_furtran' => 'VARCHAR(50) NULL',
          'total_folios' => 'INT NULL',
          'no_radicado_anterior' => 'VARCHAR(50) NULL'
        ];

        $to_add = array_merge($other_cols, $accidente_cols);

        foreach ($to_add as $col => $def) {
            $stmt = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones' AND COLUMN_NAME = ?");
            $stmt->bind_param('ss', $dbname, $col);
            $stmt->execute();
            $res = $stmt->get_result();
            $c = ($res->fetch_assoc()['c'] ?? 0);
            $stmt->close();
            if ((int)$c === 0) {
                if ($conn->query("ALTER TABLE atenciones ADD COLUMN `$col` $def")) {
                    $out['results'][] = "added atenciones.$col";
                    $out['messages'][] = "Added column atenciones.$col";
                } else {
                    $out['results'][] = "failed atenciones.$col: " . $conn->error;
                    $out['messages'][] = "Failed to add atenciones.$col: " . $conn->error;
                }
            } else {
                $out['messages'][] = "Column atenciones.$col already exists";
            }
        }

        // Ensure unique index for no_radicado_furtran
        $ix = $conn->prepare("SELECT COUNT(*) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones' AND INDEX_NAME = 'uniq_no_radicado_furtran'");
        $ix->bind_param('s', $dbname);
        $ix->execute(); $ic = ($ix->get_result()->fetch_assoc()['c'] ?? 0); $ix->close();
        if ((int)$ic === 0) {
            if ($conn->query("CREATE UNIQUE INDEX uniq_no_radicado_furtran ON atenciones (no_radicado_furtran)")) {
                $out['results'][] = 'created index uniq_no_radicado_furtran';
                $out['messages'][] = 'Created unique index uniq_no_radicado_furtran';
            } else {
                $out['results'][] = 'failed index: '.$conn->error;
                $out['messages'][] = 'Failed to create uniq_no_radicado_furtran: ' . $conn->error;
            }
        } else {
            $out['messages'][] = 'Index uniq_no_radicado_furtran already present';
        }

        // Ensure column on atenciones_sig for firma_receptor_ips
        $qsig = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones_sig' AND COLUMN_NAME = 'firma_receptor_ips'");
        $qsig->bind_param('s', $dbname);
        $qsig->execute();
        $csig = ($qsig->get_result()->fetch_assoc()['c'] ?? 0);
        $qsig->close();
        if ((int)$csig === 0) {
            if ($conn->query("ALTER TABLE atenciones_sig ADD COLUMN `firma_receptor_ips` MEDIUMTEXT NULL")) {
                $out['results'][] = 'added atenciones_sig.firma_receptor_ips';
                $out['messages'][] = 'Added column atenciones_sig.firma_receptor_ips';
            } else {
                $out['results'][] = 'failed atenciones_sig.firma_receptor_ips: '.$conn->error;
                $out['messages'][] = 'Failed to add atenciones_sig.firma_receptor_ips: '.$conn->error;
            }
        } else {
            $out['messages'][] = 'Column atenciones_sig.firma_receptor_ips already exists';
        }

    } catch (Throwable $e) {
        $out['messages'][] = 'Exception: ' . $e->getMessage();
    }
    return $out;
}
