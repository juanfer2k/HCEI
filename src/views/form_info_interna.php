<section id="info_interna" class="form-step" data-step="1">
    <div class="form-container form-section internal">
        <div class="section-header">
            <h3>Información Interna <span class="hl7-tag">HL7: PV1</span> <span class="hl7-tag">FHIR: Encounter</span></h3>
        </div>
        <div class="mb-3">
            <label class="form-label text-uppercase fw-semibold small text-secondary mb-2">Servicio prestado</label>
            <div class="tabtam-toggle" id="tabtamToggle" role="group" aria-label="Selector TAB TAM">
                <button type="button" class="btn-tabtam btn-tabtam--tab active btn-tipo-servicio" data-servicio="TAB" aria-pressed="true">
                    <span class="btn-tabtam__code">TAB</span>
                    <span class="btn-tabtam__label">Traslado Básico</span>
                </button>
                <button type="button" class="btn-tabtam btn-tabtam--tam btn-tipo-servicio" data-servicio="TAM" aria-pressed="false">
                    <span class="btn-tabtam__code">TAM</span>
                    <span class="btn-tabtam__label">Traslado Medicalizado</span>
                </button>
                <button type="button" class="btn-tabtam btn-tabtam--aph btn-tipo-servicio" data-servicio="APH" aria-pressed="false">
                    <span class="btn-tabtam__code">APH</span>
                    <span class="btn-tabtam__label">Atención Prehospitalaria</span>
                </button>
            </div>
            <input type="hidden" id="servicio" name="servicio" value="TAB">
        </div>
        <div class="row mb-1">
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
            <div class="col-md-4">
                <label for="tipo_ingreso" class="form-label">Tipo de ingreso</label>
                <select class="form-select" id="tipo_ingreso" name="tipo_ingreso">
                    <option value="urgencias" selected>Urgencias</option>
                    <option value="programado">Programado</option>
                    <option value="remitido">Remitido</option>
                    <option value="referencia">Referencia</option>
                    <option value="otro">Otro</option>
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
                    <option value="Transito">Transito</option>
                    <option value="Comunidad">Comunidad</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="finalidad_servicio" class="form-label">Finalidad del Servicio (RIPS)</label>
                <select class="form-select" id="finalidad_servicio" name="finalidad_servicio">
                    <option value="" selected>Seleccione...</option>
                    <option value="Consulta">Consulta</option>
                    <option value="Urgencia">Urgencia</option>
                    <option value="Hospitalizacion">Hospitalizaci&oacute;n</option>
                    <option value="Procedimiento">Procedimiento</option>
                    <option value="Transporte">Transporte</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-4">
                <label for="viene_remitida" class="form-label">¿Viene remitida?</label>
                <select class="form-select" id="viene_remitida" name="viene_remitida">
                    <option value="0" selected>No</option>
                    <option value="1">Sí</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="codigo_prestador_remitente" class="form-label">Código prestador remitente</label>
                <input type="text" class="form-control" id="codigo_prestador_remitente" name="codigo_prestador_remitente" placeholder="Ej. 7600123456">
            </div>
            <div class="col-md-4">
                <label for="codigo_despacho_crue" class="form-label">Código despacho CRUE</label>
                <input type="text" class="form-control" id="codigo_despacho_crue" name="codigo_despacho_crue" placeholder="CRUE que despacha">
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-4">
                <label for="codigo_crue_solicita" class="form-label">Código CRUE que solicita</label>
                <input type="text" class="form-control" id="codigo_crue_solicita" name="codigo_crue_solicita" placeholder="CRUE solicitante">
            </div>
        </div>
        <div class="row mb-2">
        </div>
        <!-- Hora de despacho e hora final se gestionarán fuera del wizard -->
        <input type="hidden" id="hora_despacho" name="hora_despacho" value="">
        <input type="hidden" id="hora_despacho" name="hora_despacho" value="">
        <input type="hidden" id="hora_final" name="hora_final" value="">
        
        <!-- Campos Res. 2284: Datos del Traslado -->
        <div class="row mb-2 p-2 border rounded bg-light">
            <div class="col-12 mb-1"><h6 class="text-muted small">Datos del Traslado (Res. 2284)</h6></div>
            <div class="col-md-3">
                <label for="triage_escena" class="form-label">Triage en Escena</label>
                <select class="form-select" id="triage_escena" name="triage_escena">
                    <option value="">Seleccione...</option>
                    <option value="1">1 - Rojo (Crítico)</option>
                    <option value="2">2 - Naranja (Emergencia)</option>
                    <option value="3">3 - Amarillo (Urgencia)</option>
                    <option value="4">4 - Verde (Menor)</option>
                    <option value="5">5 - Azul (No Urgente)</option>
                    <option value="Muerto">Negro (Sin signos vitales)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="hora_salida_escena" class="form-label">Hora Salida Escena</label>
                <div class="input-group">
                    <input type="time" class="form-control" id="hora_salida_escena" name="hora_salida_escena">
                    <button type="button" class="btn btn-outline-secondary btn-set-now" data-target="hora_salida_escena" title="Ahora"><i class="bi bi-clock"></i></button>
                </div>
            </div>
            <div class="col-md-3">
                <label for="codigo_cups_traslado" class="form-label">Código CUPS Traslado</label>
                <input type="text" class="form-control" id="codigo_cups_traslado" name="codigo_cups_traslado" placeholder="Ej. S23301">
            </div>
            <div class="col-md-3">
                <label for="codigo_reps_origen" class="form-label">Cód. REPS Origen</label>
                <input type="text" class="form-control" id="codigo_reps_origen" name="codigo_reps_origen" placeholder="Si aplica">
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-md-6">
                <label for="conductor" class="form-label">Conductor</label>
                <input type="text" class="form-control" id="conductor" name="conductor">
            </div>
            <div class="col-md-3">
                <label for="tipo_id_conductor" class="form-label">Tipo ID Conductor</label>
                <select class="form-select" id="tipo_id_conductor" name="tipo_id_conductor">
                    <option value="CC" selected>CC</option>
                    <option value="CE">CE</option>
                    <option value="Pasaporte">Pasaporte</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="cc_conductor" class="form-label">Número ID Conductor</label>
                <input type="text" class="form-control" id="cc_conductor" name="cc_conductor">
            </div>
            <div class="col-md-3">
                <label for="registro_conductor" class="form-label">Registro Profesional (Opcional)</label>
                <input type="text" class="form-control" id="registro_conductor" name="registro_conductor" placeholder="Si aplica">
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="tripulante" class="form-label">Tripulante</label>
                <input type="text" class="form-control" id="tripulante_display" value="<?= htmlspecialchars(trim(($tripulante_data['nombres'] ?? '') . ' ' . ($tripulante_data['apellidos'] ?? ''))) ?>" readonly>
                <input type="hidden" name="tripulante_hidden" id="tripulante_hidden" value="<?= htmlspecialchars(trim(($tripulante_data['nombres'] ?? '') . ' ' . ($tripulante_data['apellidos'] ?? ''))) ?>">
            </div>
            <div class="col-md-6">
                <div class="row">
                    <div class="col-md-6">
                        <label for="tipo_id_tripulante" class="form-label">Tipo ID Tripulante</label>
                        <select class="form-select" id="tipo_id_tripulante" name="tipo_id_tripulante">
                            <option value="CC" selected>CC</option>
                            <option value="Registro">Registro</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="cc_tripulante" class="form-label">Número ID Tripulante</label>
                        <input type="text" class="form-control" id="cc_tripulante" name="cc_tripulante" value="<?= htmlspecialchars($tripulante_data['id_cc'] ?? '') ?>">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <label for="registro_tripulante" class="form-label">Registro Profesional Tripulante (Opcional)</label>
                        <input type="text" class="form-control" id="registro_tripulante" name="registro_tripulante" placeholder="Si aplica">
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-2" id="medico-tripulante-container" style="display: none;">
            <div class="col-md-6">
                <label for="medico_tripulante" class="form-label">Médico Tripulante</label>
                <input type="text" class="form-control" id="medico_tripulante" name="medico_tripulante">
            </div>
            <div class="col-md-6">
                <div class="row">
                    <div class="col-md-6">
                        <label for="tipo_id_medico" class="form-label">Tipo ID Médico</label>
                        <select class="form-select" id="tipo_id_medico" name="tipo_id_medico">
                            <option value="Registro Medico" selected>Registro Médico</option>
                            <option value="CC">CC</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="cc_medico" class="form-label">Número ID Médico Tripulante</label>
                        <input type="text" class="form-control" id="cc_medico" name="cc_medico">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <label for="registro_medico" class="form-label">Registro Médico (Opcional)</label>
                        <input type="text" class="form-control" id="registro_medico" name="registro_medico" placeholder="Si aplica">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
