<?php
// =========================================================
// procesar_atencion.php — versión final
// Estructura dividida: atenciones / atenciones_sig / atenciones_att
// Guarda adjuntos y firmas directamente en la BD
// Usa transacciones MySQLi y registro de errores en ./error_log.txt
// =========================================================

error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

require_once 'conn.php'; // tu conexión mysqli $conn

$conn->begin_transaction();

try {
    // =========================================================
    // 1️⃣ Inserción principal en atenciones
    // =========================================================
    $stmt = $conn->prepare("INSERT INTO atenciones (
        registro, fecha, ambulancia, servicio, pagador, tipo_traslado,
        quien_informo, hora_despacho, hora_llegada, hora_ingreso, hora_final,
        conductor, cc_conductor, tripulante, cc_tripulante, tipo_id_tripulante,
        medico_tripulante, cc_medico, tipo_id_medico,
        direccion_servicio, localizacion,
        aseguradora_soat, ips2, ips3, ips4, eps_nombre, nombres_paciente,
        tipo_identificacion, id_paciente, genero_nacer, fecha_nacimiento,
        escena_paciente, direccion_domicilio, telefono_paciente, barrio_paciente,
        antecedentes, frecuencia_cardiaca, frecuencia_respiratoria, spo2,
        tension_arterial, glucometria, temperatura, rh, llenado_capilar,
        peso, talla, examen_fisico, procedimientos, consumo_servicio,
        escala_glasgow,
        nit_ips_receptora,
        codigo_municipio_servicio, codigo_municipio
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    if (!$stmt) {
        throw new Exception("Error preparando inserción en atenciones: " . $conn->error);
    }

    // Vincular parámetros dinámicamente (ajusta los tipos según corresponda)
    $stmt->bind_param(
        str_repeat("s", 52),
        $_POST['registro'], $_POST['fecha'], $_POST['ambulancia'], $_POST['servicio'], $_POST['pagador'],
        $_POST['tipo_traslado'], $_POST['quien_informo'], $_POST['hora_despacho'], $_POST['hora_llegada'],
        $_POST['hora_ingreso'], $_POST['hora_final'], $_POST['conductor'], $_POST['cc_conductor'],
        $_POST['tripulante_hidden'], $_POST['cc_tripulante'], $_POST['tipo_id_tripulante'], $_POST['medico_tripulante'],
        $_POST['cc_medico'], $_POST['tipo_id_medico'], $_POST['direccion_servicio'],
        $_POST['localizacion'],
        $_POST['aseguradora_soat'], $_POST['ips2'], $_POST['ips3'], $_POST['ips4'], $_POST['eps_nombre'],
        $_POST['nombres_paciente'], $_POST['tipo_identificacion'], $_POST['id_paciente'], $_POST['genero_nacer'],
        $_POST['fecha_nacimiento'], $_POST['escena_paciente'], $_POST['direccion_domicilio'],
        $_POST['telefono_paciente'], $_POST['barrio_paciente'], $_POST['antecedentes'], $_POST['frecuencia_cardiaca'],
        $_POST['frecuencia_respiratoria'], $_POST['spo2'], $_POST['tension_arterial'],
        $_POST['glucometria'], $_POST['temperatura'], $_POST['rh'], $_POST['llenado_capilar'],
        $_POST['peso'], $_POST['talla'], $_POST['examen_fisico'], $_POST['procedimientos'],
        $_POST['consumo_servicio'], $_POST['escala_glasgow'],
        $_POST['ips_nit'], $_POST['codigo_municipio_servicio'], $_POST['codigo_municipio'] // Usamos ips_nit del POST, pero lo insertamos en nit_ips_receptora
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al insertar en atenciones: " . $stmt->error);
    }

    $id_atencion = $conn->insert_id;
    $stmt->close();

    // =========================================================
    // 2️⃣ Inserción de firmas (atenciones_sig)
    // =========================================================
    $stmt_sig = $conn->prepare("INSERT INTO atenciones_sig (
        id_atencion, firma_paramedico, firma_medico, firma_paciente,
        firma_medico_receptor, firma_desistimiento, firma_receptor_admin_ips
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt_sig) {
        throw new Exception("Error preparando inserción en atenciones_sig: " . $conn->error);
    }

    $stmt_sig->bind_param(
        "issssss",
        $id_atencion,
        $_POST['firma_paramedico'],
        $_POST['firma_medico'],
        $_POST['firma_paciente'],
        $_POST['firma_medico_receptor'],
        $_POST['firma_desistimiento'],
        $_POST['firma_receptor_admin_ips']
    );

    if (!$stmt_sig->execute()) {
        throw new Exception("Error al insertar en atenciones_sig: " . $stmt_sig->error);
    }

    $stmt_sig->close();

    // =========================================================
    // 3️⃣ Inserción de adjuntos (atenciones_att)
    // =========================================================
    $stmt_att = $conn->prepare("INSERT INTO atenciones_att (id_atencion, adjuntos) VALUES (?, ?)");

    if (!$stmt_att) {
        throw new Exception("Error preparando inserción en atenciones_att: " . $conn->error);
    }

    $stmt_att->bind_param("is", $id_atencion, $_POST['adjuntos']);

    if (!$stmt_att->execute()) {
        throw new Exception("Error al insertar en atenciones_att: " . $stmt_att->error);
    }

    $stmt_att->close();

    // =========================================================
    // 4️⃣ Confirmar transacción
    // =========================================================
    $conn->commit();
    echo "✅ Registro guardado exitosamente.";

} catch (Exception $e) {
    $conn->rollback();
    error_log("[" . date("Y-m-d H:i:s") . "] " . $e->getMessage() . "\n", 3, __DIR__ . '/error_log.txt');
    echo "❌ Ocurrió un error al guardar. Revisa error_log.txt para más detalles.";
}

$conn->close();
?>
