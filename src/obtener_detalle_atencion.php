<?php
// obtener_detalle_atencion.php - Documento compacto con header/footer, firmas y adjuntos (urls absolutas)
// - Usa conn.php ($conn = new mysqli...)
// - SELECT * (trae todos los campos)
// - Estilo "ficha": muy compacto, 2 columnas, sin inputs
// - Pill corta TAB/TAM
// - Firmas: muestra imagen si hay BLOB/base64/ruta
// - Adjuntos: resuelve rutas (http, data, relativas, absolutas bajo docroot o con nombre de proyecto) a URL ABSOLUTA

// Importante: no iniciar la sesión aquí; dejar que bootstrap.php (incluido desde header.php)
// configure y arranque la sesión de forma consistente para toda la app.
require_once __DIR__ . '/conn.php';
date_default_timezone_set('America/Bogota');

/* ====================== Helpers basicos ====================== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function v($row,$key,$dash='-'){ return isset($row[$key]) && $row[$key] !== '' ? h($row[$key]) : $dash; }
function dmyhm($dt){ return $dt ? date('Y-m-d H:i', strtotime($dt)) : '-'; }
function hm($t){ return $t ? date('H:i', strtotime($t)) : '-'; }
function dmy($d){ return $d ? date('Y-m-d', strtotime($d)) : '-'; }

/* ========== Firmas: genera <img src> desde BLOB / base64 / ruta ========== */
function build_img_src($val){
  if (!$val) return null;
  
  // Si ya es data URI, retornarlo tal cual
  if (is_string($val) && stripos($val, 'data:image/') === 0) return $val;
  
  // Si es string, podría ser base64 o ruta
  if (is_string($val)) {
    $val = trim($val);
    // Ruta de imagen?
    if (preg_match('~\.(png|jpe?g|gif|webp)$~i', $val)) {
      return str_replace('\\','/',$val);
    }
    // Base64 "suelta"?
    $clean = preg_replace('~\s+~','', $val);
    if (preg_match('~^[A-Za-z0-9+/=]{100,}$~', $clean)) {
      return "data:image/png;base64,".$clean;
    }
  }
  
  // Si es binary/resource/BLOB, codificarlo como base64
  if (is_string($val) || is_resource($val)) {
    return "data:image/png;base64," . base64_encode($val);
  }
  
  return null;
}

/* ====================== Adjuntos: parseo y normalizacion ====================== */
function parse_attachments($text){
  if (!$text) return [];
  $text = trim($text);
  // JSON array de strings u objetos {url|path|file}
  $j = json_decode($text, true);
  if (is_array($j)) {
    $out = [];
    foreach ($j as $it) {
      if (is_string($it)) { $it = trim($it); if ($it!=='') $out[] = $it; continue; }
      if (is_array($it)) {
        $cand = $it['url'] ?? $it['path'] ?? $it['file'] ?? '';
        $cand = trim((string)$cand);
        if ($cand!=='') $out[] = $cand;
      }
    }
    if ($out) return $out;
  }
  // Texto con separadores
  $parts = preg_split('~[

,;]+~', $text);
  return array_values(array_filter(array_map('trim', $parts)));
}

function encode_path_segments($p){
  $lead = (strlen($p) && $p[0]==='/') ? '/' : '';
  $p = ltrim($p, '/');
  $segs = array_map(function($s){ return rawurlencode($s); }, array_filter(explode('/', $p), 'strlen'));
  return $lead . implode('/', $segs);
}

function base_origin(){
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost'; // incluye puerto si viene en HOST
  return $scheme.'://'.$host;
}

/**
 * Convierte una ruta de adjunto en URL absoluta navegable.
 * Acepta:
 *  - http(s)://...
 *  - data:...
 *  - /HCEI/uploads/..
 *  - uploads/archivo.jpg (relativa)
 *  - C:\xampp\htdocs\HCEI\uploads\archivo.jpg (docroot)
 *  - .../HCEI/uploads/archivo.jpg (contiene nombre de proyecto)
 * Devuelve: [urlAbsoluta, label, isImg, modo]
 */
function resolve_attachment_url($raw){
  if (!$raw) return [null, null, false, null];
  $s = trim((string)$raw);

  // 1) http(s) absoluto
  if (preg_match('~^https?://~i', $s)) {
    $label = basename(parse_url($s, PHP_URL_PATH) ?? '');
    $isImg = (bool)preg_match('~\.(png|jpe?g|gif|webp)$~i', $s);
    return [$s, $label ?: $s, $isImg, 'abs-http'];
  }

  // 2) data: URI
  if (stripos($s,'data:') === 0) {
    $label = 'adjunto';
    $isImg = stripos($s,'data:image/') === 0;
    return [$s, $label, $isImg, 'data'];
  }

  // Normalizar backslashes
  $sNorm = str_replace('\\','/',$s);
  $origin = base_origin();

  // 3) Si es ruta absoluta de filesystem bajo DOCUMENT_ROOT => prefijar origen
  $docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
  if ($docroot) {
    $dr = str_replace('\\','/',$docroot);
    if (substr($dr, -1) !== '/') $dr .= '/';
    // Si la ruta tiene unidad tipo C:, ya esta normalizada arriba
    if (strpos($sNorm, $dr) === 0) {
      $rel = substr($sNorm, strlen($dr));              // ej: HCEI/uploads/archivo.jpg
      $urlPath = '/' . ltrim($rel, '/');
      $urlPath = encode_path_segments($urlPath);
      $url   = $origin . $urlPath;
      $label = basename(parse_url($urlPath, PHP_URL_PATH) ?? '');
      $isImg = (bool)preg_match('~\.(png|jpe?g|gif|webp)$~i', $urlPath);
      return [$url, $label ?: 'archivo', $isImg, 'docroot'];
    }
  }

  // 4) Si empieza con '/' => ya es path web absoluto, prefijar origen
  if (strpos($sNorm, '/') === 0) {
    $urlPath = encode_path_segments($sNorm);
    $url   = $origin . $urlPath;
    $label = basename(parse_url($urlPath, PHP_URL_PATH) ?? '');
    $isImg = (bool)preg_match('~\.(png|jpe?g|gif|webp)$~i', $urlPath);
    return [$url, $label ?: 'archivo', $isImg, 'abs-path'];
  }

  // 5) Ruta absoluta que contiene el nombre del proyecto (XAMPP/WAMP)
  $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/';
  $parts = array_values(array_filter(explode('/', trim($scriptPath,'/'))));
  $project = $parts ? $parts[0] : '';
  if ($project) {
    $needle = '/'.$project.'/';
    $pos = stripos($sNorm, $needle);
    if ($pos !== false) {
      $rel = substr($sNorm, $pos); // '/HCEI/uploads/...'
      $urlPath = encode_path_segments($rel);
      $url   = $origin . $urlPath;
      $label = basename(parse_url($urlPath, PHP_URL_PATH) ?? '');
      $isImg = (bool)preg_match('~\.(png|jpe?g|gif|webp)$~i', $urlPath);
      return [$url, $label ?: 'archivo', $isImg, 'proj-cut'];
    }
  }

  // 6) Ruta relativa respecto al script (/HCEI)
  $baseDir = rtrim(dirname($scriptPath), '/\\');   // ej: /HCEI
  $urlPath = $baseDir . '/' . ltrim($sNorm,'/');
  $urlPath = encode_path_segments($urlPath);
  $url   = $origin . $urlPath;
  $label = basename(parse_url($urlPath, PHP_URL_PATH) ?? '');
  $isImg = (bool)preg_match('~\.(png|jpe?g|gif|webp)$~i', $urlPath);
  return [$url, $label ?: 'archivo', $isImg, 'rel'];
}

/* ====================== Carga de registro ====================== */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); echo "ID invalido."; exit; }

$stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) { http_response_code(404); echo "No se encontro la Atencion solicitada."; exit; }

// Obtener todas las firmas desde atenciones_sig
$firmas = [];
$stmt_sig = $conn->prepare("SELECT tipo_firma, contenido FROM atenciones_sig WHERE atencion_id = ?");
if ($stmt_sig) {
  $stmt_sig->bind_param('i', $id);
  $stmt_sig->execute();
  $res_sig = $stmt_sig->get_result();
  while ($r = $res_sig->fetch_assoc()) {
    $firmas[$r['tipo_firma']] = $r['contenido'];
  }
  $stmt_sig->close();
}

// Asignar firmas a variables específicas para compatibilidad con el código existente
$row['firma_paramedico'] = $firmas['paramedico'] ?? null;
$row['firma_medico'] = $firmas['medico'] ?? null;
$row['firma_paciente'] = $firmas['paciente'] ?? null;
$row['firma_medico_receptor'] = $firmas['medico_receptor'] ?? null;
$row['firma_receptor_ips'] = $firmas['representante_legal'] ?? null; // Antes 'receptor_ips'
$row['firma_desistimiento'] = $firmas['desistimiento'] ?? null;

/* ====================== Derivados UI ====================== */
$nivel = !empty($row['medico_tripulante']) ? 'TAM' : 'TAB';
$nivelClass = $nivel === 'TAM' ? 'pill-danger' : 'pill-success';

// Destino preferente
$destino = '-';
if (!empty($row['nombre_ips_receptora'])) {
  $destino = $row['nombre_ips_receptora'];
  if (!empty($row['nit_ips_receptora'])) $destino .= ' (NIT '. $row['nit_ips_receptora'] .')';
} elseif (!empty($row['ips_destino'])) {
  $destino = $row['ips_destino'];
}

// Firmas (receptor es BLOB)
$src_firma_paciente   = build_img_src($row['firma_paciente'] ?? null);
$src_firma_desist     = build_img_src($row['firma_desistimiento'] ?? null);
$src_firma_paramedico = build_img_src($row['firma_paramedico'] ?? null);
$src_firma_medico     = build_img_src($row['firma_medico'] ?? null);
$src_firma_receptor   = build_img_src($row['firma_medico_receptor'] ?? null, true);
// Firma receptor IPS (nuevo campo en atenciones_sig)
$src_firma_receptor_ips = build_img_src($firma_receptor_ips ?? null);

// Cargar adjuntos desde atenciones_att
$adjuntos = [];
$stmt_att = $conn->prepare("SELECT id, tipo_adjunto, contenido, nombre_archivo FROM atenciones_att WHERE atencion_id = ?");
if ($stmt_att) {
  $stmt_att->bind_param('i', $id);
  $stmt_att->execute();
  $res_att = $stmt_att->get_result();
  while ($att = $res_att->fetch_assoc()) {
    $isImg = strpos($att['tipo_adjunto'], 'image/') === 0;
    $url = "data:" . ($att['tipo_adjunto'] ?: 'application/octet-stream') . ";base64," . base64_encode($att['contenido']);
    $label = $att['nombre_archivo'] ?: 'adjunto-' . $att['id'];
    $adjuntos[] = [
      'url' => $url,
      'label' => $label,
      'isImg' => $isImg,
      'mode' => 'data',
      'raw' => $url
    ];
  }
  $stmt_att->close();
}

// Historial administrativo (cambios realizados desde la pantalla de edicion admin)
$histAdmin = [];
$checkHist = $conn->query("SHOW TABLES LIKE 'atenciones_historial'");
if ($checkHist && $checkHist->num_rows > 0) {
  $checkHist->close();
  $stmt_hist = $conn->prepare("SELECT campo, valor_anterior, valor_nuevo, usuario_id, motivo FROM atenciones_historial WHERE atencion_id = ?");
  if ($stmt_hist) {
    $stmt_hist->bind_param('i', $id);
    $stmt_hist->execute();
    $res_hist = $stmt_hist->get_result();
    while ($hrow = $res_hist->fetch_assoc()) {
      $histAdmin[] = $hrow;
    }
    $stmt_hist->close();
  }
} elseif ($checkHist) {
  $checkHist->close();
}

/* ====================== Header / Fallback ====================== */
$hasHeader = @include __DIR__ . '/header.php';
if (!$hasHeader){
  ?>
  <!doctype html>
  <html lang="es">
  <head>`n    <meta charset="utf-8">
    <meta charset="utf-8">
    <title>Detalle Atencion #<?php echo h($id); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </div>
  </div>

  </div>

  
</div>
  <?php
}
?>
<style>
  :root{
    --doc-font-size: 1rem;
    --doc-line-height: 1.5;
    --doc-pad: 0.75rem;
    --doc-gap: 0.5rem;
    --text-primary: #111111;
    --text-secondary: #2c3a4d;
    --text-muted: #6c757d;
    --border: #d7dbe0;
    --surface-soft: #fcfdff;
    --table-header: #f2f4f7;
    --pill-tab-glow: rgba(25, 135, 84, 0.28);
    --pill-tam-glow: rgba(220, 53, 69, 0.32);
    --body-bg: #f6f8fb;
    --card-bg: #ffffff;
  }
  [data-bs-theme="dark"] :root,
  [data-bs-theme="dark"] {
      --body-bg: #1c1e22;
      --card-bg: #282c34;
      --text-primary: #ced4da;
      --text-secondary: #a9d1ff;
      --text-muted: #adb5bd;
      --border: #3e444c;
      --surface-soft: #212529;
      --table-header: #343a42;
      --pill-tab-glow: rgba(46, 213, 115, 0.4);
      --pill-tam-glow: rgba(255, 99, 132, 0.4);
  }
  body{
    background: var(--body-bg);
    color: var(--text-primary);
  }
  .doc-wrap{
    max-width: 1120px;
    margin: 12px auto;
    background: var(--card-bg);
    border:1px solid var(--border);
    box-shadow: 0 6px 18px rgba(0,0,0,.15);
    border-radius: 10px;
    display:flex;
    flex-direction: column;
  }

  
  .actions{
    display:flex;
    gap:0.75rem;
    justify-content:flex-end;
    padding:0.75rem var(--doc-pad);
    border-bottom:1px solid var(--border);
    background: transparent;
  }
  .doc-header{

    display:flex;

    justify-content:space-between;

    
    align-items:center;

    
    gap:1rem;

    
    padding:1rem var(--doc-pad);

    
    border-bottom: 2px solid var(--border);

    
    background: var(--bs-tertiary-bg);
  }
  .title { margin:0; font-weight:700; font-size:1.125rem; line-height:1.2; color: var(--text-primary); }
  .subtitle { margin:4px 0 0; color:var(--text-muted); font-size:0.95rem; }

  .pill{
    display:inline-flex;
    align-items:center;
    padding:2px 12px;
    border-radius:999px;
    font-weight:700;
    font-size:0.8rem;
    letter-spacing:.3px;
    text-transform:uppercase;
    color:#fff;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05);
  }
  .pill-success{ background: linear-gradient(135deg, #3acf7c 0%, #198754 100%); box-shadow: 0 0 12px var(--pill-tab-glow); }
  .pill-danger{  background: linear-gradient(135deg, #ff4d6d 0%, #c9184a 100%); box-shadow: 0 0 12px var(--pill-tam-glow); }

  .sec{ padding:12px var(--doc-pad); border-top:1px solid var(--border); }
  .sec:first-of-type{ border-top:0; }
  .sec h3{ margin:0 0 8px; font-size:0.85rem; letter-spacing:.4px; text-transform:uppercase; color:var(--text-secondary); }

  .kv{ display:grid; grid-template-columns: 1fr 1fr; column-gap: 18px; row-gap: var(--doc-gap); }
  @media (max-width: 575.98px){ .kv{ grid-template-columns: 1fr; } }
  .kv.full{ grid-template-columns: 1fr; }
  .item{ display:flex; gap:8px; align-items:baseline; }
  .lbl{ min-width: 140px; color:var(--text-muted); font-weight:500; font-size:0.85rem; }
  .val{ flex:1; border-bottom:1px dotted rgba(99, 110, 133, 0.25); padding-bottom:1px; color:var(--text-primary); font-weight:500; word-break:break-word; overflow-wrap:anywhere; }

  .tbl{ width:100%; border-collapse:collapse; }
  .tbl th, .tbl td{ border:1px solid var(--border); padding:6px 8px; font-size:0.95rem; color:var(--text-primary); }
  .tbl th{ background:var(--table-header); font-weight:700; color:var(--text-secondary); }
  .block{ padding:6px 8px; border:1px dashed var(--border); background:var(--surface-soft); min-height: 28px; font-size: 0.95rem; }

  .sig-grid{ display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; }
  @media (min-width: 992px){ .sig-grid{ grid-template-columns: repeat(4, 1fr); } }
  .sig-box{ border:1px dashed var(--border); padding:4px; background: var(--surface-soft); min-height:110px; display:flex; align-items:center; justify-content:center; }
  .sig-box img{ max-width:100%; max-height:90px; object-fit:contain; }
  .sig-cap{ text-align:center; font-size:0.85rem; margin-top:4px; color:var(--text-secondary); }

  .att-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:8px; }
  @media (min-width: 992px){ .att-grid{ grid-template-columns: repeat(6, 1fr); } }
  .att{ border:1px solid var(--border); padding:3px; background: var(--surface-soft); text-align:center; }
  .att img{ width:100%; height:80px; object-fit:cover; }
  .att a{ font-size:0.9rem; display:block; margin-top:4px; word-break:break-word; overflow-wrap:anywhere; color:var(--text-primary); }

  .small-muted{ color:var(--text-muted); font-size: 0.85rem; }

  @media print{
    body{ background:#fff; color:#000; }
    .actions{ display:none !important; }
    .doc-wrap{ margin:0; border:0; box-shadow:none; }
    @page{ size:letter portrait; margin:10mm; }
  }
  .block{ word-break:break-word; overflow-wrap:anywhere; }
  .tbl th, .tbl td{ word-break:break-word; overflow-wrap:anywhere; }
  .doc-detail-container{
    background: transparent;
  }

  @media (max-width: 575.98px){
    .doc-wrap{
      margin: 8px auto;
      border-radius: 0;
      box-shadow: 0 3px 10px rgba(0,0,0,.12);
    }
    .item{
      align-items:flex-start;
      flex-direction:column;
    }
    .lbl{
      min-width: 0;
      font-size:0.8rem;
    }
    .val{
      border-bottom:1px dotted rgba(99, 110, 133, 0.2);
      padding-bottom:2px;
    }
  }

  
</style>

<?php if (!$hasHeader) { echo "</head><body>"; } ?>

<div class="container my-4 doc-detail-container">

  <div class="doc-wrap">
  
  <!-- Barra de acciones (no se imprime) -->
  <div class="actions">
    <a class="btn btn-sm btn-outline-secondary" href="javascript:history.back()">Volver</a>
    <?php
      $rol = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
      $rol = is_string($rol) ? strtolower(trim($rol)) : '';
      $simularTrip = in_array($rol, ['dev','master'], true)
        && isset($_GET['simular_trip'])
        && $_GET['simular_trip'] === '1';
      $rolesPdf = ['administrativo','master','secretaria','dev'];
      if (in_array($rol, $rolesPdf, true)):
    ?>
    <a class="btn btn-sm btn-outline-primary" href="generar_pdf.php?id=<?php echo urlencode($row['id']); ?>" target="_blank" rel="noopener">PDF</a>
    <?php endif; ?>

    <?php
      $estado_registro = $row['estado_registro'] ?? 'ACTIVA';
      $puedeSolicitar = ($rol === 'tripulacion') || $simularTrip;
      if ($puedeSolicitar && $estado_registro === 'ACTIVA'):
    ?>
      <a class="btn btn-sm btn-outline-danger" href="solicitar_anulacion.php?id=<?php echo urlencode($row['id']); ?>">Solicitar anulación</a>
      <?php if ($simularTrip): ?>
        <span class="badge bg-warning text-dark ms-2">Simulación rol Tripulación</span>
      <?php endif; ?>
    <?php endif; ?>

    <button class="btn btn-sm btn-success" onclick="window.print()">Imprimir</button>
    <?php
      // Mostrar boton Furtran solo para roles permitidos
      $permitidos = ['secretaria','master','administrativo','dev'];
      if (in_array($rol, $permitidos, true)):
    ?>
      <a class="btn btn-sm btn-outline-info" href="obtener_detalle_furtran.php?id=<?php echo urlencode($row['id']); ?>" target="_blank" rel="noopener">Furtran</a>
    <?php endif; ?>

  </div>

  <?php
    $estado_registro = $row['estado_registro'] ?? 'ACTIVA';
    $motivo_sol = $row['motivo_solicitud_anulacion'] ?? '';
    $motivo_resp = $row['motivo_respuesta_anulacion'] ?? '';
  ?>
  <?php if ($estado_registro === 'ANULADA'): ?>
    <div class="alert alert-danger m-3 mb-0">
      <strong>REGISTRO ANULADO.</strong>
      <?php if ($motivo_resp): ?> Motivo de anulación: <?php echo nl2br(h($motivo_resp)); ?><?php endif; ?>
    </div>
  <?php elseif ($estado_registro === 'ANULACION_SOLICITADA'): ?>
    <div class="alert alert-warning m-3 mb-0">
      <strong>Anulación solicitada.</strong>
      <?php if ($motivo_sol): ?> Motivo de la solicitud: <?php echo nl2br(h($motivo_sol)); ?><?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Encabezado -->
  <div class="doc-header">
    <div>
      <h1 class="title">Registro de Atencion Prehospitalaria</h1>
      <p class="subtitle">ID <?php echo h($row['id']); ?> |  Reg. <?php echo h($row['registro']); ?> |  <?php echo dmyhm($row['fecha_hora_atencion']); ?></p>
    </div>
    <div class="<?php echo 'pill '.$nivelClass; ?>"><?php echo h($nivel); ?></div>
  </div>

  <!-- 1. Evento / Servicio -->
  <div class="sec">
    <h3>Evento / Servicio</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Servicio</div><div class="val"><?php echo v($row,'servicio'); ?></div></div>
      <div class="item"><div class="lbl">Tipo de traslado</div><div class="val"><?php echo h(($row['tipo_traslado'] ?: '-').($row['traslado_tipo'] ? ' / '.$row['traslado_tipo'] : '')); ?></div></div>
      <div class="item"><div class="lbl">Pagador</div><div class="val"><?php echo v($row,'pagador'); ?></div></div>
      <div class="item"><div class="lbl">Quien informo</div><div class="val"><?php echo v($row,'quien_informo'); ?></div></div>
      <div class="item"><div class="lbl">Aseguradora SOAT</div><div class="val"><?php echo v($row,'aseguradora_soat'); ?></div></div>
      <div class="item"><div class="lbl">Fecha de servicio</div><div class="val"><?php echo dmy($row['fecha']); ?></div></div>
    </div>
  </div>

  <!-- 2. Transportador y Tripulación -->
  <div class="sec">
    <h3>Transportador y Tripulación</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Empresa</div><div class="val"><?php echo v($row,'nombre_empresa_transportador'); ?> (Hab: <?php echo v($row, 'codigo_habilitacion_empresa'); ?>)</div></div>
      <div class="item"><div class="lbl">Ubicación Empresa</div><div class="val"><?php
        $mun_empresa = $row['municipio_empresa'] ?? '-';
        $cod_mun_empresa = $row['codigo_municipio_empresa'] ?? 'N/A';
        echo h($mun_empresa . ' (' . $cod_mun_empresa . ')');
      ?></div></div>
      <div class="item"><div class="lbl">Placa / Movil</div><div class="val"><?php echo v($row,'ambulancia'); ?></div></div>
      <div class="item"><div class="lbl">Conductor</div><div class="val"><?php echo h(($row['conductor'] ?: '-').' |  '.($row['cc_conductor'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Tripulante (TAPH)</div><div class="val"><?php echo h(($row['tripulante'] ?: '-').' |  '.($row['cc_tripulante'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Medico/Enfermeria</div><div class="val"><?php echo h(($row['medico_tripulante'] ?: '-').' |  '.($row['cc_medico'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Dirección Transportador</div><div class="val"><?php echo v($row, 'direccion_transportador'); ?></div></div>
      <div class="item"><div class="lbl">Teléfono Transportador</div><div class="val"><?php echo v($row, 'telefono_transportador'); ?></div></div>
    </div>
  </div>

  <!-- 3. Cronología -->
  <div class="sec">
    <h3>Cronologia</h3>
    <table class="tbl">
      <thead><tr><th>Despacho</th><th>Llegada a escena</th><th>Ingreso IPS</th><th>Hora final</th></tr></thead>
      <tbody><tr>
        <td><?php echo hm($row['hora_despacho']); ?></td>
        <td><?php echo hm($row['hora_ingreso']); ?></td>
        <td><?php echo hm($row['hora_llegada']); ?></td>
        <td><?php echo hm($row['hora_final']); ?></td>
      </tr></tbody>
    </table>
    <div class="small-muted mt-1">Creado: <?php echo dmyhm($row['created_at']); ?> |  Atencion: <?php echo dmyhm($row['fecha_hora_atencion']); ?></div>
  </div>

  <!-- 4. Origen y Destino -->
  <div class="sec">
    <h3>Origen y Destino</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Atencion en</div><div class="val"><?php echo v($row,'atencion_en'); ?></div></div>
      <div class="item"><div class="lbl">Escena</div><div class="val"><?php echo v($row,'escena_paciente'); ?></div></div>
      <div class="item"><div class="lbl">Direccion servicio</div><div class="val"><?php echo v($row,'direccion_servicio'); ?></div></div>
      <div class="item"><div class="lbl">Localizacion</div><div class="val"><?php
        $loc_evento = $row['localizacion'] ?: '-';
        $municipio_evento = $row['municipio'] ?: ($row['ciudad'] ?? '-');
        echo h($loc_evento . ' |  ' . $municipio_evento);
      ?> (<?php echo v($row, 'cod_ciudad_recogida', 'N/A'); ?>)</div></div>
      <div class="item"><div class="lbl">IPS receptora</div><div class="val"><?php echo h($destino); ?></div></div>
      <div class="item"><div class="lbl">Ubicación IPS</div><div class="val"><?php
        $ciudad_ips = $row['ciudad_ips'] ?? 'N/A';
        $cod_ips = $row['cod_ciudad_ips'] ?? 'N/A';
        echo h($ciudad_ips . ' (' . $cod_ips . ')');
      ?></div></div>
      <div class="item"><div class="lbl">IPS alternas</div><div class="val"><?php echo h(($row['ips2'] ?: '-').' / '.($row['ips3'] ?: '-').' / '.($row['ips4'] ?: '-')); ?></div></div>
      <div class="item"><div class="lbl">Motivo traslado</div><div class="val block"><?php echo v($row,'motivo_traslado'); ?></div></div>
      <div class="item"><div class="lbl">Diagnostico presuntivo</div><div class="val block"><?php echo v($row,'diagnostico_principal'); ?></div></div>
    </div>
  </div>

  <!-- 5. Paciente -->
  <div class="sec">
    <h3>Paciente</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Nombre</div><div class="val"><?php 
$pacienteNombre = v($row,'nombres_paciente');
$pacienteNombre = trim(implode(' ', array_filter([
  $row['primer_nombre_paciente'] ?? '',
  $row['segundo_nombre_paciente'] ?? '',
  $row['primer_apellido_paciente'] ?? '',
  $row['segundo_apellido_paciente'] ?? ''
]))); 
if ($pacienteNombre === '') {
  $pacienteNombre = v($row,'nombres_paciente');
}
echo $pacienteNombre; ?></div></div>
      <div class="item"><div class="lbl">Documento</div><div class="val"><?php echo h(($row['tipo_identificacion'] ?: 'ID').' |  '.($row['id_paciente'] ?: '-')); ?></div></div>
      <div class="item"><div class="lbl">Genero</div><div class="val"><?php echo v($row,'genero_nacer'); ?></div></div>
      <div class="item"><div class="lbl">Fecha Nac.</div><div class="val"><?php echo dmy($row['fecha_nacimiento']); ?></div></div>
      <div class="item"><div class="lbl">RH</div><div class="val"><?php echo v($row,'rh'); ?></div></div>
      <div class="item"><div class="lbl">EPS</div><div class="val"><?php echo v($row,'eps_nombre'); ?></div></div>
      <div class="item"><div class="lbl">Telefono</div><div class="val"><?php echo v($row,'telefono_paciente'); ?></div></div>
      <div class="item"><div class="lbl">Barrio / Municipio</div><div class="val"><?php
        $loc_paciente = $row['barrio_paciente'] ?: '-';
        $municipio_paciente = $row['municipio'] ?: ($row['ciudad'] ?? '-');
        echo h($loc_paciente . ' |  ' . $municipio_paciente);
      ?></div></div>
      <div class="item"><div class="lbl">Direccion domicilio</div><div class="val"><?php echo v($row,'direccion_domicilio'); ?></div></div>
      <?php if (!empty($row['causa_externa_codigo']) || !empty($row['causa_externa'])): ?>
      <div class="item"><div class="lbl">Causa Externa</div><div class="val"><?php 
        if (!empty($row['causa_externa_codigo'])) {
          echo '<span class="badge bg-info me-1">'.h($row['causa_externa_codigo']).'</span>';
          if (!empty($row['causa_externa_categoria'])) {
            echo '<span class="badge bg-secondary me-1">'.h(ucfirst(str_replace('_', ' ', $row['causa_externa_categoria']))).'</span>';
          }
        }
        if (!empty($row['causa_externa_detalle'])) {
          echo h($row['causa_externa_detalle']);
        } elseif (!empty($row['causa_externa'])) {
          echo '<span class="text-muted">'.h($row['causa_externa']).'</span>';
        }
      ?></div></div>
      <?php endif; ?>
      <div class="item"><div class="lbl">Acompanante</div><div class="val"><?php echo h(($row['nombre_acompanante'] ?: '-').' |  '.($row['parentesco_acompanante'] ?: '-').' |  '.($row['id_acompanante'] ?: '-')); ?></div></div>
    </div>
  </div>

  <!-- 6. Valoracion clinica -->
  <div class="sec">
    <h3>Valoracion clinica</h3>
    <div class="kv">
      <div class="item"><div class="lbl">TA</div><div class="val"><?php echo v($row,'tension_arterial'); ?></div></div>
      <div class="item"><div class="lbl">FC</div><div class="val"><?php echo v($row,'frecuencia_cardiaca'); ?></div></div>
      <div class="item"><div class="lbl">FR</div><div class="val"><?php echo v($row,'frecuencia_respiratoria'); ?></div></div>
      <div class="item"><div class="lbl">SpO2 (%)</div><div class="val"><?php echo v($row,'spo2'); ?></div></div>
      <div class="item"><div class="lbl">Temperatura</div><div class="val"><?php echo v($row,'temperatura'); ?></div></div>
      <div class="item"><div class="lbl">Glasgow</div><div class="val"><?php echo v($row,'escala_glasgow'); ?></div></div>
      <div class="item"><div class="lbl">Glucometria</div><div class="val"><?php echo v($row,'glucometria'); ?></div></div>
      <div class="item"><div class="lbl">Llenado capilar</div><div class="val"><?php echo v($row,'llenado_capilar'); ?></div></div>
      <div class="item"><div class="lbl">Peso / Talla</div><div class="val"><?php echo h(($row['peso'] ?: '-').' kg |  '.($row['talla'] ?: '-').' m'); ?></div></div>
      <div class="item"><div class="lbl">Oxigeno</div><div class="val"><?php echo h(($row['oxigeno_dispositivo'] ?: '-').' |  Flujo '.($row['oxigeno_flujo'] ?: '-').' |  FiO2 '.($row['oxigeno_fio2'] ?: '-').'%'); ?></div></div>
    </div>
    <div class="kv full" style="margin-top:4px;">
      <div class="item"><div class="lbl">Examen fisico</div><div class="val block"><?php echo v($row,'examen_fisico'); ?></div></div>
    </div>
  </div>

  <!-- 7. Antecedentes -->
  <div class="sec">
    <h3>Antecedentes</h3>
    <div class="kv">
      <div class="item"><div class="lbl">Generales</div><div class="val block"><?php echo v($row,'antecedentes'); ?></div></div>
      <div class="item"><div class="lbl">Alergias</div><div class="val"><?php echo h(($row['ant_alergicos_sn'] ?: '-').' |  '.($row['ant_alergicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Gineco-obst.</div><div class="val"><?php echo h(($row['ant_ginecoobstetricos_sn'] ?: '-').' |  '.($row['ant_ginecoobstetricos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Patologicos</div><div class="val"><?php echo h(($row['ant_patologicos_sn'] ?: '-').' |  '.($row['ant_patologicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Quirurgicos</div><div class="val"><?php echo h(($row['ant_quirurgicos_sn'] ?: '-').' |  '.($row['ant_quirurgicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Traumatologicos</div><div class="val"><?php echo h(($row['ant_traumatologicos_sn'] ?: '-').' |  '.($row['ant_traumatologicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Toxicologicos</div><div class="val"><?php echo h(($row['ant_toxicologicos_sn'] ?: '-').' |  '.($row['ant_toxicologicos_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Familiares</div><div class="val"><?php echo h(($row['ant_familiares_sn'] ?: '-').' |  '.($row['ant_familiares_cual'] ?: '')); ?></div></div>
      <div class="item"><div class="lbl">Downton</div><div class="val"><?php echo v($row,'downton_total'); ?></div></div>
      <div class="item"><div class="lbl">Etnia / Discapacidad</div><div class="val"><?php echo h(($row['etnia'] ?: '-').' |  '.($row['discapacidad'] ?: '-').($row['especificar_otra'] ? ' ('.$row['especificar_otra'].')' : '')); ?></div></div>
    </div>
  </div>

  <!-- 8. Intervenciones / Medicacion -->
  <div class="sec">
    <h3>Intervenciones y medicacion</h3>
    <div class="kv full">
      <div class="item"><div class="lbl">Procedimientos</div><div class="val block"><?php echo v($row,'procedimientos'); ?></div></div>
      <div class="item"><div class="lbl">Medicamentos</div><div class="val block"><?php echo v($row,'medicamentos_aplicados'); ?></div></div>
      <div class="item"><div class="lbl">Consumo del servicio</div><div class="val block"><?php echo v($row,'consumo_servicio'); ?></div></div>
    </div>
  </div>

  <!-- Informacion Legal (FURTRAN) - Solo si existe -->
  <?php if (!empty($row['no_radicado_furtran'])): ?>
  <div class="sec">
    <h3>Información Legal (FURTRAN)</h3>
    <div class="kv">
      <div class="item"><div class="lbl">No. Radicado FURTRAN</div><div class="val"><?php echo v($row, 'no_radicado_furtran'); ?></div></div>
      <div class="item"><div class="lbl">Radicado Anterior</div><div class="val"><?php echo v($row, 'no_radicado_anterior'); ?></div></div>
      <div class="item"><div class="lbl">Placa Vehículo Involucrado</div><div class="val"><?php echo v($row, 'placa_vehiculo_involucrado'); ?></div></div>
      <div class="item"><div class="lbl">Rep. Legal Empresa</div><div class="val"><?php echo v($row, 'nombre_representante_legal_empresa'); ?></div></div>
      <div class="item"><div class="lbl">ID Rep. Legal</div><div class="val"><?php
        $tipo_doc = $row['tipo_documento_representante_legal_empresa'] ?? '';
        $id_doc = $row['id_representante_legal_empresa'] ?? '-';
        echo h($tipo_doc . ' | ' . $id_doc);
      ?></div></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($histAdmin)): ?>
  <div class="sec">
    <h3>Historial administrativo</h3>
    <table class="tbl">
      <thead>
        <tr>
          <th>Campo</th>
          <th>Valor anterior</th>
          <th>Valor nuevo</th>
          <th>Usuario ID</th>
          <th>Motivo</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($histAdmin as $hrow): ?>
          <tr>
            <td><?php echo h($hrow['campo']); ?></td>
            <td><?php echo h($hrow['valor_anterior']); ?></td>
            <td><?php echo h($hrow['valor_nuevo']); ?></td>
            <td><?php echo h($hrow['usuario_id']); ?></td>
            <td><?php echo h($hrow['motivo']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="small-muted mt-1">Solo se muestran cambios realizados desde la pantalla de edición administrativa.</p>
  </div>
  <?php endif; ?>

  <!-- Firmas (imagenes) -->
  <div class="sec">
    <h3>Firmas</h3>
    <div class="sig-grid">
      <div>
        <div class="sig-box"><?php echo $src_firma_paciente ? '<img src="'.h($src_firma_paciente).'" alt="Firma paciente">' : '<span class="small-muted">Sin firma</span>'; ?></div>
        <div class="sig-cap">Paciente / Acudiente</div>
      </div>
      <div>
        <div class="sig-box"><?php echo $src_firma_desist ? '<img src="'.h($src_firma_desist).'" alt="Firma desistimiento">' : '<span class="small-muted">Sin registro</span>'; ?></div>
        <div class="sig-cap">Desistimiento</div>
      </div>
      <div>
        <div class="sig-box"><?php echo $src_firma_paramedico ? '<img src="'.h($src_firma_paramedico).'" alt="Firma paraMedico">' : '<span class="small-muted">Sin firma</span>'; ?></div>
        <div class="sig-cap">ParaMedico / TAPH</div>
      </div>
      <div>
        <div class="sig-box"><?php echo $src_firma_medico ? '<img src="'.h($src_firma_medico).'" alt="Firma Medico">' : '<span class="small-muted">Sin firma</span>'; ?></div>
        <div class="sig-cap">Medico / Enfermeria</div>
      </div>
      <div>
        <div class="sig-box"><?php echo $src_firma_receptor ? '<img src="'.h($src_firma_receptor).'" alt="Firma receptor">' : '<span class="small-muted">Sin firma</span>'; ?></div>
        <div class="sig-cap">Profesional receptor IPS</div>
      </div>
      <div>
        <div class="sig-box"><?php echo $src_firma_receptor_ips ? '<img src="'.h($src_firma_receptor_ips).'" alt="Firma receptor IPS">' : '<span class="small-muted">Sin firma IPS</span>'; ?></div>
        <div class="sig-cap">Firma Receptor IPS</div>
      </div>
    </div>
  </div>

  <!-- Adjuntos -->
  <div class="sec">
    <h3>Adjuntos</h3>
    <?php if ($adjuntos): ?>
      <div class="att-grid">
        <?php foreach ($adjuntos as $i => $att):
          $url = $att['url']; $label = $att['label']; $isImg = $att['isImg']; $raw = $att['raw'];
        ?>
          <div class="att">
            <?php if ($isImg && $url): ?>
              <img src="<?php echo h($url); ?>" alt="Adjunto <?php echo $i+1; ?>">
            <?php else: ?>
              <div class="small-muted" style="height:80px;display:flex;align-items:center;justify-content:center;">
                <?php echo $url ? 'Archivo' : 'Sin URL'; ?>
              </div>
            <?php endif; ?>
            <?php if ($url): ?>
              <a href="<?php echo h($url); ?>" target="_blank" rel="noopener"><?php echo h($label); ?></a>
            <?php else: ?>
              <span class="small-muted"><?php echo h($raw); ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="small-muted">Sin adjuntos registrados.</p>
    <?php endif; ?>
    </div>
  </div>

  </div>

</div>

<?php
$hasFooter = @include __DIR__ . '/footer.php';
if (!$hasFooter && !$hasHeader){ echo "</body></html>"; }
