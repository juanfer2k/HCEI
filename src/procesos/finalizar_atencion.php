<?php
/**
 * finalizar_atencion.php (v4)
 * Marca una atención como finalizada si cumple condiciones:
 * - Existe registro
 * - Tiene al menos la firma del tripulante
 * - Tiene firma de paciente o desistimiento, según tipo de consentimiento
 */

require_once __DIR__ . '/../conn.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

$response = ['success' => false, 'message' => '', 'id' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $id = isset($_POST['id']) && is_numeric($_POST['id']) ? intval($_POST['id']) : null;
    if (!$id) {
        throw new Exception('ID de atención no especificado.');
    }

    // --- Validar existencia y estado ---
    $check = $conn->prepare("
        SELECT id, consent_type, firma_paramedico, firma_paciente, firma_desistimiento, firma_medico_receptor
        FROM atenciones
        WHERE id = ?
    ");
    $check->bind_param('i', $id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows === 0) {
        throw new Exception('Atención no encontrada.');
    }

    $row = $res->fetch_assoc();
    $check->close();

    $consentType = strtoupper(trim($row['consent_type'] ?? ''));
    $firmaParamedico = $row['firma_paramedico'] ?? '';
    $firmaPaciente = $row['firma_paciente'] ?? '';
    $firmaDesist = $row['firma_desistimiento'] ?? '';

    // --- Validar firmas obligatorias ---
    if (empty($firmaParamedico)) {
        throw new Exception('Falta la firma del Tripulante/Paramédico.');
    }

    if ($consentType === 'ACEPTACION' && empty($firmaPaciente)) {
        throw new Exception('Falta la firma del Paciente para aceptación.');
    }

    if ($consentType === 'DESISTIMIENTO' && empty($firmaDesist)) {
        throw new Exception('Falta la firma de Desistimiento.');
    }

    // --- Actualizar estado ---
    $sql = "
        UPDATE atenciones
        SET
            aceptacion = 1,
            estado = 'FINALIZADA',
            fecha_hora_atencion = NOW(),
            actualizado_en = NOW()
        WHERE id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        throw new Exception('Error al finalizar atención: ' . $stmt->error);
    }

    $stmt->close();

    // --- Registrar bitácora ---
    $logDir = __DIR__ . '/../logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $usuario = $_SESSION['usuario_nombre'] ?? 'Desconocido';
    $logFile = $logDir . 'finalizar_atencion.log';
    $logEntry = sprintf(
        "[%s] Atención #%d finalizada por %s (consentimiento: %s)\n",
        date('Y-m-d H:i:s'),
        $id,
        $usuario,
        $consentType
    );
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // --- Respuesta final ---
    $response = [
        'success' => true,
        'message' => 'Atención finalizada correctamente.',
        'id' => $id,
        'consent_type' => $consentType
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
