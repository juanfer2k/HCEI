<?php
require_once __DIR__ . '/../access_control.php';
require_once __DIR__ . '/../bootstrap.php';

// Este index dentro de /admin actúa como alias del panel administrativo.
$rolRaw = $_SESSION['usuario_rol'] ?? ($_SESSION['rol'] ?? $_SESSION['role'] ?? $_SESSION['perfil'] ?? '');
$rol = is_string($rolRaw) ? strtolower(trim($rolRaw)) : '';

if (in_array($rol, ['administrativo','master','dev'], true)) {
    header('Location: ' . BASE_URL . 'admin/panel.php');
    exit;
}

// Otros roles no deberían navegar directamente a /admin
header('Location: ' . BASE_URL . 'index.php');
exit;
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
        <div id="furtran-legal-block" class="row g-3 mt-2 d-none">
          
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
            <div class="form-label">Tiempo de Servicio</div>
            <span id="tiempo_servicio_ocupado" class="badge bg-info text-dark fs-6">00:00</span>
          </div>
        </div>
        
        <!-- NUEVO: Datos del Accidente y Aseguradora -->
        <!-- ACCIDENTE/ASEGURADORA: relocated to Aseguradora section -->
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
                <option value="Registro Medico" selected>Registro Médico</option>
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
    <label for="direccion_servicio" class="form-label">Dirección del Servicio/atención</label>
    <div class="input-group">
      <input type="text" class="form-control address-input" id="direccion_servicio" name="direccion_servicio" placeholder="Direccion del Servicio/Atencion" autocomplete="street-address">
      <button type="button" class="btn btn-outline-primary register-location-btn" data-target="direccion_servicio" data-feedback="direccion_servicio_feedback">
        Registrar ubicacion
      </button>
    </div>
    <div class="form-text" id="direccion_servicio_feedback"></div>
  </div>
  <div class="col-md-6 mt-2">
    <label for="municipio_servicio" class="form-label">Municipio del Servicio</label>
    <select class="form-select" id="municipio_servicio" name="municipio_servicio" style="width: 100%;"></select>
    <input type="hidden" id="codigo_municipio_servicio" name="codigo_municipio_servicio">
    <div class="row g-2 mt-1">
        <div class="col-sm-6"><input type="text" class="form-control detalle-readonly" id="municipio_servicio_display" readonly placeholder="Código"></div>
        <div class="col-sm-6"><input type="text" class="form-control detalle-readonly" id="municipio_servicio_departamento_display" readonly placeholder="Cód. Depto"></div>
        <div class="col-sm-4"><input type="text" class="form-control detalle-readonly" id="municipio_servicio_tipo_display" readonly placeholder="Tipo"></div>
        <div class="col-sm-4"><input type="text" class="form-control detalle-readonly" id="municipio_servicio_lat_display" readonly placeholder="Latitud"></div>
        <div class="col-sm-4"><input type="text" class="form-control detalle-readonly" id="municipio_servicio_lon_display" readonly placeholder="Longitud"></div>
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
            <div class="input-group">
              <input type="text" class="form-control address-input" id="direccion_domicilio" name="direccion_domicilio" autocomplete="street-address">
              <button type="button" class="btn btn-outline-primary register-location-btn" data-target="direccion_domicilio" data-feedback="direccion_domicilio_feedback">
                Registrar ubicacion
              </button>
            </div>
            <div class="form-text" id="direccion_domicilio_feedback"></div>
          </div>
          <div class="col-md-4">
            <label for="barrio_paciente" class="form-label">Barrio del Paciente</label>
            <input type="text" class="form-control" id="barrio_paciente" name="barrio_paciente">
          </div>
        </div>
        <div class="row mb-2">
          <div class="col-md-6">
            <label for="municipio" class="form-label">Municipio de Residencia del paciente</label>
            <select class="form-select" id="municipio" name="municipio_paciente" style="width: 100%;"></select>
            <input type="hidden" id="codigo_municipio" name="codigo_municipio">
            <div class="row g-2 mt-1">
              <div class="col-sm-6"><input type="text" class="form-control detalle-readonly" id="municipio_display" readonly placeholder="Código"></div>
              <div class="col-sm-6"><input type="text" class="form-control detalle-readonly" id="municipio_departamento_display" readonly placeholder="Cód. Depto"></div>
              <div class="col-sm-4"><input type="text" class="form-control detalle-readonly" id="municipio_tipo_display" readonly placeholder="Tipo"></div>
              <div class="col-sm-4"><input type="text" class="form-control detalle-readonly" id="municipio_lat_display" readonly placeholder="Latitud"></div>
              <div class="col-sm-4"><input type="text" class="form-control detalle-readonly" id="municipio_lon_display" readonly placeholder="Longitud"></div>
            </div>
          </div>
          <div class="col-md-6">
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
          <h3>Escala de Riesgo de Caídas (Downton)</h3>
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
          <h3>Contexto Clínico <span class="hl7-tag">FHIR: Condition</span></h3>
        </div>
        <div class="row">
            <div class="col-md-6" id="diagnostico-container">
                <label for="diagnostico_principal" class="form-label">Diagnostico Principal (CIE-10)</label>
                <select class="form-control" id="diagnostico_principal" name="diagnostico_principal" style="width: 100%;"></select>
                <input type="text" class="form-control mt-2 detalle-readonly" id="diagnostico_principal_display" readonly placeholder="Descripcion del diagnostico">
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
          <h3>Antecedentes Médicos <span class="hl7-tag">FHIR: Condition, AllergyIntolerance</span></h3>
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
              <option value="Ninguno"></option>
              <option value="Canula Nasal">Cánula Nasal</option>
              <option value="Mascara Simple">Máscara Simple</option>
              <option value="Mascara con Reservorio">Máscara con Reservorio</option>
              <option value="Venturi">Venturi</option>
              <option value="Intubado">Intubado</option>
              <option value="Mascara laringea">Máscara laríngea</option>
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
            <div class="col-md-4"><label class="form-label">Nombre Genérico</label><input type="text" class="form-control" name="medicamento_nombre[]"></div>
            <div class="col-md-2"><label class="form-label">Dósis</label><input type="text" class="form-control" name="medicamento_dosis[]" placeholder="Ej: 500mg"></div>
            <div class="col-md-3">
              <label class="form-label">Vía de administración</label>
              <select class="form-select" name="medicamento_via[]">
                <option value="IV">Intravenosa (IV)</option>
                <option value="IM">Intramuscular (IM)</option>
                <option value="SC">Subcutánea (SC)</option>
                <option value="VO">Vía Oral (VO)</option>
                <option value="SL">Sublingual (SL)</option>
                <option value="Topica">Tópica</option>
                <option value="Inhalada">Inhalada</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
            <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-danger btn-sm btn-remover-medicamento">X</button></div>
          </div>
        </template>
      </div>

      <!-- DATOS MÉDICO RECEPTOR -->
      <div class="form-container form-section firmas" id="recepcion-paciente-container">
        <div class="section-header">
            <h3>Datos de Recepción al Paciente</h3>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="nombre_medico_receptor" class="form-label">Nombre del Medico Receptor</label>
                <input type="text" class="form-control" id="nombre_medico_receptor" name="nombre_medico_receptor" >
            </div>
            <div class="col-md-3">
                <label for="tipo_id_medico_receptor" class="form-label">Tipo ID</label>
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
                <input type="hidden" name="municipio_ips_receptora" id="ips_ciudad">
            </div>
        </div>
      </div>
      <!-- IDENTIFICACIÓN DE LA ASEGURADORA (SOAT) -->
      <div id="datos-soat" class="form-container form-section insurance">
        <div class="section-header">
          <h3>Datos para Aseguradora <span class="hl7-tag">HL7: IN1</span> <span class="hl7-tag">FHIR: Coverage</span></h3>
        </div>
        <div class="row mb-2" id="eps-container" style="display: none;">
          <div class="col-md-12">
            <label for="eps_nombre" class="form-label">Nombre de la EPS</label>
            <select class="form-select" id="eps_nombre" name="eps_nombre">
              <option value="Seleccione una EPS..." selected>Seleccione una EPS...</option>
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
          <!-- Reemplazamos ips2/ips3/ips4 por un selector de IPS Receptora que pobla ips2 y limpia ips3/ips4 -->
          <div class="col-md-6">
            <label for="ips_receptora_select" class="form-label">Seleccionar IPS Receptora</label>
            <select id="ips_receptora_select" class="form-select" style="width:100%">
              <option value="">-- Seleccione IPS receptora --</option>
              <?php foreach ($ips_options as $opt): ?>
                <option value="<?= htmlspecialchars($opt['id']) ?>" data-nombre="<?= htmlspecialchars($opt['nombre']) ?>" data-ciudad="<?= htmlspecialchars($opt['ciudad']) ?>"><?= htmlspecialchars($opt['text']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" id="ips2" name="ips2" value="">
            <input type="hidden" id="ips3" name="ips3" value="">
            <input type="hidden" id="ips4" name="ips4" value="">
          </div>
          <div class="col-md-6" id="placa_paciente_group">
            <label for="placa_paciente" class="form-label">Placa vehículo (paciente)</label>
            <input type="text" class="form-control" id="placa_paciente" name="placa_paciente" placeholder="ABC123">
          </div>
        <div class="col-md-6">
            <label for="nombre_receptor_admin_ips" class="form-label">Nombre del responsable de admisiones</label>
            <input type="text" class="form-control" id="nombre_receptor_admin_ips" name="nombre_receptor_admin_ips" autocomplete="name">
          </div>
          <div class="col-md-3">
            <label for="tipo_documento_receptor_admin_ips" class="form-label">Tipo de documento</label>
            <select class="form-select" id="tipo_documento_receptor_admin_ips" name="tipo_documento_receptor_admin_ips">
              <option value="">Seleccione</option>
              <option value="CC">CC - Cedula de ciudadania</option>
              <option value="CE">CE - Cedula de extranjeria</option>
              <option value="PA">PA - Pasaporte</option>
              <option value="TI">TI - Tarjeta de identidad</option>
              <option value="NIT">NIT - N&uacute;mero de identificacion tributaria</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="id_receptor_admin_ips" class="form-label">Numero de documento</label>
            <input type="text" class="form-control" id="id_receptor_admin_ips" name="id_receptor_admin_ips" autocomplete="off">
          </div>
          <!-- AQUI: Datos del Accidente al final de la sección Aseguradora -->
          <div class="col-12 mt-2">
            <fieldset class="border p-3 rounded">
              <legend class="float-none w-auto px-2">Datos del Accidente</legend>
              <div class="row g-2">
                <div class="col-md-3">
                  <label for="conductor_accidente" class="form-label">Conductor involucrado</label>
                  <input type="text" class="form-control" id="conductor_accidente" name="conductor_accidente">
                </div>
                <div class="col-md-3">
                  <label for="documento_conductor_accidente" class="form-label">Documento conductor</label>
                  <input type="text" class="form-control" id="documento_conductor_accidente" name="documento_conductor_accidente">
                </div>
                <div class="col-md-3">
                  <label for="tarjeta_propiedad_accidente" class="form-label">Tarjeta de propiedad</label>
                  <input type="text" class="form-control" id="tarjeta_propiedad_accidente" name="tarjeta_propiedad_accidente">
                </div>
                <div class="col-md-3">
                  <label for="placa_vehiculo_involucrado" class="form-label">Placa vehículo (involucrado)</label>
                  <input type="text" class="form-control" id="placa_vehiculo_involucrado" name="placa_vehiculo_involucrado">
                </div>
              </div>
            </fieldset>
          </div>

          <!-- Firma del receptor IPS: movida dentro de esta sección -->
          <div class="col-12 mt-2">
            <label class="form-label">Firma del receptor (IPS)</label>
            <div id="firmaReceptorIPS" class="signature-pad-container mb-2">
              <canvas class="signature-pad" data-pen-color="#0b3d91"></canvas>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="receptorIPS">Limpiar Firma</button>
              <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="receptorIPS">Ampliar</button>
            </div>
            <input type="hidden" name="firma_receptor_ips" id="FirmaReceptorIPSData">
            <div class="form-text">Se habilita sólo para pagadores SOAT o ADRES.</div>
          </div>
        </div>
      </div>



      
      <!-- FIRMAS Y ACEPTACIÓN -->
      <div class="form-container form-section firmas">
        <div class="section-header">
          <h3>Firmas y Aceptación <span class="hl7-tag">FHIR: Consent</span></h3>
        </div>

        <!-- Sub-seccion Firmas Tripulacion -->
        <div class="sub-section mb-4">
          <h5 class="sub-section-title">Firmas de la Tripulación</h5>
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
              <label class="form-label">Médico Tripulante (si aplica)</label>
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
          <p class="text-muted">Por favor, seleccione una opción para continuar.</p>
          <div class="d-flex justify-content-center gap-3 mb-3">
            <button type="button" class="btn btn-success btn-lg" id="btn-aceptar-atencion">Acepto la Atención</button>
            <button type="button" class="btn btn-danger btn-lg" id="btn-rechazar-atencion">Rechazo la Atención</button>
          </div>

          <!-- Contenedor para la firma de ACEPTACIÓN -->
          <div id="consentimiento-container" style="display: none;">
            <label class="form-label">Firma de Aceptación</label>
            <div id="firmaPaciente" class="signature-pad-container mb-2">
              <p class="consent-text">Como firmante, mayor de edad, obrando en nombre propio y en mi condicion de Acompanante o Testigo, por medio del presente documento manifiesto que he autorizado a <?= htmlspecialchars($empresa['nombre']) ?>, empresa responsable del transporte y atención prehospitalaria, la atención y aplicacion de los tratamientos propuestos por el personal de salud y, en caso de ser necesario, el traslado en ambulancia por parte de <?= htmlspecialchars($empresa['nombre']) ?>. Asi mismo, autorizo a que se realicen los procedimientos necesarios para la estabilizacion clinica. </br>
Se y acepto que la practica de la medicina no es una ciencia exacta; no se me han prometido ni garantizado resultados esperados. Se me ha dado la oportunidad de preguntar y resolver las dudas, y todas ellas han sido atendidas a satisfacción. </br>
Manifiesto que he entendido las condiciones y objetivos de la atención que se me va a realizar, los cuidados que debo tener, y ademas comprendo y acepto el alcance y los riesgos justificados que conlleva el procedimiento. </br>
Teniendo en cuenta lo anterior, doy mi consentimiento informado libre y voluntariamente a <?= htmlspecialchars($empresa['nombre']) ?>, entendiendo y aceptando todos los riesgos, reacciones, complicaciones y resultados insatisfactorios que puedan derivarse de la atención y de los procedimientos realizados, los cuales reconozco que pueden presentarse a pesar de que se tomen las precauciones usuales para evitarlos.</br>
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
              <p class="consent-text text-danger">Me niego a recibir la atención medica, traslado o internacion sugerida por el sistema de emergencia medica. Eximo de toda responsabilidad a <?= htmlspecialchars($empresa['nombre']) ?> de las consecuencias de mi decision, y asumo los riesgos que mi negativa pueda generar.</p>
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
        <div class="sub-section" id="medico-receptor-firma-container" style="display: none;">
          <h5 class="sub-section-title" id="medico-receptor-firma-container">Firma del Médico que Recibe</h5>
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

        <!-- NUEVO: IPS Receptora (opcional) -->
        <div id="ipsReceptoraContainer" class="row g-2 mb-3" style="display:none;">
          <div class="col-md-8">
            <label for="ips_receptora_select" class="form-label">Seleccionar IPS Receptora</label>
            <select id="ips_receptora_select" class="form-select" style="width:100%">
              <option value="">-- Seleccione --</option>
              <?php foreach ($ips_options as $opt): ?>
                <option value="<?= htmlspecialchars($opt['id']) ?>" data-nombre="<?= htmlspecialchars($opt['nombre']) ?>" data-ciudad="<?= htmlspecialchars($opt['ciudad']) ?>"><?= htmlspecialchars($opt['text']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="nombre_ips_receptora" id="nombre_ips_receptora">
            <input type="hidden" name="nit_ips_receptora" id="nit_ips_receptora">
            <input type="hidden" name="municipio_ips_receptora" id="municipio_ips_receptora">
          </div>
          <div class="col-md-4">
            <label class="form-label">Firma del receptor (IPS)</label>
            <div id="firmaReceptorIPS" class="signature-pad-container mb-2">
              <canvas class="signature-pad" data-pen-color="#0b3d91"></canvas>
            </div>
            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="receptorIPS">Limpiar Firma</button>
              <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="receptorIPS">Ampliar</button>
            </div>
            <input type="hidden" name="firma_receptor_ips" id="FirmaReceptorIPSData">
          </div>
        </div>

      </div>
        <!-- ADJUNTOS -->
      <div class="form-section adjuntos">
        <div class="section-header">
          <h3>Adjuntos <span class="hl7-tag">FHIR: DocumentReference</span></h3>
        </div>
            <div class="mb-6">
            <label for="adjuntos" class="form-label">Adjuntar archivos (imágenes o PDF) - Max 10</label>
            <input type="file" class="form-control" id="adjuntos" name="adjuntos[]" accept="image/*,application/pdf" capture="environment" multiple>
            <small class="text-muted">
              Puedes seleccionar varias imágenes desde tu galería o tomar fotos directamente con la cámara. Los archivos se subirán automáticamente.
            </small>
            </div>
        <div class="progress mt-3 d-none" id="progress-container">
          <div class="progress-bar progress-bar-aph" id="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
          </div>
        </div>
      <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="aceptacion" name="aceptacion">
          <label class="form-check-label" for="aceptacion">Revisé que la información proporcionada es correcta.</label>
      </div>
      <div class="final-submit-bar">
        <button type="submit" class="btn btn-lg btn-primary">Registrar la atención</button>
      </div>
      <div id="contadorTiempo" class="mt-3 text-center contador-tiempo"></div>
       <input type="hidden" id="consent_type" name="consent_type" value=""/>
      </div>
<!--     < / f o r m > -->
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
<?php include __DIR__ . '/../footer.php'; ?>