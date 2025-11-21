<section id="paciente" class="form-step" data-step="4">
    <div class="form-container form-section patient">
        <div class="section-header">
            <h3>Identificación del Paciente <span class="hl7-tag">HL7: PID</span> <span class="hl7-tag">FHIR: Patient</span></h3>
        </div>
        <div class="row mb-2">
            <div class="col-md-3">
                <label for="primer_nombre_paciente" class="form-label">Primer Nombre</label>
                <input type="text" class="form-control" id="primer_nombre_paciente" name="primer_nombre_paciente" autocomplete="given-name">
            </div>
            <div class="col-md-3">
                <label for="segundo_nombre_paciente" class="form-label">Segundo Nombre (opcional)</label>
                <input type="text" class="form-control" id="segundo_nombre_paciente" name="segundo_nombre_paciente" autocomplete="additional-name">
            </div>
            <div class="col-md-3">
                <label for="primer_apellido_paciente" class="form-label">Primer Apellido</label>
                <input type="text" class="form-control" id="primer_apellido_paciente" name="primer_apellido_paciente" autocomplete="family-name">
            </div>
            <div class="col-md-3">
                <label for="segundo_apellido_paciente" class="form-label">Segundo Apellido (opcional)</label>
                <input type="text" class="form-control" id="segundo_apellido_paciente" name="segundo_apellido_paciente" autocomplete="additional-name">
            </div>
        </div>
        <input type="hidden" id="nombres_paciente" name="nombres_paciente">
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="tipo_identificacion" class="form-label">Tipo de Identificacion</label>
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
                <label for="id_paciente" class="form-label">Numero de Identificacion</label>
                <input type="text" class="form-control" id="id_paciente" name="id_paciente">
            </div>
            <div class="col-md-4">
                <label for="genero_nacer" class="form-label">Genero al Nacer</label>
                <select class="form-select" id="genero_nacer" name="genero_nacer">
                    <option value="Masculino">Masculino</option>
                    <option value="Femenino" selected="selected">Femenino</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="fecha-nacimiento-display" class="form-label">Fecha de Nacimiento (DD-MM-YYYY)</label>
                <input type="text" class="form-control" id="fecha_nacimiento_display" placeholder="DD-MM-YYYY">
                <input type="hidden" name="fecha_nacimiento" id="fecha_nacimiento_hidden">
                <input type="text" class="form-control mt-1" id="edad_paciente" name="edad_paciente" placeholder="Edad" readonly>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-6" id="eps-container">
                <label for="eps_nombre" class="form-label">Nombre de la EPS</label>
                <select class="form-select" id="eps_nombre" name="eps_nombre">
                    <option value="" selected>Seleccione una EPS...</option>
                    <?php foreach ($lista_eps as $eps): ?>
                        <option value="<?= htmlspecialchars($eps) ?>"><?= htmlspecialchars($eps) ?></option>
                    <?php endforeach; ?>
                    <option value="Ninguna">Ninguna</option>
                </select>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-4">
                <label for="tipo_usuario" class="form-label">Tipo de Usuario (RIPS)</label>
                <select class="form-select" id="tipo_usuario" name="tipo_usuario">
                    <option value="" selected>Seleccione...</option>
                    <option value="Contributivo">Contributivo</option>
                    <option value="Subsidiado">Subsidiado</option>
                    <option value="Vinculado">Vinculado</option>
                    <option value="Particular">Particular</option>
                    <option value="SOAT">SOAT</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="tipo_afiliacion" class="form-label">Tipo de Afiliaci&oacute;n</label>
                <select class="form-select" id="tipo_afiliacion" name="tipo_afiliacion">
                    <option value="" selected>Seleccione...</option>
                    <option value="Cotizante">Cotizante</option>
                    <option value="Beneficiario">Beneficiario</option>
                    <option value="Adicional">Adicional</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="numero_afiliacion" class="form-label">N&uacute;mero de Afiliaci&oacute;n</label>
                <input type="text" class="form-control" id="numero_afiliacion" name="numero_afiliacion">
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-md-6">
                <label for="direccion_domicilio" class="form-label">Direccion Domicilio</label>
                <div class="input-group">
                    <input type="text" class="form-control address-input" id="direccion_domicilio" name="direccion_domicilio" autocomplete="street-address">
                    <button type="button" class="btn btn-outline-primary register-location-btn" data-target="direccion_domicilio" data-feedback="direccion_domicilio_feedback">
                        Registrar ubicacion
                    </button>
                </div>
                <div class="form-text" id="direccion_domicilio_feedback"></div>
            </div>
            <div class="col-md-6">
                <label for="barrio_paciente" class="form-label">Barrio del Paciente</label>
                <input type="text" class="form-control" id="barrio_paciente" name="barrio_paciente">
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-md-4">
                <label for="hay_acompanante" class="form-label">¿Hay acompañante?</label>
                <select class="form-select" id="hay_acompanante" name="hay_acompanante">
                    <option value="0" selected>No</option>
                    <option value="1">Sí</option>
                </select>
            </div>
        </div>

        <div class="row mb-2" id="acompanante-container" style="display:none;">
            <div class="col-md-4">
                <label for="nombre_acompanante" class="form-label">Nombre del Acompañante</label>
                <input type="text" class="form-control" id="nombre_acompanante" name="nombre_acompanante">
            </div>
            <div class="col-md-4">
                <label for="parentesco_acompanante" class="form-label">Parentesco</label>
                <input type="text" class="form-control" id="parentesco_acompanante" name="parentesco_acompanante">
            </div>
            <div class="col-md-2">
                <label for="tipo_id_acompanante" class="form-label">Tipo ID</label>
                <select class="form-select" id="tipo_id_acompanante" name="tipo_id_acompanante">
                    <option value="CC" selected>CC</option>
                    <option value="CE">CE</option>
                    <option value="TI">TI</option>
                    <option value="Pasaporte">Pasaporte</option>
                    <option value="RC">RC</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="id_acompanante" class="form-label">Número ID</label>
                <input type="text" class="form-control" id="id_acompanante" name="id_acompanante">
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="municipio_paciente" class="form-label">Municipio de Residencia del paciente</label>
                <select class="form-select" id="municipio_paciente" name="municipio_paciente" style="width: 100%;"></select>
            </div>
            <div class="col-md-3">
                <label for="cod_municipio_residencia_display" class="form-label">Código DANE Municipio</label>
                <input type="text" class="form-control" id="cod_municipio_residencia_display" readonly>
                <input type="hidden" id="cod_municipio_residencia" name="cod_municipio_residencia" value="">
            </div>
            <div class="col-md-3">
                <label for="cod_depto_residencia_display" class="form-label">Departamento</label>
                <input type="text" class="form-control" id="cod_depto_residencia_display" readonly>
                <input type="hidden" id="cod_departamento_residencia" name="cod_departamento_residencia" value="">
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-6">
                <label for="estado_afiliacion" class="form-label">Estado de Afiliaci&oacute;n</label>
                <select class="form-select" id="estado_afiliacion" name="estado_afiliacion">
                    <option value="" selected>Seleccione...</option>
                    <option value="Activa">Activa</option>
                    <option value="Suspendida">Suspendida</option>
                    <option value="Retirada">Retirada</option>
                    <option value="Desconocida">Desconocida</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="telefono_paciente" class="form-label">Telefono del Paciente</label>
                <input type="tel" class="form-control" id="telefono_paciente" name="telefono_paciente">
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-4">
                <label for="atencion_en" class="form-label">Atencion en</label>
                <select class="form-select" id="atencion_en" name="atencion_en" required>
                    <option value="Via Publica">Via Publica</option>
                    <option value="Evento">Evento</option>
                    <option value="Centro Hospitalario">Centro Hospitalario</option>
                    <option value="Plantel Educativo">Plantel Educativo</option>
                    <option value="Trabajo">Trabajo</option>
                    <option value="Residencia">Residencia</option>
                    <option value="Ciclovia">Ciclovia</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="etnia" class="form-label">Enfoque Diferencial: Pertenencia Étnica</label>
                <select class="form-select" id="etnia" name="etnia" required>
                    <option value="Otro">Otro (especificar)</option>
                    <option value="Indigena">Indígena</option>
                    <option value="Afrocolombiano">Afrocolombiano (incluye afrodescendientes, negros, mulatos, palenqueros)</option>
                    <option value="Raizal">Raizal del Archipiélago de San Andrés y Providencia</option>
                    <option value="Rom">Rom (Gitano)</option>
                    <option value="Mestizo">Mestizo</option>
                </select>
                <input type="text" class="form-control mt-2" id="especificar_otra" name="especificar_otra" placeholder="Si seleccionaste 'Otro', especifica aqui" style="display:none;">
            </div>
            <div class="col-md-4">
                <label for="discapacidad" class="form-label">Discapacidad</label>
                <select class="form-select" id="discapacidad" name="discapacidad">
                    <option value="Ninguna">Ninguna</option>
                    <option value="Fisica">Fisica</option>
                    <option value="Auditiva">Auditiva</option>
                    <option value="Visual">Visual</option>
                    <option value="Sordoceguera">Sordoceguera</option>
                    <option value="Intelectual">Intelectual</option>
                    <option value="Psicosocial">Psicosocial (mental)</option>
                    <option value="Multiple">Multiple</option>
                </select>
            </div>
        </div>
        
    </div>
</section>
