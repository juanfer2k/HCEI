<?php
/**
 * Componente visual FURTRAN Detallado (sin tablas, listo para header/footer)
 * Estilo limpio tipo ficha técnica ADRES – Circular 08/2023
 */

require_once __DIR__ . '/../conn.php';
$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<div class='error'>Debe especificar el parámetro ?id= del registro</div>";
    return;
}

$stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ? OR no_radicado_furtran = ? LIMIT 1");
$stmt->bind_param("ss", $id, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='error'>No se encontró la atención solicitada.</div>";
    return;
}

$row = $result->fetch_assoc();
$conn->close();

$row['localizacion'] = ($row['localizacion'] === 'Rural') ? 'R' : 'U';
$row['manifestacion_servicios'] = (strtoupper(trim($row['manifestacion_servicios'])) === 'SI') ? 1 : 0;
?>

<style>
.furtran-ficha {
  max-width: 900px;
  margin: 2rem auto;
  background: #fff;
  padding: 2rem;
  border-radius: 1rem;
  box-shadow: 0 0 20px rgba(0,0,0,0.1);
  font-family: 'Segoe UI', sans-serif;
}
.furtran-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 2px solid #ddd;
  padding-bottom: 1rem;
  margin-bottom: 1rem;
}
.furtran-title {
  font-size: 1.5rem;
  font-weight: 700;
}
.furtran-section {
  margin-bottom: 1.5rem;
}
.furtran-section h3 {
  font-size: 1.1rem;
  margin-bottom: .5rem;
  border-left: 4px solid #007bff;
  padding-left: .5rem;
  color: #007bff;
}
.furtran-field {
  display: flex;
  flex-wrap: wrap;
  margin-bottom: .5rem;
}
.furtran-label {
  flex: 1 0 40%;
  font-weight: 600;
  color: #444;
}
.furtran-value {
  flex: 1 0 55%;
  color: #222;
}
.furtran-buttons {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 1rem;
}
.furtran-btn {
  background: #007bff;
  color: #fff;
  padding: .5rem 1rem;
  border-radius: .5rem;
  text-decoration: none;
  font-weight: bold;
  transition: .2s ease;
}
.furtran-btn:hover { background: #0056b3; }
.furtran-btn-green { background: #28a745; }
.furtran-btn-green:hover { background: #1e7e34; }
.furtran-btn-print { background: #6c757d; }
.furtran-btn-print:hover { background: #555; }

@media print {
  header, footer, .furtran-buttons { display: none !important; }
  body { background: #fff; }
  .furtran-ficha { box-shadow: none; border: none; margin: 0; padding: 0; }
}
</style>

<div class="furtran-ficha">
  <div class="furtran-header">
    <div class="furtran-title">Ficha FURTRAN – Atención #<?= htmlspecialchars($row['id']) ?></div>
    <div class="furtran-buttons">
      <a href="?id=<?= urlencode($id) ?>&format=txt" class="furtran-btn">📄 TXT</a>
      <a href="?id=<?= urlencode($id) ?>&format=json" class="furtran-btn furtran-btn-green">🔍 JSON</a>
      <button onclick="window.print()" class="furtran-btn furtran-btn-print">🖨️ Imprimir</button>
    </div>
  </div>

  <!-- I. TRANSPORTADOR -->
  <div class="furtran-section">
    <h3>I. TRANSPORTADOR</h3>
    <div class="furtran-field"><div class="furtran-label">N° Factura</div><div class="furtran-value"><?= htmlspecialchars($row['numero_factura']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Empresa</div><div class="furtran-value"><?= htmlspecialchars($row['nombre_empresa_transportador']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Conductor</div><div class="furtran-value"><?= htmlspecialchars("{$row['primer_nombre_conductor']} {$row['segundo_nombre_conductor']} {$row['primer_apellido_conductor']} {$row['segundo_apellido_conductor']}") ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Placa Vehículo</div><div class="furtran-value"><?= htmlspecialchars($row['placa_vehiculo']) ?></div></div>
  </div>

  <!-- II. VÍCTIMA -->
  <div class="furtran-section">
    <h3>II. VÍCTIMA</h3>
    <?php
      $patientName = trim(implode(' ', array_filter([
        $row['primer_nombre_paciente'] ?? '',
        $row['segundo_nombre_paciente'] ?? '',
        $row['primer_apellido_paciente'] ?? '',
        $row['segundo_apellido_paciente'] ?? ''
      ])));
      if ($patientName === '') {
        $patientName = $row['nombres_paciente'] ?? '';
      }
    ?>
    <div class="furtran-field"><div class="furtran-label">Nombre Paciente</div><div class="furtran-value"><?= htmlspecialchars($patientName) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Documento</div><div class="furtran-value"><?= htmlspecialchars($row['tipo_identificacion'] . ' ' . $row['id_paciente']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Sexo</div><div class="furtran-value"><?= htmlspecialchars($row['desc_sexo']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Fecha Nacimiento</div><div class="furtran-value"><?= htmlspecialchars($row['fecha_nacimiento']) ?></div></div>
  </div>

  <!-- III. EVENTO -->
  <div class="furtran-section">
    <h3>III. EVENTO</h3>
    <div class="furtran-field"><div class="furtran-label">Tipo Evento</div><div class="furtran-value"><?= htmlspecialchars($row['tipo_evento']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Descripción</div><div class="furtran-value"><?= htmlspecialchars($row['desc_tipo_evento']) ?></div></div>
  </div>

  <!-- IV. RECOGIDA -->
  <div class="furtran-section">
    <h3>IV. RECOGIDA DE LA VÍCTIMA</h3>
    <div class="furtran-field"><div class="furtran-label">Dirección</div><div class="furtran-value"><?= htmlspecialchars($row['direccion_servicio']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Zona (U/R)</div><div class="furtran-value"><?= htmlspecialchars($row['localizacion']) ?></div></div>
  </div>

  <!-- V. CERTIFICACIÓN -->
  <div class="furtran-section">
    <h3>V. CERTIFICACIÓN DEL TRASLADO</h3>
    <div class="furtran-field"><div class="furtran-label">Fecha</div><div class="furtran-value"><?= htmlspecialchars($row['fecha']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Hora Traslado</div><div class="furtran-value"><?= htmlspecialchars($row['hora_traslado']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">IPS Receptora</div><div class="furtran-value"><?= htmlspecialchars($row['cod_habilitacion_ips']) ?></div></div>
  </div>

  <!-- VI. ACCIDENTE -->
  <div class="furtran-section">
    <h3>VI. ACCIDENTE DE TRÁNSITO</h3>
    <div class="furtran-field"><div class="furtran-label">Condición Víctima</div><div class="furtran-value"><?= htmlspecialchars($row['condicion_victima']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Aseguradora</div><div class="furtran-value"><?= htmlspecialchars($row['nombre_aseguradora']) ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Número Póliza</div><div class="furtran-value"><?= htmlspecialchars($row['numero_poliza']) ?></div></div>
  </div>

  <!-- VII. AMPARO -->
  <div class="furtran-section">
    <h3>VII. AMPARO RECLAMADO</h3>
    <div class="furtran-field"><div class="furtran-label">Valor Facturado</div><div class="furtran-value">$<?= number_format($row['valor_facturado'], 2, ',', '.') ?></div></div>
    <div class="furtran-field"><div class="furtran-label">Valor Reclamado</div><div class="furtran-value">$<?= number_format($row['valor_reclamado'], 2, ',', '.') ?></div></div>
  </div>

  <!-- VIII. MANIFESTACIÓN -->
  <div class="furtran-section">
    <h3>VIII. MANIFESTACIÓN DEL SERVICIO</h3>
    <div class="furtran-field"><div class="furtran-label">Manifestación</div><div class="furtran-value"><?= $row['manifestacion_servicios'] ? 'Sí' : 'No' ?></div></div>
  </div>
</div>
