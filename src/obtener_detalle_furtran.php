<?php
// === Lógica PHP inicial para cargar el registro FURTRAN y controlar acceso ===
// Sesión y configuración centralizadas
require_once __DIR__ . '/bootstrap.php';
// Control de acceso común
require_once __DIR__ . '/access_control.php';
// Conexión a BD y configuración de empresa
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/titulos.php';

// Control de acceso por rol usando valor canónico de sesión
$rolSesion = $_SESSION['usuario_rol'] ?? '';
$rolKey = strtolower(is_string($rolSesion) ? trim($rolSesion) : '');
if (!in_array($rolSesion, ['Master','Administrativo'], true) && !in_array($rolKey, ['administrativo','master','secretaria','dev'], true)) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$busqueda = trim($_GET['q'] ?? '');

// ===============================
// 1) Vista de búsqueda/listado
// ===============================
if ($id <= 0) {
    $resultados = [];
    $total = 0;

    if ($conn instanceof mysqli) {
        if ($busqueda !== '') {
            $like = '%' . $busqueda . '%';

            $nameExpr = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', primer_nombre_paciente, segundo_nombre_paciente, primer_apellido_paciente, segundo_apellido_paciente)), ''), nombres_paciente) AS paciente_nombre";
            if (ctype_digit($busqueda)) {
                $stmt = $conn->prepare("SELECT id, fecha, servicio, pagador, tipo_identificacion, id_paciente, $nameExpr FROM atenciones WHERE id = ? ORDER BY id DESC LIMIT 50");
                if ($stmt) {
                    $stmt->bind_param('i', $busqueda);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $resultados = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                    $total = $res ? $res->num_rows : 0;
                    $stmt->close();
                }
            }

            if ($total === 0) {
                $stmt = $conn->prepare("SELECT id, fecha, servicio, pagador, tipo_identificacion, id_paciente, $nameExpr FROM atenciones WHERE nombres_paciente LIKE ? OR id_paciente LIKE ? ORDER BY id DESC LIMIT 50");
                if ($stmt) {
                    $stmt->bind_param('ss', $like, $like);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $resultados = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                    $total = $res ? $res->num_rows : 0;
                    $stmt->close();
                }
            }
        } else {
            $sql = "SELECT id, fecha, servicio, pagador, tipo_identificacion, id_paciente, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', primer_nombre_paciente, segundo_nombre_paciente, primer_apellido_paciente, segundo_apellido_paciente)), ''), nombres_paciente) AS paciente_nombre FROM atenciones ORDER BY id DESC LIMIT 25";
            if ($res = $conn->query($sql)) {
                $resultados = $res->fetch_all(MYSQLI_ASSOC);
                $total = $res->num_rows;
                $res->close();
            }
        }
    }

    $pageTitle = 'Generar FURTRAN';
    require_once __DIR__ . '/header.php';
    ?>
    <div class="container my-4">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0">Generar / Consultar FURTRAN</h4>
        </div>
        <div class="card-body">
          <p class="text-muted">Busca la atención que deseas revisar. Desde aquí puedes abrir el detalle o descargar el PDF oficial.</p>
          <form class="row g-3" method="get">
            <div class="col-md-8">
              <label for="busqueda" class="form-label">Buscar por ID, Radicado, Documento o Nombre</label>
              <input type="text" name="q" id="busqueda" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Ej. 1523 o CC 123456 o Juan Pérez">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar</button>
            </div>
          </form>
        </div>
      </div>

      <style>
        .furtran-results-list {
          padding: 1rem;
          display: flex;
          flex-direction: column;
          gap: 1rem;
        }
        .furtran-result-card {
          border: 1px solid #e9ecef;
          border-radius: .85rem;
          padding: 1rem 1.25rem;
          background: var(--bs-body-bg);
          box-shadow: 0 0.125rem 0.4rem rgba(0,0,0,0.04);
        }
        .furtran-result-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 1.5rem;
          margin-bottom: .75rem;
        }
        .furtran-result-meta div {
          min-width: 120px;
        }
      </style>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Resultados</span>
          <span class="badge bg-secondary"><?= $total ?> registro(s)</span>
        </div>
        <div class="card-body p-0">
          <?php if ($total === 0): ?>
            <div class="p-4 text-center text-muted">No se encontraron registros.</div>
          <?php else: ?>
            <div class="furtran-results-list">
              <?php foreach ($resultados as $row): ?>
                <div class="furtran-result-card">
                  <div class="d-flex justify-content-between flex-wrap gap-3">
                    <div>
                      <span class="badge bg-light text-dark mb-1">ID #<?= htmlspecialchars($row['id']) ?></span>
                      <div class="fs-5 fw-semibold mb-1"><?= htmlspecialchars($row['paciente_nombre'] ?? $row['nombres_paciente'] ?? '-') ?></div>
                      <div class="text-muted small">Documento: <?= htmlspecialchars(($row['tipo_identificacion'] ?? '') . ' ' . ($row['id_paciente'] ?? '')) ?></div>
                    </div>
                    <div class="text-end">
                      <div class="fw-semibold"><?= htmlspecialchars($row['servicio'] ?? '-') ?></div>
                      <div class="text-muted small">Pagador: <?= htmlspecialchars($row['pagador'] ?? '-') ?></div>
                      <div class="text-muted small">Fecha: <?= htmlspecialchars($row['fecha'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="d-flex flex-wrap gap-2 mt-3 justify-content-end">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL . 'obtener_detalle_furtran.php?id=' . $row['id']) ?>">
                      <i class="bi bi-eye"></i> Ver detalle
                    </a>
                    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= htmlspecialchars(BASE_URL . 'generar_FURTRAN.php?id=' . $row['id']) ?>">
                      <i class="bi bi-file-earmark-pdf"></i> Ver PDF
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    $conn->close();
    exit;
}

// ===============================
// 2) Vista detallada por atención
// ===============================
$stmt = $conn->prepare('SELECT * FROM atenciones WHERE id = ? LIMIT 1');
if (!$stmt) {
    exit('Error en la preparación de la consulta: ' . $conn->error);
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    $pageTitle = 'Detalle FURTRAN';
    require_once __DIR__ . '/header.php';
    echo "<div class='container my-4'><div class='alert alert-warning'>No se encontró el registro solicitado.</div></div>";
    require_once __DIR__ . '/footer.php';
    $stmt->close();
    $conn->close();
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

$pageTitle = 'Detalle FURTRAN - Atención #' . htmlspecialchars($row['id']);
require_once __DIR__ . '/header.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function showFieldDiv($label, $value) {
    $v = ($value !== null && $value !== '') ? h($value) : '<span class="text-muted">(sin dato)</span>';
    echo '
        <div class="detalle-row">
            <div class="campo">' . $label . '</div>
            <div class="valor">' . $v . '</div>
        </div>
    ';
}

echo '
<style>
.detalle-furtran {
  font-size: 0.9rem;
  border: 1px solid #dee2e6;
  border-radius: .25rem;
  overflow: hidden;
}
.detalle-row {
  display: flex;
  padding: 8px 12px;
  border-bottom: 1px solid #e9ecef;
  background-color: var(--bs-body-bg);
}
.detalle-row:last-child { border-bottom: none; }
.detalle-row:nth-child(odd) { background-color: var(--bs-tertiary-bg); }
.campo {
  flex: 0 0 250px;
  font-weight: 600;
  color: var(--bs-secondary-color);
}
.valor {
  flex: 1;
  color: var(--bs-body-color);
  display: flex;
  align-items: center;
}
</style>
';

echo "<div class='container mt-4'>";
echo "<h2 class='mb-3'>Ficha FURTRAN – Atención #" . h($row['id']) . "</h2>";
echo "<p class='text-muted'><i>Formato detallado según Anexo Técnico FURTRAN (Circular 08/2023 – ADRES)</i></p>";

echo '<div class="mb-4 d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="' . htmlspecialchars(BASE_URL . 'obtener_detalle_furtran.php') . '"><i class="bi bi-arrow-left"></i> Volver al listado</a>
    <a href="' . htmlspecialchars(BASE_URL . 'generar_FURTRAN.php?id=' . $id) . '" class="btn btn-primary" target="_blank" rel="noopener">
        <i class="bi bi-file-earmark-pdf"></i> Descargar PDF oficial
    </a>
</div>';

// I. TRANSPORTADOR
echo '<h4 class="mt-4">I. Transportador</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Número de Factura", $row['numero_factura']);
showFieldDiv("Código Habilitación Empresa", $row['codigo_habilitacion_empresa']);
showFieldDiv("Conductor", trim("{$row['primer_nombre_conductor']} {$row['segundo_nombre_conductor']} {$row['primer_apellido_conductor']} {$row['segundo_apellido_conductor']}"));
showFieldDiv("Tipo Doc. Conductor", $row['tipo_id_conductor']);
showFieldDiv("Documento Conductor", $row['cc_conductor']);
showFieldDiv("Tipo Vehículo Ambulancia", $row['tipo_vehiculo_ambulancia']);
showFieldDiv("Placa Vehículo", $row['placa_vehiculo']);
showFieldDiv("Dirección Transportador", $row['direccion_empresa_transportador']);
showFieldDiv("Teléfono Transportador", $row['telefono_empresa_transportador']);
showFieldDiv("Departamento", $row['codigo_departamento_empresa']);
showFieldDiv("Municipio", $row['codigo_municipio_empresa']);
echo '</div>';

// II. VÍCTIMA
echo '<h4 class="mt-4">II. Víctima</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Tipo Identificación", $row['tipo_identificacion']);
showFieldDiv("Número Identificación", $row['id_paciente']);
showFieldDiv(
    "Nombres",
    trim(
        implode(' ', array_filter([
            $row['nombres_paciente'] ?? '',
            $row['segundo_nombre_paciente'] ?? ''
        ]))
    )
);
showFieldDiv(
    "Apellidos",
    trim(
        implode(' ', array_filter([
            $row['primer_apellido_paciente'] ?? '',
            $row['segundo_apellido_paciente'] ?? ''
        ]))
    )
);
showFieldDiv("Fecha Nacimiento", $row['fecha_nacimiento']);
showFieldDiv("Sexo", $row['desc_sexo']);
echo '</div>';

// III. EVENTO
echo '<h4 class="mt-4">III. Evento</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Tipo Evento", $row['tipo_evento']);
showFieldDiv("Descripción Evento", $row['desc_tipo_evento']);
echo '</div>';

// IV. RECOGIDA DE LA VÍCTIMA
echo '<h4 class="mt-4">IV. Recogida de la Víctima</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Dirección Servicio", $row['direccion_servicio']);
showFieldDiv("Código DANE Depto", $row['cod_depto_recogida']);
showFieldDiv("Código DANE Ciudad", $row['cod_ciudad_recogida']);
showFieldDiv("Zona (U/R)", strtoupper($row['localizacion'] ?? ''));
echo '</div>';

// V. CERTIFICACIÓN DEL TRASLADO
echo '<h4 class="mt-4">V. Certificación del Traslado</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Fecha", $row['fecha']);
showFieldDiv("Hora Traslado", $row['hora_traslado']);
showFieldDiv("Código Habilitación IPS", $row['cod_habilitacion_ips']);
showFieldDiv("Código Dpto IPS", $row['cod_depto_ips']);
showFieldDiv("Código Ciudad IPS", $row['cod_ciudad_ips']);
echo '</div>';

// VI. ACCIDENTE DE TRÁNSITO
echo '<h4 class="mt-4">VI. Accidente de Tránsito</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Condición Víctima", $row['condicion_victima']);
showFieldDiv("Estado Aseguramiento", $row['estado_aseguramiento']);
showFieldDiv("Tipo Vehículo Involucrado", $row['tipo_vehiculo_accidente']);
showFieldDiv("Placa Involucrado", $row['placa_vehiculo_involucrado']);
showFieldDiv("Código Aseguradora", $row['codigo_aseguradora']);
showFieldDiv("Número Póliza", $row['numero_poliza']);
showFieldDiv("Vigencia Desde", $row['fecha_inicio_poliza']);
showFieldDiv("Vigencia Hasta", $row['fecha_fin_poliza']);
showFieldDiv("Radicado SRAS", $row['radicado_sras']);
echo '</div>';

// VII. AMPARO RECLAMADO
echo '<h4 class="mt-4">VII. Amparo Reclamado</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Valor Facturado", '$' . number_format($row['valor_facturado'] ?? 0, 2, ',', '.'));
showFieldDiv("Valor Reclamado", '$' . number_format($row['valor_reclamado'] ?? 0, 2, ',', '.'));
echo '</div>';

// VIII. MANIFESTACIÓN DEL SERVICIO
echo '<h4 class="mt-4">VIII. Manifestación del Servicio</h4>';
echo '<div class="detalle-furtran mb-4">';
showFieldDiv("Manifestación (1=Sí,0=No)", $row['manifestacion_servicios']);
echo '</div>';

echo "</div>";

require_once __DIR__ . '/footer.php';
$conn->close();
?>
