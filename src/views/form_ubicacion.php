<section id="ubicacion" class="form-step" data-step="2">
    <div class="form-container form-section location">
        <div class="section-header">
            <h3>Ubicación de la atención</h3>
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
            </div>
            <div class="col-md-3 mt-2">
                <label for="cod_municipio_servicio_display" class="form-label">Código DANE Municipio</label>
                <input type="text" class="form-control" id="cod_municipio_servicio_display" readonly>
                <input type="hidden" id="cod_municipio_servicio" name="codigo_municipio_servicio" value="">
            </div>
            <div class="col-md-3 mt-2">
                <label for="cod_depto_servicio_display" class="form-label">Departamento</label>
                <input type="text" class="form-control" id="cod_depto_servicio_display" readonly>
                <input type="hidden" id="cod_depto_servicio" name="codigo_departamento_servicio" value="">
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
        </div>
    </div>
</section>
