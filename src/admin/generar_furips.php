<?php
/**
 * Generador de Archivos FURIPS 1
 * Circular ADRES 0008/2023
 * 
 * Genera archivos de texto plano con 93 campos separados por comas
 * para reportar accidentes de tránsito y eventos catastróficos a ADRES
 */

require_once '../conn.php';
require_once '../access_control.php';

// Solo administradores (Master, Administrador, Administrativo)
$rol = $_SESSION['rol'] ?? $_SESSION['usuario_rol'] ?? $_SESSION['role'] ?? '';
$rolesPermitidos = ['Master', 'Administrador', 'Administrativo', 'master', 'administrador', 'administrativo'];

if (!in_array($rol, $rolesPermitidos)) {
    die(json_encode(['error' => 'Acceso denegado']));
}

/**
 * Genera archivo FURIPS 1 para un rango de fechas
 */
function generarFURIPS1($conn, $fecha_inicio, $fecha_fin, $codigo_habilitacion) {
    // Consultar atenciones de accidentes de tránsito y eventos catastróficos
    $query = "
        SELECT 
            a.*,
            ae.*,
            DATE_FORMAT(a.fecha, '%d/%m/%Y') as fecha_formatted,
            DATE_FORMAT(a.hora_despacho, '%H:%i') as hora_despacho_formatted,
            DATE_FORMAT(a.hora_llegada, '%H:%i') as hora_llegada_formatted,
            DATE_FORMAT(a.hora_ingreso, '%H:%i') as hora_ingreso_formatted,
            DATE_FORMAT(a.hora_final, '%H:%i') as hora_final_formatted
        FROM atenciones a
        LEFT JOIN atenciones_extra ae ON a.id = ae.atencion_id
        WHERE a.fecha BETWEEN ? AND ?
        AND (
            a.tipo_evento = 'Accidente de tránsito'
            OR a.tipo_evento LIKE '%catastrófico%'
            OR a.tipo_evento LIKE '%terrorista%'
        )
        AND (ae.furips_reportado IS NULL OR ae.furips_reportado = 0)
        ORDER BY a.fecha ASC, a.id ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lineas = [];
    $ids_reportados = [];
    
    while ($row = $result->fetch_assoc()) {
        $linea = generarLineaFURIPS($row);
        $lineas[] = $linea;
        $ids_reportados[] = $row['id'];
    }
    
    if (empty($lineas)) {
        return ['error' => 'No hay atenciones para reportar en el rango de fechas seleccionado'];
    }
    
    // Generar nombre de archivo
    $fecha_generacion = date('dmY');
    $nombre_archivo = "FURIPS1{$codigo_habilitacion}{$fecha_generacion}.txt";
    
    // Crear contenido del archivo
    $contenido = implode("\n", $lineas);
    
    // Guardar archivo
    $ruta_archivo = "../tmp/{$nombre_archivo}";
    file_put_contents($ruta_archivo, $contenido);
    
    // Marcar atenciones como reportadas
    if (!empty($ids_reportados)) {
        $ids_str = implode(',', $ids_reportados);
        $update_query = "UPDATE atenciones_extra SET furips_reportado = 1, furips_fecha_reporte = NOW() WHERE atencion_id IN ($ids_str)";
        $conn->query($update_query);
    }
    
    return [
        'success' => true,
        'archivo' => $nombre_archivo,
        'ruta' => $ruta_archivo,
        'total_registros' => count($lineas)
    ];
}

/**
 * Genera una línea FURIPS con 93 campos
 */
function generarLineaFURIPS($atencion) {
    $campos = [];
    
    // I. Datos de la reclamación (4 campos)
    $campos[] = ''; // 1. Número de radicado anterior
    $campos[] = ''; // 2. RG (Respuesta a glosa)
    $campos[] = $atencion['registro'] ?? ''; // 3. Número de factura
    $campos[] = $atencion['id'] ?? ''; // 4. Número consecutivo
    
    // II. Datos del prestador (1 campo)
    $campos[] = obtenerCodigoHabilitacion(); // 5. Código de habilitación
    
    // III. Datos de la víctima (14 campos)
    $nombres = explode(' ', $atencion['nombres_paciente'] ?? '');
    $campos[] = $nombres[2] ?? ''; // 6. Primer apellido
    $campos[] = $nombres[3] ?? ''; // 7. Segundo apellido
    $campos[] = $nombres[0] ?? ''; // 8. Primer nombre
    $campos[] = $nombres[1] ?? ''; // 9. Segundo nombre
    $campos[] = $atencion['tipo_identificacion'] ?? 'CC'; // 10. Tipo de documento
    $campos[] = $atencion['id_paciente'] ?? ''; // 11. Número de documento
    $campos[] = formatearFecha($atencion['fecha_nacimiento']); // 12. Fecha de nacimiento
    $campos[] = ''; // 13. Fecha de fallecimiento
    $campos[] = $atencion['genero_nacer'] ?? 'M'; // 14. Sexo
    $campos[] = $atencion['direccion_domicilio'] ?? ''; // 15. Dirección
    $campos[] = substr($atencion['cod_departamento_residencia'] ?? '', 0, 2); // 16. Código departamento
    $campos[] = substr($atencion['cod_municipio_residencia'] ?? '', -3); // 17. Código municipio
    $campos[] = $atencion['telefono_paciente'] ?? ''; // 18. Teléfono
    $campos[] = mapearCondicionVictima($atencion['condicion_victima'] ?? ''); // 19. Condición víctima
    
    // IV. Datos del sitio del evento (8 campos)
    $campos[] = mapearNaturalezaEvento($atencion['tipo_evento'] ?? ''); // 20. Naturaleza del evento
    $campos[] = $atencion['desc_tipo_evento'] ?? ''; // 21. Descripción otro evento
    $campos[] = $atencion['direccion_del_evento'] ?? $atencion['direccion_servicio'] ?? ''; // 22. Dirección
    $campos[] = $atencion['fecha_formatted'] ?? ''; // 23. Fecha de ocurrencia
    $campos[] = $atencion['hora_despacho_formatted'] ?? ''; // 24. Hora de ocurrencia
    $campos[] = substr($atencion['codigo_departamento_servicio'] ?? '', 0, 2); // 25. Código departamento
    $campos[] = substr($atencion['codigo_municipio_servicio'] ?? '', -3); // 26. Código municipio
    $campos[] = $atencion['localizacion'] === 'Urbano' ? 'U' : 'R'; // 27. Zona
    
    // V. Datos del vehículo (10 campos)
    $campos[] = mapearEstadoAseguramiento($atencion['estado_aseguramiento'] ?? ''); // 28. Estado aseguramiento
    $campos[] = ''; // 29. Marca
    $campos[] = $atencion['placa_vehiculo_involucrado'] ?? $atencion['placa_paciente'] ?? ''; // 30. Placa
    $campos[] = mapearTipoVehiculo($atencion['tipo_vehiculo_accidente'] ?? ''); // 31. Tipo de vehículo
    $campos[] = $atencion['codigo_aseguradora'] ?? ''; // 32. Código aseguradora
    $campos[] = $atencion['numero_poliza'] ?? ''; // 33. Número de póliza
    $campos[] = formatearFecha($atencion['fecha_inicio_poliza']); // 34. Fecha inicio póliza
    $campos[] = formatearFecha($atencion['fecha_fin_poliza']); // 35. Fecha fin póliza
    $campos[] = $atencion['radicado_sras'] ?? ''; // 36. Número radicado SIRAS
    $campos[] = '0'; // 37. Cobro por agotamiento tope
    
    // VI. Datos de la atención (6 campos)
    $campos[] = $atencion['codigo_cups_traslado'] ?? ''; // 38. Código CUPS servicio principal
    $campos[] = ''; // 39. Complejidad
    $campos[] = ''; // 40. Código CUPS procedimiento principal
    $campos[] = ''; // 41. Código CUPS procedimiento secundario
    $campos[] = '0'; // 42. Se prestó servicio UCI
    $campos[] = ''; // 43. Días de UCI
    
    // VII. Datos del propietario (10 campos)
    $campos[] = ''; // 44. Tipo documento propietario
    $campos[] = ''; // 45. Número documento propietario
    $campos[] = ''; // 46. Primer apellido propietario
    $campos[] = ''; // 47. Segundo apellido propietario
    $campos[] = ''; // 48. Primer nombre propietario
    $campos[] = ''; // 49. Segundo nombre propietario
    $campos[] = ''; // 50. Dirección propietario
    $campos[] = ''; // 51. Teléfono propietario
    $campos[] = ''; // 52. Código departamento propietario
    $campos[] = ''; // 53. Código municipio propietario
    
    // VIII. Datos del conductor (10 campos)
    $nombres_conductor = explode(' ', $atencion['conductor_accidente'] ?? '');
    $campos[] = $nombres_conductor[2] ?? ''; // 54. Primer apellido conductor
    $campos[] = $nombres_conductor[3] ?? ''; // 55. Segundo apellido conductor
    $campos[] = $nombres_conductor[0] ?? ''; // 56. Primer nombre conductor
    $campos[] = $nombres_conductor[1] ?? ''; // 57. Segundo nombre conductor
    $campos[] = $atencion['tipo_documento_conductor_accidente'] ?? 'CC'; // 58. Tipo documento conductor
    $campos[] = $atencion['documento_conductor_accidente'] ?? ''; // 59. Número documento conductor
    $campos[] = ''; // 60. Dirección conductor
    $campos[] = ''; // 61. Código departamento conductor
    $campos[] = ''; // 62. Código municipio conductor
    $campos[] = ''; // 63. Teléfono conductor
    
    // IX. Datos de remisión (11 campos)
    $campos[] = ''; // 64. Tipo de referencia
    $campos[] = ''; // 65. Fecha de remisión
    $campos[] = ''; // 66. Hora de salida
    $campos[] = ''; // 67. Código habilitación IPS remitente
    $campos[] = ''; // 68. Profesional que remite
    $campos[] = ''; // 69. Cargo de quien remite
    $campos[] = ''; // 70. Fecha de ingreso
    $campos[] = ''; // 71. Hora de ingreso
    $campos[] = ''; // 72. Código habilitación IPS que recibe
    $campos[] = ''; // 73. Profesional que recibe
    $campos[] = ''; // 74. Placa ambulancia traslado interinstitucional
    
    // X. Transporte y movilización (5 campos)
    $campos[] = $atencion['ambulancia'] ?? ''; // 75. Placa ambulancia traslado primario
    $campos[] = $atencion['direccion_servicio'] ?? ''; // 76. Transporte desde
    $campos[] = $atencion['nombre_ips_receptora'] ?? ''; // 77. Transporte hasta
    $campos[] = '2'; // 78. Tipo de servicio (2 = Medicalizado)
    $campos[] = $atencion['localizacion'] === 'Urbano' ? 'U' : 'R'; // 79. Zona donde recoge
    
    // XI. Certificación de atención médica (13 campos)
    $campos[] = $atencion['fecha_formatted'] ?? ''; // 80. Fecha de ingreso
    $campos[] = $atencion['hora_llegada_formatted'] ?? ''; // 81. Hora de ingreso
    $campos[] = $atencion['fecha_formatted'] ?? ''; // 82. Fecha de egreso
    $campos[] = $atencion['hora_final_formatted'] ?? ''; // 83. Hora de egreso
    $campos[] = $atencion['diagnostico_principal'] ?? ''; // 84. Código diagnóstico principal ingreso
    $campos[] = ''; // 85. Código diagnóstico asociado 1 ingreso
    $campos[] = ''; // 86. Código diagnóstico asociado 2 ingreso
    $campos[] = ''; // 87. Código diagnóstico asociado 3 ingreso
    $campos[] = ''; // 88. Código diagnóstico principal egreso (NULL - entidad de transporte)
    $campos[] = ''; // 89. Código diagnóstico asociado 1 egreso
    $campos[] = ''; // 90. Código diagnóstico asociado 2 egreso
    $campos[] = ''; // 91. Código diagnóstico asociado 3 egreso
    
    // XII. Datos del médico tratante (4 campos)
    $campos[] = $atencion['medico_tripulante'] ?? $atencion['tripulante'] ?? ''; // 92. Nombres y apellidos
    $campos[] = $atencion['tipo_id_medico'] ?? 'CC'; // 93. Tipo de documento
    $campos[] = $atencion['cc_medico'] ?? $atencion['cc_tripulante'] ?? ''; // 94. Número de documento
    $campos[] = $atencion['registro_medico'] ?? ''; // 95. Número de registro médico
    
    // XIII. Amparos que reclama (3 campos)
    $campos[] = '0'; // 96. Total facturado gastos médico-quirúrgicos
    $campos[] = '0'; // 97. Total reclamado gastos médico-quirúrgicos
    $campos[] = '0'; // 98. Total facturado gastos de transporte
    
    // Limpiar campos: eliminar comas y caracteres especiales
    $campos = array_map(function($campo) {
        $campo = str_replace(',', '', $campo); // Eliminar comas
        $campo = str_replace(["\r", "\n", "\t"], ' ', $campo); // Eliminar saltos de línea
        return trim($campo);
    }, $campos);
    
    return implode(',', $campos);
}

// Funciones auxiliares
function obtenerCodigoHabilitacion() {
    // TODO: Obtener de configuración
    return '76001234567890';
}

function formatearFecha($fecha) {
    if (empty($fecha)) return '';
    $timestamp = strtotime($fecha);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}

function mapearCondicionVictima($condicion) {
    $mapa = [
        'Conductor' => '1',
        'Peatón' => '2',
        'Pasajero' => '3',
        'Ocupante' => '3',
        'Ciclista' => '4'
    ];
    return $mapa[$condicion] ?? '';
}

function mapearNaturalezaEvento($tipo_evento) {
    if (stripos($tipo_evento, 'tránsito') !== false || stripos($tipo_evento, 'transito') !== false) {
        return '01';
    }
    // TODO: Mapear otros tipos de eventos
    return '01';
}

function mapearEstadoAseguramiento($estado) {
    $mapa = [
        'Asegurado' => '1',
        'No asegurado' => '2',
        'Vehículo fantasma' => '3',
        'Póliza falsa' => '4',
        'Vehículo en fuga' => '5'
    ];
    return $mapa[$estado] ?? '1';
}

function mapearTipoVehiculo($tipo) {
    $mapa = [
        'Automóvil' => '1',
        'Bus' => '2',
        'Buseta' => '3',
        'Camión' => '4',
        'Camioneta' => '5',
        'Campero' => '6',
        'Microbús' => '7',
        'Tractocamión' => '8',
        'Motocicleta' => '10',
        'Motocarro' => '14',
        'Mototriciclo' => '17',
        'Cuatrimoto' => '19',
        'Volqueta' => '22'
    ];
    return $mapa[$tipo] ?? '10';
}

// Procesar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $codigo_habilitacion = $_POST['codigo_habilitacion'] ?? obtenerCodigoHabilitacion();
    
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        echo json_encode(['error' => 'Debe especificar rango de fechas']);
        exit;
    }
    
    $resultado = generarFURIPS1($conn, $fecha_inicio, $fecha_fin, $codigo_habilitacion);
    echo json_encode($resultado);
}
?>
