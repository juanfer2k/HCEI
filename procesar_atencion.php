<?php
require 'conn.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar la sesión para poder pasar mensajes entre páginas
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$tripulante_lookup = [];
if (!empty($_SESSION['usuario_id'])) {
    $stmt_tripulante = $conn->prepare("SELECT nombres, apellidos, id_cc, id_registro FROM tripulacion WHERE id = ?");
    if ($stmt_tripulante) {
        $stmt_tripulante->bind_param('i', $_SESSION['usuario_id']);
        if ($stmt_tripulante->execute()) {
            if (function_exists('mysqli_stmt_get_result')) {
                $res_trip = mysqli_stmt_get_result($stmt_tripulante);
                if ($res_trip instanceof mysqli_result && $res_trip->num_rows === 1) {
                    $tripulante_lookup = $res_trip->fetch_assoc() ?: [];
                }
            } else {
                $stmt_tripulante->bind_result($nombre_trip, $apellido_trip, $cc_trip, $registro_trip);
                if ($stmt_tripulante->fetch()) {
                    $tripulante_lookup = [
                        'nombres' => $nombre_trip,
                        'apellidos' => $apellido_trip,
                        'id_cc' => $cc_trip,
                        'id_registro' => $registro_trip,
                    ];
                }
            }
        }
        $stmt_tripulante->close();
    }
}

if (empty($tripulante_lookup) && !empty($_SESSION['usuario_nombre'])) {
    $tripulante_lookup = [
        'nombres' => $_SESSION['usuario_nombre'],
        'apellidos' => $_SESSION['usuario_apellidos'] ?? '',
        'id_cc' => $_SESSION['usuario_cc'] ?? '',
        'id_registro' => $_SESSION['usuario_registro'] ?? '',
    ];
}
/**
 * Procesa los campos de antecedentes que tienen una opciÃ³n "cual" y un campo de texto para "Otro".
 *
 * @param string $post_key_base La base del nombre del campo (ej. 'ant_alergicos').
 * @param array $post_data El array $_POST completo.
 * @return string|null La cadena de texto procesada y lista para la BD.
 */
function procesar_antecedente_cual($post_key_base, $post_data) {
    $cual_data = $post_data[$post_key_base . '_cual'] ?? null;
    
    // Maneja campos que no son de selecciÃ³n mÃºltiple (ej. ginecoobstÃ©tricos)
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
            // Si 'Otro' fue seleccionado pero no se especificÃ³ nada, se elimina.
            unset($cual_array[$otro_key]);
        }
    }

    return implode(', ', $cual_array);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registro = $_POST['registro'] ?? null;
    $fecha = $_POST['fecha'] ?? null;
    
    if (!empty($fecha)) {
        $fecha = DateTime::createFromFormat('d-m-Y', $fecha)->format('Y-m-d');
    }

    if (empty($registro)) {
        $_SESSION['message'] = '<div class="alert alert-danger">Error: El campo "# de Registro" es obligatorio.</div>';
        header('Location: index.php');
        exit;
    }

    $ambulancia = $_POST['ambulancia'] ?? null;
    $servicio = $_POST['servicio'] ?? null;
    $pagador = $_POST['pagador'] ?? null;
    $quien_informo = $_POST['quien_informo'] ?? null;
    $hora_despacho = $_POST['hora_despacho'] ?? null;
    $hora_llegada = $_POST['hora_llegada'] ?? null;
    $hora_ingreso = $_POST['hora_ingreso'] ?? null;
    $hora_final = $_POST['hora_final'] ?? null;
    $conductor = $_POST['conductor'] ?? null;
    $cc_conductor = $_POST['cc_conductor'] ?? null;
    $tripulante = $_POST['tripulante_hidden'] ?? null;
    // Normalizar tripulante desde el hidden; tolerar formatos atípicos
    $tripulante = isset($_POST['tripulante_hidden']) ? trim((string)$_POST['tripulante_hidden']) : null;

    // Fallbacks para NO NULL
    if ($tripulante === null || $tripulante === '') {
        $tripNombre = $tripulante_lookup['nombres'] ?? null;
        $tripApellidos = $tripulante_lookup['apellidos'] ?? null;
        if (!empty($tripNombre) || !empty($tripApellidos)) {
            $tripulante = trim(($tripNombre ?? '') . ' ' . ($tripApellidos ?? ''));
        }
    }

    if ($tripulante === null || $tripulante === '') {
        $tripulante = 'Tripulante';
        $registroRef = isset($registro) && $registro !== null ? $registro : 'desconocido';
        error_log('HCEI: tripulante vacío; se aplicó placeholder en procesar_atencion.php para registro ' . $registroRef);
    }
    $tipo_id_tripulante = isset($_POST['tipo_id_tripulante']) ? trim((string)$_POST['tipo_id_tripulante']) : null;
    $cc_tripulante = isset($_POST['cc_tripulante']) ? trim((string)$_POST['cc_tripulante']) : null;
    if (($cc_tripulante === null || $cc_tripulante === '') && !empty($tripulante_lookup)) {
        $prefer_id = $tripulante_lookup['id_registro'] ?? '';
        if ($prefer_id === '' && !empty($tripulante_lookup['id_cc'])) {
            $prefer_id = $tripulante_lookup['id_cc'];
        }
        $cc_tripulante = $prefer_id !== '' ? $prefer_id : $cc_tripulante;
    }
    if (($tipo_id_tripulante === null || $tipo_id_tripulante === '') && !empty($tripulante_lookup)) {
        if (!empty($tripulante_lookup['id_registro'])) {
            $tipo_id_tripulante = 'Registro';
        } elseif (!empty($tripulante_lookup['id_cc'])) {
            $tipo_id_tripulante = 'CC';
        }
    }
    $medico_tripulante = $_POST['medico_tripulante'] ?? null;
    $tipo_id_medico = isset($_POST['tipo_id_medico']) ? substr($_POST['tipo_id_medico'], 0, 2) : null;
    $cc_medico = $_POST['cc_medico'] ?? null;
    $direccion_servicio = $_POST['direccion_servicio'] ?? null;
    $localizacion = $_POST['localizacion'] ?? null;
    $ips_destino = $_POST['ips_destino'] ?? null;
    $tipo_traslado = $_POST['tipo_traslado'] ?? 'Sencillo'; // Valor predeterminado: "Sencillo"
    $aseguradora_soat = $_POST['aseguradora_soat'] ?? null;
    $ips2 = $_POST['ips2'] ?? null;
    $ips3 = $_POST['ips3'] ?? null;
    $ips4 = $_POST['ips4'] ?? null;
    $eps_nombre = $_POST['eps_nombre'] ?? null;
    $nombres_paciente = $_POST['nombres_paciente'] ?? null;
    $tipo_identificacion = isset($_POST['tipo_identificacion']) ? substr($_POST['tipo_identificacion'], 0, 2) : null;
    $id_paciente = $_POST['id_paciente'] ?? null;
    $genero_nacer = $_POST['genero_nacer'] ?? null;
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $direccion_domicilio = $_POST['direccion_domicilio'] ?? null;

    // SoluciÃ³n: Si la fecha de nacimiento viene vacÃ­a, la convertimos a NULL
    $escena_paciente = $_POST['escena_paciente'] ?? null;
    if ($escena_paciente === 'Otro') {
        $escena_paciente_otro = trim($_POST['escena_paciente_otro'] ?? '');
        $escena_paciente = !empty($escena_paciente_otro) ? 'Otro: ' . $escena_paciente_otro : 'Otro';
    } else {
        $escena_paciente = $_POST['escena_paciente'] ?? null;
    }

    // SoluciÃ³n: Si la fecha de nacimiento viene vacÃ­a, la convertimos a NULL
    if (empty($fecha_nacimiento)) {
        $fecha_nacimiento = null;
    }

    $telefono_paciente = $_POST['telefono_paciente'] ?? null;
    $barrio_paciente = $_POST['barrio_paciente'] ?? null;
    $municipio = $_POST['municipio'] ?? null;
    $codigo_municipio = $_POST['codigo_municipio'] ?? null;
    $ciudad = $municipio ?? ($_POST['ciudad'] ?? null);
    $atencion_en = $_POST['atencion_en'] ?? null;
    $etnia = $_POST['etnia'] ?? null;
    $especificar_otra = $_POST['especificar_otra'] ?? null;
    $discapacidad = $_POST['discapacidad'] ?? null;
    $antecedentes = null; // Este campo ya no se usa, se desglosa en los campos ant_*
    $glucometria = isset($_POST['glucometria']) && $_POST['glucometria'] !== '' ? $_POST['glucometria'] : null;
    $frecuencia_cardiaca = isset($_POST['frecuencia_cardiaca']) && $_POST['frecuencia_cardiaca'] !== '' ? $_POST['frecuencia_cardiaca'] : null;
    $frecuencia_respiratoria = isset($_POST['frecuencia_respiratoria']) && $_POST['frecuencia_respiratoria'] !== '' ? $_POST['frecuencia_respiratoria'] : null;
    $spo2 = isset($_POST['spo2']) && $_POST['spo2'] !== '' ? $_POST['spo2'] : null;
    $tension_arterial = $_POST['tension_arterial'] ?? null;
    $temperatura = isset($_POST['temperatura']) && $_POST['temperatura'] !== '' ? $_POST['temperatura'] : null;
    $rh = $_POST['rh'] ?? null;
    $llenado_capilar = $_POST['llenado_capilar'] ?? null;
    $peso = isset($_POST['peso']) && $_POST['peso'] !== '' ? $_POST['peso'] : null;
    $talla = isset($_POST['talla']) && $_POST['talla'] !== '' ? $_POST['talla'] : null;
    $examen_fisico = $_POST['examen_fisico'] ?? null;
    $procedimientos = $_POST['procedimientos'] ?? null;
    $consumo_servicio = $_POST['consumo_servicio'] ?? null;
    $escala_glasgow = isset($_POST['escala_glasgow']) && $_POST['escala_glasgow'] !== '' ? $_POST['escala_glasgow'] : null;
    $firma_paramedico = $_POST['firma_paramedico'] ?? null;
    $firma_medico = $_POST['firma_medico'] ?? null;
    $firma_paciente = $_POST['firma_paciente'] ?? null;
    $nombre_medico_receptor = $_POST['nombre_medico_receptor'] ?? null;
    $tipo_id_medico_receptor = isset($_POST['tipo_id_medico_receptor']) ? substr($_POST['tipo_id_medico_receptor'], 0, 2) : null;
    $id_medico_receptor = $_POST['id_medico_receptor'] ?? null;
    $firma_medico_receptor = $_POST['firma_medico_receptor'] ?? null;
    $firma_desistimiento = $_POST['firma_desistimiento'] ?? null;
    $nombre_ips_receptora = $_POST['nombre_ips_receptora'] ?? null;
    $nit_ips_receptora = $_POST['nit_ips_receptora'] ?? null;

    // Limpiar firmas vacÃ­as para que se guarden como NULL
    $firma_paramedico = empty($firma_paramedico) || $firma_paramedico === 'data:,' ? null : $firma_paramedico;
    $firma_medico = empty($firma_medico) || $firma_medico === 'data:,' ? null : $firma_medico;
    $firma_paciente = empty($firma_paciente) || $firma_paciente === 'data:,' ? null : $firma_paciente;
    $firma_medico_receptor = empty($firma_medico_receptor) || $firma_medico_receptor === 'data:,' ? null : $firma_medico_receptor;
    $firma_desistimiento = empty($firma_desistimiento) || $firma_desistimiento === 'data:,' ? null : $firma_desistimiento;

    // Si hay firma de desistimiento, los campos del mÃ©dico receptor se vuelven nulos.
    if (!empty($firma_desistimiento)) {
        $nombre_medico_receptor = $tipo_id_medico_receptor = $id_medico_receptor = $firma_medico_receptor = $nombre_ips_receptora = $nit_ips_receptora = null;
    }

    $nombre_acompanante = $_POST['nombre_acompanante'] ?? null;
    $parentesco_acompanante = $_POST['parentesco_acompanante'] ?? null;
    $id_acompanante = $_POST['id_acompanante'] ?? null;
    $motivo_traslado = $_POST['motivo_traslado'] ?? null;
    $oxigeno_dispositivo = $_POST['oxigeno_dispositivo'] ?? null;
    $oxigeno_flujo = isset($_POST['oxigeno_flujo']) && $_POST['oxigeno_flujo'] !== '' ? $_POST['oxigeno_flujo'] : null;
    $oxigeno_fio2 = isset($_POST['oxigeno_fio2']) && $_POST['oxigeno_fio2'] !== '' ? $_POST['oxigeno_fio2'] : null;

    // El diagnÃ³stico principal solo es vÃ¡lido si el servicio es medicalizado
    $diagnostico_principal = ($servicio === 'Traslado Medicalizado') ? ($_POST['diagnostico_principal'] ?? null) : null;

    $ant_alergicos_sn = $_POST['ant_alergicos_sn'] ?? '';
    $ant_alergicos_cual = procesar_antecedente_cual('ant_alergicos', $_POST);
    $ant_ginecoobstetricos_sn = $_POST['ant_ginecoobstetricos_sn'] ?? '';
    $ant_ginecoobstetricos_cual = $_POST['ant_ginecoobstetricos_cual'] ?? '';
    $ant_patologicos_sn = $_POST['ant_patologicos_sn'] ?? '';
    $ant_patologicos_cual = procesar_antecedente_cual('ant_patologicos', $_POST);
    $ant_quirurgicos_sn = $_POST['ant_quirurgicos_sn'] ?? '';
    $ant_quirurgicos_cual = procesar_antecedente_cual('ant_quirurgicos', $_POST);
    $ant_traumatologicos_sn = $_POST['ant_traumatologicos_sn'] ?? '';
    $ant_traumatologicos_cual = procesar_antecedente_cual('ant_traumatologicos', $_POST);
    $ant_toxicologicos_sn = $_POST['ant_toxicologicos_sn'] ?? '';
    $ant_toxicologicos_cual = procesar_antecedente_cual('ant_toxicologicos', $_POST);
    $ant_familiares_sn = $_POST['ant_familiares_sn'] ?? '';
    $ant_familiares_cual = procesar_antecedente_cual('ant_familiares', $_POST);

    // --- NUEVOS CAMPOS ---
    $downton_total = $_POST['downton_total'] ?? null;

    // Procesar medicamentos
    $medicamentos_aplicados = [];
    if (isset($_POST['medicamento_hora']) && is_array($_POST['medicamento_hora'])) {
        foreach ($_POST['medicamento_hora'] as $key => $hora) {
            if (!empty($hora) || !empty($_POST['medicamento_nombre'][$key])) {
                $medicamentos_aplicados[] = [
                    'hora' => $hora,
                    'nombre' => $_POST['medicamento_nombre'][$key] ?? '',
                    'dosis' => $_POST['medicamento_dosis'][$key] ?? '',
                    'via' => $_POST['medicamento_via'][$key] ?? ''
                ];
            }
        }
    }
    $medicamentos_json = !empty($medicamentos_aplicados) ? json_encode($medicamentos_aplicados) : null;

    /**
     * Optimiza una imagen (redimensiona y comprime) y la convierte a Base64.
     *
     * @param string $tmpName Ruta temporal del archivo subido.
     * @param string $mimeType Tipo MIME del archivo.
     * @param int $maxWidth Ancho mÃ¡ximo deseado para la imagen.
     * @param int $quality Calidad de compresiÃ³n para JPEGs (0-100).
     * @return string|null La imagen en formato Base64 o null si hay un error.
     */
    function optimizar_y_convertir_a_base64($tmpName, $mimeType, $maxWidth = 1024, $quality = 75) {
        // Si es un PDF, solo lo convierte a Base64 sin procesarlo como imagen.
        if ($mimeType === 'application/pdf') {
            $pdfData = file_get_contents($tmpName);
            return 'data:application/pdf;base64,' . base64_encode($pdfData);
        }

        if (!file_exists($tmpName) || filesize($tmpName) == 0) {
            return null;
        }

        list($width, $height) = getimagesize($tmpName);
        if ($width <= $maxWidth) { // Si ya es pequeÃ±a, solo comprime
            $newWidth = $width;
            $newHeight = $height;
        } else { // Si es grande, redimensiona
            $ratio = $width / $height;
            $newWidth = $maxWidth;
            $newHeight = round($maxWidth / $ratio);
        }

        $thumb = imagecreatetruecolor((int)round($newWidth), (int)round($newHeight));
        $source = null;
        if ($mimeType == 'image/jpeg') {
            $source = imagecreatefromjpeg($tmpName);
        } elseif ($mimeType == 'image/png') {
            $source = imagecreatefrompng($tmpName);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        } else {
            return null; // Formato no soportado
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, (int)round($newWidth), (int)round($newHeight), $width, $height);

        ob_start();
        imagejpeg($thumb, null, $quality); // Siempre convierte a JPEG por su alta compresiÃ³n
        $imageData = ob_get_clean();
        
        imagedestroy($thumb);
        imagedestroy($source);

        return 'data:image/jpeg;base64,' . base64_encode($imageData);
    }

    $adjuntos = null;
    if (isset($_FILES['adjuntos']) && $_FILES['adjuntos']['error'][0] === 0) {
        $adjuntosArray = [];
        foreach ($_FILES['adjuntos']['tmp_name'] as $key => $tmpName) {
            $mimeType = $_FILES['adjuntos']['type'][$key];
            $base64Optimizado = optimizar_y_convertir_a_base64($tmpName, $mimeType);
            if ($base64Optimizado) {
                $adjuntosArray[] = $base64Optimizado;
            }
        }
        $adjuntos = json_encode($adjuntosArray);
    }

    $sql = "INSERT INTO atenciones (
        registro, fecha, ambulancia, servicio, pagador, tipo_traslado, quien_informo, hora_despacho, hora_llegada, hora_ingreso, hora_final,
        conductor, cc_conductor, tripulante, cc_tripulante, medico_tripulante, cc_medico, direccion_servicio, localizacion, ips_destino,
        aseguradora_soat, ips2, ips3, ips4, eps_nombre, nombres_paciente, tipo_identificacion, id_paciente, genero_nacer, fecha_nacimiento, escena_paciente,
        direccion_domicilio, telefono_paciente, barrio_paciente, ciudad,
        frecuencia_cardiaca, frecuencia_respiratoria, spo2, tension_arterial, glucometria, temperatura, rh, llenado_capilar,
        peso, talla, examen_fisico, procedimientos, consumo_servicio, escala_glasgow, firma_paramedico, firma_medico, firma_paciente, adjuntos, nombre_acompanante, parentesco_acompanante,
        atencion_en, etnia, especificar_otra, discapacidad, tipo_id_tripulante, tipo_id_medico,
        nombre_medico_receptor, tipo_id_medico_receptor, id_medico_receptor, firma_medico_receptor, nombre_ips_receptora, nit_ips_receptora,
        firma_desistimiento, id_acompanante, diagnostico_principal, motivo_traslado,
        oxigeno_dispositivo, oxigeno_flujo, oxigeno_fio2,
        ant_alergicos_sn, ant_alergicos_cual, ant_ginecoobstetricos_sn, ant_ginecoobstetricos_cual, ant_patologicos_sn, ant_patologicos_cual,
        ant_quirurgicos_sn, ant_quirurgicos_cual, ant_traumatologicos_sn, ant_traumatologicos_cual, ant_toxicologicos_sn, ant_toxicologicos_cual,
        ant_familiares_sn, ant_familiares_cual,
        downton_total, medicamentos_aplicados
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

 //   echo 'Placeholders count: ' . substr_count($sql, '?') . '<br>';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Usar die() puede exponer informaciÃ³n sensible. Mejor registrar el error y mostrar un mensaje genÃ©rico.
        error_log('Error en prepare(): ' . $conn->error);
        $_SESSION['message'] = '<div class="alert alert-danger">OcurriÃ³ un error inesperado al procesar el registro.</div>';
        header('Location: index.php');
        exit;
    }

//    echo 'Variables count: ' . count([
//        $registro, $fecha, $ambulancia, $servicio, $pagador, $quien_informo, $hora_despacho, $hora_llegada, $hora_ingreso, $hora_final,
//        $conductor, $cc_conductor, $tripulante, $cc_tripulante, $medico_tripulante, $cc_medico, $direccion_servicio, $localizacion,
//        $ips_destino, $tipo_traslado, $aseguradora_soat, $ips2, $ips3, $ips4, $nombres_paciente, $tipo_identificacion, $id_paciente,
//        $genero_nacer, $fecha_nacimiento, $direccion_domicilio, $telefono_paciente, $barrio_paciente, $ciudad, $antecedentes,
//        $frecuencia_cardiaca, $frecuencia_respiratoria, $spo2, $tension_arterial, $glucometria, $temperatura, $rh, $llenado_capilar,
//        $peso, $talla, $examen_fisico, $procedimientos, $consumo_servicio, $escala_glasgow, $firma_paramedico, $firma_medico, $firma_paciente, $adjuntos
//    ]) . '<br>';

    $stmt->bind_param(
        str_repeat('s', 90), // 90 campos en total
        $registro, $fecha, $ambulancia, $servicio, $pagador, $tipo_traslado, $quien_informo, $hora_despacho, $hora_llegada, $hora_ingreso, $hora_final,
        $conductor, $cc_conductor, $tripulante, $cc_tripulante, $medico_tripulante, $cc_medico, $direccion_servicio, $localizacion, $ips_destino,
        $aseguradora_soat, $ips2, $ips3, $ips4, $eps_nombre, $nombres_paciente, $tipo_identificacion, $id_paciente,
        $genero_nacer, $fecha_nacimiento, $escena_paciente, $direccion_domicilio, $telefono_paciente, $barrio_paciente, $ciudad,
        $frecuencia_cardiaca, $frecuencia_respiratoria, $spo2, $tension_arterial, $glucometria, $temperatura, $rh, $llenado_capilar,
        $peso, $talla, $examen_fisico, $procedimientos, $consumo_servicio, $escala_glasgow, $firma_paramedico, $firma_medico, $firma_paciente, $adjuntos, $nombre_acompanante, $parentesco_acompanante,
        $atencion_en, $etnia, $especificar_otra, $discapacidad, $tipo_id_tripulante, $tipo_id_medico,
        $nombre_medico_receptor, $tipo_id_medico_receptor, $id_medico_receptor, $firma_medico_receptor, $nombre_ips_receptora, $nit_ips_receptora,
        $firma_desistimiento, $id_acompanante, $diagnostico_principal, $motivo_traslado,
        $oxigeno_dispositivo, $oxigeno_flujo, $oxigeno_fio2,
        $ant_alergicos_sn, $ant_alergicos_cual, $ant_ginecoobstetricos_sn, $ant_ginecoobstetricos_cual, $ant_patologicos_sn, $ant_patologicos_cual,
        $ant_quirurgicos_sn, $ant_quirurgicos_cual, $ant_traumatologicos_sn, $ant_traumatologicos_cual, $ant_toxicologicos_sn, $ant_toxicologicos_cual,
        $ant_familiares_sn, $ant_familiares_cual,
        $downton_total, $medicamentos_json
    );

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;
        // Guardar mensaje de Ã©xito en la sesiÃ³n para mostrarlo en la pÃ¡gina de consulta
        $_SESSION['message'] = '<div class="alert alert-success">Registro #'.$registro.' insertado correctamente. <a href="obtener_detalle_atencion.php?id='.$last_id.'" class="alert-link">Ver Detalle</a> | <a href="generar_pdf.php?id='.$last_id.'" class="alert-link" target="_blank">Generar PDF</a></div>';
    } else {
        // Guardar mensaje de error en la sesiÃ³n
        $_SESSION['message'] = '<div class="alert alert-danger">Error al insertar datos: ' . $stmt->error . '</div>';
    }

    $stmt->close();
    $conn->close();

    // Redirigir a la pÃ¡gina de consulta para mostrar el resultado y la lista actualizada
    header('Location: consulta_atenciones.php');
    exit;
} else {
    // Si no es una solicitud POST, redirigir al formulario principal
    header('Location: index.php');
    exit;
}




