<section id="soporte_terapeutico" class="form-step" data-step="10">
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
</section>
