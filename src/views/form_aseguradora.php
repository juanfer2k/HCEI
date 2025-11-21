<section id="aseguradora" class="form-step" data-step="3">
    <!-- IDENTIFICACIÓN DE LA ASEGURADORA (SOAT/ADRES) -->
    <div id="datos-soat" class="form-container form-section insurance" style="display: none;">
        <div class="section-header">
            <h3>Datos para Aseguradora <span class="hl7-tag">HL7: IN1</span> <span class="hl7-tag">FHIR: Coverage</span></h3>
        </div>
        <div class="row mb-2 g-3">
            <div id="aseguradora-arl-container" class="col-md-6" style="display: none;">
                <label for="aseguradora_arl_select" class="form-label">Aseguradora ARL</label>
                <select id="aseguradora_arl_select" name="aseguradora_arl_select" class="form-select" style="width:100%">
                    <option value="">-- Seleccione Aseguradora ARL --</option>
                    <option value="Seguros Bolivar">Seguros Bolivar</option>
                    <option value="SURA">SURA</option>
                    <option value="Positiva">Positiva</option>
                    <option value="Liberty">Liberty</option>
                    <option value="Equidad">Equidad</option>
                    <option value="Mapfre">Mapfre</option>
                    <option value="Colmena">Colmena</option>
                    <option value="otra">Otra (especificar)</option>
                </select>
                <div class="mt-2" id="aseguradora_arl_manual_container" style="display: none;">
                    <input type="text" id="aseguradora_arl_manual" name="aseguradora_arl_manual" class="form-control form-control-sm" placeholder="Si no está en la lista, escriba el nombre aquí">
                </div>
            </div>
            <div id="fecha-vencimiento-arl-container" class="col-md-6" style="display: none;">
                <label for="fecha_vencimiento_poliza_arl" class="form-label">Fecha vencimiento póliza ARL</label>
                <input type="date" id="fecha_vencimiento_poliza_arl" name="fecha_vencimiento_poliza_arl" class="form-control">
            </div>
            <div class="col-md-6" id="placa_paciente_group" style="display: none;">
                <label for="placa_paciente" class="form-label">Placa vehículo (paciente)</label>
                <input type="text" class="form-control" id="placa_paciente" name="placa_paciente" placeholder="ABC123">
            </div>
            <div id="aseguradora-soat-container" class="col-md-4">
                <label for="aseguradora_soat" class="form-label">Aseguradora (SOAT / ADRES)</label>
                <select class="form-select" id="aseguradora_soat" name="aseguradora_soat">
                    <option value="">-- Seleccione Aseguradora --</option>
                    <option value="SURA">SURA</option>
                    <option value="Allianz">Allianz</option>
                    <option value="Axa Colpatria">Axa Colpatria</option>
                    <option value="Seguros Bolivar">Seguros Bolivar</option>
                    <option value="La Previsora">La Previsora</option>
                    <option value="La Equidad">La Equidad</option>
                    <option value="Mapfre">Mapfre</option>
                    <option value="Seguros del Estado">Seguros del Estado</option>
                    <option value="ADRES">ADRES</option>
                    <option value="Otra">Otra (especificar)</option>
                </select>
                <input type="text" class="form-control mt-2" id="aseguradora_soat_otra" name="aseguradora_soat_otra" placeholder="Especifique otra aseguradora" style="display:none;">
            </div>
            
            <div class="col-12 mt-2" id="datos-accidente-container">
                <fieldset class="border p-3 rounded">
                    <legend class="float-none w-auto px-2">Datos del Accidente</legend>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="conductor_accidente" class="form-label">Conductor involucrado</label>
                            <input type="text" class="form-control" id="conductor_accidente" name="conductor_accidente">
                        </div>
                        <div class="col-md-4">
                            <label for="tipo_documento_conductor_accidente" class="form-label">Tipo Documento</label>
                            <select class="form-select" id="tipo_documento_conductor_accidente" name="tipo_documento_conductor_accidente">
                                <option value="CC" selected>CC</option>
                                <option value="CE">CE</option>
                                <option value="Pasaporte">Pasaporte</option>
                                <option value="TI">TI</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="documento_conductor_accidente" class="form-label">Número Documento</label>
                            <input type="text" class="form-control" id="documento_conductor_accidente" name="documento_conductor_accidente">
                        </div>
                        <div class="col-md-6">
                            <label for="tarjeta_propiedad_accidente" class="form-label">Tarjeta de propiedad</label>
                            <input type="text" class="form-control" id="tarjeta_propiedad_accidente" name="tarjeta_propiedad_accidente">
                        </div>
                        <div class="col-md-6">
                            <label for="placa_vehiculo_involucrado" class="form-label">Placa vehículo (involucrado)</label>
                            <input type="text" class="form-control" id="placa_vehiculo_involucrado" name="placa_vehiculo_involucrado">
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    </div>
</section>
