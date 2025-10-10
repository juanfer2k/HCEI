<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Control de acceso: solo roles administrativos pueden generar FURTRAN
$rol = strtolower($_SESSION['usuario_rol'] ?? '');
if (!in_array($rol, ['administrativo', 'master', 'secretaria'], true)) {
    http_response_code(403); // Forbidden
    echo 'No autorizado para generar FURTRAN.';
    exit;
}

require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/titulos.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { die('ID inválido'); }

$stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { die('Registro no encontrado'); }
$at = $res->fetch_assoc();
$stmt->close();

class FURTRANPDF extends TCPDF {    // This will hold the company data passed to the PDF object
    public $empresa = [];
    function Header() {
        if (!empty($this->empresa['logo_principal']) && file_exists(__DIR__ . '/' . $this->empresa['logo_principal'])) {
            $this->Image(__DIR__ . '/' . $this->empresa['logo_principal'], 12, 8, 28);
        }
        if (!empty($this->empresa['logo_vigilado']) && file_exists(__DIR__ . '/' . $this->empresa['logo_vigilado'])) {
            $this->Image(__DIR__ . '/' . $this->empresa['logo_vigilado'], 175, 8, 22);
        }
        $this->SetFont('dejavusans','B',10);
        $this->Cell(0, 6, mb_strtoupper($this->empresa['nombre'] ?? '', 'UTF-8'), 0, 1, 'C');
        $this->SetFont('dejavusans','',8);
        $this->Cell(0, 5, 'NIT: ' . ($this->empresa['nit'] ?? '') . '  -  Cod. Hab.: ' . ($this->empresa['COD_Habilitacion'] ?? ''), 0, 1, 'C');
        $this->Ln(2);
    }
    function Footer() {
        $this->SetY(-12);
        $this->SetFont('dejavusans', '', 7);
        $this->Cell(0, 8, 'Página '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new FURTRANPDF();
$pdf->empresa = $empresa;
$pdf->SetCreator('HCEI/AVIS');
$pdf->SetAuthor($empresa['nombre'] ?? 'Ambulancias AVIS');
$pdf->SetTitle('FURTRAN - Formulario Único de Reclamación');
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(12, 28, 12);
$pdf->SetAutoPageBreak(true, 15);
$pdf->setFontSubsetting(true);
$pdf->SetFont('dejavusans', '', 9, '', true);
$pdf->AddPage();

// Titulo y encabezado FURTRAN
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->MultiCell(0, 6, 'FORMULARIO ÚNICO DE RECLAMACIÓN DE GASTOS DE TRANSPORTE Y MOVILIZACIÓN DE VÍCTIMAS (FURTRAN)', 0, 'C');
$pdf->Ln(2);
$pdf->SetFont('dejavusans','',9);

// Datos principales
$pdf->Cell(45, 7, 'No. Radicado:', 1);
$pdf->Cell(50, 7, $at['no_radicado_furtran'] ?? '', 1);
$pdf->Cell(45, 7, 'Fecha de Entrega:', 1);
$pdf->Cell(50, 7, $at['fecha'] ?? '', 1);
$pdf->Ln();

// I. DATOS DEL TRANSPORTADOR
$pdf->SetFont('dejavusans','B',9);
$pdf->Cell(0, 7, 'I. DATOS DEL TRANSPORTADOR', 1, 1, 'C', true);
$pdf->SetFont('dejavusans','',8);
$pdf->Cell(45, 6, 'Nombre o Razón Social:', 'LT');
$pdf->Cell(0, 6, ($at['nombre_empresa_transportador'] ?: $empresa['nombre'] ?? ''), 'TR', 1);
$pdf->Cell(45, 6, 'Cód. Habilitación:', 'L');
$pdf->Cell(50, 6, ($at['codigo_habilitacion_empresa'] ?: $empresa['COD_Habilitacion'] ?? ''), 'R', 0);
$pdf->Cell(45, 6, 'Teléfono:', 'L');
$pdf->Cell(0, 6, ($at['telefono_transportador'] ?: $empresa['telefono'] ?? ''), 'R', 1);
$pdf->Cell(45, 6, 'Dirección:', 'L');
$pdf->Cell(0, 6, ($at['direccion_transportador'] ?: $empresa['direccion'] ?? ''), 'R', 1);
$pdf->Cell(45, 6, 'Departamento:', 'L');
$pdf->Cell(50, 6, ($empresa['departamento'] ?? ''), 'R', 0);
$pdf->Cell(45, 6, 'Municipio:', 'L');
$pdf->Cell(0, 6, ($empresa['municipio'] ?? ''), 'R', 1);
$pdf->Cell(45, 6, 'Placa Vehículo:', 'LB');
$pdf->Cell(50, 6, $at['placa_vehiculo'] ?? '', 'RB', 0);
$pdf->Cell(45, 6, 'Total Folios:', 'LB');
$pdf->Cell(0, 6, strval($at['total_folios'] ?? ''), 'RB', 1);
$pdf->Ln(2);

// II. RELACIÓN DE LAS VÍCTIMAS TRASLADADAS
$pdf->SetFont('dejavusans','B',9);
$pdf->Cell(0, 7, 'II. RELACIÓN DE LAS VÍCTIMAS TRASLADADAS', 1, 1, 'C', true);
$pdf->SetFont('dejavusans','',8);
$pdf->Cell(45, 6, 'Nombres y Apellidos:', 'LT');
$pdf->Cell(0, 6, $at['nombres_paciente'] ?? '', 'TR', 1);
$pdf->Cell(45, 6, 'Tipo Documento:', 'L');
$pdf->Cell(50, 6, $at['tipo_identificacion'] ?? '', 'R', 0);
$pdf->Cell(45, 6, 'No. Documento:', 'L');
$pdf->Cell(0, 6, $at['id_paciente'] ?? '', 'R', 1);
$pdf->Cell(45, 6, 'Dirección Residencia:', 'LB');
$pdf->Cell(0, 6, $at['direccion_domicilio'] ?? '', 'RB', 1);
$pdf->Ln(2);

// III. LUGAR EN EL QUE SE RECOGE LA VÍCTIMA
$pdf->SetFont('dejavusans','B',9);
$pdf->Cell(0, 7, 'III. LUGAR EN EL QUE SE RECOGE LA VÍCTIMA', 1, 1, 'C', true);
$pdf->SetFont('dejavusans','',8);
$pdf->Cell(45, 6, 'Dirección:', 'LTB');
$pdf->Cell(0, 6, $at['direccion_servicio'] ?? '', 'TRB', 1);
$pdf->Cell(45, 6, 'Zona:', 'LB');
$pdf->Cell(0, 6, $at['localizacion'] ?? '', 'RB', 1);
$pdf->Ln(2);

// IV. DATOS DE LA IPS RECEPTORA
$pdf->SetFont('dejavusans','B',9);
$pdf->Cell(0, 7, 'IV. DATOS DE LA IPS RECEPTORA', 1, 1, 'C', true);
$pdf->SetFont('dejavusans','',8);
$pdf->Cell(45, 6, 'Nombre IPS:', 'LTB');
$pdf->Cell(0, 6, $at['nombre_ips_receptora'] ?? '', 'TRB', 1);
$pdf->Cell(45, 6, 'NIT:', 'LB');
$pdf->Cell(0, 6, $at['nit_ips_receptora'] ?? '', 'RB', 1);
$pdf->Ln(2);

// V. CERTIFICACIÓN DE TRASLADO, FIRMAS Y DECLARACIÓN JURADA
$pdf->Ln(6);
$pdf->SetFont('dejavusans','',8);
$pdf->MultiCell(0, 5, 'Como representante legal de la empresa transportadora, declaro bajo gravedad de juramento que la información contenida es veraz y corresponde al servicio efectivamente prestado, para efectos de reconocimiento de transporte y movilización (FURTRAN).', 1, 'J');
$pdf->Ln(8);

// Firma representante legal
$y = $pdf->GetY();
$pdf->SetFont('dejavusans','',9);
$pdf->Cell(125, 26, '', 1); // Box for signature
if (!empty($empresa['firma_representante_legal_empresa']) && file_exists(__DIR__ . '/' . $empresa['firma_representante_legal_empresa'])) {
    $pdf->Image(__DIR__ . '/' . $empresa['firma_representante_legal_empresa'], $pdf->GetX() - 123, $y + 2, 40);
}
$pdf->SetFont('dejavusans','',8);
$pdf->Cell(0, 26, 'Nombre: ' . ($empresa['nombre_representante_legal_empresa'] ?? ''), 1, 1, 'L');
$pdf->SetY($y + 26); // Move cursor to below the signature area
$pdf->Cell(62.5, 8, 'Tipo Doc: ' . ($empresa['tipo_documento_representante_legal_empresa'] ?? ''), 1, 0);
$pdf->Cell(62.5, 8, 'No. Doc: ' . ($empresa['id_representante_legal_empresa'] ?? ''), 1, 0);
$pdf->Cell(0, 8, 'Teléfono: ' . ($empresa['telefono'] ?? ''), 1, 1);

// Output final
$pdf->Output('furtran_'.$at['no_radicado_furtran'].'.pdf', 'I');
?>
