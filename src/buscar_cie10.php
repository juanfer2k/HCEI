<?php
// Endpoint de búsqueda de CIE-10 con manejo robusto de errores.
// Mantiene compatibilidad con Select2 y expone fallas del lado servidor para diagnosticar despliegues.
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/conn.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('No se pudo inicializar la conexiÃ³n mysqli.');
    }

    $conn->set_charset('utf8mb4');

    $searchTerm = trim($_GET['q'] ?? '');
    if (strlen($searchTerm) < 3) {
        echo json_encode([]);
        return;
    }

    $sql = <<<SQL
        SELECT
            d.clave AS id,
            CONCAT(d.descripcion, ' (Cat: ', c.descripcion, ')') AS text
        FROM `diagnosticoscie10` d
        INNER JOIN `categoriascie10` c ON d.idCategoria = c.id
        WHERE d.clave LIKE ? OR d.descripcion LIKE ? OR c.descripcion LIKE ?
        LIMIT 50
    SQL;

    $likeTerm = '%' . $searchTerm . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $likeTerm, $likeTerm, $likeTerm);
    $stmt->execute();

    $result = $stmt->get_result();
    $data   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $stmt->close();
    $conn->close();

    echo json_encode(['results' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $msg = '[CIE10] ' . $e->getMessage();
    if (!empty($e->getCode())) {
        $msg .= ' (code: ' . $e->getCode() . ')';
    }
    error_log($msg);

    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor al buscar CIE-10'], JSON_UNESCAPED_UNICODE);
}
?>