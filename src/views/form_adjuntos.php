<section id="adjuntos" class="form-step" data-step="11">
  <div class="form-container form-section adjuntos">
    <div class="section-header">
      <h3>Adjuntos <span class="hl7-tag">FHIR: DocumentReference</span></h3>
    </div>

    <div class="mb-6">
      <label for="adjuntos_input" class="form-label">Adjuntar archivos (imágenes o PDF) - Máx 10</label>
      <input type="file" class="form-control" id="adjuntos_input" name="adjuntos[]" accept="image/*,application/pdf" multiple>
      <small class="text-muted">
        Puedes seleccionar varias imágenes desde tu galería o tomar fotos directamente con la cámara. Los archivos se subirán con el envío del formulario.
      </small>
    </div>

    <div class="progress mt-3" id="progress-container" style="display: none;">
      <div class="progress-bar" id="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
    </div>

    <div class="mt-2 small text-muted" id="adjuntos_meta">0 archivos — 0 MB</div>
    <div id="adjuntos_preview" class="row g-2 mt-2"></div>
    <div id="adjuntos_uploads" class="mt-3"></div>
  </div>
</section>