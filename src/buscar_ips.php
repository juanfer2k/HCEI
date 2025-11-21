<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require __DIR__ . '/conn.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('No se pudo inicializar la conexión mysqli.');
    }

    $conn->set_charset('utf8mb4');

    $searchTerm = trim($_GET['q'] ?? '');
    if (strlen($searchTerm) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    $sql = "SELECT ips_nit AS id, ips_nombre, ips_ciudad 
            FROM ips_receptora 
            WHERE ips_nombre LIKE ? OR ips_nit LIKE ? OR ips_ciudad LIKE ?
            ORDER BY ips_nombre ASC
            LIMIT 50";

    $likeTerm = '%' . $searchTerm . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $likeTerm, $likeTerm, $likeTerm);
    $stmt->execute();

    $result = $stmt->get_result();
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $nombre = trim((string)($row['ips_nombre'] ?? ''));
            $nit = trim((string)($row['id'] ?? ''));
            $ciudad = trim((string)($row['ips_ciudad'] ?? ''));
            $txtParts = [];
            if ($nombre !== '') $txtParts[] = $nombre;
            if ($nit !== '') $txtParts[] = 'NIT ' . $nit;
            if ($ciudad !== '') $txtParts[] = $ciudad;
            $data[] = [
                'id'     => $nit,
                'text'   => implode(' — ', $txtParts),
                'nit'    => $nit,
                'ciudad' => $ciudad,
            ];
        }
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['results' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[IPSSearch] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor al buscar IPS']);
}
?>