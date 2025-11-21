<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Temporary debug: log incoming POST/FILES keys to error_log for troubleshooting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postKeys = array_keys($_POST);
    $fileKeys = array_keys($_FILES);
    $debugLine = sprintf("[%s] DEBUG procesar_atencion POST keys: %s | FILES: %s\n", date('Y-m-d H:i:s'), json_encode($postKeys), json_encode($fileKeys));
    error_log($debugLine, 3, sys_get_temp_dir() . '/error_log.txt');
}

// =========================================================
// procesar_atencion.php — versión final con todos los campos
// Estructura unificada: todos los datos en la tabla atenciones
// Guarda adjuntos como JSON
// =========================================================
// =========================================================
// Estructura dividida: atenciones / atenciones_sig / atenciones_att
// Guarda adjuntos y firmas directamente en la BD
// Usa transacciones MySQLi y registro de errores en ./error_log.txt
// =========================================================

// Enable verbose error reporting but keep display off; log to error_log.txt
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', sys_get_temp_dir() . '/error_log.txt');

require_once 'conn.php'; // tu conexión mysqli $conn
require_once 'bootstrap.php'; // Para BASE_URL

/**
 * Procesa los campos de antecedentes que tienen una opción "cual" y un campo de texto para "Otro".
 * @param string $post_key_base La base del nombre del campo (ej. 'ant_alergicos').
 * @param array $post_data El array $_POST completo.
 * @return string|null La cadena de texto procesada y lista para la BD.
 */
function procesar_antecedente_cual($post_key_base, $post_data) {
    $cual_data = $post_data[$post_key_base . '_cual'] ?? null;

    // Maneja campos que no son de selección múltiple (ej. ginecoobstetricos)
    if (!is_array($cual_data)) {
        return $cual_data;
    }

    $cual_array = $cual_data;
    $otro_text = trim($post_data[$post_key_base . '_cual_otro'] ?? '');

    $otro_key = array_search('Otro', $cual_array);

    if ($otro_key !== false) {
        if (!empty($otro_text)) {
            // Reemplaza 'Otro' con el texto especificado
            $cual_array[$otro_key] = 'Otro: ' . $otro_text;
        } else {
            // Si 'Otro' fue seleccionado pero no se especificó nada, se elimina.
            unset($cual_array[$otro_key]);
        }
    }

    return implode(', ', $cual_array);
}

// --- Recolección segura de datos del formulario ---
// Se utiliza el operador '?? null' para evitar errores si una clave no existe en $_POST.
$registro = null; // El registro ahora se basará en el ID auto-incremental.
$fecha = !empty($_POST['fecha']) ? DateTime::createFromFormat('d-m-Y', $_POST['fecha'])->format('Y-m-d') : null;
$ambulancia = $_POST['ambulancia'] ?? null;
$servicio = $_POST['servicio'] ?? null;
$pagador = $_POST['pagador'] ?? null;
$tipo_traslado = $_POST['tipo_traslado'] ?? 'Sencillo';
$quien_informo = $_POST['quien_informo'] ?? null;
$hora_despacho = $_POST['hora_despacho'] ?? null;
$hora_llegada = $_POST['hora_llegada'] ?? null;
$hora_ingreso = $_POST['hora_ingreso'] ?? null;
$hora_final = $_POST['hora_final'] ?? null;
$conductor = $_POST['conductor'] ?? null;
$cc_conductor = $_POST['cc_conductor'] ?? null;
$tripulante = $_POST['tripulante_hidden'] ?? null;
$cc_tripulante = $_POST['cc_tripulante'] ?? null;
$tipo_id_tripulante = $_POST['tipo_id_tripulante'] ?? null;
$medico_tripulante = $_POST['medico_tripulante'] ?? null;
$cc_medico = $_POST['cc_medico'] ?? null;
$tipo_id_medico = $_POST['tipo_id_medico'] ?? null;
$direccion_servicio = $_POST['direccion_servicio'] ?? null;
$localizacion = $_POST['localizacion'] ?? null;

// Consolidar el nombre de la aseguradora desde SOAT, EPS o ARL en un solo campo.
$aseguradora_soat = $_POST['aseguradora_soat'] ?? null;
$aseguradora_eps_manual = trim($_POST['aseguradora_eps_manual'] ?? '');
$aseguradora_arl_manual = trim($_POST['aseguradora_arl_manual'] ?? '');

if ($pagador === 'EPS' && !empty($aseguradora_eps_manual)) {
    $aseguradora_soat = $aseguradora_eps_manual;
} elseif ($pagador === 'ARL' && !empty($aseguradora_arl_manual)) {
    $aseguradora_soat = $aseguradora_arl_manual;
}

$ips2 = $_POST['ips2'] ?? null;
$ips3 = $_POST['ips3'] ?? null;
$ips4 = $_POST['ips4'] ?? null;
$eps_nombre = !empty($_POST['eps_nombre']) ? $_POST['eps_nombre'] : null;
$nombres_paciente = $_POST['nombres_paciente'] ?? null;
$tipo_identificacion = $_POST['tipo_identificacion'] ?? null;
$id_paciente = $_POST['id_paciente'] ?? null;
$genero_nacer = $_POST['genero_nacer'] ?? null;
$fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
$escena_paciente_raw = $_POST['escena_paciente'] ?? null;
$escena_paciente = ($escena_paciente_raw === 'Otro') ? 'Otro: ' . trim($_POST['escena_paciente_otro'] ?? '') : $escena_paciente_raw;
$direccion_domicilio = $_POST['direccion_domicilio'] ?? null;
$telefono_paciente = $_POST['telefono_paciente'] ?? null;
$municipio = $_POST['municipio_paciente'] ?? null;
$barrio_paciente = $_POST['barrio_paciente'] ?? null;
$frecuencia_cardiaca = !empty($_POST['frecuencia_cardiaca']) ? $_POST['frecuencia_cardiaca'] : null;
$frecuencia_respiratoria = !empty($_POST['frecuencia_respiratoria']) ? $_POST['frecuencia_respiratoria'] : null;
$spo2 = !empty($_POST['spo2']) ? $_POST['spo2'] : null;
$tension_arterial = $_POST['tension_arterial'] ?? null;
$glucometria = !empty($_POST['glucometria']) ? $_POST['glucometria'] : null;
$temperatura = !empty($_POST['temperatura']) ? $_POST['temperatura'] : null;
$rh = $_POST['rh'] ?? null;
$llenado_capilar = $_POST['llenado_capilar'] ?? null;
$peso = !empty($_POST['peso']) ? $_POST['peso'] : null;
$talla = !empty($_POST['talla']) ? $_POST['talla'] : null;
$examen_fisico = $_POST['examen_fisico'] ?? null;
$procedimientos = $_POST['procedimientos'] ?? null;
$consumo_servicio = $_POST['consumo_servicio'] ?? null;
$escala_glasgow = !empty($_POST['escala_glasgow']) ? $_POST['escala_glasgow'] : null;
$nit_ips_receptora = $_POST['ips_nit'] ?? $_POST['nit_ips_receptora'] ?? null;
$nombre_ips_receptora = $_POST['ips_nombre'] ?? $_POST['nombre_ips_receptora'] ?? null;
$codigo_municipio_servicio = $_POST['codigo_municipio_servicio'] ?? null;
$codigo_municipio = $_POST['codigo_municipio'] ?? null;
$diagnostico_principal = $_POST['diagnostico_principal'] ?? null;
$motivo_traslado = $_POST['motivo_traslado'] ?? null;
$atencion_en = $_POST['atencion_en'] ?? null;
$etnia_raw = $_POST['etnia'] ?? null;
$etnia = ($etnia_raw === 'Otro') ? 'Otro: ' . trim($_POST['especificar_otra'] ?? '') : $etnia_raw;
$discapacidad = $_POST['discapacidad'] ?? null;
$downton_total = $_POST['downton_total'] ?? null;
$oxigeno_dispositivo = $_POST['oxigeno_dispositivo'] ?? null;
$oxigeno_flujo = !empty($_POST['oxigeno_flujo']) ? $_POST['oxigeno_flujo'] : null;
$oxigeno_fio2 = !empty($_POST['oxigeno_fio2']) ? $_POST['oxigeno_fio2'] : null;
$nombre_medico_receptor = $_POST['nombre_medico_receptor'] ?? null;
$id_medico_receptor = $_POST['id_medico_receptor'] ?? null;
$tipo_id_medico_receptor = $_POST['tipo_id_medico_receptor'] ?? null;
$placa_paciente = $_POST['placa_paciente'] ?? null;
$nombre_receptor_admin_ips = $_POST['nombre_receptor_admin_ips'] ?? null;
$tipo_documento_receptor_admin_ips = $_POST['tipo_documento_receptor_admin_ips'] ?? null;
$id_receptor_admin_ips = $_POST['id_receptor_admin_ips'] ?? null;
$conductor_accidente = $_POST['conductor_accidente'] ?? null;
$documento_conductor_accidente = $_POST['documento_conductor_accidente'] ?? null;
$tarjeta_propiedad_accidente = $_POST['tarjeta_propiedad_accidente'] ?? null;
$placa_vehiculo_involucrado = $_POST['placa_vehiculo_involucrado'] ?? null;

// --- Nuevos campos para Resolución 2284/2023 (Transporte Especial) ---
$triage_escena = $_POST['triage_escena'] ?? null;
$hora_salida_escena = $_POST['hora_salida_escena'] ?? null;
$codigo_cups_traslado = $_POST['codigo_cups_traslado'] ?? null;
$codigo_reps_origen = $_POST['codigo_reps_origen'] ?? null;
$hora_recepcion_paciente = $_POST['hora_recepcion_paciente'] ?? null;
$estado_ingreso = $_POST['estado_ingreso'] ?? null;
$estado_final_traslado = $_POST['estado_final_traslado'] ?? null;
$km_inicial = !empty($_POST['km_inicial']) ? $_POST['km_inicial'] : null;
$km_final = !empty($_POST['km_final']) ? $_POST['km_final'] : null;
$horas_espera = !empty($_POST['horas_espera']) ? $_POST['horas_espera'] : null;
$eventos_traslado = $_POST['eventos_traslado'] ?? null;
$codigo_reps_destino = $_POST['codigo_reps_destino'] ?? null;

// Campos que faltaban guardar (Acompañante y Medicamentos)
$nombre_acompanante = $_POST['nombre_acompanante'] ?? null;
$parentesco_acompanante = $_POST['parentesco_acompanante'] ?? null;
$id_acompanante = $_POST['id_acompanante'] ?? null;
$medicamentos_aplicados = $_POST['medicamentos_aplicados'] ?? null;

// Calcular distancia recorrida
$distancia_recorrida = null;
if ($km_inicial !== null && $km_final !== null) {
    $distancia_recorrida = floatval($km_final) - floatval($km_inicial);
}

$conn->begin_transaction();

/**
 * Optimiza una imagen (redimensiona y comprime) y la convierte a Base64.
 * Similar a la lógica en procesar_atencionF3.php
 */
function optimizar_y_convertir_a_base64($tmpName, $mimeType, $maxWidth = 1024, $quality = 75) {
    if (!function_exists('imagecreatetruecolor')) {
        return null; // GD library not available, skip image processing
    }

    if ($mimeType === 'application/pdf') {
        $pdfData = file_get_contents($tmpName);
        return 'data:application/pdf;base64,' . base64_encode($pdfData);
    }
    if (!file_exists($tmpName) || filesize($tmpName) == 0 || !@getimagesize($tmpName)) {
        return null;
    }
    list($width, $height) = getimagesize($tmpName);
    $newWidth = ($width > $maxWidth) ? $maxWidth : $width;
    $newHeight = ($width > $maxWidth) ? round($height * ($maxWidth / $width)) : $height;
    $thumb = imagecreatetruecolor((int)$newWidth, (int)$newHeight);
    $source = ($mimeType == 'image/jpeg') ? imagecreatefromjpeg($tmpName) : imagecreatefrompng($tmpName);
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $width, $height);
    ob_start();
    imagejpeg($thumb, null, $quality);
    return 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
}

try {
    // =========================================================
    // 1️⃣ Inserción principal en atenciones
    // =========================================================
    // Build an INSERT dynamically based on which columns actually exist in the DB
    // Map some form field names to the canonical DB columns to handle schema differences.
    $desired = [
        'fecha' => $fecha,
        'registro' => $_POST['registro'] ?? date('YmdHis') . rand(100,999),
        'ambulancia' => $ambulancia,
        'servicio' => $servicio,
        'pagador' => $pagador,
        'tipo_traslado' => $tipo_traslado,
        'quien_informo' => $quien_informo,
        'hora_despacho' => $hora_despacho,
        'hora_llegada' => $hora_llegada,
        'hora_ingreso' => $hora_ingreso,
        'hora_final' => $hora_final,
        'conductor' => $conductor,
        'cc_conductor' => $cc_conductor,
        'tripulante' => $tripulante,
        'cc_tripulante' => $cc_tripulante,
        'tipo_id_tripulante' => $tipo_id_tripulante,
        'medico_tripulante' => $medico_tripulante,
        'cc_medico' => $cc_medico,
        'tipo_id_medico' => $tipo_id_medico,
        'direccion_servicio' => $direccion_servicio,
        'atencion_en' => $atencion_en,
        'etnia' => $etnia,
        'discapacidad' => $discapacidad,
        'downton_total' => $downton_total,
        'oxigeno_dispositivo' => $oxigeno_dispositivo,
        'oxigeno_flujo' => $oxigeno_flujo,
        'oxigeno_fio2' => $oxigeno_fio2,
        'localizacion' => $localizacion,
        'aseguradora_soat' => $aseguradora_soat,
        'ips2' => $ips2,
        'ips3' => $ips3,
        'ips4' => $ips4,
        'eps_nombre' => $eps_nombre,
        'nombres_paciente' => $nombres_paciente,
        'tipo_identificacion' => $tipo_identificacion,
        'id_paciente' => $id_paciente,
        'genero_nacer' => $genero_nacer,
        'fecha_nacimiento' => $fecha_nacimiento,
        'escena_paciente' => $escena_paciente,
        'direccion_domicilio' => $direccion_domicilio,
        'telefono_paciente' => $telefono_paciente,
    // Mapear variantes de municipio/ciudad; asegurar un valor no nulo para evitar errores de restricción.
    'municipio' => $municipio,
        'barrio_paciente' => $barrio_paciente,
        'frecuencia_cardiaca' => $frecuencia_cardiaca,
        'frecuencia_respiratoria' => $frecuencia_respiratoria,
        'spo2' => $spo2,
        'tension_arterial' => $tension_arterial,
        'glucometria' => $glucometria,
        'temperatura' => $temperatura,
        'rh' => $rh,
        'llenado_capilar' => $llenado_capilar,
        'peso' => $peso,
        'talla' => $talla,
        'examen_fisico' => $examen_fisico,
        'procedimientos' => $procedimientos,
        'consumo_servicio' => $consumo_servicio,
        'escala_glasgow' => $escala_glasgow,
        // map ips fields
        'nit_ips_receptora' => $nit_ips_receptora,
        'nombre_ips_receptora' => $nombre_ips_receptora,
        'codigo_municipio_servicio' => $codigo_municipio_servicio,
        'codigo_municipio' => $codigo_municipio,
        'diagnostico_principal' => $diagnostico_principal,
        'motivo_traslado' => $motivo_traslado,
        // accident fields (DB has placa_vehiculo and placa_vehiculo_involucrado)
        'conductor_accidente' => $conductor_accidente,
        'documento_conductor_accidente' => $documento_conductor_accidente,
        'tarjeta_propiedad_accidente' => $tarjeta_propiedad_accidente,
        'placa_vehiculo_involucrado' => $placa_vehiculo_involucrado,
        'placa_vehiculo' => $placa_paciente ?: $placa_vehiculo_involucrado,
        // receptor admin fields (map if present)

        'nombre_medico_receptor' => $nombre_medico_receptor,
        'id_medico_receptor' => $id_medico_receptor,
        'tipo_id_medico_receptor' => $tipo_id_medico_receptor,
    ];

    // Procesar antecedentes
    $antecedentes_keys = ['patologicos', 'quirurgicos', 'alergicos', 'familiares', 'toxicologicos', 'ginecoobstetricos'];
    foreach ($antecedentes_keys as $key) {
        $sn_key = 'ant_' . $key . '_sn';
        $cual_key = 'ant_' . $key . '_cual';
        $desired[$sn_key] = $_POST[$sn_key] ?? 'No';
        if ($desired[$sn_key] === 'Si') {
            $desired[$cual_key] = procesar_antecedente_cual('ant_' . $key, $_POST);
        } else {
            $desired[$cual_key] = null;
        }
    }

    // Query information_schema for present columns in 'atenciones'
    $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones'");
    if (!$schemaStmt) {
        throw new Exception('Error preparando consulta de esquema: ' . $conn->error);
    }
    $dbName = $conn->real_escape_string($conn->query("SELECT DATABASE() as db")->fetch_object()->db ?? '');
$schemaStmt->bind_param('s', $dbName);
    if (!$schemaStmt->execute()) {
        throw new Exception('Error ejecutando consulta de esquema: ' . $schemaStmt->error);
    }
    $res = $schemaStmt->get_result();
    $existing = [];
    while ($r = $res->fetch_assoc()) {
        $existing[] = $r['COLUMN_NAME'];
    }
    $schemaStmt->close();

    $insertCols = [];
    $placeholders = [];
    $values = [];
    foreach ($desired as $col => $val) {
        if (in_array($col, $existing) && $val !== null) {
            $insertCols[] = $col;
            $placeholders[] = '?';
            $values[] = $val;
        }
    }

    if (empty($insertCols)) {
        throw new Exception('No hay columnas válidas para insertar en atenciones.');
    }

    $sql = 'INSERT INTO atenciones (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparando inserción dinámica en atenciones: ' . $conn->error . ' | SQL: ' . $sql);
    }

    // Build types string and bind params dynamically (all as strings)
    $types = str_repeat('s', count($values));
    // Prepare bind_param arguments by reference
    $bindParams = [];
    $bindParams[] = & $types;
    for ($i = 0; $i < count($values); $i++) {
        // ensure variables are passed by reference
        $bindParams[] = & $values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);

    // Debug: log the SQL and parameter count before executing
    error_log(sprintf("[%s] DEBUG SQL atenciones: %s | params: %d\n", date('Y-m-d H:i:s'), $sql, count($values)), 3, sys_get_temp_dir() . '/error_log.txt');

    if (!$stmt->execute()) {
        error_log(sprintf("[%s] ERROR executing atenciones: %s | stmt_error: %s\n", date('Y-m-d H:i:s'), $sql, $stmt->error), 3, sys_get_temp_dir() . '/error_log.txt');
        throw new Exception('Error al insertar en atenciones: ' . $stmt->error . ' | SQL: ' . $sql);
    }

    $id_atencion = $conn->insert_id;
    error_log(sprintf("[%s] INFO atenciones inserted id: %d\n", date('Y-m-d H:i:s'), $id_atencion), 3, sys_get_temp_dir() . '/error_log.txt');
    $stmt->close();

    // Ahora, actualizamos la columna 'registro' con el ID recién insertado.
    $stmt_update_reg = $conn->prepare("UPDATE atenciones SET registro = ? WHERE id = ?");
    if ($stmt_update_reg) {
        $id_str = (string)$id_atencion;
        $stmt_update_reg->bind_param('si', $id_str, $id_atencion);
        $stmt_update_reg->execute();
        $stmt_update_reg->close();
    }

    // =========================================================
    // 1.5️⃣ Inserción en atenciones_extra (Res. 2284)
    // =========================================================
    $sql_extra = "INSERT INTO atenciones_extra (
        atencion_id, triage_escena, hora_salida_escena, codigo_cups_traslado, 
        codigo_reps_origen, hora_recepcion_paciente, estado_ingreso, 
        estado_final_traslado, km_inicial, km_final, distancia_recorrida, 
        horas_espera, eventos_traslado, codigo_reps_destino, 
        nombre_acompanante, parentesco_acompanante, id_acompanante, 
        medicamentos_aplicados
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt_extra = $conn->prepare($sql_extra);
    if ($stmt_extra) {
        $stmt_extra->bind_param(
            'isssssssddddssssss',
            $id_atencion,
            $triage_escena,
            $hora_salida_escena,
            $codigo_cups_traslado,
            $codigo_reps_origen,
            $hora_recepcion_paciente,
            $estado_ingreso,
            $estado_final_traslado,
            $km_inicial,
            $km_final,
            $distancia_recorrida,
            $horas_espera,
            $eventos_traslado,
            $codigo_reps_destino,
            $nombre_acompanante,
            $parentesco_acompanante,
            $id_acompanante,
            $medicamentos_aplicados
        );
        
        if (!$stmt_extra->execute()) {
             error_log(sprintf("[%s] ERROR inserting atenciones_extra for atencion %d: %s\n", date('Y-m-d H:i:s'), $id_atencion, $stmt_extra->error), 3, sys_get_temp_dir() . '/error_log.txt');
             // No lanzamos excepción para no revertir la atención principal, pero logueamos el error.
        }
        $stmt_extra->close();
    } else {
         error_log(sprintf("[%s] ERROR preparing atenciones_extra: %s\n", date('Y-m-d H:i:s'), $conn->error), 3, sys_get_temp_dir() . '/error_log.txt');
    }

    // =========================================================
    // 2️⃣ Inserción de firmas (atenciones_sig)
    // =========================================================
    // Dynamic insertion into atenciones_sig depending on available columns.
    // Collect signature fields from POST and normalize empty data URIs to null.
    $firma_paramedico = $_POST['firma_paramedico'] ?? null;
    $firma_medico = $_POST['firma_medico'] ?? null;
    $firma_paciente = $_POST['firma_paciente'] ?? null;
    $firma_medico_receptor = $_POST['firma_medico_receptor'] ?? null;
    $firma_desistimiento = $_POST['firma_desistimiento'] ?? null;
    $firma_receptor_admin_ips = $_POST['firma_receptor_admin_ips'] ?? null;
    $firma_receptor_ips = $_POST['firma_receptor_ips'] ?? null; // newer field

    $normalize = function($f) {
        return (empty($f) || $f === 'data:,') ? null : $f;
    };
    $firma_paramedico = $normalize($firma_paramedico);
    $firma_medico = $normalize($firma_medico);
    $firma_paciente = $normalize($firma_paciente);
    $firma_medico_receptor = $normalize($firma_medico_receptor);
    $firma_desistimiento = $normalize($firma_desistimiento);
    $firma_receptor_admin_ips = $normalize($firma_receptor_admin_ips);
    $firma_receptor_ips = $normalize($firma_receptor_ips);

    if (!empty($firma_desistimiento)) {
        $firma_medico_receptor = null;
        $firma_receptor_admin_ips = null;
        $firma_receptor_ips = null;
    }

    $desiredSig = [
        'atencion_id' => $id_atencion,
        'firma_paramedico' => $firma_paramedico,
        'firma_medico' => $firma_medico,
        'firma_paciente' => $firma_paciente,
        'firma_medico_receptor' => $firma_medico_receptor,
        'firma_desistimiento' => $firma_desistimiento
    ];
    // Añadir firmas adicionales solo si los campos existen en el POST

    if (isset($_POST['firma_receptor_ips'])) $desiredSig['firma_receptor_ips'] = $firma_receptor_ips;
    // Para compatibilidad con esquemas antiguos, si existe firma_receptor_ips, se usa para firma_representante_legal_ips
    if (isset($_POST['firma_receptor_ips']) || isset($_POST['firma_receptor_admin_ips'])) {
        $desiredSig['firma_representante_legal_ips'] = $firma_receptor_ips ?? $firma_receptor_admin_ips;
    }

    // Insert signatures as one row per signature in `atenciones_sig` (schema: atencion_id, tipo_firma, contenido, nombre_firmante, cargo_firmante)
    $sigMap = [
        'paramedico' => $firma_paramedico,
        'medico' => $firma_medico,
        'paciente' => $firma_paciente,
        'medico_receptor' => $firma_medico_receptor,
        'desistimiento' => $firma_desistimiento,
        // representante legal can come from new or legacy field
        'representante_legal' => $firma_receptor_ips ?? $firma_receptor_admin_ips
    ];

    $stmt_sig = $conn->prepare("INSERT INTO atenciones_sig (atencion_id, tipo_firma, contenido, nombre_firmante, cargo_firmante) VALUES (?, ?, ?, NULL, NULL)");
    if (!$stmt_sig) {
        throw new Exception('Error preparando inserción en atenciones_sig: ' . $conn->error);
    }

    foreach ($sigMap as $tipo => $contenido) {
        if (empty($contenido)) continue;
        // normalize content if it's a data:...base64 string
        if (strpos($contenido, 'base64,') !== false) {
            $bin = base64_decode(substr($contenido, strpos($contenido, 'base64,') + 7));
        } else {
            $bin = $contenido;
        }
        $stmt_sig->bind_param('iss', $id_atencion, $tipo, $bin);
        if (!$stmt_sig->execute()) {
            error_log(sprintf("[%s] ERROR inserting signature %s for atencion %d: %s\n", date('Y-m-d H:i:s'), $tipo, $id_atencion, $stmt_sig->error), 3, sys_get_temp_dir() . '/error_log.txt');
            // continue inserting others but log error
            continue;
        }
    }
    error_log(sprintf("[%s] INFO atenciones_sig inserted for atencion_id: %d\n", date('Y-m-d H:i:s'), $id_atencion), 3, sys_get_temp_dir() . '/error_log.txt');
    $stmt_sig->close();

    // =========================================================
    // 3️⃣ Inserción de adjuntos (atenciones_att)
    // =========================================================
    $attachments = [];

    if (!empty($_FILES['adjuntos']['name'][0])) {
        foreach ($_FILES['adjuntos']['name'] as $key => $filename) {
            $tmpName = $_FILES['adjuntos']['tmp_name'][$key];
            $mimeType = $_FILES['adjuntos']['type'][$key];

            // Try to optimize images (returns data:<mime>;base64,... ) or fallback to raw file contents
            $maybeBase64 = optimizar_y_convertir_a_base64($tmpName, $mimeType);
            if ($maybeBase64 && strpos($maybeBase64, 'base64,') !== false) {
                $binary = base64_decode(substr($maybeBase64, strpos($maybeBase64, 'base64,') + 7));
            } else {
                $binary = file_get_contents($tmpName);
            }

            if ($binary !== false && strlen($binary) > 0) {
                $attachments[] = [
                    'atencion_id' => $id_atencion,
                    'tipo_adjunto' => $mimeType,
                    'contenido' => $binary,
                    'nombre_archivo' => $filename
                ];
            }
        }
    }

    if (!empty($attachments)) {
        $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'atenciones_att'");
        if (!$schemaStmt) {
            throw new Exception('Error preparando consulta de esquema para atenciones_att: ' . $conn->error);
        }
        $schemaStmt->bind_param('s', $conn->real_escape_string($conn->query("SELECT DATABASE() as db")->fetch_object()->db ?? ''));
        if (!$schemaStmt->execute()) {
            throw new Exception('Error ejecutando consulta de esquema para atenciones_att: ' . $schemaStmt->error);
        }
        $res = $schemaStmt->get_result();
        $existing = [];
        while ($r = $res->fetch_assoc()) {
            $existing[] = $r['COLUMN_NAME'];
        }
        $schemaStmt->close();

        foreach ($attachments as $attachment) {
            $insertCols = [];
            $placeholders = [];
            $values = [];

            foreach ($attachment as $col => $val) {
                if (in_array($col, $existing) && $val !== null) {
                    $insertCols[] = $col;
                    $placeholders[] = '?';
                    $values[] = $val;
                }
            }

            if (!empty($insertCols)) {
                $sql = 'INSERT INTO atenciones_att (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Error preparando inserción dinámica en atenciones_att: ' . $conn->error . ' | SQL: ' . $sql);
                }

                $types = str_repeat('s', count($values));
                $bindParams = [];
                $bindParams[] = & $types;
                for ($i = 0; $i < count($values); $i++) {
                    $bindParams[] = & $values[$i];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindParams);

                if (!$stmt->execute()) {
                    error_log(sprintf("[%s] ERROR executing atenciones_att: %s | stmt_error: %s\n", date('Y-m-d H:i:s'), $sql, $stmt->error), 3, sys_get_temp_dir() . '/error_log.txt');
                    throw new Exception('Error al insertar en atenciones_att: ' . $stmt->error . ' | SQL: ' . $sql);
                }

                $stmt->close();
            }
        }
    }

    // =========================================================
    // 4️⃣ Confirmar transacción
    // =========================================================
    $conn->commit();

    // Mensaje de éxito y script para limpiar localStorage en la página de consulta.
    $_SESSION['message'] = '<div class="alert alert-success">Registro #' . $id_atencion . ' guardado exitosamente.</div>';
    $_SESSION['clear_storage'] = <<<HTML
<script>
  try { localStorage.removeItem('form_autosave_data'); } catch(e) { console.error('Failed to clear form autosave data:', e); }
</script>
HTML;

    // Redirigir a la página de consulta para mostrar el resultado y la lista actualizada.
    header('Location: ' . BASE_URL . 'consulta_atenciones.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $errMsg = '[' . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString() . "\n";
    // include a light snapshot of the request environment for debugging
    $errMsg .= "POST count: " . count($_POST) . " | FILES count: " . count($_FILES) . "\n";
$errMsg .= "Recent POST keys: " . json_encode(array_slice(array_keys($_POST), 0, 25)) . "\n";
error_log($errMsg, 3, sys_get_temp_dir() . '/error_log.txt');

    $_SESSION['message'] = '<div class="alert alert-danger">❌ Ocurrió un error al guardar. Por favor, intenta de nuevo.</div>';
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$conn->close();
?>
