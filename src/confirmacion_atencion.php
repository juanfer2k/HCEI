<?php
$pageTitle = 'Confirmación de Registro';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/conn.php';

$atencion_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$atencion = null;

if ($atencion_id > 0) {
    $stmt = $conn->prepare("SELECT id, servicio, nombres_paciente, tipo_identificacion, id_paciente, fecha, ambulancia, diagnostico_principal FROM atenciones WHERE id = ?");
    $stmt->bind_param("i", $atencion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $atencion = $result->fetch_assoc();
    }
    $stmt->close();
}

$conn->close();

function getPillClass($servicio) {
    switch (strtoupper($servicio)) {
        case 'TRASLADO BASICO':
            return 'pill-success';
        case 'TRASLADO MEDICALIZADO':
            return 'pill-danger';
        case 'ATENCION PREHOSPITALARIA':
            return 'pill-warning';
        default:
            return 'pill-secondary';
    }
}

function getPillText($servicio) {
    switch (strtoupper($servicio)) {
        case 'TRASLADO BASICO':
            return 'TAB';
        case 'TRASLADO MEDICALIZADO':
            return 'TAM';
        case 'ATENCION PREHOSPITALARIA':
            return 'APH';
        default:
            return $servicio;
    }
}
?>

<style>
    .confirmation-card {
        max-width: 600px;
        margin: 2rem auto;
        border-left: 5px solid #198754;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .confirmation-card .card-header {
        background-color: #d1e7dd;
        color: #0f5132;
        font-weight: 600;
        font-size: 1.2rem;
    }
    .confirmation-card .card-body {
        font-size: 1.05rem;
    }
    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid #eee;
    }
    .info-item:last-child {
        border-bottom: none;
    }
    .info-label {
        font-weight: 600;
        color: #555;
    }
    .info-value {
        color: #212529;
        text-align: right;
    }
    .pill { display:inline-block; padding: .25em .6em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; color: #fff; }
    .pill-success { background-color: #198754; }
    .pill-danger { background-color: #dc3545; }
    .pill-warning { background-color: #ffc107; color: #000; }
    .pill-secondary { background-color: #6c757d; }
</style>

<div class="container py-5">
    <?php if ($atencion): ?>
    <div class="card confirmation-card">
        <div class="card-header text-center">
            <i class="bi bi-check-circle-fill me-2"></i> Registro Guardado Exitosamente
        </div>
        <div class="card-body">
            <div class="info-item">
                <span class="info-label">Registro N° (ID):</span>
                <span class="info-value"><?= htmlspecialchars($atencion['id']) ?> <span class="pill <?= getPillClass($atencion['servicio']) ?>"><?= getPillText($atencion['servicio']) ?></span></span>
            </div>
            <div class="info-item">
                <span class="info-label">Paciente:</span>
                <span class="info-value"><?= htmlspecialchars($atencion['nombres_paciente']) ?> (<?= htmlspecialchars($atencion['tipo_identificacion'] . ' ' . $atencion['id_paciente']) ?>)</span>
            </div>
            <div class="info-item">
                <span class="info-label">Fecha / Ambulancia:</span>
                <span class="info-value"><?= htmlspecialchars($atencion['fecha']) ?> / <?= htmlspecialchars($atencion['ambulancia']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Diagnóstico:</span>
                <span class="info-value"><?= htmlspecialchars($atencion['diagnostico_principal']) ?></span>
            </div>
        </div>
        <div class="card-footer text-center">
            <a href="index.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Registrar Nueva Atención</a>
            <a href="consulta_atenciones.php" class="btn btn-secondary"><i class="bi bi-list-ul me-1"></i> Consultar Registros</a>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-danger text-center">
            <h4><i class="bi bi-exclamation-triangle-fill"></i> Error</h4>
            <p>No se pudo encontrar el registro de atención. Por favor, verifica el ID o contacta al administrador.</p>
            <a href="consulta_atenciones.php" class="btn btn-primary">Volver a la Consulta</a>
        </div>
    <?php endif; ?>
</div>

<?php
// Inyectar script para limpiar localStorage si viene de procesar_atencion.php
if (isset($_SESSION['clear_storage'])) {
    echo $_SESSION['clear_storage'];
    unset($_SESSION['clear_storage']); // Limpiar para no mostrarlo de nuevo
}
require_once __DIR__ . '/footer.php';
?>