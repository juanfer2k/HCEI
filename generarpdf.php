<?php
require_once('tcpdf/tcpdf.php');

// Crear PDF
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// ------------------------------------------------------------
// Simulación de imágenes de entrada (ejemplo PNG base64)
// En tu caso esto serán las firmas reales: $firma_paciente, $firma_medico, etc.
$firmas = [
    $firma_paciente ?? '',
    $firma_medico ?? '',
    $firma_paramedico ?? '',
    $firma_receptor ?? '',
    $firma_acudiente ?? '',
    $firma_extra1 ?? '',
    $firma_extra2 ?? '',
    $firma_extra3 ?? '',
    $firma_extra4 ?? '',
    $firma_extra5 ?? '',
    $firma_extra6 ?? '',
    $firma_extra7 ?? '',
    $firma_extra8 ?? ''
];

// ------------------------------------------------------------
// CONFIGURACIÓN DE REJILLA
$anchoFirma  = 40;   // ancho de cada imagen
$altoFirma   = 20;   // alto de cada imagen
$espaciadoX  = 10;   // espacio horizontal
$espaciadoY  = 20;   // espacio vertical entre filas
$margenX     = 20;   // margen izquierdo
$margenY     = 230;  // punto inicial en la última parte de la página
$maxColumnas = 4;    // hasta 4 por fila
$maxFilas    = 3;    // hasta 3 filas por página
// ------------------------------------------------------------

// Contador para colocar las imágenes
$col = 0;
$fila = 0;

foreach ($firmas as $i => $firma) {
    if (!empty($firma)) {
        $x = $margenX + ($col * ($anchoFirma + $espaciadoX));
        $y = $margenY + ($fila * ($altoFirma + $espaciadoY));

        // Dibujar imagen
        $pdf->Image('@' . $firma, $x, $y, $anchoFirma, $altoFirma, 'PNG');

        // Opcional: etiqueta debajo
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Text($x, $y + $altoFirma + 2, "Firma " . ($i+1));

        // Avanzar columna
        $col++;

        // Si ya se llenó la fila → saltar a la siguiente
        if ($col >= $maxColumnas) {
            $col = 0;
            $fila++;
        }

        // Si ya se llenaron las filas → nueva página y reset
        if ($fila >= $maxFilas) {
            $pdf->AddPage();
            $col = 0;
            $fila = 0;
        }
    }
}

// ------------------------------------------------------------
// Salida del PDF
$pdf->Output('firmas.pdf', 'I');
