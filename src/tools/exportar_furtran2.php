<?php
/**
 * exportar_furtran.php
 * Exporta datos FURTRAN desde v_furtran_export en formato TXT o JSON
 * Compatible con Circular 08 / 2023 - ADRES
 * Uso: exportar_furtran.php?format=txt   o   exportar_furtran.php?format=json
 */

require_once __DIR__ . '/../conn.php';
$format = $_GET['format'] ?? 'txt';

$sql = "SELECT * FROM v_furtran_export";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    die("No hay registros disponibles en la vista FURTRAN.");
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    $data = [];
    while ($r = $result->fetch_assoc()) {
        // Normalizaciones según ADRES
        $r['localizacion'] = ($r['localizacion'] === 'Rural') ? 'R' : 'U';
        $r['manifestacion_servicios'] = (strtoupper(trim($r['manifestacion_servicios'])) === 'SI') ? 1 : 0;
        $data[] = $r;
    }
    echo json_encode(['FURTRAN' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($format === 'txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename=FURTRAN_' . date('Ymd_His') . '.txt');

    while ($row = $result->fetch_assoc()) {
        // Normalizar campos y tipos
        $localizacion = ($row['localizacion'] === 'Rural') ? 'R' : 'U';
        $manifestacion = (strtoupper(trim($row['manifestacion_servicios'])) === 'SI') ? '1' : '0';
        $condicionVictima = preg_replace('/[^0-9]/', '', $row['condicion_victima']);
        $estadoAseg = preg_replace('/[^0-9]/', '', $row['estado_aseguramiento']);
        $tipoEvento = preg_replace('/[^0-9]/', '', $row['tipo_evento']);

        // Orden 1–46 según Anexo Técnico ADRES
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
            $row['primer_apellido_paciente'],
            $row['segundo_apellido_paciente'],
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
    }
    exit;
}

echo "Formato no válido. Usa ?format=txt o ?format=json";
