<?php
/**
 * Script de diagnóstico FURIPS
 * Verifica el estado de las tablas y datos
 */

require_once '../conn.php';
require_once '../access_control.php';

// Verificar si existe la tabla atenciones_extra
$check_table = "SHOW TABLES LIKE 'atenciones_extra'";
$result = $conn->query($check_table);
$tabla_existe = $result->num_rows > 0;

echo "<h3>Diagnóstico FURIPS</h3>";
echo "<hr>";

echo "<h4>1. Verificación de Tabla atenciones_extra</h4>";
if ($tabla_existe) {
    echo "<p class='text-success'>✓ La tabla atenciones_extra existe</p>";
    
    // Verificar si tiene los campos FURIPS
    $check_columns = "SHOW COLUMNS FROM atenciones_extra LIKE 'furips_reportado'";
    $result = $conn->query($check_columns);
    if ($result->num_rows > 0) {
        echo "<p class='text-success'>✓ Los campos FURIPS existen en atenciones_extra</p>";
    } else {
        echo "<p class='text-danger'>✗ Faltan los campos FURIPS en atenciones_extra</p>";
        echo "<p><strong>Acción:</strong> Ejecutar furips_schema.sql</p>";
    }
} else {
    echo "<p class='text-danger'>✗ La tabla atenciones_extra NO existe</p>";
    echo "<p><strong>Acción:</strong> Ejecutar schema_update.sql primero</p>";
}

echo "<hr>";
echo "<h4>2. Conteo de Atenciones por Tipo de Evento</h4>";

// Contar atenciones por tipo de evento
$query_tipos = "
    SELECT 
        tipo_evento,
        COUNT(*) as total
    FROM atenciones
    GROUP BY tipo_evento
    ORDER BY total DESC
    LIMIT 20
";
$result = $conn->query($query_tipos);

echo "<table class='table table-sm'>";
echo "<thead><tr><th>Tipo de Evento</th><th>Total</th></tr></thead>";
echo "<tbody>";
while ($row = $result->fetch_assoc()) {
    $clase = (stripos($row['tipo_evento'], 'tránsito') !== false || 
              stripos($row['tipo_evento'], 'catastrófico') !== false) ? 'table-success' : '';
    echo "<tr class='{$clase}'>";
    echo "<td>" . htmlspecialchars($row['tipo_evento']) . "</td>";
    echo "<td>" . $row['total'] . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";

echo "<hr>";
echo "<h4>3. Atenciones Candidatas para FURIPS</h4>";

// Buscar atenciones candidatas
$query_candidatas = "
    SELECT 
        a.id,
        a.fecha,
        a.tipo_evento,
        a.nombres_paciente,
        a.condicion_victima
    FROM atenciones a
    WHERE (
        a.tipo_evento LIKE '%tránsito%'
        OR a.tipo_evento LIKE '%transito%'
        OR a.tipo_evento LIKE '%catastrófico%'
        OR a.tipo_evento LIKE '%terrorista%'
    )
    ORDER BY a.fecha DESC
    LIMIT 10
";
$result = $conn->query($query_candidatas);

if ($result->num_rows > 0) {
    echo "<p class='text-success'>✓ Encontradas " . $result->num_rows . " atenciones candidatas (mostrando últimas 10)</p>";
    echo "<table class='table table-sm'>";
    echo "<thead><tr><th>ID</th><th>Fecha</th><th>Tipo Evento</th><th>Paciente</th><th>Condición</th></tr></thead>";
    echo "<tbody>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "<td>" . htmlspecialchars($row['tipo_evento']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nombres_paciente']) . "</td>";
        echo "<td>" . htmlspecialchars($row['condicion_victima'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p class='text-warning'>⚠ No se encontraron atenciones de accidentes de tránsito o eventos catastróficos</p>";
}

echo "<hr>";
echo "<h4>4. Estado de Reportes FURIPS</h4>";

if ($tabla_existe) {
    $query_reportados = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN ae.furips_reportado = 1 THEN 1 ELSE 0 END) as reportados,
            SUM(CASE WHEN ae.furips_reportado IS NULL OR ae.furips_reportado = 0 THEN 1 ELSE 0 END) as pendientes
        FROM atenciones a
        LEFT JOIN atenciones_extra ae ON a.id = ae.atencion_id
        WHERE (
            a.tipo_evento LIKE '%tránsito%'
            OR a.tipo_evento LIKE '%transito%'
            OR a.tipo_evento LIKE '%catastrófico%'
            OR a.tipo_evento LIKE '%terrorista%'
        )
    ";
    $result = $conn->query($query_reportados);
    $row = $result->fetch_assoc();
    
    echo "<ul>";
    echo "<li>Total de atenciones FURIPS: <strong>" . ($row['total'] ?? 0) . "</strong></li>";
    echo "<li>Reportadas: <strong>" . ($row['reportados'] ?? 0) . "</strong></li>";
    echo "<li>Pendientes: <strong>" . ($row['pendientes'] ?? 0) . "</strong></li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h4>5. Recomendaciones</h4>";
echo "<ol>";
if (!$tabla_existe) {
    echo "<li><strong>Ejecutar schema_update.sql</strong> para crear la tabla atenciones_extra</li>";
}
echo "<li><strong>Ejecutar furips_schema.sql</strong> para agregar campos de control FURIPS</li>";
echo "<li>Verificar que el campo <code>tipo_evento</code> contenga exactamente 'Accidente de tránsito'</li>";
echo "<li>Asegurarse de que haya atenciones en el rango de fechas seleccionado</li>";
echo "</ol>";

$conn->close();
?>
