/**
 * form-bundle.v1.js — Paquete consolidado para el formulario de atención
 *
 * Combina la lógica de:
 * - form-behaviors.js (base para navegación, validación y guardado)
 * - form-logic.js (cálculo de escalas como Downton)
 * - form-init.js (inicialización de librerías como flatpickr y select2)
 *
 * Propósito:
 * - Eliminar redundancias y conflictos de scripts cargados por separado.
 * - Centralizar toda la lógica del formulario en un único archivo.
 * - Asegurar un orden de ejecución predecible.
 *
 * Requiere:
 *  - Librerías (jQuery, Bootstrap, flatpickr, select2, moment, SignaturePad) cargadas previamente.
 *  - window.AppConfig.baseUrl definido en el HTML (usualmente desde header.php).
 *  - form-signatures.js para manejar la lógica de los pads de firma (cargado por separado).
 */

(function (window, document, jQuery, moment, flatpickr) {
  'use strict';

  // --- VERIFICACIÓN DE DEPENDENCIAS ---
  function checkDependencies() {
    const dependencies = {
      'jQuery': jQuery,
      'SignaturePad': window.SignaturePad,
      'flatpickr': flatpickr,
      'moment': moment,
      'select2': jQuery && jQuery.fn.select2,
      'AppConfig': window.AppConfig
    };
    const missing = Object.entries(dependencies)
      .filter(([, value]) => !value)
      .map(([name]) => name);

    if (missing.length > 0) {
      console.error('form-bundle.v1.js: Faltan dependencias críticas:', missing.join(', '));
      return false;
    }
    return true;
  }

  // Ejecutar solo cuando el DOM esté listo y las dependencias existan
  document.addEventListener('DOMContentLoaded', () => {
    if (!checkDependencies()) {
      alert('Error crítico: El formulario no puede iniciarse por falta de componentes. Contacte a soporte.');
      return;
    }

    function setupPatientNameSync() {
        const first = document.getElementById('primer_nombre_paciente');
        const second = document.getElementById('segundo_nombre_paciente');
        const firstLast = document.getElementById('primer_apellido_paciente');
        const secondLast = document.getElementById('segundo_apellido_paciente');
        const legacyFull = document.getElementById('nombres_paciente');

        if (!legacyFull || !first || !firstLast) return;

        const sync = () => {
            const parts = [first.value, second?.value, firstLast.value, secondLast?.value]
                .map(v => (v || '').trim())
                .filter(Boolean);
            legacyFull.value = parts.join(' ');
        };

        [first, second, firstLast, secondLast].forEach(el => {
            if (el) el.addEventListener('input', sync);
        });
        sync();
    }

    const form = document.getElementById('clinical-form');
    if (!form) {
      console.warn('form-bundle.v1.js: No se encontró #clinical-form. Abortando.');
      return;
    }

    setupPatientNameSync();

    // --- CONFIGURACIÓN Y VARIABLES GLOBALES DEL FORMULARIO ---
    const baseUrl = window.AppConfig.baseUrl || '/';
    let sections = Array.from(form.querySelectorAll('.form-section'));
    if (!sections.length) {
        console.error('form-bundle.v1.js: No se encontraron .form-section en el formulario.');
        return;
    }
    
    sections.forEach((s, i) => {
      if (!s.dataset.step) s.dataset.step = String(i + 1);
      if (!s.id) s.id = `step_auto_${i+1}`;
    });

    let currentStep = sections.findIndex(s => s.classList.contains('active')) || 0;
    if (currentStep < 0) currentStep = 0;

    const btnNext = form.querySelector('.btn-next');
    const btnPrev = form.querySelector('.btn-prev');
    const btnFinalizar = document.getElementById('btnFinalizar');

    // --- INICIALIZACIÓN DE COMPONENTES (de form-init.js) ---

    // 1. Configuración de Select2
    function setupSelect2() {
        if (!jQuery.fn.select2) return;

        const createSelect2 = (selector, placeholder, minLength, url, processFn) => {
            const $el = jQuery(selector);
            if (!$el.length) return;
            if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
            
            $el.select2({
                width: '100%',
                placeholder: placeholder,
                minimumInputLength: minLength,
                allowClear: true,
                language: {
                    inputTooShort: () => `Ingrese ${minLength} o más caracteres`,
                    noResults: () => 'No se encontraron resultados',
                    searching: () => 'Buscando...', 
                    errorLoading: () => 'Error al cargar datos'
                },
                ajax: {
                    url: baseUrl + url,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: processFn,
                    cache: true
                },
                templateResult: repo => repo.loading ? repo.text : (repo.id ? `<strong>${repo.id}</strong>: ${repo.text}` : repo.text),
                templateSelection: repo => repo.id ? `${repo.id} - ${repo.text}` : repo.text
            });
        };

        // CIE10 en contexto clínico
        createSelect2('.cie10-select, #diagnostico_principal', 'Buscar diagnóstico...', 2, 'buscar_cie10.php', data => ({ results: data.results || [] }));

        // Municipios en ubicación de servicio (v4)
        createSelect2('#municipio_servicio', 'Buscar Municipio...', 2, 'buscar_municipio.php', data => ({ results: data.results || [] }));

        // Municipio de residencia del paciente
        createSelect2('#municipio', 'Buscar Municipio...', 2, 'buscar_municipio.php', data => ({ results: data.results || [] }));

        // Municipio del evento (para códigos DANE en Datos del Accidente o Evento)
        createSelect2('#municipio_evento', 'Buscar Municipio...', 2, 'buscar_municipio.php', data => ({ results: data.results || [] }));

        // Al seleccionar municipio del evento, completar códigos DANE
        const $munEvento = jQuery('#municipio_evento');
        if ($munEvento.length) {
            $munEvento.on('select2:select', (e) => {
                const d = e.params?.data || {};
                const id = String(d.id || '').trim();
                const depInput = document.getElementById('cod_depto_recogida');
                const munInput = document.getElementById('cod_ciudad_recogida');
                if (munInput && id) munInput.value = id;
                if (depInput && id.length >= 2) depInput.value = id.slice(0, 2);
            });
        }

        // IPS Receptora con datos locales si están disponibles
        const $ips = jQuery('#ips');
        if ($ips.length) {
            if ($ips.hasClass('select2-hidden-accessible')) $ips.select2('destroy');
            const localData = Array.isArray(window.IPS_DATA) ? window.IPS_DATA : (Array.isArray(window.AppData?.ipsOptions) ? window.AppData.ipsOptions : []);
            if (localData && localData.length) {
                $ips.select2({
                    width: '100%',
                    data: localData.map(x => ({ id: x.id, text: x.text, nombre: x.nombre, nit: x.nit, ciudad: x.ciudad }))
                }).on('select2:select', (e) => {
                    const d = e.params.data || {};
                    const nombre = d.nombre || (d.text || '').replace(/\s*\(NIT:.*$/, '');
                    const nit = d.nit || d.id || '';
                    const ciudad = d.ciudad || '';
                    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                    setVal('ips_nombre', nombre);
                    setVal('nombre_ips_receptora', nombre);
                    setVal('ips_nit', nit);
                    const disp = document.getElementById('ips_nit_display'); if (disp) disp.value = nit;
                    setVal('nit_ips_receptora', nit);
                    setVal('ips_ciudad', ciudad);
                    const dispC = document.getElementById('ips_ciudad_display'); if (dispC) dispC.value = ciudad;
                    setVal('municipio_ips_receptora', ciudad);
                });
            } else {
                // AJAX con plantilla de 2 líneas
                $ips.select2({
                  width: '100%',
                  placeholder: 'Buscar IPS Receptora...',
                  minimumInputLength: 3,
                  allowClear: true,
                  language: {
                    inputTooShort: () => 'Ingrese 3 o más caracteres',
                    noResults: () => 'No se encontraron resultados',
                    searching: () => 'Buscando...',
                    errorLoading: () => 'Error al cargar datos'
                  },
                  ajax: {
                    url: baseUrl + 'buscar_ips.php',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: (data) => ({ results: data.results || [] }),
                    cache: true
                  },
                  templateResult: (repo) => {
                    if (!repo || repo.loading) return repo && repo.text;
                    // Texto esperado: "Nombre — NIT 900... — Ciudad"
                    const parts = String(repo.text || '').split(' — ');
                    const nombre = parts[0] || repo.text || '';
                    const nit = (parts.find(p => /^NIT\s/i.test(p)) || '').trim();
                    const ciudad = parts.length > 1 ? parts[parts.length-1] : '';
                    const $c = jQuery('<div class="d-flex flex-column">'
                      + `<div class="fw-semibold">${nombre}</div>`
                      + `<div class="text-muted small">${[nit, ciudad].filter(Boolean).join(' — ')}</div>`
                      + '</div>');
                    return $c;
                  },
                  templateSelection: (repo) => {
                    if (!repo) return '';
                    const txt = String(repo.text || '');
                    const parts = txt.split(' — ');
                    return parts[0] || txt;
                  }
                }).on('select2:select', (e) => {
                  const d = e.params.data || {};
                  // Parsear texto combinado para llenar campos
                  const txt = String(d.text || '');
                  const parts = txt.split(' — ');
                  const nombre = parts[0] || '';
                  const nitPart = (parts.find(p => /^NIT\s/i.test(p)) || '').replace(/^NIT\s*/i, '').trim();
                  const ciudad = parts.length > 1 ? parts[parts.length-1] : '';
                  const nit = d.id || nitPart;
                  const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                  setVal('ips_nombre', nombre);
                  setVal('nombre_ips_receptora', nombre);
                  setVal('ips_nit', nit);
                  const disp = document.getElementById('ips_nit_display'); if (disp) disp.value = nit;
                  setVal('nit_ips_receptora', nit);
                  setVal('ips_ciudad', ciudad);
                  const dispC = document.getElementById('ips_ciudad_display'); if (dispC) dispC.value = ciudad;
                  setVal('municipio_ips_receptora', ciudad);
                });
            }
        }

        // Mostrar descripción para diagnóstico principal
        jQuery('#diagnostico_principal').on('select2:select', (e) => {
            const d = e.params.data || {};
            const input = document.getElementById('diagnostico_principal_display');
            if (input) input.value = d.text || '';
        });
    }

    // Selects estáticos
    jQuery('#eps, #tipo_documento, #sexo, #parentesco_acudiente, #departamento, #zona, #tipo_via, #tipo_traslado, #condicion_paciente, #prioridad_despacho, #institucion_remitente, #medico_remite').select2({
        width: '100%'
    });

    // 2. Configuración de Date/Time Pickers
    function setupDatePickers() {
        flatpickr.localize(flatpickr.l10ns.es);
        flatpickr("#fecha", { dateFormat: "d-m-Y", defaultDate: "today" });

        flatpickr("#fecha_nacimiento_display", {
            dateFormat: "d-m-Y",
            locale: "es",
            onChange: (selectedDates, dateStr) => {
                const hiddenInput = document.getElementById("fecha_nacimiento_hidden");
                const edadInput = document.getElementById("edad_paciente");
                if (dateStr && hiddenInput && edadInput) {
                    const birthDate = moment(dateStr, "DD-MM-YYYY");
                    hiddenInput.value = birthDate.format("YYYY-MM-DD");
                    edadInput.value = `${moment().diff(birthDate, 'years')} años`;
                }
            }
        });
    }

    function setupTimePickers() {
        const timeConfig = { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true };
        const timeInputs = ['hora_despacho', 'hora_llegada', 'hora_ingreso', 'hora_final'];
        
        timeInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                try { el.type = 'text'; } catch(e){}
                flatpickr(el, timeConfig);
            }
        });
    }

    // Sincronizar hora del traslado con hora de llegada cuando no se diligencie manualmente
    function setupHoraTrasladoSync() {
        const llegada = document.getElementById('hora_llegada');
        const traslado = document.getElementById('hora_traslado');
        if (!llegada || !traslado) return;
        const sync = () => {
            if (!traslado.value && llegada.value) {
                traslado.value = llegada.value;
            }
        };
        llegada.addEventListener('change', sync);
        llegada.addEventListener('blur', sync);
        sync();
    }

    // 3. Lógica de cálculo de tiempo de servicio
    function calcularTiempoServicio() {
        const despacho = document.getElementById('hora_despacho')?.value;
        const final = document.getElementById('hora_final')?.value;
        const display = document.getElementById('tiempo_servicio_ocupado');
        if (!despacho || !final || !display) return;

        const inicio = moment(despacho, "HH:mm");
        const fin = moment(final, "HH:mm");
        if (!inicio.isValid() || !fin.isValid() || fin.isBefore(inicio)) {
            display.textContent = 'Error';
            return;
        }
        const duracion = moment.duration(fin.diff(inicio));
        display.textContent = `${String(Math.floor(duracion.asHours())).padStart(2, '0')}:${String(duracion.minutes()).padStart(2, '0')}`;
    }
    ['hora_despacho', 'hora_final'].forEach(id => document.getElementById(id)?.addEventListener('change', calcularTiempoServicio));

    // 4. Totalización Glasgow
    function calcGlasgow() {
        const v = (name) => Number(document.querySelector(`input[name="${name}"]:checked`)?.value || 0);
        const total = v('ocular') + v('verbal') + v('motora');
        const el = document.getElementById('escala_glasgow');
        if (el) el.value = String(total || 0);
    }
    form.addEventListener('change', (e) => {
        if (e.target && e.target.classList.contains('glasgow-check')) calcGlasgow();
    });

    // 5. IMC cálculo y color
    function actualizarIMC() {
        const peso = parseFloat(document.getElementById('peso')?.value || '');
        const tallaCm = parseFloat(document.getElementById('talla')?.value || '');
        const imcEl = document.getElementById('imc');
        const badge = document.getElementById('imc-riesgo');
        if (!imcEl || !badge || !peso || !tallaCm) { if (imcEl) imcEl.value = ''; if (badge) { badge.textContent=''; badge.className='badge'; } return; }
        const tallaM = tallaCm / 100;
        const imc = peso / (tallaM * tallaM);
        imcEl.value = imc.toFixed(1);
        let label = 'Normal', cls = 'valor-normal';
        if (imc < 18.5) { label = 'Bajo peso'; cls = 'imc-bajo'; }
        else if (imc < 25) { label = 'Saludable'; cls = 'imc-saludable'; }
        else if (imc < 30) { label = 'Sobrepeso'; cls = 'imc-sobrepeso'; }
        else if (imc < 35) { label = 'Obeso'; cls = 'imc-obeso'; }
        else { label = 'Obesidad extrema'; cls = 'imc-obesidad-extrema'; }
        badge.className = `badge ${cls}`;
        badge.textContent = label;
    }
    ['peso','talla'].forEach(id => document.getElementById(id)?.addEventListener('input', actualizarIMC));

    // 6. Alertas de signos vitales básicas (color de fondo)
    function setAlertClass(input, ok) {
        if (!input) return;
        input.classList.toggle('is-invalid', !ok);
        input.classList.toggle('is-valid', ok);
    }
    function validarVitales() {
        const fc = document.getElementById('frecuencia_cardiaca');
        const fr = document.getElementById('frecuencia_respiratoria');
        const spo2 = document.getElementById('spo2');
        const temp = document.getElementById('temperatura');
        if (fc) setAlertClass(fc, fc.value === '' || (fc.value >= 50 && fc.value <= 120));
        if (fr) setAlertClass(fr, fr.value === '' || (fr.value >= 10 && fr.value <= 24));
        if (spo2) setAlertClass(spo2, spo2.value === '' || (spo2.value >= 92));
        if (temp) setAlertClass(temp, temp.value === '' || (temp.value >= 35 && temp.value <= 38));
    }
    ['frecuencia_cardiaca','frecuencia_respiratoria','spo2','temperatura'].forEach(id => document.getElementById(id)?.addEventListener('input', validarVitales));

    // 7. Geolocalización para botones de registro de ubicación
    function setupGeoButtons() {
        const buttons = form.querySelectorAll('.register-location-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-target');
                const feedbackId = btn.getAttribute('data-feedback');
                const target = targetId ? document.getElementById(targetId) : null;
                const feedback = feedbackId ? document.getElementById(feedbackId) : null;
                if (!navigator.geolocation) {
                    if (feedback) feedback.textContent = 'Geolocalización no soportada.';
                    return;
                }
                navigator.geolocation.getCurrentPosition((pos) => {
                    const { latitude, longitude } = pos.coords;
                    const text = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
                    if (target) target.value = `${(target.value || '').trim()} ${text}`.trim();
                    if (feedback) feedback.textContent = `Ubicación registrada: ${text}`;
                }, (err) => {
                    if (feedback) feedback.textContent = `No se pudo obtener ubicación: ${err.message}`;
                }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 });
            });
        });
    }


    // --- LÓGICA DE VISIBILIDAD Y CONDICIONES (de form-logic.js y form-behaviors.js) ---

    // 1. Escala de Downton
    function calcDowntonTotal() {
        const getCheckedValue = name => Number(document.querySelector(`input[name="${name}"]:checked`)?.value || 0);
        const sum = getCheckedValue('downton_caidas') + getCheckedValue('downton_mental') + getCheckedValue('downton_medicamentos') + getCheckedValue('downton_deficit') + getCheckedValue('downton_deambulacion');
        
        const totalBox = document.getElementById('downton_total_display') || document.getElementById('downton_total');
        const hidden = document.getElementById('downton_total_hidden');
        const riesgo = document.getElementById('downton_riesgo');

        if (totalBox) totalBox.textContent = sum;
        if (hidden) hidden.value = sum;
        if (riesgo) {
            riesgo.textContent = sum >= 2 ? 'Riesgo alto de caídas' : 'Riesgo bajo de caídas';
            riesgo.className = sum >= 2 ? 'mt-2 text-danger fw-bold' : 'mt-2 text-success fw-bold';
        }
    }
    form.addEventListener('change', e => {
        if (e.target && e.target.classList.contains('downton-check')) calcDowntonTotal();
    });

    // 2. Lógica de Tipo de Servicio (TAM/APH)
function setupTipoServicio() {
  const sel = document.getElementById('servicio');
  const botones = document.querySelectorAll('.btn-tipo-servicio');
  const pagadorSel = document.getElementById('pagador');

  const disallowPagadorForAPH = (isAPH) => {
    if (!pagadorSel) return;
    const opts = Array.from(pagadorSel.options || []);
    opts.forEach(o => {
      const v = String(o.value || '').toLowerCase();
      if (v === 'soat' || v === 'adres') {
        o.disabled = !!isAPH;
      }
    });
    if (isAPH) {
      const cur = String(pagadorSel.value || '').toLowerCase();
      if (cur === 'soat' || cur === 'adres') {
        // Prefer EPS if present; otherwise first enabled option
        const eps = opts.find(o => String(o.value || '').toLowerCase() === 'eps' && !o.disabled);
        const firstEnabled = opts.find(o => !o.disabled && o.value !== '');
        const target = eps || firstEnabled || null;
        pagadorSel.value = target ? target.value : '';
        pagadorSel.dispatchEvent(new Event('change'));
      }
    }
  };

  const handleChange = (value) => {
    if (sel) sel.value = value;
    const val = (value || '').toLowerCase();
    const isTAM = val === 'tam' || /medicalizado/i.test(val);
    const isAPH = val.includes('aph') || val.includes('prehospitalaria');

    const sections = {
      'medico-tripulante-container': isTAM,
      'medicamentos-aplicados-container': isTAM,
      'downton-container': isTAM,
      'diagnostico-container': isTAM,
      'motivo-traslado-container': !isAPH,
      'recepcion-paciente-container': !isAPH
    };

    Object.entries(sections).forEach(([id, show]) => {
      const el = document.getElementById(id);
      if (el) el.style.display = show ? '' : 'none';
    });

    form.classList.remove('theme--tab', 'theme--tam', 'theme--aph');
    form.classList.add(isTAM ? 'theme--tam' : (isAPH ? 'theme--aph' : 'theme--tab'));
    // Notificar a otros scripts (progreso, etc.)
    document.dispatchEvent(new CustomEvent('serviciochange', { detail: { isTAM, isAPH, value: value } }));

    botones.forEach(b => b.classList.toggle('active', b.dataset.servicio?.toLowerCase() === val));

    // Enforce pagador restriction for APH
    disallowPagadorForAPH(isAPH);
  };

  // Soporte para clic en botones
  botones.forEach(boton => {
    boton.addEventListener('click', () => handleChange(boton.dataset.servicio));
  });

  // Soporte para select tradicional (si existe)
  sel?.addEventListener('change', () => handleChange(sel.value));

  // Estado inicial
  if (sel) handleChange(sel.value);
  else handleChange('');
  disallowPagadorForAPH(sel?.value?.toLowerCase().includes('aph') || sel?.value?.toLowerCase().includes('prehospitalaria'));
}
    
    // 3. Lógica de Consentimiento
function setupConsentimiento() {
  try {
    const setConsentMode = (mode) => {
      const isAccept = mode === 'ACEPTACION';
      const consentimiento = document.getElementById('consentimiento-container');
      const desistimiento = document.getElementById('desistimiento-container');
      const btnAceptar = document.getElementById('btn-aceptar-atencion');
      const btnRechazar = document.getElementById('btn-rechazar-atencion');
      const hidden = document.getElementById('consent_type');

      if (consentimiento) consentimiento.style.display = isAccept ? '' : 'none';
      if (desistimiento) desistimiento.style.display = isAccept ? 'none' : '';
      if (btnAceptar) btnAceptar.classList.toggle('active', isAccept);
      if (btnRechazar) btnRechazar.classList.toggle('active', !isAccept);
      if (hidden) hidden.value = mode;
    };

    const btnAceptar = document.getElementById('btn-aceptar-atencion');
    const btnRechazar = document.getElementById('btn-rechazar-atencion');

    if (btnAceptar) btnAceptar.addEventListener('click', () => setConsentMode('ACEPTACION'));
    if (btnRechazar) btnRechazar.addEventListener('click', () => setConsentMode('DESISTIMIENTO'));

    // Solo establecer modo inicial si existe alguno de los contenedores
    if (document.getElementById('consentimiento-container') || document.getElementById('desistimiento-container')) {
      setConsentMode('ACEPTACION');
    }

  } catch (err) {
    console.warn('setupConsentimiento() ignorado temporalmente:', err.message);
  }
}

    // Lógica de Pagador (EPS, SOAT, ARL, etc.) para mostrar/ocultar secciones de aseguradora
    function setupPagador() {
      const sel = document.getElementById('pagador');
      if (!sel) return;

      const ids = {
        eps: 'aseguradora-eps-container',
        arl: 'aseguradora-arl-container',
        soat: 'aseguradora-soat-container',
        placa: 'placa_paciente_group'
      };

      const showEl = (id, show) => {
        const el = document.getElementById(id);
        if (el) el.style.display = show ? '' : 'none';
      };

      const apply = (val) => {
        const v = String(val || '').toLowerCase();
        const isSOAT = v.includes('soat');
        const isARL = v.includes('arl');
        const isEPS = v.includes('eps');
        // ADRES/Convenio/Servicio Social/Particular => sin aseguradora específica

        showEl(ids.eps, isEPS);
        showEl(ids.arl, isARL);
        showEl(ids.soat, isSOAT);
        showEl(ids.placa, isSOAT); // placa solo cuando es SOAT
      };

      sel.addEventListener('change', () => apply(sel.value));
      // Estado inicial
      apply(sel.value);

      // Reaplicar restricciones cuando cambie el servicio (APH)
      document.addEventListener('serviciochange', (e) => {
        if (!e || !e.detail) return;
        const isAPH = !!e.detail.isAPH;
        // Disable SOAT/ADRES and fix current selection if needed
        const opts = Array.from(sel.options || []);
        opts.forEach(o => {
          const v = String(o.value || '').toLowerCase();
          if (v === 'soat' || v === 'adres') o.disabled = isAPH;
        });
        if (isAPH) {
          const cur = String(sel.value || '').toLowerCase();
          if (cur === 'soat' || cur === 'adres') {
            const eps = opts.find(o => String(o.value || '').toLowerCase() === 'eps' && !o.disabled);
            const firstEnabled = opts.find(o => !o.disabled && o.value !== '');
            const target = eps || firstEnabled || null;
            sel.value = target ? target.value : '';
            sel.dispatchEvent(new Event('change'));
          }
        }
      });
    }

    // --- NAVEGACIÓN Y VALIDACIÓN (de form-behaviors.js) ---
    
    function showStep(index) {
      sections.forEach((sec, i) => {
        sec.style.display = i === index ? '' : 'none';
        sec.classList.toggle('active', i === index);
      });
      currentStep = index;
      if (btnPrev) btnPrev.disabled = index === 0;
      if (btnNext) btnNext.style.display = index === sections.length - 1 ? 'none' : 'inline-block';
      if (btnFinalizar) btnFinalizar.classList.toggle('d-none', index !== sections.length - 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      try { if (typeof updateStepLinksActive === 'function') updateStepLinksActive(index); } catch(e){}
    }

    function getLabelText(el) {
        if (!el) return '';
        const label = form.querySelector(`label[for="${el.id}"]`);
        return label ? label.textContent.trim() : (el.placeholder || el.name || '');
    }

    function validateSection(index) {
        const section = sections[index];
        const errors = [];
        const required = section.querySelectorAll('[required]:not([disabled])');

        required.forEach(el => {
            if (el.offsetParent !== null && !String(el.value || '').trim()) {
                errors.push(`Campo requerido: ${getLabelText(el)}`);
            }
        });
        return errors;
    }

    function validateAll() {
        const errors = [];
        // Validar todos los campos requeridos visibles en todo el formulario
        sections.forEach((section, index) => {
            errors.push(...validateSection(index));
        });

        // Validaciones de lógica de negocio
        const ta = document.getElementById('tension_arterial')?.value || '';
        if (ta.trim() && !/^\d{2,3}\s*\/\s*\d{2,3}$/.test(ta)) {
            errors.push('Tensión arterial debe tener formato S/D (ej. 120/80).');
        }
        
        // Validar firmas (asumiendo que form-signatures.js actualiza los campos hidden)
        if (!document.getElementById('FirmaParamedicoData')?.value) errors.push('Firma del tripulante requerida.');
        const hasPaciente = document.getElementById('FirmaPacienteData')?.value;
        const hasDesistimiento = document.getElementById('FirmaDesistimientoData')?.value;
        if (!hasPaciente && !hasDesistimiento) errors.push('Se requiere firma de aceptación o desistimiento.');

        return [...new Set(errors)]; // Retornar errores únicos
    }

    // --- VERIFICACIÓN POR PASOS (badges en enlaces) ---
    let verificationEnabled = false;
    function getSectionErrors(index){ return validateSection(index); }
    function sectionIsComplete(index){ return getSectionErrors(index).length === 0; }
    function refreshStepBadges(){
      const container = document.getElementById('wizardStepLinks');
      if (!container) return;
      container.querySelectorAll('.step-link').forEach((btn, i) => {
        const errs = getSectionErrors(i);
        const ok = errs.length === 0;
        // Añadir/actualizar badge
        let badge = btn.querySelector('.badge');
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'badge ms-2';
          btn.appendChild(badge);
        }
        if (verificationEnabled) {
          badge.className = 'badge ms-2 ' + (ok ? 'bg-success' : 'bg-danger');
          badge.textContent = ok ? 'OK' : `Faltan (${errs.length})`;
          badge.title = ok ? 'Sección completa' : errs.slice(0,5).join('\n');
          badge.style.display = '';
        } else {
          badge.style.display = 'none';
        }
      });
    }

    // --- GUARDADO Y ENVÍO (de form-behaviors.js) ---

    async function guardarParcial() {
        const payload = new FormData(form);
        const atencionId = payload.get('atencion_id') || localStorage.getItem('atencion_id');
        if (atencionId) {
            payload.set('atencion_id', atencionId);
        }

        try {
            const response = await fetch(`${baseUrl}procesos/guardar_parcial.php`, {
                method: 'POST',
                body: payload
            });
            const result = await response.json();
            if (result.success && result.id) {
                localStorage.setItem('atencion_id', result.id);
                const idInput = form.querySelector('input[name="atencion_id"]');
                if (idInput) idInput.value = result.id;
                console.log(`Guardado parcial OK, ID: ${result.id}`);
            }
        } catch (error) {
            console.error('Error en guardado parcial:', error);
        }
    }

    async function finalizarAtencion() {
        // Sincronizar firmas antes de validar (compat: updateHidden(nombre))
        try {
          if (typeof window.syncAllSignatures === 'function') {
            window.syncAllSignatures();
          } else if (typeof window.updateHidden === 'function') {
            ['paramedico','medico','paciente','desistimiento','medicoReceptor','receptorIPS'].forEach(window.updateHidden);
          }
        } catch(e) { console.warn('Sync firmas (bundle):', e); }

        const errors = validateAll();
        if (errors.length > 0) {
            alert('Por favor corrija los siguientes errores:\n\n- ' + errors.join('\n- '));
            return;
        }

        btnFinalizar.disabled = true;
        btnFinalizar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Finalizando...';

        try {
            const payload = new FormData(form);
            // incluir atencion_id desde localStorage si falta
            const lsId = localStorage.getItem('atencion_id');
            if (lsId && !payload.get('atencion_id')) payload.set('atencion_id', lsId);
            const response = await fetch(`${baseUrl}procesos/procesar_atencion_v4.php`, {
                method: 'POST',
                body: payload
            });
            const result = await response.json();

            if (result.success) {
                alert('✅ Atención finalizada y guardada correctamente.');
                localStorage.removeItem('atencion_id');
                window.location.href = `${baseUrl}consulta_atenciones.php`;
            } else {
                alert('Error al finalizar la atención: ' + (result.message || 'Error desconocido.'));
                btnFinalizar.disabled = false;
                btnFinalizar.textContent = 'Finalizar atención';
            }
        } catch (error) {
            console.error('Error al finalizar:', error);
            alert('Error de red al finalizar la atención. Intente nuevamente.');
            btnFinalizar.disabled = false;
            btnFinalizar.textContent = 'Finalizar atención';
        }
    }

    // --- ASIGNACIÓN DE EVENTOS ---
    
    btnNext?.addEventListener('click', async () => {
        const errors = validateSection(currentStep);
        if (errors.length > 0) {
            alert('Corrija los errores antes de continuar:\n\n' + errors.join('\n'));
            return;
        }
        await guardarParcial();
        if (currentStep < sections.length - 1) {
            showStep(currentStep + 1);
        }
    });

    btnPrev?.addEventListener('click', () => {
        if (currentStep > 0) {
            showStep(currentStep - 1);
        }
    });

    btnFinalizar?.addEventListener('click', finalizarAtencion);

    // --- INICIALIZACIÓN FINAL ---
    
    // Llamar a todas las funciones de inicialización
    setupSelect2();
    setupDatePickers();
    setupTimePickers();
    setupHoraTrasladoSync();
    calcularTiempoServicio();
    calcDowntonTotal();
    setupTipoServicio();
    setupPagador();
    setupConsentimiento();
    setupGeoButtons();

    function setupIMC() {
      const peso = document.getElementById('peso');
      const talla = document.getElementById('talla');
      const imcInput = document.getElementById('imc');
      const badge = document.getElementById('imc-riesgo');
      if (!peso || !talla || !imcInput || !badge) return;
      const calc = () => {
        const p = parseFloat(peso.value);
        const tcm = parseFloat(talla.value);
        if (!p || !tcm) { imcInput.value = ''; badge.textContent = ''; return; }
        const tm = tcm / 100;
        const imc = tm > 0 ? (p / (tm*tm)) : 0;
        if (!isFinite(imc) || imc <= 0) { imcInput.value = ''; badge.textContent = ''; return; }
        const val = imc.toFixed(1);
        imcInput.value = val;
        let riesgo = '';
        if (imc < 18.5) riesgo = 'Bajo peso';
        else if (imc < 25) riesgo = 'Normal';
        else if (imc < 30) riesgo = 'Sobrepeso';
        else riesgo = 'Obesidad';
        badge.textContent = riesgo;
      };
      peso.addEventListener('input', calc);
      talla.addEventListener('input', calc);
      calc();
    }

    function setupIMCColors(){
      const badge = document.getElementById('imc-riesgo');
      if (!badge) return;
      const apply = () => {
        const t = (badge.textContent || '').toLowerCase();
        badge.classList.remove('bg-success','bg-warning','bg-danger','bg-secondary');
        if (!t) { badge.classList.add('bg-secondary'); return; }
        if (t.includes('normal')) badge.classList.add('bg-success');
        else if (t.includes('bajo') || t.includes('sobrepeso')) badge.classList.add('bg-warning');
        else badge.classList.add('bg-danger');
      };
      const obs = new MutationObserver(apply);
      obs.observe(badge, { childList: true, characterData: true, subtree: true });
      apply();
    }

    function setupGlasgow(){
      const out = document.getElementById('escala_glasgow');
      if (!out) return;
      const calc = () => {
        const get = (n) => {
          const el = document.querySelector(`input[name="${n}"]:checked`);
          return el ? parseInt(el.value,10) || 0 : 0;
        };
        out.value = get('ocular') + get('verbal') + get('motora');
      };
      document.querySelectorAll('.glasgow-check').forEach(el => el.addEventListener('change', calc));
      calc();
    }

    function setupAntecedentes(){
      document.querySelectorAll('select[id^="ant_"][id$="_sn"]').forEach(sel => {
        const id = sel.id.replace(/_sn$/,'');
        const container = document.getElementById(id + '_cual_container');
        const otroInput = document.querySelector(`#${id}_cual_container input[name^="${id}_cual_otro"]`);
        const multi = container ? container.querySelector('select[multiple]') : null;
        const refresh = () => {
          const show = (sel.value || '').toLowerCase() === 'si';
          if (container) container.style.display = show ? '' : 'none';
          if (!show && otroInput) otroInput.value = '';
        };
        sel.addEventListener('change', refresh);
        refresh();
        if (multi) {
          multi.addEventListener('change', () => {
            if (!otroInput) return;
            const hasOtro = Array.from(multi.options).some(op => op.selected && /otro/i.test(op.value||op.text));
            otroInput.style.display = hasOtro ? '' : 'none';
            if (!hasOtro) otroInput.value = '';
          });
        }
      });
    }

    function setupAccidenteVisibility(){
      const sec = document.getElementById('evento');
      const pag = document.getElementById('pagador');
      const aten = document.getElementById('atencion_en');
      if (!sec) return;
      const apply = () => {
        const vPag = (pag && pag.value ? pag.value : '').toLowerCase();
        const vAten = (aten && aten.value ? aten.value : '').toLowerCase();
        const byPag = vPag.includes('soat') || vPag.includes('arl');
        const byAten = ['via publica','evento','trabajo','plantel educativo'].some(s => vAten.includes(s));
        const show = byPag || byAten;
        sec.style.display = show ? '' : 'none';
      };
      if (pag) pag.addEventListener('change', apply);
      if (aten) aten.addEventListener('change', apply);
      apply();
    }

    function setupTipoEventoDefaults(){
      const tipo = document.getElementById('tipo_evento');
      const desc = document.getElementById('desc_tipo_evento');
      const pag = document.getElementById('pagador');
      const causa = document.getElementById('causa_externa');
      if (!tipo) return;

      const setOptionIfExists = (value) => {
        const opts = Array.from(tipo.options || []);
        const found = opts.find(o => String(o.value || '').toLowerCase() === value.toLowerCase());
        if (found) tipo.value = found.value;
      };

      const apply = () => {
        const vPag = (pag && pag.value ? pag.value : '').toLowerCase();
        const vCausa = (causa && causa.value ? causa.value : '').toLowerCase();

        // Accidente de tránsito priorizado por SOAT/ADRES o por Causa Externa
        if (vPag === 'soat' || vPag === 'adres' || vCausa.includes('accidente de transito')) {
          setOptionIfExists('Accidente de tránsito');
          if (desc && !desc.value) {
            desc.value = 'Accidente de tránsito. Ver detalles en historia clínica.';
          }
          return;
        }

        // Eventos catastróficos
        if (vCausa.includes('evento catastrofico')) {
          setOptionIfExists('Evento catastrófico');
          return;
        }

        // Default para otros pagadores/causas cuando no haya selección explícita
        if (!tipo.value) {
          setOptionIfExists('Urgencia no accidental');
        }
      };

      // Estado inicial
      if (!tipo.value) {
        setOptionIfExists('Urgencia no accidental');
      }

      if (pag) pag.addEventListener('change', apply);
      if (causa) causa.addEventListener('change', apply);
      apply();
    }

    setupIMC();
    setupIMCColors();
    setupGlasgow();
    setupAntecedentes();
    setupAccidenteVisibility();
    setupTipoEventoDefaults();

    const stepLinksContainer = document.getElementById('wizardStepLinks');
    function getSectionTitle(sec){
      const h = sec.querySelector('.section-header h3');
      return (h ? h.textContent.trim() : sec.id || 'Paso');
    }
    function renderStepLinks(){
      if (!stepLinksContainer) return;
      stepLinksContainer.innerHTML = '';
      const ul = document.createElement('div');
      ul.className = 'd-flex flex-wrap gap-2';
      sections.forEach((sec, i) => {
        const a = document.createElement('button');
        a.type = 'button';
        a.className = 'btn btn-sm btn-outline-secondary step-link';
        a.dataset.index = String(i);
        a.textContent = `${i+1}. ${getSectionTitle(sec)}`;
        a.addEventListener('click', () => showStep(i));
        ul.appendChild(a);
      });
      stepLinksContainer.appendChild(ul);
      updateStepLinksActive(currentStep);
    }
    function updateStepLinksActive(idx){
      if (!stepLinksContainer) return;
      stepLinksContainer.querySelectorAll('.step-link').forEach((el, i) => {
        el.classList.toggle('btn-primary', i === idx);
        el.classList.toggle('btn-outline-secondary', i !== idx);
      });
    }
    renderStepLinks();
    const verifyToggle = document.getElementById('wizardVerifyToggle');
    if (verifyToggle) {
      verificationEnabled = !!verifyToggle.checked;
      verifyToggle.addEventListener('change', () => { verificationEnabled = verifyToggle.checked; refreshStepBadges(); });
    }
    // Recalcular badges cuando cambian inputs visibles
    form.addEventListener('input', () => { if (verificationEnabled) refreshStepBadges(); });
    form.addEventListener('change', () => { if (verificationEnabled) refreshStepBadges(); });

    // Auto-carga de adjuntos con progreso por archivo
    async function ensureAtencionIdForUploads() {
      let aid = localStorage.getItem('atencion_id');
      if (aid) return aid;
      try {
        // Enviar guardado parcial mínimo para obtener un ID
        const fd = new FormData();
        fd.append('seccion', 'adjuntos_auto');
        fd.append('data', JSON.stringify({ servicio: document.getElementById('servicio')?.value || '' }));
        const resp = await fetch(`${baseUrl}procesos/guardar_parcial.php`, { method: 'POST', body: fd });
        const json = await resp.json();
        if (json && json.success && json.id) {
          aid = String(json.id);
          localStorage.setItem('atencion_id', aid);
          const idInput = form.querySelector('input[name="atencion_id"]');
          if (idInput) idInput.value = aid;
          return aid;
        }
      } catch (e) { console.warn('ensureAtencionIdForUploads error', e); }
      return null;
    }

    function renderUploadItem(container, file, idx) {
      const row = document.createElement('div');
      row.className = 'mb-2';
      row.innerHTML = `
        <div class="d-flex align-items-center gap-2">
          <div class="flex-grow-1">
            <div class="small text-truncate" title="${file.name}"><strong>${idx+1}.</strong> ${file.name}</div>
            <div class="progress" style="height:12px;">
              <div class="progress-bar" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
          </div>
          <span class="badge bg-secondary">${file.type || 'archivo'}</span>
        </div>`;
      container.appendChild(row);
      return row.querySelector('.progress-bar');
    }

    async function uploadFileWithProgress(aid, file, progEl) {
      return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', `${baseUrl}procesos/procesar_atencion_v4.php`);
        xhr.responseType = 'json';
        xhr.upload.onprogress = (e) => {
          if (!progEl || !e.lengthComputable) return;
          const pct = Math.min(100, Math.round((e.loaded / e.total) * 100));
          progEl.style.width = pct + '%';
          progEl.setAttribute('aria-valuenow', String(pct));
          progEl.textContent = pct + '%';
        };
        xhr.onerror = () => resolve({ success:false, message:'error de red' });
        xhr.onload = () => {
          const res = xhr.response || {};
          if (res && res.success) {
            if (progEl) { progEl.classList.add('bg-success'); progEl.textContent = 'Completado'; }
            resolve({ success:true });
          } else {
            if (progEl) { progEl.classList.add('bg-danger'); progEl.textContent = 'Error'; }
            resolve({ success:false, message: res.message || 'error' });
          }
        };
        const fd = new FormData();
        fd.append('action', 'upload_attachment');
        fd.append('atencion_id', aid);
        fd.append('file', file, file.name);
        xhr.send(fd);
      });
    }

    const adjInput = document.getElementById('adjuntos_input');
    const uploadsList = document.getElementById('adjuntos_uploads');
    if (adjInput) {
      adjInput.addEventListener('change', async (ev) => {
        const files = Array.from(ev.target?.files || []);
        if (!files.length) return;
        if (files.length > 10) {
          alert('Máximo 10 archivos permitidos. Se tomarán los primeros 10.');
        }
        const slice = files.slice(0, 10);
        const aid = await ensureAtencionIdForUploads();
        if (!aid) { alert('No fue posible preparar el registro para subir adjuntos.'); return; }
        if (uploadsList) uploadsList.innerHTML = '';
        for (let i=0; i<slice.length; i++) {
          const pb = uploadsList ? renderUploadItem(uploadsList, slice[i], i) : null;
          await uploadFileWithProgress(aid, slice[i], pb);
        }
      });
    }

    // Mostrar el paso inicial
    showStep(currentStep);

    // Exponer API global si es necesario
    window.FormWizard = {
        showStep,
        guardarParcial,
        finalizarAtencion,
        validateAll
    };
  });

})(window, document, window.jQuery, window.moment, window.flatpickr);
