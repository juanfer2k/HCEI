<?php
require_once 'access_control.php'; // Proteger esta página
require_once('conn.php');

// --- Si llegamos aquí, el usuario ESTÁ AUTENTICADO ---

// --- CONTROL DE ACCESO POR ROL ---
if ($_SESSION['usuario_rol'] === 'Administrativo') {
    // Si el rol es Administrativo, no puede crear registros. Redirigir a la consulta.
    $_SESSION['message'] = '<div class="alert alert-info">Tu rol solo permite consultar registros.</div>';
    header('Location: ' . BASE_URL . 'consulta_atenciones.php');
    exit;
}

// --- PRE-LLENADO DE DATOS PARA TRIPULACIÓN ---
$tripulante_data = [];
if ($_SESSION['usuario_rol'] === 'Tripulacion') {
    $stmt_tripulante = $conn->prepare("SELECT nombres, apellidos, id_cc, id_registro FROM tripulacion WHERE id = ?");
    $stmt_tripulante->bind_param("i", $_SESSION['usuario_id']);
    $stmt_tripulante->execute();
    $result_tripulante = $stmt_tripulante->get_result();
    if ($result_tripulante->num_rows === 1) {
        $tripulante_data = $result_tripulante->fetch_assoc();
    }
    $stmt_tripulante->close();
}

// Si los datos del tripulante no se llenaron (porque el rol no es 'Tripulacion'),
// pero hay un usuario en sesion, usamos los datos de la sesion.
if (empty($tripulante_data) && !empty($_SESSION['usuario_nombre'])) {
    $tripulante_data = [
        'nombres' => $_SESSION['usuario_nombre'],
        'apellidos' => $_SESSION['usuario_apellidos'] ?? '', // Asumiendo que estos datos se guardan en sesion
        'id_cc' => $_SESSION['usuario_cc'] ?? '',
        'id_registro' => $_SESSION['usuario_registro'] ?? ''
    ];
}

// --- CÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂLCULO DEL NÃƒÆ’Ã†â€™Ãƒâ€¦Ã‚Â¡MERO DE REGISTRO CONSECUTIVO ---
$siguiente_registro = '0575'; // Valor inicial si no hay registros
$max_registro = 0; // Valor por defecto

// Usar consulta preparada para consistencia y seguridad.
$stmt = $conn->prepare("SELECT MAX(CAST(registro AS UNSIGNED)) as max_registro FROM atenciones");
if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($max_registro);
    $stmt->fetch();
    $stmt->close();
}

$siguiente_numero = max(575, intval($max_registro ?? 0) + 1);
$siguiente_registro = str_pad($siguiente_numero, 4, '0', STR_PAD_LEFT);

// Lista de EPS en Colombia
$lista_eps = require 'eps_list.php';
sort($lista_eps);

// Precargar lista de IPS receptora para Select2 local (sin usar archivos aparte)
$ips_options = [];
if (isset($conn) && $conn instanceof mysqli) {
    $sql_ips = "SELECT ips_nit AS nit, ips_nombre AS nombre, ips_ciudad AS ciudad FROM ips_receptora ORDER BY ips_nombre ASC";
    if ($st_ips = $conn->prepare($sql_ips)) {
        $st_ips->execute();
        if (function_exists('mysqli_stmt_get_result')) {
            $res_ips = mysqli_stmt_get_result($st_ips);
            if ($res_ips instanceof mysqli_result) {
                while ($row = $res_ips->fetch_assoc()) {
                    $nit = (string)($row['nit'] ?? '');
                    $nom = (string)($row['nombre'] ?? '');
                    $ciu = (string)($row['ciudad'] ?? '');
                    $ips_options[] = [
                        'id' => $nit,
                        'text' => $nom . ' (NIT: ' . $nit . ')' . ($ciu !== '' ? ' - ' . $ciu : ''),
                        'nit' => $nit,
                        'nombre' => $nom,
                        'ciudad' => $ciu,
                    ];
                }
            }
        } else {
            $st_ips->bind_result($nit, $nom, $ciu);
            while ($st_ips->fetch()) {
                $nit = (string)($nit ?? '');
                $nom = (string)($nom ?? '');
                $ciu = (string)($ciu ?? '');
                $ips_options[] = [
                    'id' => $nit,
                    'text' => $nom . ' (NIT: ' . $nit . ')' . ($ciu !== '' ? ' - ' . $ciu : ''),
                    'nit' => $nit,
                    'nombre' => $nom,
                    'ciudad' => $ciu,
                ];
            }
        }
        $st_ips->close();
    }
}

include 'header.php'; // Incluimos el header ANTES de cualquier salida HTML
?>
<script>
  window.IPS_DATA = <?= json_encode($ips_options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<style type="text/css">
.text-muted {
}
</style>
<style>
  /* Estilos dinamicos para las secciones del formulario, inspirados en el entorno urbano y de emergencias */
  .form-section {
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease-in-out;
  }
  .form-section:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(0, 0, 0, 0.35);
  }
  :root {
    --tabtam-tam: #d80f17;
    --tabtam-tab: #252f60;
    --tabtam-aph: #d9d900;
  }
  .tabtam-toggle {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
  }
  .btn-tabtam {
    border: 0;
    border-radius: 999px;
    padding: 10px 18px;
    background: rgba(37, 47, 96, 0.12);
    color: var(--tabtam-tab);
    font-weight: 600;
    letter-spacing: 0.02em;
    box-shadow: 0 4px 10px rgba(29, 43, 68, 0.15);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 10px;
    transition: all 0.2s ease-in-out;
  }
  .btn-tabtam:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 18px rgba(29, 43, 68, 0.2);
  }
  .btn-tabtam.active {
    background: linear-gradient(135deg, #1d2b44, #405ef4);
    color: #fff;
    box-shadow: 0 12px 20px rgba(29, 43, 68, 0.25);
  }
  .btn-tabtam--tab {
    background: rgba(37, 47, 96, 0.12);
    background: color-mix(in srgb, var(--tabtam-tab) 12%, transparent);
    color: var(--tabtam-tab);
  }
  .btn-tabtam--tam {
    background: rgba(216, 15, 23, 0.12);
    background: color-mix(in srgb, var(--tabtam-tam) 12%, transparent);
    color: color-mix(in srgb, var(--tabtam-tam) 70%, #4d0609);
  }
  .btn-tabtam--aph {
    background: rgba(217, 217, 0, 0.18);
    background: color-mix(in srgb, var(--tabtam-aph) 18%, transparent);
    color: color-mix(in srgb, var(--tabtam-aph) 65%, #3f3f00);
  }
  .btn-tabtam--tab.active {
    background: linear-gradient(135deg, var(--tabtam-tab), #3b4a8f);
    background: linear-gradient(135deg, var(--tabtam-tab), color-mix(in srgb, var(--tabtam-tab) 70%, #6273c6));
    color: #fff;
  }
  .btn-tabtam--tam.active {
    background: linear-gradient(135deg, var(--tabtam-tam), #f23a3f);
    background: linear-gradient(135deg, var(--tabtam-tam), color-mix(in srgb, var(--tabtam-tam) 75%, #ff6a70));
    color: #fff;
  }
  .btn-tabtam--aph.active {
    background: linear-gradient(135deg, var(--tabtam-aph), #f0f064);
    background: linear-gradient(135deg, var(--tabtam-aph), color-mix(in srgb, var(--tabtam-aph) 60%, #fffce2));
    color: var(--tabtam-tab);
  }
  .btn-tabtam__code {
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    margin: 0;
  }
  .btn-tabtam.active .btn-tabtam__code {
    background: rgba(255, 255, 255, 0.28);
  }
  .btn-tabtam__label {
    font-size: 0.95rem;
    letter-spacing: 0.01em;
    text-transform: none;
  }
  /* Paleta de colores con gradientes para dar mas vida y separar visualmente las etapas del registro */
  .form-section.internal { background-image: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); } /* Gris claro - Profesional y clinico */
  .form-section.insurance { background-image: linear-gradient(135deg, #f1f8e9 0%, #dcedc8 100%); } /* Verde claro - Tramite, papeleo */
  .form-section.patient { background-image: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); } /* Purpura suave - Centrado en el paciente */
  .form-section.context { background-image: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); } /* Naranja suave - Contexto de la emergencia */
  .form-section.clinical { background-image: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%); } /* Verde azulado - Datos medicos */
  .form-section.glasgow { background-image: linear-gradient(135deg, #fffde7 0%, #fff59d 100%); } /* Amarillo - Alerta y evaluacion critica */
  .form-section.antecedentes { background-image: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%); } /* Rosa suave - Historial medico */
  .form-section.oxigeno { background-image: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); } /* Azul claro para Oxigenoterapia */
  .form-section.firmas { background-image: linear-gradient(135deg, #eceff1 0%, #cfd8dc 100%); } /* Gris - Formalidad y legalidad */
  .form-section.adjuntos { background-image: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); } /* Gris claro - Neutral */
  .form-section label { font-weight: 500; }
  .form-section .form-control, .form-section .form-select { min-width: 100%; }
  .section-header { background: none; border-bottom: 2px solid #34446d; margin-bottom: 10px; padding-bottom: 8px; }
  .section-header { background: none; border-bottom: 2px solid #34446d; margin-bottom: 10px; padding-top: 5px; padding-bottom: 5px; }
  .section-header h3 {
    font-size: 1rem; /* Tamano de fuente mas pequeno */
    margin-bottom: 0; /* Eliminar margen inferior del h3 */
  }
  .form-container h2 { font-size: 1rem; }
  .select2-container .select2-selection--single { height: 38px !important; }
  .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
  .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
  .signature-pad-container {
    max-width: 500px; /* Ancho maximo para el contenedor de la firma */
    margin-left: auto;
    margin-right: auto; }
</style>
<style>
  /* Estilo Neumorfico para inputs, textareas y selects */
  .form-control {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 5px 6px;
    font-size: 16px;
    box-shadow: inset 2px 3px 1px rgba(0, 0, 0, 0.15), inset -3px -3px 6px rgba(255, 255, 255, 0.7);
    transition: box-shadow 0.3s ease;
    color: #5b6f9d;
    height: auto; /* Para que el padding funcione bien en selects */
  }
</style>
  <div class="container-fluid px-3">
    <form id="clinical-form" method="post" action="procesar_atencion.php" enctype="multipart/form-data">
      <!-- INFORMACIÓN INTERNA -->
      <div class="form-container form-section internal">
        <div class="section-header">

          <h3>Informacion Interna <span class="hl7-tag">HL7: PV1</span> <span class="hl7-tag">FHIR: Encounter</span></h3>
        </div>
        <div class="mb-3">
          <label class="form-label text-uppercase fw-semibold small text-secondary mb-2">Servicio prestado</label>
          <div class="tabtam-toggle" id="tabtamToggle" role="group" aria-label="Selector TAB TAM">
            <button type="button" class="btn-tabtam btn-tabtam--tab active" data-value="Traslado Basico" aria-pressed="true">
              <span class="btn-tabtam__code">TAB</span>
              <span class="btn-tabtam__label">Traslado Básico</span>
            </button>
            <button type="button" class="btn-tabtam btn-tabtam--tam" data-value="Traslado Medicalizado" aria-pressed="false">
              <span class="btn-tabtam__code">TAM</span>
              <span class="btn-tabtam__label">Traslado Medicalizado</span>
            </button>
            <button type="button" class="btn-tabtam btn-tabtam--aph" data-value="Atencion Prehospitalaria" aria-pressed="false">
              <span class="btn-tabtam__code">APH</span>
              <span class="btn-tabtam__label">Atención Prehospitalaria</span>
            </button>
          </div>
          <select class="form-select d-none" id="servicio" name="servicio">
            <option value="Traslado Basico" selected>Traslado Basico (TAB)</option>
            <option value="Traslado Medicalizado">Traslado Medicalizado (TAM)</option>
            <option value="Atencion Prehospitalaria">Atencion Prehospitalaria</option>
          </select>
        </div>
        <div class="row mb-1">
          <div class="col-md-4">
            <label for="registro" class="form-label required-field"># De Registro</label>
            <input name="registro" type="text" required class="form-control" id="registro" value="<?= htmlspecialchars($siguiente_registro) ?>" readonly>
          </div>
          <div class="col-md-4">
            <label for="fecha" class="form-label">Fecha (DD-MM-YYYY)</label>
            <input type="text" class="form-control" id="fecha" name="fecha" placeholder="DD-MM-YYYY">
          </div>
          <div class="col-md-4">
            <label for="ambulancia" class="form-label">Ambulancia de turno</label>
            <select class="form-select" id="ambulancia" name="ambulancia">
              <?php foreach ($empresa['ambulancias'] as $ambulancia): ?>
                <option value="<?= htmlspecialchars($ambulancia) ?>"><?= htmlspecialchars($ambulancia) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="pagador" class="form-label">Pagador</label>
            <select class="form-select" id="pagador" name="pagador">
              <option value="Particular">Particular</option>
              <option value="SOAT">SOAT</option>
              <option value="ARL">ARL</option>
              <option value="EPS">EPS</option>
              <option value="ADRES">ADRES</option>
              <option value="Servicio Social">Servicio Social</option>
              <option value="Convenio">Convenio</option>
            </select>
          </div>
          <div class="col-md-4" id="placa_paciente_group" style="display:none;">
            <label for="placa_paciente" class="form-label">Placa vehículo (paciente)</label>
            <input type="text" class="form-control" id="placa_paciente" name="placa_paciente" placeholder="ABC123">
          </div>
          <div class="col-md-4">
            <label for="quien_informo" class="form-label">¿Quién informó el servicio?</label>
            <select class="form-select" id="quien_informo" name="quien_informo">
              <option value="Central">Central</option>
              <option value="Turno">Turno</option>
              <option value="PONAL">PONAL</option>
              <option value="Transito">Transito</option>
              <option value="Comunidad">Comunidad</option>
            </select>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col">
            <label for="hora_despacho" class="form-label">Hora de Despacho</label>
            <input type="time" class="form-control" id="hora_despacho" name="hora_despacho" placeholder="HH:MM">
          </div> 
          <div class="col">
            <label for="hora_llegada" class="form-label">Hora de Llegada</label>
            <input type="time" class="form-control" id="hora_llegada" name="hora_llegada" placeholder="HH:MM">
          </div>
          <div class="col">
            <label for="hora_ingreso" class="form-label">Hora Ingreso IPS</label>
            <input type="time" class="form-control" id="hora_ingreso" name="hora_ingreso" placeholder="HH:MM">
          </div>
          <div class="col">
            <label for="hora_final" class="form-label">Hora Final</label>
            <input type="time" class="form-control" id="hora_final" name="hora_final" placeholder="HH:MM">
          </div>
          <div class="col d-flex flex-column align-items-center justify-content-center">
            <label for="tiempo_servicio_ocupado" class="form-label">Tiempo de Servicio</label>
            <span id="tiempo_servicio_ocupado" class="badge bg-info text-dark fs-6">00:00</span>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-8">
              <label for="conductor" class="form-label">Conductor</label>
              <input type="text" class="form-control" id="conductor" name="conductor">
          </div>
          <div class="col-md-4">
            <label for="cc_conductor" class="form-label">CC Conductor</label>
            <input type="text" class="form-control" id="cc_conductor" name="cc_conductor">
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-6">
            <label for="tripulante" class="form-label">Tripulante</label>
            <input type="text" class="form-control" id="tripulante_display" value="<?= htmlspecialchars(trim(($tripulante_data['nombres'] ?? '') . ' ' . ($tripulante_data['apellidos'] ?? ''))) ?>" readonly>
            <input type="hidden" name="tripulante_hidden" id="tripulante_hidden" value="<?= htmlspecialchars(trim(($tripulante_data['nombres'] ?? '') . ' ' . ($tripulante_data['apellidos'] ?? ''))) ?>">
          </div>
          <div class="col-md-3">
            <label for="tipo_id_tripulante" class="form-label">Tipo ID Tripulante</label>
            <select class="form-select" id="tipo_id_tripulante" name="tipo_id_tripulante">
                <option value="Registro" <?= !empty($tripulante_data['id_registro']) ? 'selected' : '' ?>>Registro</option>
                <option value="CC" <?= empty($tripulante_data['id_registro']) ? 'selected' : '' ?>>CC</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="cc_tripulante" class="form-label">Numero ID</label>
            <input type="text" class="form-control" id="cc_tripulante" name="cc_tripulante" value="<?= htmlspecialchars(!empty($tripulante_data['id_registro']) ? $tripulante_data['id_registro'] : ($tripulante_data['id_cc'] ?? '')) ?>">
          </div>
        </div>
        <div class="row mb-2" id="medico-tripulante-container" style="display: none;">
          <div class="col-md-6">
            <label for="medico_tripulante" class="form-label">Medico Tripulante</label>
            <input type="text" class="form-control" id="medico_tripulante" name="medico_tripulante">
          </div>
          <div class="col-md-3">
            <label for="tipo_id_medico" class="form-label">Tipo ID Medico</label>
            <select class="form-select" id="tipo_id_medico" name="tipo_id_medico">
                <option value="Registro Medico" selected>Registro Medico</option>
                <option value="CC">CC</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="cc_medico" class="form-label">Numero ID</label>
            <input type="text" class="form-control" id="cc_medico" name="cc_medico">
          </div>
        </div>
<div class="row mb-2">
  <div class="col-md-12">
    <label for="direccion_servicio" class="form-label">Direccion del Servicio/Atencion</label>
    <div class="input-group">
      <input type="text" class="form-control" id="direccion_servicio" name="direccion_servicio" placeholder="Direccion del Servicio/Atencion">
      
    </div>
  </div>
</div>
<div class="row mb-2">
  <div class="col-md-3">
    <label for="localizacion" class="form-label">Localizacion del Evento</label>
    <div class="form-check">
      <input class="form-check-input" checked type="radio" name="localizacion" id="urbano" value="Urbano">
      <label class="form-check-label" for="urbano">Urbano</label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="localizacion" id="rural" value="Rural">
      <label class="form-check-label" for="rural">Rural</label>
    </div>
  </div>
  <div class="col-md-4">
    <!-- Campo IPS eliminado y moveremos el selector a la sección de Nombre de IPS Receptora -->
  </div>
  <div class="col-md-3">
    <label for="tipo_traslado" class="form-label">Tipo de Servicio</label>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="tipo_traslado" id="sencillo" value="Sencillo" checked>
      <label class="form-check-label" for="sencillo">Sencillo</label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="tipo_traslado" id="redondo" value="Redondo">
      <label class="form-check-label" for="redondo">Redondo</label>
    </div>
  </div>
</div> </div><!-- Cierre de .form-section.internal -->
      <!-- IDENTIFICACIÓN DE LA ASEGURADORA (SOAT) -->
      <div id="datos-soat" class="form-container form-section insurance">
        <div class="section-header">
          <h3>Datos para ASEGURADORA <span class="hl7-tag">HL7: IN1</span> <span class="hl7-tag">FHIR: Coverage</span></h3>
        </div>
        <div class="row mb-2" id="eps-container" style="display: none;">
          <div class="col-md-12">
            <label for="eps_nombre" class="form-label">Nombre de la EPS</label>
            <select class="form-select" id="eps_nombre" name="eps_nombre">
              <option value=""Seleccione una EPS...</option>
              <?php foreach ($lista_eps as $eps): ?>
                <option value="<?= htmlspecialchars($eps) ?>"><?= htmlspecialchars($eps) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="aseguradora_soat" class="form-label">Aseguradora </label>
            <input type="text" class="form-control" id="aseguradora_soat" name="aseguradora_soat">
          </div>
          <div class="col-md-4">
            <label for="ips2" class="form-label">IPS 2 de Destino</label>
            <input type="text" class="form-control" id="ips2" name="ips2">
          </div>
          <div class="col-md-4">
            <label for="ips3" class="form-label">IPS 3 de Destino</label>
            <input type="text" class="form-control" id="ips3" name="ips3">
          </div>
          <div class="col-md-4">
            <label for="ips4" class="form-label">IPS 4 de Destino</label>
            <input type="text" class="form-control" id="ips4" name="ips4">
          </div>
        </div>
      </div>

      <!-- IDENTIFICACIÓN DEL PACIENTE -->
       <div class="form-container form-section patient"> 
        <div class="section-header">
        <h3>Identificacion del Paciente <span class="hl7-tag">HL7: PID</span> <span class="hl7-tag">FHIR: Patient</span></h3>
        </div>
        <div class="row mb-2">
          <div class="col-md-6">
            <label for="nombres_paciente" class="form-label">Nombres del Paciente</label>
            <input type="text" class="form-control" id="nombres_paciente" name="nombres_paciente">
          </div>
          <div class="col-md-6">
            <label for="tipo_identificacion" class="form-label">Tipo de Identificacion</label>
            <select class="form-select" id="tipo_identificacion" name="tipo_identificacion">
              <option value="CC" selected="selected">CC</option>
              <option value="CE">CE</option>
              <option value="Pasaporte">Pasaporte</option>
              <option value="TI">TI</option>
              <option value="RC">RC</option>
              <option value="NN">NN</option>
              <option value="PEP">PEP</option>
              <option value="otro">Otro</option>

            </select>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="id_paciente" class="form-label">Numero de Identificacion</label>
            <input type="text" class="form-control" id="id_paciente" name="id_paciente">
          </div>
          <div class="col-md-4">
            <label for="genero_nacer" class="form-label">Genero al Nacer</label>
            <select class="form-select" id="genero_nacer" name="genero_nacer">
              <option value="Masculino">Masculino</option>
              <option value="Femenino" selected="selected">Femenino</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="fecha-nacimiento-display" class="form-label">Fecha de Nacimiento (DD-MM-YYYY)</label>
            <input type="text" class="form-control" id="fecha_nacimiento_display" placeholder="DD-MM-YYYY">
            <input type="hidden" name="fecha_nacimiento" id="fecha_nacimiento_hidden">
            <input type="text" class="form-control mt-1" id="edad_paciente" name="edad_paciente" placeholder="Edad" readonly>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-8">
            <label for="direccion_domicilio" class="form-label">Direccion Domicilio</label>
            <input type="text" class="form-control" id="direccion_domicilio" name="direccion_domicilio">
          </div>
          <div class="col-md-4">
            <label for="barrio_paciente" class="form-label">Barrio del Paciente</label>
            <input type="text" class="form-control" id="barrio_paciente" name="barrio_paciente">
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="municipio" class="form-label">Municipio</label>
            <select class="form-select" id="municipio" name="municipio" style="width: 100%;"></select>
            <input type="hidden" id="codigo_municipio" name="codigo_municipio">
          </div>
                    <div class="col-md-4">
            <label for="telefono_paciente" class="form-label">Telefono del Paciente</label>
            <input type="tel" class="form-control" id="telefono_paciente" name="telefono_paciente">
          </div>
        </div>
        <!-- Aqui van los 3 campos solicitados de at diferencial -->
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="atencion_en" class="form-label">Atencion en</label>
            <select class="form-select" id="atencion_en" name="atencion_en" required>
              <option value="Via Publica">Via Publica</option>
              <option value="Evento">Evento</option>
              <option value="Centro Hospitalario">Centro Hospitalario</option>
              <option value="Plantel Educativo">Plantel Educativo</option>
              <option value="Trabajo">Trabajo</option>
              <option value="Residencia">Residencia</option>
              <option value="Ciclovia">Ciclovia</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="etnia" class="form-label">Enfoque Diferencial: Pertenencia Étnica</label>
            <select class="form-select" id="etnia" name="etnia" required>
              <option value="Otro">Otro (especificar)</option>
              <option value="Indigena">Indígena</option>
              <option value="Afrocolombiano">Afrocolombiano (incluye afrodescendientes, negros, mulatos, palenqueros)</option>
              <option value="Raizal">Raizal del Archipiélago de San Andrés y Providencia</option>
              <option value="Rom">Rom (Gitano)</option>
              <option value="Mestizo">Mestizo</option>
              
            </select>
            <input type="text" class="form-control mt-2" id="especificar_otra" name="especificar_otra" placeholder="Si seleccionaste 'Otro', especifica aqui" style="display:none;">
          </div>
          <div class="col-md-4">
            <label for="discapacidad" class="form-label">Discapacidad</label>
            <select class="form-select" id="discapacidad" name="discapacidad">
              <option value="Ninguna">Ninguna</option>
              <option value="Fisica">Fisica</option>
              <option value="Auditiva">Auditiva</option>
              <option value="Visual">Visual</option>
              <option value="Sordoceguera">Sordoceguera</option>
              <option value="Intelectual">Intelectual</option>
              <option value="Psicosocial">Psicosocial (mental)</option>
              <option value="Multiple">Multiple</option>
            </select>
          </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-12">
                <label for="escena_paciente" class="form-label">Situacion del Paciente en la Escena</label>
                <select class="form-select" id="escena_paciente" name="escena_paciente">
                    <option value="Peaton">Peatón</option>
                    <option value="Ciclista">Ciclista</option>
                    <option value="Motociclista">Motociclista</option>
                    <option value="Conductor Auto">Conductor Auto</option>
                    <option value="Pasajero Auto">Pasajero Auto</option>
                    <option value="Conductor Maquinaria">Conductor Maquinaria</option>
                    <option value="Caida propia altura">Caída de su propia altura</option>
                    <option value="Otro">Otro</option>
                </select>
                <input type="text" class="form-control mt-2" id="escena_paciente_otro" name="escena_paciente_otro" placeholder="Si seleccionaste 'Otro', especifica aqui" style="display:none;">
            </div>
        </div>
      </div>
      <!-- ESCALA DE DOWNTON -->
      <div class="form-container form-section clinical" id="downton-container">
        <div class="section-header">
          <h3>Escala de Riesgo de Caidas (Downton)</h3>
        </div>
        <input type="hidden" name="downton_total" id="downton_total">
        <div class="row">
          <!-- Columna 1 -->
          <div class="col-md-3">
            <h5>Caidas Previas</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_caidas" value="0" checked> No (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_caidas" value="1"> Si (1)</label></div>
            <h5 class="mt-3">Estado Mental</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_mental" value="0" checked> Orientado (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_mental" value="1"> Confuso (1)</label></div>
          </div>
          <!-- Columna 2 -->
          <div class="col-md-5">
            <h5>Medicamentos (1 punto si se marca cualquiera)</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_medicamentos" value="0" checked> Ninguno</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_medicamentos" value="1"> Tranquilizantes/Sedantes</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_medicamentos" value="1"> Diuréticos</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_medicamentos" value="1"> Hipotensores (no diuréticos)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_medicamentos" value="1"> Antiparkinsonianos</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_medicamentos" value="1"> Antidepresivos</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_medicamentos" value="1"> Otros medicamentos</label></div>
          </div>
          <!-- Columna 3 -->
          <div class="col-md-4">
            <h5>Deficits Sensoriales</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="0" checked> Ninguno (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="1"> Alteraciones visuales (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="1"> Alteraciones auditivas (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="1"> Extremidades (ej. ACV) (1)</label></div>
            <h5 class="mt-3">Deambulacion</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="0" checked> Normal (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="1"> Segura con ayuda (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="1"> Insegura con/sin ayuda (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="1"> Imposible (1)</label></div>
          </div>
        </div>
        <div class="text-center mt-3">
          <label class="form-label" style="font-weight: bold;">Total Escala Downton:</label>
          <div id="downton_total">0</div>
          <input type="hidden" name="downton_total" id="downton_total_hidden">
          <p id="downton_riesgo" class="mt-2" style="font-weight: bold;"></p>
        </div>
      </div>
      <!-- DIAGNÓSTICO Y MOTIVO -->
      <div class="form-container form-section context">
        <div class="section-header">
          <h3>Contexto Clinico <span class="hl7-tag">FHIR: Condition</span></h3>
        </div>
        <div class="row">
            <div class="col-md-6" id="diagnostico-container">
                <label for="diagnostico_principal" class="form-label">Diagnostico Principal (CIE-10)</label>
                <select class="form-control" id="diagnostico_principal" name="diagnostico_principal" style="width: 100%;"></select>
            </div>
            <div class="col-md-6" id="motivo-traslado-container">
                <label for="motivo_traslado" class="form-label">Motivo del Traslado</label>
                <textarea class="form-control" id="motivo_traslado" name="motivo_traslado" rows="2"></textarea>
            </div>
        </div>
      </div>

      <!-- DATOS CLÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂNICOS -->
      <div class="form-container form-section clinical">
        <div class="section-header">
          <h3>Datos Clinicos <span class="hl7-tag">HL7: OBR</span> <span class="hl7-tag">FHIR: Observation</span></h3>
        </div>
          <div class="row mb-2">
          <div class="col-md-3">
            <label for="frecuencia_cardiaca" class="form-label">Frecuencia Cardiaca</label>
            <div class="input-group">
              <input type="number" class="form-control" id="frecuencia_cardiaca" name="frecuencia_cardiaca">
              <span class="input-group-text">lpm</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="frecuencia_respiratoria" class="form-label">Frecuencia Respiratoria</label>
            <div class="input-group">
              <input type="number" class="form-control" id="frecuencia_respiratoria" name="frecuencia_respiratoria">
              <span class="input-group-text">rpm</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="spo2" class="form-label">SpO2</label>
            <div class="input-group">
              <input type="number" class="form-control" id="spo2" name="spo2">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="tension_arterial" class="form-label">Tension Arterial</label>
            <input type="text" class="form-control" id="tension_arterial" name="tension_arterial" placeholder="ej: 120/80">
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-3">
            <label for="glucometria" class="form-label">Glucometria</label>
            <div class="input-group">
              <input type="number" class="form-control" id="glucometria" name="glucometria">
              <span class="input-group-text">mg/dL</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="temperatura" class="form-label">Temperatura</label>
            <div class="input-group">
              <input type="number" step="0.1" class="form-control" id="temperatura" name="temperatura">
              <span class="input-group-text">°C</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="rh" class="form-label">RH</label>
            <select class="form-select" id="rh" name="rh">
              <option value="O+" selected="selected">O+</option>
              <option value="O-">O-</option>
              <option value="A+">A+</option>
              <option value="A-">A-</option>
              <option value="B+">B+</option>
              <option value="B-">B-</option>
              <option value="AB+">AB+</option>
              <option value="AB-">AB-</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Llenado Capilar</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" checked name="llenado_capilar" id="menos-3" value="<3 segundos">
              <label class="form-check-label" for="menos-3" ><3 segundos</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="llenado_capilar" id="mas-3" value=">3 segundos">
              <label class="form-check-label" for="mas-3">>3 segundos</label>
            </div>
          </div>
        </div>
        <div class="row mb-2">
  <div class="col-md-3">
    <label for="peso" class="form-label">Peso</label>
    <div class="input-group">
      <input type="number" step="0.1" class="form-control" id="peso" name="peso">
      <span class="input-group-text">kg</span>
    </div>
  </div>
  <div class="col-md-3">
    <label for="talla" class="form-label">Talla</label>
    <div class="input-group">
      <input type="number" class="form-control" id="talla" name="talla">
      <span class="input-group-text">cm</span>
    </div>
  </div>
  <div class="col-md-3">
    <label for="imc" class="form-label">IMC</label>
    <input type="text" class="form-control" id="imc" readonly>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <span id="imc-riesgo" class="badge"></span>
  </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-12">
            <label for="examen_fisico" class="form-label" style="font-size: 1.1rem; font-weight: bold;">Describa el Examen Fisico</label>
            <textarea class="form-control" id="examen_fisico" name="examen_fisico" rows="3"></textarea>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-12">
            <label for="procedimientos" class="form-label" style="font-size: 1.1rem; font-weight: bold;">Procedimientos</label>
            <textarea class="form-control" id="procedimientos" name="procedimientos" rows="3"></textarea>
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-12">
            <label for="consumo_servicio" class="form-label" style="font-size: 1.1rem; font-weight: bold;">Consumo durante el Servicio</label>
            <textarea class="form-control" id="consumo_servicio" name="consumo_servicio" rows="3"></textarea>
          </div>
        </div>
      </div>
      <!-- ESCALA DE COMA DE GLASGOW -->
      <div class="form-container form-section glasgow">
        <div class="section-header">
          <h3>Escala de Coma de Glasgow <span class="hl7-tag">HL7: OBX</span> <span class="hl7-tag">FHIR: Observation</span></h3>
        </div>
        <div class="row">
          <div class="col-md-4">
            <h5>Apertura Ocular</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="4"> (4) Espontanea</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="3" checked> (3) Al lenguaje verbal</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="2"> (2) Al dolor</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="1"> (1) Sin respuesta</label></div>
          </div>
          <div class="col-md-4">
            <h5>Respuesta Verbal</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="5"> (5) Orientado</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="4"> (4) Confuso</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="3" checked> (3) Palabras inapropiadas</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="2"> (2) Sonidos incomprensibles</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="1"> (1) Sin respuesta</label></div>
          </div>
          <div class="col-md-4">
            <h5>Respuesta Motora</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="6"> (6) Obedece ordenes</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="5"> (5) Localiza el dolor</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="4"> (4) Retirada al dolor</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="3" checked> (3) Flexion anormal</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="2"> (2) Extension anormal</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="1"> (1) Sin respuesta</label></div>
          </div>
        </div>
        <div class="text-center mt-4">
          <label class="form-label" style="font-weight: bold;">Total Escala Glasgow:</label>
          <input type="number" class="form-control text-center" id="escala_glasgow" name="escala_glasgow" readonly style="font-size: 2rem; font-weight: bold;">
        </div>
      </div>
      <!-- ANTECEDENTES -->
      <div class="form-container form-section antecedentes">
        <div class="section-header">
          <h3>Antecedentes Medicos <span class="hl7-tag">FHIR: Condition, AllergyIntolerance</span></h3>
        </div>
        <?php
          $opciones_antecedentes = [
              'Patologicos' => ['Hipertension Arterial (HTA)', 'Diabetes Mellitus (DM)', 'Enfermedad Cardiovascular (ECV)', 'EPOC', 'Asma', 'Cancer', 'Enfermedad Renal Cronica (ERC)', 'Convulsiones / Epilepsia', 'Otro'],
              'Alergicos' => ['Medicamentos (AINES, Penicilina)', 'Alimentos (Mani, Mariscos)', 'Latex', 'Picaduras de insectos', 'Polen / Ambientales', 'Otro'],
              'Quirurgicos' => ['Apendicectomia', 'Colecistectomia', 'Cesarea', 'Cirugia Cardiaca', 'Cirugia Ortopedica', 'Otro'],
              'Traumatologicos' => ['Fracturas', 'Esguinces', 'TEC (Traumatismo)', 'Heridas por arma', 'Otro'],
              'Toxicologicos' => ['Alcohol', 'Tabaco', 'Sustancias Psicoactivas (SPA)', 'Intoxicacion por medicamentos', 'Intoxicacion por quimicos', 'Otro'],
              'Familiares' => ['Hipertension Arterial (HTA)', 'Diabetes Mellitus (DM)', 'Cancer', 'Enfermedad Cardiovascular', 'Otro']
          ];
          $antecedentes = [
            "Patologicos" => "Patologicos",
            "Alergicos" => "Alergicos",
            "Quirurgicos" => "Quirurgicos",
            "Traumatologicos" => "Traumatologicos",
            "Toxicologicos" => "Toxicologicos",
            "GinecoObstetricos" => "Gineco-Obstetricos",
            "Familiares" => "Familiares"
          ];
          foreach ($antecedentes as $key => $label) {
            $key_lower = strtolower($key);
        ?>
        <div class="row mb-2 align-items-center" id="row_ant_<?= $key_lower ?>">
          <div class="col-md-3">
            <label class="form-label"><?= $label ?></label>
          </div>
          <div class="col-md-2">
            <select class="form-select ant-select" name="ant_<?= $key_lower ?>_sn" id="ant_<?= $key_lower ?>_sn">
              <option value="No" selected>No</option>
              <option value="Si">Si</option>
            </select>
          </div>
          <div class="col-md-7 ant-cual-container" id="ant_<?= $key_lower ?>_cual_container" style="display:none;">
            <?php if (isset($opciones_antecedentes[$key])): ?>
              <select class="form-select" name="ant_<?= $key_lower ?>_cual[]" multiple>
                <?php foreach ($opciones_antecedentes[$key] as $opcion): ?>
                  <option value="<?= htmlspecialchars($opcion ?? '') ?>"><?= htmlspecialchars($opcion ?? '') ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" class="form-control mt-2 ant-otro-input" name="ant_<?= $key_lower ?>_cual_otro" placeholder="Especifique 'Otro'" style="display:none;">
              <small class="form-text text-muted">Puedes seleccionar varias (Ctrl/Cmd + clic).</small>
            <?php else: ?>
              <input type="text" class="form-control" name="ant_<?= $key_lower ?>_cual" placeholder="ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿Cual(es)?">
            <?php endif; ?>
          </div>
        </div>
        <?php } ?>
      </div>
      <!-- OXIGENOTERAPIA -->
      <div class="form-container form-section oxigeno">
        <div class="section-header">
          <h3>Oxigenoterapia <span class="hl7-tag">FHIR: Procedure</span></h3>
        </div>
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="oxigeno_dispositivo" class="form-label">Dispositivo</label>
            <select class="form-select" id="oxigeno_dispositivo" name="oxigeno_dispositivo">
              <option value=""Ninguno</option>
              <option value="Canula Nasal">Canula Nasal</option>
              <option value="Mascara Simple">Mascara Simple</option>
              <option value="Mascara con Reservorio">Mascara con Reservorio</option>
              <option value="Venturi">Venturi</option>
              <option value="Intubado">Intubado</option>
              <option value="Mascara laringea">Mascara laringea</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="oxigeno_flujo" class="form-label">Flujo (L/min)</label>
            <input type="number" step="0.5" class="form-control" id="oxigeno_flujo" name="oxigeno_flujo">
          </div>
          <div class="col-md-4">
            <label for="oxigeno_fio2" class="form-label">FiO2 (%)</label>
            <input type="number" class="form-control" id="oxigeno_fio2" name="oxigeno_fio2">
          </div>
        </div>
      </div>

      <!-- MEDICAMENTOS APLICADOS (TAM) -->
      <div class="form-container form-section oxigeno" id="medicamentos-aplicados-container" style="display: none;">
        <div class="section-header">
          <h3>Medicamentos Aplicados <span class="hl7-tag">FHIR: MedicationAdministration</span></h3>
        </div>
        <div id="lista-medicamentos">
          <!-- Los medicamentos se anadiran aqui dinamicamente -->
        </div>
        <button type="button" class="btn btn-outline-primary mt-2" id="btn-agregar-medicamento">
          + Agregar Medicamento
        </button>
        <template id="medicamento-template">
          <div class="row mb-2 medicamento-item">
            <div class="col-md-2"><label class="form-label">Hora</label><input type="text" class="form-control medicamento-hora-input" name="medicamento_hora[]" placeholder="HH:MM"></div>
            <div class="col-md-4"><label class="form-label">Nombre Generico</label><input type="text" class="form-control" name="medicamento_nombre[]"></div>
            <div class="col-md-2"><label class="form-label">Dosis</label><input type="text" class="form-control" name="medicamento_dosis[]" placeholder="Ej: 500mg"></div>
            <div class="col-md-3">
              <label class="form-label">Via</label>
              <select class="form-select" name="medicamento_via[]">
                <option value="IV">Intravenosa (IV)</option>
                <option value="IM">Intramuscular (IM)</option>
                <option value="SC">Subcutanea (SC)</option>
                <option value="VO">Via Oral (VO)</option>
                <option value="SL">Sublingual (SL)</option>
                <option value="Topica">Topica</option>
                <option value="Inhalada">Inhalada</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
            <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-danger btn-sm btn-remover-medicamento">X</button></div>
          </div>
        </template>
      </div>

      <!-- DATOS MÉDICO RECEPTOR -->
      <div class="form-container form-section firmas">
        <div class="section-header">
            <h3>Datos de Recepción al paciente</h3>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="nombre_medico_receptor" class="form-label">Nombre del Medico Receptor</label>
                <input type="text" class="form-control" id="nombre_medico_receptor" name="nombre_medico_receptor" >
            </div>
            <div class="col-md-3">
                <label for="tipo_id_medico_receptor" class="form-label ">Tipo ID</label>
                <select class="form-select" id="tipo_id_medico_receptor" name="tipo_id_medico_receptor">
                    <option value="Registro Medico" selected>Registro Medico</option>
                    <option value="CC">CC</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="id_medico_receptor" class="form-label">Numero ID</label>
                <input type="text" class="form-control" id="id_medico_receptor" name="id_medico_receptor" >
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="ips" class="form-label">Nombre de IPS Receptora</label>
                <select id="ips" style="width: 100%;"></select>
                <input type="hidden" name="ips_nombre" id="ips_nombre">
            </div>
            <div class="col-md-3">
                <label for="ips_nit" class="form-label">NIT de IPS Receptora</label>
                <input type="text" class="form-control" id="ips_nit_display" readonly>
                <input type="hidden" name="ips_nit" id="ips_nit">
            </div>
            <div class="col-md-3">
                <label for="ips_ciudad_display" class="form-label">Ciudad IPS Receptora</label>
                <input type="text" class="form-control" id="ips_ciudad_display" readonly>
                <input type="hidden" name="ips_ciudad" id="ips_ciudad">
            </div>
        </div>
      </div>
      <!-- FIRMAS Y ACEPTACIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN -->
      <div class="form-container form-section firmas">
        <div class="section-header">
          <h3>Firmas y Aceptacion <span class="hl7-tag">FHIR: Consent</span></h3>
        </div>

        <!-- Sub-seccion Firmas Tripulacion -->
        <div class="sub-section mb-4">
          <h5 class="sub-section-title">Firmas de la Tripulacion</h5>
          <div class="row">
            <div class="col-md-6">
              <label class="form-label">Tripulante</label>
              <div id="firmaParamedico" class="signature-pad-container mb-2">
                <canvas class="signature-pad" data-pen-color="#34446d"></canvas>
              </div>
              <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="paramedico">Limpiar Firma</button>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="paramedico">Ampliar</button>
              </div>
              <input type="hidden" name="firma_paramedico" id="FirmaParamedicoData">
            </div>
            <div class="col-md-6" id="medico-tripulante-firma-container" style="display: none;">
              <label class="form-label">Medico Tripulante (si aplica)</label>
              <div id="firmaMedico" class="signature-pad-container mb-2">
                <canvas class="signature-pad" data-pen-color="#a25505"></canvas>
              </div>
              <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="medico">Limpiar Firma</button>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="medico">Ampliar</button>
              </div>
              <input type="hidden" name="firma_medico" id="FirmaMedicoData">
            </div>
          </div>
        </div>

        <!-- Sub-seccion Consentimiento del Paciente -->
        <div class="sub-section mb-4">
          <h5 class="sub-section-title">Consentimiento del Paciente o Representante</h5>
          <p class="text-muted">Por favor, seleccione una opcion para continuar.</p>
          <div class="d-flex justify-content-center gap-3 mb-3">
            <button type="button" class="btn btn-success btn-lg" id="btn-aceptar-atencion">Acepto la Atencion</button>
            <button type="button" class="btn btn-danger btn-lg" id="btn-rechazar-atencion">Rechazo la Atencion</button>
          </div>

          <!-- Contenedor para la firma de ACEPTACIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN -->
          <div id="consentimiento-container" style="display: none;">
            <label class="form-label">Firma de Aceptacion</label>
            <div id="firmaPaciente" class="signature-pad-container mb-2">
              <p class="consent-text">Como firmante, mayor de edad, obrando en nombre propio y en mi condicion de Acompanante o Testigo, por medio del presente documento manifiesto que he autorizado a <?= htmlspecialchars($empresa['nombre']) ?>, empresa responsable del transporte y atencion prehospitalaria, la atencion y aplicacion de los tratamientos propuestos por el personal de salud y, en caso de ser necesario, el traslado en ambulancia por parte de <?= htmlspecialchars($empresa['nombre']) ?>. Asi mismo, autorizo a que se realicen los procedimientos necesarios para la estabilizacion clinica. </br>
Se y acepto que la practica de la medicina no es una ciencia exacta; no se me han prometido ni garantizado resultados esperados. Se me ha dado la oportunidad de preguntar y resolver las dudas, y todas ellas han sido atendidas a satisfaccion. </br>
Manifiesto que he entendido las condiciones y objetivos de la atencion que se me va a realizar, los cuidados que debo tener, y ademas comprendo y acepto el alcance y los riesgos justificados que conlleva el procedimiento. </br>
Teniendo en cuenta lo anterior, doy mi consentimiento informado libre y voluntariamente a <?= htmlspecialchars($empresa['nombre']) ?>, entendiendo y aceptando todos los riesgos, reacciones, complicaciones y resultados insatisfactorios que puedan derivarse de la atencion y de los procedimientos realizados, los cuales reconozco que pueden presentarse a pesar de que se tomen las precauciones usuales para evitarlos.</br>
Me comprometo a cumplir con las recomendaciones, instrucciones y controles posteriores al tratamiento realizado. Certifico que he tenido la oportunidad de hacer todas las preguntas pertinentes para aclarar mis dudas y que se me han respondido de manera clara y suficiente.</p>
              <canvas class="signature-pad" data-pen-color="#202020"></canvas>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="paciente">Limpiar Firma</button>
              <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="paciente">Ampliar</button>
            </div>
            <input type="hidden" name="firma_paciente" id="FirmaPacienteData">
          </div>

          <!-- Contenedor para la firma de RECHAZO (Desistimiento) -->
          <div id="desistimiento-container" style="display: none;">
            <label class="form-label">Firma de Desistimiento Voluntario</label>
            <div id="firmaDesistimiento" class="signature-pad-container mb-2">
              <p class="consent-text text-danger">Me niego a recibir la atencion medica, traslado o internacion sugerida por el sistema de emergencia medica. Eximo de toda responsabilidad a <?= htmlspecialchars($empresa['nombre']) ?> de las consecuencias de mi decision, y asumo los riesgos que mi negativa pueda generar.</p>
              <canvas class="signature-pad" data-pen-color="#d32f2f"></canvas>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="desistimiento">Limpiar Firma</button>
              <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="desistimiento">Ampliar</button>
            </div>
            <input type="hidden" name="firma_desistimiento" id="FirmaDesistimientoData">
          </div>
        </div>

        <!-- Sub-seccion Firma Medico Receptor -->
        <div class="sub-section">
          <h5 class="sub-section-title">Firma del Medico que Recibe</h5>
          <div class="col-md-6">
            <label class="form-label">Medico Receptor</label>
            <div id="firmaMedicoReceptor" class="signature-pad-container mb-2">
              <canvas class="signature-pad" data-pen-color="#0e6b0e"></canvas>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-sm btn-danger" data-tipo="medicoReceptor">Limpiar Firma</button>
              <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="medicoReceptor">Ampliar</button>
            </div>
            <input type="hidden" name="firma_medico_receptor" id="FirmaMedicoReceptorData">
          </div>
        </div>

      </div>

  </div>        <!-- ADJUNTOS -->
  <div class="form-section adjuntos">
  <div class="section-header">
<h3>Adjuntos <span class="hl7-tag">FHIR: DocumentReference</span></h3>  </div>
  <div class="mb-6">
    <label for="adjuntos" class="form-label">Adjuntar archivos (imagenes o PDF) - Max 10</label>
    <input type="file" class="form-control" id="adjuntos" name="adjuntos[]" accept="image/*,application/pdf" multiple>
    <small class="text-muted">
      Puedes seleccionar varias imagenes desde tu galeria o tomar fotos directamente con la camara. Los archivos se subiran automaticamente.
    </small>
  </div>
  <div class="progress mt-3" id="progress-container" style="display: none;">
    <div class="progress-bar" id="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
	  </div>
  </div>

  <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="aceptacion" name="aceptacion">
          <label class="form-check-label" for="aceptacion">Revisé que la informacion proporcionada es correcta.</label>
  </div>


       <div class="d-grid">
        <button type="submit" class="btn btn-lg btn-primary">Registrar la atencion</button>
       </div><div id="contadorTiempo" class="mt-3 text-center" style="font-size: 1.2em;"></div>
       <input type="hidden" id="consent_type" name="consent_type" value=""
    </form>

    <!-- Modal para Firma en Pantalla Completa -->
    <div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="signatureModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="signatureModalLabel">Firma Ampliada</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body d-flex flex-column">
            <div id="modalSignaturePad" class="flex-grow-1 signature-pad-container" style="border: 1px solid #ccc; border-radius: 8px;">
              <canvas id="modalCanvas" class="signature-pad" style="width: 100%; height: 100%;" data-pen-color="#34446d"></canvas>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="clearSignature">Limpiar</button>
            <button type="button" class="btn btn-primary" id="saveSignature" data-bs-dismiss="modal">Guardar</button>
          </div>
        </div>
      </div>
  </div>

  </form>
  <script>
    (function(){
      function getCheckedNumber(name){
        var el = document.querySelector('input[name="'+name+'"]:checked');
        return el ? Number(el.value||0) : 0;
      }
      function calcDowntonTotal(){
        var sum = getCheckedNumber('downton_caidas')
                + getCheckedNumber('downton_mental')
                + getCheckedNumber('downton_medicamentos')
                + getCheckedNumber('downton_deficit')
                + getCheckedNumber('downton_deambulacion');
        var totalBox = document.getElementById('downton_total');
        if (totalBox && typeof totalBox.textContent !== 'undefined') totalBox.textContent = String(sum);
        var hidden = document.getElementById('downton_total_hidden');
        if (hidden) hidden.value = sum;
        var riesgo = document.getElementById('downton_riesgo');
        if (riesgo){
          riesgo.textContent = sum >= 3 ? 'Riesgo alto de caidas' : 'Riesgo bajo de caidas';
          riesgo.className = sum >= 3 ? 'mt-2 text-danger fw-bold' : 'mt-2 text-success fw-bold';
        }
      }
      document.addEventListener('change', function(e){
        if (e.target && e.target.classList && e.target.classList.contains('downton-check')) calcDowntonTotal();
      });
      function isTAMSelected(){
        var sel = document.getElementById('servicio');
        if (!sel) return false;
        var v = sel.value || '';
        return v === 'TAM' || /Medicalizado/i.test(v);
      }
      function toggleTAM(){
        var cont = document.getElementById('medico-tripulante-container') || document.getElementById('camposTAM');
        if (!cont) return;
        cont.style.display = isTAMSelected() ? '' : 'none';
      }
      var servicioSel = document.getElementById('servicio');
      if (servicioSel) servicioSel.addEventListener('change', toggleTAM);

      // Exclusion: Aceptacion vs Desistimiento
      function setConsentMode(mode){
        var accept = mode === 'ACEPTACION';
        var ctnA = document.getElementById('consentimiento-container');
        var ctnD = document.getElementById('desistimiento-container');
        var btnA = document.getElementById('btn-aceptar-atencion');
        var btnD = document.getElementById('btn-rechazar-atencion');
        var hiddenMode = document.getElementById('consent_type');
        if (hiddenMode) hiddenMode.value = mode;
        if (ctnA) ctnA.style.display = accept ? '' : 'none';
        if (ctnD) ctnD.style.display = accept ? 'none' : '';
        try {
          if (accept){
            if (window.padDesistimiento && window.padDesistimiento.clear) window.padDesistimiento.clear();
            var hD = document.getElementById('FirmaDesistimientoData'); if (hD) hD.value = '';
          } else {
            if (window.padPaciente && window.padPaciente.clear) window.padPaciente.clear();
            var hP = document.getElementById('FirmaPacienteData'); if (hP) hP.value = '';
          }
        } catch(e){}
        if (btnA){ btnA.classList.add('active'); if(btnD) btnD.classList.remove('active'); }
        if (btnD && !accept){ btnD.classList.add('active'); if(btnA) btnA.classList.remove('active'); }
      }
      var btnAceptar = document.getElementById('btn-aceptar-atencion');
      var btnRechazar = document.getElementById('btn-rechazar-atencion');
      if (btnAceptar) btnAceptar.addEventListener('click', function(){ setConsentMode('ACEPTACION'); });
      if (btnRechazar) btnRechazar.addEventListener('click', function(){ setConsentMode('DESISTIMIENTO'); });

      function toDataUrlOrEmpty(pad){
        try { return pad && !pad.isEmpty ? (pad.isEmpty() ? '' : pad.toDataURL()) : ''; } catch(e){ return ''; }
      }
      function getPadData(sel){
        try {
          var cv = document.querySelector(sel);
          var pad = cv && cv._pad ? cv._pad : null;
          return (pad && !pad.isEmpty()) ? pad.toDataURL('image/png') : '';
        } catch(e){ return ''; }
      }
      var form = document.getElementById('clinical-form');
      if (form){
        form.addEventListener('submit', function(e){
          try {
            if (window.padParamedico) document.getElementById('FirmaParamedicoData').value = toDataUrlOrEmpty(window.padParamedico);
            if (window.padMedico) document.getElementById('FirmaMedicoData').value = toDataUrlOrEmpty(window.padMedico);
            if (window.padPaciente) document.getElementById('FirmaPacienteData').value = toDataUrlOrEmpty(window.padPaciente);
            if (window.padDesistimiento) document.getElementById('FirmaDesistimientoData').value = toDataUrlOrEmpty(window.padDesistimiento);
            if (window.padMedicoReceptor) document.getElementById('FirmaMedicoReceptorData').value = toDataUrlOrEmpty(window.padMedicoReceptor);
          } catch(err) {}
          // Copiar desde canvas._pad a los inputs ocultos (si hay trazo)
          (function(){
            var v;
            v = getPadData('#firmaParamedico .signature-pad'); if (v) { var el=document.getElementById('FirmaParamedicoData'); if (el) el.value = v; }
            v = getPadData('#firmaMedico .signature-pad'); if (v) { var el2=document.getElementById('FirmaMedicoData'); if (el2) el2.value = v; }
            v = getPadData('#firmaPaciente .signature-pad'); if (v) { var el3=document.getElementById('FirmaPacienteData'); if (el3) el3.value = v; }
            v = getPadData('#firmaDesistimiento .signature-pad'); if (v) { var el4=document.getElementById('FirmaDesistimientoData'); if (el4) el4.value = v; }
            v = getPadData('#firmaMedicoReceptor .signature-pad'); if (v) { var el5=document.getElementById('FirmaMedicoReceptorData'); if (el5) el5.value = v; }
          })();
          var firmaTrip = (document.getElementById('FirmaParamedicoData') && document.getElementById('FirmaParamedicoData').value || '').trim();
          var firmaPac  = (document.getElementById('FirmaPacienteData') && document.getElementById('FirmaPacienteData').value || '').trim();
          var firmaDes  = (document.getElementById('FirmaDesistimientoData') && document.getElementById('FirmaDesistimientoData').value || '').trim();
          var modeElRef = document.getElementById('consent_type');
          var mode = (modeElRef && modeElRef.value || '').toUpperCase();
          if (!mode){
            if (firmaPac && !firmaDes) { mode = 'ACEPTACION'; if (modeElRef) modeElRef.value = mode; }
            else if (!firmaPac && firmaDes) { mode = 'DESISTIMIENTO'; if (modeElRef) modeElRef.value = mode; }
          }
          // Validacion adicional: firma del Medico Receptor obligatoria si hay ACEPTACIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN
          var firmaRec = (document.getElementById('FirmaMedicoReceptorData') && document.getElementById('FirmaMedicoReceptorData').value || '').trim();
          if (mode === 'ACEPTACION' && !firmaRec) {
            try {
              var cvR = document.querySelector('#firmaMedicoReceptor .signature-pad');
              var padR = cvR && cvR._pad ? cvR._pad : null;
              if (padR && !padR.isEmpty()) {
                var vR = padR.toDataURL('image/png');
                document.getElementById('FirmaMedicoReceptorData').value = vR;
                firmaRec = vR;
              }
            } catch(e){}
            if (!firmaRec) {
              e.preventDefault();
              alert('La firma del Medico Receptor es obligatoria cuando se acepta la atencion.');
              return;
            }
          }
          // Completar ocultos desde los canvas si estan vacios
          if (!firmaTrip){ var vT = getPadData('#firmaParamedico .signature-pad'); if (vT){ document.getElementById('FirmaParamedicoData').value = vT; firmaTrip = vT; } }
          if (mode === 'ACEPTACION'){
            if (!firmaPac){ var vP = getPadData('#firmaPaciente .signature-pad'); if (vP){ document.getElementById('FirmaPacienteData').value = vP; firmaPac = vP; } }
          } else if (mode === 'DESISTIMIENTO'){
            if (!firmaDes){ var vD = getPadData('#firmaDesistimiento .signature-pad'); if (vD){ document.getElementById('FirmaDesistimientoData').value = vD; firmaDes = vD; } }
          }
          if (mode === 'ACEPTACION'){
            var hD2 = document.getElementById('FirmaDesistimientoData'); if (hD2) hD2.value = '';
            firmaDes = '';
          } else if (mode === 'DESISTIMIENTO'){
            var hP2 = document.getElementById('FirmaPacienteData'); if (hP2) hP2.value = '';
            firmaPac = '';
          }
          if (!firmaTrip){
            e.preventDefault();
            alert('La firma del Tripulante es obligatoria.');
            return;
          }
          if (!firmaPac && !firmaDes){
            e.preventDefault();
            alert('Debes registrar la firma de Aceptacion o la de Desistimiento.');
            return;
          }
        });
      }
      document.addEventListener('DOMContentLoaded', function(){
        calcDowntonTotal();
        toggleTAM();
      });
    })();
  </script>
  <style>
    body {
      padding-bottom: 60px; /* Anade espacio para el footer fijo */
    }
    .imc-bajo { background-color: #e0f7fa; color: #00838f; border: 1px solid #00838f; }
    .imc-saludable { background-color: #c8e6c9; color: #388e3c; }
    .imc-sobrepeso { background-color: #fff9c4; color: #f9a825; border: 1px solid #f9a825; }
    .imc-obeso { background-color: #ffcdd2; color: #d32f2f; border: 1px solid #d32f2f; }
    .imc-obesidad-extrema { background-color: #e0b0ff; color: #6a0dad; border: 1px solid #6a0dad; }
    /* Estilos para validacion de signos vitales */
    .valor-normal { background-color: #c8e6c9; color: #388e3c; } /* Verde saludable */
    .valor-alerta { background-color: #fff9c4; color: #f9a825; border: 1px solid #f9a825; } /* Amarillo/Naranja advertencia */
    .valor-peligro { background-color: #ffcdd2; color: #d32f2f; border: 1px solid #d32f2f; } /* Rojo peligro */
  </style>
  
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      // Establecer el idioma espanol para moment.js globalmente
      moment.locale('es');
      const horaDespachoInput = document.getElementById('hora_despacho');
      const horaIngresoInput = document.getElementById('hora_ingreso');
      const horaFinalInput = document.getElementById('hora_final');
      const tiempoServicioDisplay = document.getElementById('tiempo_servicio_ocupado');

      function setTiempoServicio(valor) {
        if (tiempoServicioDisplay) {
          tiempoServicioDisplay.textContent = valor;
        }
      }

      function calcularTiempoServicio() {
        if (!horaDespachoInput || !horaFinalInput) {
          return;
        }
        const despacho = horaDespachoInput.value;
        const final = horaFinalInput.value;

        if (!despacho || !final) {
          setTiempoServicio('00:00');
          return;
        }

        const inicio = moment(despacho, "HH:mm");
        const fin = moment(final, "HH:mm");
        if (!inicio.isValid() || !fin.isValid()) {
          setTiempoServicio('00:00');
          return;
        }
        if (fin.isBefore(inicio)) {
          setTiempoServicio('Error');
          return;
        }
        const duracion = moment.duration(fin.diff(inicio));
        const horas = Math.floor(duracion.asHours());
        const minutos = duracion.minutes();
        setTiempoServicio(`${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`);
      }

      [horaDespachoInput, horaIngresoInput, horaFinalInput].forEach(function(input) {
        if (!input) return;
        input.addEventListener('change', calcularTiempoServicio);
        input.addEventListener('input', calcularTiempoServicio);
      });
      calcularTiempoServicio();

      // --- CONTADOR DE TIEMPO ---
    const contadorTiempo = document.getElementById("contadorTiempo");
    let segundos = 0;
    function actualizarContador() {
      const horas = Math.floor(segundos / 3600);
      const minutos = Math.floor((segundos % 3600) / 60);
      const segs = segundos % 60;
      contadorTiempo.textContent = `Tiempo transcurrido: ${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}:${segs.toString().padStart(2, '0')}`;
      segundos++;
    }
    // Actualizar el contador cada segundo
    setInterval(actualizarContador, 1000);

    // --- CONFIGURACIÓN DE FLATPICKR ---
    const timeConfig = {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 1
    };
    flatpickr("#fecha", { dateFormat: "d-m-Y", defaultDate: new Date(), locale: "es" });
    flatpickr("#fecha_nacimiento_display", {
        dateFormat: "d-m-Y",
        locale: "es",
        onChange: function(selectedDates, dateStr, instance) {
            const fechaNacimientoHidden = document.getElementById("fecha_nacimiento_hidden");
            const edadInput = document.getElementById("edad_paciente");
            if (dateStr) {
                const birthDate = moment(dateStr, "DD-MM-YYYY");
                fechaNacimientoHidden.value = birthDate.format("YYYY-MM-DD");
                const age = moment().diff(birthDate, 'years');
                edadInput.value = age >= 0 ? `${age} años` : '';
            } else {
                fechaNacimientoHidden.value = '';
                edadInput.value = '';
            }
        }
    });

    // --- LÓGICA DE TIEMPOS AUTOMÁTICOS ---
    const llegadaPicker = flatpickr("#hora_llegada", { ...timeConfig, locale: "es", onChange: calcularTiempoServicio });
const finalPicker   = flatpickr("#hora_final",   { ...timeConfig, locale: "es", onChange: calcularTiempoServicio });

flatpickr("#hora_despacho", {
  ...timeConfig,
  locale: "es",
  onChange: function(selectedDates, dateStr) {
    if (dateStr && typeof moment !== "undefined") {
      const llegadaTime = moment(dateStr, "HH:mm").add(Math.floor(Math.random() * 3) + 3, "minutes").format("HH:mm");
      llegadaPicker.setDate(llegadaTime, true);
    }
    calcularTiempoServicio();
  }
});

flatpickr("#hora_ingreso", {
  ...timeConfig,
  locale: "es",
  onChange: function(selectedDates, dateStr) {
    if (dateStr && typeof moment !== "undefined") {
      const finalTime = moment(dateStr, "HH:mm").add(Math.floor(Math.random() * 11) + 15, "minutes").format("HH:mm");
      finalPicker.setDate(finalTime, true);
    }
    calcularTiempoServicio();
  }
});

    // --- VALIDACIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN DE SIGNOS VITALES CON COLORES ---
    function configurarValidacionSignos() {
      // Funcion generica para valores numericos simples
      function validarSigno(input, rangos) {
        const valor = parseFloat(input.value);
        input.classList.remove('valor-normal', 'valor-alerta', 'valor-peligro');
        if (isNaN(valor) || input.value.trim() === '') return;

        if (valor < rangos.peligro_min || valor > rangos.peligro_max) {
          input.classList.add('valor-peligro');
        } else if (valor < rangos.normal_min || valor > rangos.normal_max) {
          input.classList.add('valor-alerta');
        } else {
          input.classList.add('valor-normal');
        }
      }

      // Frecuencia Cardiaca (FC)
      document.getElementById('frecuencia_cardiaca').addEventListener('input', function() {
        validarSigno(this, { normal_min: 60, normal_max: 90, peligro_min: 50, peligro_max: 110 });
      });

      // Frecuencia Respiratoria (FR)
      document.getElementById('frecuencia_respiratoria').addEventListener('input', function() {
        validarSigno(this, { normal_min: 12, normal_max: 20, peligro_min: 8, peligro_max: 24 });
      });

      // SpO2
      document.getElementById('spo2').addEventListener('input', function() {
        const valor = parseFloat(this.value);
        this.classList.remove('valor-normal', 'valor-alerta', 'valor-peligro');
        if (isNaN(valor) || this.value.trim() === '') return;

        if (valor >= 95 && valor <= 100) this.classList.add('valor-normal');
        else if (valor >= 90 && valor < 94) this.classList.add('valor-alerta');
        else if (valor < 93) this.classList.add('valor-peligro');
      });

      // Glucometria
      document.getElementById('glucometria').addEventListener('input', function() {
        validarSigno(this, { normal_min: 70, normal_max: 140, peligro_min: 60, peligro_max: 250 });
      });

      // Temperatura
      document.getElementById('temperatura').addEventListener('input', function() {
        validarSigno(this, { normal_min: 36.5, normal_max: 37.5, peligro_min: 35, peligro_max: 40 });
      });

      // Tension Arterial (TA)
      document.getElementById('tension_arterial').addEventListener('input', function() {
        this.classList.remove('valor-normal', 'valor-alerta', 'valor-peligro');
        const partes = this.value.split('/');
        if (partes.length !== 2) return;
        const sistolica = parseInt(partes[0], 10);
        const diastolica = parseInt(partes[1], 10);
        if (isNaN(sistolica) || isNaN(diastolica)) return;

        if (sistolica > 180 || diastolica > 120) this.classList.add('valor-peligro'); // Crisis hipertensiva
        else if (sistolica >= 90 && sistolica <= 139 && diastolica >= 60 && diastolica <= 89) this.classList.add('valor-normal');
        else this.classList.add('valor-alerta'); // Hipotension o Hipertension
      });
    }
    configurarValidacionSignos();

    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA SECCIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN ASEGURADORA (SOAT) ---
    const pagadorField = document.getElementById("pagador");
const datosSoatSection = document.getElementById("datos-soat");
const epsContainer = document.getElementById("eps-container");
function toggleDatosSoat() {
  if (!pagadorField || !datosSoatSection || !epsContainer) {
    return;
  }
  const selectedValue = pagadorField.value;
  const showSoat = selectedValue === "SOAT" || selectedValue === "ARL" || selectedValue === "EPS";
  datosSoatSection.style.display = showSoat ? "block" : "none";
  epsContainer.style.display = (selectedValue === "EPS") ? "flex" : "none";
      if (selectedValue !== "EPS" && document.getElementById('eps_nombre')) {
        document.getElementById('eps_nombre').value = '';
      }
      const placaGrp = document.getElementById('placa_paciente_group');
      if (placaGrp) placaGrp.style.display = (selectedValue === 'SOAT') ? 'block' : 'none';
    }
    if (pagadorField) {
      pagadorField.addEventListener("change", toggleDatosSoat);
      toggleDatosSoat();
    }

    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA SECCIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN PACIENTE (ETNIA Y OTROS) ---
    document.getElementById('etnia').addEventListener('change', function() {
      document.getElementById('especificar_otra').style.display = this.value === 'Otro' ? 'block' : 'none';
    });
    document.getElementById('escena_paciente').addEventListener('change', function() {
        document.getElementById('escena_paciente_otro').style.display = this.value === 'Otro' ? 'block' : 'none';
    });

    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA CÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂLCULO IMC ---
    const pesoInput = document.getElementById("peso");
    const tallaInput = document.getElementById("talla");
    const imcInput = document.getElementById("imc");
    const imcRiesgo = document.getElementById("imc-riesgo");
    function calcularIMC() {
        const peso = parseFloat(pesoInput.value);
        const talla = parseFloat(tallaInput.value) / 100;
        imcInput.className = 'form-control';
        imcRiesgo.className = 'badge';
        if (peso > 0 && talla > 0) {
            const imc = (peso / (talla * talla)).toFixed(2);
            imcInput.value = imc;
            if (imc < 18.5) { imcInput.classList.add("imc-bajo"); imcRiesgo.textContent = 'Bajo Peso'; imcRiesgo.classList.add('bg-info', 'text-dark'); }
            else if (imc <= 24.9) { imcInput.classList.add("imc-saludable"); imcRiesgo.textContent = 'Peso Saludable'; imcRiesgo.classList.add('bg-success'); }
            else if (imc <= 29.9) { imcInput.classList.add("imc-sobrepeso"); imcRiesgo.textContent = 'Sobrepeso'; imcRiesgo.classList.add('bg-warning', 'text-dark'); }
            else { imcInput.classList.add("imc-obeso"); imcRiesgo.textContent = 'Obesidad'; imcRiesgo.classList.add('bg-danger'); }
        } else { imcInput.value = ""; imcRiesgo.textContent = ''; }
    }
    pesoInput.addEventListener("input", calcularIMC);
    tallaInput.addEventListener("input", calcularIMC);

    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA ANTECEDENTES GINECOLÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICOS ---
    const generoField = document.getElementById('genero_nacer');
    const ginecoRow = document.getElementById('row_ant_ginecoobstetricos');
    function toggleGineco() {
      if (generoField.value === 'Masculino') {
        ginecoRow.style.display = 'none';
      } else {
        ginecoRow.style.display = 'flex';
      }
    }
    generoField.addEventListener('change', toggleGineco);
    toggleGineco(); // Estado inicial
    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA ANTECEDENTES "OTRO" ---
    document.querySelectorAll('.ant-cual-container select[multiple]').forEach(select => {
      const otroInput = select.parentElement.querySelector('.ant-otro-input');
      if (otroInput) {
        select.addEventListener('change', function() {
          const selectedOptions = Array.from(select.selectedOptions).map(opt => opt.value);
          otroInput.style.display = selectedOptions.includes('Otro') ? 'block' : 'none';
          if (!selectedOptions.includes('Otro')) otroInput.value = '';
        });
      }
    });
    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA ANTECEDENTES ---
    document.querySelectorAll('.ant-select').forEach(function(select) {
      const cualContainer = document.getElementById(select.id.replace('_sn', '_cual_container'));
      function toggleCualInput() {
        if (select.value === 'Si') {
          cualContainer.style.display = 'block';
        } else {
          cualContainer.style.display = 'none';
          // Resetear los valores de los inputs/selects dentro del contenedor
          const inputs = cualContainer.querySelectorAll('input, select');
          inputs.forEach(input => {
            if (input.type === 'text') {
              input.value = '';
            } else if (input.tagName === 'SELECT') {
              Array.from(input.options).forEach(option => option.selected = false);
            }
          });
        }
      }
      select.addEventListener('change', toggleCualInput);
      toggleCualInput(); // Estado inicial
    });
    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA ADJUNTOS (PROGRESS BAR) ---
    document.getElementById('adjuntos').addEventListener('change', function(event) {
      const progressContainer = document.getElementById('progress-container');
      const progressBar = document.getElementById('progress-bar');
      if (event.target.files.length > 0) {
        progressContainer.style.display = 'block';
        let progress = 0;
        const interval = setInterval(() => {
          progress += 10;
          progressBar.style.width = progress + '%';
          progressBar.setAttribute('aria-valuenow', progress);
          progressBar.textContent = progress + '%';
          if (progress >= 100) {
            clearInterval(interval);
            progressBar.style.width = '100%';
            progressBar.setAttribute('aria-valuenow', 100);
            progressBar.textContent = '100%';
          }
        }, 200);
      }
    });
    // --- VALIDACIÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN DEL FORMULARIO ---
    $('#clinical-form').on('submit', function(e) {
        e.preventDefault(); // Prevenimos el envio por defecto para validar con calma.
        let errores = [];

        // 1. Validar campos de texto y selectores basicos
        if ($('#tripulante_hidden').val().trim() === '') {
            errores.push('El campo Tripulante es obligatorio. Por favor, inicie sesion de nuevo si el problema persiste.');
        }

        // 2. Validar campos de hora
        ['#hora_despacho', '#hora_llegada', '#hora_ingreso', '#hora_final'].forEach(field => {
            if (!$(`${field}`).val()) {
                errores.push(`El campo de hora "${$(`label[for='${field.substring(1)}']`).text()}" es obligatorio.`);
            }
        });

        // 3. Validar firmas y campos condicionales
        const firmaTripulante = $('#FirmaParamedicoData').val();
        const firmaPaciente = $('#FirmaPacienteData').val();
        const firmaDesistimiento = $('#FirmaDesistimientoData').val();
        const firmaMedicoReceptor = $('#FirmaMedicoReceptorData').val();

        if (!firmaTripulante) {
            errores.push('La firma del Tripulante es obligatoria.');
        }

        if (!firmaPaciente && !firmaDesistimiento) {
            errores.push('Se requiere la firma de Aceptacion o de Desistimiento del paciente/representante.');
        }

        // Si se acepto la atencion (no hay desistimiento), validar los datos y firma del medico receptor.
        if (firmaPaciente && !firmaDesistimiento) {
            if (!$('#nombre_medico_receptor').val() || !$('#id_medico_receptor').val()) {
                errores.push('Los datos del Medico Receptor son obligatorios cuando se acepta la atencion.');
            }
            if (!firmaMedicoReceptor) {
                errores.push('La firma del Medico Receptor es obligatoria cuando se acepta la atencion.');
            }
        }

        // 4. Comprobar si hay errores y actuar
        if (errores.length > 0) {
            alert('Por favor, corrija los siguientes errores:\n\n- ' + errores.join('\n- '));
        } else {
            // Si no hay errores, limpiamos el autoguardado y enviamos el formulario.
            localStorage.removeItem('form_autosave_data');
            this.submit();
        }
    });

    // --- LÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œGICA DE AUTOGUARDADO EN LOCALSTORAGE ---
    const form = document.getElementById('clinical-form');
    const formId = 'form_autosave_data'; // ID unico para los datos de este formulario

    // Funcion para guardar el estado del formulario
    function saveFormState() {
        const formData = new FormData(form);
        const dataToStore = {};
        // No guardamos archivos, solo valores de texto/seleccionados
        for (const [key, value] of formData.entries()) {
            // Excluimos los campos de adjuntos y de datos de firma
            if (key !== 'adjuntos[]' && !key.startsWith('firma_')) {
                dataToStore[key] = value;
            }
        }
        localStorage.setItem(formId, JSON.stringify(dataToStore));
    }

    // Funcion para restaurar el estado del formulario
    function restoreFormState() {
        const savedData = localStorage.getItem(formId);
        if (savedData) {
            const data = JSON.parse(savedData);
            for (const key in data) {
                const element = form.elements[key];
                if (element) {
                    // Manejar radio buttons y checkboxes
                    if (element.type === 'radio' || element.type === 'checkbox') {
                        // Para grupos de radio, necesitamos encontrar el correcto
                        const matchingElement = document.querySelector(`[name="${key}"][value="${data[key]}"]`);
                        if (matchingElement) matchingElement.checked = true;
                    } else {
                        element.value = data[key];
                    }
                }
            }
            // Disparar eventos 'change' para que otras logicas (como la de antecedentes) se actualicen
            // Usamos un pequeno retraso para asegurar que todos los elementos esten listos
            setTimeout(() => {
                $(form).find('select, input[type!=radio][type!=checkbox]').trigger('change');
            }, 100);
        }
    }

    // Guardar el estado en cada cambio
    form.addEventListener('input', saveFormState);
    form.addEventListener('change', saveFormState); // Para selects y checkboxes

    // Restaurar el estado al cargar la pagina
    restoreFormState();

    // --- CÃƒÆ’Ã†â€™Ãƒâ€šÃ‚ÂLCULO ESCALA DE GLASGOW ---
    const inputs = document.querySelectorAll(".glasgow-check");
    const totalInput = document.getElementById("escala_glasgow");
    function calcularTotalGlasgow() {
      let total = 0;
      document.querySelectorAll(".glasgow-check:checked").forEach(input => {
        total += parseInt(input.value);
      });
      totalInput.value = total || "";
    }
    inputs.forEach(input => {
      input.addEventListener("change", calcularTotalGlasgow);
    });
    calcularTotalGlasgow(); // Estado inicial
// --- MANEJO DE FIRMAS + TAB/TAM + MEDS (version robusta) ---
(function(){
  // Helpers
  function resolvePenColor(canvas){
    if (!canvas) return '#34446d';
    const attr = (canvas.getAttribute('data-pen-color') || '').trim();
    if (attr) return attr;
    const cssVar = (getComputedStyle(canvas).getPropertyValue('--signature-color') || '').trim();
    if (cssVar) return cssVar;
    const parent = canvas.parentElement;
    if (parent) {
      const parentAttr = (parent.getAttribute('data-pen-color') || '').trim();
      if (parentAttr) return parentAttr;
      const parentVar = (getComputedStyle(parent).getPropertyValue('--signature-color') || '').trim();
      if (parentVar) return parentVar;
    }
    return '#34446d';
  }
  function ensurePad(sel){
    const canvas = document.querySelector(sel);
    if (!canvas) return null;
    const color = resolvePenColor(canvas);
    if (canvas._pad) { // already exists: just resize
      const data = canvas._pad.isEmpty() ? null : canvas._pad.toData();
      resizeCanvasToCSS(canvas);
      canvas._pad.clear();
      if (data) { try { canvas._pad.fromData(data); } catch {} }
      if (color) { canvas._pad.penColor = color; }
      return canvas._pad;
    }
    if (!canvas.style.height) canvas.style.height = '220px';
    resizeCanvasToCSS(canvas);
    try {
      const pad = new SignaturePad(canvas, { penColor: color, minWidth:0.5, maxWidth:1.5 });
      canvas._pad = pad;
      return pad;
    } catch(e){ return null; }
  }
  function resizeCanvasToCSS(canvas) {
    if (!canvas) return;
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const rect = canvas.getBoundingClientRect();
    const w = Math.max(1, Math.floor(rect.width * ratio));
    const h = Math.max(1, Math.floor(rect.height * ratio));
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    const ctx = canvas.getContext('2d');
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  }

  function fitCanvasToContainer(canvas, container, targetRatio){
    if (!canvas || !container) return;
    const rect = container.getBoundingClientRect();
    const maxW = Math.max(1, Math.floor(rect.width));
    const maxH = Math.max(1, Math.floor(rect.height));
    let w = maxW;
    let h = Math.round(w / targetRatio);
    if (h > maxH) { h = maxH; w = Math.round(h * targetRatio); }
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    resizeCanvasToCSS(canvas);
  }

  function setVisible(el, on){ if(el) el.style.display = on ? '' : 'none'; }
  function norm(t){ return (t||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase(); }
  function hidId(tipo){ return 'Firma' + tipo.charAt(0).toUpperCase() + tipo.slice(1) + 'Data'; }

  // Mapeo unico de pads
  const mapping = {
    paramedico:      '#firmaParamedico .signature-pad',
    medico:          '#firmaMedico .signature-pad',
    paciente:        '#firmaPaciente .signature-pad',
    desistimiento:   '#firmaDesistimiento .signature-pad',
    medicoReceptor:  '#firmaMedicoReceptor .signature-pad'
  };

  // Inicializar pads pequenos
  const firmas = {};
  Object.entries(mapping).forEach(([key, sel]) => {
    const canvas = document.querySelector(sel);
    if (!canvas) return;
    // Forzar altura CSS razonable si no existe
    if (!canvas.style.height) canvas.style.height = '220px';
    resizeCanvasToCSS(canvas);
    const pad = new SignaturePad(canvas, { penColor:'#34446d', minWidth:0.5, maxWidth:1.5 });
    canvas._pad = pad;
    firmas[key] = pad;
  });

  function updateHidden(tipo){
    const pad = firmas[tipo];
    const hid = document.getElementById(hidId(tipo));
    if (hid) hid.value = pad && !pad.isEmpty() ? pad.toDataURL('image/png') : '';
  }
  function clearPad(tipo){
    const pad = firmas[tipo];
    if (!pad) return;
    pad.clear();
    updateHidden(tipo);
  }

  // Delegacion: botones "Limpiar Firma" de cada tarjeta
  document.body.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    if (btn.matches('[data-tipo]') && /limpiar/i.test(btn.textContent)) {
      clearPad(btn.getAttribute('data-tipo'));
    }
  });

  // Modal unificado
  const modalEl = document.getElementById('signatureModal');
  const modalCanvas = document.getElementById('modalCanvas');
  let modalPad = null, currentTipo = null;

  if (modalEl && modalCanvas && typeof SignaturePad !== 'undefined') {
    modalEl.addEventListener('shown.bs.modal', (ev) => {
      currentTipo = ev.relatedTarget ? ev.relatedTarget.getAttribute('data-sig-target') : null;
      if (!modalPad) modalPad = new SignaturePad(modalCanvas, { penColor:'#000' });
      resizeCanvasToCSS(modalCanvas);
      modalPad.clear();
      const smallSel = currentTipo && mapping[currentTipo] ? mapping[currentTipo] : null;
      const small = smallSel ? document.querySelector(smallSel) : null;
      if (small && small._pad && !small._pad.isEmpty()) {
        try { modalPad.fromData(small._pad.toData()); } catch {}
      }
    });

    modalEl.addEventListener('shown.bs.modal', () => {
      // Reajustar de nuevo por si Bootstrap termino la animacion y cambio tamano
      setTimeout(() => { resizeCanvasToCSS(modalCanvas); }, 100);
    });

    const btnClear = document.getElementById('clearSignature');
    const btnSave  = document.getElementById('saveSignature');
    if (btnClear) btnClear.onclick = () => modalPad && modalPad.clear();
    if (btnSave)  btnSave.onclick  = () => {
      if (!modalPad || !currentTipo) return;
      const smallSel = mapping[currentTipo];
      const small = smallSel ? document.querySelector(smallSel) : null;
      if (!small || !small._pad) return;
      const data = modalPad.isEmpty() ? null : modalPad.toData();
      small._pad.clear();
      if (data) { try { small._pad.fromData(data); } catch {} }
      updateHidden(currentTipo);
    };
  }

  // Mantener relacion al redimensionar
  window.addEventListener('resize', () => {
    Object.values(mapping).forEach(sel => {
      const cv = document.querySelector(sel);
      if (!cv || !cv._pad) return;
      const data = cv._pad.isEmpty() ? null : cv._pad.toData();
      resizeCanvasToCSS(cv);
      cv._pad.clear();
      if (data) { try { cv._pad.fromData(data); } catch {} }
    });
    if (modalCanvas && modalPad) {
      const data = modalPad.isEmpty() ? null : modalPad.toData();
      resizeCanvasToCSS(modalCanvas);
      modalPad.clear();
      if (data) { try { modalPad.fromData(data); } catch {} }
    }
  });

  // TAB/TAM: mostrar/ocultar contenedores TAM
  const servicioSel = document.getElementById('servicio');
  const tabtamToggle = document.getElementById('tabtamToggle');
  const tabtamButtons = tabtamToggle ? Array.from(tabtamToggle.querySelectorAll('.btn-tabtam')) : [];

  function setTabTamActive(value) {
    const normalized = (value || '').toLowerCase();
    tabtamButtons.forEach(btn => {
      const isActive = (btn.dataset.value || '').toLowerCase() === normalized;
      btn.classList.toggle('active', isActive);
      btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function syncTabTam(value, triggerChange = false) {
    if (!servicioSel) return;
    const options = Array.from(servicioSel.options || []);
    const match = options.find(opt => opt.value === value);
    if (match) {
      servicioSel.value = match.value;
    } else if (options.length && !options.find(opt => opt.value === servicioSel.value)) {
      servicioSel.selectedIndex = 0;
    }
    setTabTamActive(servicioSel.value);
    if (triggerChange) {
      servicioSel.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  if (servicioSel && tabtamButtons.length) {
    tabtamButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const targetValue = btn.dataset.value || '';
        syncTabTam(targetValue || servicioSel.value, true);
      });
    });
    const opts = servicioSel.options || [];
    let initialValue = servicioSel.value;
    if ((!initialValue || !initialValue.length) && opts.length) {
      const idx = servicioSel.selectedIndex >= 0 ? servicioSel.selectedIndex : 0;
      initialValue = (opts[idx] && opts[idx].value) ? opts[idx].value : '';
    }
    syncTabTam(initialValue || '', false);
  }

  if (servicioSel) {
    servicioSel.addEventListener('change', () => setTabTamActive(servicioSel.value));
  }
  const medicoTripFirma = document.getElementById('medico-tripulante-firma-container');
  const medicoTripDatos = document.getElementById('medico-tripulante-container');
  const medsContainer   = document.getElementById('medicamentos-aplicados-container');
  function actualizarTABTAM(){
    if (!servicioSel) return;
    const txt = (servicioSel.options[servicioSel.selectedIndex]?.text || '') + ' ' + (servicioSel.value || '');
    const v = norm(txt);
    const isTAM = v.includes('medicalizado') || v.includes('(tam)');
    setVisible(medicoTripFirma, isTAM);`r`n    var downton = document.getElementById("downton-container");`r`n    setVisible(downton, isTAM);
    setVisible(medicoTripDatos, isTAM);
    setVisible(medsContainer,   isTAM);
    if (isTAM) { ensurePad('#firmaMedico .signature-pad'); }
  }
  if (servicioSel) {
    servicioSel.addEventListener('change', actualizarTABTAM);
    actualizarTABTAM();
  }

  // Botones grandes Aceptar / Rechazar
  const btnAceptar  = document.getElementById('btn-aceptar-atencion');
  const btnRechazar = document.getElementById('btn-rechazar-atencion');
  const contConsent = document.getElementById('consentimiento-container');
  const contDesist  = document.getElementById('desistimiento-container');
  function clickAceptar(){
    setVisible(contConsent, true);
    setVisible(contDesist,  false);
    ensurePad('#firmaPaciente .signature-pad');
    if (firmas.desistimiento) clearPad('desistimiento');
    updateHidden('paciente');
  }
  function clickRechazar(){
    setVisible(contConsent, false);
    setVisible(contDesist,  true);
    ensurePad('#firmaDesistimiento .signature-pad');
    if (firmas.paciente) clearPad('paciente');
    if (firmas.medicoReceptor) clearPad('medicoReceptor');
    updateHidden('desistimiento');
  }
  if (btnAceptar)  btnAceptar.addEventListener('click', clickAceptar);
  if (btnRechazar) btnRechazar.addEventListener('click', clickRechazar);

  // Medicamentos (agregar/remover + hora con flatpickr)
  const medsList = document.getElementById('lista-medicamentos');
  const medsTpl  = document.getElementById('medicamento-template');
  const btnAdd   = document.getElementById('btn-agregar-medicamento');
  function initMedHora(input){
    if (!input) return;
    try {
      flatpickr(input, { enableTime:true, noCalendar:true, dateFormat:"H:i", time_24hr:true, minuteIncrement:1, locale:"es" });
    } catch {}
  }
  function addMedicamento(){
    if (!medsTpl || !medsList) return;
    const node = medsTpl.content.cloneNode(true);
    medsList.appendChild(node);
    // Inicializar el/los time pickers recien insertados
    medsList.querySelectorAll('.medicamento-hora-input').forEach(initMedHora);
  }
  if (btnAdd) btnAdd.addEventListener('click', addMedicamento);
  // Delegacion para remover
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-remover-medicamento');
    if (!btn) return;
    const item = btn.closest('.medicamento-item');
    if (item) item.remove();
  });

})(); // fin wrapper
  
  // Keep modal signature aspect ratio in sync with inline pads
  (function(){
    const modal = document.getElementById('signatureModal');
    const modalBody = modal ? modal.querySelector('.modal-body .signature-pad-container') : null;
    const modalCanvas = document.getElementById('modalCanvas');
    const baseW = 500, baseH = 220; // match inline pad aspect
    const targetRatio = baseW / baseH;
    function layoutModalCanvas(){
      if (!modal || !modalCanvas || !modalBody) return;
      fitCanvasToContainer(modalCanvas, modalBody, targetRatio);
      ensurePad('#modalCanvas.signature-pad');
    }
    if (modal) {
      modal.addEventListener('shown.bs.modal', layoutModalCanvas);
      window.addEventListener('resize', function(){ if (modal.classList.contains('show')) layoutModalCanvas(); }, {passive:true});
    }
  })();

}); // Fin de DOMContentLoaded

</script>
<!-- Overrides: CIE-10 gating (solo TAM) y IPS con datos locales -->
<script>
(function(){
  function updateCIE10Visibility(){
    try {
      var sel = document.getElementById('servicio');
      var isTAM = false;
      if (sel) { var v = (sel.value||''); isTAM = (v === 'TAM' || /Medicalizado/i.test(v)); }
      var $cont = window.jQuery ? jQuery('#diagnostico-container') : null;
      var $cie  = window.jQuery ? jQuery('#diagnostico_principal') : null;
      if ($cont && $cont.length) $cont.toggle(isTAM);
      if (!$cie || !$cie.length || !window.jQuery || !jQuery.fn.select2) return;
      if (isTAM) {
        if (!$cie.hasClass('select2-hidden-accessible')) {
          var $dp = $cont && $cont.length ? $cont : jQuery('body');
          $cie.select2({
            width: '100%',
            dropdownParent: $dp,
            placeholder: 'Buscar diagnostico por codigo o nombre...',
            minimumInputLength: 3,
            language: {
              inputTooShort: function () { return 'Ingrese 3 o mas caracteres'; },
              noResults: function () { return 'No se encontraron resultados'; },
              searching: function () { return 'Buscando...'; },
              errorLoading: function () { return 'Error cargando resultados'; }
            },
            ajax: {
              url: (window.AppBaseUrl || '') + 'buscar_cie10.php',
              dataType: 'json',
              delay: 250,
              data: function (params) { return { q: params.term }; },
              processResults: function (data) { return { results: Array.isArray(data) ? data : [] }; },
              cache: true
            },
            escapeMarkup: function (m) { return m; },
            templateResult: function (repo) { if (repo.loading) return repo.text; return '<strong>' + (repo.id || '') + '</strong>: ' + (repo.text || ''); },
            templateSelection: function (repo) { if (!repo || !repo.id) return repo.text || ''; return (repo.id || '') + ' - ' + (repo.text || ''); }
          });
        }
      } else {
        if ($cie.hasClass('select2-hidden-accessible')) { try { $cie.val(null).trigger('change'); $cie.select2('destroy'); } catch(e){} }
      }
    } catch(e) { try { console.warn('CIE-10 gating error', e); } catch(_){} }
  }

  function initIPSLocal(){
    try {
      if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
      var $ips = jQuery('#ips'); if (!$ips.length) return;
      if ($ips.hasClass('select2-hidden-accessible')) { try { $ips.select2('destroy'); } catch(e){} }
      var $drop = $ips.closest('.form-container');
      function norm(s){
        try {
          var t = (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
          // Remove all non letters/digits (including spaces and punctuation) for robust matching
          try { t = t.replace(/[^\p{L}\p{N}]+/gu,''); } catch(_) { t = t.replace(/[^A-Za-z0-9]+/g,''); }
          return t.toLowerCase();
        } catch(e){
          return (s||'').toString().toLowerCase().replace(/[^a-z0-9]+/g,'');
        }
      }
      $ips.select2({
        width: '100%',
        dropdownParent: $drop.length ? $drop : jQuery('body'),
        placeholder: 'Buscar IPS por nombre o NIT',
        minimumInputLength: 2,
        allowClear: true,
        language: {
          inputTooShort: function () { return 'Ingrese 2 o mas caracteres'; },
          noResults: function () { return 'No se encontraron resultados'; },
          searching: function () { return 'Buscando...'; },
          errorLoading: function () { return 'Error cargando resultados'; }
        },
        data: (Array.isArray(window.IPS_DATA) ? window.IPS_DATA : []),
        matcher: function(params, data){
          if (jQuery.trim(params.term) === '') return data;
          var q = norm(params.term);
          var nom = norm(data.nombre || data.text || '');
          var nit = norm(data.nit || data.id || '');
          var city = norm(data.ciudad || '');
          return (q && (nom.indexOf(q) > -1 || nit.indexOf(q) > -1 || city.indexOf(q) > -1)) ? data : null;
        },
        escapeMarkup: function (m) { return m; },
        templateResult: function (item) {
          if (item.loading) return item.text;
          var nit = item.nit || item.id || '';
          var nom = item.nombre || item.text || '';
          var city = item.ciudad ? ' - ' + item.ciudad : '';
          return '<strong>' + nom + '</strong> (NIT: ' + nit + ')' + city;
        },
        templateSelection: function (item) {
          if (!item) return '';
          var nit = item.nit || item.id || '';
          var nom = item.nombre || item.text || '';
          return nit && nom ? (nom + ' - ' + nit) : (item.text || '');
        }
      });
      $ips.on('select2:select', function (e) {
        var data = e.params.data || {};
        jQuery('#ips_nit').val(data.nit || data.id || '');
        jQuery('#ips_nombre').val(data.nombre || data.text || '');
        jQuery('#ips_nit_display').val(data.nit || data.id || '');
        jQuery('#ips_ciudad').val(data.ciudad || '');
        jQuery('#ips_ciudad_display').val(data.ciudad || '');
      });
      $ips.on('select2:clear', function () {
        jQuery('#ips_nit, #ips_nombre, #ips_ciudad').val('');
        jQuery('#ips_nit_display, #ips_ciudad_display').val('');
      });
    } catch(e) { try { console.warn('IPS local init error', e); } catch(_){} }
  }

  function initMunicipio($display, $codigo) {
    try {
      if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
      if (!$display || !$display.length) return;
      if ($display.hasClass('select2-hidden-accessible')) {
        try { $display.select2('destroy'); } catch (err) {}
      }
      $display.select2({
        width: '100%',
        placeholder: 'Buscar municipio por nombre',
        minimumInputLength: 3,
        allowClear: true,
        language: {
          inputTooShort: function () { return 'Ingrese 3 o mas caracteres'; },
          noResults: function () { return 'No se encontraron resultados'; },
          searching: function () { return 'Buscando...'; },
          errorLoading: function () { return 'Error cargando resultados'; }
        },
        ajax: {
          url: (window.AppBaseUrl || '') + 'buscar_municipio.php',
          dataType: 'json',
          delay: 250,
          data: function (params) { return { q: params.term }; },
          processResults: function (data) { return { results: (data && data.results) ? data.results : [] }; },
          cache: true
        },
        escapeMarkup: function (markup) { return markup; },
        templateResult: function (item) {
          if (item.loading) return item.text;
          return '<strong>' + (item.id || '') + '</strong>: ' + (item.text || '');
        },
        templateSelection: function (item) {
          if (!item) return '';
          if (!item.id) return item.text || '';
          return (item.id || '') + ' - ' + (item.text || '');
        }
      });
      $display.off('select2:select.municipio').on('select2:select.municipio', function (e) {
        var data = (e && e.params && e.params.data) ? e.params.data : {};
        if ($codigo && $codigo.length) { $codigo.val(data.id || ''); }
      });
      $display.off('select2:clear.municipio').on('select2:clear.municipio', function () {
        if ($codigo && $codigo.length) { $codigo.val(''); }
      });
    } catch (e) {
      try { console.warn('Municipio init error', e); } catch (_) {}
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    updateCIE10Visibility();
    var svc = document.getElementById('servicio'); if (svc) svc.addEventListener('change', updateCIE10Visibility);
    initIPSLocal();
    if (window.jQuery) {
      var $municipio = jQuery('#municipio');
      var $codigoMunicipio = jQuery('#codigo_municipio');
      initMunicipio($municipio, $codigoMunicipio);
    }
  });
})();
</script>

<?php include 'footer.php'; ?>


