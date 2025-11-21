/**
 * form-signatures.js - Manejo de firmas de atención
 * Compatible con form-logic.js y backend firma4
 */
(function(){
  'use strict';

  const FIRMA_CONFIG = {
    paramedico: { selector: '#firmaParamedico', penColor: '#000000', label: 'Firma Tripulante' },
    medico: { selector: '#firmaMedico', penColor: '#000000', label: 'Firma Médico Tripulante' },
    paciente: { selector: '#firmaPaciente', penColor: '#000000', label: 'Firma Paciente' },
    desistimiento: { selector: '#firmaDesistimiento', penColor: '#d9534f', label: 'Firma Desistimiento' },
    medico_receptor: { selector: '#firmaMedicoReceptor', penColor: '#0e6b0e', label: 'Firma Médico Receptor' },
    representante_legal: { selector: '#firmaReceptorIPS', penColor: '#0b3d91', label: 'Firma Representante Legal' }
  };

  const signaturePads = new Map();

  // Map external names -> internal keys and hidden input IDs
  const NAME_MAP = {
    paramedico: { key: 'paramedico', hiddenId: 'FirmaParamedicoData' },
    medico: { key: 'medico', hiddenId: 'FirmaMedicoData' },
    paciente: { key: 'paciente', hiddenId: 'FirmaPacienteData' },
    desistimiento: { key: 'desistimiento', hiddenId: 'FirmaDesistimientoData' },
    medicoReceptor: { key: 'medico_receptor', hiddenId: 'FirmaMedicoReceptorData' },
    receptorIPS: { key: 'representante_legal', hiddenId: 'FirmaReceptorIPSData' }
  };

  function getHiddenInputByTipo(externalName){
    const map = NAME_MAP[externalName] || NAME_MAP[paramCase(externalName)];
    const id = map?.hiddenId;
    return id ? document.getElementById(id) : null;
  }

  function paramCase(name){ return String(name || '').replace(/[A-Z]/g, m => '_' + m.toLowerCase()); }

  function initSignaturePad(tipo){
    const config = FIRMA_CONFIG[tipo];
    if (!config) return null;

    const container = document.querySelector(config.selector);
    if (!container) return null;

    const canvas = container.querySelector('.signature-pad');
    if (!canvas) return null;

    function resizeCanvas(){
      const w = Math.max(280, container.clientWidth || 280);
      const h = Math.min(220, Math.round(w * 0.5));
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      canvas.width = w * ratio;
      canvas.height = h * ratio;
      canvas.style.width = w + 'px';
      canvas.style.height = h + 'px';
      const pad = canvas._pad;
      if (pad) pad.clear();
    }
    resizeCanvas();

    const pad = new SignaturePad(canvas, {
      penColor: config.penColor,
      backgroundColor: '#ffffff'
    });
    canvas._pad = pad;
    signaturePads.set(tipo, pad);

    // Clear buttons inside this container group
    container.parentElement?.querySelectorAll('.clear-signature, button[data-tipo]')
      .forEach(btn => btn.addEventListener('click', () => {
        try {
          pad.clear();
          // Try the hidden next to this group first
          const localHidden = container.parentElement?.querySelector('input[type="hidden"]');
          if (localHidden) localHidden.value = '';
        } catch(e){}
      }));

    // Resize when the section becomes visible or window resizes
    const ro = new ResizeObserver(() => resizeCanvas());
    ro.observe(container);
    window.addEventListener('resize', resizeCanvas);

    return pad;
  }

  function getSignatureData(tipo){
    try {
      const pad = signaturePads.get(tipo);
      if (!pad || pad.isEmpty()) return '';
      const src = pad._canvas || pad.canvas || null;
      if (!src) return '';
      const maxW = 600;
      const scale = Math.min(1, maxW / (src.width || maxW));
      const outW = Math.round((src.width || maxW) * scale);
      const outH = Math.round((src.height || Math.floor(maxW*0.5)) * scale);
      const tmp = document.createElement('canvas');
      tmp.width = outW; tmp.height = outH;
      const ctx = tmp.getContext('2d');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0,0,outW,outH);
      ctx.drawImage(src, 0,0,outW,outH);
      return tmp.toDataURL('image/jpeg', 0.75);
    } catch(e){ 
      console.error('Error obteniendo firma:', tipo, e);
      return ''; 
    }
  }

  function isAPHSelected() {
    const sel = document.getElementById('servicio');
    if (!sel) return false;
    const v = (sel.value || '').toLowerCase();
    return v.includes('aph') || v.includes('prehospitalaria');
  }

  function validateSignatures(){
    const mode = (document.getElementById('consent_type')?.value || '').toUpperCase();
    const aceptacion = mode === 'ACEPTACION';

    // Siempre exigir Tripulante
    if (!getSignatureData('paramedico')){
      alert('La firma del Tripulante es obligatoria.');
      return false;
    }

    // Aceptación o desistimiento
    if (aceptacion){
      if (!getSignatureData('paciente')){
        alert('La firma del Paciente es obligatoria para la aceptación.');
        return false;
      }
    } else {
      if (!getSignatureData('desistimiento')){
        alert('Debes registrar la firma de Desistimiento al rechazar la atención.');
        return false;
      }
    }

    // Médico receptor (solo si visible y no es APH)
    if (aceptacion && !isAPHSelected()) {
      const medRecContainer = document.getElementById('medico-receptor-firma-container');
      const visible = medRecContainer && window.getComputedStyle(medRecContainer).display !== 'none';
      if (visible && !getSignatureData('medico_receptor')) {
        alert('La firma del Médico Receptor es obligatoria cuando se acepta la atención.');
        return false;
      }
    }

    return true;
  }

  function prepareSignatureData(){
    const firmas = {};
    const mode = (document.getElementById('consent_type')?.value || '').toUpperCase();

    Object.keys(FIRMA_CONFIG).forEach(tipo => {
      const contenido = getSignatureData(tipo);
      if (contenido) {
        firmas[tipo] = {
          tipo_firma: tipo,
          contenido: contenido
        };
      }
    });

    if (mode === 'ACEPTACION') delete firmas.desistimiento;
    else if (mode === 'DESISTIMIENTO') delete firmas.paciente;

    return firmas;
  }

  document.addEventListener('DOMContentLoaded', () => {
    Object.keys(FIRMA_CONFIG).forEach(initSignaturePad);

    // Expose updater for form-behaviors.js
    window.updateHidden = function(externalName){
      try {
        const map = NAME_MAP[externalName];
        if (!map) return;
        const pad = signaturePads.get(map.key);
        const input = document.getElementById(map.hiddenId);
        if (!input) return;
        input.value = pad && !pad.isEmpty() ? getSignatureData(map.key) : '';
      } catch(e) { console.warn('updateHidden error', externalName, e); }
    };

    const form = document.getElementById('clinical-form');
    if (form) {
      form.addEventListener('submit', function(e) {
        if (!validateSignatures()) {
          e.preventDefault();
          return;
        }

        const firmas = prepareSignatureData();
        Object.values(firmas).forEach(firma => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = `firmas[${firma.tipo_firma}][contenido]`;
          input.value = firma.contenido;
          this.appendChild(input);

          const tipoInput = document.createElement('input');
          tipoInput.type = 'hidden';
          tipoInput.name = `firmas[${firma.tipo_firma}][tipo_firma]`;
          tipoInput.value = firma.tipo_firma;
          this.appendChild(tipoInput);
        });
      });
    }

    // --- Modo de firma ampliada en modal fullscreen ---
    try {
      const modalEl = document.getElementById('signatureModal');
      const modalCanvas = document.getElementById('modalCanvas');
      const clearBtn = document.getElementById('clearSignature');
      const saveBtn = document.getElementById('saveSignature');
      if (modalEl && modalCanvas && window.SignaturePad) {
        let modalPad = null;
        let currentExternalName = null; // ej: 'paramedico', 'medico', 'paciente'

        function resizeModalCanvas(){
          const container = modalCanvas.parentElement;
          if (!container) return;
          // Mantener una relación de aspecto similar a la del canvas principal (~2:1)
          const w = container.clientWidth || window.innerWidth || 800;
          const h = Math.round(w * 0.5);
          const ratio = Math.max(window.devicePixelRatio || 1, 1);
          modalCanvas.width = w * ratio;
          modalCanvas.height = h * ratio;
          modalCanvas.style.width = w + 'px';
          modalCanvas.style.height = h + 'px';
          if (modalPad) modalPad.clear();
        }

        modalPad = new SignaturePad(modalCanvas, {
          penColor: '#000000',
          backgroundColor: '#ffffff'
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          modalEl.addEventListener('show.bs.modal', (ev) => {
            const btn = ev.relatedTarget;
            const external = btn ? btn.getAttribute('data-sig-target') : null;
            currentExternalName = external || null;
            resizeModalCanvas();
            modalPad.clear();

            if (!currentExternalName) return;
            const map = NAME_MAP[currentExternalName];
            if (!map) return;
            // Usar el mismo color de pluma que el pad principal
            const cfg = FIRMA_CONFIG[map.key];
            if (cfg && cfg.penColor) {
              modalPad.penColor = cfg.penColor;
            }
            // Cargar la firma actual del pad principal (si existe) usando datos vectoriales
            const mainPad = signaturePads.get(map.key);
            if (mainPad && !mainPad.isEmpty()) {
              try { modalPad.fromData(mainPad.toData()); } catch(e) {}
            }
          });

          modalEl.addEventListener('shown.bs.modal', () => {
            resizeModalCanvas();
          });
        }

        window.addEventListener('resize', () => {
          if (modalEl.classList.contains('show')) resizeModalCanvas();
        });

        if (clearBtn) {
          clearBtn.addEventListener('click', () => {
            modalPad.clear();
          });
        }

        if (saveBtn) {
          saveBtn.addEventListener('click', () => {
            if (!currentExternalName) return;
            const map = NAME_MAP[currentExternalName];
            if (!map) return;
            const mainPad = signaturePads.get(map.key);
            if (!mainPad) return;
            try {
              if (modalPad.isEmpty()) {
                mainPad.clear();
              } else {
                // Transferir trazos vectoriales para mantener proporción y forma
                mainPad.fromData(modalPad.toData());
              }
              // Sincronizar hidden correspondiente
              if (typeof window.updateHidden === 'function') {
                window.updateHidden(currentExternalName);
              }
            } catch(e) {
              console.warn('Error al guardar firma desde modal', e);
            }
          });
        }
      }
    } catch(e) {
      console.warn('Error inicializando firma ampliada', e);
    }
  });
})();
