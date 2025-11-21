<section id="evento" class="form-step" data-step="5">
<div class="form-container form-section evento" id="evento-container" style="display: none;">
  <h4 class="mb-3">🚨 Datos del Accidente o Evento</h4>

  <div class="row g-3">
    <div class="col-md-6">
      <label for="tipo_evento" class="form-label">Tipo de evento</label>
      <select id="tipo_evento" name="tipo_evento" class="form-select" required>
        <option value="">Seleccione...</option>
        <option value="Accidente de tránsito">Accidente de tránsito</option>
        <option value="Evento catastrófico">Evento catastrófico</option>
        <option value="Urgencia no accidental">Urgencia no accidental</option>
        <option value="Atención domiciliaria">Atención domiciliaria</option>
        <option value="Otro">Otro</option>
      </select>
    </div>

    <div class="col-md-6">
      <label for="desc_tipo_evento" class="form-label">Descripción del evento</label>
      <input type="text" id="desc_tipo_evento" name="desc_tipo_evento" class="form-control" placeholder="Ej. Colisión moto con automóvil">
    </div>

    <div class="col-md-6">
      <label for="direccion_del_evento" class="form-label">Dirección o lugar del evento</label>
      <input type="text" id="direccion_del_evento" name="direccion_del_evento" class="form-control" placeholder="Ej. Av. Bolívar #20-15, Armenia">
    </div>

    <div class="col-md-4">
      <label for="cod_depto_recogida" class="form-label">Código DANE - Departamento</label>
      <input type="text" id="cod_depto_recogida" name="cod_depto_recogida" maxlength="10" class="form-control" placeholder="Ej. 63">
    </div>

    <div class="col-md-4">
      <label for="municipio_evento" class="form-label">Municipio del evento</label>
      <select id="municipio_evento" name="municipio_evento" class="form-select" style="width: 100%;"></select>
    </div>

    <div class="col-md-4">
      <label for="cod_ciudad_recogida" class="form-label">Código DANE - Municipio</label>
      <input type="text" id="cod_ciudad_recogida" name="cod_ciudad_recogida" maxlength="10" class="form-control" placeholder="Ej. 63001">
    </div>

    <div class="col-md-4">
      <label for="hora_traslado" class="form-label">Hora del traslado</label>
      <div class="input-group">
        <input type="time" id="hora_traslado" name="hora_traslado" class="form-control">
        <button type="button" class="btn btn-outline-secondary btn-set-now" data-target="hora_traslado">
          Ahora
        </button>
      </div>
    </div>

    <div class="col-md-6">
      <label for="condicion_victima" class="form-label">Condición de la víctima</label>
      <select id="condicion_victima" name="condicion_victima" class="form-select">
        <option value="">Seleccione...</option>
        <option value="Conductor">Conductor</option>
        <option value="Pasajero">Pasajero</option>
        <option value="Peatón">Peatón</option>
        <option value="Ciclista">Ciclista</option>
        <option value="Otro">Otro</option>
      </select>
    </div>

    <div class="col-md-6">
      <label for="desc_condicion_victima" class="form-label">Descripción condición víctima</label>
      <input type="text" id="desc_condicion_victima" name="desc_condicion_victima" class="form-control" placeholder="Ej. Pasajero trasero sin cinturón">
    </div>

    <div class="col-md-6">
      <label for="tipo_vehiculo_accidente" class="form-label">Tipo de vehículo involucrado</label>
      <select id="tipo_vehiculo_accidente" name="tipo_vehiculo_accidente" class="form-select">
        <option value="">Seleccione...</option>
        <option value="Motocicleta">Motocicleta</option>
        <option value="Automóvil">Automóvil</option>
        <option value="Camión">Camión</option>
        <option value="Bicicleta">Bicicleta</option>
        <option value="Otro">Otro</option>
      </select>
    </div>

    <div class="col-md-6">
      <label for="desc_tipo_vehiculo" class="form-label">Descripción vehículo</label>
      <input type="text" id="desc_tipo_vehiculo" name="desc_tipo_vehiculo" class="form-control" placeholder="Ej. Motocicleta Pulsar negra">
    </div>

    <div class="col-md-6">
      <label for="estado_aseguramiento" class="form-label">Estado de aseguramiento</label>
      <select id="estado_aseguramiento" name="estado_aseguramiento" class="form-select">
        <option value="">Seleccione...</option>
        <option value="Asegurado">Asegurado</option>
        <option value="No asegurado">No asegurado</option>
        <option value="Desconocido">Desconocido</option>
      </select>
    </div>

    <div class="col-md-6">
      <label for="desc_estado_aseguramiento" class="form-label">Descripción estado aseguramiento</label>
      <input type="text" id="desc_estado_aseguramiento" name="desc_estado_aseguramiento" class="form-control">
    </div>

    <div class="col-md-6">
      <label for="nombre_aseguradora" class="form-label">Nombre aseguradora</label>
      <input type="text" id="nombre_aseguradora" name="nombre_aseguradora" class="form-control" placeholder="Ej. Seguros del Estado S.A.">
    </div>

    <div class="col-md-3">
      <label for="codigo_aseguradora" class="form-label">Código aseguradora</label>
      <input type="text" id="codigo_aseguradora" name="codigo_aseguradora" maxlength="20" class="form-control">
    </div>

    <div class="col-md-3">
      <label for="numero_poliza" class="form-label">Número de póliza</label>
      <input type="text" id="numero_poliza" name="numero_poliza" class="form-control">
    </div>

    <div class="col-md-6">
      <label for="fecha_inicio_poliza" class="form-label">Fecha inicio póliza</label>
      <input type="date" id="fecha_inicio_poliza" name="fecha_inicio_poliza" class="form-control">
    </div>

    <div class="col-md-6">
      <label for="fecha_fin_poliza" class="form-label">Fecha fin póliza</label>
      <input type="date" id="fecha_fin_poliza" name="fecha_fin_poliza" class="form-control">
    </div>



  </div>
</div>
</section>