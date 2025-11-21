/**
 * form-logic.js - Control de flujo y condiciones del formulario
 * (sin interferir con form-signatures.js)
 */
(function () {
  'use strict';

  // --- Escala de Downton ---
  function getCheckedNumber(name) {
    const el = document.querySelector(`input[name="${name}"]:checked`);
    return el ? Number(el.value || 0) : 0;
  }
  function calcDowntonTotal() {
    const sum = getCheckedNumber('downton_caidas')
      + getCheckedNumber('downton_mental')
      + getCheckedNumber('downton_medicamentos')
      + getCheckedNumber('downton_deficit')
      + getCheckedNumber('downton_deambulacion');
    const totalBox = document.getElementById('downton_total');
    const hidden = document.getElementById('downton_total_hidden');
    const riesgo = document.getElementById('downton_riesgo');

    // Guardar valor numérico en campo hidden
    if (hidden) hidden.value = sum;

    // Determinar nivel de riesgo
    const riesgoText = sum >= 3 ? 'Riesgo alto de caídas' : 'Riesgo bajo de caídas';
    const riesgoClass = sum >= 3 ? 'mt-2 text-danger fw-bold' : 'mt-2 text-success fw-bold';

    // Mostrar número + riesgo en el display
    if (totalBox) {
      totalBox.textContent = sum + ' puntos (' + (sum >= 3 ? 'Riesgo Alto' : 'Riesgo Bajo') + ')';
    }

    // Mostrar alerta de riesgo
    if (riesgo) {
      riesgo.textContent = riesgoText;
      riesgo.className = riesgoClass;
    }
  }
  document.addEventListener('change', e => {
    if (e.target && e.target.classList.contains('downton-check')) calcDowntonTotal();
  });

  // --- Tipo de servicio ---
  function isTAMSelected() {
    const sel = document.getElementById('servicio');
    if (!sel) return false;
    const v = sel.value || '';
    return v === 'TAM' || /medicalizado/i.test(v);
  }
  function isAPHSelected() {
    const sel = document.getElementById('servicio');
    if (!sel) return false;
    const v = (sel.value || '').toString().toLowerCase();
    return v.includes('aph') || v.includes('prehospitalaria');
  }

  function toggleTAM() {
    const cont = document.getElementById('medico-tripulante-container') || document.getElementById('camposTAM');
    if (!cont) return;
    cont.style.display = isTAMSelected() ? '' : 'none';
  }
  const servicioSel = document.getElementById('servicio');
  if (servicioSel) servicioSel.addEventListener('change', toggleTAM);

  // --- Consentimiento (aceptación / desistimiento) ---
  function setConsentMode(mode) {
    const accept = mode === 'ACEPTACION';
    const ctnA = document.getElementById('consentimiento-container');
    const ctnD = document.getElementById('desistimiento-container');
    const btnA = document.getElementById('btn-aceptar-atencion');
    const btnD = document.getElementById('btn-rechazar-atencion');
    const hiddenMode = document.getElementById('consent_type');
    if (hiddenMode) hiddenMode.value = mode;
    if (ctnA) ctnA.style.display = accept ? '' : 'none';
    if (ctnD) ctnD.style.display = accept ? 'none' : '';
    if (btnA) { btnA.classList.add('active'); if (btnD) btnD.classList.remove('active'); }
    if (btnD && !accept) { btnD.classList.add('active'); if (btnA) btnA.classList.remove('active'); }
  }

  const btnAceptar = document.getElementById('btn-aceptar-atencion');
  const btnRechazar = document.getElementById('btn-rechazar-atencion');
  if (btnAceptar) btnAceptar.addEventListener('click', () => setConsentMode('ACEPTACION'));
  if (btnRechazar) btnRechazar.addEventListener('click', () => setConsentMode('DESISTIMIENTO'));

  // --- Inicialización ---
  document.addEventListener('DOMContentLoaded', function () {
    calcDowntonTotal();
    toggleTAM();
    setupNameConcatenation();
  });

  // --- Auto-concatenación de nombres ---
  function setupNameConcatenation() {
    // Médico Receptor: concatenar nombres y apellidos
    const medicoFields = [
      'primer_nombre_medico_receptor',
      'segundo_nombre_medico_receptor',
      'primer_apellido_medico_receptor',
      'segundo_apellido_medico_receptor'
    ];

    medicoFields.forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field) {
        field.addEventListener('input', updateMedicoReceptorName);
        field.addEventListener('blur', updateMedicoReceptorName);
      }
    });

    // Receptor Admin IPS: concatenar nombres y apellidos
    const adminFields = [
      'primer_nombre_receptor_admin',
      'segundo_nombre_receptor_admin',
      'primer_apellido_receptor_admin',
      'segundo_apellido_receptor_admin'
    ];

    adminFields.forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field) {
        field.addEventListener('input', updateReceptorAdminName);
        field.addEventListener('blur', updateReceptorAdminName);
      }
    });
  }

  function updateMedicoReceptorName() {
    const primerNombre = (document.getElementById('primer_nombre_medico_receptor')?.value || '').trim();
    const segundoNombre = (document.getElementById('segundo_nombre_medico_receptor')?.value || '').trim();
    const primerApellido = (document.getElementById('primer_apellido_medico_receptor')?.value || '').trim();
    const segundoApellido = (document.getElementById('segundo_apellido_medico_receptor')?.value || '').trim();

    const nombreCompleto = [primerNombre, segundoNombre, primerApellido, segundoApellido]
      .filter(part => part.length > 0)
      .join(' ');

    const hiddenField = document.getElementById('nombre_medico_receptor');
    if (hiddenField) {
      hiddenField.value = nombreCompleto;
    }
  }

  function updateReceptorAdminName() {
    const primerNombre = (document.getElementById('primer_nombre_receptor_admin')?.value || '').trim();
    const segundoNombre = (document.getElementById('segundo_nombre_receptor_admin')?.value || '').trim();
    const primerApellido = (document.getElementById('primer_apellido_receptor_admin')?.value || '').trim();
    const segundoApellido = (document.getElementById('segundo_apellido_receptor_admin')?.value || '').trim();

    const nombreCompleto = [primerNombre, segundoNombre, primerApellido, segundoApellido]
      .filter(part => part.length > 0)
      .join(' ');

    const hiddenField = document.getElementById('nombre_receptor_admin_ips');
    if (hiddenField) {
      hiddenField.value = nombreCompleto;
    }
  }

  // Exportar funciones globales necesarias para form-signatures.js
  window.isAPHSelected = isAPHSelected;
  window.isTAMSelected = isTAMSelected;
})();

