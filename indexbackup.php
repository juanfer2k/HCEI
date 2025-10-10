<?php
require_once 'access_control.php'; // Proteger esta página
require_once('conn.php');

// --- Si llegamos aquí, el usuario ESTÁ AUTENTICADO ---

// --- CONTROL DE ACCESO POR ROL ---
if ($_SESSION['usuario_rol'] === 'Administrativo') {
    // Si el rol es Administrativo, no puede crear registros. Redirigir a la consulta.
    $_SESSION['message'] = '<div class="alert alert-info">Tu rol solo permite consultar registros.</div>';
    header('Location: consulta_atenciones.php');
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
// pero hay un usuario en sesión, usamos los datos de la sesión.
if (empty($tripulante_data) && !empty($_SESSION['usuario_nombre'])) {
    $tripulante_data = [
        'nombres' => $_SESSION['usuario_nombre'],
        'apellidos' => $_SESSION['usuario_apellidos'] ?? '', // Asumiendo que estos datos se guardan en sesión
        'id_cc' => $_SESSION['usuario_cc'] ?? '',
        'id_registro' => $_SESSION['usuario_registro'] ?? ''
    ];
}

// --- CÁLCULO DEL NÚMERO DE REGISTRO CONSECUTIVO ---
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

include 'header.php'; // Incluimos el header ANTES de cualquier salida HTML
?>

<style type="text/css">
.text-muted {
}
</style>
<style>
  /* Estilos dinámicos para las secciones del formulario, inspirados en el entorno urbano y de emergencias */
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
  /* Paleta de colores con gradientes para dar más vida y separar visualmente las etapas del registro */
  .form-section.internal { background-image: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); } /* Gris claro - Profesional y clínico */
  .form-section.insurance { background-image: linear-gradient(135deg, #f1f8e9 0%, #dcedc8 100%); } /* Verde claro - Trámite, papeleo */
  .form-section.patient { background-image: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); } /* Púrpura suave - Centrado en el paciente */
  .form-section.context { background-image: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); } /* Naranja suave - Contexto de la emergencia */
  .form-section.clinical { background-image: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%); } /* Verde azulado - Datos médicos */
  .form-section.glasgow { background-image: linear-gradient(135deg, #fffde7 0%, #fff59d 100%); } /* Amarillo - Alerta y evaluación crítica */
  .form-section.antecedentes { background-image: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%); } /* Rosa suave - Historial médico */
  .form-section.oxigeno { background-image: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); } /* Azul claro para Oxigenoterapia */
  .form-section.firmas { background-image: linear-gradient(135deg, #eceff1 0%, #cfd8dc 100%); } /* Gris - Formalidad y legalidad */
  .form-section.adjuntos { background-image: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); } /* Gris claro - Neutral */
  .form-section label { font-weight: 500; }
  .form-section .form-control, .form-section .form-select { min-width: 100%; }
  .section-header { background: none; border-bottom: 2px solid #34446d; margin-bottom: 10px; padding-bottom: 8px; }
  .section-header { background: none; border-bottom: 2px solid #34446d; margin-bottom: 10px; padding-top: 5px; padding-bottom: 5px; }
  .section-header h3 {
    font-size: 1rem; /* Tamaño de fuente más pequeño */
    margin-bottom: 0; /* Eliminar margen inferior del h3 */
  }
  .form-container h2 { font-size: 1rem; }
  .select2-container .select2-selection--single { height: 38px !important; }
  .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
  .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
  .signature-pad-container {
    max-width: 500px; /* Ancho máximo para el contenedor de la firma */
    margin-left: auto;
    margin-right: auto; }
</style>
<style>
  /* Estilo Neumórfico para inputs, textareas y selects */
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

          <h3>Información Interna <span class="hl7-tag">HL7: PV1</span> <span class="hl7-tag">FHIR: Encounter</span></h3>
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
          <div class="col-md-4">
            <label for="quien_informo" class="form-label">¿Quién informó el servicio?</label>
            <select class="form-select" id="quien_informo" name="quien_informo">
              <option value="Central">Central</option>
              <option value="Turno">Turno</option>
              <option value="PONAL">PONAL</option>
              <option value="Tránsito">Tránsito</option>
              <option value="Comunidad">Comunidad</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="servicio" class="form-label">Servicio Prestado</label>
            <select class="form-select" id="servicio" name="servicio">
              <option value="Traslado Básico" selected>Traslado Básico (TAB)</option>
              <option value="Traslado Medicalizado">Traslado Medicalizado (TAM)</option>
              <option value="Atención Prehospitalaria" >Atención Prehospitalaria</option>            </select>
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
            <label for="cc_tripulante" class="form-label">Número ID</label>
            <input type="text" class="form-control" id="cc_tripulante" name="cc_tripulante" value="<?= htmlspecialchars(!empty($tripulante_data['id_registro']) ? $tripulante_data['id_registro'] : ($tripulante_data['id_cc'] ?? '')) ?>">
          </div>
        </div>
        <div class="row mb-2" id="medico-tripulante-container" style="display: none;">
          <div class="col-md-6">
            <label for="medico_tripulante" class="form-label">Médico Tripulante</label>
            <input type="text" class="form-control" id="medico_tripulante" name="medico_tripulante">
          </div>
          <div class="col-md-3">
            <label for="tipo_id_medico" class="form-label">Tipo ID Médico</label>
            <select class="form-select" id="tipo_id_medico" name="tipo_id_medico">
                <option value="Registro Médico" selected>Registro Médico</option>
                <option value="CC">CC</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="cc_medico" class="form-label">Número ID</label>
            <input type="text" class="form-control" id="cc_medico" name="cc_medico">
          </div>
        </div>
<div class="row mb-2">
  <div class="col-md-12">
    <label for="direccion_servicio" class="form-label">Dirección del Servicio/Atención</label>
    <div class="input-group">
      <input type="text" class="form-control" id="direccion_servicio" name="direccion_servicio" placeholder="Dirección del Servicio/Atención">
      
    </div>
  </div>
</div>
<div class="row mb-2">
  <div class="col-md-3">
    <label for="localizacion" class="form-label">Localización del Evento</label>
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
    <label for="ips_destino" class="form-label">IPS de Destino</label>
    <input type="text" class="form-control" id="ips_destino" name="ips_destino">
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
              <option value="">Seleccione una EPS...</option>
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
        <h3>Identificación del Paciente <span class="hl7-tag">HL7: PID</span> <span class="hl7-tag">FHIR: Patient</span></h3>
        </div>
        <div class="row mb-2">
          <div class="col-md-6">
            <label for="nombres_paciente" class="form-label">Nombres del Paciente</label>
            <input type="text" class="form-control" id="nombres_paciente" name="nombres_paciente">
          </div>
          <div class="col-md-6">
            <label for="tipo_identificacion" class="form-label">Tipo de Identificación</label>
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
            <label for="id_paciente" class="form-label">Número de Identificación</label>
            <input type="text" class="form-control" id="id_paciente" name="id_paciente">
          </div>
          <div class="col-md-4">
            <label for="genero_nacer" class="form-label">Género al Nacer</label>
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
            <label for="direccion_domicilio" class="form-label">Dirección Domicilio</label>
            <input type="text" class="form-control" id="direccion_domicilio" name="direccion_domicilio">
          </div>
          <div class="col-md-4">
            <label for="barrio_paciente" class="form-label">Barrio del Paciente</label>
            <input type="text" class="form-control" id="barrio_paciente" name="barrio_paciente">
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="ciudad" class="form-label">Ciudad</label>
            <input type="text" class="form-control" id="ciudad" name="ciudad">
          </div>
                    <div class="col-md-4">
            <label for="telefono_paciente" class="form-label">Teléfono del Paciente</label>
            <input type="tel" class="form-control" id="telefono_paciente" name="telefono_paciente">
          </div>
        </div>
        <!-- Aquí van los 3 campos solicitados de at diferencial -->
        <div class="row mb-2">
          <div class="col-md-4">
            <label for="atencion_en" class="form-label">Atención en</label>
            <select class="form-select" id="atencion_en" name="atencion_en" required>
              <option value="Via Pública">Vía Pública</option>
              <option value="Evento">Evento</option>
              <option value="Centro Hospitalario">Centro Hospitalario</option>
              <option value="Plantel Educativo">Plantel Educativo</option>
              <option value="Trabajo">Trabajo</option>
              <option value="Residencia">Residencia</option>
              <option value="Ciclovía">Ciclovía</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="etnia" class="form-label">Enfoque Diferencial: Pertenencia Étnica</label>
            <select class="form-select" id="etnia" name="etnia" required>
              <option value="Otro">Otro (especificar)</option>
              <option value="Indígena">Indígena</option>
              <option value="Afrocolombiano">Afrocolombiano (incluye afrodescendientes, negros, mulatos, palenqueros)</option>
              <option value="Raizal">Raizal del Archipiélago de San Andrés y Providencia</option>
              <option value="Rom">Rom (Gitano)</option>
              <option value="Mestizo">Mestizo</option>
              
            </select>
            <input type="text" class="form-control mt-2" id="especificar_otra" name="especificar_otra" placeholder="Si seleccionaste 'Otro', especifica aquí" style="display:none;">
          </div>
          <div class="col-md-4">
            <label for="discapacidad" class="form-label">Discapacidad</label>
            <select class="form-select" id="discapacidad" name="discapacidad">
              <option value="Ninguna">Ninguna</option>
              <option value="Física">Física</option>
              <option value="Auditiva">Auditiva</option>
              <option value="Visual">Visual</option>
              <option value="Sordoceguera">Sordoceguera</option>
              <option value="Intelectual">Intelectual</option>
              <option value="Psicosocial">Psicosocial (mental)</option>
              <option value="Múltiple">Múltiple</option>
            </select>
          </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-12">
                <label for="escena_paciente" class="form-label">Situación del Paciente en la Escena</label>
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
                <input type="text" class="form-control mt-2" id="escena_paciente_otro" name="escena_paciente_otro" placeholder="Si seleccionaste 'Otro', especifica aquí" style="display:none;">
            </div>
        </div>
      </div>
      <!-- ESCALA DE DOWNTON -->
      <div class="form-container form-section clinical" id="downton-container" style="display: none;">
        <div class="section-header">
          <h3>Escala de Riesgo de Caídas (Downton)</h3>
        </div>
        <input type="hidden" name="downton_total" id="downton_total">
        <div class="row">
          <!-- Columna 1 -->
          <div class="col-md-3">
            <h5>Caídas Previas</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_caidas" value="0" checked> No (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_caidas" value="1"> Sí (1)</label></div>
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
            <h5>Déficits Sensoriales</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="0" checked> Ninguno (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="1"> Alteraciones visuales (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="1"> Alteraciones auditivas (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deficit" value="1"> Extremidades (ej. ACV) (1)</label></div>
            <h5 class="mt-3">Deambulación</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="0" checked> Normal (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="1"> Segura con ayuda (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="1"> Insegura con/sin ayuda (1)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_deambulacion" value="1"> Imposible (1)</label></div>
          </div>
        </div>
        <div class="text-center mt-3">
          <label class="form-label" style="font-weight: bold;">Total Escala Downton:</label>
          <input type="hidden" name="downton_total" id="downton_total_hidden">
          <input type="text" class="form-control text-center w-50 mx-auto" id="downton_resultado" readonly style="font-size: 1.5rem; font-weight: bold;">
          <p id="downton_riesgo" class="mt-2" style="font-weight: bold;"></p>
        </div>
      </div>
      <!-- DIAGNÓSTICO Y MOTIVO -->
      <div class="form-container form-section context">
        <div class="section-header">
          <h3>Contexto Clínico <span class="hl7-tag">FHIR: Condition</span></h3>
        </div>
        <div class="row">
            <div class="col-md-6" id="diagnostico-container" style="display: none;">
                <label for="diagnostico_principal" class="form-label">Diagnóstico Principal (CIE-10)</label>
                <select class="form-control" id="diagnostico_principal" name="diagnostico_principal" style="width: 100%;"></select>
            </div>
            <div class="col-md-6" id="motivo-traslado-container">
                <label for="motivo_traslado" class="form-label">Motivo del Traslado</label>
                <textarea class="form-control" id="motivo_traslado" name="motivo_traslado" rows="2"></textarea>
            </div>
        </div>
      </div>

      <!-- DATOS CLÍNICOS -->
      <div class="form-container form-section clinical">
        <div class="section-header">
          <h3>Datos Clínicos <span class="hl7-tag">HL7: OBR</span> <span class="hl7-tag">FHIR: Observation</span></h3>
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
            <label for="tension_arterial" class="form-label">Tensión Arterial</label>
            <input type="text" class="form-control" id="tension_arterial" name="tension_arterial" placeholder="ej: 120/80">
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-3">
            <label for="glucometria" class="form-label">Glucometría</label>
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
            <label for="examen_fisico" class="form-label" style="font-size: 1.1rem; font-weight: bold;">Describa el Examen Físico</label>
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
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="4"> (4) Espontánea</label></div>
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
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="6"> (6) Obedece órdenes</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="5"> (5) Localiza el dolor</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="4"> (4) Retirada al dolor</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="3" checked> (3) Flexión anormal</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="2"> (2) Extensión anormal</label></div>
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
          <h3>Antecedentes Médicos <span class="hl7-tag">FHIR: Condition, AllergyIntolerance</span></h3>
        </div>
        <?php
          $opciones_antecedentes = [
              'Patologicos' => ['Hipertensión Arterial (HTA)', 'Diabetes Mellitus (DM)', 'Enfermedad Cardiovascular (ECV)', 'EPOC', 'Asma', 'Cáncer', 'Enfermedad Renal Crónica (ERC)', 'Convulsiones / Epilepsia', 'Otro'],
              'Alergicos' => ['Medicamentos (AINES, Penicilina)', 'Alimentos (Maní, Mariscos)', 'Látex', 'Picaduras de insectos', 'Polen / Ambientales', 'Otro'],
              'Quirurgicos' => ['Apendicectomía', 'Colecistectomía', 'Cesárea', 'Cirugía Cardíaca', 'Cirugía Ortopédica', 'Otro'],
              'Traumatologicos' => ['Fracturas', 'Esguinces', 'TEC (Traumatismo)', 'Heridas por arma', 'Otro'],
              'Toxicologicos' => ['Alcohol', 'Tabaco', 'Sustancias Psicoactivas (SPA)', 'Intoxicación por medicamentos', 'Intoxicación por químicos', 'Otro'],
              'Familiares' => ['Hipertensión Arterial (HTA)', 'Diabetes Mellitus (DM)', 'Cáncer', 'Enfermedad Cardiovascular', 'Otro']
          ];
          $antecedentes = [
            "Patologicos" => "Patológicos",
            "Alergicos" => "Alérgicos",
            "Quirurgicos" => "Quirúrgicos",
            "Traumatologicos" => "Traumatológicos",
            "Toxicologicos" => "Toxicológicos",
            "GinecoObstetricos" => "Gineco-Obstétricos",
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
              <option value="Si">Sí</option>
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
              <input type="text" class="form-control" name="ant_<?= $key_lower ?>_cual" placeholder="¿Cuál(es)?">
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
              <option value="">Ninguno</option>
              <option value="Cánula Nasal">Cánula Nasal</option>
              <option value="Máscara Simple">Máscara Simple</option>
              <option value="Máscara con Reservorio">Máscara con Reservorio</option>
              <option value="Venturi">Venturi</option>
              <option value="Intubado">Intubado</option>
              <option value="Máscara laríngea">Máscara laríngea</option>
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
          <!-- Los medicamentos se añadirán aquí dinámicamente -->
        </div>
        <button type="button" class="btn btn-outline-primary mt-2" id="btn-agregar-medicamento">
          + Agregar Medicamento
        </button>
        <template id="medicamento-template">
          <div class="row mb-2 medicamento-item">
            <div class="col-md-2"><label class="form-label">Hora</label><input type="text" class="form-control medicamento-hora-input" name="medicamento_hora[]" placeholder="HH:MM"></div>
            <div class="col-md-4"><label class="form-label">Nombre Genérico</label><input type="text" class="form-control" name="medicamento_nombre[]"></div>
            <div class="col-md-2"><label class="form-label">Dosis</label><input type="text" class="form-control" name="medicamento_dosis[]" placeholder="Ej: 500mg"></div>
            <div class="col-md-3">
              <label class="form-label">Vía</label>
              <select class="form-select" name="medicamento_via[]">
                <option value="IV">Intravenosa (IV)</option>
                <option value="IM">Intramuscular (IM)</option>
                <option value="SC">Subcutánea (SC)</option>
                <option value="VO">Vía Oral (VO)</option>
                <option value="SL">Sublingual (SL)</option>
                <option value="Tópica">Tópica</option>
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
            <h3>Datos del Médico Receptor</h3>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="nombre_medico_receptor" class="form-label">Nombre del Médico Receptor</label>
                <input type="text" class="form-control" id="nombre_medico_receptor" name="nombre_medico_receptor" >
            </div>
            <div class="col-md-3">
                <label for="tipo_id_medico_receptor" class="form-label ">Tipo ID</label>
                <select class="form-select" id="tipo_id_medico_receptor" name="tipo_id_medico_receptor">
                    <option value="Registro Médico" selected>Registro Médico</option>
                    <option value="CC">CC</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="id_medico_receptor" class="form-label">Número ID</label>
                <input type="text" class="form-control" id="id_medico_receptor" name="id_medico_receptor" >
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-8">
                <label for="nombre_ips_receptora" class="form-label">Nombre de IPS Receptora</label>
                <input type="text" class="form-control" id="nombre_ips_receptora" name="nombre_ips_receptora">
            </div>
            <div class="col-md-4">
                <label for="nit_ips_receptora" class="form-label">NIT de IPS Receptora</label>
                <input type="text" class="form-control" id="nit_ips_receptora" name="nit_ips_receptora">
            </div>
        </div>
      </div>
      <!-- FIRMAS Y ACEPTACIÓN -->
      <div class="form-container form-section firmas">
        <div class="section-header">
          <h3>Firmas y Aceptación <span class="hl7-tag">FHIR: Consent</span></h3>
        </div>

        <!-- Sub-sección Firmas Tripulación -->
        <div class="sub-section mb-4">
          <h5 class="sub-section-title">Firmas de la Tripulación</h5>
          <div class="row">
            <div class="col-md-6">
              <label class="form-label">Tripulante</label>
              <div id="firmaParamedico" class="signature-pad-container mb-2">
                <canvas class="signature-pad"></canvas>
              </div>
              <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="paramedico">Limpiar Firma</button>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="paramedico">Ampliar</button>
              </div>
              <input type="hidden" name="firma_paramedico" id="FirmaParamedicoData">
            </div>
            <div class="col-md-6" id="medico-tripulante-firma-container" style="display: none;">
              <label class="form-label">Médico Tripulante (si aplica)</label>
              <div id="firmaMedico" class="signature-pad-container mb-2">
                <canvas class="signature-pad"></canvas>
              </div>
              <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="medico">Limpiar Firma</button>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="medico">Ampliar</button>
              </div>
              <input type="hidden" name="firma_medico" id="FirmaMedicoData">
            </div>
          </div>
        </div>

        <!-- Sub-sección Consentimiento del Paciente -->
        <div class="sub-section mb-4">
          <h5 class="sub-section-title">Consentimiento del Paciente o Representante</h5>
          <p class="text-muted">Por favor, seleccione una opción para continuar.</p>
          <div class="d-flex justify-content-center gap-3 mb-3">
            <button type="button" class="btn btn-success btn-lg" id="btn-aceptar-atencion">Acepto la Atención</button>
            <button type="button" class="btn btn-danger btn-lg" id="btn-rechazar-atencion">Rechazo la Atención</button>
          </div>

          <!-- Contenedor para la firma de ACEPTACIÓN -->
          <div id="consentimiento-container" style="display: none;">
            <label class="form-label">Firma de Aceptación</label>
            <div id="firmaPaciente" class="signature-pad-container mb-2">
              <p class="consent-text">Como firmante, mayor de edad, obrando en nombre propio y en mi condición de Acompañante o Testigo, por medio del presente documento manifiesto que he autorizado a <?= htmlspecialchars($empresa['nombre']) ?>, empresa responsable del transporte y atención prehospitalaria, la atención y aplicación de los tratamientos propuestos por el personal de salud y, en caso de ser necesario, el traslado en ambulancia por parte de <?= htmlspecialchars($empresa['nombre']) ?>. Así mismo, autorizo a que se realicen los procedimientos necesarios para la estabilización clínica. </br>
Sé y acepto que la práctica de la medicina no es una ciencia exacta; no se me han prometido ni garantizado resultados esperados. Se me ha dado la oportunidad de preguntar y resolver las dudas, y todas ellas han sido atendidas a satisfacción. </br>
Manifiesto que he entendido las condiciones y objetivos de la atención que se me va a realizar, los cuidados que debo tener, y además comprendo y acepto el alcance y los riesgos justificados que conlleva el procedimiento. </br>
Teniendo en cuenta lo anterior, doy mi consentimiento informado libre y voluntariamente a <?= htmlspecialchars($empresa['nombre']) ?>, entendiendo y aceptando todos los riesgos, reacciones, complicaciones y resultados insatisfactorios que puedan derivarse de la atención y de los procedimientos realizados, los cuales reconozco que pueden presentarse a pesar de que se tomen las precauciones usuales para evitarlos.</br>
Me comprometo a cumplir con las recomendaciones, instrucciones y controles posteriores al tratamiento realizado. Certifico que he tenido la oportunidad de hacer todas las preguntas pertinentes para aclarar mis dudas y que se me han respondido de manera clara y suficiente.</p>
              <canvas class="signature-pad"></canvas>
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
              <p class="consent-text text-danger">Me niego a recibir la atención médica, traslado o internación sugerida por el sistema de emergencia médica. Eximo de toda responsabilidad a <?= htmlspecialchars($empresa['nombre']) ?> de las consecuencias de mi decisión, y asumo los riesgos que mi negativa pueda generar.</p>
              <canvas class="signature-pad"></canvas>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="desistimiento">Limpiar Firma</button>
              <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="desistimiento">Ampliar</button>
            </div>
            <input type="hidden" name="firma_desistimiento" id="FirmaDesistimientoData">
          </div>
        </div>

        <!-- Sub-sección Firma Médico Receptor -->
        <div class="sub-section">
          <h5 class="sub-section-title">Firma del Médico que Recibe</h5>
          <div class="col-md-6">
            <label class="form-label">Médico Receptor</label>
            <div id="firmaMedicoReceptor" class="signature-pad-container mb-2">
              <canvas class="signature-pad"></canvas>
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
    <label for="adjuntos" class="form-label">Adjuntar archivos (imágenes o PDF) - Máx 10</label>
    <input type="file" class="form-control" id="adjuntos" name="adjuntos[]" accept="image/*,application/pdf" multiple>
    <small class="text-muted">
      Puedes seleccionar varias imágenes desde tu galería o tomar fotos directamente con la cámara. Los archivos se subirán automáticamente.
    </small>
  </div>
  <div class="progress mt-3" id="progress-container" style="display: none;">
    <div class="progress-bar" id="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
	  </div>
  </div>

  <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="aceptacion" name="aceptacion">
          <label class="form-check-label" for="aceptacion">Reconozco que la información proporcionada es ficticia y este es un ambiente de pruebas en desarrollo.</label>
  </div>


       <div class="d-grid">
        <button type="submit" class="btn btn-lg btn-primary">Guardar Registro</button>
      </div><div id="contadorTiempo" class="mt-3 text-center" style="font-size: 1.2em;"></div>
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
              <canvas id="modalCanvas" class="signature-pad" style="width: 100%; height: 100%;"></canvas>
            </div>
          </div>
          <div class="modal-footer">
                     </div>
        </div>
      </div>
    </div>

  </form>
  <style>
    body {
      padding-bottom: 60px; /* Añade espacio para el footer fijo */
    }
    .imc-bajo { background-color: #e0f7fa; color: #00838f; border: 1px solid #00838f; }
    .imc-saludable { background-color: #c8e6c9; color: #388e3c; }
    .imc-sobrepeso { background-color: #fff9c4; color: #f9a825; border: 1px solid #f9a825; }
    .imc-obeso { background-color: #ffcdd2; color: #d32f2f; border: 1px solid #d32f2f; }
    .imc-obesidad-extrema { background-color: #e0b0ff; color: #6a0dad; border: 1px solid #6a0dad; }
    /* Estilos para validación de signos vitales */
    .valor-normal { background-color: #c8e6c9; color: #388e3c; } /* Verde saludable */
    .valor-alerta { background-color: #fff9c4; color: #f9a825; border: 1px solid #f9a825; } /* Amarillo/Naranja advertencia */
    .valor-peligro { background-color: #ffcdd2; color: #d32f2f; border: 1px solid #d32f2f; } /* Rojo peligro */
  </style>
  
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    // Establecer el idioma español para moment.js globalmente
    moment.locale('es');

    // --- BÚSQUEDA CIE-10 ---
    $('#diagnostico_principal').select2({
      placeholder: 'Buscar diagnóstico por código o nombre...',
      minimumInputLength: 3,
      language: {
          inputTooShort: function () {
              return "Por favor, ingrese 3 o más caracteres";
          },
          noResults: function () {
              return "No se encontraron resultados";
          },
          searching: function () {
              return "Buscando...";
          }
      },
      ajax: {
        url: AppBaseUrl + 'buscar_cie10.php', // Usar la URL base definida en el header
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return { q: params.term };
        },
        processResults: function (data) {
          return { results: data };
        },
        cache: true
      },
      escapeMarkup: function (markup) { return markup; }, // Permitir HTML en los resultados
      templateResult: formatRepo, // Cómo se ven los resultados en la lista desplegable
      templateSelection: formatRepoSelection // Cómo se ve el resultado seleccionado
    });

    function formatRepo (repo) {
      if (repo.loading) return repo.text;
      // El markup que se mostrará en la lista de resultados
      return "<strong>" + repo.id + "</strong>: " + repo.text;
    }

    function formatRepoSelection (repo) {
      return repo.id + ' - ' + repo.text || repo.text;
    }

    // --- CÁLCULO TIEMPO DE SERVICIO OCUPADO ---
    const horaDespachoInput = document.getElementById('hora_despacho');
    const horaFinalInput = document.getElementById('hora_final');
    const tiempoServicioDisplay = document.getElementById('tiempo_servicio_ocupado');

    function calcularTiempoServicio() {
        const despacho = horaDespachoInput.value;
        const final = horaFinalInput.value;

        if (despacho && final) {
            const inicio = moment(despacho, "HH:mm");
            const fin = moment(final, "HH:mm");
            if (fin.isBefore(inicio)) {
                tiempoServicioDisplay.textContent = 'Error';
                return;
            }
            const duracion = moment.duration(fin.diff(inicio));
            const horas = Math.floor(duracion.asHours());
            const minutos = duracion.minutes();
            tiempoServicioDisplay.textContent = `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}`;
        }
    }

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

    // --- VALIDACIÓN DE SIGNOS VITALES CON COLORES ---
    function configurarValidacionSignos() {
      // Función genérica para valores numéricos simples
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

      // Glucometría
      document.getElementById('glucometria').addEventListener('input', function() {
        validarSigno(this, { normal_min: 70, normal_max: 140, peligro_min: 60, peligro_max: 250 });
      });

      // Temperatura
      document.getElementById('temperatura').addEventListener('input', function() {
        validarSigno(this, { normal_min: 36.5, normal_max: 37.5, peligro_min: 35, peligro_max: 40 });
      });

      // Tensión Arterial (TA)
      document.getElementById('tension_arterial').addEventListener('input', function() {
        this.classList.remove('valor-normal', 'valor-alerta', 'valor-peligro');
        const partes = this.value.split('/');
        if (partes.length !== 2) return;
        const sistolica = parseInt(partes[0], 10);
        const diastolica = parseInt(partes[1], 10);
        if (isNaN(sistolica) || isNaN(diastolica)) return;

        if (sistolica > 180 || diastolica > 120) this.classList.add('valor-peligro'); // Crisis hipertensiva
        else if (sistolica >= 90 && sistolica <= 139 && diastolica >= 60 && diastolica <= 89) this.classList.add('valor-normal');
        else this.classList.add('valor-alerta'); // Hipotensión o Hipertensión
      });
    }
    configurarValidacionSignos();

    // --- LÓGICA SECCIÓN ASEGURADORA (SOAT) ---
    const pagadorField = document.getElementById("pagador");
    const datosSoatSection = document.getElementById("datos-soat");
    const epsContainer = document.getElementById("eps-container");
    function toggleDatosSoat() {
      const selectedValue = pagadorField.value;
      datosSoatSection.style.display = (selectedValue === "SOAT" || selectedValue === "ARL" || selectedValue === "EPS") ? "block" : "none";
      epsContainer.style.display = (selectedValue === "EPS") ? "flex" : "none";
      if (selectedValue !== "EPS") {
        document.getElementById('eps_nombre').value = '';
      }
    }
    pagadorField.addEventListener("change", toggleDatosSoat);
    toggleDatosSoat();

    // --- LÓGICA SECCIÓN PACIENTE (ETNIA Y OTROS) ---
    document.getElementById('etnia').addEventListener('change', function() {
      document.getElementById('especificar_otra').style.display = this.value === 'Otro' ? 'block' : 'none';
    });
    document.getElementById('escena_paciente').addEventListener('change', function() {
        document.getElementById('escena_paciente_otro').style.display = this.value === 'Otro' ? 'block' : 'none';
    });

    // --- LÓGICA CÁLCULO IMC ---
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

    // --- LÓGICA ANTECEDENTES GINECOLÓGICOS ---
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
    // --- LÓGICA ANTECEDENTES "OTRO" ---
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
    // --- LÓGICA ANTECEDENTES ---
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
    // --- LÓGICA ADJUNTOS (PROGRESS BAR) ---
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
    // --- VALIDACIÓN DEL FORMULARIO ---
    $('#clinical-form').on('submit', function(e) {
        e.preventDefault(); // Prevenimos el envío por defecto para validar con calma.
        let errores = [];

        // 1. Validar campos de texto y selectores básicos
        if ($('#tripulante_hidden').val().trim() === '') {
            errores.push('El campo Tripulante es obligatorio. Por favor, inicie sesión de nuevo si el problema persiste.');
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
            errores.push('Se requiere la firma de Aceptación o de Desistimiento del paciente/representante.');
        }

        // Si se aceptó la atención (no hay desistimiento), validar los datos y firma del médico receptor.
        if (firmaPaciente && !firmaDesistimiento) {
            if (!$('#nombre_medico_receptor').val() || !$('#id_medico_receptor').val()) {
                errores.push('Los datos del Médico Receptor son obligatorios cuando se acepta la atención.');
            }
            if (!firmaMedicoReceptor) {
                errores.push('La firma del Médico Receptor es obligatoria cuando se acepta la atención.');
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

    // --- LÓGICA DE AUTOGUARDADO EN LOCALSTORAGE ---
    const form = document.getElementById('clinical-form');
    const formId = 'form_autosave_data'; // ID único para los datos de este formulario

    // Función para guardar el estado del formulario
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

    // Función para restaurar el estado del formulario
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
            // Disparar eventos 'change' para que otras lógicas (como la de antecedentes) se actualicen
            // Usamos un pequeño retraso para asegurar que todos los elementos estén listos
            setTimeout(() => {
                $(form).find('select, input[type!=radio][type!=checkbox]').trigger('change');
            }, 100);
        }
    }

    // Guardar el estado en cada cambio
    form.addEventListener('input', saveFormState);
    form.addEventListener('change', saveFormState); // Para selects y checkboxes

    // Restaurar el estado al cargar la página
    restoreFormState();

    // --- CÁLCULO ESCALA DE GLASGOW ---
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
    // --- MANEJO DE FIRMAS ---
// === FIX: Firmas y TAB/TAM (dentro de DOMContentLoaded) ===
function resizeCanvas(canvas) {
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  const rect = canvas.getBoundingClientRect();
  canvas.width = Math.max(1, Math.floor(rect.width * ratio));
  canvas.height = Math.max(1, Math.floor(rect.height * ratio));
  const ctx = canvas.getContext('2d');
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function dataInputIdFor(tipo) {
  const cap = tipo.charAt(0).toUpperCase() + tipo.slice(1);
  return 'Firma' + cap + 'Data';
}

const firmas = {};
const mapping = {
  paramedico: '#firmaParamedico .signature-pad',
  medico: '#firmaMedico .signature-pad',
  paciente: '#firmaPaciente .signature-pad',
  desistimiento: '#firmaDesistimiento .signature-pad',
  medicoReceptor: '#firmaMedicoReceptor .signature-pad'
};
Object.entries(mapping).forEach(([key, sel]) => {
  const canvas = document.querySelector(sel);
  if (canvas) {
    resizeCanvas(canvas);
    firmas[key] = new SignaturePad(canvas, { penColor: '#34446d', minWidth: 0.5, maxWidth: 1.5 });
  }
});

function updateSignatureHidden(tipo) {
  const pad = firmas[tipo];
  const hidden = document.getElementById(dataInputIdFor(tipo));
  if (hidden) hidden.value = pad && !pad.isEmpty() ? pad.toDataURL('image/png') : '';
}

function clearSignature(tipo) {
  if (!firmas[tipo]) return;
  firmas[tipo].clear();
  updateSignatureHidden(tipo);
}

// Modal unificado (usa #signatureModal, #modalCanvas, #clearSignature, #saveSignature)
const modalEl = document.getElementById('signatureModal');
const modalCanvas = document.getElementById('modalCanvas');
const modalPad = modalCanvas ? new SignaturePad(modalCanvas, { penColor: '#000' }) : null;
let currentSigTarget = null;

if (modalEl && modalPad) {
  modalEl.addEventListener('shown.bs.modal', (ev) => {
    const trigger = ev.relatedTarget;
    currentSigTarget = trigger ? trigger.getAttribute('data-sig-target') : null;
    resizeCanvas(modalCanvas);
    modalPad.clear();
    if (currentSigTarget && firmas[currentSigTarget] && !firmas[currentSigTarget].isEmpty()) {
      try { modalPad.fromData(firmas[currentSigTarget].toData()); } catch {}
    }
  });

  document.getElementById('clearSignature')?.addEventListener('click', () => modalPad.clear());

  document.getElementById('saveSignature')?.addEventListener('click', () => {
    if (!currentSigTarget || !firmas[currentSigTarget]) return;
    const target = firmas[currentSigTarget];
    const data = modalPad.isEmpty() ? null : modalPad.toData();
    target.clear();
    if (data) { try { target.fromData(data); } catch {} }
    updateSignatureHidden(currentSigTarget);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
  });
}

// Delegación: limpiar y abrir modal
document.body.addEventListener('click', (e) => {
  const btn = e.target.closest('button');
  if (!btn) return;
  if (btn.matches('[data-tipo]') && /limpiar/i.test(btn.textContent)) {
    clearSignature(btn.getAttribute('data-tipo'));
  }
  // Abrir modal: Bootstrap lo gestiona con data-bs-target="#signatureModal"
});

// TAB/TAM toggle
const servicioSel = document.getElementById('servicio');
const medicoTrip = document.getElementById('medico-tripulante-firma-container');
const meds = document.getElementById('medicamentos-aplicados-container');
function setDisplay(el, show){ if (el) el.style.display = show ? '' : 'none'; }
function actualizarSeccionesTABTAM() {
  const v = (servicioSel?.value || '').toLowerCase();
  const isTAM = v.includes('medicalizado');
  setDisplay(medicoTrip, isTAM);
  setDisplay(meds, isTAM);
  if (!isTAM && firmas.medico) clearSignature('medico');
}
if (servicioSel) {
  servicioSel.addEventListener('change', actualizarSeccionesTABTAM);
  actualizarSeccionesTABTAM();
}

// Re–size on window resize
window.addEventListener('resize', () => {
  Object.values(firmas).forEach(p => {
    if (!p) return;
    const data = p.isEmpty() ? null : p.toData();
    resizeCanvas(p.canvas);
    p.clear();
    if (data) { try { p.fromData(data); } catch {} }
  });
  if (modalEl && modalPad && modalEl.classList.contains('show')) {
    const d = modalPad.isEmpty() ? null : modalPad.toData();
    resizeCanvas(modalCanvas);
    modalPad.clear();
    if (d) { try { modalPad.fromData(d); } catch {} }
  }
});


// === FIX: Firmas y TAB/TAM (dentro de DOMContentLoaded) ===
function resizeCanvas(canvas) {
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  const rect = canvas.getBoundingClientRect();
  canvas.width = Math.max(1, Math.floor(rect.width * ratio));
  canvas.height = Math.max(1, Math.floor(rect.height * ratio));
  const ctx = canvas.getContext('2d');
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function dataInputIdFor(tipo) {
  const cap = tipo.charAt(0).toUpperCase() + tipo.slice(1);
  return 'Firma' + cap + 'Data';
}

// DEDUP: removed duplicate "const firmas = {};"
// DEDUP: removed duplicate "const mapping = {};"
  paramedico: '#firmaParamedico .signature-pad',
  medico: '#firmaMedico .signature-pad',
  paciente: '#firmaPaciente .signature-pad',
  desistimiento: '#firmaDesistimiento .signature-pad',
  medicoReceptor: '#firmaMedicoReceptor .signature-pad'
};
Object.entries(mapping).forEach(([key, sel]) => {
  const canvas = document.querySelector(sel);
  if (canvas) {
    resizeCanvas(canvas);
    firmas[key] = new SignaturePad(canvas, { penColor: '#34446d', minWidth: 0.5, maxWidth: 1.5 });
  }
});

function updateSignatureHidden(tipo) {
  const pad = firmas[tipo];
  const hidden = document.getElementById(dataInputIdFor(tipo));
  if (hidden) hidden.value = pad && !pad.isEmpty() ? pad.toDataURL('image/png') : '';
}

function clearSignature(tipo) {
  if (!firmas[tipo]) return;
  firmas[tipo].clear();
  updateSignatureHidden(tipo);
}

// Modal unificado (usa #signatureModal, #modalCanvas, #clearSignature, #saveSignature)
const modalEl = document.getElementById('signatureModal');
const modalCanvas = document.getElementById('modalCanvas');
const modalPad = modalCanvas ? new SignaturePad(modalCanvas, { penColor: '#000' }) : null;
let currentSigTarget = null;

if (modalEl && modalPad) {
  modalEl.addEventListener('shown.bs.modal', (ev) => {
    const trigger = ev.relatedTarget;
    currentSigTarget = trigger ? trigger.getAttribute('data-sig-target') : null;
    resizeCanvas(modalCanvas);
    modalPad.clear();
    if (currentSigTarget && firmas[currentSigTarget] && !firmas[currentSigTarget].isEmpty()) {
      try { modalPad.fromData(firmas[currentSigTarget].toData()); } catch {}
    }
  });

  document.getElementById('clearSignature')?.addEventListener('click', () => modalPad.clear());

  document.getElementById('saveSignature')?.addEventListener('click', () => {
    if (!currentSigTarget || !firmas[currentSigTarget]) return;
    const target = firmas[currentSigTarget];
    const data = modalPad.isEmpty() ? null : modalPad.toData();
    target.clear();
    if (data) { try { target.fromData(data); } catch {} }
    updateSignatureHidden(currentSigTarget);
    (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
  });
}

// Delegación: limpiar y abrir modal
document.body.addEventListener('click', (e) => {
  const btn = e.target.closest('button');
  if (!btn) return;
  if (btn.matches('[data-tipo]') && /limpiar/i.test(btn.textContent)) {
    clearSignature(btn.getAttribute('data-tipo'));
  }
  // Abrir modal: Bootstrap lo gestiona con data-bs-target="#signatureModal"
});

// === TAB/TAM toggle (FIX robusto) ===
const servicioSel = document.getElementById('servicio');
const medicoTrip = document.getElementById('medico-tripulante-firma-container');
const meds = document.getElementById('medicamentos-aplicados-container');

function mostrar(el, on) { if (el) el.style.display = on ? '' : 'none'; }

function normaliza(t) {
  return (t || '').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();
}

function actualizarSeccionesTABTAM() {
  if (!servicioSel) return;
  const optText = servicioSel.options[servicioSel.selectedIndex]?.text || '';
  const val = normaliza(servicioSel.value) + ' ' + normaliza(optText);
  const isTAM = val.includes('medicalizado') || val.includes('(tam)');
  mostrar(medicoTrip, isTAM);
  mostrar(meds, isTAM);
}

if (servicioSel) {
  servicioSel.addEventListener('change', actualizarSeccionesTABTAM);
  actualizarSeccionesTABTAM();
}

// Re–size on window resize
window.addEventListener('resize', () => {
  Object.values(firmas).forEach(p => {
    if (!p) return;
    const data = p.isEmpty() ? null : p.toData();
    resizeCanvas(p.canvas);
    p.clear();
    if (data) { try { p.fromData(data); } catch {} }
  });
  if (modalEl && modalPad && modalEl.classList.contains('show')) {
    const d = modalPad.isEmpty() ? null : modalPad.toData();
    resizeCanvas(modalCanvas);
    modalPad.clear();
    if (d) { try { modalPad.fromData(d); } catch {} }
  }
});
  }); // Fin de DOMContentLoaded

</script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php include 'footer.php'; ?>