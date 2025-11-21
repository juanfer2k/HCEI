<section id="step4" class="form-step">
  <h2 class="section-title">Consentimiento, Firmas y Cierre Legal</h2>

  <!-- Botones de aceptación / desistimiento -->
  <div class="mb-4 text-center">
    <p class="fw-semibold mb-2">Seleccione el tipo de consentimiento:</p>
    <div class="btn-group" role="group">
      <button type="button" id="btn-aceptar-atencion" class="btn btn-success">✅ Aceptación</button>
      <button type="button" id="btn-rechazar-atencion" class="btn btn-danger">❌ Desistimiento</button>
    </div>
    <input type="hidden" name="consent_type" id="consent_type" value="">
  </div>

  <!-- IPS receptora -->
  <div class="mb-4">
    <label class="form-label">IPS receptora:</label>
    <select id="nombre_ips_receptora" name="nombre_ips_receptora" class="form-select select2">
      <option value="">Seleccione una IPS...</option>
    </select>
  </div>

  <!-- Firma del Tripulante / Paramedico -->
  <div id="firmaParamedico" class="signature-container mb-4">
    <label class="form-label">Firma del Tripulante</label>
    <canvas class="signature-pad border rounded w-100"></canvas>
    <div class="mt-2">
      <button type="button" class="btn btn-outline-secondary clear-signature">Limpiar</button>
    </div>
    <input type="hidden" name="firmas[paramedico][contenido]" id="FirmaParamedicoData">
  </div>

  <!-- Firma del Paciente -->
  <div id="consentimiento-container" class="mb-4">
    <label class="form-label">Firma del Paciente / Acompañante</label>
    <canvas class="signature-pad border rounded w-100"></canvas>
    <div class="mt-2">
      <button type="button" class="btn btn-outline-secondary clear-signature">Limpiar</button>
    </div>
    <input type="hidden" name="firmas[paciente][contenido]" id="FirmaPacienteData">
  </div>

  <!-- Firma de Desistimiento -->
  <div id="desistimiento-container" class="mb-4" style="display:none;">
    <label class="form-label text-danger">Firma de Desistimiento</label>
    <canvas class="signature-pad border rounded w-100"></canvas>
    <div class="mt-2">
      <button type="button" class="btn btn-outline-secondary clear-signature">Limpiar</button>
    </div>
    <input type="hidden" name="firmas[desistimiento][contenido]" id="FirmaDesistimientoData">
  </div>

  <!-- Firma Médico Receptor -->
  <div id="medico-receptor-firma-container" class="mb-4">
    <label class="form-label">Firma del Médico Receptor (solo IPS / ADRES / SOAT)</label>
    <canvas class="signature-pad border rounded w-100"></canvas>
    <div class="mt-2">
      <button type="button" class="btn btn-outline-secondary clear-signature">Limpiar</button>
    </div>
    <input type="hidden" name="firmas[medico_receptor][contenido]" id="FirmaMedicoReceptorData">
  </div>

  <!-- Firma Representante Legal -->
  <div id="firmaReceptorIPS" class="signature-container mb-4" style="display:none;">
    <label class="form-label">Firma del Representante Legal (solo ADRES)</label>
    <canvas class="signature-pad border rounded w-100"></canvas>
    <div class="mt-2">
      <button type="button" class="btn btn-outline-secondary clear-signature">Limpiar</button>
    </div>
    <input type="hidden" name="firmas[representante_legal][contenido]" id="FirmaRepresentanteLegalData">
  </div>

  <!-- Botones de navegación -->
  <div class="d-flex justify-content-between mt-4">
    <button type="button" class="btn btn-secondary btn-prev" data-step="4">← Anterior</button>
    <button type="button" class="btn btn-success" id="btnFinalizar">Finalizar Atención</button>
  </div>
</section>
