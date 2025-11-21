<?php
require_once __DIR__ . '/../conn.php'; // Ajusta ruta si necesario

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename=FURTRAN_' . date('Ymd_His') . '.txt');

$sql = "SELECT * FROM atenciones ORDER BY id DESC LIMIT 200"; // ajusta límite
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    // Normalizar campos según formato ADRES
    $localizacion = ($row['localizacion'] === 'Rural') ? 'R' : 'U';
    $manifestacion = (strtoupper(trim($row['manifestacion_servicios'])) === 'SI') ? '1' : '0';
    $condicionVictima = preg_replace('/[^0-9]/', '', $row['condicion_victima']);
    $estadoAseg = preg_replace('/[^0-9]/', '', $row['estado_aseguramiento']);
    $tipoEvento = preg_replace('/[^0-9]/', '', $row['tipo_evento']);

    // Orden estricto Tabla 3 ADRES (1–46)
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

    // Armar línea TXT (separador |)
    echo implode('|', array_map(fn($v) => trim((string)$v), $campos)) . "\n";
}

$conn->close();
