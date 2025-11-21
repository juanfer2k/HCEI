/**
 * form-init.js - Inicialización del formulario de atención
 * Este archivo maneja la carga inicial y configuración de los componentes del formulario
 */

(function (window, document, jQuery, moment, flatpickr) {
    'use strict';

    // Namespace principal
    window.AppForm = window.AppForm || {};

    // Helper base URL
    const apiUrl = (path) => {
        try { return ((window.AppConfig && window.AppConfig.baseUrl) || '/') + String(path || ''); }
        catch (e) { return '/' + String(path || ''); }
    };

    // -------------------
    // CHECK DEPENDENCIES
    // -------------------
    function checkDependencies() {
        const dependencies = {
            'jQuery': jQuery,
            'SignaturePad': window.SignaturePad,
            'flatpickr': flatpickr,
            'moment': moment,
            'select2': jQuery && jQuery.fn.select2
        };
        const missing = Object.entries(dependencies)
            .filter(([, value]) => !value)
            .map(([name]) => name);

        if (missing.length > 0) {
            console.error('Form initialization failed. Missing dependencies:', missing.join(', '));
            // Optional: Display a user-friendly error message
            // const errorDiv = document.createElement('div');
            // errorDiv.className = 'alert alert-danger';
            // errorDiv.innerText = 'Error crítico: Faltan componentes para que el formulario funcione. Contacte a soporte.';
            // document.body.prepend(errorDiv);
            return false;
        }
        return true;
    }

    // -------------------
    // SERVICE TIME CALCULATION
    // -------------------
    function calcularTiempoServicio() {
        const horaDespachoInput = document.getElementById('hora_despacho');
        const horaFinalInput = document.getElementById('hora_final');
        const tiempoServicioDisplay = document.getElementById('tiempo_servicio_ocupado');

        if (!horaDespachoInput || !horaFinalInput || !tiempoServicioDisplay) return;

        const despacho = horaDespachoInput.value;
        const final = horaFinalInput.value;

        if (!despacho || !final) {
            tiempoServicioDisplay.textContent = '00:00';
            return;
        }

        const inicio = moment(despacho, "HH:mm");
        const fin = moment(final, "HH:mm");

        if (!inicio.isValid() || !fin.isValid() || fin.isBefore(inicio)) {
            tiempoServicioDisplay.textContent = fin.isBefore(inicio) ? 'Error' : '00:00';
            return;
        }

        const duracion = moment.duration(fin.diff(inicio));
        const horas = Math.floor(duracion.asHours());
        const minutos = duracion.minutes();
        tiempoServicioDisplay.textContent = `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}`;
    }

    // -------------------
    // WAITING HOURS CALCULATION (Horas de Espera)
    // -------------------
    function calcularHorasEspera() {
        const horaDespachoInput = document.getElementById('hora_despacho');
        const horaRecepcionInput = document.getElementById('hora_recepcion_paciente');
        const horasEsperaInput = document.getElementById('horas_espera');

        if (!horaDespachoInput || !horaRecepcionInput || !horasEsperaInput) return;

        const despacho = horaDespachoInput.value;
        const recepcion = horaRecepcionInput.value;

        if (!despacho || !recepcion) {
            horasEsperaInput.value = '';
            return;
        }

        const inicio = moment(despacho, "HH:mm");
        const fin = moment(recepcion, "HH:mm");

        if (!inicio.isValid() || !fin.isValid() || fin.isBefore(inicio)) {
            horasEsperaInput.value = '';
            return;
        }

        const duracion = moment.duration(fin.diff(inicio));
        const horasDecimal = duracion.asHours();
        horasEsperaInput.value = horasDecimal.toFixed(1);
    }

    // -------------------
    // DISTANCE CALCULATION (Distancia Recorrida)
    // -------------------
    function calcularDistanciaRecorrida() {
        const kmInicialInput = document.getElementById('km_inicial');
        const kmFinalInput = document.getElementById('km_final');
        const distanciaInput = document.getElementById('distancia_recorrida');

        if (!kmInicialInput || !kmFinalInput) return;

        const kmInicial = parseFloat(kmInicialInput.value) || 0;
        const kmFinal = parseFloat(kmFinalInput.value) || 0;

        if (kmInicial > 0 && kmFinal > 0 && kmFinal >= kmInicial) {
            const distancia = kmFinal - kmInicial;
            if (distanciaInput) {
                distanciaInput.value = distancia.toFixed(1);
            }
        } else if (distanciaInput) {
            distanciaInput.value = '';
        }
    }

    // -------------------
    // MEDICATION MANAGEMENT
    // -------------------
    function setupMedicamentos() {
        const btnAgregar = document.getElementById('btn-agregar-medicamento');
        const listaMedicamentos = document.getElementById('lista-medicamentos');
        const template = document.getElementById('medicamento-template');

        if (!btnAgregar || !listaMedicamentos || !template) return;

        btnAgregar.addEventListener('click', () => {
            const clone = template.content.cloneNode(true);

            // Add flatpickr to the hora input
            const horaInput = clone.querySelector('.medicamento-hora-input');
            listaMedicamentos.appendChild(clone);

            // Initialize flatpickr for the newly added hora input
            if (horaInput && window.flatpickr) {
                flatpickr(listaMedicamentos.lastElementChild.querySelector('.medicamento-hora-input'), {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: "H:i",
                    time_24hr: true,
                    minuteIncrement: 1,
                    locale: "es"
                });
            }
        });

        // Handle remove buttons (event delegation)
        listaMedicamentos.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-remover-medicamento') ||
                e.target.closest('.btn-remover-medicamento')) {
                const item = e.target.closest('.medicamento-item');
                if (item) item.remove();
            }
        });
    }

    // -------------------
    // DYNAMIC SELECTS (Select2)
    // -------------------
    function setupSelect2() {
        if (!jQuery.fn.select2) return;

        // No se usa Select2 para conductor y paramedico - son campos de texto simples
        const $conductor = jQuery('#conductor');
        const $ccConductor = jQuery('#cc_conductor');

        // Limpiar cualquier Select2 previo si existe
        if ($conductor.length && $conductor.hasClass('select2-hidden-accessible')) {
            $conductor.select2('destroy');
        }

        // CIE10 Search - soporte para selector por id (#diagnostico_principal, #diagnostico_principal_condicion) y/o clase (.cie10-select)
        const $cieElements = jQuery('.cie10-select, #diagnostico_principal, #diagnostico_principal_condicion');
        if ($cieElements.length) {
            $cieElements.each(function () {
                const $el = jQuery(this);
                if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
                $el.select2({
                    width: '100%',
                    placeholder: 'Buscar diagnóstico (código o nombre)...',
                    minimumInputLength: 2,
                    allowClear: true,
                    language: {
                        inputTooShort: () => 'Ingrese 2 o más caracteres',
                        noResults: () => 'No se encontraron resultados',
                        searching: () => 'Buscando...',
                        errorLoading: () => 'Error al cargar diagnósticos'
                    },
                    ajax: {
                        url: apiUrl('buscar_cie10.php'),
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term }),
                        processResults: data => ({ results: data.results || [] }),
                        cache: true
                    },
                    // Render plain text to avoid literal HTML tags in UI
                    templateResult: repo => {
                        if (repo.loading) return repo.text;
                        if (repo.id) return `${repo.id}: ${repo.text}`;
                        return repo.text;
                    },
                    templateSelection: repo => (repo && repo.id ? `${repo.id}: ${repo.text}` : (repo && repo.text ? repo.text : '')),
                    escapeMarkup: m => m
                }).on('select2:select', function (e) {
                    // Si existe el campo display lo actualizamos
                    const display = document.getElementById('diagnostico_principal_display');
                    if (display) display.value = e.params.data.text || '';
                }).on('select2:clear', function () {
                    const display = document.getElementById('diagnostico_principal_display');
                    if (display) display.value = '';
                });
            });
        }

        // Other simple selects
        // Remove EPS from Select2 list since it's no longer used
        jQuery('#eps, #eps_nombre, #aseguradora_arl_select, #aseguradora_soat, #ambulancia, #tipo_ingreso, #pagador, #quien_informo, #finalidad_servicio, #viene_remitida, #tipo_usuario, #tipo_afiliacion, #causa_externa, #causa_externa_categoria, #estado_afiliacion, #atencion_en, #etnia, #discapacidad, #tipo_evento, #condicion_victima, #tipo_vehiculo_accidente, #estado_aseguramiento, #tipo_id_tripulante, #tipo_id_medico, #tipo_id_medico_receptor, #tipo_documento, #sexo, #parentesco_acudiente, #departamento, #zona, #tipo_via, #tipo_traslado, #condicion_paciente, #prioridad_despacho, #institucion_remitente, #medico_remite').select2({
            width: '100%'
        });

        // IPS Receptora Search (supports #ips or #ips_receptora)
        const $ipsReceptora = jQuery('#ips').length ? jQuery('#ips') : jQuery('#ips_receptora');
        if ($ipsReceptora.length) {
            if ($ipsReceptora.hasClass('select2-hidden-accessible')) $ipsReceptora.select2('destroy');

            const hasLocalIps = window.AppData && Array.isArray(window.AppData.ipsOptions) && window.AppData.ipsOptions.length > 0;
            const localData = hasLocalIps ? window.AppData.ipsOptions : null;
            const baseConfig = {
                width: '100%',
                placeholder: 'Buscar IPS Receptora...',
                minimumInputLength: 0,
                allowClear: true,
                language: {
                    inputTooShort: () => 'Ingrese 3 o más caracteres',
                    noResults: () => 'No se encontraron resultados',
                    searching: () => 'Buscando...',
                    errorLoading: () => 'Error al cargar IPS'
                },
                templateResult: (repo) => {
                    if (repo.loading) return repo.text;
                    const name = repo.text || repo.nombre || '';
                    const nit = repo.nit || '';
                    const city = repo.ciudad || '';
                    const extra = [nit ? `NIT: ${nit}` : '', city ? city : ''].filter(Boolean).join(' · ');
                    const $markup = jQuery(`<div><div class="fw-semibold">${name}</div>${extra ? `<div class="text-muted small">${extra}</div>` : ''}</div>`);
                    return $markup;
                },
                templateSelection: (repo) => repo.text || repo.nombre || ''
            };

            if (localData) {
                $ipsReceptora.select2({
                    ...baseConfig,
                    data: localData
                });
            } else {
                $ipsReceptora.select2({
                    ...baseConfig,
                    minimumInputLength: 3,
                    ajax: {
                        url: apiUrl('buscar_ips.php'),
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term }),
                        processResults: data => ({ results: data.results || [] }),
                        cache: true
                    }
                });
            }

            // On select, populate hidden/display fields (support multiple possible field IDs)
            $ipsReceptora.on('select2:select', function (e) {
                const d = e.params && e.params.data ? e.params.data : {};
                const name = d.text || d.nombre || '';
                const nit = d.nit || '';
                const city = d.ciudad || '';
                const nameInput = document.getElementById('ips_nombre') || document.getElementById('nombre_ips_receptora');
                const nitInput = document.getElementById('ips_nit');
                const nitDisplay = document.getElementById('ips_nit_display');
                const nitAlt = document.getElementById('nit_ips_receptora');
                const cityHidden = document.getElementById('ips_ciudad');
                const cityDisplay = document.getElementById('ips_ciudad_display');
                const cityAlt = document.getElementById('municipio_ips_receptora');
                if (nameInput) nameInput.value = name;
                if (nitInput) nitInput.value = nit;
                if (nitDisplay) nitDisplay.value = nit;
                if (nitAlt) nitAlt.value = nit;
                if (cityHidden) cityHidden.value = city;
                if (cityDisplay) cityDisplay.value = city;
                if (cityAlt) cityAlt.value = city;
            });
        }

        // Municipio Search: initialize municipio_servicio, municipio_paciente y municipio_evento
        jQuery('#municipio_servicio, #municipio_paciente, #municipio_evento').each(function () {
            const $m = jQuery(this);
            if ($m.hasClass('select2-hidden-accessible')) $m.select2('destroy');
            $m.select2({
                width: '100%',
                placeholder: 'Buscar Municipio...',
                minimumInputLength: 2,
                allowClear: true,
                language: {
                    inputTooShort: () => 'Ingrese 2 o más caracteres',
                    noResults: () => 'No se encontraron resultados',
                    searching: () => 'Buscando...',
                    errorLoading: () => 'Error al cargar Municipios'
                },
                ajax: {
                    url: apiUrl('buscar_municipio.php'),
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.results || [] }),
                    cache: true
                },
                templateResult: repo => repo.loading ? repo.text : (repo.text || ''),
                templateSelection: repo => (repo && repo.text ? repo.text : '')
            });

            // Si el select corresponde al municipio de residencia del paciente,
            // poblar automáticamente los campos de códigos DANE de residencia.
            if ($m.attr('id') === 'municipio_paciente') {
                $m.on('select2:select', function (e) {
                    try {
                        const data = e.params && e.params.data ? e.params.data : {};
                        const codigoMun = data.codigo_municipio || data.id || '';
                        const codigoDepto = data.codigo_departamento || (codigoMun.length >= 2 ? codigoMun.substring(0, 2) : '');
                        const nombreDepto = data.nombre_departamento || '';

                        // Hidden fields
                        const deptoInput = document.getElementById('cod_departamento_residencia');
                        const munInput = document.getElementById('cod_municipio_residencia');
                        if (deptoInput) deptoInput.value = codigoDepto;
                        if (munInput) munInput.value = codigoMun;

                        // Display fields
                        const munDisplay = document.getElementById('cod_municipio_residencia_display');
                        const deptoDisplay = document.getElementById('cod_depto_residencia_display');
                        if (munDisplay) munDisplay.value = codigoMun;
                        if (deptoDisplay) deptoDisplay.value = nombreDepto;
                    } catch (err) { /* noop */ }
                });

                $m.on('select2:clear', function () {
                    const deptoInput = document.getElementById('cod_departamento_residencia');
                    const munInput = document.getElementById('cod_municipio_residencia');
                    const munDisplay = document.getElementById('cod_municipio_residencia_display');
                    const deptoDisplay = document.getElementById('cod_depto_residencia_display');
                    if (deptoInput) deptoInput.value = '';
                    if (munInput) munInput.value = '';
                    if (munDisplay) munDisplay.value = '';
                    if (deptoDisplay) deptoDisplay.value = '';
                });
            }

            // Si el select corresponde al municipio del evento,
            // poblar automáticamente los campos de códigos DANE del evento.
            if ($m.attr('id') === 'municipio_evento') {
                $m.on('select2:select', function (e) {
                    try {
                        const data = e.params && e.params.data ? e.params.data : {};
                        const codigoMun = data.codigo_municipio || data.id || '';
                        const codigoDepto = data.codigo_departamento || (codigoMun.length >= 2 ? codigoMun.substring(0, 2) : '');
                        const nombreDepto = data.nombre_departamento || '';

                        // Hidden fields
                        const deptoInput = document.getElementById('cod_depto_recogida');
                        const munInput = document.getElementById('cod_ciudad_recogida');
                        if (deptoInput) deptoInput.value = codigoDepto;
                        if (munInput) munInput.value = codigoMun;

                        // Display fields
                        const munDisplay = document.getElementById('cod_ciudad_recogida_display');
                        const deptoDisplay = document.getElementById('cod_depto_recogida_display');
                        if (munDisplay) munDisplay.value = codigoMun;
                        if (deptoDisplay) deptoDisplay.value = nombreDepto;
                    } catch (err) { /* noop */ }
                });

                $m.on('select2:clear', function () {
                    const deptoInput = document.getElementById('cod_depto_recogida');
                    const munInput = document.getElementById('cod_ciudad_recogida');
                    const munDisplay = document.getElementById('cod_ciudad_recogida_display');
                    const deptoDisplay = document.getElementById('cod_depto_recogida_display');
                    if (deptoInput) deptoInput.value = '';
                    if (munInput) munInput.value = '';
                    if (munDisplay) munDisplay.value = '';
                    if (deptoDisplay) deptoDisplay.value = '';
                });
            }

            // Si el select corresponde al municipio del servicio,
            // poblar automáticamente los campos de códigos DANE del lugar del servicio.
            if ($m.attr('id') === 'municipio_servicio') {
                $m.on('select2:select', function (e) {
                    try {
                        const data = e.params && e.params.data ? e.params.data : {};
                        const codigoMun = data.codigo_municipio || data.id || '';
                        const codigoDepto = data.codigo_departamento || (codigoMun.length >= 2 ? codigoMun.substring(0, 2) : '');
                        const nombreDepto = data.nombre_departamento || '';

                        // Hidden fields
                        const deptoInput = document.getElementById('cod_depto_servicio');
                        const munInput = document.getElementById('cod_municipio_servicio');
                        if (deptoInput) deptoInput.value = codigoDepto;
                        if (munInput) munInput.value = codigoMun;

                        // Display fields
                        const munDisplay = document.getElementById('cod_municipio_servicio_display');
                        const deptoDisplay = document.getElementById('cod_depto_servicio_display');
                        if (munDisplay) munDisplay.value = codigoMun;
                        if (deptoDisplay) deptoDisplay.value = nombreDepto;
                    } catch (err) { /* noop */ }
                });

                $m.on('select2:clear', function () {
                    const deptoInput = document.getElementById('cod_depto_servicio');
                    const munInput = document.getElementById('cod_municipio_servicio');
                    const munDisplay = document.getElementById('cod_municipio_servicio_display');
                    const deptoDisplay = document.getElementById('cod_depto_servicio_display');
                    if (deptoInput) deptoInput.value = '';
                    if (munInput) munInput.value = '';
                    if (munDisplay) munDisplay.value = '';
                    if (deptoDisplay) deptoDisplay.value = '';
                });
            }
        });
    }

    // -------------------
    // AFILIACIÓN: AUTO-FILL NÚMERO DESDE ID PACIENTE
    // -------------------
    function setupAfiliacionAutofill() {
        try {
            const idInput = document.getElementById('id_paciente');
            const afilInput = document.getElementById('numero_afiliacion');
            if (!idInput || !afilInput) return;

            // Auto-fill cuando cambia el ID del paciente
            const autoFillAfiliacion = () => {
                const idVal = (idInput.value || '').trim();
                if (idVal) {
                    afilInput.value = idVal;
                } else {
                    afilInput.value = '';
                }
            };

            idInput.addEventListener('input', autoFillAfiliacion);
            idInput.addEventListener('blur', autoFillAfiliacion);

            // Sincronizar en tiempo real
            autoFillAfiliacion();
        } catch (e) { /* noop */ }
    }

    // -------------------
    // SERVICE TYPE LOGIC
    // -------------------
    function setupTipoServicio() {
        const sel = document.getElementById('servicio');
        const mainForm = document.getElementById('clinical-form');
        // support both legacy and new button classes
        const serviceTypeButtons = document.querySelectorAll('.btn-tabtam, .btn-tipo-servicio');

        if (!sel || !mainForm) return;

        const handleChange = () => {
            const value = (sel.value || '').toString();
            const vLower = value.toLowerCase();
            const isTAM = value === 'TAM' || /medicalizado/i.test(value) || vLower.includes('tam');
            const isAPH = value === 'APH' || /prehospitalaria/i.test(value) || vLower.includes('aph');

            // 1. Toggle section visibility
            const sections = {
                'medico-tripulante-container': isTAM,
                'medico-tripulante-firma-container': isTAM,
                'medicamentos-aplicados-container': isTAM,
                'downton-container': isTAM,
                'diagnostico-container': isTAM,
                'motivo-traslado-container': !isAPH,
                'recepcion-paciente-container': !isAPH
                // 'datos-soat' removed - controlled by setupPagador() based on pagador selection
            };
            Object.entries(sections).forEach(([id, show]) => {
                const el = document.getElementById(id);
                if (el) el.style.display = show ? '' : 'none';
            });

            // 2. Update theme classes for colors on the main form and body
            const themeClasses = ['theme--tab', 'theme--tam', 'theme--aph'];
            mainForm.classList.remove(...themeClasses);
            document.body.classList.remove(...themeClasses);
            if (isTAM) {
                mainForm.classList.add('theme--tam');
                document.body.classList.add('theme--tam');
            } else if (isAPH) {
                mainForm.classList.add('theme--aph');
                document.body.classList.add('theme--aph');
            } else {
                mainForm.classList.add('theme--tab');
                document.body.classList.add('theme--tab');
            }

            // 3. Update active state for service type buttons
            serviceTypeButtons.forEach(button => {
                const buttonValue = button.dataset.value || button.dataset.servicio || '';
                button.classList.toggle('active', buttonValue === value);
                button.setAttribute('aria-pressed', buttonValue === value);
            });

            // 4. Dispatch custom event for other scripts
            document.dispatchEvent(new CustomEvent('serviciochange', {
                detail: { isTAM, isAPH, value }
            }));
        };

        // Listen for changes on the actual select element
        sel.addEventListener('change', handleChange);

        // Listen for clicks on the service type buttons
        serviceTypeButtons.forEach(button => {
            button.addEventListener('click', () => {
                const buttonValue = button.dataset.value || button.dataset.servicio;
                if (buttonValue && sel.value !== buttonValue) {
                    sel.value = buttonValue;
                    sel.dispatchEvent(new Event('change')); // Trigger change event on the select
                }
            });
        });

        // Trigger initial state
        handleChange();
    }

    // -------------------
    // GEOLOCATION BUTTONS
    // -------------------
    function setupGeoButtons() {
        try {
            const buttons = document.querySelectorAll('.register-location-btn');
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
        } catch (e) { console.warn('setupGeoButtons error', e); }
    }

    // -------------------
    // PAGADOR CONDITIONALS
    // -------------------
    function setupPagador() {
        const sel = document.getElementById('pagador');
        if (!sel) return;

        const ids = {
            eps: 'aseguradora-eps-container',
            arl: 'aseguradora-arl-container',
            soat: 'aseguradora-soat-container',
            placa: 'placa_paciente_group',
            datos: 'datos-soat',
            evento: 'evento-container',
            accidente: 'datos-accidente-container',
            fecha_vencimiento_arl: 'fecha-vencimiento-arl-container'
        };

        const showEl = (id, show) => {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = show ? 'block' : 'none';
                console.log(`showEl: ${id} = ${show ? 'block' : 'none'}`);
            } else {
                console.warn(`showEl: elemento ${id} no encontrado`);
            }
        };

        const apply = (val) => {
            const v = String(val || '').toLowerCase();
            const isSOAT = v.includes('soat');
            const isARL = v.includes('arl');
            const isEPS = v.includes('eps');
            const isADRES = v.includes('adres');

            // Check if current service is APH
            const servicioSel = document.getElementById('servicio');
            const servicioValue = servicioSel ? (servicioSel.value || '').toLowerCase() : '';
            const isAPH = servicioValue === 'aph' || /prehospitalaria/i.test(servicioValue) || servicioValue.includes('aph');

            // Mostrar para SOAT/ADRES/ARL Y cuando NO sea APH, O si es ADRES (independientemente de APH/TAM)
            // La lógica anterior ocultaba todo si era APH. Para ADRES TAM necesitamos mostrarlo.
            const showDatos = (isSOAT || isARL) && !isAPH || isADRES;

            console.log('=== setupPagador DEBUG ===');
            console.log('Pagador:', val);
            console.log('Servicio element exists:', !!servicioSel);
            console.log('Servicio value:', servicioValue);
            console.log('isSOAT:', isSOAT, 'isADRES:', isADRES, 'isARL:', isARL, 'isAPH:', isAPH);
            console.log('showDatos:', showDatos);

            // Verificar que el elemento datos-soat existe
            const datosSoatEl = document.getElementById('datos-soat');
            console.log('datos-soat element exists:', !!datosSoatEl);
            if (datosSoatEl) {
                console.log('datos-soat current display:', datosSoatEl.style.display);
            }

            // EPS no muestra bloque de aseguradora
            showEl(ids.eps, false);

            // ARL: mostrar selector y fecha de vencimiento
            showEl(ids.arl, isARL && showDatos);
            showEl(ids.fecha_vencimiento_arl, isARL && showDatos);

            // aseguradora_soat usado solo para SOAT / ADRES
            showEl(ids.soat, (isSOAT || isADRES));

            // placa sólo cuando es SOAT (y no APH)
            showEl(ids.placa, isSOAT && !isAPH);

            // Bloque completo "Datos para Aseguradora"
            showEl(ids.datos, showDatos);

            // Sección "Datos del Accidente o Evento" (solo SOAT/ADRES)
            showEl(ids.evento, (isSOAT || isADRES));

            // Contenedor de datos de accidente dentro de la sección (solo SOAT/ADRES)
            showEl(ids.accidente, (isSOAT || isADRES));

            // Auto-select ADRES in insurer dropdown if Pagador is ADRES
            if (isADRES && !isAPH) {
                const soatSel = document.getElementById('aseguradora_soat');
                if (soatSel && (!soatSel.value || soatSel.value === '')) {
                    soatSel.value = 'ADRES';
                    // Trigger change for select2 if present
                    if (window.jQuery && window.jQuery(soatSel).data('select2')) {
                        window.jQuery(soatSel).trigger('change');
                    }
                }
            }

            console.log('=== END setupPagador DEBUG ===');
        };

        sel.addEventListener('change', () => apply(sel.value));

        // Listen to service type changes to re-evaluate visibility
        document.addEventListener('serviciochange', () => {
            console.log('serviciochange event received, re-evaluating pagador');
            apply(sel.value);
        });

        // Trigger initial state on page load
        apply(sel.value);

        // Manual "otra" toggles if present
        const soatSel = document.getElementById('aseguradora_soat');
        const soatOtra = document.getElementById('aseguradora_soat_otra');
        if (soatSel && soatOtra) {
            const toggleOtra = () => { soatOtra.style.display = (soatSel.value || '').toLowerCase() === 'otra' ? '' : 'none'; };
            soatSel.addEventListener('change', toggleOtra);
            toggleOtra();
        }

        const epsSel = document.getElementById('aseguradora_eps_select');
        const epsManualCtn = document.getElementById('aseguradora_eps_manual_container');
        if (epsSel && epsManualCtn) {
            const toggle = () => { epsManualCtn.style.display = (epsSel.value || '').toLowerCase() === 'otra' ? '' : 'none'; };
            epsSel.addEventListener('change', toggle);
            toggle();
        }

        const arlSel = document.getElementById('aseguradora_arl_select');
        const arlManualCtn = document.getElementById('aseguradora_arl_manual_container');
        if (arlSel && arlManualCtn) {
            const toggle = () => { arlManualCtn.style.display = (arlSel.value || '').toLowerCase() === 'otra' ? '' : 'none'; };
            arlSel.addEventListener('change', toggle);
            toggle();
        }
    }

    // -------------------
    // DATE & TIME PICKERS (flatpickr)
    // -------------------
    function setupDatePickers() {
        flatpickr("#fecha", { dateFormat: "d-m-Y", defaultDate: "today", locale: "es" });

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
                } else if (hiddenInput && edadInput) {
                    hiddenInput.value = '';
                    edadInput.value = '';
                }
            }
        });
    }

    function setupTimePickers() {
        console.log('setupTimePickers called');
        const timeConfig = {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            minuteIncrement: 1,
            locale: "es"
        };

        const pickers = {};

        // hora_despacho, hora_llegada y hora_ingreso ahora son campos ocultos gestionados fuera del wizard
        // const llegadaInput = document.getElementById("hora_llegada");
        // if (!llegadaInput) console.error('hora_llegada input not found');
        // const ingresoInput = document.getElementById("hora_ingreso");
        // if (!ingresoInput) console.error('hora_ingreso input not found');

        // const llegadaPicker = flatpickr(llegadaInput, {
        //     ...timeConfig,
        //     onChange: function (selectedDates, dateStr) {
        //         console.log('hora_llegada onChange fired', dateStr);
        //         if (dateStr) {
        //             ingresoPicker.set('minDate', dateStr);
        //         }
        //         calcularTiempoServicio();
        //     }
        // });
        // pickers['hora_llegada'] = llegadaPicker;

        // const ingresoPicker = flatpickr(ingresoInput, {
        //     ...timeConfig,
        //     onChange: function (selectedDates, dateStr) {
        //         console.log('hora_ingreso onChange fired', dateStr);
        //         calcularTiempoServicio();
        //     }
        // });
        // pickers['hora_ingreso'] = ingresoPicker;

        // Add listeners for horas_espera calculation
        const horaDespachoInput = document.getElementById('hora_despacho');
        const horaRecepcionInput = document.getElementById('hora_recepcion_paciente');

        if (horaDespachoInput) {
            horaDespachoInput.addEventListener('change', calcularHorasEspera);
        }
        if (horaRecepcionInput) {
            horaRecepcionInput.addEventListener('change', calcularHorasEspera);
            horaRecepcionInput.addEventListener('blur', calcularHorasEspera);
        }

        // Add "Now" button logic (marca visualmente el botón cuando se usa)
        document.querySelectorAll('.btn-set-now').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.dataset.target;
                const picker = pickers[targetId];
                const now = new Date();

                if (picker) {
                    // flatpickr: esto dispara onChange y recalcula tiempos
                    picker.setDate(now, true);
                } else if (targetId) {
                    // Soporte para campos sin flatpickr (ej. hora_despacho, hora_final, hora_recepcion_paciente)
                    const input = document.getElementById(targetId);
                    if (input) {
                        const hh = String(now.getHours()).padStart(2, '0');
                        const mm = String(now.getMinutes()).padStart(2, '0');
                        input.value = `${hh}:${mm}`;
                        try {
                            calcularTiempoServicio();
                            calcularHorasEspera();
                        } catch (e) { }
                    }
                }

                // Marcar visualmente el botón como "ya usado"
                btn.classList.add('btn-set-now-used');
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-success');
                btn.setAttribute('aria-pressed', 'true');
                // Tooltip amigable indicando que la hora fue registrada
                btn.title = 'Hora marcada';
            });
        });
    }

    // -------------------
    // EVENTOS: AUTOFILL DESCRIPCIONES Y POLIZA
    // -------------------
    function setupEventoAutofill() {
        try {
            const pairs = [
                { sel: '#tipo_evento', out: '#desc_tipo_evento' },
                { sel: '#condicion_victima', out: '#desc_condicion_victima' },
                { sel: '#tipo_vehiculo_accidente', out: '#desc_tipo_vehiculo' },
                { sel: '#estado_aseguramiento', out: '#desc_estado_aseguramiento' }
            ];
            pairs.forEach(p => {
                const s = document.querySelector(p.sel);
                const o = document.querySelector(p.out);
                if (s && o) {
                    s.addEventListener('change', () => {
                        const txt = s.options && s.selectedIndex >= 0 ? s.options[s.selectedIndex].text : (s.value || '');
                        if (!o.value) o.value = txt;
                    });
                }
            });
            const inicio = document.getElementById('fecha_inicio_poliza');
            const fin = document.getElementById('fecha_fin_poliza');
            if (inicio && fin && window.moment) {
                inicio.addEventListener('change', () => {
                    if (!inicio.value) { fin.value = ''; return; }
                    const m = window.moment(inicio.value, ['YYYY-MM-DD', 'DD-MM-YYYY', 'YYYY/MM/DD'], true);
                    if (!m.isValid()) return;
                    const plus = m.clone().add(365, 'days');
                    fin.value = plus.format('YYYY-MM-DD');
                });
            }
        } catch (e) { console.warn('setupEventoAutofill error', e); }
    }

    // -------------------
    // GLASGOW TOTAL
    // -------------------
    function setupGlasgow() {
        try {
            const ocular = () => parseInt((document.querySelector('input[name="ocular"]:checked') || {}).value || '0', 10);
            const verbal = () => parseInt((document.querySelector('input[name="verbal"]:checked') || {}).value || '0', 10);
            const motora = () => parseInt((document.querySelector('input[name="motora"]:checked') || {}).value || '0', 10);
            const out = document.getElementById('escala_glasgow');
            const calc = () => { if (out) out.value = String(ocular() + verbal() + motora()); };
            document.querySelectorAll('.glasgow-check').forEach(el => el.addEventListener('change', calc));
            calc();
        } catch (e) { console.warn('setupGlasgow error', e); }
    }

    // -------------------
    // ANTECEDENTES TOGGLE
    // -------------------
    function setupAntecedentes() {
        try {
            document.querySelectorAll('select[id^="ant_"][id$="_sn"]').forEach(sel => {
                const id = sel.id.replace(/_sn$/, '');
                const container = document.getElementById(id + '_cual_container');
                const otroInput = document.getElementById(id + '_cual_otro');
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
                        const hasOtro = Array.from(multi.options).some(op => op.selected && /otro/i.test(op.value || op.text));
                        otroInput.style.display = hasOtro ? '' : 'none';
                        if (!hasOtro) otroInput.value = '';
                    });
                }
            });
        } catch (e) { console.warn('setupAntecedentes error', e); }
    }

    // -------------------
    // ACOMPAÑANTE: TOGGLE CAMPOS
    // -------------------
    function setupAcompanante() {
        try {
            const sel = document.getElementById('hay_acompanante');
            const ctn = document.getElementById('acompanante-container');
            if (!sel || !ctn) return;

            const nombre = document.getElementById('nombre_acompanante');
            const parentesco = document.getElementById('parentesco_acompanante');
            const idA = document.getElementById('id_acompanante');

            const apply = () => {
                const isYes = String(sel.value || '').trim() === '1';
                ctn.style.display = isYes ? '' : 'none';
                if (!isYes) {
                    if (nombre) nombre.value = '';
                    if (parentesco) parentesco.value = '';
                    if (idA) idA.value = '';
                }
            };

            sel.addEventListener('change', apply);
            if (window.jQuery && jQuery(sel).data('select2')) {
                jQuery(sel).on('change', apply);
            }

            apply();
        } catch (e) { console.warn('setupAcompanante error', e); }
    }

    // -------------------
    // IMC RISK COLOR
    // -------------------
    function setupIMCColors() {
        const badge = document.getElementById('imc-riesgo');
        if (!badge) return;
        const apply = () => {
            const t = (badge.textContent || '').toLowerCase();
            badge.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-secondary');
            if (!t) { badge.classList.add('bg-secondary'); return; }
            if (t.includes('normal')) badge.classList.add('bg-success');
            else if (t.includes('bajo') || t.includes('sobrepeso')) badge.classList.add('bg-warning');
            else badge.classList.add('bg-danger');
        };
        const obs = new MutationObserver(apply);
        obs.observe(badge, { childList: true, characterData: true, subtree: true });
        apply();
    }

    // -------------------
    // REMISIÓN: CAMPOS CONDICIONALES
    // -------------------
    function setupRemision() {
        try {
            const sel = document.getElementById('viene_remitida');
            if (!sel) return;

            const findContainer = (id) => {
                const el = document.getElementById(id);
                if (!el) return null;
                // Preferir la columna bootstrap más cercana
                return el.closest('.col-md-4, .col-md-6, .col') || el.parentElement;
            };

            const ctnPrestador = findContainer('codigo_prestador_remitente');
            const ctnDespacho = findContainer('codigo_despacho_crue');
            const ctnCrue = findContainer('codigo_crue_solicita');

            const toggle = () => {
                const isYes = String(sel.value || '').trim() === '1';
                const show = (ctn, doShow) => { if (ctn) ctn.style.display = doShow ? '' : 'none'; };
                show(ctnPrestador, isYes);
                show(ctnDespacho, isYes);
                show(ctnCrue, isYes);
            };

            sel.addEventListener('change', toggle);
            // Si está inicializado con Select2, escuchar también el evento de ese plugin
            if (window.jQuery && jQuery(sel).data('select2')) {
                jQuery(sel).on('change', toggle);
            }

            // Estado inicial
            toggle();
        } catch (e) {
            console.warn('setupRemision error', e);
        }
    }

    // -------------------
    // AUTO-FILL TRIPULANTE DATA FROM SESSION
    // -------------------
    function setupTripulanteAutofill() {
        if (!window.userData || !window.userData.nombre) {
            return; // No hay datos de usuario
        }

        const tripulanteField = document.getElementById('tripulante');
        const ccTripulanteField = document.getElementById('cc_tripulante');
        const registroTripulanteField = document.getElementById('registro_tripulante');
        const tipoIdField = document.getElementById('tipo_id_tripulante');

        // Auto-llenar nombre completo
        if (tripulanteField && !tripulanteField.value) {
            const nombreCompleto = [window.userData.nombre, window.userData.apellidos]
                .filter(part => part && part.trim())
                .join(' ');
            if (nombreCompleto) {
                tripulanteField.value = nombreCompleto;
                tripulanteField.setAttribute('readonly', true);
                tripulanteField.style.backgroundColor = '#f0f0f0';
            }
        }

        // Auto-llenar CC o Registro según disponibilidad
        if (ccTripulanteField && !ccTripulanteField.value) {
            if (window.userData.registro) {
                ccTripulanteField.value = window.userData.registro;
                if (tipoIdField) tipoIdField.value = 'Registro';
            } else if (window.userData.cc) {
                ccTripulanteField.value = window.userData.cc;
                if (tipoIdField) tipoIdField.value = 'CC';
            }
            if (ccTripulanteField.value) {
                ccTripulanteField.setAttribute('readonly', true);
                ccTripulanteField.style.backgroundColor = '#f0f0f0';
            }
        }

        // Auto-llenar registro profesional si existe
        if (registroTripulanteField && !registroTripulanteField.value && window.userData.registro) {
            registroTripulanteField.value = window.userData.registro;
            registroTripulanteField.setAttribute('readonly', true);
            registroTripulanteField.style.backgroundColor = '#f0f0f0';
        }
    }

    // -------------------
    // INITIALIZATION
    // -------------------
    function init() {
        if (!checkDependencies()) return;

        // Set global locales
        moment.locale('es');
        flatpickr.localize(flatpickr.l10ns.es);

        // Initialize components
        setupDatePickers();
        setupTimePickers();
        setupSelect2();
        setupAfiliacionAutofill();
        setupTipoServicio();
        setupPagador();
        setupGeoButtons();
        setupGlasgow();
        setupAntecedentes();
        setupIMCColors();
        setupRemision();
        setupAcompanante();
        setupTripulanteAutofill(); // Auto-llenar datos del tripulante
        setupMedicamentos();

        // Add listeners for distance calculation
        const kmInicialInput = document.getElementById('km_inicial');
        const kmFinalInput = document.getElementById('km_final');
        if (kmInicialInput) {
            kmInicialInput.addEventListener('change', calcularDistanciaRecorrida);
            kmInicialInput.addEventListener('blur', calcularDistanciaRecorrida);
        }
        if (kmFinalInput) {
            kmFinalInput.addEventListener('change', calcularDistanciaRecorrida);
            kmFinalInput.addEventListener('blur', calcularDistanciaRecorrida);
        }

        // Initial calculation
        calcularTiempoServicio();

        // Re-initialize dynamic selects on window focus as a fallback
        window.addEventListener('focus', setupSelect2);
    }

    // Run initialization on DOM ready
    jQuery(document).ready(init);

})(window, document, window.jQuery, window.moment, window.flatpickr);
// -------------------
// Causa Externa -> Categoría (autofill)
// -------------------
(function setupCausaExternaCategoria() {
    const causaSel = document.getElementById('causa_externa');
    const catSel = document.getElementById('causa_externa_categoria');
    if (!causaSel || !catSel) return;

    const mapCategoria = (val) => {
        const v = String(val || '').trim();
        if (!v) return '';

        // Si viene código 01–48
        if (/^\d{2}$/.test(v)) {
            if (['01', '02', '03', '04', '05', '21', '22', '23', '24', '25', '26', '45', '46', '47', '48'].includes(v)) return 'accidente';
            if (['06', '27', '44'].includes(v)) return 'evento_catastrofico';
            if (['07', '08', '09', '10', '11', '12', '28', '29', '30', '31', '32', '33'].includes(v)) return 'violencia';
            if (['34', '35', '36'].includes(v)) return 'salud_sexual';
            if (['13', '14', '37', '38', '39'].includes(v)) return 'enfermedad';
            if (['40', '41', '42', '43'].includes(v)) return 'salud_publica';
            if (v === '15') return 'otro';
        }

        // Compatibilidad con texto antiguo
        const txt = v.toLowerCase();
        if (txt.includes('trabajo') || txt.includes('transito') || txt.includes('accident')) return 'accidente';
        if (txt.includes('catastrof')) return 'evento_catastrofico';
        if (txt.includes('agresion') || txt.includes('auto infligida') || txt.includes('autoinfligida') ||
            txt.includes('maltrato') || txt.includes('violencia')) return 'violencia';
        if (txt.includes('ive') || txt.includes('embarazo')) return 'salud_sexual';
        if (txt.includes('enfermedad') || txt.includes('evento adverso')) return 'enfermedad';
        if (txt.includes('promoción') || txt.includes('mantenimiento') || txt.includes('intervención') ||
            txt.includes('riesgo ambiental')) return 'salud_publica';
        if (txt.includes('otra') || txt.includes('otro')) return 'otro';

        return '';
    };

    const applyCategoria = () => {
        const catValue = mapCategoria(causaSel.value);
        if (!catValue) return;
        catSel.value = catValue;
        // Si está con select2, dispara el cambio para actualizar UI
        if (window.jQuery && jQuery(catSel).data('select2')) {
            jQuery(catSel).trigger('change');
        }
    };

    causaSel.addEventListener('change', applyCategoria);
    // Inicializar por si viene precargado
    applyCategoria();
})();