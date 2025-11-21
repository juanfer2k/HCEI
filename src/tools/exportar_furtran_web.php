<?php
/**
 * exportar_detalle_furtran.php
 * Genera la ficha FURTRAN detallada de un solo paciente (id o no_radicado_furtran)
 * Compatible con Circular 08 / 2023 – ADRES
 * Uso:
 * exportar_detalle_furtran.php?id=123
 * exportar_detalle_furtran.php?id=123&format=json
 * exportar_detalle_furtran.php?id=123&format=txt
 */

require_once __DIR__ . '/../conn.php';
// Asegúrate de que tienes acceso a la variable $pageTitle y a las funciones h(), v(), etc., 
// si planeas usarlas en el HTML detallado. Por simplicidad, se omiten aquí, asumiendo 
// que el archivo original no las usa, pero sí necesita 'header.php' y 'footer.php'.

// --- Validación del parámetro ---
$id = $_GET['id'] ?? null;
if (!$id) {
    // Si no hay ID, aún se puede usar header/footer para mostrar el error, pero 'die' es suficiente.
    die("<b>Error:</b> Debe especificar el parámetro <code>?id=</code> del registro en atenciones.");
}

// --- Consulta del registro ---
$stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ? OR no_radicado_furtran = ? LIMIT 1");
$stmt->bind_param("ss", $id, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<b>No se encontró el registro solicitado.</b>");
}

$row = $result->fetch_assoc();
$conn->close();

// --- Normalizaciones ---
$row['localizacion'] = ($row['localizacion'] === 'Rural') ? 'R' : 'U';
// La manifestación_servicios se obtiene de la BDD, pero se ajusta a 1/0 para el TXT. 
// En el ejemplo adjunto, se asume que la columna manifestacion_servicios es un string ('SI'/'NO') o similar.
$row['manifestacion_servicios'] = (strtoupper(trim($row['manifestacion_servicios'])) === 'SI') ? 1 : 0; 
$condicionVictima = preg_replace('/[^0-9]/', '', $row['condicion_victima']);
$estadoAseg = preg_replace('/[^0-9]/', '', $row['estado_aseguramiento']);
$tipoEvento = preg_replace('/[^0-9]/', '', $row['tipo_evento']);

// --- Modo JSON ---
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['FURTRAN' => $row], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Modo TXT ---
if (isset($_GET['format']) && $_GET['format'] === 'txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename=FURTRAN_' . $row['id'] . '.txt');

    $localizacion = $row['localizacion'];
    $manifestacion = $row['manifestacion_servicios'];

    $campos = [
        $row['no_radicado_anterior'],
        $row['rg_respuesta_glosa'],
        $row['numero_factura'],
        $row['codigo_habilitacion_empresa'],
        $row['primer_nombre_conductor'],
        $row['segundo_nombre_conductor'],
        $row['primer_apellido_conductor'],
        $row['segundo_apellido_conductor'],
        $row['tipo_id_conductor'],
        $row['cc_conductor'],
        $row['tipo_vehiculo_ambulancia'],
        $row['placa_vehiculo'],
        $row['direccion_empresa_transportador'],
        $row['telefono_empresa_transportador'],
        $row['codigo_departamento_empresa'],
        $row['codigo_municipio_empresa'],
        $row['tipo_identificacion'],
        $row['id_paciente'],
        $row['nombres_paciente'],
        $row['segundo_nombre_paciente'],
        $row['segundo_apellido_paciente'], // tu estructura no tiene primer_apellido_paciente
        null, // espacio reservado para mantener posición 22
        $row['fecha_nacimiento'],
        $row['desc_sexo'],
        $tipoEvento,
        $row['direccion_servicio'],
        $row['cod_depto_recogida'],
        $row['cod_ciudad_recogida'],
        $localizacion,
        $row['fecha'],
        $row['hora_traslado'],
        $row['cod_habilitacion_ips'],
        $row['cod_depto_ips'],
        $row['cod_ciudad_ips'],
        $condicionVictima,
        $estadoAseg,
        $row['tipo_vehiculo_accidente'],
        $row['placa_vehiculo_involucrado'],
        $row['codigo_aseguradora'],
        $row['numero_poliza'],
        $row['fecha_inicio_poliza'],
        $row['fecha_fin_poliza'],
        $row['radicado_sras'],
        number_format($row['valor_facturado'], 2, '.', ''),
        number_format($row['valor_reclamado'], 2, '.', ''),
        $manifestacion
    ];

    echo implode('|', array_map(fn($v) => trim((string)$v), $campos)) . "\n";
    exit;
}

// --- Salida HTML detallada (Se incluye header y footer aquí) ---

// 1. Definir el título de la página (necesario para la inclusión de header.php)
$pageTitle = "Detalle FURTRAN - Atención #" . htmlspecialchars($row['id']);

// 2. Incluir el header
require_once __DIR__ . '/../header.php'; // Se asume que /../header.php es la ruta correcta

// 3. Funciones de ayuda (se deben definir si el código HTML original las usa)
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function showField($label, $value) {
    $v = ($value !== null && $value !== '') ? h($value) : '<span style="color:#999;">(sin dato)</span>';
    echo "<tr><td><b>$label</b></td><td>$v</td></tr>";
}

echo "<div class='container mt-4'>"; // Se agrega un contenedor para mejor estilo
echo "<h2>Ficha FURTRAN – Atención #{$row['id']}</h2>";
echo "<p><i>Formato detallado según Anexo Técnico FURTRAN (Circular 08/2023 – ADRES)</i></p>";

echo '<p>
<a href="?id=' . urlencode($id) . '&format=txt" 
style="background:#007bff;color:white;padding:8px 14px;border-radius:5px;text-decoration:none;font-weight:bold;">
📄 Descargar TXT FURTRAN
</a> 
<a href="?id=' . urlencode($id) . '&format=json" 
style="background:#28a745;color:white;padding:8px 14px;border-radius:5px;text-decoration:none;font-weight:bold;margin-left:10px;">
🔍 Ver JSON
</a>
</p>';

echo "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse:collapse;font-family:Arial,monospace;font-size:14px;'>";

echo "<tr style='background:#eee;'><th colspan='2'>I. TRANSPORTADOR</th></tr>";
showField("Número de Factura", $row['numero_factura']);
showField("Código Habilitación Empresa", $row['codigo_habilitacion_empresa']);
showField("Conductor", trim("{$row['primer_nombre_conductor']} {$row['segundo_nombre_conductor']} {$row['primer_apellido_conductor']} {$row['segundo_apellido_conductor']}"));
showField("Tipo Doc. Conductor", $row['tipo_id_conductor']);
showField("Documento Conductor", $row['cc_conductor']);
showField("Tipo Vehículo Ambulancia", $row['tipo_vehiculo_ambulancia']);
showField("Placa Vehículo", $row['placa_vehiculo']);
showField("Dirección Transportador", $row['direccion_empresa_transportador']);
showField("Teléfono Transportador", $row['telefono_empresa_transportador']);
showField("Departamento", $row['codigo_departamento_empresa']);
showField("Municipio", $row['codigo_municipio_empresa']);

echo "<tr style='background:#eee;'><th colspan='2'>II. VÍCTIMA</th></tr>";
showField("Tipo Identificación", $row['tipo_identificacion']);
showField("Número Identificación", $row['id_paciente']);
showField("Nombres", $row['nombres_paciente']);
showField("Apellidos", $row['segundo_apellido_paciente']);
showField("Fecha Nacimiento", $row['fecha_nacimiento']);
showField("Sexo", $row['desc_sexo']);

echo "<tr style='background:#eee;'><th colspan='2'>III. EVENTO</th></tr>";
showField("Tipo Evento", $row['tipo_evento']);
showField("Descripción Evento", $row['desc_tipo_evento']);

echo "<tr style='background:#eee;'><th colspan='2'>IV. RECOGIDA DE LA VÍCTIMA</th></tr>";
showField("Dirección Servicio", $row['direccion_servicio']);
showField("Código DANE Depto", $row['cod_depto_recogida']);
showField("Código DANE Ciudad", $row['cod_ciudad_recogida']);
showField("Zona (U/R)", $row['localizacion']);

echo "<tr style='background:#eee;'><th colspan='2'>V. CERTIFICACIÓN DEL TRASLADO</th></tr>";
showField("Fecha", $row['fecha']);
showField("Hora Traslado", $row['hora_traslado']);
showField("Código Habilitación IPS", $row['cod_habilitacion_ips']);
showField("Código Dpto IPS", $row['cod_depto_ips']);
showField("Código Ciudad IPS", $row['cod_ciudad_ips']);

echo "<tr style='background:#eee;'><th colspan='2'>VI. ACCIDENTE DE TRÁNSITO</th></tr>";
showField("Condición Víctima", $row['condicion_victima']);
showField("Estado Aseguramiento", $row['estado_aseguramiento']);
showField("Tipo Vehículo Involucrado", $row['tipo_vehiculo_accidente']);
showField("Placa Involucrado", $row['placa_vehiculo_involucrado']);
showField("Código Aseguradora", $row['codigo_aseguradora']);
showField("Número Póliza", $row['numero_poliza']);
showField("Vigencia Desde", $row['fecha_inicio_poliza']);
showField("Vigencia Hasta", $row['fecha_fin_poliza']);
showField("Radicado SRAS", $row['radicado_sras']);

echo "<tr style='background:#eee;'><th colspan='2'>VII. AMPARO RECLAMADO</th></tr>";
// Código original (con error):
// showField("Valor Facturado", number_format($row['valor_facturado'], 2)); 
// showField("Valor Reclamado", number_format($row['valor_reclamado'], 2)); 

// Código CORREGIDO (Opción 1: Usando ?? 0)
showField("Valor Facturado", number_format($row['valor_facturado'] ?? 0, 2));
showField("Valor Reclamado", number_format($row['valor_reclamado'] ?? 0, 2));
echo "<tr style='background:#eee;'><th colspan='2'>VIII. MANIFESTACIÓN DEL SERVICIO</th></tr>";
showField("Manifestación (1=Sí,0=No)", $row['manifestacion_servicios']);

echo "</table>";
echo "</div>"; // Cierre del contenedor

// 4. Incluir el footer
require_once __DIR__ . '/../footer.php'; // Se asume que /../footer.php es la ruta correcta

?>