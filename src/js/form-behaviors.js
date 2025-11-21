/**
 * form-behaviors.js — Versión final integrada
 * - navegación por secciones
 * - validación híbrida (por paso + total al finalizar)
 * - guardado parcial (procesos/guardar_parcial.php)
 * - envío final (procesar_atencion_v4.php)
 * - mantiene IMC y validaciones de signos vitales
 *
 * Requiere:
 *  - window.AppConfig.baseUrl definido (header.php)
 *  - form-signatures.js maneja SignaturePad y hidden inputs Firma*Data
 */

(function () {
  'use strict';

  const baseUrl = (window.AppConfig && window.AppConfig.baseUrl) || '/';

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('clinical-form');
    if (!form) {
      console.warn('⚠️ form-behaviors.js: no se encontró #clinical-form — abortando comportamiento principal.');
      return;
    }

    // --- Detectar secciones: preferencia: section > .form-container > .form-section ---
    let sections = Array.from(form.querySelectorAll('section'));
    if (!sections.length) sections = Array.from(form.querySelectorAll('.form-container.form-section, .form-section, .form-container'));
    if (!sections.length) {
      // fallback: group by direct children that contain inputs
      sections = Array.from(form.children).filter(ch => ch.querySelector && ch.querySelector('input, select, textarea'));
    }
    if (!sections.length) {
      console.warn('⚠️ form-behaviors.js: no se encontraron secciones manejables dentro del formulario.');
      return;
    }

    // Normalize sections: ensure they have dataset.step
    sections.forEach((s, i) => {
      if (!s.dataset.step) s.dataset.step = String(i + 1);
      if (!s.id) s.id = `step_auto_${i + 1}`;
    });

    // --- Persistencia ligera de paso actual (solo índice, sin valores de campos) ---
    // Clave basada en atencion_id cuando exista, para evitar mezclar pasos entre atenciones.
    const atencionInputForStep = form.querySelector('input[name="atencion_id"], input#atencion_id');
    const localAtencionId = (atencionInputForStep && atencionInputForStep.value) || localStorage.getItem('atencion_id') || '';
    const stepStorageKey = localAtencionId ? `wizard_current_step_${localAtencionId}` : 'wizard_current_step_generic';

    let currentStep = 0;
    // find if any section already has class active
    const idxActive = sections.findIndex(s => s.classList.contains('active'));
    if (idxActive >= 0) currentStep = idxActive;

    // Si hay un paso previo guardado en localStorage para esta atención, úsalo si es válido.
    try {
      const savedStepRaw = localStorage.getItem(stepStorageKey);
      if (savedStepRaw != null) {
        const savedStep = parseInt(savedStepRaw, 10);
        if (!Number.isNaN(savedStep) && savedStep >= 0 && savedStep < sections.length) {
          currentStep = savedStep;
        }
      }
    } catch (e) { /* noop: si localStorage falla, continuamos con currentStep calculado */ }

    // Buttons
    const btnNext = document.querySelector('.btn-next');
    const btnPrev = document.querySelector('.btn-prev');
    const btnFinalizar = document.getElementById('btnFinalizar');

    // Helpers
    function isVisible(el) {
      return !!(el && el.offsetParent !== null);
    }

    // Progress bar elements
    const progressWrap = document.getElementById('wizardProgress');
    const progressBar = progressWrap ? progressWrap.querySelector('.progress-bar') : null;
    const savedStepEl = document.getElementById('wizardSavedStep');
    const serviceTimerEl = document.getElementById('wizardServiceTimer');
    let serviceTimerInterval = null;

    function formatDuration(totalSeconds) {
      const sec = Math.max(0, Math.floor(totalSeconds));
      const h = Math.floor(sec / 3600);
      const m = Math.floor((sec % 3600) / 60);
      const s = sec % 60;
      const mm = String(m).padStart(2, '0');
      const ss = String(s).padStart(2, '0');
      if (h > 0) {
        const hh = String(h).padStart(2, '0');
        return `${hh}:${mm}:${ss}`;
      }
      return `${mm}:${ss}`;
    }

    function startServiceTimer() {
      if (!serviceTimerEl) return;
      const startedAt = Date.now();
      const update = () => {
        const diffSec = (Date.now() - startedAt) / 1000;
        serviceTimerEl.textContent = `Tiempo en servicio: ${formatDuration(diffSec)}`;
      };
      update();
      serviceTimerInterval = window.setInterval(update, 1000);
    }

    function updateSavedStepIndicator(stepIndex) {
      if (!savedStepEl) return;
      try {
        const raw = localStorage.getItem(stepStorageKey);
        const savedIdx = raw != null ? parseInt(raw, 10) : NaN;
        if (!Number.isNaN(savedIdx) && savedIdx >= 0 && savedIdx < sections.length) {
          const total = sections.length;
          const humanStep = savedIdx + 1;
          const title = getSectionTitle(savedIdx) || '';
          const titlePart = title ? ` · ${title}` : '';
          savedStepEl.textContent = `Último paso guardado: ${humanStep}/${total}${titlePart}`;
          savedStepEl.style.display = '';
        } else {
          savedStepEl.textContent = '';
          savedStepEl.style.display = 'none';
        }
      } catch (e) {
        savedStepEl.textContent = '';
        savedStepEl.style.display = 'none';
      }
    }
    function applyProgressColor() {
      if (!progressBar) return;
      const formEl = document.getElementById('clinical-form');
      const cls = ['progress-bar-tab', 'progress-bar-tam', 'progress-bar-aph'];
      progressBar.classList.remove(...cls);
      if (formEl?.classList.contains('theme--tam')) progressBar.classList.add('progress-bar-tam');
      else if (formEl?.classList.contains('theme--aph')) progressBar.classList.add('progress-bar-aph');
      else progressBar.classList.add('progress-bar-tab');
    }
    function getSectionTitle(stepIndex) {
      try {
        const sec = sections[stepIndex];
        if (!sec) return '';
        const h3 = sec.querySelector('.section-header h3');
        if (!h3) return '';
        // build text without hl7-tag spans
        let txt = '';
        h3.childNodes.forEach(n => { if (n.nodeType === Node.TEXT_NODE) txt += n.textContent; });
        txt = txt.replace(/\s+/g, ' ').trim();
        return txt || h3.textContent.trim();
      } catch { return ''; }
    }
    function updateProgress(stepIndex) {
      if (!progressBar) return;
      const total = sections.length;
      const current = stepIndex + 1;
      const pct = Math.round((current / total) * 100);
      progressBar.style.width = pct + '%';
      progressBar.setAttribute('aria-valuenow', String(pct));
      const sr = progressBar.querySelector('.visually-hidden');
      if (sr) sr.textContent = pct + '%';
      const titleText = `Paso ${current}/${total} · ${getSectionTitle(stepIndex)}`;
      const titleEl = document.getElementById('wizardProgressTitle');
      if (titleEl) titleEl.textContent = titleText;
      applyProgressColor();
      updateSavedStepIndicator(stepIndex);
    }

    function showStep(index) {
      sections.forEach((sec, i) => {
        if (i === index) {
          sec.classList.add('active');
          sec.style.display = '';
        } else {
          sec.classList.remove('active');
          sec.style.display = 'none';
        }
      });
      currentStep = index;
      if (btnPrev) btnPrev.disabled = index === 0;
      if (btnNext) btnNext.style.display = index === sections.length - 1 ? 'none' : 'inline-block';
      if (btnFinalizar) btnFinalizar.classList.toggle('d-none', index !== sections.length - 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      updateProgress(currentStep);
    }

    // --- VALIDACIONES ---

    // Basic: required visible elements in a section
    function validateSectionBasic(index) {
      const sec = sections[index];
      const requiredEls = Array.from(sec.querySelectorAll('input[required], select[required], textarea[required]'));
      const errors = [];

      requiredEls.forEach(el => {
        if (!isVisible(el)) return; // only validate visible required
        if (el.type === 'checkbox' || el.type === 'radio') {
          // group check: ensure any in group checked
          const name = el.name;
          if (name) {
            const anyChecked = sec.querySelectorAll(`[name="${name}"]:checked`).length > 0;
            if (!anyChecked) errors.push(`Campo requerido: ${getLabelText(el) || name}`);
          }
        } else if (!String(el.value || '').trim()) {
          errors.push(`Campo requerido: ${getLabelText(el) || el.name}`);
        }
      });

      // Additional local checks: pressure field inside section
      const taEl = sec.querySelector('#tension_arterial');
      if (taEl && isVisible(taEl) && String(taEl.value || '').trim()) {
        if (!/^\s*\d{2,3}\s*\/\s*\d{2,3}\s*$/.test(taEl.value.trim())) {
          errors.push('Tensión arterial debe tener formato S/ D (ej. 120/80)');
        }
      }

      // Validate times if present
      const times = Array.from(sec.querySelectorAll('input[type="time"], input.time-input')).filter(isVisible);
      times.forEach(t => {
        if (!String(t.value || '').trim()) errors.push(`Campo hora requerido: ${getLabelText(t) || t.name}`);
      });

      return errors;
    }

    // Full validation across the whole form (called at finalize)
    function validateAll() {
      const errors = [];

      // 1. required visible anywhere
      const requiredEls = Array.from(form.querySelectorAll('input[required], select[required], textarea[required]'));
      requiredEls.forEach(el => {
        if (!isVisible(el)) return;
        if (el.type === 'checkbox' || el.type === 'radio') {
          const name = el.name;
          if (name) {
            const anyChecked = form.querySelectorAll(`[name="${name}"]:checked`).length > 0;
            if (!anyChecked) errors.push(`Campo requerido: ${getLabelText(el) || name}`);
          }
        } else if (!String(el.value || '').trim()) {
          errors.push(`Campo requerido: ${getLabelText(el) || el.name}`);
        }
      });

      // 2. Signos vitales logical checks (if present)
      const fc = parseFloat(document.getElementById('frecuencia_cardiaca')?.value || NaN);
      const fr = parseFloat(document.getElementById('frecuencia_respiratoria')?.value || NaN);
      const spo2 = parseFloat(document.getElementById('spo2')?.value || NaN);
      const glu = parseFloat(document.getElementById('glucometria')?.value || NaN);
      const temp = parseFloat(document.getElementById('temperatura')?.value || NaN);
      if (!isNaN(fc) && (fc < 20 || fc > 250)) errors.push('Frecuencia cardiaca fuera de rango plausible.');
      if (!isNaN(fr) && (fr < 5 || fr > 60)) errors.push('Frecuencia respiratoria fuera de rango plausible.');
      if (!isNaN(spo2) && (spo2 < 30 || spo2 > 100)) errors.push('SpO2 fuera de rango (30-100).');
      if (!isNaN(glu) && (glu < 10 || glu > 2000)) errors.push('Glucometría fuera de rango plausible.');
      if (!isNaN(temp) && (temp < 30 || temp > 45)) errors.push('Temperatura fuera de rango plausible.');

      // 3. Tension arterial format and logic
      const ta = document.getElementById('tension_arterial')?.value || '';
      if (ta.trim()) {
        const m = ta.match(/^\s*(\d{2,3})\s*\/\s*(\d{2,3})\s*$/);
        if (!m) errors.push('Tensión arterial debe tener formato S/D (ej. 120/80).');
        else {
          const s = parseInt(m[1], 10), d = parseInt(m[2], 10);
          if (s < 50 || s > 300 || d < 30 || d > 200) errors.push('Tensión arterial con valores no plausibles.');
          if (s <= d) errors.push('Tensión sistólica debe ser mayor que diastólica.');
        }
      }

      // 4. Tripulante must exist
      const tripHidden = document.getElementById('tripulante_hidden') || document.getElementById('tripulante');
      if (tripHidden && !String(tripHidden.value || '').trim()) {
        errors.push('El Tripulante es obligatorio.');
      }

      // 5. Signatures: require tripulante; require patient or desistimiento; if accept require medico receptor when visible
      const firmaT = document.getElementById('FirmaParamedicoData')?.value || document.getElementById('firma_paramedico')?.value || '';
      const firmaP = document.getElementById('FirmaPacienteData')?.value || document.getElementById('firma_paciente')?.value || '';
      const firmaD = document.getElementById('FirmaDesistimientoData')?.value || document.getElementById('firma_desistimiento')?.value || '';
      const firmaRec = document.getElementById('FirmaMedicoReceptorData')?.value || document.getElementById('firma_medico_receptor')?.value || '';

      if (!firmaT) errors.push('Firma del tripulante requerida.');
      if (!firmaP && !firmaD) errors.push('Se requiere firma de aceptación o firma de desistimiento.');
      // if accepted (patient signature present) check medico receptor if visible and not APH
      if (firmaP && !firmaD) {
        const medRecContainer = document.getElementById('medico-receptor-firma-container');
        const visible = medRecContainer && window.getComputedStyle(medRecContainer).display !== 'none';
        if (visible && !firmaRec && !isAPHSelected()) {
          errors.push('Firma del médico receptor es obligatoria cuando se acepta la atención.');
        }
      }

      return errors;
    }

    function getLabelText(el) {
      try {
        if (!el) return '';
        const id = el.id;
        if (id) {
          const lab = form.querySelector(`label[for="${id}"]`);
          if (lab) return lab.textContent.trim();
        }
        // fallback to placeholder or name
        return el.placeholder || el.getAttribute('aria-label') || el.name || '';
      } catch (e) { return el.name || ''; }
    }

    // Helper to detect APH/TAM from service select
    function isAPHSelected() {
      try {
        const servicioSel = document.getElementById('servicio');
        if (!servicioSel) return false;
        const v = (servicioSel.value || '').toLowerCase();
        return v.includes('aph') || v.includes('prehospitalaria');
      } catch { return false; }
    }
    function isTAMSelected() {
      try {
        const servicioSel = document.getElementById('servicio');
        if (!servicioSel) return false;
        const v = (servicioSel.value || '').toLowerCase();
        return v.includes('tam') || v.includes('medicalizado');
      } catch { return false; }
    }

    // --- Guardado parcial per-step (reusable) ---
    function shouldSaveField(name) {
      if (!name) return false;
      // Exclude UI/helper/signature/display fields not mapped to DB
      if (/Firma.*Data/i.test(name)) return false;
      if (/^firmas\[/i.test(name)) return false;
      if (/_display$/i.test(name)) return false;
      if (/_hidden$/i.test(name)) return false;
      if (name === 'tripulante_hidden') return false;
      if (name === 'consent_type') return false; // only final submission
      return true;
    }
    async function guardarParcial(index) {
      const sec = sections[index];
      if (!sec) return null;
      // Sync signatures before collecting data so hidden inputs are up-to-date
      try {
        if (typeof updateHidden === 'function') {
          ['paramedico', 'medico', 'paciente', 'desistimiento', 'medicoReceptor', 'receptorIPS'].forEach(updateHidden);
        }
      } catch (e) { }
      const inputs = sec.querySelectorAll('input, select, textarea');
      const payload = {};
      inputs.forEach(i => {
        if (i.name && !i.disabled && shouldSaveField(i.name)) {
          // skip file inputs
          if (i.type === 'file') return;
          payload[i.name] = i.value || '';
        }
      });
      // If there's nothing to save, skip the request to avoid backend 400s
      if (Object.keys(payload).length === 0) {
        return { success: true, skipped: true };
      }
      // include atencion_id if present in form
      const atencionInput = form.querySelector('input[name="atencion_id"], input#atencion_id');
      const idVal = atencionInput?.value || localStorage.getItem('atencion_id') || '';
      try {
        const url = baseUrl + 'procesos/guardar_parcial.php';
        const body = new URLSearchParams();
        body.append('id', idVal);
        body.append('seccion', index + 1);
        body.append('data', JSON.stringify(payload));
        const resp = await fetch(url, { method: 'POST', body });
        const json = await resp.json();
        if (json.success) {
          const newId = json.id || idVal;
          if (newId) {
            localStorage.setItem('atencion_id', newId);
            const atInp = form.querySelector('input[name="atencion_id"], input#atencion_id');
            if (atInp) atInp.value = newId;
          }
          return json;
        } else {
          console.warn('guardar_parcial: respuesta no-ok:', json);
          return json;
        }
      } catch (e) {
        console.error('guardar_parcial: error fetch', e);
        return { success: false, message: 'Error de conexión' };
      }
    }

    // Ensure only the first (or active) section shows on load
    showStep(currentStep);

    // Iniciar pequeño cronómetro visual de tiempo en servicio (solo UI)
    startServiceTimer();

    // Apply initial color in case service is already set
    applyProgressColor();

    // Listen for service changes to recolor the bar in real-time
    document.addEventListener('serviciochange', applyProgressColor);

    // --- Animación de barra de progreso y previsualización de adjuntos ---
    (function initAdjuntosProgress() {
      try {
        const input = document.getElementById('adjuntos_input');
        if (!input) return;
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const meta = document.getElementById('adjuntos_meta');
        const preview = document.getElementById('adjuntos_preview');
        let urls = [];

        function resetPreview() {
          if (preview) preview.innerHTML = '';
          urls.forEach(u => { try { URL.revokeObjectURL(u); } catch (e) { } });
          urls = [];
        }

        input.addEventListener('change', (event) => {
          const files = Array.from(event.target?.files || []);
          // Progreso
          if (progressContainer && progressBar) {
            if (files.length > 0) {
              progressContainer.style.display = 'block';
              let progress = 0;
              progressBar.style.width = '0%';
              progressBar.setAttribute('aria-valuenow', '0');
              progressBar.textContent = '0%';
              const interval = setInterval(() => {
                progress += 10;
                if (progress >= 100) { progress = 100; clearInterval(interval); }
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', String(progress));
                progressBar.textContent = progress + '%';
              }, 200);
            } else {
              progressContainer.style.display = 'none';
              progressBar.style.width = '0%';
              progressBar.setAttribute('aria-valuenow', '0');
              progressBar.textContent = '0%';
            }
          }

          // Meta (conteo y tamaño total)
          if (meta) {
            const totalBytes = files.reduce((sum, f) => sum + (f.size || 0), 0);
            const mb = (totalBytes / (1024 * 1024)).toFixed(1);
            meta.textContent = `${files.length} archivos — ${mb} MB`;
          }

          // Previews
          resetPreview();
          if (preview && files.length) {
            files.forEach((f) => {
              const col = document.createElement('div');
              col.className = 'col-6 col-md-4 col-lg-3';
              const card = document.createElement('div');
              card.className = 'card h-100';
              const body = document.createElement('div');
              body.className = 'card-body p-2 d-flex flex-column justify-content-between';

              if (f.type && f.type.startsWith('image/')) {
                const url = URL.createObjectURL(f);
                urls.push(url);
                const img = document.createElement('img');
                img.src = url;
                img.alt = f.name;
                img.style.maxWidth = '100%';
                img.style.height = '120px';
                img.style.objectFit = 'cover';
                img.className = 'mb-2 rounded border';
                body.appendChild(img);
              } else {
                const icon = document.createElement('div');
                icon.className = 'mb-2 text-center';
                icon.innerHTML = '<span class="badge bg-secondary">' + (f.type || 'archivo') + '</span>';
                body.appendChild(icon);
              }

              const name = document.createElement('div');
              name.className = 'small text-truncate';
              name.title = f.name;
              name.textContent = f.name;
              body.appendChild(name);

              card.appendChild(body);
              col.appendChild(card);
              preview.appendChild(col);
            });
          }

          // Recomendación si excede 10
          if (files.length > 10) {
            try { alert('Sugerencia: se recomienda adjuntar máx. 10 archivos.'); } catch (e) { }
          }
        });
      } catch (e) { /* noop */ }
    })();

    // --- Navegación: next/prev handlers ---
    const nextButtons = Array.from(document.querySelectorAll('.btn-next'));
    const prevButtons = Array.from(document.querySelectorAll('.btn-prev'));

    nextButtons.forEach(btn => {
      btn.addEventListener('click', async (ev) => {
        ev.preventDefault();
        // validate current step basic required visible
        const basicErrors = validateSectionBasic(currentStep);
        if (basicErrors.length) {
          alert('Corrija estos errores antes de continuar:\n\n' + basicErrors.join('\n'));
          return;
        }
        // Additional lightweight checks: pressure pair, times
        const ta = sections[currentStep].querySelector('#tension_arterial');
        if (ta && ta.value.trim()) {
          if (!/^\s*\d{2,3}\s*\/\s*\d{2,3}\s*$/.test(ta.value.trim())) {
            alert('Tensión arterial: formato inválido (ej. 120/80)');
            return;
          }
        }
        // Save partial then advance; solo actualizamos el paso guardado si la respuesta fue exitosa
        const result = await guardarParcial(currentStep);
        if (result && result.success) {
          try {
            localStorage.setItem(stepStorageKey, String(currentStep));
          } catch (e) { /* noop */ }
          updateSavedStepIndicator(currentStep);
        }
        if (currentStep < sections.length - 1) {
          currentStep++;
          showStep(currentStep);
        }
      });
    });

    prevButtons.forEach(btn => {
      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        if (currentStep > 0) {
          currentStep--;
          showStep(currentStep);
        }
      });
    });

    // --- Finalizar: full validation + enviar a procesar_atencion_v4.php ---
    const finalizarBtn = document.getElementById('btnFinalizar') || document.querySelector('.btn-finalizar');
    if (finalizarBtn) {
      finalizarBtn.addEventListener('click', async (ev) => {
        ev.preventDefault();
        // First, sync signature hidden inputs so validation sees current canvas state
        try {
          if (typeof updateHidden === 'function') {
            ['paramedico', 'medico', 'paciente', 'desistimiento', 'medicoReceptor', 'receptorIPS'].forEach(updateHidden);
          }
        } catch (e) { }
        // Run complete validation
        const errors = validateAll();
        if (errors.length) {
          alert('Por favor corrija los siguientes errores antes de finalizar:\n\n- ' + errors.join('\n- '));
          return;
        }

        // Prepare form data and send final
        try {
          const fd = new FormData(form);
          // include atencion_id fallback
          const localId = localStorage.getItem('atencion_id');
          if (localId && !fd.has('atencion_id')) fd.append('atencion_id', localId);
          const adjInput = document.getElementById('adjuntos_input');
          const selectedFiles = Array.from(adjInput?.files || []).map(f => ({ name: f.name, type: f.type, size: f.size }));
          const pc = document.getElementById('progress-container');
          const pb = document.getElementById('progress-bar');
          if (pc && pb) { pc.style.display = 'block'; pb.style.width = '100%'; pb.setAttribute('aria-valuenow', '100'); pb.textContent = '100%'; }

          const url = baseUrl + 'procesar_atencion.php';
          const resp = await fetch(url, { method: 'POST', body: fd });

          const ct = resp.headers.get('content-type') || '';
          const rawText = await resp.text();

          // Validar que la respuesta sea HTTP OK y con JSON válido
          if (!resp.ok || !ct.includes('application/json')) {
            const snippet = (rawText || '').slice(0, 600);
            console.error('procesar_atencion_v4.php non-JSON/HTTP error', resp.status, resp.statusText, snippet);
            alert(`Error del servidor (${resp.status}). Consulte a soporte.\n\nDetalle:\n${snippet}`);
            return;
          }

          let json;
          try {
            json = rawText ? JSON.parse(rawText) : null;
          } catch (parseErr) {
            const snippet = (rawText || '').slice(0, 600);
            console.error('procesar_atencion_v4.php JSON parse error', parseErr, snippet);
            alert('Error al interpretar la respuesta del servidor. Consulte a soporte.\n\nDetalle:\n' + snippet);
            return;
          }
          if (json && json.success) {
            if (serviceTimerInterval) {
              window.clearInterval(serviceTimerInterval);
              serviceTimerInterval = null;
            }
            localStorage.removeItem('atencion_id');
            for (let i = 0; i < sections.length; i++) localStorage.removeItem(`section_${i}`);
            // limpiar paso guardado para esta atención
            try {
              localStorage.removeItem(stepStorageKey);
            } catch (e) { /* noop */ }
            updateSavedStepIndicator(currentStep);
            if (adjInput) adjInput.disabled = true;
            const wrap = document.getElementById('form-wizard');
            if (wrap) {
              const servicio = document.getElementById('servicio')?.value || '';
              const paciente = document.getElementById('nombres_paciente')?.value || '';
              const idpac = document.getElementById('id_paciente')?.value || '';
              const ips = document.getElementById('ips_nombre')?.value || document.getElementById('nombre_ips_receptora')?.value || '';
              const fecha = document.getElementById('fecha')?.value || '';
              const reg = document.getElementById('registro')?.value || '';
              const filesHtml = selectedFiles.length ? selectedFiles.map(f => `<li>${f.name}</li>`).join('') : '<li>Sin adjuntos</li>';
              const resumen = `
                <div class="row g-3 mt-3">
                  <div class="col-md-4">
                    <div class="card h-100"><div class="card-body">
                      <h5 class="card-title mb-2">Atención</h5>
                      <p class="mb-1"><strong>ID:</strong> ${json.id ?? ''}</p>
                      <p class="mb-1"><strong>Registro:</strong> ${reg}</p>
                      <p class="mb-1"><strong>Fecha:</strong> ${fecha}</p>
                      <p class="mb-0"><strong>Servicio:</strong> ${servicio}</p>
                    </div></div>
                  </div>
                  <div class="col-md-4">
                    <div class="card h-100"><div class="card-body">
                      <h5 class="card-title mb-2">Paciente</h5>
                      <p class="mb-1"><strong>Nombre:</strong> ${paciente}</p>
                      <p class="mb-0"><strong>Documento:</strong> ${idpac}</p>
                    </div></div>
                  </div>
                  <div class="col-md-4">
                    <div class="card h-100"><div class="card-body">
                      <h5 class="card-title mb-2">Destino</h5>
                      <p class="mb-0"><strong>IPS:</strong> ${ips}</p>
                    </div></div>
                  </div>
                </div>
                <div class="card mt-3"><div class="card-body">
                  <h5 class="card-title mb-2">Adjuntos (${selectedFiles.length})</h5>
                  <ul class="mb-0">${filesHtml}</ul>
                </div></div>
                <div class="mt-3 d-flex gap-2">
                  <a class="btn btn-outline-primary" href="${baseUrl}consulta_atenciones.php">Ir a Consultas</a>
                  ${json.id ? `<a class="btn btn-primary" target="_blank" href="${baseUrl}generar_pdf.php?id=${json.id}">Ver PDF</a>` : ''}
                </div>
              `;
              wrap.innerHTML = resumen;
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }
          } else {
            console.warn('procesar_atencion_v4.php ->', json);
            alert('Error al procesar la atención: ' + (json && json.message ? json.message : 'Error desconocido'));
          }
        } catch (err) {
          console.error('Error enviando procesar_atencion_v4.php', err);
          alert('Error de red al enviar la atención final. Intente nuevamente.');
        }
        finally {
          if (finalizarBtn) {
            finalizarBtn.disabled = false;
          }
        }
      });
    }

    // --- IMC and signs color logic (kept minimal; existing code elsewhere may also set these) ---
    (function initIMCandSigns() {
      const peso = document.getElementById('peso');
      const talla = document.getElementById('talla');
      const imc = document.getElementById('imc');
      const imcRiesgo = document.getElementById('imc-riesgo');

      function calc() {
        try {
          const p = parseFloat(peso?.value || 0);
          const t = parseFloat(talla?.value || 0) / 100;
          if (p > 0 && t > 0) {
            const val = p / (t * t);
            if (imc) imc.value = val.toFixed(1);
            if (imcRiesgo) {
              imcRiesgo.textContent = val < 18.5 ? 'Bajo peso' : val < 25 ? 'Normal' : val < 30 ? 'Sobrepeso' : 'Obesidad';
            }
          } else {
            if (imc) imc.value = '';
            if (imcRiesgo) imcRiesgo.textContent = '';
          }
        } catch (e) { }
      }
      if (peso) peso.addEventListener('input', calc);
      if (talla) talla.addEventListener('input', calc);
      calc();
    })();

    // --- Signos: color classes (preserve existing CSS classes: valor-normal/valor-alerta/valor-peligro) ---
    (function initSignListeners() {
      function addRangeListener(id, normalMin, normalMax, dangerMin, dangerMax) {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', () => {
          el.classList.remove('valor-normal', 'valor-alerta', 'valor-peligro');
          const v = parseFloat(el.value || NaN);
          if (isNaN(v)) return;
          if (v < dangerMin || v > dangerMax) el.classList.add('valor-peligro');
          else if (v < normalMin || v > normalMax) el.classList.add('valor-alerta');
          else el.classList.add('valor-normal');
        });
      }
      addRangeListener('frecuencia_cardiaca', 60, 90, 40, 160);
      addRangeListener('frecuencia_respiratoria', 12, 20, 6, 40);
      addRangeListener('spo2', 95, 100, 80, 100);
      addRangeListener('glucometria', 70, 140, 40, 600);
      addRangeListener('temperatura', 36.0, 37.5, 33.0, 41.0);

      // tension arterial special parsing
      const ta = document.getElementById('tension_arterial');
      if (ta) {
        ta.addEventListener('input', () => {
          ta.classList.remove('valor-normal', 'valor-alerta', 'valor-peligro');
          const parts = (ta.value || '').split('/');
          if (parts.length !== 2) return;
          const s = parseInt(parts[0], 10), d = parseInt(parts[1], 10);
          if (isNaN(s) || isNaN(d)) return;
          if (s > 180 || d > 120) ta.classList.add('valor-peligro');
          else if (s >= 90 && s <= 139 && d >= 60 && d <= 89) ta.classList.add('valor-normal');
          else ta.classList.add('valor-alerta');
        });
      }
    })();

    // expose a small API
    window.FormWizard = {
      showStep,
      guardarParcial,
      validateSectionBasic,
      validateAll
    };

  }); // end DOMContentLoaded
})();
