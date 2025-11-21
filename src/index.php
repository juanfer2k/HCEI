<?php
require_once 'access_control.php'; // Proteger esta página
require_once 'bootstrap.php';      // Configuración global
require_once 'conn.php';           // Conexión a la base de datos ($conn)

// --- Si llegamos aquí, el usuario ESTÁ AUTENTICADO ---

// --- CONTROL DE ACCESO POR ROL ---
if ($_SESSION['usuario_rol'] === 'Administrativo') {
    // Si el rol es Administrativo, no puede crear registros. Redirigir a la consulta.
    $_SESSION['message'] = '<div class="alert alert-info">Tu rol solo permite consultar registros.</div>';
    header('Location: ' . BASE_URL . 'consulta_atenciones.php');
    exit;
}

// --- PRE-LLENADO DE DATOS PARA TRIPULACIÓN ---
$tripulante_data = [];
if ($_SESSION['usuario_rol'] === 'Tripulacion') {
    // Bloquear nueva atención si ya existe una activa sin finalizar para este tripulante
    $ccUsuario = $_SESSION['usuario_cc'] ?? ($_SESSION['usuario_identificacion'] ?? null);
    if ($ccUsuario) {
        if ($stmtAct = $conn->prepare("SELECT id FROM atenciones WHERE cc_tripulante = ? AND (hora_final IS NULL OR hora_final = '00:00:00') AND (estado_registro IS NULL OR estado_registro <> 'ANULADA') LIMIT 1")) {
            $stmtAct->bind_param('s', $ccUsuario);
            $stmtAct->execute();
            $stmtAct->store_result();
            if ($stmtAct->num_rows > 0) {
                $_SESSION['message'] = '<div class="alert alert-warning">Ya tienes una atención activa sin finalizar. Debes finalizarla antes de crear un nuevo registro.</div>';
                $stmtAct->close();
                header('Location: ' . BASE_URL . 'consulta_atenciones.php');
                exit;
            }
            $stmtAct->close();
        }
    }

    $stmt_tripulante = $conn->prepare("SELECT nombres, apellidos, id_cc, id_registro FROM tripulacion WHERE id = ?");
    $stmt_tripulante->bind_param("i", $_SESSION['usuario_id']);
    $stmt_tripulante->execute();
    $result_tripulante = $stmt_tripulante->get_result();
    if ($result_tripulante->num_rows === 1) {
        $tripulante_data = $result_tripulante->fetch_assoc();
    }
    $stmt_tripulante->close();
}

if (empty($tripulante_data) && !empty($_SESSION['usuario_nombre'])) {
    $tripulante_data = [
        'nombres' => $_SESSION['usuario_nombre'],
        'apellidos' => $_SESSION['usuario_apellidos'] ?? '',
        'id_cc' => $_SESSION['usuario_cc'] ?? '',
        'id_registro' => $_SESSION['usuario_registro'] ?? ''
    ];
}

$siguiente_registro_id = 'Automático';
$lista_eps = require 'eps_list.php';
sort($lista_eps);

// OPTIMIZACIÓN: No cargar 45,000+ registros de IPS en el HTML
// En su lugar, usar Select2 con AJAX (buscar_ips.php)
// Esto reduce el tiempo de carga de 6-10 segundos a <1 segundo
$ips_options = [];

/* CÓDIGO ANTERIOR QUE CAUSABA LAG:
if (isset($conn) && $conn instanceof mysqli) {
    $sql_ips = "SELECT ips_nit AS nit, ips_nombre AS nombre, ips_ciudad AS ciudad FROM ips_receptora ORDER BY ips_nombre ASC";
    if ($st_ips = $conn->prepare($sql_ips)) {
        $st_ips->execute();
        $res_ips = $st_ips->get_result();
        if ($res_ips instanceof mysqli_result) {
            while ($row = $res_ips->fetch_assoc()) {
                $nit = (string)($row['nit'] ?? '');
                $nom = (string)($row['nombre'] ?? '');
                $ciu = (string)($row['ciudad'] ?? '');
                $ips_options[] = [
                    'id' => $nit,
                    'text' => $nom . ' (NIT: ' . $nit . ')' . ($ciu !== '' ? ' - ' . $ciu : ''),
                    'nit' => $nit,
                    'nombre' => $nom,
                    'ciudad' => $ciu,
                ];
            }
        }
        $st_ips->close();
    }
}
*/

include 'header.php';
?>
<script>
  window.AppData = Object.assign(window.AppData || {}, {
    ipsOptions: <?= json_encode($ips_options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
  });
  window.IPS_DATA = window.AppData.ipsOptions;
  
  // Datos del usuario para auto-llenado
  window.userData = {
    nombre: <?= json_encode($tripulante_data['nombres'] ?? '') ?>,
    apellidos: <?= json_encode($tripulante_data['apellidos'] ?? '') ?>,
    cc: <?= json_encode($tripulante_data['id_cc'] ?? '') ?>,
    registro: <?= json_encode($tripulante_data['id_registro'] ?? '') ?>
  };
</script>

<!-- Wizard Progress (debajo del nav superior) -->
<div class="container-fluid px-3 mt-2">
  <div id="wizardSavedStep" class="mb-1 text-primary fw-semibold" style="font-size: .85rem; display:none;"></div>
  <div class="d-flex justify-content-between align-items-center mb-1">
    <div id="wizardProgressTitle" style="font-weight:600; font-size: .95rem;"></div>
    <div class="d-flex flex-column align-items-end">
      <div id="wizardServiceTimer" class="small text-muted" style="min-width: 140px; text-align: right;"></div>
      <div class="btn-group btn-group-sm mt-1" role="group" aria-label="Marcar tiempos de servicio">
        <button type="button" class="btn btn-outline-secondary btn-set-now" data-target="hora_despacho" title="Marcar hora de despacho">Desp</button>
        <button type="button" class="btn btn-outline-secondary btn-set-now" data-target="hora_recepcion_paciente" title="Marcar hora de recepción médico">Lleg</button>
        <button type="button" class="btn btn-outline-secondary btn-set-now" data-target="hora_final" title="Marcar hora final del servicio">Fin</button>
      </div>
    </div>
  </div>
  <div id="wizardProgress" class="progress" style="height: 18px;">
    <div class="progress-bar progress-bar-striped" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
      <span class="visually-hidden">0%</span>
    </div>
  </div>
  <!-- Enlaces rápidos de pasos -->
  <div class="d-flex align-items-center justify-content-between mt-2">
    <div id="wizardStepLinks" class="small"></div>
    <div class="form-check form-switch ms-2">
      <input class="form-check-input" type="checkbox" role="switch" id="wizardVerifyToggle">
      <label class="form-check-label" for="wizardVerifyToggle">Verificación por pasos</label>
    </div>
  </div>
  </div>

<div class="container-fluid px-3" id="form-wizard">
  <form id="clinical-form" method="post" action="procesar_atencion_v4.php" enctype="multipart/form-data">

    <?php include 'views/form_info_interna.php'; ?>
    <?php include 'views/form_ubicacion.php'; ?>
    <?php include 'views/form_aseguradora.php'; ?>
    <?php include 'views/form_paciente.php'; ?>
    <?php include 'views/form_evento.php'; ?>
    <?php include 'views/form_condicion.php'; ?>
    <?php include 'views/form_clinico.php'; ?>
    <?php include 'views/form_glasgow.php'; ?>
    <?php include 'views/form_antecedentes.php'; ?>
    <?php include 'views/form_soporte_terapeutico.php'; ?>
    <?php include 'views/form_adjuntos.php'; ?>
    <?php include 'views/form_final.php'; ?>

    <div class="wizard-controls text-center mt-4">
      <button type="button" class="btn btn-secondary btn-prev">Anterior</button>
      <button type="button" class="btn btn-primary btn-next">Siguiente</button>
      <button type="button" id="btnFinalizar" class="btn btn-success d-none">Registrar la atención</button>
    </div>

  </form>
</div>

<script>
// Configurar Select2 con AJAX para IPS Receptora (evita cargar 45k registros)
jQuery(document).ready(function($) {
    const $ipsSelect = $('#nombre_ips_receptora');
    if ($ipsSelect.length) {
        $ipsSelect.select2({
            ajax: {
                url: 'buscar_ips.php',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || []
                    };
                },
                cache: true
            },
            placeholder: 'Buscar IPS por nombre, NIT o ciudad...',
            minimumInputLength: 2,
            language: {
                inputTooShort: function() {
                    return 'Escriba al menos 2 caracteres para buscar';
                },
                searching: function() {
                    return 'Buscando...';
                },
                noResults: function() {
                    return 'No se encontraron resultados';
                }
            },
            templateResult: function(item) {
                if (item.loading) return item.text;
                return $('<span>').text(item.text);
            },
            templateSelection: function(item) {
                // Guardar datos en campos ocultos cuando se selecciona
                if (item.nit) {
                    $('#ips_nit').val(item.nit);
                    $('#nit_ips_receptora').val(item.nit);
                }
                if (item.ciudad) {
                    $('#ips_ciudad').val(item.ciudad);
                    $('#municipio_ips_receptora').val(item.ciudad);
                }
                return item.text;
            }
        });
    }
});
</script>

<!-- Modal para Firma en Pantalla Completa -->
<div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="signatureModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="signatureModalLabel">Firma Ampliada</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-flex flex-column">
        <div id="modalSignaturePad" class="flex-grow-1 signature-pad-container" style="border: 1px solid #ccc; border-radius: 8px;">
          <canvas id="modalCanvas" class="signature-pad" style="width: 100%; height: 100%;" data-pen-color="#34446d"></canvas>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="clearSignature">Limpiar</button>
        <button type="button" class="btn btn-primary" id="saveSignature" data-bs-dismiss="modal">Guardar</button>
      </div>
    </div>
  </div>
</div>

  <script src="<?= BASE_URL ?>js/form-init.js?v=<?= time() ?>" defer></script>
  <script src="<?= BASE_URL ?>js/form-logic.js?v=<?= time() ?>" defer></script>
  <script src="<?= BASE_URL ?>js/form-behaviors.js?v=<?= time() ?>" defer></script>
<?php include 'footer.php'; 
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>