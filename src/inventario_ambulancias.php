﻿<?php
$pageTitle = 'Inventario Ambulancias V4';
require_once __DIR__ . '/header.php';
$inventarioConfig = require __DIR__ . '/inventario_config.php';
$tipoInicial = isset($_GET['tipo']) && in_array(strtoupper($_GET['tipo']), ['TAM', 'TAB'], true)
    ? strtoupper($_GET['tipo'])
    : 'TAB';
$usuarioActual = $_SESSION['usuario_nombre'] ?? 'desconocido';
?>
<div class="inventory-container" data-tipo="<?php echo $tipoInicial; ?>">
<?php
?>
<style>
  .inventory-container {
    --color-tam-base: rgb(216, 15, 23);
    --color-tab-base: rgb(13, 110, 253);
  }

  /* Define color schemes for each type */
  .inventory-container[data-tipo="TAM"] {
    --inventory-base: var(--color-tam-base);
    --inventory-color: var(--color-tam);
    --inventory-bg-soft: color-mix(in srgb, var(--color-tam-base) 8%, var(--bs-body-bg, #ffffff));
    --inventory-border: color-mix(in srgb, var(--color-tam-base) 20%, var(--bs-body-bg, #ffffff));
    --inventory-text: var(--color-tam);
  }

  .inventory-container[data-tipo="TAB"] {
    --inventory-base: var(--color-tab-base);
    --inventory-color: var(--color-tab);
    --inventory-bg-soft: color-mix(in srgb, var(--color-tab-base) 8%, var(--bs-body-bg, #ffffff));
    --inventory-border: color-mix(in srgb, var(--color-tab-base) 20%, var(--bs-body-bg, #ffffff));
    --inventory-text: var(--color-tab);
  }

  /* Header styles */
  .inventory-header {
    background: var(--inventory-bg-soft);
    color: var(--inventory-color);
    border: 1px solid var(--inventory-border);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 6px 18px rgba(18, 58, 99, 0.08);
  }

  .inventory-header h2 {
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: var(--inventory-color);
  }

  .inventory-header p {
    color: color-mix(in srgb, var(--inventory-color) 75%, var(--bs-body-bg, #ffffff));
    margin: 0;
    font-size: 0.9rem;
  }

  /* Button styles */
  .inventory-container .btn-primary {
    min-width: 150px;
    background-color: var(--inventory-base);
    border-color: var(--inventory-base);
    color: white;
  }

  .inventory-container .btn-primary:hover,
  .inventory-container .btn-primary:active {
    background-color: color-mix(in srgb, var(--inventory-base) 85%, #000000);
    border-color: color-mix(in srgb, var(--inventory-base) 85%, #000000);
  }

  .inventory-container .btn-outline-primary {
    color: var(--inventory-base);
    border-color: var(--inventory-base);
  }

  .inventory-container .btn-outline-primary:hover {
    background-color: var(--inventory-base);
    color: white;
  }
  /* TAM selector button: fixed strong red, independent of current tipo */
  .inventory-toggle-tam {
    color: #ef4444;
    border-color: #ef4444;
  }
  .inventory-toggle-tam:hover,
  .inventory-toggle-tam:active {
    background-color: #ef4444;
    border-color: #ef4444;
    color: #ffffff;
  }
  .dark-theme [data-tipo="TAM"],
  [data-bs-theme="dark"] [data-tipo="TAM"] {
    --inventory-bg-soft: color-mix(in srgb, var(--color-tam) 15%, var(--bs-body-bg, #212529));
    --inventory-border: color-mix(in srgb, var(--color-tam) 30%, var(--bs-body-bg, #212529));
  }
  .dark-theme [data-tipo="TAB"],
  [data-bs-theme="dark"] [data-tipo="TAB"] {
    --inventory-bg-soft: color-mix(in srgb, var(--color-tab) 15%, var(--bs-body-bg, #212529));
    --inventory-border: color-mix(in srgb, var(--color-tab) 30%, var(--bs-body-bg, #212529));
  }
  .history-card .card-header {
    background: #eef4fb;
    color: #123a63;
    font-weight: 600;
    border-bottom: none;
  }
  .history-card .card-body {
    max-height: 220px;
    overflow-y: auto;
    background: #ffffff;
  }
  .history-card .list-group-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #e0e6ef;
    border-radius: 10px;
    margin-bottom: 0.75rem;
    padding: 0.75rem 0.9rem;
    background: #fbfdff;
  }
  .history-card .list-group-item:last-child {
    margin-bottom: 0;
  }
  .inventory-card {
    border-radius: 14px;
    border: 1px solid #dde6f2;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(18, 58, 99, 0.08);
  }
  .inventory-card .card-body {
    padding: 1.5rem;
  }
  .section-card {
    border: 1px solid #e2e9f4;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    background: #ffffff;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(18, 58, 99, 0.05);
  }
  .section-card h5 {
    padding: 0.9rem 1.25rem;
    margin: 0;
    background: #f2f7fc;
    color: #123a63;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }
  .section-card .table-responsive {
    padding: 0 1rem 1rem;
  }
  .inventory-table thead th {
    background: #eef4fb;
    color: #123a63;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid #d9e5f4;
  }
  .inventory-table tbody td {
    vertical-align: middle;
    font-size: 0.85rem;
    color: #1b2f4a;
    background: #ffffff;
  }
  .inventory-table input,
  .inventory-table textarea,
  .inventory-table select {
    border-radius: 8px;
    border: 1px solid #cad6e6;
    background: #fdfefe;
  }
  .estado-toggle {
    display: inline-flex;
    gap: 0.35rem;
    background: #f4f7fb;
    padding: 0.25rem;
    border-radius: 999px;
  }
  .estado-toggle input[type="radio"] {
    display: none;
  }
  .estado-toggle label {
    cursor: pointer;
    padding: 0.32rem 0.9rem;
    border-radius: 999px;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #516b86;
    border: 1px solid transparent;
    transition: all 0.2s ease;
  }
  .estado-toggle input[type="radio"]:checked + label {
    border-color: #0d6efd;
    background: rgba(13, 110, 253, 0.12);
    color: #0d50af;
    font-weight: 600;
  }
  .action-buttons {
    display: flex;
    gap: 0.35rem;
    justify-content: center;
  }
  .action-buttons .btn {
    padding: 0.25rem 0.5rem;
  }
  #autoSaveStatus {
    font-size: 0.8rem;
    color: #516b86;
  }
  #autoSaveStatus.error {
    color: #c0392b;
  }
  @media (max-width: 992px) {
    .inventory-header {
      text-align: center;
    }
    .inventory-actions {
      justify-content: center !important;
    }
  }
  @media print {
    body {
      background: #ffffff !important;
      color: #000000 !important;
    }
    .no-print {
      display: none !important;
    }
    .inventory-header,
    .section-card,
    .inventory-card {
      box-shadow: none !important;
      border: 1px solid #333;
    }
    .inventory-table thead th {
      background: #f0f0f0 !important;
      color: #000 !important;
    }
  }
</style>
<div class="container-fluid py-4">
  <div class="inventory-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3" data-tipo="<?php echo $tipoInicial; ?>">
    <div>
      <h2 class="h4 mb-0">Inventario de Ambulancias - Version 4</h2>
      <p>Resolucion 9279 de 1993 · Formatos TAM y TAB con auditorIa integrada</p>
    </div>
    <div class="btn-group btn-group-lg" role="group" aria-label="Selector de tipo">
      <input type="radio" class="btn-check" name="tipoSelector" id="tipoTam" autocomplete="off" value="TAM" <?= $tipoInicial === 'TAM' ? 'checked' : '' ?>>
      <label class="btn btn-outline-primary inventory-toggle-tam" for="tipoTam">Inventario TAM</label>
      <input type="radio" class="btn-check" name="tipoSelector" id="tipoTab" autocomplete="off" value="TAB" <?= $tipoInicial === 'TAB' ? 'checked' : '' ?>>
      <label class="btn btn-outline-primary" for="tipoTab">Inventario TAB</label>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12">
      <div class="card inventory-card theme-<?= strtolower($tipoInicial) ?>">
        <div class="card-body">
          <form id="inventarioForm" class="needs-validation" novalidate>
            <input type="hidden" name="tipo" id="tipo" value="<?= htmlspecialchars($tipoInicial) ?>">
            <input type="hidden" name="form_uuid" id="form_uuid" value="">

            <div class="row g-3 align-items-end mb-4">
              <div class="col-md-4">
                <label class="form-label">Placa</label>
                <select class="form-select form-select-sm" id="placa" required>
                  <option value="" selected disabled>Seleccione una ambulancia</option>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label">Descripcion</label>
                <input type="text" class="form-control form-control-sm" id="descripcionAmbulancia" placeholder="Movil / placa" readonly>
              </div>
              <div class="col-md-3 col-lg-2">
                <label class="form-label">Fecha</label>
                <input type="date" class="form-control form-control-sm" id="fecha" required>
              </div>
            </div>

            <div id="seccionesContainer"></div>

            <div class="inventory-actions d-flex flex-wrap gap-3 justify-content-between align-items-center mt-4">
              <div id="autoSaveStatus" class="text-muted">Aun no se ha guardado informacion.</div>
              <div class="d-flex flex-wrap gap-3 no-print">
                <button type="button" class="btn btn-outline-secondary" id="btnNuevo"><i class="bi bi-file-earmark-plus me-1"></i>Nuevo</button>
                <button type="button" class="btn btn-primary" id="btnGuardar"><i class="bi bi-save me-1"></i>Guardar ahora</button>
                <button type="button" class="btn btn-outline-success" id="btnXls" disabled><i class="bi bi-filetype-xls me-1"></i>XLS</button>

              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card history-card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Historial guardado</span>
          <button class="btn btn-sm btn-outline-primary" type="button" id="btnRecargarListado"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        <div class="card-body">
          <div id="listadoRegistros" class="list-group"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const INVENTARIO_CONFIG = <?php echo json_encode($inventarioConfig, JSON_PRETTY_PRINT); ?>;
  const USUARIO_ACTUAL = "<?= htmlspecialchars($usuarioActual, ENT_QUOTES, 'UTF-8') ?>";
  const MEDICATION_SECTION = 'Medicamentos y soluciones';
  const SURGICAL_SECTION = 'Material quirurgico y de curacion';
  const AUDIT_SECTION = 'Campos de auditoria interna';
  const estadoLabels = [
    { value: 'bueno', label: 'Bueno' },
    { value: 'regular', label: 'Regular' },
    { value: 'malo', label: 'Malo' }
  ];
  let rowsByKey = new Map();
  let isLoading = false;
  let autoSaveTimer = null;
  const AUTO_SAVE_DELAY = 1200;

  const formEl = document.getElementById('inventarioForm');
  const seccionesContainer = document.getElementById('seccionesContainer');
  const btnGuardar = document.getElementById('btnGuardar');
  const btnNuevo = document.getElementById('btnNuevo');
  const btnXls = document.getElementById('btnXls');
  // const btnImprimir = document.getElementById('btnImprimir');
  const btnRecargar = document.getElementById('btnRecargarListado');
  const listadoRegistros = document.getElementById('listadoRegistros');
  const tipoSelector = document.querySelectorAll('input[name="tipoSelector"]');
  const tipoInput = document.getElementById('tipo');
  const formUuidInput = document.getElementById('form_uuid');
  const placaSelect = document.getElementById('placa');
  const inventoryCard = document.querySelector('.inventory-card');
  const descripcionAmbulancia = document.getElementById('descripcionAmbulancia');
  const fechaInput = document.getElementById('fecha');
  const autoSaveStatus = document.getElementById('autoSaveStatus');

  function todayISO() {
    return new Date().toISOString().slice(0, 10);
  }

  function setFechaHoy() {
    fechaInput.value = todayISO();
  }

  function clearAutoSaveTimer() {
    if (autoSaveTimer) {
      clearTimeout(autoSaveTimer);
      autoSaveTimer = null;
    }
  }

  function scheduleAutoSave() {
    if (isLoading || !formReadyForSave()) {
      return;
    }
    clearAutoSaveTimer();
    autoSaveTimer = setTimeout(() => {
      guardarInventario({ silent: true }).catch(() => {});
    }, AUTO_SAVE_DELAY);
  }

  function formReadyForSave() {
    return Boolean(placaSelect.value);
  }

  function registerRow(row) {
    const key = `${row.dataset.seccion}::${row.dataset.codigo}`;
    if (!rowsByKey.has(key)) {
      rowsByKey.set(key, []);
    }
    rowsByKey.get(key).push(row);
  }

  function unregisterRow(row) {
    const key = `${row.dataset.seccion}::${row.dataset.codigo}`;
    if (!rowsByKey.has(key)) return;
    const filtered = rowsByKey.get(key).filter(item => item !== row);
    rowsByKey.set(key, filtered);
  }

  function renderEstadoToggles(groupId, defaultValue = 'bueno') {
    return estadoLabels.map(({ value, label }) => {
      const inputId = `${groupId}-${value}`;
      const checked = value === defaultValue ? 'checked' : '';
      return `
        <div>
          <input type="radio" id="${inputId}" name="${groupId}" value="${value}" ${checked}>
          <label for="${inputId}">${label}</label>
        </div>`;
    }).join('');
  }

  let rowCounter = 0;
  function createRowId(prefix) {
    rowCounter += 1;
    return `${prefix}-${rowCounter}-${Date.now()}`;
  }

  // === PATCH: Simplified per-section column rules (PDF removed, XLS kept) === //

function createRow(item, seccion, { isClone = false } = {}) {
  const row = document.createElement('tr');
  const codigo = item.codigo;
  const nombre = item.nombre;
  const estadoGroup = `estado-${codigo}-${Date.now()}`;

  row.dataset.codigo = codigo;
  row.dataset.nombre = nombre;
  row.dataset.seccion = seccion;

  if (seccion === MEDICATION_SECTION || seccion === SURGICAL_SECTION) {
    row.dataset.cloneable = 'true';
  }

  const renderEstado = () => `
    <div class="estado-toggle">
      <div><input type="radio" id="${estadoGroup}-b" name="${estadoGroup}" value="bueno" checked><label for="${estadoGroup}-b">Bueno</label></div>
      <div><input type="radio" id="${estadoGroup}-r" name="${estadoGroup}" value="regular"><label for="${estadoGroup}-r">Regular</label></div>
      <div><input type="radio" id="${estadoGroup}-m" name="${estadoGroup}" value="malo"><label for="${estadoGroup}-m">Malo</label></div>
    </div>`;

  const renderActions = () => `
    <td class="action-buttons">
      <button type="button" class="btn btn-outline-primary btn-sm add-row" title="Agregar fila"><i class="bi bi-plus"></i></button>
      <button type="button" class="btn btn-outline-danger btn-sm remove-row" title="Eliminar fila"><i class="bi bi-dash"></i></button>
    </td>`;

  // === SECTION-SPECIFIC STRUCTURES === //
  switch (seccion) {
    case "Documentacion y elementos administrativos":
      row.innerHTML = `
        <td><strong>${nombre}</strong><div class="text-muted small">${codigo}</div></td>
        <td>${renderEstado()}</td>
        <td><textarea class="form-control form-control-sm" data-field="observaciones" rows="2"></textarea></td>
        <td><input type="date" class="form-control form-control-sm" data-field="fecha_revision"></td>`;
      break;

    case "Dotacion de cabina asistencial":
      row.innerHTML = `
        <td><strong>${nombre}</strong><div class="text-muted small">${codigo}</div></td>
        <td>${renderEstado()}</td>
        <td><input type="text" class="form-control form-control-sm" data-field="serial" placeholder="Serial / registro"></td>
        <td><textarea class="form-control form-control-sm" data-field="observaciones" rows="2"></textarea></td>`;
      break;

    case "Equipos medicos":
      row.innerHTML = `
        <td><strong>${nombre}</strong><div class="text-muted small">${codigo}</div></td>
        <td><input type="number" min="0" class="form-control form-control-sm" data-field="cantidad" value="1"></td>
        <td>${renderEstado()}</td>
        <td><input type="text" class="form-control form-control-sm" data-field="serial" placeholder="Serial / registro"></td>
        <td><input type="date" class="form-control form-control-sm" data-field="fecha_revision"></td>
        <td><textarea class="form-control form-control-sm" data-field="observaciones" rows="2"></textarea></td>`;
      break;

    case "Material quirurgico y de curacion":
    case MEDICATION_SECTION:
      row.innerHTML = `
        <td><strong>${nombre}</strong><div class="text-muted small">${codigo}</div></td>
        <td><input type="number" min="0" class="form-control form-control-sm" data-field="cantidad" value="1"></td>
        <td>${renderEstado()}</td>
        <td><input type="text" class="form-control form-control-sm" data-field="registro_invima" placeholder="Registro Invima"></td>
        <td><input type="text" class="form-control form-control-sm" data-field="lote" placeholder="Lote"></td>
        <td><input type="date" class="form-control form-control-sm" data-field="fecha_vencimiento"></td>
        <td><textarea class="form-control form-control-sm" data-field="observaciones" rows="2"></textarea></td>
        ${renderActions()}
        `;
      break;

    case "Inmovilizadores y dispositivos de traslado":
    case "Bioseguridad y aseo":
    case "Equipos de comunicacion":
      row.innerHTML = `
        <td><strong>${nombre}</strong><div class="text-muted small">${codigo}</div></td>
        <td><input type="number" min="0" class="form-control form-control-sm" data-field="cantidad" value="1"></td>
        <td>${renderEstado()}</td>
        <td><input type="text" class="form-control form-control-sm" data-field="serial" placeholder="Serial / registro"></td>
        <td><input type="date" class="form-control form-control-sm" data-field="fecha_revision"></td>
        <td><textarea class="form-control form-control-sm" data-field="observaciones" rows="2"></textarea></td>`;
      break;

    default:
      row.innerHTML = `
        <td><strong>${nombre}</strong><div class="text-muted small">${codigo}</div></td>
        <td><input type="number" min="0" class="form-control form-control-sm" data-field="cantidad" value="1"></td>
        <td>${renderEstado()}</td>
        <td><input type="text" class="form-control form-control-sm" data-field="serial"></td>
        <td><input type="text" class="form-control form-control-sm" data-field="ubicacion"></td>
        <td><textarea class="form-control form-control-sm" data-field="observaciones" rows="2"></textarea></td>`;
  }

  row.querySelectorAll('input, textarea').forEach(el => {
    el.addEventListener('input', scheduleAutoSave); el.addEventListener('change', scheduleAutoSave); });
  return row;
}

  function attachRowEvents(row) {
    row.querySelectorAll('input, textarea').forEach(el => {
      el.addEventListener('input', scheduleAutoSave);
      el.addEventListener('change', scheduleAutoSave);
    });

    if (row.dataset.cloneable === 'true') {
      const addBtn = row.querySelector('.add-row');
      const removeBtn = row.querySelector('.remove-row');
      if (addBtn) {
        addBtn.addEventListener('click', () => addMedicationRow(row));
      }
      if (removeBtn) {
        removeBtn.addEventListener('click', () => removeMedicationRow(row));
      }
    }
  }

  // === rebuild tables dynamically === //
function buildSecciones(tipo) {
  isLoading = true;
  clearAutoSaveTimer();
  rowsByKey = new Map();
  seccionesContainer.innerHTML = "";

  const configTipo = INVENTARIO_CONFIG[tipo] || {};
  Object.entries(configTipo).forEach(([seccion, items]) => {
    if (seccion === "Campos de auditoria interna") return;

    // Ocultar la sección de medicamentos si el tipo es TAB
    if (tipo === 'TAB' && seccion === MEDICATION_SECTION) {
      return;
    }

    const card = document.createElement("div");
    card.className = "section-card";
    card.innerHTML = `
      <h5>${seccion}</h5>
      <div class="table-responsive">
        <table class="table table-sm inventory-table mb-0" data-seccion="${seccion}">
          <thead></thead>
          <tbody></tbody>
        </table>
      </div>`;
    const table = card.querySelector("table");
    const thead = table.querySelector("thead");
    const tbody = table.querySelector("tbody");

    // === CUSTOM HEADERS PER SECTION === //
    switch (seccion) {
      case "Documentacion y elementos administrativos":
        thead.innerHTML = `
          <tr>
            <th style="width:30%">Documento</th>
            <th style="width:20%">Estado</th>
            <th style="width:30%">Observaciones</th>
            <th style="width:20%"></th>
          </tr>`;
        break;

      case "Dotacion de cabina asistencial":
        thead.innerHTML = `
          <tr>
            <th style="width:30%">Item</th>
            <th style="width:20%">Estado</th>
            <th style="width:25%">Identificador</th>
            <th style="width:25%">Observaciones</th>
          </tr>`;
        break;

      case "Equipos medicos":
        thead.innerHTML = `
          <tr>
            <th style="width:25%">Equipo</th>
            <th style="width:10%">Cantidad</th>
            <th style="width:20%">Estado</th>
            <th style="width:20%">Identificador</th>
            <th style="width:10%"></th>
            <th style="width:15%">Observaciones</th>
          </tr>`;
        break;

      case "Material quirurgico y de curacion":
      case MEDICATION_SECTION:
        thead.innerHTML = `
          <tr>
            <th style="width:22%">Material</th>
            <th style="width:8%">Cantidad</th>
            <th style="width:18%">Estado</th>
            <th style="width:14%">Registro Invima</th>
            <th style="width:12%">Lote</th>
            <th style="width:12%">Fecha vencimiento</th>
            <th style="width:18%">Observaciones</th>
            <th style="width:8%" class="text-center">Acciones</th>
          </tr>`; // Added Acciones header
        break;

      case "Inmovilizadores y dispositivos de traslado":
      case "Bioseguridad y aseo":
      case "Equipos de comunicacion":
        thead.innerHTML = `
          <tr>
            <th style="width:25%">Item</th>
            <th style="width:10%">Cantidad</th>
            <th style="width:20%">Estado</th>
            <th style="width:20%">Identificador</th>
            <th style="width:10%"></th>
            <th style="width:15%">Observaciones</th>
          </tr>`;
        break;

      default:
        thead.innerHTML = `
          <tr>
            <th>Item</th>
            <th>Cantidad</th>
            <th>Estado</th>
            <th>Identificador</th>
            <th>Ubicacion</th>
            <th>Observaciones</th>
          </tr>`;
    }

    // Populate rows
    (items || []).forEach(item => {
      const row = createRow(item, seccion);
      tbody.appendChild(row);
      registerRow(row);
      attachRowEvents(row); // Attach events right after creation
    });

    seccionesContainer.appendChild(card);
  });

  isLoading = false;
}

  function addMedicationRow(referenceRow, options = {}) {
    const tbody = referenceRow.closest('tbody');
    const item = {
      codigo: referenceRow.dataset.codigo,
      nombre: referenceRow.dataset.nombre
    };
    const newRow = createRow(item, referenceRow.dataset.seccion, { isClone: true });
    tbody.insertBefore(newRow, referenceRow.nextSibling);
    registerRow(newRow);
    attachRowEvents(newRow);
    if (!options.silent) {
      scheduleAutoSave();
    }
    return newRow;
  }

  function removeMedicationRow(row) {
    const tbody = row.closest('tbody');
    const sameRows = tbody.querySelectorAll(`tr[data-codigo="${row.dataset.codigo}"]`);
    if (sameRows.length <= 1) {
      row.querySelectorAll('input[data-field="cantidad"], textarea[data-field="observaciones"], input[data-field="registro_invima"], input[data-field="lote"], input[data-field="fecha_vencimiento"]').forEach(el => el.value = '');
      row.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.checked = radio.value === 'bueno';
      });
      scheduleAutoSave();
      return;
    }
    unregisterRow(row);
    row.remove();
    scheduleAutoSave();
  }

  function leerFormulario() {
    const tipo = tipoInput.value;
    const selectedOption = placaSelect.selectedOptions[0];
    const descripcion = selectedOption ? (selectedOption.dataset.descripcion || selectedOption.textContent) : '';

    const header = {
      placa: placaSelect.value,
      ambulancia: descripcion,
      fecha: fechaInput.value,
      responsable_general: USUARIO_ACTUAL
    };

    const items = [];
    seccionesContainer.querySelectorAll('table').forEach(table => {
      const seccion = table.dataset.seccion;
      table.querySelectorAll('tbody tr').forEach(row => {
        const item = {
          seccion,
          codigo: row.dataset.codigo,
          nombre: row.dataset.nombre,
          cantidad: 0,
          estado: 'bueno',
          observaciones: null,
          serial: null,
          ubicacion: null,
          fecha_revision: null
        };
        // Campos adicionales para medicamentos/quirurgicos
        item.registro_invima = null;
        item.lote = null;
        item.fecha_vencimiento = null;
        const cantidadInput = row.querySelector('[data-field="cantidad"]');
        // Si el campo cantidad no existe, se asume 1 (para secciones como Documentación)
        item.cantidad = cantidadInput ? parseInt(cantidadInput.value || '0', 10) : 1;

        const estadoInput = row.querySelector('input[type="radio"]:checked');
        if (estadoInput) item.estado = estadoInput.value;

        const observaciones = row.querySelector('[data-field="observaciones"]');
        if (observaciones) item.observaciones = observaciones.value.trim() || null;

        const serialInput = row.querySelector('[data-field="serial"]');
        if (serialInput) item.serial = serialInput.value.trim() || null;

        const ubicacionInput = row.querySelector('[data-field="ubicacion"]');
        if (ubicacionInput) item.ubicacion = ubicacionInput.value.trim() || null;

        const fechaRevision = row.querySelector('[data-field="fecha_revision"]');
        if (fechaRevision) item.fecha_revision = fechaRevision.value || null;

        const registroInvima = row.querySelector('[data-field="registro_invima"]');
        if (registroInvima) item.registro_invima = registroInvima.value.trim() || null;

        const lote = row.querySelector('[data-field="lote"]');
        if (lote) item.lote = lote.value.trim() || null;

        const fechaVenc = row.querySelector('[data-field="fecha_vencimiento"]');
        if (fechaVenc) item.fecha_vencimiento = fechaVenc.value || null;

        items.push(item);
      });
    });

    return { tipo, header, items };
  }

  async function parseJsonResponse(response) {
    const text = await response.text();
    try {
      return JSON.parse(text);
    } catch (error) {
      throw new Error(text.trim() || 'Respuesta no valida del servidor');
    }
  }

  async function guardarInventario({ silent = false } = {}) {
    if (!formReadyForSave()) {
      if (!silent) {
        alert('Seleccione una placa antes de guardar.');
      }
      return;
    }

    const payload = leerFormulario();
    payload.form_uuid = formUuidInput.value || undefined;

    const response = await fetch('inventario_guardar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await parseJsonResponse(response);
    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'Error al guardar inventario');
    }

    formUuidInput.value = data.form_uuid;
    btnXls.disabled = false;

    const now = new Date();
    const hora = now.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
    autoSaveStatus.classList.remove('error');
    autoSaveStatus.textContent = silent ? `Guardado automatico ${hora}` : `Guardado manual ${hora}`;

    if (!silent) {
      btnGuardar.classList.add('btn-success');
      setTimeout(() => btnGuardar.classList.remove('btn-success'), 1500);
    }

    await cargarListado({ silent: true });
    return data;
  }

  async function cargarListado({ silent = false } = {}) {
    listadoRegistros.innerHTML = '<div class="text-muted">Cargando...</div>';
    const tipo = tipoInput.value;

    try {
      const resp = await fetch(`inventario_listar.php?tipo=${tipo}`);
      const data = await parseJsonResponse(resp);

      listadoRegistros.innerHTML = '';
      if (!data.ok || !Array.isArray(data.registros) || data.registros.length === 0) {
        listadoRegistros.innerHTML = '<div class="text-muted">Sin registros guardados.</div>';
        return;
      }

      data.registros.forEach(reg => {
        const item = document.createElement('div');
        item.className = 'list-group-item';
        item.innerHTML = `
          <div>
            <div class="fw-semibold">${reg.ambulancia || 'Ambulancia'} <span class="badge bg-primary ms-2">${tipo}</span></div>
            <div class="small text-muted">Placa ${reg.placa || ''} · Fecha ${reg.fecha || ''}</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" data-form="${reg.form_uuid}"><i class="bi bi-eye"></i></button>
          </div>`;
        listadoRegistros.appendChild(item);
      });
    } catch (error) {
      listadoRegistros.innerHTML = `<div class="text-danger">${error.message}</div>`;
      if (!silent) {
        console.error(error);
      }
    }
  }

  function fillRowWithData(row, item) {
    const cantidad = row.querySelector('[data-field="cantidad"]');
    if (cantidad) cantidad.value = item.cantidad ?? 0;

    const estadoInput = row.querySelector(`input[type="radio"][value="${item.estado}"]`);
    if (estadoInput) estadoInput.checked = true;

    const observaciones = row.querySelector('[data-field="observaciones"]');
    if (observaciones) observaciones.value = item.observaciones || '';

    const serial = row.querySelector('[data-field="serial"]');
    if (serial) serial.value = item.serial || '';

    const ubicacion = row.querySelector('[data-field="ubicacion"]');
    if (ubicacion) ubicacion.value = item.ubicacion || '';

    const fechaRevision = row.querySelector('[data-field="fecha_revision"]');
    if (fechaRevision) fechaRevision.value = item.fecha_revision || '';

    const registroInvima = row.querySelector('[data-field="registro_invima"]');
    if (registroInvima) registroInvima.value = item.registro_invima || '';

    const lote = row.querySelector('[data-field="lote"]');
    if (lote) lote.value = item.lote || '';

    const fechaVenc = row.querySelector('[data-field="fecha_vencimiento"]');
    if (fechaVenc) fechaVenc.value = item.fecha_vencimiento || '';
  }

  async function cargarDetalle(formUuid) {
    const tipo = tipoInput.value;
    isLoading = true;
    clearAutoSaveTimer();

    try {
      const resp = await fetch(`inventario_detalle.php?tipo=${tipo}&form_uuid=${formUuid}`);
      const data = await parseJsonResponse(resp);
      if (!data.ok) {
        throw new Error(data.error || 'No fue posible obtener el detalle');
      }

      formUuidInput.value = formUuid;

      setFechaHoy();
      if (data.header) {
        if (data.header.fecha) {
          fechaInput.value = data.header.fecha;
        }
        if (data.header.placa) {
          placaSelect.value = data.header.placa;
        }
        if (data.header.ambulancia) {
          descripcionAmbulancia.value = data.header.ambulancia;
        }
      }

      buildSecciones(tipo);

      const items = Array.isArray(data.items) ? data.items : [];
      const grouped = new Map();
      items.forEach(item => {
        const key = `${item.seccion}::${item.codigo}`;
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(item);
      });

      grouped.forEach((itemList, key) => {
        const rows = rowsByKey.get(key) || [];
        const [seccion] = key.split('::');
        itemList.forEach((itemData, index) => {
          let row = rows[index];
          if (!row && seccion === MEDICATION_SECTION) {
            const baseRow = rows[0];
            if (baseRow) {
              row = addMedicationRow(baseRow, { silent: true });
              const updatedRows = rowsByKey.get(key) || [];
              row = updatedRows[updatedRows.length - 1];
            }
          }
          if (row) {
            fillRowWithData(row, itemData);
          }
        });
      });

      btnXls.disabled = false;
      autoSaveStatus.classList.remove('error');
      autoSaveStatus.textContent = 'Inventario cargado, recuerde verificar la informacion antes de imprimir.';
    } catch (error) {
      console.error(error);
      alert(error.message);
    } finally {
      isLoading = false;
    }
  }

  async function cargarAmbulancias() {
    try {
      const resp = await fetch('inventario_ambulancias_listado.php');
      const data = await parseJsonResponse(resp);
      if (!data.ok) {
        throw new Error(data.error || 'No fue posible obtener las ambulancias');
      }
      placaSelect.innerHTML = '<option value="" disabled selected>Seleccione una ambulancia</option>';
      (data.ambulancias || []).forEach(item => {
        const option = document.createElement('option');
        option.value = item.placa;
        option.textContent = item.descripcion;
        option.dataset.descripcion = item.descripcion;
        placaSelect.appendChild(option);
      });
    } catch (error) {
      console.error(error);
      placaSelect.innerHTML = '<option value="" disabled selected>No se pudieron cargar las ambulancias</option>';
    }
  }

  btnGuardar.addEventListener('click', async () => {
    btnGuardar.disabled = true;
    try {
      await guardarInventario({ silent: false });
    } catch (error) {
      autoSaveStatus.classList.add('error');
      autoSaveStatus.textContent = error.message;
      alert(error.message);
    } finally {
      btnGuardar.disabled = false;
    }
  });

  btnNuevo.addEventListener('click', () => {
    formUuidInput.value = '';
    autoSaveStatus.classList.remove('error');
    autoSaveStatus.textContent = 'Aun no se ha guardado informacion.';
    btnXls.disabled = true;
    setFechaHoy();
    descripcionAmbulancia.value = '';
    placaSelect.selectedIndex = 0;
    buildSecciones(tipoInput.value);
  });

  btnXls.addEventListener('click', () => {
    if (!formUuidInput.value) return;
    // La funcionalidad XLS se ha movido a la página de consulta.
    window.location.href = `consultar_inventario.php?form_uuid=${formUuidInput.value}`;
  });

  // imprimir deshabilitado

  btnRecargar.addEventListener('click', () => cargarListado());

  listadoRegistros.addEventListener('click', event => {
    const button = event.target.closest('button[data-form]');
    if (!button) return;
    const formUuid = button.dataset.form;
    cargarDetalle(formUuid);
  });

  tipoSelector.forEach(input => {
    input.addEventListener('change', () => {
      if (!input.checked) return;
      const tipo = input.value;
      
      // Actualizar clase para el borde de color
      if (inventoryCard) {
        inventoryCard.classList.remove('theme-tam', 'theme-tab');
        inventoryCard.classList.add(tipo === 'TAM' ? 'theme-tam' : 'theme-tab');
      }

      tipoInput.value = tipo;
      btnXls.disabled = true;
      formUuidInput.value = '';
      autoSaveStatus.classList.remove('error');
      autoSaveStatus.textContent = 'Aun no se ha guardado informacion.';

      buildSecciones(tipo);
      cargarListado();
    });
  });

  placaSelect.addEventListener('change', () => {
    const selected = placaSelect.selectedOptions[0];
    descripcionAmbulancia.value = selected ? (selected.dataset.descripcion || selected.textContent) : '';
    scheduleAutoSave();
  });

  document.addEventListener('input', event => {
    if (event.target.closest('#inventarioForm')) {
      scheduleAutoSave();
    }
  });

  // Set initial theme class
  if (inventoryCard) {
      inventoryCard.classList.add(tipoInput.value === 'TAM' ? 'theme-tam' : 'theme-tab');
  }

  setFechaHoy();
  buildSecciones(tipoInput.value);
  cargarAmbulancias().then(() => cargarListado());
</script>
</div><!-- /.inventory-container -->
<?php require_once __DIR__ . '/footer.php'; ?>
