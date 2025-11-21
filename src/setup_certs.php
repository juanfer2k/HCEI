<?php
/**
 * Script para generar certificados auto-firmados para pruebas de firma digital TCPDF.
 * USO: Ejecutar una vez y luego eliminar o proteger.
 */

// Configuración
$certDir = __DIR__ . '/certs';
$certFile = $certDir . '/tcpdf.crt';
$keyFile = $certDir . '/tcpdf.key'; // Clave privada separada (opcional, pero común)
$dn = array(
    "countryName" => "CO",
    "stateOrProvinceName" => "Bogota",
    "localityName" => "Bogota",
    "organizationName" => "HCEI Test",
    "organizationalUnitName" => "IT Dept",
    "commonName" => "HCEI Test Certificate",
    "emailAddress" => "admin@example.com"
);

// Crear directorio si no existe
if (!file_exists($certDir)) {
    mkdir($certDir, 0755, true);
    echo "Directorio 'certs' creado.<br>";
}

// Generar claves
$privkey = openssl_pkey_new(array(
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
));

if (!$privkey) {
    die('Error al generar clave privada. Verifique configuración OpenSSL en PHP.');
}

// Generar CSR
$csr = openssl_csr_new($dn, $privkey, array('digest_alg' => 'sha256'));

// Generar Certificado Auto-firmado (validez 365 días)
$sscert = openssl_csr_sign($csr, null, $privkey, 365, array('digest_alg' => 'sha256'));

// Exportar Clave Privada
openssl_pkey_export($privkey, $pkeyout);
file_put_contents($keyFile, $pkeyout);

// Exportar Certificado
openssl_x509_export($sscert, $certout);
file_put_contents($certFile, $certout);

// Crear .htaccess para protección
$htaccessContent = "Order Deny,Allow\nDeny from all";
file_put_contents($certDir . '/.htaccess', $htaccessContent);

echo "<h3>Certificados Generados Exitosamente</h3>";
echo "Ubicación: " . $certDir . "<br>";
echo "Archivos: tcpdf.crt, tcpdf.key<br>";
echo "Protección: .htaccess creado.<br>";
echo "<hr>";
echo "<b>IMPORTANTE:</b> Agregue '/certs' a su .gitignore si usa Git.";
?>
