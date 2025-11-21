<?php
// sync_ips_receptora.php
// Sincroniza tabla ips_receptora con la API de prestadores habilitados (REPS)
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0); // Sin límite de tiempo
ini_set('max_execution_time', 0);

// Forzar output inmediato
if (ob_get_level()) ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no'); // Nginx
ob_implicit_flush(true);

require_once __DIR__ . '/conn.php';

// Opcional: App Token de datos.gov.co
$appToken = '';

$baseUrl = 'https://www.datos.gov.co/resource/ugc5-acjp.json';
$limit   = 100; // Reducido para mejor feedback
$offset  = 0;
$totalProcessed = 0;
$totalErrors = 0;

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;}</style>";
echo "</head><body>";
echo "<h2>🔄 Sincronización IPS Receptora - REPS</h2>";
echo "<p>Iniciando sincronización...</p>";
flush();

// Helper function para escapar valores
function escapeValue($conn, $value) {
    if ($value === null) {
        return 'NULL';
    }
    return "'" . $conn->real_escape_string((string)$value) . "'";
}

$pageNum = 0;
do {
    $pageNum++;
    // Construir URL de la página actual
    $url = $baseUrl . '?$limit=' . $limit . '&$offset=' . $offset;
    echo "<p><strong>📄 Página $pageNum (offset: $offset):</strong> Consultando API...</p>";
    flush();

    $headers = [];
    if ($appToken) {
        $headers[] = "X-App-Token: $appToken";
    }

    // --- Llamada HTTP usando cURL ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 segundos timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    // Configuración SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $json = curl_exec($ch);
    if ($json === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        echo "<p class='error'>❌ Error cURL ($code): $err</p>";
        curl_close($ch);
        break;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode < 200 || $httpCode >= 300) {
        echo "<p class='error'>❌ Respuesta HTTP $httpCode</p>";
        curl_close($ch);
        break;
    }

    curl_close($ch);

    $data = json_decode($json, true);
    if (!is_array($data) || count($data) === 0) {
        echo "<p class='success'>✅ No hay más registros. Fin de la sincronización.</p>";
        break;
    }

    $recordsInPage = count($data);
    echo "<p>📥 Recibidos <strong>$recordsInPage</strong> registros. Procesando...</p>";
    flush();

    $processedInPage = 0;
    $errorsInPage = 0;

    foreach ($data as $row) {
        $codigo_habilitacion = $row['codigo_habilitacion'] ?? '';
        if ($codigo_habilitacion === '') {
            continue;
        }

        // Campos NOT NULL en la tabla
        $ips_nit         = $row['nit']              ?? '';
        $dv              = $row['dv']               ?? null;
        $ips_nombre      = $row['nombre']           ?? '';

        $nombre_prestador= $row['nombre_prestador'] ?? null;
        $depa_nombre     = $row['departamento']     ?? null;
        $muni_nombre     = $row['municipio']        ?? null;
        $ips_ciudad      = $muni_nombre ?? '';

        $tido_codigo     = $row['tido_codigo']      ?? null;
        $nits_nit        = $row['nits_nit']         ?? null;
        $razon_social    = $row['razon_social']     ?? null;
        $clpr_codigo     = $row['clpr_codigo']      ?? null;
        $clpr_nombre     = $row['clpr_nombre']      ?? null;
        $ese             = isset($row['ese']) ? (int)$row['ese'] : null;
        $direccion       = $row['direccion']        ?? null;
        $telefono        = $row['telefono']         ?? null;
        $fax             = $row['fax']              ?? null;
        $email           = $row['email']            ?? null;
        $gerente         = $row['gerente']          ?? null;
        $nivel           = $row['nivel']            ?? null;
        $caracter        = $row['caracter']         ?? null;
        $habilitado      = isset($row['habilitado']) ? (int)$row['habilitado'] : null;
        $fecha_radicacion    = $row['fecha_radicacion']    ?? null;
        $fecha_vencimiento   = $row['fecha_vencimiento']   ?? null;
        $fecha_cierre        = $row['fecha_cierre']        ?? null;
        $clase_persona       = $row['clase_persona']       ?? null;
        $naju_codigo         = $row['naju_codigo']         ?? null;
        $naju_nombre         = $row['naju_nombre']         ?? null;
        $numero_sede_principal = isset($row['numero_sede_principal']) ? (int)$row['numero_sede_principal'] : null;
        $fecha_corte_REPS      = $row['fecha_corte_reps'] ?? null;

        // Normalizar fechas
        foreach (['fecha_radicacion','fecha_vencimiento','fecha_cierre','fecha_corte_REPS'] as $campoFecha) {
            if (!empty($$campoFecha)) {
                $ts = strtotime($$campoFecha);
                $$campoFecha = $ts ? date('Y-m-d', $ts) : null;
            }
        }

        $sql = "INSERT INTO ips_receptora (
              codigo_habilitacion, ips_nit, ips_nombre, ips_ciudad,
              depa_nombre, muni_nombre, nombre_prestador, tido_codigo,
              nits_nit, razon_social, clpr_codigo, clpr_nombre,
              ese, direccion, telefono, fax, email, gerente,
              nivel, caracter, habilitado, fecha_radicacion,
              fecha_vencimiento, fecha_cierre, dv, clase_persona,
              naju_codigo, naju_nombre, numero_sede_principal, fecha_corte_REPS
          ) VALUES (
              " . escapeValue($conn, $codigo_habilitacion) . ",
              " . escapeValue($conn, $ips_nit) . ",
              " . escapeValue($conn, $ips_nombre) . ",
              " . escapeValue($conn, $ips_ciudad) . ",
              " . escapeValue($conn, $depa_nombre) . ",
              " . escapeValue($conn, $muni_nombre) . ",
              " . escapeValue($conn, $nombre_prestador) . ",
              " . escapeValue($conn, $tido_codigo) . ",
              " . escapeValue($conn, $nits_nit) . ",
              " . escapeValue($conn, $razon_social) . ",
              " . escapeValue($conn, $clpr_codigo) . ",
              " . escapeValue($conn, $clpr_nombre) . ",
              " . escapeValue($conn, $ese) . ",
              " . escapeValue($conn, $direccion) . ",
              " . escapeValue($conn, $telefono) . ",
              " . escapeValue($conn, $fax) . ",
              " . escapeValue($conn, $email) . ",
              " . escapeValue($conn, $gerente) . ",
              " . escapeValue($conn, $nivel) . ",
              " . escapeValue($conn, $caracter) . ",
              " . escapeValue($conn, $habilitado) . ",
              " . escapeValue($conn, $fecha_radicacion) . ",
              " . escapeValue($conn, $fecha_vencimiento) . ",
              " . escapeValue($conn, $fecha_cierre) . ",
              " . escapeValue($conn, $dv) . ",
              " . escapeValue($conn, $clase_persona) . ",
              " . escapeValue($conn, $naju_codigo) . ",
              " . escapeValue($conn, $naju_nombre) . ",
              " . escapeValue($conn, $numero_sede_principal) . ",
              " . escapeValue($conn, $fecha_corte_REPS) . "
          )
          ON DUPLICATE KEY UPDATE
              ips_nit=VALUES(ips_nit), ips_nombre=VALUES(ips_nombre),
              ips_ciudad=VALUES(ips_ciudad), depa_nombre=VALUES(depa_nombre),
              muni_nombre=VALUES(muni_nombre), nombre_prestador=VALUES(nombre_prestador),
              habilitado=VALUES(habilitado), fecha_vencimiento=VALUES(fecha_vencimiento)
        ";

        if ($conn->query($sql)) {
            $processedInPage++;
        } else {
            $errorsInPage++;
            if ($errorsInPage <= 3) { // Solo mostrar primeros 3 errores
                echo "<p class='warning'>⚠️ Error: {$conn->error}</p>";
            }
        }
    }

    $totalProcessed += $processedInPage;
    $totalErrors += $errorsInPage;
    
    echo "<p class='success'>✅ Página $pageNum: <strong>$processedInPage</strong> registros guardados";
    if ($errorsInPage > 0) {
        echo " | <span class='warning'>$errorsInPage errores</span>";
    }
    echo " | <strong>Total: $totalProcessed</strong></p>";
    flush();

    $offset += $limit;
    
    // Pausa breve para no saturar
    usleep(100000); // 0.1 segundos

} while (true);

echo "<hr>";
echo "<h3 class='success'>✅ Sincronización completada</h3>";
echo "<p>📊 <strong>Total procesados:</strong> $totalProcessed registros</p>";
if ($totalErrors > 0) {
    echo "<p class='warning'>⚠️ <strong>Total errores:</strong> $totalErrors</p>";
    echo "<p><em>Nota: Los errores suelen ser por registros duplicados o campos faltantes en la BD.</em></p>";
}
echo "</body></html>";
?>