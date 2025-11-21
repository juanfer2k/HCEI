<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';
$allowed = ['administrativo','master','dev'];
if (!in_array($rol, $allowed, true)) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'ID inválido';
    exit;
}

// Cargar atención
$stmt = $conn->prepare('SELECT * FROM atenciones WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$atencion = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$atencion) {
    http_response_code(404);
    echo 'Atención no encontrada';
    exit;
}

// Cargar datos del municipio desde la BD si existe cod_ciudad_recogida
$municipioData = null;
if (!empty($atencion['cod_ciudad_recogida'])) {
    $stmtMun = $conn->prepare('
        SELECT m.codigo_municipio, m.nombre_municipio, d.codigo_departamento, d.nombre_departamento 
        FROM municipios m 
        LEFT JOIN departamentos d ON m.codigo_departamento = d.codigo_departamento 
        WHERE m.codigo_municipio = ?
    ');
    $stmtMun->bind_param('s', $atencion['cod_ciudad_recogida']);
    $stmtMun->execute();
    $resMun = $stmtMun->get_result();
    $municipioData = $resMun ? $resMun->fetch_assoc() : null;
    $stmtMun->close();
}

// Procesar POST (guardar cambios administrativos)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $camposEditable = [
        'pagador',
        'eps_nombre',
        'tipo_usuario',
        'tipo_afiliacion',
        'numero_afiliacion',
        'estado_afiliacion',
        'nombre_aseguradora',
        'codigo_aseguradora',
        'numero_poliza',
        'fecha_inicio_poliza',
        'fecha_fin_poliza',
        'direccion_servicio',
        'localizacion',
        'cod_depto_recogida',
        'cod_ciudad_recogida',
        'nombre_ips_receptora',
        'nit_ips_receptora'
    ];

    $motivo = trim($_POST['motivo_cambio_admin'] ?? '');
    if ($motivo === '') {
        $_SESSION['message'] = '<div class="alert alert-danger">Debe indicar un motivo para los cambios administrativos.</div>';
        header('Location: editar_atencion_admin.php?id=' . $id);
        exit;
    }

    $updates = [];
    $types  = '';
    $values = [];
    $cambios = [];

    foreach ($camposEditable as $campo) {
        if (!array_key_exists($campo, $atencion)) continue;
        $nuevo = $_POST[$campo] ?? null;
        if ($nuevo === null) $nuevo = '';
        $nuevo = is_string($nuevo) ? trim($nuevo) : $nuevo;
        $actual = $atencion[$campo];
        if ((string)$nuevo !== (string)$actual) {
            $updates[] = "$campo = ?";
            $types   .= 's';
            $values[] = $nuevo;
            $cambios[] = [
                'campo' => $campo,
                'antes' => (string)$actual,
                'despues' => (string)$nuevo,
            ];
            $atencion[$campo] = $nuevo;
        }
    }

    if ($updates) {
        // Añadir metadata de ultima modificacion admin si existen las columnas
        $cols = [];
        if ($rs = $conn->query("SHOW COLUMNS FROM atenciones")) {
            while ($row = $rs->fetch_assoc()) { $cols[$row['Field']] = true; }
            $rs->close();
        }
        if (isset($cols['ultima_modificacion_admin'])) {
            $updates[] = 'ultima_modificacion_admin = NOW()';
        }
        if (isset($cols['ultima_modificacion_admin_usuario'])) {
            $updates[] = 'ultima_modificacion_admin_usuario = ?';
            $types    .= 'i';
            $values[]  = (int)($_SESSION['usuario_id'] ?? 0);
        }

        $sql = 'UPDATE atenciones SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $id;

        $stmtUp = $conn->prepare($sql);
        if ($stmtUp) {
            $stmtUp->bind_param($types, ...$values);
            $stmtUp->execute();
            $stmtUp->close();
        }

        // Registrar historial si existe tabla atenciones_historial
        try {
            $conn->query('SELECT 1 FROM atenciones_historial LIMIT 1');
            if ($cambios) {
                $stmtHist = $conn->prepare('INSERT INTO atenciones_historial (atencion_id, campo, valor_anterior, valor_nuevo, usuario_id, motivo) VALUES (?,?,?,?,?,?)');
                if ($stmtHist) {
                    $uid = (int)($_SESSION['usuario_id'] ?? 0);
                    foreach ($cambios as $c) {
                        $antes   = $c['antes'];
                        $despues = $c['despues'];
                        $campo   = $c['campo'];
                        $stmtHist->bind_param('isssis', $id, $campo, $antes, $despues, $uid, $motivo);
                        $stmtHist->execute();
                    }
                    $stmtHist->close();
                }
            }
        } catch (Throwable $e) {
            // Si la tabla no existe, no interrumpimos el flujo
        }

        $_SESSION['message'] = '<div class="alert alert-success">Datos administrativos actualizados correctamente.</div>';
    } else {
        $_SESSION['message'] = '<div class="alert alert-info">No se detectaron cambios administrativos.</div>';
    }

    header('Location: ../consulta_atenciones.php?query=' . urlencode($id));
    exit;
}

include __DIR__ . '/../header.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

?>
<div class="container mt-4">
  <h2 class="mb-3">Editar datos administrativos de la atención #<?php echo h($atencion['id']); ?></h2>

  <?php if (!empty($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } ?>

  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title mb-2">Resumen de la atención</h5>
      <p class="mb-1"><strong>Estado de Registro:</strong> <?php echo h($atencion['estado'] ?? 'ACTIVA'); ?></p>
      <p class="mb-1"><strong>Paciente:</strong> <?php echo h($atencion['nombres_paciente'] ?? ''); ?></p>
      <p class="mb-1"><strong>Documento:</strong> <?php echo h(($atencion['tipo_identificacion'] ?? '') . ' ' . ($atencion['id_paciente'] ?? '')); ?></p>
      <p class="mb-1"><strong>Fecha atención:</strong> <?php echo h($atencion['fecha'] ?? ($atencion['fecha_hora_atencion'] ?? '')); ?></p>
      <p class="mb-0"><strong>Servicio:</strong> <?php echo h($atencion['servicio'] ?? ''); ?></p>
    </div>
  </div>

  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div class="text-muted small">Necesitas contrastar con la vista operativa.</div>
    <div class="d-flex gap-2">
      <a href="../obtener_detalle_atencion.php?id=<?php echo urlencode($atencion['id']); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
        Ver detalle (vista tripulación)
      </a>
      <a href="../obtener_detalle_atencion.php?id=<?php echo urlencode($atencion['id']); ?>&simular_trip=1" target="_blank" rel="noopener" class="btn btn-outline-warning btn-sm">
        Ver detalle (simular rol trip)
      </a>
    </div>
  </div>

  <form method="post">
    <div class="card mb-3">
      <div class="card-header">Pagador y Afiliación</div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label for="pagador" class="form-label">Pagador</label>
          <select class="form-select" id="pagador" name="pagador">
            <?php
              $optsPagador = ['Particular','SOAT','ARL','EPS','ADRES','Servicio Social','Convenio'];
              $curPagador = $atencion['pagador'] ?? '';
              foreach ($optsPagador as $opt) {
                $sel = ((string)$curPagador === $opt) ? 'selected' : '';
                echo '<option value="'.h($opt).'" '.$sel.'>'.h($opt).'</option>';
              }
            ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="eps_nombre" class="form-label">Nombre de la EPS</label>
          <input type="text" class="form-control" id="eps_nombre" name="eps_nombre" value="<?php echo h($atencion['eps_nombre'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label for="estado_afiliacion" class="form-label">Estado de Afiliación</label>
          <input type="text" class="form-control" id="estado_afiliacion" name="estado_afiliacion" value="<?php echo h($atencion['estado_afiliacion'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label for="tipo_usuario" class="form-label">Tipo de Usuario</label>
          <input type="text" class="form-control" id="tipo_usuario" name="tipo_usuario" value="<?php echo h($atencion['tipo_usuario'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label for="tipo_afiliacion" class="form-label">Tipo de Afiliación</label>
          <input type="text" class="form-control" id="tipo_afiliacion" name="tipo_afiliacion" value="<?php echo h($atencion['tipo_afiliacion'] ?? ''); ?>">
        </div>
        <div class="col-md-4">
          <label for="numero_afiliacion" class="form-label">Número de Afiliación</label>
          <input type="text" class="form-control" id="numero_afiliacion" name="numero_afiliacion" value="<?php echo h($atencion['numero_afiliacion'] ?? ''); ?>">
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Datos de Aseguradora / SOAT / ARL</div>
      <div class="card-body row g-3">
        <div class="col-md-6">
          <label for="nombre_aseguradora" class="form-label">Nombre aseguradora</label>
          <input type="text" class="form-control" id="nombre_aseguradora" name="nombre_aseguradora" value="<?php echo h($atencion['nombre_aseguradora'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
          <label for="codigo_aseguradora" class="form-label">Código aseguradora</label>
          <input type="text" class="form-control" id="codigo_aseguradora" name="codigo_aseguradora" value="<?php echo h($atencion['codigo_aseguradora'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
          <label for="numero_poliza" class="form-label">Número de póliza</label>
          <input type="text" class="form-control" id="numero_poliza" name="numero_poliza" value="<?php echo h($atencion['numero_poliza'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
          <label for="fecha_inicio_poliza" class="form-label">Fecha inicio póliza</label>
          <input type="date" class="form-control" id="fecha_inicio_poliza" name="fecha_inicio_poliza" value="<?php echo h($atencion['fecha_inicio_poliza'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
          <label for="fecha_fin_poliza" class="form-label">Fecha fin póliza <small class="text-muted">(auto-calculada +1 año)</small></label>
          <input type="date" class="form-control" id="fecha_fin_poliza" name="fecha_fin_poliza" value="<?php echo h($atencion['fecha_fin_poliza'] ?? ''); ?>">
          <small class="text-muted">Se calcula automáticamente. Puede editarse si es diferente.</small>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Lugar del servicio / recogida</div>
      <div class="card-body row g-3">
        <div class="col-md-6">
          <label for="direccion_servicio" class="form-label">Dirección del servicio</label>
          <input type="text" class="form-control" id="direccion_servicio" name="direccion_servicio" value="<?php echo h($atencion['direccion_servicio'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label d-block">Localización (Urbano/Rural)</label>
          <select class="form-select" id="localizacion" name="localizacion">
            <?php
              $locActual = $atencion['localizacion'] ?? '';
              $locOpts = ['', 'Urbano', 'Rural'];
              foreach ($locOpts as $opt) {
                $sel = ($opt !== '' && $opt === $locActual) ? 'selected' : '';
                $label = $opt === '' ? 'Seleccione...' : $opt;
                echo '<option value="'.h($opt).'" '.$sel.'>'.h($label).'</option>';
              }
            ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="cod_depto_recogida" class="form-label">Código DANE Departamento</label>
          <input type="text" class="form-control" id="cod_depto_recogida" name="cod_depto_recogida" 
                 value="<?php echo h($municipioData['codigo_departamento'] ?? $atencion['cod_depto_recogida'] ?? ''); ?>" readonly>
          <small class="text-muted">Cargado desde BD</small>
        </div>
        <div class="col-md-3">
          <label for="cod_ciudad_recogida" class="form-label">Código DANE Municipio</label>
          <input type="text" class="form-control" id="cod_ciudad_recogida" name="cod_ciudad_recogida" 
                 value="<?php echo h($municipioData['codigo_municipio'] ?? $atencion['cod_ciudad_recogida'] ?? ''); ?>" readonly>
          <small class="text-muted">Cargado desde BD</small>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nombre Municipio</label>
          <input type="text" class="form-control" 
                 value="<?php echo h($municipioData['nombre_municipio'] ?? 'No disponible'); ?>" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nombre Departamento</label>
          <input type="text" class="form-control" 
                 value="<?php echo h($municipioData['nombre_departamento'] ?? 'No disponible'); ?>" readonly>
        </div>
        <div class="col-md-6">
          <label for="nombre_ips_receptora" class="form-label">Nombre IPS receptora</label>
          <input type="text" class="form-control" id="nombre_ips_receptora" name="nombre_ips_receptora" value="<?php echo h($atencion['nombre_ips_receptora'] ?? ''); ?>">
        </div>
        <div class="col-md-3">
          <label for="nit_ips_receptora" class="form-label">NIT IPS receptora</label>
          <input type="text" class="form-control" id="nit_ips_receptora" name="nit_ips_receptora" value="<?php echo h($atencion['nit_ips_receptora'] ?? ''); ?>">
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Motivo de la modificación</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="motivo_cambio_admin" class="form-label">Describa el motivo de los cambios</label>
          <textarea class="form-control" id="motivo_cambio_admin" name="motivo_cambio_admin" rows="3" required></textarea>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
      <a href="../consulta_atenciones.php?query=<?php echo urlencode($atencion['id']); ?>" class="btn btn-outline-secondary">Volver a consulta</a>
      <button type="submit" class="btn btn-primary">Guardar cambios administrativos</button>
    </div>
  </form>
</div>

<script>
// Auto-calculate policy end date (1 year from start date)
document.addEventListener('DOMContentLoaded', function() {
  const fechaInicio = document.getElementById('fecha_inicio_poliza');
  const fechaFin = document.getElementById('fecha_fin_poliza');
  
  if (fechaInicio && fechaFin) {
    // Store original end date to detect manual changes
    let originalEndDate = fechaFin.value;
    let manuallyEdited = false;
    
    // Mark as manually edited if user changes the end date
    fechaFin.addEventListener('change', function() {
      manuallyEdited = true;
    });
    
    fechaInicio.addEventListener('change', function() {
      if (!fechaInicio.value) {
        fechaFin.value = '';
        return;
      }
      
      // Only auto-calculate if not manually edited
      if (!manuallyEdited) {
        const startDate = new Date(fechaInicio.value);
        if (!isNaN(startDate.getTime())) {
          // Add 1 year
          const endDate = new Date(startDate);
          endDate.setFullYear(endDate.getFullYear() + 1);
          
          // Format as YYYY-MM-DD
          const year = endDate.getFullYear();
          const month = String(endDate.getMonth() + 1).padStart(2, '0');
          const day = String(endDate.getDate()).padStart(2, '0');
          fechaFin.value = `${year}-${month}-${day}`;
        }
      }
    });
    
    // Trigger calculation on page load if start date exists but end date doesn't
    if (fechaInicio.value && !fechaFin.value) {
      fechaInicio.dispatchEvent(new Event('change'));
    }
  }
});
</script>

<?php include __DIR__ . '/../footer.php'; ?>
