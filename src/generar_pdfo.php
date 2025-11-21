<?php
require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/conn.php';
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
if (!$stmt) {
    die('Error en la preparación de la declaración: ' . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();

// Vincular resultados
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('No se encontró el registro.');
}
$atencion = $result->fetch_assoc();

// Clase personalizada para encabezado, pie de página y fondo
class CustomPDF extends TCPDF {
    public $registro_nro = ''; // Propiedad para almacenar el número de registro para el footer

    public function Header() {
        // Logo
        $image_file = __DIR__ . '/AVIS.png';
        $this->Image($image_file, 10, 2, 30, '', 'PNG');
        // Logo "Vigilado" alineado arriba a la derecha del header
        $image_file = __DIR__ . '/vigilado.png';
        $this->Image($image_file, 173, 3, 30, '', 'PNG');
       
        // Títulos
        $this->SetFont('helvetica', 'B', 11);
        $this->SetTextColor(37, 47, 96); // Color #252f60
        $this->SetY(5);
        $this->Cell(0, 6, 'Registro de Atención a Pacientes (HCEI)', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 1, 'Ambulancias Asistencia Vital Integral de Salud (AVIS) S.A.S ', 0, 1, 'C');
        $this->SetFont('helvetica', '', 7);
        $this->Cell(0, 1, 'NIT: 900981955-1', 0, 1, 'C');
    }

    public function Footer() {
        // Posición a 3.5 cm del final para dar espacio suficiente al footer.
        $this->SetY(-35);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(128, 128, 128);
        // Línea superior del pie de página
        $this->SetLineStyle(array('width' => 0.2, 'color' => array(150, 150, 150)));
        $this->Cell(0, 0, '', 'T', 1, 'C');

        // Contenido del pie de página (Registro, Página, Copyright)
        $this->SetY($this->GetY() + 1);
        $this->Cell($this->getPageWidth() * 0.45, 4, 'Registro: ' . $this->registro_nro . ' | Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'L');
        $this->Cell(0, 4, 'Ambulancias AVIS © ' . date("Y"), 0, 0, 'R');
        
        $this->Ln(4);

        // Texto legal con sangría y letra pequeña
        $this->SetFont('helvetica', 'I', 6);
        $legal_text = 'La información contenida en este documento es una representación impresa y copia fiel de los registros electrónicos diligenciados por los profesionales firmantes durante la prestación del servicio. Este documento se genera a solicitud expresa del paciente, la entidad aseguradora o por requerimiento de autoridad judicial competente, según los casos autorizados por la ley. El tratamiento de los datos personales sensibles aquí consignados está protegido y se rige por los principios de confidencialidad y seguridad, en estricto cumplimiento de lo dispuesto en la Ley 2015 de 2020 (de Historia Clínica Electrónica Interoperable) y la Ley Estatutaria 1581 de 2012 (Régimen General de Protección de Datos Personales - Habeas Data).';
        
        $side_padding = 5;
        $this->SetX($this->getMargins()['left'] + $side_padding);
        $text_width = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'] - (2 * $side_padding);
        $this->MultiCell($text_width, 0, $legal_text, 0, 'C', false, 1, '', '', true, 0, false, true, 0, 'T');
    }
}

// Crear PDF
$pdf = new CustomPDF();
$pdf->registro_nro = $atencion['registro']; // Asignar el número de registro para el footer

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Ambulancias AVIS');
$pdf->SetTitle('Registro de Atención');
$pdf->SetSubject('Atención a Pacientes');
$pdf->SetMargins(10, 25, 7.5); // Margen izq 1cm, der ~0.75cm
$pdf->SetAutoPageBreak(TRUE, 35); // Margen inferior aumentado para el footer
$pdf->AddPage();

// Define las secciones y los campos que pertenecen a cada una
$secciones = [
    'Información Interna' => [
        /* 'registro', */ 'fecha', 'ambulancia', 'servicio', 'pagador', 'quien_informo', 'hora_despacho', 'hora_llegada', 'hora_ingreso', 'hora_final', 'conductor', 'cc_conductor', 'tripulante', 'cc_tripulante', 'medico_tripulante', 'cc_medico', 'direccion_servicio', 'localizacion', 'ips_destino', 'tipo_traslado'
    ],
    'Datos para Aseguradora' => [
        'aseguradora_soat'
    ],
    'Identificación del Paciente' => [
        'nombres_paciente', 'tipo_identificacion', 'id_paciente', 'genero_nacer', 'fecha_nacimiento', 'direccion_domicilio', 'telefono_paciente', 'barrio_paciente', 'ciudad', 'atencion_en', 'etnia', 'especificar_otra', 'discapacidad'
    ],
    'Antecedentes Médicos' => [
        'ant_alergicos_sn', 'ant_alergicos_cual', 'ant_ginecoobstetricos_sn', 'ant_ginecoobstetricos_cual', 'ant_patologicos_sn', 'ant_patologicos_cual', 'ant_quirurgicos_sn', 'ant_quirurgicos_cual', 'ant_traumatologicos_sn', 'ant_traumatologicos_cual', 'ant_toxicologicos_sn', 'ant_toxicologicos_cual', 'ant_familiares_sn', 'ant_familiares_cual'
    ],
    'Datos Clínicos' => [
        'frecuencia_cardiaca', 'frecuencia_respiratoria', 'spo2', 'tension_arterial', 'glucometria', 'temperatura', 'rh', 'llenado_capilar', 'peso', 'talla', 'escala_glasgow'
    ],
    'Describa el Examen Físico' => [
        'examen_fisico'
    ],
    'Procedimientos' => [
        'procedimientos'
    ],
    'Consumo durante el Servicio' => [
        'consumo_servicio'
    ],
];

// Paleta de colores replicada del CSS para los bordes de sección.
// Formato: [R, G, B]
$seccion_colores = [
    'Información Interna' => [187, 222, 251], // Azul claro (#bbdefb)
    'Datos para Aseguradora' => [220, 237, 200], // Verde claro (#dcedc8)
    'Identificación del Paciente' => [197, 202, 233], // Índigo suave (#c5cae9)
    'Antecedentes Médicos' => [248, 187, 208], // Rosa suave (#f8bbd0)
    'Datos Clínicos' => [178, 223, 219], // Verde azulado (#b2dfdb)
    'Firmas' => [207, 216, 220], // Gris (#cfd8dc)
    'Adjuntos' => [224, 224, 224], // Gris claro (#e0e0e0)
];

// --- Renderizado del PDF por Secciones ---

$page_width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
$space_between_cols = 1.5;
$label_width = 22; // Ancho para las etiquetas (aún más pequeño para 3 columnas)
$column_pair_width = ($page_width - (2 * $space_between_cols)) / 3; // Ancho para un par de etiqueta+valor
$value_width = $column_pair_width - $label_width; // Ancho para los valores

// Campos que se mostrarán en ancho completo.
$full_width_fields = ['examen_fisico', 'procedimientos', 'consumo_servicio', 'ant_alergicos_cual', 'ant_ginecoobstetricos_cual', 'ant_patologicos_cual', 'ant_quirurgicos_cual', 'ant_traumatologicos_cual', 'ant_toxicologicos_cual', 'ant_familiares_cual', 'antecedentes'];

foreach ($secciones as $titulo_seccion => $campos) {
    // Estilos para las celdas de datos: borde punteado sutil y más espaciado vertical.
    $color_celda = $seccion_colores[$titulo_seccion] ?? [150, 170, 200];
    $pdf->setCellPaddings(1, 0.2, 1, 0.2);
    $pdf->SetLineStyle(array('width' => 0.1, 'dash' => 1, 'color' => $color_celda));

    // Dibujar cabecera de la sección como la primera fila de la grilla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(37, 47, 96);
    $pdf->SetFillColor(245, 245, 245); // Gris sutil para el título
    if ($titulo_seccion == 'Información Interna') {
        // Título de la sección a la izquierda
        $pdf->Cell($page_width / 2, 4, $titulo_seccion, 1, 0, 'L', true);
        // Número de Registro a la derecha
        $pdf->SetFont('pdfacourier', 'B', 12);
        $pdf->SetTextColor(255, 0, 0); // Rojo
        $pdf->Cell($page_width / 2, 4, 'Registro: ' . $atencion['registro'], 0, 1, 'R', false);
        // Restaurar color de texto para las siguientes secciones
        $pdf->SetTextColor(37, 47, 96);
    } else {
        $pdf->MultiCell($page_width, 4, $titulo_seccion, 1, 'L', true, 1);
    }

    // Resetear fuente para las celdas de datos
    $pdf->SetFont('helvetica', '', 7);

    // Mostrar siempre todos los campos de la sección
    $fields_in_section = $campos;
    $num_fields = count($fields_in_section);
    $i = 0;

    while ($i < $num_fields) {
        $campo1 = $fields_in_section[$i];
        $label1 = $titulos[$campo1] ?? ucfirst(str_replace('_', ' ', $campo1));
        $value1 = $atencion[$campo1] ?? '-';

        // Eliminar la palabra "Antecedentes" para hacer las etiquetas más cortas en esa sección
        if ($titulo_seccion == 'Antecedentes Médicos') {
            $label1 = trim(str_ireplace('Antecedentes', '', $label1));
            if (strpos($campo1, '_cual') !== false) {
                $label1 = trim(str_ireplace('(¿Cuál?)', '', $label1));
            }
        }

        if (in_array($campo1, $full_width_fields)) {
            // Special handling for Antecedentes _cual fields to save space
            if ($titulo_seccion == 'Antecedentes Médicos' && strpos($campo1, '_cual') !== false) {
                $base_campo = str_replace('_cual', '', $campo1);
                $campo_sn = $base_campo . '_sn';
                $valor_sn = strtolower(trim($atencion[$campo_sn] ?? 'no'));
                $valor_cual = trim($atencion[$campo1] ?? '');

                // Skip rendering this field if it's not applicable
                if (($valor_sn !== 'si' && $valor_sn !== 'sí') && empty($valor_cual)) {
                    $i++;
                    continue;
                }
            }

            if (count($fields_in_section) > 1) {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(37, 47, 96);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->MultiCell($page_width, 3, $label1, 1, 'L', true, 1, '', '', true, 0, false, true, 3, 'M');
            }
            // Altura dinámica para la celda de valor
            if (empty(trim($value1)) || trim($value1) === '-') {
                $cell_height = 3;
            } else {
                $text_height = $pdf->getStringHeight($page_width - 4, $value1); // -4 for horizontal padding
                $cell_height = max(3, $text_height + 2); // min height 3, add vertical padding
            }
            // El 4to parámetro (1) indica que se debe añadir una nueva página si la celda no cabe.
            // El 7mo parámetro (1) indica que se debe mover el cursor hacia abajo después de la celda.
            $pdf->MultiCell($page_width, $cell_height, $value1, 1, 'L', true, 1, '', '', true, 0, false, true, $cell_height, 'T', false);

            $i++;
            continue;
        }

        $campo2 = ($i + 1 < $num_fields) && !in_array($fields_in_section[$i + 1], $full_width_fields) ? $fields_in_section[$i + 1] : null;
        $campo3 = ($i + 2 < $num_fields) && !in_array($fields_in_section[$i + 2], $full_width_fields) ? $fields_in_section[$i + 2] : null;

        $pdf->SetFont('helvetica', '', 7);
        $h1 = $pdf->getStringHeight($value_width, $value1);

        $h2 = 0;
        if ($campo2) {
            $label2 = $titulos[$campo2] ?? ucfirst(str_replace('_', ' ', $campo2));
            $value2 = $atencion[$campo2] ?? '-';

            // Eliminar la palabra "Antecedentes" para hacer las etiquetas más cortas en esa sección
            if ($titulo_seccion == 'Antecedentes Médicos') {
                $label2 = trim(str_ireplace('Antecedentes', '', $label2));
                if (strpos($campo2, '_cual') !== false) {
                    $label2 = trim(str_ireplace('(¿Cuál?)', '', $label2));
                }
            }

            $h2 = $pdf->getStringHeight($value_width, $value2);
        }
        
        $h3 = 0;
        if ($campo3) {
            $label3 = $titulos[$campo3] ?? ucfirst(str_replace('_', ' ', $campo3));
            $value3 = $atencion[$campo3] ?? '-';

            if ($titulo_seccion == 'Antecedentes Médicos') {
                $label3 = trim(str_ireplace('Antecedentes', '', $label3));
                 if (strpos($campo3, '_cual') !== false) {
                    $label3 = trim(str_ireplace('(¿Cuál?)', '', $label3));
                }
            }

            $h3 = $pdf->getStringHeight($value_width, $value3);
        }

        // La altura de la fila se define por un valor fijo y pequeño. TCPDF la ajustará si el contenido es mayor.
        $row_height = 4;

        $startY = $pdf->GetY();
        
        // Celda 1
        $pdf->SetFont('helvetica', '', 7); // Fuente de etiqueta más pequeña, sin negrita
        $pdf->SetTextColor(37, 47, 96); // Color de título
        $pdf->SetFillColor(245, 245, 245); // Gris sutil
        $pdf->MultiCell($label_width, $row_height, $label1, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(37, 47, 96); // Color de título para el valor
        $pdf->SetFillColor(255, 255, 255);
        // Si no hay más celdas, esta se encarga del salto de línea (ln=1)
        $pdf->MultiCell($value_width, $row_height, $value1, 1, 'L', true, ($campo2 || $campo3) ? 0 : 1, '', '', true, 0, false, true, $row_height, 'M');

        $current_x = $pdf->GetX();

        if ($campo2) {
            $x_col2 = $current_x + $space_between_cols;
            // Usar SetY solo si es necesario para alinear verticalmente.
            // $pdf->SetY($startY); 
            $pdf->SetX($x_col2);
            // Celda 2
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96); // Color de título
            $pdf->SetFillColor(245, 245, 245); // Gris sutil
            $pdf->MultiCell($label_width, $row_height, $label2, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96); // Color de título para el valor
            $pdf->SetFillColor(255, 255, 255);
            // Si no hay tercera celda, esta se encarga del salto de línea (ln=1)
            $pdf->MultiCell($value_width, $row_height, $value2, 1, 'L', true, $campo3 ? 0 : 1, '', '', true, 0, false, true, $row_height, 'M');
            $current_x = $pdf->GetX();
        }

        if ($campo3) {
            $x_col3 = $current_x + $space_between_cols;
            // Usar SetY solo si es necesario para alinear verticalmente.
            // $pdf->SetY($startY);
            $pdf->SetX($x_col3);
            // Celda 3
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96); // Color de título
            $pdf->SetFillColor(245, 245, 245); // Gris sutil
            $pdf->MultiCell($label_width, $row_height, $label3, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96); // Color de título para el valor
            $pdf->SetFillColor(255, 255, 255);
            // Esta es la última celda, siempre hace el salto de línea (ln=1)
            $pdf->MultiCell($value_width, $row_height, $value3, 1, 'L', true, 1, '', '', true, 0, false, true, $row_height, 'M');
        }

        $i += ($campo3 ? 3 : ($campo2 ? 2 : 1));
    }
    // Resetear el padding para no afectar a los siguientes títulos de sección
    $pdf->setCellPaddings(0, 0, 0, 0);
}

/**
 * Dibuja un cuadro de firma y la imagen de la firma si está disponible y es válida.
 *
 * Versión mejorada para manejar transparencia y alineación.
 *
 * @param TCPDF $pdf La instancia del PDF.
 * @param string|null $imageData La firma en formato base64.
 * @param float $x La coordenada X.
 * @param float $y La coordenada Y.
 * @param float $w El ancho del cuadro.
 * @param float $h La altura del cuadro.
 */
function drawSignatureImage($pdf, $imageData, $x, $y, $w, $h) {
    // Dibuja siempre el recuadro
    $pdf->SetXY($x, $y);
    $pdf->Cell($w, $h, '', 1, 0, 'C');

    // Valida que la firma no esté vacía y sea un PNG válido antes de dibujarla
    if (empty($imageData) || !preg_match('/^data:image\/png;base64,/', $imageData)) {
        return;
    }

    $imageDataDecoded = base64_decode(preg_replace('/^data:image\/png;base64,/', '', $imageData));
    if (strlen($imageDataDecoded) === 0) {
        return;
    }

    // Guardar la firma como archivo temporal para mejor manejo
    $tempFile = tempnam(sys_get_temp_dir(), 'sig');
    file_put_contents($tempFile, $imageDataDecoded);

    // Obtener dimensiones de la imagen
    $imageInfo = getimagesize($tempFile);
    $imgWidth = $imageInfo[0];
    $imgHeight = $imageInfo[1];

    // Calcular relación de aspecto y dimensiones para encajar en el cuadro
    $aspectRatio = $imgWidth / $imgHeight;
    $boxAspectRatio = ($w - 2) / ($h - 2);

    if ($aspectRatio > $boxAspectRatio) {
        // La imagen es más ancha que el cuadro
        $displayWidth = $w - 2;
        $displayHeight = $displayWidth / $aspectRatio;
    } else {
        // La imagen es más alta que el cuadro
        $displayHeight = $h - 2;
        $displayWidth = $displayHeight * $aspectRatio;
    }

    // Centrar la imagen en el cuadro
    $xPos = $x + (($w - $displayWidth) / 2);
    $yPos = $y + (($h - $displayHeight) / 2);

    // Dibujar la imagen con transparencia preservada
    $pdf->Image(
        $tempFile,
        $xPos,
        $yPos,
        $displayWidth,
        $displayHeight,
        'PNG',
        '',
        'T',      // Alinear en la parte SUPERIOR del cuadro
        false,
        300,
        '',
        false,
        false,
        0,
        false,
        false,
        true  // Preservar transparencia
    );

    // Eliminar archivo temporal
    unlink($tempFile);
}

// --- SECCIÓN DE FIRMAS Y DESISTIMIENTO ---
if ($pdf->GetY() > ($pdf->getPageHeight() - 100)) { // Check if enough space is left for both rows
    $pdf->AddPage();
}
$pdf->Ln(1);
$page_width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
$space_between = 4;
$col_width = ($page_width - $space_between) / 2;
$left_x = $pdf->GetX();
$right_x = $left_x + $col_width + $space_between;

// --- FILA SUPERIOR DE FIRMAS ---
$y_fila1 = $pdf->GetY();
$sig_box_height = 25;
$title_height = 5;
$info_height = 5;
$total_box_height = $title_height + $sig_box_height + $info_height;

// --- Columna Izquierda (Tripulante y Médico Entrega) ---
$firma_medico_presente = !empty($atencion['firma_medico']) && strlen(base64_decode(preg_replace('/^data:image\/png;base64,/', '', $atencion['firma_medico']))) > 0;
$crew_sig_width = $firma_medico_presente ? floor(($col_width - 2) / 2) : $col_width;

// Caja Tripulante
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetFillColor(245, 245, 245);
$pdf->SetXY($left_x, $y_fila1);
$pdf->Cell($crew_sig_width, $title_height, 'Tripulante', 1, 0, 'C', true);
drawSignatureImage($pdf, $atencion['firma_paramedico'], $left_x, $y_fila1 + $title_height, $crew_sig_width, $sig_box_height);

// Caja Medico Entrega (si existe)
if ($firma_medico_presente) {
    $medic_x = $left_x + $crew_sig_width + 2;
    $pdf->SetXY($medic_x, $y_fila1);
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell($crew_sig_width, $title_height, 'Medico Entrega', 1, 0, 'C', true);
    drawSignatureImage($pdf, $atencion['firma_medico'], $medic_x, $y_fila1 + $title_height, $crew_sig_width, $sig_box_height);
}

// Info del tripulante/medico
$pdf->SetXY($left_x, $y_fila1 + $title_height + $sig_box_height);
$pdf->SetFont('helvetica', '', 7);
$tripulante_info = 'Nombre: ' . ($atencion['tripulante'] ?? 'N/A') . '  |  ID: ' . ($atencion['cc_tripulante'] ?? 'N/A');
$pdf->Cell($crew_sig_width, $info_height, $tripulante_info, 1, 0, 'C');

if ($firma_medico_presente) {
    $pdf->SetXY($medic_x, $y_fila1 + $title_height + $sig_box_height);
    $medico_info = 'Nombre: ' . ($atencion['medico_tripulante'] ?? 'N/A') . '  |  ID: ' . ($atencion['cc_medico'] ?? 'N/A');
    $pdf->Cell($crew_sig_width, $info_height, $medico_info, 1, 0, 'C');
}

// --- COLUMNA DERECHA (Médico Receptor) ---
$pdf->SetXY($right_x, $y_fila1);
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell($col_width, $title_height, 'Firma Médico que Recibe', 1, 0, 'C', true);
// La función drawSignatureImage ahora se encarga de dibujar el recuadro Y la imagen
drawSignatureImage($pdf, $atencion['firma_medico_receptor'] ?? null, $right_x, $y_fila1 + $title_height, $col_width, $sig_box_height);$pdf->SetXY($right_x, $y_fila1 + $title_height + $sig_box_height); // Posicionar para la línea de info
$pdf->SetFont('helvetica', '', 7);
$receptor_info = 'Nombre: ' . ($atencion['nombre_medico_receptor'] ?? 'N/A') . '  |  ID/Registro: ' . ($atencion['id_medico_receptor'] ?? 'N/A');
$pdf->Cell($col_width, $info_height, $receptor_info, 1, 0, 'C');

// --- AVANZAR CURSOR PARA LA SIGUIENTE FILA ---
// Se calcula la posición de la segunda fila basándose en la altura total de la primera.
// Esto es más fiable que usar GetY() después de dibujar celdas complejas.
$pdf->SetY($y_fila1 + $total_box_height + 2); // +2 para un pequeño margen
$y_fila2 = $pdf->GetY();

// --- FILA INFERIOR (Consentimiento y Desistimiento) ---
$bottom_text_height = 10; // Altura para el texto legal
$bottom_sig_height = 18;  // Altura para el espacio de la firma
$bottom_info_height = 5;  // Altura para las líneas de Nombre/ID
$total_bottom_height = $title_height + $bottom_text_height + $bottom_sig_height + $bottom_info_height; // Altura total de esta caja

// -- Columna Izquierda: Consentimiento --
$pdf->SetXY($left_x, $y_fila2);
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetFillColor(245, 245, 245); // Gris sutil
$pdf->Cell($col_width, $title_height, 'Consentimiento de Paciente o Acompañante', 'LTR', 0, 'C', true);
$pdf->SetFont('helvetica', 'I', 6.5);
$pdf->setCellPaddings(2, 2, 2, 2);
$consent_text = 'Declaro en mis facultades Autorizo mi traslado y atención a Ambulancias AVIS S.A.S. en el sistema de emergencias.';
$pdf->MultiCell($col_width, $bottom_text_height, $consent_text, 'LR', 'C', false, 1, $left_x, $y_fila2 + $title_height, true, 0, false, true, $bottom_text_height, 'M');
$pdf->setCellPaddings(0, 0, 0, 0);
$consent_sig_y = $y_fila2 + $title_height + $bottom_text_height;
drawSignatureImage($pdf, $atencion['firma_paciente'] ?? null, $left_x, $consent_sig_y, $col_width, $bottom_sig_height); // Dibuja dentro del espacio asignado
$pdf->SetXY($left_x, $consent_sig_y + $bottom_sig_height); // Posicionar para las líneas de info
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell($col_width / 2, $bottom_info_height, 'Nombre / Firma', 'T', 0, 'L');
$pdf->Cell($col_width / 2, $bottom_info_height, 'Identificación', 'T', 1, 'L');
$pdf->SetXY($left_x, $y_fila2); // Volver a la posición Y para dibujar el borde completo
$pdf->Cell($col_width, $total_bottom_height, '', 'LTRB', 0, 'C'); // Dibujar bordes completos

// -- Columna Derecha: Desistimiento --
$pdf->SetXY($right_x, $y_fila2);
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell($col_width, $title_height, 'DESISTIMIENTO VOLUNTARIO', 'LTR', 0, 'C', true);
$pdf->SetTextColor(255, 0, 0); // *** Cambiar color a ROJO para el texto de negación ***
$pdf->SetFont('helvetica', 'I', 6.5);
$pdf->setCellPaddings(2, 2, 2, 2);
$refusal_text = 'Me niego a recibir la atención médica, traslado o internación sugerida por el sistema de emergencia médica. Eximo de toda responsabilidad a AMBULANCIAS AVIS S.A.S. de las consecuencias de mi decisión, y asumo los riesgos que mi negativa pueda generar.';
$pdf->MultiCell($col_width, $bottom_text_height, $refusal_text, 'LR', 'C', false, 1, $right_x, $y_fila2 + $title_height, true, 0, false, true, $bottom_text_height, 'M');
$pdf->SetTextColor(37, 47, 96); // *** Restaurar color de texto a AZUL por defecto ***
$pdf->setCellPaddings(0, 0, 0, 0);
$desistimiento_sig_y = $y_fila2 + $title_height + $bottom_text_height;
drawSignatureImage($pdf, null, $right_x, $desistimiento_sig_y, $col_width, $bottom_sig_height); // Dibuja el recuadro vacío
$pdf->SetXY($right_x, $desistimiento_sig_y + $bottom_sig_height); // Posicionar para las líneas de info
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell($col_width / 2, $bottom_info_height, 'Nombre / Firma', 'T', 0, 'L');
$pdf->Cell($col_width / 2, $bottom_info_height, 'Identificación', 'T', 1, 'L');
$pdf->SetXY($right_x, $y_fila2); // Volver a la posición Y para dibujar el borde completo
$pdf->Cell($col_width, $total_bottom_height, '', 'LTRB', 0, 'C'); // Dibujar bordes completos


// --- SECCIÓN DE ADJUNTOS EN PÁGINA NUEVA ---
if (!empty($atencion['adjuntos'])) {
    $adjuntos = json_decode($atencion['adjuntos'], true);
    if (is_array($adjuntos) && !empty($adjuntos)) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(37, 47, 96);
        $pdf->Cell(0, 10, 'Adjuntos del Registro', 0, 1, 'C');
        $pdf->Ln(5);
 
        $x_pos = $pdf->GetX();
        $y_pos = $pdf->GetY();
        $img_width = ($page_width / 4) - 3; // Ancho para 4 imágenes por fila
        $col_count = 0;
 
        foreach ($adjuntos as $adjunto) {
            // Insertar imagen solo si detectada como base64 válida
            if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $adjunto, $matches)) {
                // Si el espacio vertical se agota, crear nueva página
                if ($y_pos + $img_width > ($pdf->getPageHeight() - $pdf->getFooterMargin())) {
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 12);
                    $pdf->Cell(0, 10, 'Adjuntos (Continuación)', 0, 1, 'C');
                    $pdf->Ln(5);
                    $x_pos = $pdf->GetX();
                    $y_pos = $pdf->GetY();
                    $col_count = 0;
                }

                // Usar @ para pasar los datos de la imagen directamente a TCPDF sin crear un archivo temporal
                $img_data = base64_decode(preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $adjunto));
                $pdf->Image('@' . $img_data, $x_pos, $y_pos, $img_width, 0, '', '', 'T', false, 150, 'C');
 
                // Mover la posición X para la siguiente imagen
                $x_pos += $img_width + 4;
                $col_count++;
 
                // Si se completa la fila, saltar y reiniciar valores
                if ($col_count >= 4) {
                    $pdf->Ln($img_width + 4); // Salta la línea acorde al alto de imagen
                    // Clave: Actualizar la posición Y para la siguiente fila
                    $y_pos = $pdf->GetY();
                    $x_pos = $pdf->GetX();
                    $col_count = 0;
                }
            }
        }
    }
}

// Salida del PDF
$pdf->Output('registro_atencion_' . $id . '.pdf', 'I');