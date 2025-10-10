<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../bootstrap.php';

// Permitir acceso solo a Master
if (($_SESSION['usuario_rol'] ?? '') !== 'Master') {
    $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para acceder a esta seccion.</div>';
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Cargar configuracion actual
require_once __DIR__ . '/../titulos.php';
$empresa_actual = $empresa ?? [];

// Cargar configuracion desde BD si existe en empresa_config (id=1)
try {
    $conn->query("CREATE TABLE IF NOT EXISTS empresa_config (
        id TINYINT PRIMARY KEY DEFAULT 1,
        data_json JSON NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $resCfg = $conn->query("SELECT data_json FROM empresa_config WHERE id = 1");
    if ($resCfg && ($rowCfg = $resCfg->fetch_assoc())) {
        $decoded = json_decode($rowCfg['data_json'] ?? '', true);
        if (is_array($decoded)) {
            $empresa_actual = array_merge($empresa_actual, $decoded);
        }
    }
} catch (Throwable $e) {
    // Silencioso: usar defaults si falla
}

// Helpers
function guardar_archivo($input_name, $dest_dir) {
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$input_name];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','webp','gif'])) return null;
    if (!is_dir($dest_dir)) { @mkdir($dest_dir, 0755, true); }
    $slug = preg_replace('~[^a-z0-9]+~i', '-', pathinfo($f['name'], PATHINFO_FILENAME));
    $filename = $slug . '-' . date('YmdHis') . '.' . $ext;
    $target = rtrim($dest_dir,'/\\') . DIRECTORY_SEPARATOR . $filename;
    if (move_uploaded_file($f['tmp_name'], $target)) {
        // retornar ruta relativa desde la raiz de la app
        return 'uploads/' . basename($target);
    }
    return null;
}

function write_titulos_php(array $empresa, array $titulos) {
    $path = __DIR__ . '/../titulos.php';
    // Sanitizar arrays simples
    $empresa['ambulancias'] = array_values(array_filter(array_map('strval', $empresa['ambulancias'] ?? [])));

    $export_array = function($arr) {
        $out = var_export($arr, true);
        // var_export usa array(); mantenerlo asi para compatibilidad
        return $out;
    };

    $php = "<?php\n\n// --- CONFIGURACIÃ“N DE LA EMPRESA ---\n$".
           "empresa = " . $export_array($empresa) . ";\n\n\n$".
           "titulos = " . $export_array($titulos) . ";\n";
    // Escribir con codificacion UTF-8
    file_put_contents($path, $php);
}

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_empresa') {
        // Recoger campos
        $campos = [
            'nombre','nit','direccion','municipio','departamento','codigo_municipio','pais','telefono',
            'COD_Habilitacion','nombre_representante_legal_empresa','tipo_documento_representante_legal_empresa',
            'id_representante_legal_empresa', 'web','titulo_general','titulo_pdf','version'
        ];
        $data = [];
        foreach ($campos as $c) { $data[$c] = trim($_POST[$c] ?? ($empresa_actual[$c] ?? '')); }

        // Ambulancias (hasta 5)
        $ambulancias = [];
        for ($i=0; $i<5; $i++) {
            $key = 'ambulancia_'.$i;
            $val = trim($_POST[$key] ?? '');
            if ($val !== '') { $ambulancias[] = $val; }
        }
        $data['ambulancias'] = $ambulancias;

        // Cargas de archivo (guardamos solo el nombre de archivo en raiz actual)
        $uploads_dir = __DIR__ . '/../uploads';
        if ($logo = guardar_archivo('logo_principal', $uploads_dir)) { $data['logo_principal'] = $logo; }
        else { $data['logo_principal'] = $empresa_actual['logo_principal'] ?? ''; }
        if ($vigilado = guardar_archivo('logo_vigilado', $uploads_dir)) { $data['logo_vigilado'] = $vigilado; }
        else { $data['logo_vigilado'] = $empresa_actual['logo_vigilado'] ?? ''; }
        if ($firma = guardar_archivo('firma_representante_legal_empresa', $uploads_dir)) { $data['firma_representante_legal_empresa'] = $firma; }
        else { $data['firma_representante_legal_empresa'] = $empresa_actual['firma_representante_legal_empresa'] ?? ''; }

        // Persistir tambien en BD (creamos tabla si no existe)
        $conn->query("CREATE TABLE IF NOT EXISTS empresa_config (
            id TINYINT PRIMARY KEY DEFAULT 1,
            data_json JSON NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            $safe = $conn->real_escape_string($json);
            $conn->query("INSERT INTO empresa_config (id, data_json) VALUES (1, '$safe')
                          ON DUPLICATE KEY UPDATE data_json = VALUES(data_json)");
        }

        // Reescribir titulos.php manteniendo $titulos existente
        write_titulos_php($data, $titulos);

        $_SESSION['message'] = '<div class="alert alert-success">Configuracion de la empresa guardada y sincronizada.</div>';
        header('Location: ' . BASE_URL . 'admin/contrato.php');
        exit;
    }

    if ($accion === 'aplicar_migracion') {
        if (!in_array($_SESSION['usuario_rol'], ['Administrativo', 'Master'])) {
            $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para aplicar migraciones.</div>';
            header('Location: ' . BASE_URL . 'admin/contrato.php');
            exit;
        }
        // Obtener DB actual
        $dbres = $conn->query('SELECT DATABASE() as db');
        $dbname = ($dbres && $row=$dbres->fetch_assoc()) ? $row['db'] : '';
        $faltantes = [
            'nombre_empresa_transportador' => 'VARCHAR(255) NULL',
            'codigo_habilitacion_empresa' => 'VARCHAR(50) NULL',
            'direccion_transportador' => 'VARCHAR(255) NULL',
            'telefono_transportador' => 'VARCHAR(50) NULL',
            'placa_vehiculo' => 'VARCHAR(20) NULL',
            'no_radicado_furtran' => 'VARCHAR(50) NULL',
            'total_folios' => 'INT NULL',
            'no_radicado_anterior' => 'VARCHAR(50) NULL'
        ];
        $added = [];
        foreach ($faltantes as $col => $def) {
            $q = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones' AND COLUMN_NAME = ?");
            $q->bind_param('ss', $dbname, $col);
            $q->execute();
            $c = ($q->get_result()->fetch_assoc()['c'] ?? 0);
            $q->close();
            if ((int)$c === 0) {
                $conn->query("ALTER TABLE atenciones ADD COLUMN `$col` $def");
                if ($conn->error) { $_SESSION['message'] = '<div class="alert alert-danger">Error al agregar '+$col+': '.htmlspecialchars($conn->error).'</div>'; }
                $added[] = $col;
            }
        }
        // Ãndice unico para no_radicado_furtran
        $ix = $conn->prepare("SELECT COUNT(*) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones' AND INDEX_NAME = 'uniq_no_radicado_furtran'");
        $ix->bind_param('s', $dbname);
        $ix->execute(); $ic = ($ix->get_result()->fetch_assoc()['c'] ?? 0); $ix->close();
        if ((int)$ic === 0) {
            // Quitar duplicados previos si existieran: no forzamos, solo intentamos crear
            $conn->query("CREATE UNIQUE INDEX uniq_no_radicado_furtran ON atenciones (no_radicado_furtran)");
        }

        $_SESSION['message'] = '<div class="alert alert-success">Migracion aplicada. Columnas agregadas: ' . htmlspecialchars(implode(', ', $added)) . '.</div>';
        header('Location: ' . BASE_URL . 'admin/contrato.php');
        exit;
    }
}

$pageTitle = 'Contrato y FURTRAN';
include __DIR__ . '/../header.php';
?>

<div class="container mt-3">
  <?php if (!empty($_SESSION['message'])){ echo $_SESSION['message']; unset($_SESSION['message']); } ?>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header"><strong>Configuracion de la Empresa</strong></div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="guardar_empresa" />
            <div class="row g-2">
              <div class="col-md-8">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" value="<?= htmlspecialchars($empresa_actual['nombre'] ?? '') ?>" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">NIT</label>
                <input class="form-control" name="nit" value="<?= htmlspecialchars($empresa_actual['nit'] ?? '') ?>" />
              </div>
              <div class="col-md-8">
                <label class="form-label">Direccion</label>
                <input class="form-control" name="direccion" value="<?= htmlspecialchars($empresa_actual['direccion'] ?? '') ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Telefono</label>
                <input class="form-control" name="telefono" value="<?= htmlspecialchars($empresa_actual['telefono'] ?? '') ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Municipio (DANE)</label>
                <input class="form-control" name="codigo_municipio" value="<?= htmlspecialchars($empresa_actual['codigo_municipio'] ?? '') ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Municipio (Nombre)</label>
                <input class="form-control" name="municipio" value="<?= htmlspecialchars($empresa_actual['municipio'] ?? '') ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Departamento</label>
                <input class="form-control" name="departamento" value="<?= htmlspecialchars($empresa_actual['departamento'] ?? '') ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Pais</label>
                <input class="form-control" name="pais" value="<?= htmlspecialchars($empresa_actual['pais'] ?? '') ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Cod. Habilitacion</label>
                <input class="form-control" name="COD_Habilitacion" value="<?= htmlspecialchars($empresa_actual['COD_Habilitacion'] ?? '') ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Representante Legal</label>
                <input class="form-control" name="nombre_representante_legal_empresa" value="<?= htmlspecialchars($empresa_actual['nombre_representante_legal_empresa'] ?? '') ?>" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Tipo Doc.</label>
                <input class="form-control" name="tipo_documento_representante_legal_empresa" value="<?= htmlspecialchars($empresa_actual['tipo_documento_representante_legal_empresa'] ?? '') ?>" />
              </div>
              <div class="col-md-3">
                <label class="form-label">No. Doc.</label>
                <input class="form-control" name="id_representante_legal_empresa" value="<?= htmlspecialchars($empresa_actual['id_representante_legal_empresa'] ?? '') ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Sitio Web</label>
                <input class="form-control" name="web" value="<?= htmlspecialchars($empresa_actual['web'] ?? '') ?>" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Titulo General</label>
                <input class="form-control" name="titulo_general" value="<?= htmlspecialchars($empresa_actual['titulo_general'] ?? '') ?>" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Titulo PDF</label>
                <input class="form-control" name="titulo_pdf" value="<?= htmlspecialchars($empresa_actual['titulo_pdf'] ?? '') ?>" />
              </div>
              <div class="col-md-2">
                <label class="form-label">Version</label>
                <input class="form-control" name="version" value="<?= htmlspecialchars($empresa_actual['version'] ?? '') ?>" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Logo Principal</label>
                <input class="form-control" type="file" name="logo_principal" accept="image/*" />
                <?php if(!empty($empresa_actual['logo_principal'])): ?><small>Actual: <?= htmlspecialchars($empresa_actual['logo_principal']) ?></small><?php endif; ?>
              </div>
              <div class="col-md-3">
                <label class="form-label">Logo Vigilado</label>
                <input class="form-control" type="file" name="logo_vigilado" accept="image/*" />
                <?php if(!empty($empresa_actual['logo_vigilado'])): ?><small>Actual: <?= htmlspecialchars($empresa_actual['logo_vigilado']) ?></small><?php endif; ?>
              </div>
              <div class="col-md-6">
                <label class="form-label">Firma Rep. Legal (imagen)</label>
                <input class="form-control" type="file" name="firma_representante_legal_empresa" accept="image/*" />
                <?php if(!empty($empresa_actual['firma_representante_legal_empresa'])): ?><small>Actual: <?= htmlspecialchars($empresa_actual['firma_representante_legal_empresa']) ?></small><?php endif; ?>
              </div>

              <div class="col-12 mt-2"><strong>Ambulancias (maximo 5)</strong></div>
              <?php for($i=0;$i<5;$i++): $val = $empresa_actual['ambulancias'][$i] ?? ''; ?>
                <div class="col-md-4"><input class="form-control" placeholder="Placa #<?= $i+1 ?>" name="ambulancia_<?= $i ?>" value="<?= htmlspecialchars($val) ?>" /></div>
              <?php endfor; ?>
              <div class="col-12 mt-3"><button class="btn btn-primary" type="submit">Guardar configuracion</button></div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php
      // Mostrar bloque de migracion solo si faltan columnas
      $showMigration = false; $missing = [];
      try {
        $columnsNeeded = [
          'nombre_empresa_transportador','codigo_habilitacion_empresa','direccion_transportador','telefono_transportador',
          'placa_vehiculo','no_radicado_furtran','total_folios','no_radicado_anterior'
        ];
        $dbres = $conn->query('SELECT DATABASE() as db');
        $dbname = ($dbres && ($row=$dbres->fetch_assoc())) ? $row['db'] : '';
        foreach ($columnsNeeded as $col) {
          $q = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones' AND COLUMN_NAME = ?");
          $q->bind_param('ss', $dbname, $col);
          $q->execute();
          $c = ($q->get_result()->fetch_assoc()['c'] ?? 0);
          $q->close();
          if ((int)$c === 0) { $missing[] = $col; }
        }
        $showMigration = count($missing) > 0;
      } catch (Throwable $e) {
        $showMigration = false; // si falla el chequeo, no mostrar
      }
    ?>
    <?php if (in_array($_SESSION['usuario_rol'], ['Administrativo', 'Master']) && $showMigration): ?>
      <div class="col-lg-5">
        <div class="card mb-3">
          <div class="card-header"><strong>Migracion de Esquema FURTRAN</strong></div>
          <div class="card-body">
          <p>Se detectaron columnas faltantes en <code>atenciones</code>. Puedes aplicarlas aqui.</p>
          <p class="small">Faltantes: <?= htmlspecialchars(implode(', ', $missing)) ?></p>
          <form method="POST">
            <input type="hidden" name="accion" value="aplicar_migracion" />
            <button class="btn btn-warning" type="submit" onclick="return confirm('Â¿Aplicar cambios de esquema?')">Aplicar migracion</button>
          </form>
          <hr>
          <p class="small text-muted">Para codificacion DANE (municipios/departamentos), podemos importar un catalogo oficial y habilitar autocompletado por codigo. Puedo agregar el importador si lo deseas.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
