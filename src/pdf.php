<?php
require('fpdf.php');
require('conn.php');
require_once __DIR__ . '/titulos.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Validar ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('ID inválido.');
}

// Obtener datos
$stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$atencion = $result->fetch_assoc();
$stmt->close();

if (!$atencion) {
    die('No se encontró el registro.');
}

// Crear PDF
class PDF extends FPDF {
    function Header() {
        // Logo y encabezado
        $this->Image('logoAVIS.png', 10, 8, 30);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 10, utf8_decode('Registro de Atención a Pacientes'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 6, utf8_decode('Ambulancias Asistencia Vital Integral de Salud S.A.S (AVIS)'), 0, 1, 'C');
        $this->Cell(0, 6, 'NIT: 900981955-1', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        // Pie de página
        $this->SetY(-30);
        $this->Image('logo-vigilado-supersalud-nuevo.png', 10, $this->GetY(), 20);
        $this->SetFont('Helvetica', '', 8);
        $this->SetX(35);
        $this->MultiCell(0, 4, utf8_decode("ambulanciasavis.com\nCalle 9a#8-33 Urbanización Coral, El Cerrito, Valle"), 0, 'L');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 10);

// Mostrar datos
foreach ($atencion as $campo => $valor) {
    if (in_array($campo, ['id', 'created_at', 'firma_paramedico', 'firma_medico', 'firma_paciente', 'adjuntos', 'firma_medico_receptor'])) continue;
    if (empty($valor)) continue; // No mostrar campos vacíos
    $pdf->SetFont('Helvetica', 'B', 9);
    $label = $titulos[$campo] ?? ucwords(str_replace('_', ' ', $campo));
    $pdf->Cell(60, 6, utf8_decode($label) . ':', 0, 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->MultiCell(0, 6, utf8_decode($valor ?? 'N/A'));
}

// Mostrar firmas si existen
$firmas = [
    'firma_paramedico' => $titulos['firma_paramedico'] ?? 'Firma del Paramedico',
    'firma_medico' => $titulos['firma_medico'] ?? 'Firma del Médico',
    'firma_paciente' => $titulos['firma_paciente'] ?? 'Firma del Paciente',
    'firma_medico_receptor' => $titulos['firma_medico_receptor'] ?? 'Firma del Médico Receptor'
];
foreach ($firmas as $campo => $titulo) {
    if (!empty($atencion[$campo]) && $atencion[$campo] !== 'data:,') {
        $firma = $atencion[$campo];
        // Guardar imagen temporal
        $firmaPath = tempnam(sys_get_temp_dir(), 'firma') . '.png';
        file_put_contents($firmaPath, base64_decode(str_replace('data:image/png;base64,', '', $firma)));
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(0, 6, utf8_decode($titulo), 0, 1);
        $pdf->Image($firmaPath, $pdf->GetX(), $pdf->GetY(), 60);
        $pdf->Ln(30);
        unlink($firmaPath); // Limpieza
    }
}

$pdf->Output('I', 'Atencion_'.$id.'.pdf');
?>