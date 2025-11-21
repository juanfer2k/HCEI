<section id="clinico" class="form-step" data-step="7">
    <!-- ESCALA DE DOWNTON -->
    <div class="form-container form-section clinical" id="downton-container">
        <div class="section-header">
          <h3>Escala de Riesgo de Caídas (Downton)</h3>
        </div>
        <input type="hidden" name="downton_total" id="downton_total">
        <div class="row">
          <div class="col-md-3">
            <h5>Caidas Previas</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_caidas" value="0" checked> No (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_caidas" value="1"> Si (1)</label></div>
            <h5 class="mt-3">Estado Mental</h5>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_mental" value="0" checked> Orientado (0)</label></div>
            <div class="form-check"><label class="form-check-label w-100"><input class="form-check-input downton-check" type="radio" name="downton_mental" value="1"> Confuso (1)</label></div>
          </div>
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
            <label class="form-label fw-bold">Total Escala Downton:</label>
            <div id="downton_total_display" class="fs-4 fw-bold">0</div>
            <input type="hidden" name="downton_total" id="downton_total_hidden">
            <p id="downton_riesgo" class="mt-2 fw-bold"></p>
        </div>
    </div>

    <!-- DIAGNÓSTICO Y MOTIVO -->
    <div class="form-container form-section context">
        <div class="section-header">
          <h3>Contexto Clínico <span class="hl7-tag">FHIR: Condition</span></h3>
        </div>
        <div class="row">
            <div class="col-md-12" id="diagnostico-container">
                <label for="diagnostico_principal" class="form-label">Diagnostico Principal (CIE-10)</label>
                <select class="form-control" id="diagnostico_principal" name="diagnostico_principal" style="width: 100%;"></select>
                <input type="text" class="form-control mt-2 detalle-readonly" id="diagnostico_principal_display" readonly placeholder="Descripcion del diagnostico">
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
                    <label class="form-check-label" for="menos-3"><3 segundos</label>
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
                <label for="examen_fisico" class="form-label fw-bold">Describa el Examen Fisico</label>
                <textarea class="form-control" id="examen_fisico" name="examen_fisico" rows="3"></textarea>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-12">
                <label for="procedimientos" class="form-label fw-bold">Procedimientos</label>
                <textarea class="form-control" id="procedimientos" name="procedimientos" rows="3"></textarea>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-12">
                <label for="consumo_servicio" class="form-label fw-bold">Consumo durante el Servicio</label>
                <textarea class="form-control" id="consumo_servicio" name="consumo_servicio" rows="3"></textarea>
            </div>
        </div>
    </div>
</section>
