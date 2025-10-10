<?php
session_start();

// ====== 1. Hash de la contraseña (solo se genera una vez) ======
// Puedes generar el hash en un archivo aparte y luego pegarlo aquí.
$hashGuardado = password_hash("sesion+25", PASSWORD_DEFAULT);

// ====== 2. Validar si el usuario ya está logueado ======
if (!isset($_SESSION['acceso'])) {
    // Si el usuario aún no ha ingresado la contraseña
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['password'])) {
        $passwordIngresada = $_POST['password'] ?? '';

        if (password_verify($passwordIngresada, $hashGuardado)) {
            // Contraseña correcta -> damos acceso
            $_SESSION['acceso'] = true;
            header("Location: " . $_SERVER['PHP_SELF']); // Redirigir para limpiar el POST
            exit;
        } else {
            $error = "❌ Contraseña incorrecta";
        }
    }
}

require_once('conn.php');

// --- Si llegamos aquí, el usuario ESTÁ AUTENTICADO ---
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
$lista_eps = [
    "ALIANSALUD EPS S.A.",
    "ALIANZA MEDELLIN ANTIOQUIA EPS S.A.S. \"SAVIA SALUD EPS\"",
    "ASMET SALUD EPS S.A.S.",
    "ASOCIACION INDÍGENA DEL CAUCA A.I.C. EPSI",
    "ASOCIACION MUTUAL SER EMPRESA SOLIDARIA DE SALUD ENTIDAD PROMOTORA DE SALUD - MUTUAL SER EPS",
    "ASOCIACIÓN DE CABILDOS INDÍGENAS DEL CESAR Y GUAJIRA \"DUSAKAWI A.R.S.I.\"",
    "CAJA DE COMPENSACIÓN FAMILIAR COMPENSAR",
    "CAJA DE COMPENSACION FAMILIAR DEL VALLE DEL CAUCA \"COMFENALCO VALLE DE LA GENTE\"",
    "CAJA DE COMPENSACIÓN FAMILIAR DEL CHOCÓ",
    "CAJA DE COMPENSACIÓN FAMILIAR DEL ORIENTE COLOMBIANO \"COMFAORIENTE\"",
    "CAJACOPI EPS S.A.S",
    "CAPITAL SALUD ENTIDAD PROMOTORA DE SALUD DEL RÉGIMEN SUBSIDIADO SAS \"CAPITAL SALUD EPS-S S.A.S.\"",
    "CAPRESOCA E.P.S.",
    "COOSALUD EPS S.A.",
    "EMPRESA PROMOTORA DE SALUD INDÍGENA ANAS WAYUU EPSI",
    "EMPRESAS PUBLICAS DE MEDELLIN - DEPARTAMENTO MEDICO",
    "EMSSANAR S.A.S.",
    "ENTIDAD PROMOTORA DE SALUD MALLAMAS EPSI",
    "ENTIDAD PROMOTORA DE SALUD SANITAS S.A.S.",
    "ENTIDAD PROMOTORA DE SALUD SERVICIO OCCIDENTAL DE SALUD S.A. S.O.S.",
    "EPS FAMILIAR DE COLOMBIA S.A.S.",
    "EPS FAMISANAR S.A.S.",
    "EPS SURAMERICANA S.A.",
    "FONDO PASIVO SOCIAL DE LOS FERROCARRILES NACIONALES",
    "FUNDACIÓN SALUD MIA",
    "NUEVA EPS S.A.",
    "PIJAOS SALUD EPSI",
    "SALUD BOLÍVAR EPS SAS",
    "SALUD TOTAL ENTIDAD PROMOTORA DE SALUD DEL REGIMEN CONTRIBUTIVO Y DEL REGIMEN SUBSIDIADO S.A."
];
sort($lista_eps);
?>
<?php if (!isset($_SESSION['acceso'])): ?>
    <!DOCTYPE html>
    <html lang="es" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <title>Acceso Protegido - AVIS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Ingrese la contraseña</h3>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña:</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Entrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php exit; // Detenemos la ejecución para no mostrar el formulario principal ?>
<?php endif; ?>
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
  .form-section.internal { background-image: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); } /* Azul claro - Profesional y clínico */
  .form-section.insurance { background-image: linear-gradient(135deg, #f1f8e9 0%, #dcedc8 100%); } /* Verde claro - Trámite, papeleo */
  .form-section.patient { background-image: linear-gradient(135deg, #e8eaf6 0%, #c5cae9 100%); } /* Índigo suave - Centrado en el paciente */
  .form-section.context { background-image: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); } /* Naranja suave - Contexto de la emergencia */
  .form-section.clinical { background-image: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%); } /* Verde azulado - Datos médicos */
  .form-section.glasgow { background-image: linear-gradient(135deg, #fffde7 0%, #fff59d 100%); } /* Amarillo - Alerta y evaluación crítica */
  .form-section.antecedentes { background-image: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%); } /* Rosa suave - Historial médico */
  .form-section.firmas { background-image: linear-gradient(135deg, #eceff1 0%, #cfd8dc 100%); } /* Gris - Formalidad y legalidad */
  .form-section.adjuntos { background-image: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); } /* Gris claro - Neutral */
  .form-section label { font-weight: 500; }
  .form-section .form-control, .form-section .form-select { min-width: 100%; }
  .section-header { background: none; border-bottom: 2px solid #34446d; margin-bottom: 10px; padding-bottom: 8px; }
  .section-header { background: none; border-bottom: 2px solid #34446d; margin-bottom: 10px; padding-top: 5px; padding-bottom: 5px; }
  .section-header h3 {
    font-size: 1rem; /* Tamaño de fuente más pequeño */
    margin-bottom: 0; /* Eliminar margen inferior del h3 */
  } .form-container h2 { font-size: 1rem; }
</style>
<?php
$pageTitle = "Registro de Atención y Traslado de Pacientes";
include 'header.php'; // Incluimos el header solo si el formulario principal se va a mostrar
?>
  <div class="container">
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
             <option value="Móvil 1 JYQ-312" selected="selected">Móvil 1 JYQ-312</option>
              <option value="Móvil 835 HAX-835">Móvil 835 HAX-835</option>
              <option value="Móvil 199 HMK-199">Móvil 199 HMK-199</option>
              <option value="Móvil 777 OVE-187">Móvil 187 OVE-187</option>
              <option value="Móvil 187 CEW-154">Móvil 154 CEW-154</option>
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
          <div class="col-md-3">
            <label for="hora_despacho" class="form-label">Hora de Despacho</label>
            <input type="time" class="form-control" id="hora_despacho" name="hora_despacho" placeholder="HH:MM">
          </div> 
          <div class="col-md-3">
            <label for="hora_llegada" class="form-label">Hora de Llegada</label>
            <input type="time" class="form-control" id="hora_llegada" name="hora_llegada" placeholder="HH:MM">
          </div>
   <!--     </div>
        <div class="row mb-3"> -->
          <div class="col-md-3">
            <label for="hora_ingreso" class="form-label">Hora de Ingreso del Paciente</label>
            <input type="time" class="form-control" id="hora_ingreso" name="hora_ingreso" placeholder="HH:MM">
          </div>
          <div class="col-md-3">
            <label for="hora_final" class="form-label">Hora Final</label>
            <input type="time" class="form-control" id="hora_final" name="hora_final" placeholder="HH:MM">
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
            <input type="text" class="form-control" id="tripulante" name="tripulante">
          </div>
          <div class="col-md-3">
            <label for="tipo_id_tripulante" class="form-label">Tipo ID Tripulante</label>
            <select class="form-select" id="tipo_id_tripulante" name="tipo_id_tripulante">
                <option value="CC">CC</option>
                <option value="Registro">Registro</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="cc_tripulante" class="form-label">Número ID</label>
            <input type="text" class="form-control" id="cc_tripulante" name="cc_tripulante">
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
                <option value="CC">CC</option>
                <option value="Registro">Registro</option>
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
      <button type="button" class="btn btn-outline-primary" onclick="obtenerUbicacion()">Usar mi ubicación</button>
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
    <label for="tipo_traslado" class="form-label">Traslado Ida y Vuelta</label>
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
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="4"> Espontánea (4)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="3" checked> Al lenguaje verbal (3)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="2"> Al dolor (2)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="ocular" value="1"> Sin respuesta (1)</label></div>
          </div>
          <div class="col-md-4">
            <h5>Respuesta Verbal</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="5"> Orientado (5)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="4"> Confuso (4)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="3" checked> Palabras inapropiadas (3)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="2"> Sonidos incomprensibles (2)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="verbal" value="1"> Sin respuesta (1)</label></div>
          </div>
          <div class="col-md-4">
            <h5>Respuesta Motora</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="6"> Obedece órdenes (6)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="5"> Localiza el dolor (5)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="4"> Retirada al dolor (4)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="3" checked> Flexión anormal (3)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="2"> Extensión anormal (2)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input glasgow-check" type="radio" name="motora" value="1"> Sin respuesta (1)</label></div>
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
      
      <!-- DATOS MÉDICO RECEPTOR -->
      <div class="form-container form-section firmas">
        <div class="section-header">
            <h3>Datos del Médico Receptor</h3>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="nombre_medico_receptor" class="form-label required-field">Nombre del Médico Receptor</label>
                <input type="text" class="form-control" id="nombre_medico_receptor" name="nombre_medico_receptor" required>
            </div>
            <div class="col-md-3">
                <label for="tipo_id_medico_receptor" class="form-label required-field">Tipo ID</label>
                <select class="form-select" id="tipo_id_medico_receptor" name="tipo_id_medico_receptor" required>
                    <option value="CC">CC</option>
                    <option value="Registro Médico">Registro Médico</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="id_medico_receptor" class="form-label required-field">Número ID</label>
                <input type="text" class="form-control" id="id_medico_receptor" name="id_medico_receptor" required>
            </div>
        </div>
      </div>
      <!-- FIRMAS Y ACEPTACIÓN -->
      <div class="form-container form-section firmas">
        <div class="section-header">
<h3>Firmas y Aceptación <span class="hl7-tag">FHIR: Consent</span></h3>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Tripulante</label>
            <div id="firmaParamedico" class="signature-pad-container mb-2">
              <canvas class="signature-pad"></canvas>
            </div>
            <button type="button" class="btn btn-sm btn-danger" data-tipo="paramedico">Limpiar Firma</button>
            <input type="hidden" name="firma_paramedico" id="FirmaParamedicoData">
          </div>
          <div class="col-md-6" id="medico-tripulante-firma-container" style="display: none;">
            <label class="form-label"> Médico Tripulante (si aplica)</label>
            <div id="firmaMedico" class="signature-pad-container mb-2">
              <canvas class="signature-pad"></canvas>
            </div>
            <button type="button" class="btn btn-sm btn-danger" data-tipo="medico">Limpiar Firma</button>
            <input type="hidden" name="firma_medico" id="FirmaMedicoData">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label"> Paciente o Representante</label>
            <div id="firmaPaciente" class="signature-pad-container mb-2">
              <p style="padding: 7px; text-align: justify;"><em class="text-muted">Como firmante, mayor de edad, obrando en nombre propio y
en mi condición de Acompañante o testigo , por medio del presente documento, manifiesto que he autorizado la
atención, aplicación de los tratamiento propuestos por el personal de salud y en caso de ser necesario el traslado en
ambulancia, así mismo, autorizo a que se realicen los procedimientos necesarios para la estabilización clínica. Sé y acepto
que la práctica de la medicina no es una ciencia exacta, no se me han prometido ni garantizado resultados esperados. Se
me ha dado la oportunidad de preguntar y resolver las dudas y todas ellas han sido resueltas a satisfacción. Manifiesto que
he entendido sobre las condiciones y objetivos de la atención que se me va a realizar, los cuidados que debo tener, además
comprendo y acepto el alcance y los riesgos justificados de posible previsión que conlleva el procedimiento. Teniendo en
cuenta todo lo anterior doy mi consentimiento informado libre y voluntariamente, entendiendo y aceptando todos los
riesgos, reacciones, complicaciones y resultados insatisfactorios que puedan derivarse de la misma y de los procedimientos
realizados en ella, los cuales reconozco que puedan presentarse a pesar de que se tomen las precauciones usuales para
evitarlos; me comprometo a cumplir con las recomendaciones, instrucciones y controles después del tratamiento realizado
Certifico que he tenido la oportunidad de hacer todas las preguntas pertinentes para aclarar mis dudas y se me han
respondido de manera clara y suficiente</em></p>
              <canvas class="signature-pad"></canvas>
            </div>
           <button type="button" class="btn btn-sm btn-danger" data-tipo="paciente">Limpiar Firma</button>
            <input type="hidden" name="firma_paciente" id="FirmaPacienteData">
          </div>
          <div class="col-md-6">
            <label class="form-label required-field">Médico Receptor</label>
            <div id="firmaMedicoReceptor" class="signature-pad-container mb-2">
              <canvas class="signature-pad"></canvas>
            </div>
            <button type="button" class="btn btn-sm btn-danger" data-tipo="medicoReceptor">Limpiar Firma</button>
            <input type="hidden" name="firma_medico_receptor" id="FirmaMedicoReceptorData">
          </div>
        </div>
      </div>

  </div>        <!-- ADJUNTOS -->
  <div class="form-section adjuntos">
  <div class="section-header">
<h3>Adjuntos <span class="hl7-tag">FHIR: DocumentReference</span></h3>  </div>
  <div class="mb-6">
    <label for="adjuntos" class="form-label">Adjuntar imágenes (examinar o tomar foto) - Máx 10</label>
    <input type="file" class="form-control" id="adjuntos" name="adjuntos[]" accept="image/*" multiple>
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
          <label class="form-check-label" for="aceptacion">Revisé que la información proporcionada es correcta y verídica.</label>
  </div>


       <div class="d-grid">
        <button type="submit" class="btn btn-lg btn-primary">Guardar Registro</button>
      </div><div id="contadorTiempo" class="mt-3 text-center" style="font-size: 1.2em;"></div>
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
  <nav class="navbar fixed-bottom navbar-light bg-light">
    <div class="container-fluid justify-content-end">
        <a href="logout.php" class="btn btn-outline-danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
            </svg>
            Cerrar Sesión
        </a>
    </div>
  </nav>
  <script>
  document.addEventListener("DOMContentLoaded", function () {
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
      onChange: function(selectedDates, dateStr) {
        document.getElementById("fecha_nacimiento_hidden").value = moment(dateStr, "DD-MM-YYYY").format("YYYY-MM-DD");
      }
    });
    // --- LÓGICA DE TIEMPOS AUTOMÁTICOS ---
    const llegadaPicker = flatpickr("#hora_llegada", timeConfig);
    const finalPicker = flatpickr("#hora_final", timeConfig);
    flatpickr("#hora_despacho", {
        ...timeConfig,
        onChange: function(selectedDates, dateStr, instance) {
            if (dateStr) {
                const despachoTime = moment(dateStr, "HH:mm");
                const randomMinutes = Math.floor(Math.random() * (5 - 3 + 1)) + 3; // Random entre 3 y 5
                const llegadaTime = despachoTime.add(randomMinutes, 'minutes').format("HH:mm");
                llegadaPicker.setDate(llegadaTime, false);
            }
        }
    });
    flatpickr("#hora_ingreso", {
        ...timeConfig,
        onChange: function(selectedDates, dateStr, instance) {
            if (dateStr) {
                const ingresoTime = moment(dateStr, "HH:mm");
                const randomMinutes = Math.floor(Math.random() * (25 - 15 + 1)) + 15; // Random entre 15 y 25
                const finalTime = ingresoTime.add(randomMinutes, 'minutes').format("HH:mm");
                finalPicker.setDate(finalTime, false);
            }
        }
    });
    // --- LÓGICA SECCIÓN ASEGURADORA (SOAT) ---
    const pagadorField = document.getElementById("pagador");
    const datosSoatSection = document.getElementById("datos-soat");
    const epsContainer = document.getElementById("eps-container");
    function toggleDatosSoat() {
      const selectedValue = pagadorField.value;
      if (selectedValue === "SOAT" || selectedValue === "ARL" || selectedValue === "EPS") {
        datosSoatSection.style.display = "block";
      } else {
        datosSoatSection.style.display = "none";
      }
      // Mostrar el contenedor de EPS solo si se selecciona "EPS"
      if (selectedValue === "EPS") {
        epsContainer.style.display = "flex";
      } else {
        epsContainer.style.display = "none";
        document.getElementById('eps_nombre').value = ''; // Limpia la selección si se cambia de pagador
      }
    }
    pagadorField.addEventListener("change", toggleDatosSoat);
    toggleDatosSoat(); // Estado inicial
    // --- LÓGICA SECCIÓN PACIENTE (ETNIA) ---
    document.getElementById('etnia').addEventListener('change', function() {
      document.getElementById('especificar_otra').style.display = this.value === 'Otro' ? 'block' : 'none';
    });
    // --- LÓGICA CÁLCULO IMC ---
    const pesoInput = document.getElementById("peso");
    const tallaInput = document.getElementById("talla");
    const imcInput = document.getElementById("imc");
    function calcularIMC() {
      const peso = parseFloat(pesoInput.value);
      const talla = parseFloat(tallaInput.value) / 100; // Convertir cm a metros
      imcInput.classList.remove("imc-bajo", "imc-saludable", "imc-sobrepeso", "imc-obeso", "imc-obesidad-extrema");
      if (peso > 0 && talla > 0) {
        const imc = (peso / (talla * talla)).toFixed(2);
        imcInput.value = imc;
        if (imc < 18.5) { imcInput.classList.add("imc-bajo"); }
        else if (imc >= 18.5 && imc <= 24.9) { imcInput.classList.add("imc-saludable"); }
        else if (imc >= 25 && imc <= 29.9) { imcInput.classList.add("imc-sobrepeso"); }
        else if (imc >= 30 && imc <= 39.9) { imcInput.classList.add("imc-obeso"); }
        else if (imc >= 40) { imcInput.classList.add("imc-obesidad-extrema"); }
      } else {
        imcInput.value = "";
      }
    }
    pesoInput.addEventListener("input", calcularIMC);
    tallaInput.addEventListener("input", calcularIMC);
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
      let valid = true;
      const nombreMedicoReceptor = $('#nombre_medico_receptor').val();
      const idMedicoReceptor = $('#id_medico_receptor').val();
      if (!nombreMedicoReceptor || !idMedicoReceptor) {
          valid = false;
          alert('Por favor, complete los datos del médico receptor.');
      }
      const requiredTimeFields = ['#hora_despacho', '#hora_llegada', '#hora_ingreso', '#hora_final'];
      requiredTimeFields.forEach(field => {
        const value = $(field).val();
        if (!value || !moment(value, "HH:mm", true).isValid()) {
          valid = false;
          alert(`Por favor, ingrese un valor válido para ${$(field).closest('.col-md-3').find('label').text()}`);
        }
      });
      const requiredSignatures = ['#FirmaParamedicoData', '#FirmaPacienteData', '#FirmaMedicoReceptorData'];
      requiredSignatures.forEach(field => {
        if (!$(field).val()) {
          valid = false;
          const label = $(field).closest('.col-md-6').find('label').text().trim();
          alert(`Por favor, complete la firma requerida: ${label}`);
        }
      });
      if (!valid) e.preventDefault();
    });
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
    const firmas = {
      paramedico: new SignaturePad(document.querySelector('#firmaParamedico canvas'), { penColor: '#34446d' }),
      medico: new SignaturePad(document.querySelector('#firmaMedico canvas'), { penColor: '#a25505' }),
      paciente: new SignaturePad(document.querySelector('#firmaPaciente canvas'), { penColor: '#000' }),
      medicoReceptor: new SignaturePad(document.querySelector('#firmaMedicoReceptor canvas'), { penColor: '#0e6b0e' })
    };
    const updateSignatureData = (tipo) => {
      const firmaPad = firmas[tipo];
      const input = document.getElementById('Firma' + tipo.charAt(0).toUpperCase() + tipo.slice(1) + 'Data');
      if (firmaPad.isEmpty()) {
        input.value = ''; // Si está vacía, el valor es nulo
      } else {
        input.value = firmaPad.toDataURL(); // Si no, se guarda la imagen
      }
    };
    Object.keys(firmas).forEach(tipo => {
      firmas[tipo].onEnd = () => updateSignatureData(tipo);
    });
    $('[data-tipo]').on('click', function() {
      const tipo = $(this).data('tipo');
      firmas[tipo].clear();
      updateSignatureData(tipo); // Actualizar el input a vacío
    });

    // --- LÓGICA CONDICIONAL MÉDICO TRIPULANTE ---
    const servicioField = document.getElementById('servicio');
    const medicoTripulanteContainer = document.getElementById('medico-tripulante-container');
    const medicoTripulanteFirmaContainer = document.getElementById('medico-tripulante-firma-container');

    function toggleMedicoTripulante() {
        const esTAM = servicioField.value === 'Traslado Medicalizado';
        medicoTripulanteContainer.style.display = esTAM ? 'flex' : 'none';
        medicoTripulanteFirmaContainer.style.display = esTAM ? 'block' : 'none';

        if (!esTAM) {
            // Limpiar campos si no es TAM
            document.getElementById('medico_tripulante').value = '';
            document.getElementById('tipo_id_medico').value = 'CC';
            document.getElementById('cc_medico').value = '';
            if (firmas.medico && !firmas.medico.isEmpty()) {
                firmas.medico.clear();
                updateSignatureData('medico');
            }
        }
    }
    servicioField.addEventListener('change', toggleMedicoTripulante);
    toggleMedicoTripulante(); // Llamada inicial
  }); // Fin de DOMContentLoaded
  </script>
  <script>
function obtenerUbicacion() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      const lat = position.coords.latitude;
      const lon = position.coords.longitude;
      // Puedes usar una API de geocodificación para convertir lat/lon a dirección
      document.getElementById('direccion_servicio').value = `Lat: ${lat}, Lon: ${lon}`;
      // Si quieres la dirección real, usa una API como Google Maps Geocoding aquí
    }, function(error) {
      alert('No se pudo obtener la ubicación.');
    });
  } else {
    alert('Geolocalización no soportada por tu navegador.');
  }
}
</script>
<?php include 'footer.php'; ?>