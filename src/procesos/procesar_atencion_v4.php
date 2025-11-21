<?php
/**
 * procesar_atencion_v4.php
 * Inserta o actualiza una atención médica en la tabla `atenciones`
 * Compatible con los nuevos formularios por secciones y sistema de firmas.
 */

require_once '../conn.php';
// Asegurar zona horaria consistente con operación en Colombia
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/Bogota');
}
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'id' => null];

// Utilidad: optimiza imágenes a JPEG y PDFs a base64, devuelve binario optimizado
function v4_optimize_to_binary($tmpName, $mimeType) {
    if ($mimeType === 'application/pdf') {
        return file_exists($tmpName) ? file_get_contents($tmpName) : null;
    }
    if (!function_exists('imagecreatetruecolor')) {
        return file_exists($tmpName) ? file_get_contents($tmpName) : null;
    }
    if (!file_exists($tmpName) || filesize($tmpName) == 0) {
        return null;
    }
    
    // Get image info to determine actual type
    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return null;
    }
    
    list($width, $height, $imageType) = $imageInfo;
    
    // Create source image based on actual type
    $source = null;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $source = @imagecreatefromjpeg($tmpName);
            break;
        case IMAGETYPE_PNG:
            $source = @imagecreatefrompng($tmpName);
            break;
        case IMAGETYPE_GIF:
            $source = @imagecreatefromgif($tmpName);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $source = @imagecreatefromwebp($tmpName);
            }
            break;
        default:
            // Unsupported format, return original
            return file_get_contents($tmpName);
    }
    
    if (!$source) {
        return file_get_contents($tmpName);
    }
    
    // Resize if needed
    $maxWidth = 1280;
    $newWidth = ($width > $maxWidth) ? $maxWidth : $width;
    $newHeight = ($width > $maxWidth) ? round($height * ($maxWidth / max($width,1))) : $height;
    $thumb = imagecreatetruecolor((int)$newWidth, (int)$newHeight);
    
    // Preserve transparency for PNG/GIF
    if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $width, $height);
    imagedestroy($source);
    
    ob_start();
    imagejpeg($thumb, null, 75);
    $result = ob_get_clean();
    imagedestroy($thumb);
    
    return $result;
}

try {
    // --- Validar método ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido. Use POST.');
    }

    // --- Acciones tempranas: subir adjunto(s) individuales ---
    if (!empty($_POST['action']) && $_POST['action'] === 'upload_attachment') {
        $aid = isset($_POST['atencion_id']) && is_numeric($_POST['atencion_id']) ? (int)$_POST['atencion_id'] : 0;
        if ($aid <= 0) throw new Exception('Falta atencion_id para subir adjuntos.');
        if (empty($_FILES) || (!isset($_FILES['file']) && !isset($_FILES['adjuntos']))) throw new Exception('No se recibió archivo.');

        // Permitir subir 1..N con key 'file' o 'adjuntos'
        $key = isset($_FILES['file']) ? 'file' : 'adjuntos';
        $names = is_array($_FILES[$key]['name']) ? $_FILES[$key]['name'] : [$_FILES[$key]['name']];
        $tmps = is_array($_FILES[$key]['tmp_name']) ? $_FILES[$key]['tmp_name'] : [$_FILES[$key]['tmp_name']];
        $types = is_array($_FILES[$key]['type']) ? $_FILES[$key]['type'] : [$_FILES[$key]['type']];

        // Validar esquema de atenciones_att
        $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atenciones_att'");
        if (!$schemaStmt) throw new Exception('Error consultando esquema atenciones_att: ' . $conn->error);
        $schemaStmt->execute();
        $resA = $schemaStmt->get_result();
        $attCols = [];
        while ($r=$resA->fetch_assoc()) { $attCols[]=$r['COLUMN_NAME']; }
        $schemaStmt->close();

        $saved = [];
        for ($i=0; $i<count($names); $i++) {
            $filename = $names[$i];
            $tmp = $tmps[$i];
            $mime = $types[$i] ?: 'application/octet-stream';
            if (!is_uploaded_file($tmp)) continue;
            $bin = v4_optimize_to_binary($tmp, $mime);
            if ($bin === null) $bin = file_get_contents($tmp);
            if (empty($bin)) continue;

            $data = [
                'atencion_id' => $aid,
                'tipo_adjunto' => $mime,
                'contenido' => $bin,
                'nombre_archivo' => $filename
            ];
            $colsI=[];$phI=[];$valsI=[]; foreach ($data as $c=>$v) { if (in_array($c,$attCols) && $v !== null) { $colsI[]=$c;$phI[]='?';$valsI[]=$v; } }
            if ($colsI) {
                $sqlI='INSERT INTO atenciones_att (' . implode(',',$colsI) . ') VALUES (' . implode(',',$phI) . ')';
                $st=$conn->prepare($sqlI);
                if ($st){ $typesB=str_repeat('s',count($valsI)); $bind=[];$bind[]=&$typesB; for($k=0;$k<count($valsI);$k++){ $bind[]=&$valsI[$k]; } call_user_func_array([$st,'bind_param'],$bind); $st->execute(); $st->close(); }
                $saved[] = ['name'=>$filename,'type'=>$mime];
            }
        }

        echo json_encode(['success'=>true,'uploaded'=>$saved,'id'=>$aid], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Merge JSON payload if provided in 'data'
    if (!empty($_POST['data']) && is_string($_POST['data'])) {
        $decoded = json_decode($_POST['data'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $_POST = array_merge($_POST, $decoded);
        }
    }

    // Detect ID if present (update vs insert). En V4 normalmente es inserción.
    $id = null;
    if (!empty($_POST['atencion_id']) && is_numeric($_POST['atencion_id'])) $id = (int)$_POST['atencion_id'];
    elseif (!empty($_POST['id']) && is_numeric($_POST['id'])) $id = (int)$_POST['id'];

    // --- Sección opcional (guardado parcial) ---
    $seccion = $_POST['seccion'] ?? null;
    $data = $_POST['data'] ?? null;

    // Si llega un JSON de datos embebido en 'data', intentar fusionarlo a $_POST
    if ($data && is_string($data)) {
        $decoded = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $_POST = array_merge($_POST, $decoded);
        } else {
            // No abortar por JSON inválido; solo registrar mensaje
            $response['message'] = 'Advertencia: payload JSON ignorado por formato inválido.';
        }
    }

    // Si es guardado final (sin seccion especifica), completar horas si vienen vacias
    if (empty($seccion)) {
        $ahora = date('H:i:s');
        if (empty($_POST['hora_despacho'])) {
            $_POST['hora_despacho'] = $ahora;
        }
        if (empty($_POST['hora_final'])) {
            $_POST['hora_final'] = $ahora;
        }
    }

    // --- Limpiar y validar entrada ---
    $campos = [];
    foreach ($_POST as $k => $v) {
        // Mantener archivos fuera
        if ($k === 'data') continue;
        $campos[$k] = is_string($v) ? trim($v) : $v;
    }

    // Normalizar nombres separados del paciente
    $fullName = trim($campos['nombres_paciente'] ?? '');
    $primerNombre = trim($campos['primer_nombre_paciente'] ?? '');
    $segundoNombre = trim($campos['segundo_nombre_paciente'] ?? '');
    $primerApellido = trim($campos['primer_apellido_paciente'] ?? '');
    $segundoApellido = trim($campos['segundo_apellido_paciente'] ?? '');

    if (!$primerNombre && $fullName) {
        $parts = preg_split('/\s+/', $fullName);
        $primerNombre = array_shift($parts) ?? '';
        if (!$segundoNombre && !empty($parts)) {
            $segundoNombre = array_shift($parts) ?? '';
        }
    }
    if (!$primerApellido && !empty($campos['primer_apellido_estatico'])) {
        $primerApellido = trim($campos['primer_apellido_estatico']);
    }
    if (!$primerApellido && !empty($campos['segundo_apellido_paciente'])) {
        $primerApellido = trim(explode(' ', $campos['segundo_apellido_paciente'])[0] ?? '');
    }

    $campos['primer_nombre_paciente'] = $primerNombre ?: null;
    $campos['segundo_nombre_paciente'] = $segundoNombre ?: null;
    $campos['primer_apellido_paciente'] = $primerApellido ?: null;
    $campos['segundo_apellido_paciente'] = $segundoApellido ?: null;

    // --- Sincronización de firmas: verificar presencia (no forzar error duro) ---
    $firmaTrip = $campos['FirmaParamedicoData'] ?? $campos['firma_paramedico'] ?? '';
    $firmaPac = $campos['FirmaPacienteData'] ?? $campos['firma_paciente'] ?? '';
    $firmaDes = $campos['FirmaDesistimientoData'] ?? $campos['firma_desistimiento'] ?? '';

    // Regla: Requiere firma de tripulante y (paciente o desistimiento)
    $errores = [];
    if (!$firmaTrip) $errores[] = 'Firma del tripulante requerida.';
    if (!$firmaPac && !$firmaDes) $errores[] = 'Se requiere firma de aceptación o desistimiento.';

    if (!empty($errores)) {
        $response['success'] = false;
        $response['message'] = implode(' ', $errores);
        $response['id'] = $id;
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Begin transaction
    $conn->begin_transaction();

    // Consolidar nombre de aseguradora (SOAT / EPS / ARL) en un único campo
    $pagador = $_POST['pagador'] ?? null;
    $aseguradora_soat = $_POST['aseguradora_soat'] ?? null;
    $aseguradora_soat_otra = trim($_POST['aseguradora_soat_otra'] ?? '');
    $aseguradora_eps_manual = trim($_POST['aseguradora_eps_manual'] ?? '');
    $aseguradora_arl_manual = trim($_POST['aseguradora_arl_manual'] ?? '');

    if ($aseguradora_soat && strcasecmp($aseguradora_soat, 'Otra') === 0 && $aseguradora_soat_otra !== '') {
        $aseguradora_soat = $aseguradora_soat_otra;
    }
    if ($pagador === 'EPS' && $aseguradora_eps_manual !== '') {
        $aseguradora_soat = $aseguradora_eps_manual;
    } elseif ($pagador === 'ARL' && $aseguradora_arl_manual !== '') {
        $aseguradora_soat = $aseguradora_arl_manual;
    }

    // Build INSERT for atenciones dynamically
    $desired = [
        'fecha' => (!empty($_POST['fecha']) ? DateTime::createFromFormat('d-m-Y', $_POST['fecha'])->format('Y-m-d') : ($_POST['fecha'] ?? null)),
        'registro' => $_POST['registro'] ?? date('YmdHis') . rand(100,999),
        'ambulancia' => $_POST['ambulancia'] ?? null,
        'servicio' => $_POST['servicio'] ?? null,
        'pagador' => $_POST['pagador'] ?? null,
        'tipo_traslado' => $_POST['tipo_traslado'] ?? 'Sencillo',
        'quien_informo' => $_POST['quien_informo'] ?? null,
        'finalidad_servicio' => $_POST['finalidad_servicio'] ?? null,
        'causa_externa' => $_POST['causa_externa'] ?? null,
        'hora_despacho' => $_POST['hora_despacho'] ?? null,
        'hora_llegada' => $_POST['hora_llegada'] ?? null,
        'hora_ingreso' => $_POST['hora_ingreso'] ?? null,
        'hora_final' => $_POST['hora_final'] ?? null,
        'conductor' => $_POST['conductor'] ?? ($_POST['conductor_display'] ?? null),
        'cc_conductor' => $_POST['cc_conductor'] ?? null,
        'tripulante' => $_POST['tripulante_hidden'] ?? $_POST['tripulante'] ?? null,
        'cc_tripulante' => $_POST['cc_tripulante'] ?? null,
        'tipo_id_tripulante' => $_POST['tipo_id_tripulante'] ?? null,
        'medico_tripulante' => $_POST['medico_tripulante'] ?? null,
        'cc_medico' => $_POST['cc_medico'] ?? null,
        'tipo_id_medico' => $_POST['tipo_id_medico'] ?? null,
        'direccion_servicio' => $_POST['direccion_servicio'] ?? null,
        'localizacion' => $_POST['localizacion'] ?? null,
        'atencion_en' => $_POST['atencion_en'] ?? null,
        'etnia' => (($_POST['etnia'] ?? '') === 'Otro') ? ('Otro: ' . trim($_POST['especificar_otra'] ?? '')) : ($_POST['etnia'] ?? null),
        'discapacidad' => $_POST['discapacidad'] ?? null,
        'downton_total' => $_POST['downton_total'] ?? null,
        'oxigeno_dispositivo' => $_POST['oxigeno_dispositivo'] ?? null,
        'oxigeno_flujo' => $_POST['oxigeno_flujo'] ?? null,
        'oxigeno_fio2' => $_POST['oxigeno_fio2'] ?? null,
        'aseguradora_soat' => $aseguradora_soat ?: ($_POST['eps_nombre'] ?? null),
        'ips2' => $_POST['ips2'] ?? null,
        'ips3' => $_POST['ips3'] ?? null,
        'ips4' => $_POST['ips4'] ?? null,
        'eps_nombre' => $_POST['eps_nombre'] ?? null,
        'nombres_paciente' => $_POST['nombres_paciente'] ?? null,
        'primer_nombre_paciente' => $campos['primer_nombre_paciente'],
        'segundo_nombre_paciente' => $campos['segundo_nombre_paciente'],
        'primer_apellido_paciente' => $campos['primer_apellido_paciente'],
        'segundo_apellido_paciente' => $campos['segundo_apellido_paciente'],
        'tipo_identificacion' => $_POST['tipo_identificacion'] ?? null,
        'id_paciente' => $_POST['id_paciente'] ?? null,
        'genero_nacer' => $_POST['genero_nacer'] ?? null,
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
        'tipo_usuario' => $_POST['tipo_usuario'] ?? null,
        'tipo_afiliacion' => $_POST['tipo_afiliacion'] ?? null,
        'numero_afiliacion' => $_POST['numero_afiliacion'] ?? null,
        'estado_afiliacion' => $_POST['estado_afiliacion'] ?? null,
        'escena_paciente' => (($_POST['escena_paciente'] ?? '') === 'Otro') ? ('Otro: ' . trim($_POST['escena_paciente_otro'] ?? '')) : ($_POST['escena_paciente'] ?? null),
        'direccion_domicilio' => $_POST['direccion_domicilio'] ?? null,
        'telefono_paciente' => $_POST['telefono_paciente'] ?? null,
        'municipio' => $_POST['municipio_paciente'] ?? $_POST['nombre_municipio_paciente'] ?? $_POST['municipio'] ?? null,
        'cod_departamento_residencia' => $_POST['cod_departamento_residencia'] ?? null,
        'cod_municipio_residencia' => $_POST['cod_municipio_residencia'] ?? null,
        'barrio_paciente' => $_POST['barrio_paciente'] ?? null,
        // Acompañante
        'hay_acompanante' => $_POST['hay_acompanante'] ?? null,
        'nombre_acompanante' => $_POST['nombre_acompanante'] ?? null,
        'parentesco_acompanante' => $_POST['parentesco_acompanante'] ?? null,
        'id_acompanante' => $_POST['id_acompanante'] ?? null,
        'frecuencia_cardiaca' => $_POST['frecuencia_cardiaca'] ?? null,
        'frecuencia_respiratoria' => $_POST['frecuencia_respiratoria'] ?? null,
        'spo2' => $_POST['spo2'] ?? null,
        'tension_arterial' => $_POST['tension_arterial'] ?? null,
        'glucometria' => $_POST['glucometria'] ?? null,
        'temperatura' => $_POST['temperatura'] ?? null,
        'rh' => $_POST['rh'] ?? null,
        'llenado_capilar' => $_POST['llenado_capilar'] ?? null,
        'peso' => $_POST['peso'] ?? null,
        'talla' => $_POST['talla'] ?? null,
        'examen_fisico' => $_POST['examen_fisico'] ?? null,
        'procedimientos' => $_POST['procedimientos'] ?? null,
        'consumo_servicio' => $_POST['consumo_servicio'] ?? null,
        'escala_glasgow' => $_POST['escala_glasgow'] ?? null,
        'nit_ips_receptora' => $_POST['ips_nit'] ?? $_POST['nit_ips_receptora'] ?? null,
        'nombre_ips_receptora' => $_POST['ips_nombre'] ?? $_POST['nombre_ips_receptora'] ?? null,
        'codigo_municipio_servicio' => $_POST['codigo_municipio_servicio'] ?? null,
        'codigo_departamento_servicio' => $_POST['codigo_departamento_servicio'] ?? null,
        'codigo_municipio' => $_POST['codigo_municipio'] ?? null,
        'diagnostico_principal' => $_POST['diagnostico_principal'] ?? null,
        'motivo_traslado' => $_POST['motivo_traslado'] ?? null,
        'conductor_accidente' => $_POST['conductor_accidente'] ?? null,
        'documento_conductor_accidente' => $_POST['documento_conductor_accidente'] ?? null,
        'tarjeta_propiedad_accidente' => $_POST['tarjeta_propiedad_accidente'] ?? null,
        'placa_vehiculo_involucrado' => $_POST['placa_vehiculo_involucrado'] ?? null,
        'placa_vehiculo' => $_POST['placa_paciente'] ?? $_POST['placa_vehiculo_involucrado'] ?? null,
        'nombre_medico_receptor' => $_POST['nombre_medico_receptor'] ?? null,
        'id_medico_receptor' => $_POST['id_medico_receptor'] ?? null,
        'tipo_id_medico_receptor' => $_POST['tipo_id_medico_receptor'] ?? null,
    ];

    // Antecedentes *_sn y *_cual
    foreach (['patologicos','quirurgicos','alergicos','familiares','toxicologicos','ginecoobstetricos'] as $key) {
        $sn_key = 'ant_' . $key . '_sn';
        $cual_key = 'ant_' . $key . '_cual';
        $desired[$sn_key] = $_POST[$sn_key] ?? 'No';
        if (($desired[$sn_key] ?? 'No') === 'Si') {
            $val = $_POST['ant_' . $key . '_cual'] ?? null;
            if (is_array($val)) {
                $otroIdx = array_search('Otro', $val);
                $otroTxt = trim($_POST['ant_' . $key . '_cual_otro'] ?? '');
                if ($otroIdx !== false) {
                    if ($otroTxt) $val[$otroIdx] = 'Otro: ' . $otroTxt; else unset($val[$otroIdx]);
                }
                $desired[$cual_key] = implode(', ', $val);
            } else {
                $desired[$cual_key] = $val;
            }
        } else {
            $desired[$cual_key] = null;
        }
    }

    // Filter by existing columns
    $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atenciones'");
    if (!$schemaStmt) throw new Exception('Error consultando esquema atenciones: ' . $conn->error);
    $schemaStmt->execute();
    $resCols = $schemaStmt->get_result();
    $existing = [];
    while ($r = $resCols->fetch_assoc()) { $existing[] = $r['COLUMN_NAME']; }
    $schemaStmt->close();

    $cols = [];$ph=[];$vals=[];
    foreach ($desired as $c=>$v) { if (in_array($c,$existing) && $v !== null) { $cols[]=$c; $ph[]='?'; $vals[]=$v; }}
    if (empty($cols)) throw new Exception('No hay datos válidos para insertar.');

    $sql = 'INSERT INTO atenciones (' . implode(',',$cols) . ') VALUES (' . implode(',',$ph) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Error preparando inserción en atenciones: ' . $conn->error);
    $types = str_repeat('s', count($vals));
    $bind = [];$bind[]=&$types; for($i=0;$i<count($vals);$i++) { $bind[]=&$vals[$i]; }
    call_user_func_array([$stmt,'bind_param'],$bind);
    if (!$stmt->execute()) throw new Exception('Error al insertar atenciones: ' . $stmt->error);
    $id = $conn->insert_id;
    $stmt->close();

    // Insert signatures into atenciones_sig (row per signature)
    $sigMap = [
        'paramedico' => ($_POST['FirmaParamedicoData'] ?? $_POST['firma_paramedico'] ?? null),
        'medico' => ($_POST['FirmaMedicoData'] ?? $_POST['firma_medico'] ?? null),
        'paciente' => ($_POST['FirmaPacienteData'] ?? $_POST['firma_paciente'] ?? null),
        'medico_receptor' => ($_POST['FirmaMedicoReceptorData'] ?? $_POST['firma_medico_receptor'] ?? null),
        'desistimiento' => ($_POST['FirmaDesistimientoData'] ?? $_POST['firma_desistimiento'] ?? null),
        // receptor IPS puede llamarse receptor_ips o representante_legal según UI
        'representante_legal' => ($_POST['FirmaReceptorIPSData'] ?? $_POST['firma_receptor_ips'] ?? $_POST['firma_receptor_admin_ips'] ?? null)
    ];
    $stmtSig = $conn->prepare('INSERT INTO atenciones_sig (atencion_id, tipo_firma, contenido, nombre_firmante, cargo_firmante) VALUES (?, ?, ?, NULL, NULL)');
    if ($stmtSig) {
        foreach ($sigMap as $tipo=>$contenido) {
            if (empty($contenido)) continue;
            // Guardar como data URL para compatibilidad con generador de PDF
            $dataUrl = (strpos($contenido,'data:')===0) ? $contenido : ('data:image/jpeg;base64,' . base64_encode($contenido));
            if (empty($dataUrl)) continue;
            $stmtSig->bind_param('iss', $id, $tipo, $dataUrl);
            $stmtSig->execute();
        }
        $stmtSig->close();
    }

    // Attachments -> atenciones_att
    if (!empty($_FILES['adjuntos']['name'][0])) {
        $schemaStmt = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atenciones_att'");
        if (!$schemaStmt) throw new Exception('Error consultando esquema atenciones_att: ' . $conn->error);
        $schemaStmt->execute();
        $resA = $schemaStmt->get_result();
        $attCols = [];
        while ($r=$resA->fetch_assoc()) { $attCols[]=$r['COLUMN_NAME']; }
        $schemaStmt->close();

        for ($i=0; $i<count($_FILES['adjuntos']['name']); $i++) {
            $filename = $_FILES['adjuntos']['name'][$i];
            $tmp = $_FILES['adjuntos']['tmp_name'][$i];
            $mime = $_FILES['adjuntos']['type'][$i];
            if (!is_uploaded_file($tmp)) continue;
            $bin = v4_optimize_to_binary($tmp, $mime);
            if ($bin === null) $bin = file_get_contents($tmp);
            if (empty($bin)) continue;

            $data = [
                'atencion_id' => $id,
                'tipo_adjunto' => $mime,
                'contenido' => $bin,
                'nombre_archivo' => $filename
            ];
            $colsI=[];$phI=[];$valsI=[]; foreach ($data as $c=>$v) { if (in_array($c,$attCols) && $v !== null) { $colsI[]=$c;$phI[]='?';$valsI[]=$v; } }
            if ($colsI) {
                $sqlI='INSERT INTO atenciones_att (' . implode(',',$colsI) . ') VALUES (' . implode(',',$phI) . ')';
                $st=$conn->prepare($sqlI); if ($st){ $types=str_repeat('s',count($valsI)); $bind=[];$bind[]=&$types; for($k=0;$k<count($valsI);$k++){ $bind[]=&$valsI[$k]; } call_user_func_array([$st,'bind_param'],$bind); $st->execute(); $st->close(); }
            }
        }
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Atención procesada correctamente.';
    $response['id'] = $id;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) { try { $conn->rollback(); } catch (Throwable $e2) {} }
    http_response_code(200);
    $response['success'] = false;
    $response['message'] = 'Error al procesar la atención: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

?>
