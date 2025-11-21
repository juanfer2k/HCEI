<section id="final" class="form-step" data-step="12">
    <!-- DATOS MÉDICO RECEPTOR / RECEPCIÓN PACIENTE -->
    <div class="form-container form-section firmas" id="recepcion-paciente-container">
        <div class="section-header">
            <h3>Datos de Recepción al Paciente <span class="hl7-tag">HL7: PV1</span> <span class="hl7-tag">FHIR: Encounter</span></h3>
        </div>
        <div class="row mb-2">
            <div class="col-md-3">
                <label for="primer_nombre_medico_receptor" class="form-label">Primer Nombre Médico</label>
                <input type="text" class="form-control" id="primer_nombre_medico_receptor" name="primer_nombre_medico_receptor">
            </div>
            <div class="col-md-3">
                <label for="segundo_nombre_medico_receptor" class="form-label">Segundo Nombre (opcional)</label>
                <input type="text" class="form-control" id="segundo_nombre_medico_receptor" name="segundo_nombre_medico_receptor">
            </div>
            <div class="col-md-3">
                <label for="primer_apellido_medico_receptor" class="form-label">Primer Apellido Médico</label>
                <input type="text" class="form-control" id="primer_apellido_medico_receptor" name="primer_apellido_medico_receptor">
            </div>
            <div class="col-md-3">
                <label for="segundo_apellido_medico_receptor" class="form-label">Segundo Apellido (opcional)</label>
                <input type="text" class="form-control" id="segundo_apellido_medico_receptor" name="segundo_apellido_medico_receptor">
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-4">
                <label for="tipo_id_medico_receptor" class="form-label">Tipo ID Médico Receptor</label>
                <select class="form-select" id="tipo_id_medico_receptor" name="tipo_id_medico_receptor">
                    <option value="Registro Medico" selected>Registro Médico</option>
                    <option value="CC">CC</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="id_medico_receptor" class="form-label">Número ID Médico Receptor</label>
                <input type="text" class="form-control" id="id_medico_receptor" name="id_medico_receptor">
            </div>
            <div class="col-md-4">
                <label for="registro_md_receptor" class="form-label">Registro Médico (Opcional)</label>
                <input type="text" class="form-control" id="registro_md_receptor" name="registro_md_receptor" placeholder="Si aplica">
            </div>
        </div>
        <!-- Hidden field to store concatenated full name -->
        <input type="hidden" name="nombre_medico_receptor" id="nombre_medico_receptor">
        
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="ips" class="form-label">Nombre de IPS Receptora (IPS Destino)</label>
                <select id="ips" name="ips_nombre" style="width: 100%;" placeholder="Buscar IPS Receptora..."><option value=""></option></select>
                <input type="hidden" name="ips_nombre_backup" id="ips_nombre">
            </div>
            <div class="col-md-2">
                <label for="ips_nit" class="form-label">NIT de IPS Receptora</label>
                <input type="text" class="form-control" id="ips_nit_display" readonly>
                <input type="hidden" name="ips_nit" id="ips_nit">
            </div>
            <div class="col-md-2">
                <label for="ips_ciudad_display" class="form-label">Ciudad IPS</label>
                <input type="text" class="form-control" id="ips_ciudad_display" readonly>
                <input type="hidden" name="municipio_ips_receptora" id="ips_ciudad">
            </div>
            <div class="col-md-2">
                <label for="codigo_reps_destino" class="form-label">Cód. REPS Destino</label>
                <input type="text" class="form-control" id="codigo_reps_destino" name="codigo_reps_destino" placeholder="Código Habilitación">
            </div>
            
            <!-- Campos Res. 2284: Cierre del Traslado -->
            <div class="col-12 mt-3 mb-2"><h6 class="text-muted border-bottom pb-1">Detalles del Cierre de Traslado (Res. 2284)</h6></div>
            
            <div class="col-md-3">
                <label for="hora_recepcion_paciente" class="form-label">Hora Recepción Médico</label>
                <div class="input-group">
                    <input type="time" class="form-control" id="hora_recepcion_paciente" name="hora_recepcion_paciente">
                    <button type="button" class="btn btn-outline-secondary btn-set-now" data-target="hora_recepcion_paciente"><i class="bi bi-clock"></i></button>
                </div>
            </div>
            <div class="col-md-3">
                <label for="estado_ingreso" class="form-label">Estado al Ingreso</label>
                <select class="form-select" id="estado_ingreso" name="estado_ingreso">
                    <option value="Vivo" selected>Vivo</option>
                    <option value="Muerto">Muerto</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="estado_final_traslado" class="form-label">Estado Final Traslado</label>
                <select class="form-select" id="estado_final_traslado" name="estado_final_traslado">
                    <option value="Vivo" selected>Vivo</option>
                    <option value="Muerto">Muerto</option>
                </select>
            </div>

            <div class="col-md-3 mt-2">
                <label for="km_inicial" class="form-label">Km Inicial</label>
                <input type="number" class="form-control" id="km_inicial" name="km_inicial" step="0.1">
            </div>
            <div class="col-md-3 mt-2">
                <label for="km_final" class="form-label">Km Final</label>
                <input type="number" class="form-control" id="km_final" name="km_final" step="0.1">
            </div>
            <div class="col-md-3 mt-2">
                <label for="distancia_recorrida" class="form-label">Distancia Recorrida (calculado)</label>
                <input type="number" class="form-control" id="distancia_recorrida" name="distancia_recorrida" step="0.1" placeholder="Auto-calculado" readonly>
            </div>
            <div class="col-md-3 mt-2">
                <label for="horas_espera" class="form-label">Horas de Espera (calculado)</label>
                <input type="number" class="form-control" id="horas_espera" name="horas_espera" step="0.1" placeholder="Auto-calculado" readonly>
            </div>
             <div class="col-md-12 mt-2">
                <label for="eventos_traslado" class="form-label">Eventos / Complicaciones durante el traslado</label>
                <textarea class="form-control" id="eventos_traslado" name="eventos_traslado" rows="2" placeholder="Registre novedades, cambios clínicos o paradas..."></textarea>
            </div>
            <div class="col-md-6 mt-2">
                <label class="form-label">Firma del Médico que Recibe</label>
                <div id="firmaMedicoReceptor" class="signature-pad-container mb-2">
                    <canvas class="signature-pad" data-pen-color="#0e6b0e"></canvas>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="medicoReceptor">Limpiar Firma</button>
                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="medicoReceptor">Ampliar</button>
                </div>
                <input type="hidden" name="firma_medico_receptor" id="FirmaMedicoReceptorData">
            </div>
            <div class="col-md-6 mt-2">
                <label class="form-label">Firma del responsable de admisiones (IPS)</label>
                <div id="firmaReceptorIPS" class="signature-pad-container mb-2">
                    <canvas class="signature-pad" data-pen-color="#0b3d91"></canvas>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="receptorIPS">Limpiar Firma</button>
                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#signatureModal" data-sig-target="receptorIPS">Ampliar</button>
                </div>
                <input type="hidden" name="firma_receptor_ips" id="FirmaReceptorIPSData">
                <!-- Datos del responsable de admisiones junto a la firma -->
                <div class="row mt-2 g-2">
                    <div class="col-md-3">
                        <label for="primer_nombre_receptor_admin" class="form-label">Primer Nombre</label>
                        <input type="text" class="form-control" id="primer_nombre_receptor_admin" name="primer_nombre_receptor_admin">
                    </div>
                    <div class="col-md-3">
                        <label for="segundo_nombre_receptor_admin" class="form-label">Segundo Nombre (opc.)</label>
                        <input type="text" class="form-control" id="segundo_nombre_receptor_admin" name="segundo_nombre_receptor_admin">
                    </div>
                    <div class="col-md-3">
                        <label for="primer_apellido_receptor_admin" class="form-label">Primer Apellido</label>
                        <input type="text" class="form-control" id="primer_apellido_receptor_admin" name="primer_apellido_receptor_admin">
                    </div>
                    <div class="col-md-3">
                        <label for="segundo_apellido_receptor_admin" class="form-label">Segundo Apellido (opc.)</label>
                        <input type="text" class="form-control" id="segundo_apellido_receptor_admin" name="segundo_apellido_receptor_admin">
                    </div>
                </div>
                <div class="row mt-2 g-2">
                    <div class="col-md-6">
                        <label for="tipo_documento_receptor_admin_ips" class="form-label">Tipo de documento</label>
                        <select class="form-select" id="tipo_documento_receptor_admin_ips" name="tipo_documento_receptor_admin_ips">
                            <option value="CC" selected>CC - Cédula de ciudadanía</option>
                            <option value="CE">CE - Cédula de extranjería</option>
                            <option value="PA">PA - Pasaporte</option>
                            <option value="TI">TI - Tarjeta de identidad</option>
                            <option value="NIT">NIT - Número de identificación tributaria</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="id_receptor_admin_ips" class="form-label">Número de documento</label>
                        <input type="text" class="form-control" id="id_receptor_admin_ips" name="id_receptor_admin_ips" autocomplete="off">
                    </div>
                </div>
                <!-- Hidden field to store concatenated full name -->
                <input type="hidden" name="nombre_receptor_admin_ips" id="nombre_receptor_admin_ips">
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
              <label class="form-label" id="medico-firma-label">Médico Tripulante</label>
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
    </div>

    

    <div class="mb-3 form-check mt-4">
        <input type="checkbox" class="form-check-input" id="aceptacion" name="aceptacion">
        <label class="form-check-label" for="aceptacion">Revisé que la información proporcionada es correcta.</label>
    </div>
    <input type="hidden" id="consent_type" name="consent_type" value=""/>
</section>
