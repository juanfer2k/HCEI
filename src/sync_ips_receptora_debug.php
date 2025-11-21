<?php
// sync_ips_receptora_debug.php
// Versión con debug para identificar el error 500 en live
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
echo "<h2>Diagnóstico de Sincronización IPS Receptora</h2>";

// Test 1: Verificar que conn.php existe
echo "<h3>1. Verificando conn.php...</h3>";
if (file_exists(__DIR__ . '/conn.php')) {
    echo "<p style='color:green;'>✅ conn.php encontrado</p>";
    require_once __DIR__ . '/conn.php';
    
    // Test 2: Verificar conexión a BD
    echo "<h3>2. Verificando conexión a BD...</h3>";
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            echo "<p style='color:red;'>❌ Error de conexión: " . $conn->connect_error . "</p>";
            die();
        }
        echo "<p style='color:green;'>✅ Conexión exitosa a: " . $conn->host_info . "</p>";
        
        // Test 3: Verificar estructura de tabla
        echo "<h3>3. Verificando estructura de tabla ips_receptora...</h3>";
        $result = $conn->query("DESCRIBE ips_receptora");
        if ($result) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['Field'] . "</td>";
                echo "<td>" . $row['Type'] . "</td>";
                echo "<td>" . $row['Null'] . "</td>";
                echo "<td>" . $row['Key'] . "</td>";
                echo "</tr>";
                $columns[] = $row['Field'];
            }
            echo "</table>";
            
            // Verificar columnas requeridas
            echo "<h3>4. Verificando columnas requeridas...</h3>";
            $required = ['id', 'codigo_habilitacion', 'ips_nit', 'ips_nombre', 'ips_ciudad', 
                        'depa_nombre', 'muni_nombre', 'nombre_prestador'];
            $missing = [];
            foreach ($required as $col) {
                if (!in_array($col, $columns)) {
                    $missing[] = $col;
                }
            }
            
            if (empty($missing)) {
                echo "<p style='color:green;'>✅ Todas las columnas básicas existen</p>";
                echo "<p><strong>Total de columnas:</strong> " . count($columns) . "</p>";
            } else {
                echo "<p style='color:red;'>❌ Columnas faltantes:</p>";
                echo "<ul>";
                foreach ($missing as $col) {
                    echo "<li style='color:red;'>$col</li>";
                }
                echo "</ul>";
                echo "<p><strong>SOLUCIÓN:</strong> Ejecuta el script SQL de actualización de esquema en phpMyAdmin</p>";
            }
            
        } else {
            echo "<p style='color:red;'>❌ Error al consultar tabla: " . $conn->error . "</p>";
        }
        
        // Test 4: Probar API
        echo "<h3>5. Probando conexión a API REPS...</h3>";
        $testUrl = 'https://www.datos.gov.co/resource/ugc5-acjp.json?$limit=1';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            echo "<p style='color:green;'>✅ API accesible (HTTP $httpCode)</p>";
            $data = json_decode($json, true);
            if (is_array($data) && count($data) > 0) {
                echo "<p style='color:green;'>✅ Datos recibidos correctamente</p>";
                echo "<p>Ejemplo de registro:</p>";
                echo "<pre>" . print_r($data[0], true) . "</pre>";
            }
        } else {
            echo "<p style='color:red;'>❌ Error al acceder API (HTTP $httpCode)</p>";
        }
        
    } else {
        echo "<p style='color:red;'>❌ Variable \$conn no está definida o no es mysqli</p>";
    }
    
} else {
    echo "<p style='color:red;'>❌ conn.php NO encontrado en: " . __DIR__ . "</p>";
    echo "<p><strong>SOLUCIÓN:</strong> Sube el archivo conn.php al servidor</p>";
}

echo "<hr>";
echo "<h3>Información del Sistema</h3>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Directorio actual:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";

echo "</body></html>";
?>
