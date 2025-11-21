<?php
require_once __DIR__ . '/bootstrap.php';

// Helper para diferencias en minutos y clases de semaforo
function minutesDiffNullable($a, $b){
  if (!$a || !$b) return null;
  $ta = strtotime($a); $tb = strtotime($b);
  if (!$ta || !$tb) return null;
  return (int) round(($tb - $ta) / 60);
}
function pillClassByMinutes($m, $g1, $g2){
  if ($m === null) return null;
  if ($m < $g1) return 'ok';
  if ($m <= $g2) return 'warn';
  return 'danger';
}

// consulta_atenciones.php - Golden refinado (cards), busqueda y paginacion
// - Usa conn.php ($conn = new mysqli...)
// - Botones: Ver -> obtener_detalle_atencion.php, PDF -> generar_pdf.php
// - Pills TAB/TAM con color distinto, lineas compactas, sin "Amb " en placa

// Importante: no iniciar la sesión aquí; dejar que bootstrap.php (incluido desde header.php)
// configure y arranque la sesión de forma consistente para toda la app.
require_once __DIR__ . '/conn.php';
date_default_timezone_set('America/Bogota');

// ===== Header / Fallback =====
$hasHeader = @include __DIR__ . '/header.php';
if (!$hasHeader) {
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Consulta de Atenciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap (solo si no hay header propio) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      /* Compactar lineas y espacios */
      .lh-tight   { line-height: 1.15; }
      .mb-compact { margin-bottom: .25rem !important; }
      .divider    { height:1px; background:#e9ecef; margin:.25rem 0 .5rem; }
      .card-compact .card-body { padding: .9rem .9rem; }
      .card-compact .card-footer { padding: .5rem .9rem; }
      .chipline .badge { font-weight: 500; }
      .muted { color:#6c757d; }
      .card-hover:hover{ box-shadow:0 .6rem 1.2rem rgba(0,0,0,.08); transform: translateY(-1px); transition:.18s; }
    </style>
  </head>
  <body class="bg-light">
  <?php
} else {
  // Si tu header ya incluye CSS, solo anadimos las utilidades si quieres:
  echo '<style>
      .lh-tight{line-height:1.15}.mb-compact{margin-bottom:.25rem!important}.divider{height:1px;background:#e9ecef;margin:.25rem 0 .5rem}
      .card-compact .card-body{padding:.9rem .9rem}.card-compact .card-footer{padding:.5rem .9rem}
      .chipline .badge{font-weight:500}.muted{color:#6c757d}.card-hover:hover{box-shadow:0 .6rem 1.2rem rgba(0,0,0,.08);transform:translateY(-1px);transition:.18s}
    </style>';
}

// ===== Parametros de busqueda y paginacion =====
$query = isset($_GET['query']) ? trim($_GET['query']) : "";
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// ===== WHERE dinamico (solo columnas existentes) =====
$where  = " WHERE 1=1 ";
$params = [];
$types  = "";

if ($query !== "") {
  $where .= " AND (
      id                     LIKE CONCAT('%', ?, '%')
   OR nombres_paciente       LIKE CONCAT('%', ?, '%')
   OR id_paciente            LIKE CONCAT('%', ?, '%')
   OR tripulante             LIKE CONCAT('%', ?, '%')
   OR ambulancia             LIKE CONCAT('%', ?, '%')
   OR ips_destino            LIKE CONCAT('%', ?, '%')
   OR nombre_ips_receptora   LIKE CONCAT('%', ?, '%')
   OR municipio              LIKE CONCAT('%', ?, '%')
   OR municipio_empresa      LIKE CONCAT('%', ?, '%')
   OR cod_ciudad_recogida    LIKE CONCAT('%', ?, '%')
   OR cod_ciudad_ips         LIKE CONCAT('%', ?, '%')
  )";
  for ($i = 0; $i < 11; $i++) { $params[] = $query; $types .= "s"; }
}

// ===== Total para paginacion =====
$sqlCount = "SELECT COUNT(*) AS total FROM atenciones {$where}";
$stmt = $conn->prepare($sqlCount);
if (!$stmt) { die("Error preparando COUNT: " . htmlspecialchars($conn->error)); }
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

// ===== Data principal (solo columnas existentes) =====
$sqlData = "
  SELECT
    id, fecha_hora_atencion, fecha,
    hora_despacho, hora_ingreso, hora_llegada, hora_final,
    nombres_paciente, tipo_identificacion, id_paciente,
    genero_nacer, fecha_nacimiento, rh, eps_nombre,
    servicio, tipo_traslado, traslado_tipo, pagador, quien_informo,
    atencion_en, direccion_servicio, localizacion,
    ips_destino, nombre_ips_receptora, nit_ips_receptora,
    ambulancia, conductor, tripulante, medico_tripulante, municipio_empresa, cod_ciudad_recogida, cod_ciudad_ips,
    municipio,
    estado_registro,
    tension_arterial, frecuencia_cardiaca, frecuencia_respiratoria, spo2, temperatura,
    procedimientos, medicamentos_aplicados
  FROM atenciones
  {$where}
  ORDER BY fecha_hora_atencion DESC, id DESC
  LIMIT ? OFFSET ?
";

$paramsData = $params;
$typesData  = $types . "ii";
$paramsData[] = $perPage;
$paramsData[] = $offset;

$stmt = $conn->prepare($sqlData);
if (!$stmt) { die("Error preparando datos: " . htmlspecialchars($conn->error)); }
$stmt->bind_param($typesData, ...$paramsData);
$stmt->execute();
$res = $stmt->get_result();

// ===== Helpers de UI =====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hhmm($t){ return $t ? date('H:i', strtotime($t)) : null; }
function timePill($label, $t, $minutesDiff = null, $g1 = 15, $g2 = 30) {
  $v = hhmm($t);
  if(!$v) return '<span class="badge bg-light text-dark me-1">'.$label.': -</span>';
  $class = 'bg-secondary';
  $title = '';
  if ($minutesDiff !== null) {
    $state = pillClassByMinutes($minutesDiff, $g1, $g2);
    $map = [
      'ok' => 'bg-success',
      'warn' => 'bg-warning text-dark',
      'danger' => 'bg-danger'
    ];
    if ($state && isset($map[$state])) {
      $class = $map[$state];
    }
    $title = ' title="'.h('Intervalo: '.$minutesDiff.' min').'"';
  }
  return '<span class="badge '.$class.' me-1"'.$title.'>'.$label.': '.h($v).'</span>';
}
function chip($text, $class='bg-primary'){
  return '<span class="badge '.$class.' me-1">'.h($text).'</span>';
}
function svResumen($r){
  $parts = [];
  if(!empty($r['tension_arterial']))        $parts[] = 'TA '.h($r['tension_arterial']);
  if(!empty($r['frecuencia_cardiaca']))     $parts[] = 'FC '.h($r['frecuencia_cardiaca']);
  if(!empty($r['frecuencia_respiratoria'])) $parts[] = 'FR '.h($r['frecuencia_respiratoria']);
  if(!empty($r['spo2']))                    $parts[] = 'SpO2 '.h($r['spo2']).'%';
  if(!empty($r['temperatura']))             $parts[] = 'Temp '.h($r['temperatura']).' C';
  return $parts ? implode(' | ', $parts) : '-';
}
?>

<div class="container mt-4">

  <?php 
    // Mostrar mensaje de sesión (éxito/error) y limpiar localStorage si es necesario.
    if (isset($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } 
    if (isset($_SESSION['clear_storage'])) {
        echo $_SESSION['clear_storage'];
        unset($_SESSION['clear_storage']); // Limpiar para no ejecutarlo de nuevo.
    }
  ?>

  <!-- Buscador -->
  <form method="GET" class="mb-3">
    <div class="input-group">
      <input type="text" name="query" class="form-control" placeholder="Buscar: registro, paciente, documento, IPS, ambulancia, tripulante..." value="<?php echo h($query); ?>">
      <button class="btn btn-success" type="submit">Buscar</button>
    </div>
  </form>

  <!-- Tarjetas -->
  <div class="row g-2">
    <?php while($row = $res->fetch_assoc()): ?>
      <?php
        // Nivel TAB/TAM segun haya medico en la tripulacion
        $nivel = !empty($row['medico_tripulante']) ? 'TAM' : 'TAB';
        $nivelClass = ($nivel === 'TAM') ? 'bg-danger' : 'bg-success';

        // Chips servicio / traslado / placa / nivel
        $chips = [];
        $chips[] = chip($nivel, $nivelClass); // TAB/TAM coloreado
        if(!empty($row['servicio'])) {
//          $chips[] = chip($row['servicio'], 'bg-primary');
        }
        $tt = $row['tipo_traslado'];
        if(!empty($row['traslado_tipo'])) $tt = ($tt ? $tt.' / ' : '').$row['traslado_tipo'];
        if(!empty($tt)) $chips[] = chip($tt, 'bg-light text-dark');
        if(!empty($row['ambulancia'])) $chips[] = chip($row['ambulancia'], 'bg-dark'); // solo placa, sin "Amb "

        // Chip de estado de registro (anulaciones)
        $estadoReg = $row['estado_registro'] ?? 'ACTIVA';
        if ($estadoReg === 'ANULADA') {
          $chips[] = chip('ANULADA', 'bg-danger');
        } elseif ($estadoReg === 'ANULACION_SOLICITADA') {
          $chips[] = chip('Anulación solicitada', 'bg-warning text-dark');
        }

        // Origen / destino
        $origen  = !empty($row['atencion_en']) ? $row['atencion_en'] : '-';
        $destino = '-';
        if(!empty($row['nombre_ips_receptora'])){
          $destino = $row['nombre_ips_receptora'];
          if(!empty($row['nit_ips_receptora'])) $destino .= ' (NIT '. $row['nit_ips_receptora'] .')';
        } elseif(!empty($row['ips_destino'])) {
          $destino = $row['ips_destino'];
        }

        // Localizacion compacta
        $loc = [];
        if(!empty($row['direccion_servicio'])) $loc[] = $row['direccion_servicio'];
        if(!empty($row['localizacion'])) $loc[] = $row['localizacion'];
        if(!empty($row['municipio'])) $loc[] = $row['municipio'];
        $locTxt = $loc ? '<div class="small text-muted lh-tight">'.h(implode('  |   ', $loc)).'</div>' : '';

        // Tripulacion
        $trip = [];
        if(!empty($row['conductor']))         $trip[]='Cond: '.$row['conductor'];
        if(!empty($row['tripulante']))        $trip[]='TAPH: '.$row['tripulante'];
        if(!empty($row['medico_tripulante'])) $trip[]='TAM: '.$row['medico_tripulante'];
        $tripTxt = $trip ? implode(' | ', $trip) : '-';

        // Fecha/hora y SV / badges
        $fh = $row['fecha_hora_atencion'] ? date('Y-m-d H:i', strtotime($row['fecha_hora_atencion'])) : ($row['fecha'] ? $row['fecha'] : '-');
        $sv = svResumen($row);
        $badgeProc = !empty(trim((string)$row['procedimientos'])) ? '<span class="badge bg-success me-1">Proc</span>' : '<span class="badge bg-light text-dark me-1">Proc: 0</span>';
        $diffDespLleg = minutesDiffNullable($row['hora_despacho'], $row['hora_llegada']);
        $diffLlegIngreso = minutesDiffNullable($row['hora_llegada'], $row['hora_ingreso']);
        $diffIngresoFinal = minutesDiffNullable($row['hora_ingreso'], $row['hora_final']);
        foreach (['diffDespLleg','diffLlegIngreso','diffIngresoFinal'] as $diffKey) {
          if ($$diffKey !== null && $$diffKey < 0) { $$diffKey = null; }
        }
        $badgeMeds = !empty(trim((string)$row['medicamentos_aplicados'])) ? '<span class="badge bg-success me-1">Meds</span>' : '<span class="badge bg-light text-dark me-1">Meds: 0</span>';
      ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card card-hover card-compact h-100">
          <div class="card-body lh-tight">
            <div class="d-flex justify-content-between align-items-start mb-compact">
              <div class="lh-tight">
                <div class="fw-semibold mb-compact">Registro #<?php echo h($row['id']); ?></div>
                <div class="small muted mb-compact"><?php echo h($fh); ?></div>
              </div>
              <div class="text-end chipline"><?php echo implode('', $chips); ?></div>
            </div>

            <div class="divider"></div>

            <div class="mb-compact lh-tight">
              <div class="fw-semibold mb-compact">Paciente</div>
              <div class="fw-semibold mb-compact"><?php echo h($row['nombres_paciente']); ?></div>
              <div class="small muted mb-compact"><?php echo h(($row['tipo_identificacion'] ?: 'ID').'  |   '.$row['id_paciente']); ?></div>
              <?php
                $px = [];
                if(!empty($row['genero_nacer']))     $px[] = $row['genero_nacer'];
                if(!empty($row['fecha_nacimiento'])) $px[] = 'FN: '.h($row['fecha_nacimiento']);
                if(!empty($row['rh']))               $px[] = 'RH: '.$row['rh'];
                if(!empty($row['eps_nombre']))       $px[] = 'EPS: '.$row['eps_nombre'];
                if($px) echo '<div class="small text-muted lh-tight mb-compact">'.h(implode('  |   ', $px)).'</div>';
              ?>
            </div>

            <?php if (!empty($row['causa_externa_codigo']) || !empty($row['causa_externa'])): ?>
            <div class="mb-compact lh-tight">
              <div class="fw-semibold mb-compact">Causa Externa</div>
              <div class="small">
                <?php 
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
                ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="mb-compact lh-tight">
              <div class="mb-compact"><strong>Origen</strong>: <?php echo h($origen); ?> <?php echo $locTxt; ?></div>
              <div class="text-muted">&rarr; <strong>Destino</strong>: <?php echo h($destino); ?></div>
            </div>

            <div class="mb-compact lh-tight">
              <div class="fw-semibold mb-compact">Tripulacion</div>
              <div class="small"><?php echo h($tripTxt); ?></div>
            </div>

            <div class="mb-compact lh-tight">
              <div class="fw-semibold mb-compact">Linea de tiempo</div>
              <div>
                <?php
                  echo timePill('Despacho', $row['hora_despacho']);
                  echo timePill('Llegada', $row['hora_llegada'], $diffDespLleg);
                  echo timePill('Ingreso IPS', $row['hora_ingreso'], $diffLlegIngreso);
                  echo timePill('Hora final', $row['hora_final'], $diffIngresoFinal);
                ?>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between lh-tight">
              <div><span class="fw-semibold">SV</span>: <?php echo h($sv); ?></div>
              <div class="chipline"><?php echo $badgeProc . $badgeMeds; ?></div>
            </div>
          </div>

          <div class="card-footer d-flex justify-content-end gap-2">
            <a class="btn btn-sm btn-primary" href="obtener_detalle_atencion.php?id=<?php echo urlencode($row['id']); ?>">Ver</a>
    <?php
      $rol = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
      $rol = is_string($rol) ? strtolower(trim($rol)) : "";
      $rolesAdmin = ['administrativo','master','dev'];
      if (in_array($rol, $rolesAdmin, true)):
    ?>
    <a class="btn btn-sm btn-outline-secondary" href="generar_pdf.php?id=<?php echo urlencode($row['id']); ?>" target="_blank" rel="noopener">PDF</a>
    <a class="btn btn-sm btn-outline-warning" href="admin/editar_atencion_admin.php?id=<?php echo urlencode($row['id']); ?>">Editar datos administrativos</a>
    <?php endif; ?>
    <button class="btn btn-sm btn-success" onclick="window.print()">Imprimir</button>
          </div>
          </div>        </div>    <?php endwhile; $stmt->close(); ?>
  </div>

  <!-- Paginacion -->
  <nav class="mt-3" aria-label="Paginacion de atenciones">
    <ul class="pagination justify-content-center">
      <?php $base = '?'.http_build_query(['query'=>$query]); ?>
      <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
        <a class="page-link" href="<?php echo $base.'&page='.max(1,$page-1); ?>" aria-label="Anterior">&laquo;</a>
      </li>
      <?php
        $start = max(1, $page-2);
        $end   = min($totalPages, $page+2);
        for ($p=$start; $p<=$end; $p++):
      ?> 
      <li class="page-item <?php echo $p==$page?'active':''; ?>">
        <a class="page-link" href="<?php echo $base.'&page='.$p; ?>"><?php echo $p; ?></a>
      </li>
      <?php endfor; ?>
      <li class="page-item <?php echo $page>=$totalPages?'disabled':''; ?>">
        <a class="page-link" href="<?php echo $base.'&page='.min($totalPages,$page+1); ?>" aria-label="Siguiente">&raquo;</a>
      </li>
    </ul>
  </nav>

</div>

<?php
// ===== Footer / Fallback =====
$hasFooter = @include __DIR__ . '/footer.php';
if (!$hasFooter && !$hasHeader) {
  echo "</body></html>";
}
