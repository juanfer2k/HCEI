<?php
// obtener_detalle_atencion.php — Documento compacto con firmas, adjuntos y header/footer
// - Usa conn.php ($conn = new mysqli...)
// - SELECT * para traer todos los campos existentes
// - Encabezado con pill TAB/TAM (corta), estilo de documento MUY compacto
// - Muestra firmas (imagen si hay BLOB/base64/ruta) y adjuntos (miniaturas/enlaces)
// - Incluye header.php y footer.php si existen; si no, usa fallback mínimo

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/conn.php';
date_default_timezone_set('America/Bogota');

// Helpers básicos
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function v($row,$key,$dash='—'){ return isset($row[$key]) && $row[$key] !== '' ? h($row[$key]) : $dash; }
function dmyhm($dt){ return $dt ? date('Y-m-d H:i', strtotime($dt)) : '—'; }
function hm($t){ return $t ? date('H:i', strtotime($t)) : '—'; }
function dmy($d){ return $d ? date('Y-m-d', strtotime($d)) : '—'; }

// Detección/creación de <img src> a partir de distintos formatos (blob, base64, ruta)
function build_img_src($val, $isBlob=false){
  if ($isBlob) {
    if ($val === null || $val === '') return null;
    $b64 = base64_encode($val);
    return "data:image/png;base64,".$b64;
  }
  if (!$val) return null;
  $val = trim((string)$val);
  // data URI directo
  if (stripos($val, 'data:image/') === 0) return $val;
  // ruta con extensión de imagen
  if (preg_match('~\.(png|jpe?g|gif|webp)$~i', $val)) return $val;
  // base64 "suelta"
  $clean = preg_replace('~\s+~', '', $val);
  if (preg_match('~^[A-Za-z0-9+/=]{100,}$~', $clean)) {
    // Intentamos decodificar para validar
    $bin = base64_decode($clean, true);
    if ($bin !== false && strlen($bin) > 0) {
      return "data:image/png;base64,".$clean;
    }
  }
  return null; // no es renderizable como imagen
}

// Adjuntos: intenta parsear JSON o separadores comunes
function parse_attachments($text){
  if (!$text) return [];
  $text = trim($text);
  // JSON array?
  $json = json_decode($text, true);
  if (is_array($json)) {
    return array_values(array_filter(array_map('trim', $json)));
  }
  // Separado por saltos/comas/punto y coma
  $parts = preg_split('~[\r\n,;]+~', $text);
  return array_values(array_filter(array_map('trim', $parts)));
}

// === Cargar registro ===
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo "ID inválido."; exit; }

$stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if ($row['aceptacion'] == 1) { echo '<script>document.querySelectorAll("input, select, textarea").forEach(e => e.disabled = true);</script>'; }
if (!$row) { http_response_code(404); echo "No se encontró la atención solicitada."; exit; }

// Nivel TAB/TAM (corto)
$nivel = !empty($row['medico_tripulante']) ? 'TAM' : 'TAB';
$nivelClass = $nivel === 'TAM' ? 'pill-danger' : 'pill-success';

// Destino preferente
$destino = '—';
if (!empty($row['nombre_ips_receptora'])) {
  $destino = $row['nombre_ips_receptora'] . (!empty($row['nit_ips_receptora']) ? ' (NIT '. $row['nit_ips_receptora'] .')' : '');
} elseif (!empty($row['ips_destino'])) {
  $destino = $row['ips_destino'];
}

// Firmas: intentamos mostrar imagen si hay datos
$src_firma_paciente   = build_img_src($row['firma_paciente'] ?? null);
$src_firma_desist     = build_img_src($row['firma_desistimiento'] ?? null);
$src_firma_paramedico = build_img_src($row['firma_paramedico'] ?? null);
$src_firma_medico     = build_img_src($row['firma_medico'] ?? null);
$src_firma_receptor   = build_img_src($row['firma_medico_receptor'] ?? null, true); // BLOB

// Adjuntos
$adjuntos = parse_attachments($row['adjuntos'] ?? null);

// ===== Header (si existe) o fallback mínimo =====
$hasHeader = @include __DIR__ . '/header.php';
if (!$hasHeader){
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Detalle Atención #<?php echo h($id); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php
}
?>
<style>
  :root{
    --doc-font-size: 12px;
    --doc-line-height: 1.12;
    --doc-pad: 6px;
    --doc-gap: 4px;
    --gray: #6c757d;
    --border: #d7dbe0;
  }
  body{ background:#f6f8fb; }
  .doc-wrap{ max-width: 980px; margin: 8px auto; background:#fff; border:1px solid var(--border); box-shadow:0 6px 18px rgba(0,0,0,.05); }
  .actions{ display:flex; gap:6px; justify-content:flex-end; padding:4px var(--doc-pad); border-bottom:1px solid var(--border); background:#fff; }
  .doc-header{ display:flex; justify-content:space-between; align-items:flex-start; padding:4px var(--doc-pad); border-bottom:1px solid var(--border); background:#fff; }
  .title { margin:0; font-weight:700; font-size:14px; line-height:1.1; }
  .subtitle { margin:1px 0 0; color:var(--gray); font-size:11px; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-weight:700; font-size:11px; letter-spacing:.2px; }
  .pill-success{ background:#198754; color:#fff; }
  .pill-danger{  background:#dc3545; color:#fff; }

  .sec{ padding:5px var(--doc-pad); border-top:1px solid var(--border); }
  .sec:first-of-type{ border-top:0; }
  .sec h3{ margin:0 0 4px; font-size:11px; letter-spacing:.4px; text-transform:uppercase; color:#111; }

  .kv{ display:grid; grid-template-columns: 1fr 1fr; column-gap: 14px; row-gap: var(--doc-gap); }
  .kv.full{ grid-template-columns: 1fr; }
  .item{ display:flex; gap:8px; align-items:baseline; }
  .lbl{ min-width: 160px; color:#333; font-weight:600; font-size:11px; }
  .val{ flex:1; border-bottom:1px dotted #c6cbd1; padding-bottom:1px; color:#111; }

  .tbl{ width:100%; border-collapse:collapse; }
  .tbl th, .tbl td{ border:1px solid #e5e7eb; padding:3px 5px; font-size:11px; }
  .tbl th{ background:#f2f4f7; font-weight:700; }

  .block{ padding:3px 4px; border:1px dashed #d7dbe0; background:#fcfdff; min-height: 22px; font-size: 12px; }

  .sig-grid{ display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; }
  @media (min-width: 992px){ .sig-grid{ grid-template-columns: repeat(4, 1fr); } }
  .sig-box{ border:1px dashed #cbd3da; padding:4px; background:#fff; min-height:110px; display:flex; align-items:center; justify-content:center; }
  .sig-box img{ max-width:100%; max-height:90px; object-fit:contain; }
  .sig-cap{ text-align:center; font-size:11px; margin-top:2px; color:#333; }

  .att-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; }
  @media (min-width: 992px){ .att-grid{ grid-template-columns: repeat(6, 1fr); } }
  .att{ border:1px solid #e5e7eb; padding:3px; background:#fff; text-align:center; }
  .att img{ width:100%; height:80px; object-fit:cover; }
  .att a{ font-size:11px; display:block; margin-top:2px; word-break:break-all; }

  .small-muted{ color:var(--gray); font-size: 11px; }

  @media print{
    body{ background:#fff; }
    .actions{ display:none !important; }
    .doc-wrap{ margin:0; border:0; box-shadow:none; }
    @page{ size:A4 portrait; margin:10mm; }
  }
</style>
<?php if (!$hasHeader) { echo "</head><body>"; } ?>

<div class="doc-wrap">

  <!-- Barra acciones (no imprime) -->
  <div class="actions">
    <a class="btn btn-sm btn-outline-secondary" href="javascript:history.back()">Volver</a>
    <a class="btn btn-sm btn-outline-primary" href="generar_pdf.php?id=<?php echo urlencode($row['id']); ?>" target="_blank" rel="noopener">PDF</a>
    <button class="btn btn-sm btn-success" onclick="window.print()">Imprimir</button>
  </div>

  <!-- Encabezado -->
  <div class="doc-header">
    <div>
      <h1 class="title">Registro de Atención Prehospitalaria</h1>
      <p class="subtitle">ID <?php echo h($row['id']); ?> · Reg. <?php echo h($row['registro']); ?> · <?php echo dmyhm($row['fecha_hora_atencion']); ?></p>
    </div>
    <div class="<?php echo 'pill '.$nivelClass; ?>"><?php echo h($nivel); ?></div>
  </div>

  <!-- Paciente -->
  <div class="sec">
    <h3>Paciente</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Nombre</div><div class="val"><?php echo v($row,'nombres_paciente'); ?></div></div>
      <div class="item"><div class="lbl">Documento</div><div class="val"><?php echo h(($row['tipo_identificacion'] ?: 'ID').' · '.($row['id_paciente'] ?: '—')); ?></div></div>
      <div class="item"><div class="lbl">Género</div><div class="val"><?php echo v($row,'genero_nacer'); ?></div></div>
      <div class="item"><div class="lbl">Fecha Nac.</div><div class="val"><?php echo dmy($row['fecha_nacimiento']); ?></div></div>
      <div class="item"><div class="lbl">RH</div><div class="val"><?php echo v($row,'rh'); ?></div></div>
      <div class="item"><div class="lbl">EPS</div><div class="val"><?php echo v($row,'eps_nombre'); ?></div></div>
      <div class="item"><div class="lbl">Teléfono</div><div class="val"><?php echo v($row,'telefono_paciente'); ?></div></div>
      <div class="item"><div class="lbl">Barrio / Ciudad</div><div class="val"><?php echo h(($row['barrio_paciente'] ?: '—').' · '.($row['ciudad'] ?: '—')); ?></div></div>
      <div class="item"><div class="lbl">Dirección domicilio</div><div class="val"><?php echo v($row,'direccion_domicilio'); ?></div></div>
      <div class="item"><div class="lbl">Acompañante</div><div class="val"><?php echo h(($row['nombre_acompanante'] ?: '—').' · '.($row['parentesco_acompanante'] ?: '—').' · '.($row['id_acompanante'] ?: '—')); ?></div></div>
    </div>
  </div>

  <!-- Evento / Servicio -->
  <div class="sec">
    <h3>Evento / Servicio</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Servicio</div><div class="val"><?php echo v($row,'servicio'); ?></div></div>
      <div class="item"><div class="lbl">Tipo de traslado</div><div class="val"><?php echo h(($row['tipo_traslado'] ?: '—').($row['traslado_tipo'] ? ' / '.$row['traslado_tipo'] : '')); ?></div></div>
      <div class="item"><div class="lbl">Pagador</div><div class="val"><?php echo v($row,'pagador'); ?></div></div>
      <div class="item"><div class="lbl">Quien informó</div><div class="val"><?php echo v($row,'quien_informo'); ?></div></div>
      <div class="item"><div class="lbl">Aseguradora SOAT</div><div class="val"><?php echo v($row,'aseguradora_soat'); ?></div></div>
      <div class="item"><div class="lbl">Fecha de servicio</div><div class="val"><?php echo dmy($row['fecha']); ?></div></div>
    </div>
  </div>

  <!-- Origen / Destino -->
  <div class="sec">
    <h3>Origen y Destino</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Atención en</div><div class="val"><?php echo v($row,'atencion_en'); ?></div></div>
      <div class="item"><div class="lbl">Escena</div><div class="val"><?php echo v($row,'escena_paciente'); ?></div></div>
      <div class="item"><div class="lbl">Dirección servicio</div><div class="val"><?php echo v($row,'direccion_servicio'); ?></div></div>
      <div class="item"><div class="lbl">Localización</div><div class="val"><?php echo h(($row['localizacion'] ?: '—').' · '.($row['ciudad'] ?: '—')); ?></div></div>
      <div class="item"><div class="lbl">IPS receptora</div><div class="val"><?php echo h($destino); ?></div></div>
      <div class="item"><div class="lbl">IPS alternas</div><div class="val"><?php echo h(($row['ips2'] ?: '—').' / '.($row['ips3'] ?: '—').' / '.($row['ips4'] ?: '—')); ?></div></div>
      <div class="item"><div class="lbl">Motivo traslado</div><div class="val"><?php echo v($row,'motivo_traslado'); ?></div></div>
      <div class="item"><div class="lbl">Diagnóstico presuntivo</div><div class="val"><?php echo v($row,'diagnostico_principal'); ?></div></div>
    </div>
  </div>

  <!-- Cronología -->
  <div class="sec">
    <h3>Cronología</h3>
    <table class="tbl">
      <thead><tr><th>Despacho</th><th>Llegada a escena</th><th>Salida de escena</th><th>Arribo a destino</th></tr></thead>
      <tbody>
        <tr>
          <td><?php echo hm($row['hora_despacho']); ?></td>
          <td><?php echo hm($row['hora_ingreso']); ?></td>
          <td><?php echo hm($row['hora_llegada']); ?></td>
          <td><?php echo hm($row['hora_final']); ?></td>
        </tr>
      </tbody>
    </table>
    <div class="small-muted mt-1">Creado: <?php echo dmyhm($row['created_at']); ?> · Atención: <?php echo dmyhm($row['fecha_hora_atencion']); ?></div>
  </div>

  <!-- Ambulancia / Tripulación -->
  <div class="sec">
    <h3>Ambulancia y Tripulación</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Placa / Móvil</div><div class="val"><?php echo v($row,'ambulancia'); ?></div></div>
  <div class="item"><div class="lbl">Placa vehículo involucrado</div><div class="val"><?php echo v($row,'placa_vehiculo_involucrado'); ?></div></div>
  <div class="item"><div class="lbl">Conductor</div><div class="val"><?php echo h(($row['conductor'] ?: '—').' · '.($row['cc_conductor'] ?: '')); ?></div></div>
  <div class="item"><div class="lbl">Conductor (accidente)</div><div class="val"><?php echo v($row,'conductor_accidente'); ?></div></div>
  <div class="item"><div class="lbl">Documento conductor (accidente)</div><div class="val"><?php echo v($row,'documento_conductor_accidente'); ?></div></div>
  <div class="item"><div class="lbl">Tarjeta propiedad (acc.)</div><div class="val"><?php echo v($row,'tarjeta_propiedad_accidente'); ?></div></div>
      <div class="item"><div class="lbl">Tripulante (TAPH)</div><div class="val"><?php echo h(($row['tripulante'] ?: '—').' · '.($row['cc_tripulante'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Médico/Enfermería</div><div class="val"><?php echo h(($row['medico_tripulante'] ?: '—').' · '.($row['cc_medico'] ?: '')); ?></div></div>
    </div>
  </div>

  <!-- Valoración clínica -->
  <div class="sec">
    <h3>Valoración clínica</h3>
    <div class="kv">
      <div class="item"><div class="lbl">TA</div><div class="val"><?php echo v($row,'tension_arterial'); ?></div></div>
      <div class="item"><div class="lbl">FC</div><div class="val"><?php echo v($row,'frecuencia_cardiaca'); ?></div></div>
      <div class="item"><div class="lbl">FR</div><div class="val"><?php echo v($row,'frecuencia_respiratoria'); ?></div></div>
      <div class="item"><div class="lbl">SpO₂</div><div class="val"><?php echo v($row,'spo2'); ?></div></div>
      <div class="item"><div class="lbl">Temperatura</div><div class="val"><?php echo v($row,'temperatura'); ?></div></div>
      <div class="item"><div class="lbl">Glasgow</div><div class="val"><?php echo v($row,'escala_glasgow'); ?></div></div>
      <div class="item"><div class="lbl">Glucometría</div><div class="val"><?php echo v($row,'glucometria'); ?></div></div>
      <div class="item"><div class="lbl">Llenado capilar</div><div class="val"><?php echo v($row,'llenado_capilar'); ?></div></div>
      <div class="item"><div class="lbl">Peso / Talla</div><div class="val"><?php echo h(($row['peso'] ?: '—').' kg · '.($row['talla'] ?: '—').' m'); ?></div></div>
      <div class="item"><div class="lbl">Oxígeno</div><div class="val"><?php echo h(($row['oxigeno_dispositivo'] ?: '—').' · Flujo '.($row['oxigeno_flujo'] ?: '—').' · FiO₂ '.($row['oxigeno_fio2'] ?: '—').'%'); ?></div></div>
    </div>
    <div class="kv full" style="margin-top:4px;">
      <div class="item"><div class="lbl">Examen físico</div><div class="val block"><?php echo v($row,'examen_fisico'); ?></div></div>
    </div>
  </div>

  <!-- Intervenciones / Medicación -->
  <div class="sec">
    <h3>Intervenciones y medicación</h3>
    <div class="kv full">
      <div class="item"><div class="lbl">Procedimientos</div><div class="val block"><?php echo v($row,'procedimientos'); ?></div></div>
      <div class="item"><div class="lbl">Medicamentos</div><div class="val block"><?php echo v($row,'medicamentos_aplicados'); ?></div></div>
      <div class="item"><div class="lbl">Consumo del servicio</div><div class="val block"><?php echo v($row,'consumo_servicio'); ?></div></div>
    </div>
  </div>

  <!-- Antecedentes -->
  <div class="sec">
    <h3>Antecedentes</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Generales</div><div class="val block"><?php echo v($row,'antecedentes'); ?></div></div>
      <div class="item"><div class="lbl">Alergias</div><div class="val"><?php echo h(($row['ant_alergicos_sn'] ?: '—').' · '.($row['ant_alergicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Gineco-obst.</div><div class="val"><?php echo h(($row['ant_ginecoobstetricos_sn'] ?: '—').' · '.($row['ant_ginecoobstetricos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Patológicos</div><div class="val"><?php echo h(($row['ant_patologicos_sn'] ?: '—').' · '.($row['ant_patologicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Quirúrgicos</div><div class="val"><?php echo h(($row['ant_quirurgicos_sn'] ?: '—').' · '.($row['ant_quirurgicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Traumatológicos</div><div class="val"><?php echo h(($row['ant_traumatologicos_sn'] ?: '—').' · '.($row['ant_traumatologicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Toxicológicos</div><div class="val"><?php echo h(($row['ant_toxicologicos_sn'] ?: '—').' · '.($row['ant_toxicologicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Familiares</div><div class="val"><?php echo h(($row['ant_familiares_sn'] ?: '—').' · '.($row['ant_familiares_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Downton</div><div class="val"><?php echo v($row,'downton_total'); ?></div></div>
      <div class="item"><div class="lbl">Etnia / Discapacidad</div><div class="val"><?php echo h(($row['etnia'] ?: '—').' · '.($row['discapacidad'] ?: '—').($row['especificar_otra'] ? ' ('.$row['especificar_otra'].')' : '')); ?></div></div>
    </div>
  </div>

  <!-- Firmas (IMÁGENES) -->
  <div class="sec">
    <h3>Firmas</h3>
    <div class="sig-grid">
      <div>
        <div class="sig-box">
          <?php if ($src_firma_paciente): ?>
            <img src="<?php echo $src_firma_paciente; ?>" alt="Firma paciente">
          <?php else: ?>
            <span class="small-muted">Sin firma</span>
          <?php endif; ?>
        </div>
        <div class="sig-cap">Paciente / Acudiente</div>
      </div>
      <div>
        <div class="sig-box">
          <?php if ($src_firma_desist): ?>
            <img src="<?php echo $src_firma_desist; ?>" alt="Firma desistimiento">
          <?php else: ?>
            <span class="small-muted">Sin registro</span>
          <?php endif; ?>
        </div>
        <div class="sig-cap">Desistimiento</div>
      </div>
      <div>
        <div class="sig-box">
          <?php if ($src_firma_paramedico): ?>
            <img src="<?php echo $src_firma_paramedico; ?>" alt="Firma paramédico">
          <?php else: ?>
            <span class="small-muted">Sin firma</span>
          <?php endif; ?>
        </div>
        <div class="sig-cap">Paramédico / TAPH</div>
      </div>
      <div>
        <div class="sig-box">
          <?php if ($src_firma_medico): ?>
            <img src="<?php echo $src_firma_medico; ?>" alt="Firma médico">
          <?php else: ?>
            <span class="small-muted">Sin firma</span>
          <?php endif; ?>
        </div>
        <div class="sig-cap">Médico / Enfermería</div>
      </div>
      <div>
        <div class="sig-box">
          <?php if ($src_firma_receptor): ?>
            <img src="<?php echo $src_firma_receptor; ?>" alt="Firma profesional receptor">
          <?php else: ?>
            <span class="small-muted">Sin firma</span>
          <?php endif; ?>
        </div>
        <div class="sig-cap">Profesional receptor IPS</div>
      </div>
    </div>
  </div>

  <!-- Adjuntos -->
  <div class="sec">
    <h3>Adjuntos</h3>
    <?php if ($adjuntos): ?>
      <div class="att-grid">
        <?php foreach ($adjuntos as $i => $a): 
          $isImg = preg_match('~\.(png|jpe?g|gif|webp)$~i', $a);
          $label = basename($a);
        ?>
          <div class="att">
            <?php if ($isImg): ?>
              <img src="<?php echo h($a); ?>" alt="Adjunto <?php echo $i+1; ?>">
            <?php else: ?>
              <div class="small-muted" style="height:80px;display:flex;align-items:center;justify-content:center;">Archivo</div>
            <?php endif; ?>
            <a href="<?php echo h($a); ?>" target="_blank" rel="noopener"><?php echo h($label); ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="block">—</div>
    <?php endif; ?>
    <div class="small-muted" style="margin-top:4px;">
      Aceptación de términos: <?php echo isset($row['aceptacion']) ? ($row['aceptacion'] ? 'Sí' : 'No') : '—'; ?>
    </div>
  </div>

</div>

<?php
$hasFooter = @include __DIR__ . '/footer.php';
if (!$hasFooter && !$hasHeader){
  echo "</body></html>";
}
