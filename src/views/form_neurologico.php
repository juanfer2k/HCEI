<section id="neurologico" class="form-step" data-step="5">
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
          <label class="form-label fw-bold">Total Escala Glasgow:</label>
          <input type="number" class="form-control text-center" id="escala_glasgow" name="escala_glasgow" readonly style="font-size: 2rem; font-weight: bold;">
        </div>
    </div>
</section>
