<?php
// Siempre usar la sesión y configuración centralizadas de bootstrap
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';

// Usar directamente el rol canónico de sesión definido en login.php
$rolSesion = $_SESSION['usuario_rol'] ?? '';

// Permitir acceso a la página a Master y Administrativo.
// Algunas acciones específicas más abajo siguen restringidas solo a Master.
if (!in_array($rolSesion, ['Master','Administrativo'], true)) {
    $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para acceder a esta seccion.</div>';
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Cargar configuracion actual
require_once __DIR__ . '/../titulos.php';
$empresa_actual = $empresa ?? [];

function normalize_empresa_config(mysqli $conn, array $empresaDefaults) {
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS empresa_config (
            id TINYINT NOT NULL,
            data_json JSON NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Intentar agregar la columna updated_at si faltaba
        try {
            $conn->query("ALTER TABLE empresa_config ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } catch (Throwable $e) {
            // ignorar si ya existe
        }

        // Limpiar duplicados si hay más de un registro
        $resCount = $conn->query('SELECT COUNT(*) AS c FROM empresa_config');
        $count = ($resCount && ($rowC = $resCount->fetch_assoc())) ? (int)$rowC['c'] : 0;
        if ($resCount) { $resCount->close(); }
        if ($count > 1) {
            $conn->query('TRUNCATE TABLE empresa_config');
            $json = json_encode($empresaDefaults, JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $stmt = $conn->prepare('INSERT INTO empresa_config (id, data_json) VALUES (1, ?)');
                if ($stmt) {
                    $stmt->bind_param('s', $json);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        try {
            $conn->query('ALTER TABLE empresa_config ADD PRIMARY KEY (id)');
        } catch (Throwable $e) {
            // ignorar si ya existe o no es posible
        }
    } catch (Throwable $e) {
        // No interrumpir flujo por errores de normalización
    }
}

// Cargar configuracion desde BD si existe en empresa_config (id=1)
try {
    normalize_empresa_config($conn, $empresa_actual);
    $resCfg = $conn->query("SELECT data_json FROM empresa_config WHERE id = 1 ORDER BY updated_at DESC LIMIT 1");
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

    $php = "<?php\n\n// --- CONFIGURACIÓN DE LA EMPRESA ---\n$".
           "empresa = " . $export_array($empresa) . ";\n\n\n$".
           "titulos = " . $export_array($titulos) . ";\n";
    // Añadir definiciones de constantes para logos si existen (compatibilidad con PDF y otras partes)
    $logo_principal = $empresa['logo_principal'] ?? '';
    $logo_vigilado = $empresa['logo_vigilado'] ?? '';
    $php .= "\n// Constantes derivadas de la configuracion (no modificar manualmente)\n";
    $php .= "if (!defined('LOGO_PRINCIPAL')) define('LOGO_PRINCIPAL', '" . addslashes($logo_principal) . "');\n";
    $php .= "if (!defined('LOGO_VIGILADO')) define('LOGO_VIGILADO', '" . addslashes($logo_vigilado) . "');\n";
    // Escribir con codificacion UTF-8 y validar resultado
    $written = file_put_contents($path, $php);
    if ($written === false) {
        throw new RuntimeException('No se pudo escribir el archivo titulos.php (verifica permisos).');
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
}

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_empresa') {
        try {
        // Recoger campos
        $campos = [
            'nombre','nit','direccion','municipio','departamento','codigo_municipio','codigo_departamento_empresa','pais','telefono',
            'COD_Habilitacion','nombre_representante_legal_empresa','tipo_documento_representante_legal_empresa',
            'id_representante_legal_empresa', 'web','titulo_general','titulo_pdf','version'
        ];
        $data = [];
        foreach ($campos as $c) { $data[$c] = trim($_POST[$c] ?? ($empresa_actual[$c] ?? '')); }

        // Ambulancias (dinámico)
        $ambulancias = [];
        if (isset($_POST['ambulancias']) && is_array($_POST['ambulancias'])) {
            foreach ($_POST['ambulancias'] as $placa) {
                $placa = trim((string)$placa);
                if ($placa !== '') { $ambulancias[] = $placa; }
            }
        } else {
            // Compatibilidad con campos legacy ambulancia_0...4
            for ($i=0; $i<5; $i++) {
                $key = 'ambulancia_'.$i;
                $val = trim($_POST[$key] ?? '');
                if ($val !== '') { $ambulancias[] = $val; }
            }
        }
        $data['ambulancias'] = $ambulancias;

        // Replicar datos derivados
        $data['municipio_empresa'] = $data['municipio'];
        $data['departamento_empresa'] = $data['departamento'];
        if (empty($data['codigo_departamento_empresa']) && !empty($data['codigo_municipio'])) {
            $data['codigo_departamento_empresa'] = substr($data['codigo_municipio'], 0, 2);
        }

        // Cargas de archivo (guardamos solo el nombre de archivo en raiz actual)
        $uploads_dir = __DIR__ . '/../uploads';
        if ($logo = guardar_archivo('logo_principal', $uploads_dir)) { $data['logo_principal'] = $logo; }
        else { $data['logo_principal'] = $empresa_actual['logo_principal'] ?? ''; }
        $data['logo_vigilado'] = $empresa_actual['logo_vigilado'] ?? 'assets/img/vigilado.png';
        if ($firma = guardar_archivo('firma_representante_legal_empresa', $uploads_dir)) { $data['firma_representante_legal_empresa'] = $firma; }
        else { $data['firma_representante_legal_empresa'] = $empresa_actual['firma_representante_legal_empresa'] ?? ''; }

        // Persistir tambien en BD (creamos tabla si no existe)
        $conn->query("CREATE TABLE IF NOT EXISTS empresa_config (
            id TINYINT PRIMARY KEY DEFAULT 1,
            data_json JSON NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar la configuración en JSON.');
        }
        $stmtCfg = $conn->prepare("INSERT INTO empresa_config (id, data_json) VALUES (1, ?) ON DUPLICATE KEY UPDATE data_json = VALUES(data_json)");
        if (!$stmtCfg) {
            throw new RuntimeException('Error preparando guardado de configuración: ' . $conn->error);
        }
        $stmtCfg->bind_param('s', $json);
        if (!$stmtCfg->execute()) {
            $stmtCfg->close();
            throw new RuntimeException('No se pudo guardar la configuración de empresa: ' . $stmtCfg->error);
        }
        $stmtCfg->close();

        // Reescribir titulos.php manteniendo $titulos existente
        try {
            write_titulos_php($data, $titulos);
        } catch (Throwable $e) {
            $_SESSION['message'] = '<div class="alert alert-danger">No se pudo sincronizar titulos.php: ' . htmlspecialchars($e->getMessage()) . '</div>';
            header('Location: ' . BASE_URL . 'admin/contrato.php');
            exit;
        }

        } catch (Throwable $e) {
            $_SESSION['message'] = '<div class="alert alert-danger">Error al guardar la configuración: ' . htmlspecialchars($e->getMessage()) . '</div>';
            header('Location: ' . BASE_URL . 'admin/contrato.php');
            exit;
        }

        $_SESSION['message'] = '<div class="alert alert-success">Configuracion de la empresa guardada y sincronizada.</div>';
        header('Location: ' . BASE_URL . 'admin/contrato.php');
        exit;
    }

    if ($accion === 'set_autoincrement') {
        if ($rolSesion !== 'master') {
            $_SESSION['message'] = '<div class="alert alert-danger">Solo el rol Master puede ajustar el auto-incremento.</div>';
        } else {
            $next_id = filter_input(INPUT_POST, 'next_autoincrement', FILTER_VALIDATE_INT);
            if ($next_id && $next_id > 0) {
                $conn->query("ALTER TABLE atenciones AUTO_INCREMENT = " . $next_id);
                $_SESSION['message'] = '<div class="alert alert-success">El próximo ID de atención se ha establecido en ' . $next_id . '.</div>';
            } else {
                $_SESSION['message'] = '<div class="alert alert-danger">Por favor, ingrese un número válido.</div>';
            }
        }
    }

    if ($accion === 'aplicar_migracion') {
        if (!in_array($rolSesion, ['administrativo', 'master'], true)) {
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
    // Añadimos columnas relativas al accidente si faltan
    $accidente_cols = [
      'conductor_accidente' => 'VARCHAR(255) NULL',
      'documento_conductor_accidente' => 'VARCHAR(100) NULL',
      'tarjeta_propiedad_accidente' => 'VARCHAR(100) NULL',
      'placa_vehiculo_involucrado' => 'VARCHAR(50) NULL'
    ];
    $faltantes = array_merge($faltantes, $accidente_cols);
        $added = [];
        foreach ($faltantes as $col => $def) {
            $q = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones' AND COLUMN_NAME = ?");
            $q->bind_param('ss', $dbname, $col);
            $q->execute();
            $c = ($q->get_result()->fetch_assoc()['c'] ?? 0);
            $q->close();
            if ((int)$c === 0) {
                $conn->query("ALTER TABLE atenciones ADD COLUMN `$col` $def");
                if ($conn->error) { $_SESSION['message'] = '<div class="alert alert-danger">Error al agregar ' . htmlspecialchars($col) . ': ' . htmlspecialchars($conn->error) . '</div>'; }
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

    // Asegurarnos de que la tabla atenciones_sig tenga columna para la firma del receptor IPS
    try {
      $qsig = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones_sig' AND COLUMN_NAME = 'firma_receptor_ips'");
      $qsig->bind_param('s', $dbname);
      $qsig->execute();
      $csig = ($qsig->get_result()->fetch_assoc()['c'] ?? 0);
      $qsig->close();
      if ((int)$csig === 0) {
        $conn->query("ALTER TABLE atenciones_sig ADD COLUMN `firma_receptor_ips` MEDIUMTEXT NULL");
      }
    } catch (Throwable $e) {
      // No crítico: seguimos con el proceso y notificamos si hay error
      $_SESSION['message'] = '<div class="alert alert-warning">Advertencia: no se pudo verificar/crear columna firma_receptor_ips en atenciones_sig. ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    require_once __DIR__ . '/migration_lib.php';
    $res = run_schema_migration($conn);
    $html = '';
    foreach (($res['messages'] ?? []) as $m) { $html .= '<div class="small">' . htmlspecialchars($m) . '</div>'; }
    if (!empty($res['results'])) {
      $html = '<div class="alert alert-success">Migracion aplicada. Cambios: ' . htmlspecialchars(implode(', ', $res['results'])) . '</div>' . $html;
    } else {
      $html = '<div class="alert alert-info">No se necesitaron cambios en el esquema.</div>' . $html;
    }
    $_SESSION['message'] = $html;
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
              <?php
                $munCode = $empresa_actual['codigo_municipio'] ?? '';
                $munName = $empresa_actual['municipio'] ?? '';
                $deptName = $empresa_actual['departamento'] ?? '';
                $deptCode = $empresa_actual['codigo_departamento_empresa'] ?? substr($munCode,0,2);
              ?>
              <div class="col-md-6">
                <label class="form-label">Municipio (catálogo DANE)</label>
                <select class="form-select" id="empresa_municipio_select" data-current-code="<?= htmlspecialchars($munCode) ?>" data-current-name="<?= htmlspecialchars($munName) ?>" data-current-depto="<?= htmlspecialchars($deptName) ?>" data-current-depto-code="<?= htmlspecialchars($deptCode) ?>">
                  <?php if ($munCode && $munName): ?>
                    <option value="<?= htmlspecialchars($munCode) ?>" selected><?= htmlspecialchars($munName) ?></option>
                  <?php endif; ?>
                </select>
                <input type="hidden" name="municipio" id="empresa_municipio_nombre" value="<?= htmlspecialchars($munName) ?>" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Código municipio</label>
                <input class="form-control" id="empresa_codigo_municipio" name="codigo_municipio" value="<?= htmlspecialchars($munCode) ?>" readonly />
              </div>
              <div class="col-md-3">
                <label class="form-label">Departamento</label>
                <input class="form-control" id="empresa_departamento" name="departamento" value="<?= htmlspecialchars($deptName) ?>" readonly />
              </div>
              <input type="hidden" name="codigo_departamento_empresa" id="empresa_codigo_departamento" value="<?= htmlspecialchars($deptCode) ?>" />
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
                <div class="form-control bg-light text-muted">Se define en diseño (no editable)</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Firma Rep. Legal (imagen)</label>
                <input class="form-control" type="file" name="firma_representante_legal_empresa" accept="image/*" />
                <?php if(!empty($empresa_actual['firma_representante_legal_empresa'])): ?><small>Actual: <?= htmlspecialchars($empresa_actual['firma_representante_legal_empresa']) ?></small><?php endif; ?>
              </div>

              <div class="col-12 mt-2 d-flex justify-content-between align-items-center">
                <strong>Ambulancias / Móviles</strong>
                <button class="btn btn-sm btn-outline-primary" type="button" id="btnAgregarAmbulancia">
                  <i class="bi bi-plus-circle"></i> Agregar móvil
                </button>
              </div>
              <div class="col-12">
                <div class="row g-2" id="listaAmbulancias">
                  <?php
                    $ambulanciasActuales = $empresa_actual['ambulancias'] ?? [];
                    if (!is_array($ambulanciasActuales) || count($ambulanciasActuales) === 0) {
                        $ambulanciasActuales = [''];
                    }
                    foreach ($ambulanciasActuales as $placa):
                  ?>
                  <div class="col-md-4 ambulancia-item">
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-truck"></i></span>
                      <input class="form-control" name="ambulancias[]" value="<?= htmlspecialchars($placa) ?>" placeholder="Placa / interno" />
                      <button class="btn btn-outline-danger" type="button" data-remove-ambulancia>&times;</button>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <small class="text-muted">Puedes agregar o quitar móviles según la flota actual.</small>
              </div>
              <div class="col-12 mt-3 d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-primary" type="submit">Guardar configuracion</button>
                <span class="text-muted small">Los datos visibles abajo se actualizan con cada guardado.</span>
              </div>
            </div>
          </form>
                  </div>
      </div>
    </div>
<div class="mt-4 border rounded-3 p-3 bg-light-subtle">
            <div class="row g-3">
              <div class="col-12 col-md-4">
                <div class="d-flex align-items-center gap-3">
                  <?php if (!empty($empresa_actual['logo_principal'])): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($empresa_actual['logo_principal']) ?>" alt="Logo empresa" style="height:60px;" class="img-fluid">
                  <?php endif; ?>
                  <div>
                    <strong><?= htmlspecialchars($empresa_actual['nombre'] ?? 'Nombre empresa') ?></strong>
                    <div class="text-muted small">NIT: <?= htmlspecialchars($empresa_actual['nit'] ?? '-') ?></div>
                    <a href="https://<?= htmlspecialchars($empresa_actual['web'] ?? '') ?>" class="text-decoration-none small" target="_blank" rel="noopener"><?= htmlspecialchars($empresa_actual['web'] ?? '') ?></a>
                  </div>
                </div>
              </div>
              <div class="col-6 col-md-4">
                <h6 class="fw-semibold small mb-1">Contacto</h6>
                <ul class="list-unstyled small mb-0">
                  <li>Dirección: <?= htmlspecialchars($empresa_actual['direccion'] ?? '-') ?></li>
                  <li>Oficina: <?= htmlspecialchars($empresa_actual['telefono'] ?? '-') ?></li>
                </ul>
              </div>
              <div class="col-6 col-md-4">
                <h6 class="fw-semibold small mb-1">Enlaces</h6>
                <ul class="list-unstyled small mb-0">
                  <li><a href="<?= BASE_URL ?>index.php" class="text-decoration-none">Inicio</a></li>
                  <li><a href="<?= BASE_URL ?>consulta_atenciones.php" class="text-decoration-none">Consulta Atenciones</a></li>
                </ul>
              </div>
              <div class="col-12">
                <div class="small text-muted">Registro basado en estándares HL7-FHIR. Cumple con la implementación obligatoria de la Interoperabilidad de la Historia Clínica Electrónica y el Resumen Digital de Atención en Salud (RDA). Resolución vigente: 1888 de 2025.</div>
              </div>
            </div>
          </div>

    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header"><strong>Configuración de Registros</strong></div>
        <div class="card-body">
          <p>Establecer el número para el próximo registro de atención. El sistema usará este número o el siguiente más alto disponible.</p>
          <form method="POST">
            <input type="hidden" name="accion" value="set_autoincrement" />
            <div class="input-group">
              <span class="input-group-text">Próximo ID</span>
              <input type="number" class="form-control" name="next_autoincrement" placeholder="Ej: 1000" min="1" required>
              <button class="btn btn-warning" type="submit">Ajustar</button>
            </div>
            <div class="form-text">
              <?php $res = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atenciones'");
                    $next = $res ? $res->fetch_assoc()['AUTO_INCREMENT'] : 'N/A';
                    echo "Valor actual: <strong>" . $next . "</strong>"; ?>
            </div>
          </form>
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
    <?php if (in_array($rolSesion, ['administrativo', 'master'], true) && $showMigration): ?>
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
 <    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>

<script>
(function(){
  const container = document.getElementById('listaAmbulancias');
  const addBtn = document.getElementById('btnAgregarAmbulancia');
  if (!container || !addBtn) return;

  const createItem = (value = '') => {
    const col = document.createElement('div');
    col.className = 'col-md-4 ambulancia-item';
    col.innerHTML = `
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-truck"></i></span>
        <input class="form-control" name="ambulancias[]" placeholder="Placa / interno" value="${value.replace(/"/g,'&quot;')}">
        <button class="btn btn-outline-danger" type="button" data-remove-ambulancia>&times;</button>
      </div>`;
    hookupRemove(col);
    return col;
  };

  const hookupRemove = (node) => {
    const btn = node.querySelector('[data-remove-ambulancia]');
    if (btn) {
      btn.addEventListener('click', () => {
        if (container.querySelectorAll('.ambulancia-item').length === 1) {
          node.querySelector('input').value = '';
          return;
        }
        node.remove();
      });
    }
  };

  addBtn.addEventListener('click', () => {
    const item = createItem('');
    container.appendChild(item);
    const input = item.querySelector('input');
    if (input) input.focus();
  });

  Array.from(container.querySelectorAll('.ambulancia-item')).forEach(hookupRemove);
})();

(function(){
  if (!window.jQuery || !jQuery.fn.select2) return;
  const $select = jQuery('#empresa_municipio_select');
  if (!$select.length) return;
  const baseUrl = (window.AppConfig && window.AppConfig.baseUrl) ? window.AppConfig.baseUrl : (window.AppBaseUrl || '');
  const hiddenNombre = document.getElementById('empresa_municipio_nombre');
  const inputCodigo = document.getElementById('empresa_codigo_municipio');
  const inputDepto = document.getElementById('empresa_departamento');
  const hiddenDeptoCode = document.getElementById('empresa_codigo_departamento');

  const applyData = (data) => {
    const codigoMun = data.id || '';
    const nombreMun = data.nombre_municipio || data.text || '';
    const nombreDepto = data.nombre_departamento || '';
    const codigoDepto = data.codigo_departamento || (codigoMun ? codigoMun.slice(0,2) : '');
    if (hiddenNombre) hiddenNombre.value = nombreMun;
    if (inputCodigo) inputCodigo.value = codigoMun;
    if (inputDepto) inputDepto.value = nombreDepto;
    if (hiddenDeptoCode) hiddenDeptoCode.value = codigoDepto;
  };

  $select.select2({
    width: '100%',
    placeholder: 'Buscar municipio...',
    minimumInputLength: 2,
    allowClear: true,
    language: {
      inputTooShort: () => 'Ingrese 2 o más caracteres',
      noResults: () => 'No se encontraron resultados',
      searching: () => 'Buscando...'
    },
    ajax: {
      url: baseUrl + 'buscar_municipio.php',
      dataType: 'json',
      delay: 250,
      data: params => ({ q: params.term }),
      processResults: data => ({ results: (data.results || []).map(item => ({
        ...item,
        text: item.text || item.nombre_municipio || ''
      })) })
    },
    templateResult: repo => repo.loading ? repo.text : `<strong>${repo.text || ''}</strong> <span class="text-muted">${repo.nombre_departamento || ''}</span>`,
    templateSelection: repo => repo.id ? `${repo.text || ''}` : repo.text
  }).on('select2:select', e => {
    applyData(e.params?.data || {});
  }).on('select2:clear', () => applyData({}));

  const currentCode = $select.data('current-code');
  const currentName = $select.data('current-name');
  const currentDept = $select.data('current-depto');
  const currentDeptCode = $select.data('current-depto-code');
  if (currentCode && currentName && !$select.find('option[value="' + currentCode + '"]').length) {
    const opt = new Option(`${currentName}`, currentCode, true, true);
    $select.append(opt).trigger('change');
  }
  applyData({ id: currentCode, nombre_municipio: currentName, nombre_departamento: currentDept, codigo_departamento: currentDeptCode });
})();
</script>
