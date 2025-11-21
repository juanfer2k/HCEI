<?php
// Endpoint de búsqueda de Municipios para Select2.
// Devuelve un array JSON plano.
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en producción, solo en logs.
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . '/conn.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('No se pudo inicializar la conexión mysqli.');
    }

    $conn->set_charset('utf8mb4');

    $searchTerm = trim($_GET['q'] ?? '');
    if (strlen($searchTerm) < 2) {
        echo json_encode(['results' => []]);
        return;
    }

    $sql = "SELECT 
                m.codigo_municipio AS id, 
                CONCAT(m.nombre_municipio, ' (', d.nombre_departamento, ') - DANE: ', m.codigo_municipio) AS text,
                m.codigo_municipio,
                m.nombre_municipio,
                d.codigo_departamento,
                d.nombre_departamento
            FROM municipios m
            LEFT JOIN departamentos d ON m.codigo_departamento = d.codigo_departamento
            WHERE m.nombre_municipio LIKE ? 
               OR m.codigo_municipio LIKE ?
               OR d.nombre_departamento LIKE ?
            ORDER BY m.nombre_municipio ASC
            LIMIT 50";

    $likeTerm = '%' . $searchTerm . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $likeTerm, $likeTerm, $likeTerm);

    $stmt->execute();

    $result = $stmt->get_result();
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $stmt->close();
    $conn->close();

    echo json_encode(['results' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[MunicipioSearch] ' . $e->getMessage());
    http_response_code(500); // Devolver un error de servidor
    echo json_encode(['error' => 'Error en el servidor al buscar municipios']); // Devolver un mensaje de error.
}