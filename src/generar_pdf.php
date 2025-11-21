<?php
ob_start(); // Start output buffering to prevent any premature output
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

// Obtener datos - usar campos de atenciones_extra para antecedentes (migrados)
$stmt = $conn->prepare("
    SELECT 
        a.*,
        ae.id as ae_id,
        ae.atencion_id,
        ae.triage_escena,
        ae.furips_reportado,
        ae.furips_fecha_reporte,
        ae.created_at as ae_created_at,
        COALESCE(ae.diagnostico_principal, a.diagnostico_principal) as diagnostico_principal,
        COALESCE(ae.motivo_traslado, a.motivo_traslado) as motivo_traslado,
        COALESCE(ae.examen_fisico, a.examen_fisico) as examen_fisico,
        COALESCE(ae.procedimientos, a.procedimientos) as procedimientos,
        COALESCE(ae.consumo_servicio, a.consumo_servicio) as consumo_servicio,
        COALESCE(ae.medicamentos_aplicados, a.medicamentos_aplicados) as medicamentos_aplicados,
        COALESCE(ae.nombres_paciente, a.nombres_paciente) as nombres_paciente,
        COALESCE(ae.direccion_domicilio, a.direccion_domicilio) as direccion_domicilio,
        COALESCE(ae.escena_paciente, a.escena_paciente) as escena_paciente,
        ae.antecedentes,
        ae.ant_alergicos_sn,
        ae.ant_alergicos_cual,
        ae.ant_ginecoobstetricos_sn,
        ae.ant_ginecoobstetricos_cual,
        ae.ant_patologicos_sn,
        ae.ant_patologicos_cual,
        ae.ant_quirurgicos_sn,
        ae.ant_quirurgicos_cual,
        ae.ant_traumatologicos_sn,
        ae.ant_traumatologicos_cual,
        ae.ant_toxicologicos_sn,
        ae.ant_toxicologicos_cual,
        ae.ant_familiares_sn,
        ae.ant_familiares_cual
    FROM atenciones a 
    LEFT JOIN atenciones_extra ae ON a.id = ae.atencion_id 
    WHERE a.id = ?
");
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

// Concatenar nombre del paciente si nombres_paciente está vacío
if (empty(trim($atencion['nombres_paciente'] ?? ''))) {
    $atencion['nombres_paciente'] = trim(implode(' ', array_filter([
        $atencion['primer_nombre_paciente'] ?? '',
        $atencion['segundo_nombre_paciente'] ?? '',
        $atencion['primer_apellido_paciente'] ?? '',
        $atencion['segundo_apellido_paciente'] ?? ''
    ])));
}

// Obtener todas las firmas desde atenciones_sig
$firmas = array();
$stmt_sig = $conn->prepare("SELECT tipo_firma, contenido FROM atenciones_sig WHERE atencion_id = ?");
if ($stmt_sig) {
    $stmt_sig->bind_param('i', $id);
    $stmt_sig->execute();
    $res_sig = $stmt_sig->get_result();
    while ($row_sig = $res_sig->fetch_assoc()) {
        $firmas[$row_sig['tipo_firma']] = $row_sig['contenido'];
    }
    $stmt_sig->close();
}

// Asignar firmas a variables específicas para compatibilidad con el código existente
$firma_paramedico = $firmas['paramedico'] ?? null;
$firma_medico = $firmas['medico'] ?? null;
$firma_paciente = $firmas['paciente'] ?? null;
$firma_medico_receptor = $firmas['medico_receptor'] ?? null;
$firma_receptor_ips = $firmas['representante_legal'] ?? null; // Este era 'receptor_ips' en el esquema anterior

// Clase personalizada para encabezado, pie de página y fondo
class CustomPDF extends TCPDF {
    public $registro_nro = ''; // Propiedad para almacenar el número de registro para el footer

    public function Header() {
        // Logo
        // Preferir constantes si existen (compatibilidad)
        $logoPrincipal = defined('LOGO_PRINCIPAL') && LOGO_PRINCIPAL ? LOGO_PRINCIPAL : ($GLOBALS['empresa']['logo_principal'] ?? '');
        $logoVigilado = defined('LOGO_VIGILADO') && LOGO_VIGILADO ? LOGO_VIGILADO : ($GLOBALS['empresa']['logo_vigilado'] ?? 'vigilado.png');
        $image_file = $logoPrincipal ? (__DIR__ . '/' . ltrim($logoPrincipal, '/')) : '';
        if ($image_file && file_exists($image_file)) {
            $this->Image($image_file, 10, 2, 30, '', '', '', '', false, 300);
        }
        // Logo "Vigilado" alineado arriba a la derecha del header
        $image_file = $logoVigilado ? (__DIR__ . '/' . ltrim($logoVigilado, '/')) : '';
        if ($image_file && file_exists($image_file)) {
            $this->Image($image_file, $this->getPageWidth() - 40, 3, 30, '', '', '', '', false, 300);
        }
       
        // Títulos
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(37, 47, 96); // Color #252f60
        $this->SetY(5);
        // Usar el título del PDF desde la configuración
        $this->Cell(0, 6, $GLOBALS['empresa']['titulo_pdf'], 0, 1, 'C');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 1, $GLOBALS['empresa']['nombre'], 0, 1, 'C');
        $this->SetFont('helvetica', '', 7);
        $this->Cell(0, 1, 'NIT: ' . $GLOBALS['empresa']['nit'], 0, 1, 'C');
        if (!empty($GLOBALS['empresa']['direccion'])) {
            $this->Cell(0, 1, $GLOBALS['empresa']['direccion'], 0, 1, 'C');
        }

        // Dibujar guías de perforación en cada página
        $this->drawHoleGuides();
    }

    protected function drawHoleGuides() {
        // Norma ISO 838 (2 agujeros):
        // - Distancia desde el borde del papel al centro del agujero: ~20–25 mm
        // - Distancia entre centros: ~80 mm (8 cm)
        // - Diámetro de agujero: ~6 mm (radio 3 mm)

        // Centro de los agujeros medido desde el borde físico izquierdo de la hoja
        $x = 12; // mm desde el borde izquierdo de la hoja (dentro de la zona de margen)

        // Calcular altura de la página y centrar el par de agujeros
        $pageHeight = $this->getPageHeight();
        $offset = 40;                  // 40 mm a cada lado del eje => 80 mm entre agujeros
        $yCenter = $pageHeight / 2;    // Eje horizontal de referencia
        $yTop = $yCenter - $offset;    // agujero superior
        $yBottom = $yCenter + $offset; // agujero inferior

        $radius = 3; // radio de 3 mm => diámetro 6 mm

        $this->SetDrawColor(180, 180, 180);
        $this->SetLineWidth(0.2);
        // Círculos sólo de contorno, sin relleno
        $this->Circle($x, $yTop, $radius, 0, 360, 'D');
        $this->Circle($x, $yBottom, $radius, 0, 360, 'D');
    }

    public function Footer() {
        // Position slightly higher to separar mejor del margen inferior
        $this->SetY(-30);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(128, 128, 128);

        // Footer content (record number, page number, copyright)
        $this->Cell($this->getPageWidth() * 0.45, 4, 'Registro: ' . $this->registro_nro . ' | Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'L');
        $this->Cell(0, 4, $GLOBALS['empresa']['nombre'] . ' ' . date("Y"), 0, 1, 'R');

        // Draw a separator line between pagination y texto legal
        $this->Ln(1);
        $this->SetLineStyle(array('width' => 0.2, 'color' => array(150, 150, 150)));
        $this->Cell(0, 0, '', 'T', 1, 'C');
        // Reducir aún más la separación para acercar el texto legal a la regla
        $this->Ln(0.2);

        // Legal text with padding and smaller font
        $this->SetFont('helvetica', 'I', 6);
        $legal_text = 'Este documento es una representación impresa fiel de los registros electrónicos diligenciados y firmados por los profesionales durante la prestación del servicio. Su generación ocurre únicamente a solicitud expresa del paciente, de la entidad aseguradora, o por requerimiento de autoridad judicial competente, conforme a los casos autorizados por la ley.  El tratamiento de los datos personales (incluyendo los sensibles) aquí consignados se rige por los principios de confidencialidad y seguridad, en estricto cumplimiento del marco normativo colombiano. Esto incluye la Ley Estatutaria 1581 de 2012 (Habeas Data) y la Ley 2015 de 2020 (Historia Clínica Electrónica Interoperable).  El registro está basado en estándares HL7-FHIR y cumple con la implementación obligatoria de la Interoperabilidad de la Historia Clínica Electrónica y el Resumen Digital de Atención en Salud (RDA), conforme a la Resolución 1888 de 2025.';

        $side_padding = 20;
        $this->SetX($this->getMargins()['left'] + $side_padding);
        $text_width = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'] - (2 * $side_padding);
        // Texto legal justificado para una apariencia más profesional
        $this->MultiCell($text_width, 0, $legal_text, 0, 'J', false, 1, '', '', true, 0, false, true, 0, 'T');
    }
}

// Crear PDF (tamaño carta para Colombia)
$pdf = new CustomPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'LETTER', true, 'UTF-8', false);
// Usar el ID de la atención como número de registro visible (controlado por AUTO_INCREMENT)
$pdf->registro_nro = $id;

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($empresa['nombre']);
// Nombre del archivo según Anexo 2: TAP{NIT}-{FACTURA/REGISTRO}-{DOC_PACIENTE}.pdf
$nit_limpio = preg_replace('/[^0-9]/', '', $GLOBALS['empresa']['nit']);
$doc_paciente = preg_replace('/[^a-zA-Z0-9]/', '', $atencion['id_paciente'] ?? 'SIN_DOC');
$filename = 'TAP' . $nit_limpio . '-' . $id . '-' . $doc_paciente . '.pdf';
$pdf->SetTitle($filename);
$pdf->SetSubject('Atención a Pacientes');
// Aumentar el margen izquierdo en 8 mm para permitir perforación/legajado
$pdf->SetMargins(18, 25, 7.5); // Izq 1.8cm, der ~0.75cm
// Aumentar levemente el margen inferior efectivo para evitar que los textos largos
// se acerquen demasiado a la paginación y al bloque legal del footer.
$pdf->SetAutoPageBreak(TRUE, 30); // bottom margin 30mm
$pdf->AddPage();

// Define las secciones y los campos que pertenecen a cada una
$secciones = [
    'Información Interna' => [
        /* 'registro', */
        'fecha',
        'ambulancia',
        'servicio',
        'pagador',
        'quien_informo',
        'hora_despacho',
        'hora_llegada',
        'hora_ingreso',
        'hora_final',
        'conductor',
        'cc_conductor',
        'registro_conductor',
        'tripulante',
        'tipo_id_tripulante',
        'cc_tripulante',
        'registro_tripulante',
        'medico_tripulante',
        'tipo_id_medico',
        'cc_medico',
        'registro_medico',
        'direccion_servicio',
        'localizacion',
        'tipo_traslado'
    ],
    'Información del Traslado' => [
        'triage_escena', 'hora_salida_escena', 'codigo_cups_traslado', 'codigo_reps_origen', 'hora_recepcion_paciente', 'estado_ingreso', 'codigo_reps_destino'
    ],
    'Traslado Secundario / Redondo' => [
        'km_inicial', 'km_final', 'distancia_recorrida', 'horas_espera', 'estado_final_traslado', 'eventos_traslado'
    ],
    'Identificación del Paciente' => [
        'nombres_paciente',
        'tipo_identificacion',
        'id_paciente',
        'genero_nacer',
        'fecha_nacimiento',
        'direccion_domicilio',
        'telefono_paciente',
        'barrio_paciente',
        'ciudad',
        'atencion_en',
        'eps_nombre',
        'etnia',
        // OJO: ya NO va 'especificar_otra' aquí
        'discapacidad'
    ],
    'Datos del Acompañante' => [
        'nombre_acompanante', 'parentesco_acompanante', 'id_acompanante'
    ],
    'Contexto Clínico' => [
        'diagnostico_principal', 'motivo_traslado', 'escena_paciente'
    ],
    'Accidente de tránsito / Vehículo' => [
        'tipo_vehiculo_accidente',
        'placa_vehiculo_involucrado',
        'conductor_accidente',
        'documento_conductor_accidente',
        'aseguradora_soat',
        'numero_poliza'
    ],
    'Antecedentes Médicos' => [
        'ant_alergicos_sn', 'ant_alergicos_cual',
        'ant_ginecoobstetricos_sn', 'ant_ginecoobstetricos_cual',
        'ant_patologicos_sn', 'ant_patologicos_cual',
        'ant_quirurgicos_sn', 'ant_quirurgicos_cual',
        'ant_traumatologicos_sn', 'ant_traumatologicos_cual',
        'ant_toxicologicos_sn', 'ant_toxicologicos_cual',
        'ant_familiares_sn', 'ant_familiares_cual'
    ],
    'Datos Clínicos' => [
        'frecuencia_cardiaca', 'frecuencia_respiratoria', 'spo2',
        'tension_arterial', 'glucometria', 'temperatura', 'rh',
        'llenado_capilar', 'peso', 'talla', 'escala_glasgow'
    ],
    'Escala de Riesgo de Caídas (Downton)' => [
        'downton_total'
    ],
    'Oxigenoterapia' => [
        'oxigeno_dispositivo', 'oxigeno_flujo', 'oxigeno_fio2'
    ],
    'Examen Físico' => [
        'examen_fisico'
    ],
    'Medicamentos Aplicados' => [
        'medicamentos_aplicados'
    ],
    'Procedimientos' => [
        'procedimientos'
    ],
    'Consumo durante el Servicio' => [
        'consumo_servicio'
    ],
    // Movida al final para ir en la misma página que las firmas
    // Ahora enfocada en datos de la entidad receptora y de la tripulación/médico receptor
    'Datos Entidad Receptora' => [
        'nombre_ips_receptora',
        'nit_ips_receptora',
        'codigo_reps_destino',
        // Datos del profesional que recibe
        'nombre_medico_receptor',
        'tipo_id_medico_receptor',
        'id_medico_receptor',
        'registro_md_receptor'
    ],
];

// Paleta de colores replicada del CSS para los bordes de sección.
// Formato: [R, G, B]
$seccion_colores = [
    'Información Interna' => [187, 222, 251], // Azul claro (#bbdefb)
    'Datos Entidad Receptora' => [220, 237, 200], // Verde claro (#dcedc8)
    'Datos del Acompañante' => [197, 202, 233], // Índigo suave (#c5cae9)
    'Identificación del Paciente' => [197, 202, 233], // Índigo suave (#c5cae9)
    'Contexto Clínico' => [255, 224, 178], // Naranja suave (#ffe0b2)
    'Antecedentes Médicos' => [248, 187, 208], // Rosa suave (#f8bbd0)
    'Oxigenoterapia' => [179, 229, 252], // Azul más claro
    'Datos Clínicos' => [178, 223, 219], // Verde azulado (#b2dfdb)
    'Escala de Riesgo de Caídas (Downton)' => [220, 237, 200], // Verde claro
    'Firmas' => [207, 216, 220], // Gris (#cfd8dc)
    'Adjuntos' => [224, 224, 224], // Gris claro (#e0e0e0)
    'Información del Traslado (Res. 2284)' => [255, 245, 157], // Amarillo suave
    'Traslado Secundario / Redondo' => [255, 204, 188], // Rojo/Naranja suave
];

// --- Renderizado del PDF por Secciones ---

$page_width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
$space_between_cols = 1.5;
$label_width = 22; // Ancho para las etiquetas (compactas)
$column_pair_width = ($page_width - (2 * $space_between_cols)) / 3; // Ancho para un par de etiqueta+valor
$value_width = $column_pair_width - $label_width; // Ancho para los valores

// Campos que se mostrarán en ancho completo.
$full_width_fields = ['diagnostico_principal', 'motivo_traslado', 'examen_fisico', 'procedimientos', 'consumo_servicio', 'medicamentos_aplicados', 'ant_alergicos_cual', 'ant_ginecoobstetricos_cual', 'ant_patologicos_cual', 'ant_quirurgicos_cual', 'ant_traumatologicos_cual', 'ant_toxicologicos_cual', 'ant_familiares_cual', 'antecedentes', 'eventos_traslado'];

// Renderizar todas las secciones EXCEPTO "Datos Entidad Receptora" en la primera página
foreach ($secciones as $titulo_seccion => $campos) {
    if ($titulo_seccion === 'Datos Entidad Receptora') {
        continue; // se dibujará más adelante, encima de las firmas
    }

    // Estilos para las celdas de datos: borde punteado sutil y más espaciado vertical.
    $color_celda = $seccion_colores[$titulo_seccion] ?? [150, 170, 200];
    $pdf->setCellPaddings(1, 0.2, 1, 0.2);
    $pdf->SetLineStyle(array('width' => 0.1, 'dash' => 1, 'color' => $color_celda));

    // Dibujar cabecera de la sección como la primera fila de la grilla (modo compacto)
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(37, 47, 96);
    $pdf->SetFillColor(245, 245, 245); // Gris sutil para el título
    
    // Secciones compactas que comparten línea con sus campos
    $compact_sections = ['Escala de Riesgo de Caídas (Downton)', 'Oxigenoterapia'];
    $is_compact = in_array($titulo_seccion, $compact_sections);
    
    if ($titulo_seccion == 'Información Interna') {
        // Título de la sección a la izquierda
        $pdf->Cell($page_width / 2, 3, $titulo_seccion, 1, 0, 'L', true);
        // Número de Registro a la derecha
        $pdf->SetFont('pdfacourier', 'B', 12);
        $pdf->SetTextColor(255, 0, 0); // Rojo
        // Mostrar el ID de la atención como número de registro
        $pdf->Cell($page_width / 2, 4, 'Registro: ' . $id, 0, 1, 'R', false);
        // Restaurar color de texto para las siguientes secciones
        $pdf->SetTextColor(37, 47, 96);
    } elseif ($is_compact) {
        // Para secciones compactas, el título se dibujará inline con los campos
        // No dibujar el título aquí, se manejará en el renderizado de campos
    } else {
        $pdf->MultiCell($page_width, 3, $titulo_seccion, 1, 'L', true, 1);
    }

    // Fuente para las celdas de datos (valores en 9pt para mejor legibilidad)
    $pdf->SetFont('helvetica', '', 9);

    // Special handling for Oxigenoterapia: show all 3 fields in one line
    if ($titulo_seccion === 'Oxigenoterapia') {
        $oxigeno_dispositivo = $atencion['oxigeno_dispositivo'] ?? '-';
        $oxigeno_flujo = $atencion['oxigeno_flujo'] ?? '-';
        $oxigeno_fio2 = $atencion['oxigeno_fio2'] ?? '-';
        
        $combined_value = "Dispositivo: $oxigeno_dispositivo | Flujo: $oxigeno_flujo L/min | FiO2: $oxigeno_fio2%";
        
        $pdf->SetFillColor(255, 255, 255);
        $pdf->MultiCell($page_width, 2, $combined_value, 1, 'L', true, 1, '', '', true, 0, false, true, 2, 'M');
        
        $pdf->setCellPaddings(0, 0, 0, 0);
        continue; // Skip normal field processing for this section
    }

    // Mostrar siempre todos los campos de la sección
    $fields_in_section = $campos;
    $num_fields = count($fields_in_section);
    $i = 0;

    while ($i < $num_fields) {
        $campo1 = $fields_in_section[$i];
        $label1 = $titulos[$campo1] ?? ucfirst(str_replace('_', ' ', $campo1));
        $value1 = $atencion[$campo1] ?? '-';

        // Lógica de compatibilidad para municipio/ciudad
        if ($campo1 === 'municipio' && empty($atencion['municipio']) && !empty($atencion['ciudad'])) {
            $value1 = $atencion['ciudad'];
        }
        if ($campo1 === 'ciudad' && !empty($atencion['municipio'])) {
            $i++; // Si ya usamos 'municipio', saltamos el campo 'ciudad' para no duplicarlo.
            continue;
        }

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

            if ($campo1 == 'downton_total') {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(37, 47, 96);
                
                if ($value1 !== null && $value1 !== '') {
                    $riesgo = ($value1 >= 2) ? 'Riesgo Alto' : 'Riesgo Bajo';
                    $display_value = $value1 . ' puntos (' . $riesgo . ')';
                } else {
                    $display_value = 'No evaluado';
                }
                
                // Single line: Label + Value
                $label_width = $page_width * 0.4;
                $value_width = $page_width * 0.6;
                
                $pdf->SetFillColor(245, 245, 245);
                $pdf->MultiCell($label_width, 2, $label1, 1, 'L', true, 0, '', '', true, 0, false, true, 2, 'M');
                $pdf->SetFillColor(255, 255, 255);
                $pdf->MultiCell($value_width, 2, $display_value, 1, 'L', true, 1, '', '', true, 0, false, true, 2, 'M');
                
                $i++;
                continue;
            }
            
            if ($campo1 == 'medicamentos_aplicados' && !empty(trim($value1))) {
                $medicamentos = json_decode($value1, true);
                if (is_array($medicamentos) && !empty($medicamentos)) {
                    // Dibujar cabecera de la tabla de medicamentos con el mismo estilo
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->SetFillColor(245, 245, 245);
                    $col_widths = [$page_width * 0.15, $page_width * 0.45, $page_width * 0.20, $page_width * 0.20];
                    $pdf->Cell($col_widths[0], 3, 'Hora', 1, 0, 'C', true);
                    $pdf->Cell($col_widths[1], 3, 'Nombre', 1, 0, 'C', true);
                    $pdf->Cell($col_widths[2], 3, 'Dosis', 1, 0, 'C', true);
                    $pdf->Cell($col_widths[3], 3, 'Vía', 1, 1, 'C', true);

                    // Dibujar filas de medicamentos
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetFillColor(255, 255, 255);
                    foreach ($medicamentos as $med) {
                        $h_hora = $pdf->getStringHeight($col_widths[0], $med['hora'] ?? '-');
                        $h_nombre = $pdf->getStringHeight($col_widths[1], $med['nombre'] ?? '-');
                        $h_dosis = $pdf->getStringHeight($col_widths[2], $med['dosis'] ?? '-');
                        $h_via = $pdf->getStringHeight($col_widths[3], $med['via'] ?? '-');
                        $row_height = max($h_hora, $h_nombre, $h_dosis, $h_via, 3);

                        $pdf->MultiCell($col_widths[0], $row_height, $med['hora'] ?? '-', 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
                        $pdf->MultiCell($col_widths[1], $row_height, $med['nombre'] ?? '-', 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
                        $pdf->MultiCell($col_widths[2], $row_height, $med['dosis'] ?? '-', 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
                        $pdf->MultiCell($col_widths[3], $row_height, $med['via'] ?? '-', 1, 'L', true, 1, '', '', true, 0, false, true, $row_height, 'M');
                    }
                }
                $i++;
                continue;
            }

            // Para campos de ancho completo, siempre mostrar la etiqueta en una celda separada.
            $pdf->SetFont('helvetica', '', 7); // Etiqueta (títulos de campos)
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->MultiCell($page_width, 3, $label1, 1, 'L', true, 1, '', '', true, 0, false, true, 3, 'M');
            
            // Altura dinámica para la celda de valor.
            // Si el campo está vacío o solo tiene guion, usamos un guion con altura mínima
            // para evitar grandes espacios en blanco sin ocultar la sección.
            if (empty(trim($value1)) || trim($value1) === '-' ) {
                $value1 = '-';
                $cell_height = 3;
            } else {
                $text_height = $pdf->getStringHeight($page_width - 4, $value1); // -4 for horizontal padding
                // Limitar la altura máxima para evitar bloques excesivos
                $cell_height = min(max(4, $text_height + 2), 40);
            }
            $pdf->MultiCell($page_width, $cell_height, $value1, 1, 'L', true, 1, '', '', true, 0, false, true, $cell_height, 'T');
            $i++;
            continue;
        }

        $campo2 = ($i + 1 < $num_fields) && !in_array($fields_in_section[$i + 1], $full_width_fields) ? $fields_in_section[$i + 1] : null;
        $campo3 = ($i + 2 < $num_fields) && !in_array($fields_in_section[$i + 2], $full_width_fields) ? $fields_in_section[$i + 2] : null;

        $pdf->SetFont('helvetica', '', 9);
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

        $row_height = max($h1, $h2, $h3, 3);

        $startY = $pdf->GetY();
        
        // Para secciones compactas, renderizar título inline en la primera celda
        if ($is_compact && $i === 0) {
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(245, 245, 245);
            $titulo_width = $page_width * 0.25; // 25% para el título de sección
            $pdf->Cell($titulo_width, $row_height, $titulo_seccion, 1, 0, 'L', true);
            // Ajustar el ancho disponible para los campos
            $remaining_width = $page_width - $titulo_width;
            $label_width_adjusted = 18; // Reducir ancho de etiquetas
            $value_width_adjusted = ($remaining_width - (2 * $space_between_cols)) / 3 - $label_width_adjusted;
        } else {
            $label_width_adjusted = $label_width;
            $value_width_adjusted = $value_width;
        }

        // Celda 1
        $pdf->SetFont('helvetica', '', 7); // Fuente de etiqueta (títulos de campos)
        $pdf->SetTextColor(37, 47, 96); // Color de título
        $pdf->SetFillColor(245, 245, 245); // Gris sutil
        $pdf->MultiCell($label_width_adjusted, $row_height, $label1, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(37, 47, 96); // Color para el valor
        $pdf->SetFillColor(255, 255, 255);
        $pdf->MultiCell($value_width_adjusted, $row_height, $value1, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');

        $current_x = $pdf->GetX();

        if ($campo2) {
            $x_col2 = $current_x + $space_between_cols;
            $pdf->SetY($startY);
            $pdf->SetX($x_col2);
            // Celda 2
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96); // Color de título
            $pdf->SetFillColor(245, 245, 245); // Gris sutil
            $pdf->MultiCell($label_width_adjusted, $row_height, $label2, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(37, 47, 96); // Color para el valor
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell($value_width_adjusted, $row_height, $value2, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $current_x = $pdf->GetX();
        }

        if ($campo3) {
            $x_col3 = $current_x + $space_between_cols;
            $pdf->SetY($startY);
            $pdf->SetX($x_col3);
            // Celda 3
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96); // Color de título
            $pdf->SetFillColor(245, 245, 245); // Gris sutil
            $pdf->MultiCell($label_width_adjusted, $row_height, $label3, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(37, 47, 96); // Color para el valor
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell($value_width_adjusted, $row_height, $value3, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
        }

        $pdf->Ln($row_height);

        if ($campo3) {
            $i += 3;
        } elseif ($campo2) {
            $i += 2;
        } else {
            $i++;
        }
    }
    // Resetear el padding para no afectar a los siguientes títulos de sección
    $pdf->setCellPaddings(0, 0, 0, 0);
}

// --- Sección "Datos Entidad Receptora" encima de las firmas ---
$campos_aseguradora = $secciones['Datos Entidad Receptora'] ?? [];
if (!empty($campos_aseguradora)) {
    // Garantizar que esta sección vaya en una página distinta de la primera
    if ($pdf->getPage() === 1) {
        $pdf->AddPage();
    }

    $titulo_seccion = 'Datos Entidad Receptora';
    $color_celda = $seccion_colores[$titulo_seccion] ?? [150, 170, 200];
    $pdf->setCellPaddings(1, 0.2, 1, 0.2);
    $pdf->SetLineStyle(array('width' => 0.1, 'dash' => 1, 'color' => $color_celda));

    // Cabecera de la sección
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(37, 47, 96);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->MultiCell($page_width, 3, $titulo_seccion, 1, 'L', true, 1);

    // Celdas de datos
    $pdf->SetFont('helvetica', '', 9);
    $fields_in_section = $campos_aseguradora;
    $num_fields = count($fields_in_section);
    $i = 0;

    while ($i < $num_fields) {
        $campo1 = $fields_in_section[$i];
        $label1 = $titulos[$campo1] ?? ucfirst(str_replace('_', ' ', $campo1));
        $value1 = $atencion[$campo1] ?? '-';

        if (in_array($campo1, $full_width_fields)) {
            if ($campo1 == 'downton_total') {
                // Renderizar título de sección inline con el campo
                $pdf->SetFont('helvetica', 'B', 6);
                $pdf->SetTextColor(37, 47, 96);
                $pdf->SetFillColor(245, 245, 245);
                $titulo_width = $page_width * 0.35; // 35% para el título
                $valor_width = $page_width - $titulo_width; // 65% para el valor
                
                $pdf->Cell($titulo_width, 3, $titulo_seccion, 1, 0, 'L', true);
                
                if ($value1 !== null && $value1 !== '') {
                    $riesgo = ($value1 >= 2) ? 'Riesgo Alto' : 'Riesgo Bajo';
                    $display_value = $value1 . ' puntos (' . $riesgo . ')';
                } else {
                    $display_value = 'No evaluado';
                }
                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Cell($valor_width, 3, $display_value, 1, 1, 'L', true);
                $i++;
                continue;
            }
            
            if ($campo1 == 'medicamentos_aplicados' && !empty(trim($value1))) {
                $medicamentos = json_decode($value1, true);
                if (is_array($medicamentos) && !empty($medicamentos)) {
                    // Dibujar cabecera de la tabla de medicamentos con el mismo estilo
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->SetFillColor(245, 245, 245);
                    $col_widths = [$page_width * 0.15, $page_width * 0.45, $page_width * 0.20, $page_width * 0.20];
                    $pdf->Cell($col_widths[0], 3, 'Hora', 1, 0, 'C', true);
                    $pdf->Cell($col_widths[1], 3, 'Nombre', 1, 0, 'C', true);
                    $pdf->Cell($col_widths[2], 3, 'Dosis', 1, 0, 'C', true);
                    $pdf->Cell($col_widths[3], 3, 'Vía', 1, 1, 'C', true);

                    // Dibujar filas de medicamentos
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetFillColor(255, 255, 255);
                    foreach ($medicamentos as $med) {
                        $h_hora = $pdf->getStringHeight($col_widths[0], $med['hora'] ?? '-');
                        $h_nombre = $pdf->getStringHeight($col_widths[1], $med['nombre'] ?? '-');
                        $h_dosis = $pdf->getStringHeight($col_widths[2], $med['dosis'] ?? '-');
                        $h_via = $pdf->getStringHeight($col_widths[3], $med['via'] ?? '-');
                        $row_height = max($h_hora, $h_nombre, $h_dosis, $h_via, 3);

                        $pdf->MultiCell($col_widths[0], $row_height, $med['hora'] ?? '-', 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
                        $pdf->MultiCell($col_widths[1], $row_height, $med['nombre'] ?? '-', 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
                        $pdf->MultiCell($col_widths[2], $row_height, $med['dosis'] ?? '-', 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
                        $pdf->MultiCell($col_widths[3], $row_height, $med['via'] ?? '-', 1, 'L', true, 1, '', '', true, 0, false, true, $row_height, 'M');
                    }
                }
                $i++;
                continue;
            }

            // Etiqueta
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->MultiCell($page_width, 3, $label1, 1, 'L', true, 1, '', '', true, 0, false, true, 3, 'M');

            // Valor
            if (empty(trim($value1)) || trim($value1) === '-') {
                $value1 = '-';
                $cell_height = 3;
            } else {
                $text_height = $pdf->getStringHeight($page_width - 4, $value1);
                $cell_height = min(max(4, $text_height + 2), 40);
            }
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell($page_width, $cell_height, $value1, 1, 'L', true, 1, '', '', true, 0, false, true, $cell_height, 'T');
            $i++;
            continue;
        }

        $campo2 = ($i + 1 < $num_fields) && !in_array($fields_in_section[$i + 1], $full_width_fields) ? $fields_in_section[$i + 1] : null;
        $campo3 = ($i + 2 < $num_fields) && !in_array($fields_in_section[$i + 2], $full_width_fields) ? $fields_in_section[$i + 2] : null;

        $pdf->SetFont('helvetica', '', 9);
        $h1 = $pdf->getStringHeight($value_width, $value1);

        $h2 = 0;
        if ($campo2) {
            $label2 = $titulos[$campo2] ?? ucfirst(str_replace('_', ' ', $campo2));
            $value2 = $atencion[$campo2] ?? '-';
            $h2 = $pdf->getStringHeight($value_width, $value2);
        }

        $h3 = 0;
        if ($campo3) {
            $label3 = $titulos[$campo3] ?? ucfirst(str_replace('_', ' ', $campo3));
            $value3 = $atencion[$campo3] ?? '-';
            $h3 = $pdf->getStringHeight($value_width, $value3);
        }

        $row_height = max($h1, $h2, $h3, 3);
        $startY = $pdf->GetY();

        // Columna 1
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(37, 47, 96);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->MultiCell($label_width, $row_height, $label1, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(37, 47, 96);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->MultiCell($value_width, $row_height, $value1, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');

        $current_x = $pdf->GetX();

        // Columna 2
        if ($campo2) {
            $x_col2 = $current_x + $space_between_cols;
            $pdf->SetY($startY);
            $pdf->SetX($x_col2);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->MultiCell($label_width, $row_height, $label2, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell($value_width, $row_height, $value2, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $current_x = $pdf->GetX();
        }

        // Columna 3
        if ($campo3) {
            $x_col3 = $current_x + $space_between_cols;
            $pdf->SetY($startY);
            $pdf->SetX($x_col3);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->MultiCell($label_width, $row_height, $label3, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(37, 47, 96);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell($value_width, $row_height, $value3, 1, 'L', true, 0, '', '', true, 0, false, true, $row_height, 'M');
        }

        $pdf->Ln($row_height);

        if ($campo3) {
            $i += 3;
        } elseif ($campo2) {
            $i += 2;
        } else {
            $i++;
        }
    }

    // Resetear padding
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

    // Validar que la firma no esté vacía y sea PNG o JPEG base64
    if (empty($imageData) || !preg_match('/^data:image\/(png|jpe?g);base64,/i', $imageData, $mimeMatch)) {
        return;
    }

    $mimeType = strtolower('image/' . $mimeMatch[1]); // image/png o image/jpeg
    $imageDataDecoded = base64_decode(preg_replace('/^data:image\/(png|jpe?g);base64,/i', '', $imageData));
    if (strlen($imageDataDecoded) === 0) {
        return;
    }

    // Guardar la firma como archivo temporal para mejor manejo
    $tempFile = tempnam(sys_get_temp_dir(), 'sig');
    file_put_contents($tempFile, $imageDataDecoded);

    // Para PNG: intentar aplanar (flatten). Para JPEG no es necesario.
    $flattened = ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg');
    $flattenFile = $tempFile . '_flat.png';

    if ($mimeType === 'image/png') {
        // Use GD if available
        if (extension_loaded('gd')) {
            $img = @imagecreatefromstring($imageDataDecoded);
            if ($img !== false) {
                $wImg = imagesx($img);
                $hImg = imagesy($img);
                $bg = imagecreatetruecolor($wImg, $hImg);
                // Fill with white background
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefilledrectangle($bg, 0, 0, $wImg, $hImg, $white);
                // Preserve quality when copying
                imagecopy($bg, $img, 0, 0, 0, 0, $wImg, $hImg);
                // Write flattened PNG
                imagepng($bg, $flattenFile, 6);
                imagedestroy($bg);
                imagedestroy($img);
                $flattened = true;
            }
        }

        // Fallback to Imagick if available
        if (!$flattened && class_exists('Imagick')) {
            try {
                $mi = new Imagick();
                $mi->readImageBlob($imageDataDecoded);
                $mi->setImageBackgroundColor('white');
                $mi = $mi->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $mi->setImageFormat('png');
                $mi->writeImage($flattenFile);
                $mi->clear();
                $mi->destroy();
                $flattened = true;
            } catch (Exception $e) {
                $flattened = false;
            }
        }
    }

    // Determine which file to use (flattened if available, otherwise original)
    $useFile = ($mimeType === 'image/png' && $flattened && file_exists($flattenFile)) ? $flattenFile : $tempFile;

    // If neither GD nor Imagick were available to safely flatten and the image likely
    // contains alpha, avoid calling TCPDF->Image with PNG alpha to prevent fatal.
    $imageInfo = @getimagesize($useFile);
    $imgWidth = $imageInfo[0] ?? 0;
    $imgHeight = $imageInfo[1] ?? 0;

    if ($imgWidth <= 0 || $imgHeight <= 0) {
        // Can't get dimensions; draw placeholder
        $pdf->SetXY($x, $y + ($h/2) - 3);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetTextColor(120,120,120);
        $pdf->Cell($w, 6, 'Firma (imagen inválida)', 0, 0, 'C');
        // Cleanup temp files
        if ($mimeType === 'image/png' && file_exists($flattenFile)) @unlink($flattenFile);
        if (file_exists($tempFile)) @unlink($tempFile);
        return;
    }

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

    // If we couldn't flatten and the image is PNG with alpha, avoid passing it directly
    // to TCPDF because it will raise a fatal error without GD/Imagick support.
    if ($mimeType === 'image/png' && !$flattened && !extension_loaded('gd') && !class_exists('Imagick')) {
        // Draw a placeholder and a hint
        $pdf->SetXY($x, $y + ($h/2) - 4);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetTextColor(120,120,120);
        $pdf->MultiCell($w, 4, 'Firma (no renderizada). Habilite GD o Imagick en PHP para mostrar PNG con transparencia.', 0, 'C', false, 1);
        if ($mimeType === 'image/png' && file_exists($flattenFile)) @unlink($flattenFile);
        if (file_exists($tempFile)) @unlink($tempFile);
        return;
    }

    // Dibujar la imagen (usar archivo aplanado si existe)
    $pdf->Image(
        $useFile,
        $xPos,
        $yPos,
        $displayWidth,
        $displayHeight,
        '',
        '',
        'T',      // Alinear en la parte SUPERIOR del cuadro
        false,
        300,
        '',
        false,
        false,
        0,
        false,
        false
    );

    // Eliminar archivos temporales
    if ($mimeType === 'image/png' && file_exists($flattenFile)) @unlink($flattenFile);
    if (file_exists($tempFile)) @unlink($tempFile);
}

// --- SECCIÓN DE FIRMAS Y DESISTIMIENTO ---
// Determinar si hay acompañante registrado para etiquetar correctamente el consentimiento
$hayAcompanante = !empty($atencion['nombre_acompanante']);

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
$firma_medico_presente = !empty($firma_medico);
$crew_sig_width = $firma_medico_presente ? floor(($col_width - 2) / 2) : $col_width;

// Caja Tripulante
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetFillColor(245, 245, 245);
$pdf->SetXY($left_x, $y_fila1);
$pdf->Cell($crew_sig_width, $title_height, 'Tripulante', 1, 0, 'C', true);
drawSignatureImage($pdf, $firma_paramedico, $left_x, $y_fila1 + $title_height, $crew_sig_width, $sig_box_height);

// Caja Medico Entrega (si existe)
if ($firma_medico_presente) {
    $medic_x = $left_x + $crew_sig_width + 2;
    $pdf->SetXY($medic_x, $y_fila1);
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell($crew_sig_width, $title_height, 'Medico Entrega', 1, 0, 'C', true);
    drawSignatureImage($pdf, $firma_medico, $medic_x, $y_fila1 + $title_height, $crew_sig_width, $sig_box_height);
}

// Info del tripulante/medico
$pdf->SetXY($left_x, $y_fila1 + $title_height + $sig_box_height);
$pdf->SetFont('helvetica', '', 5);
$tripulante_info = 'Nombre: ' . ($atencion['tripulante'] ?? 'N/A') . '  |  ID: ' . ($atencion['cc_tripulante'] ?? 'N/A');
$pdf->Cell($crew_sig_width, $info_height, $tripulante_info, 1, 0, 'C');
$bottom_y_left = $y_fila1 + $title_height + $sig_box_height + $info_height;

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
drawSignatureImage($pdf, $firma_medico_receptor, $right_x, $y_fila1 + $title_height, $col_width, $sig_box_height);
$pdf->SetXY($right_x, $y_fila1 + $title_height + $sig_box_height); // Posicionar para la línea de info
$pdf->SetFont('helvetica', '', 5);
$receptor_info = 'Nombre: ' . ($atencion['nombre_medico_receptor'] ?? 'N/A') . '  |  ID/Registro: ' . ($atencion['id_medico_receptor'] ?? 'N/A');
$pdf->Cell($col_width, $info_height, $receptor_info, 1, 0, 'C');
$bottom_y_right = $y_fila1 + $title_height + $sig_box_height + $info_height;

// Si hay firma del receptor IPS, dibujarla en una caja adicional debajo del Médico Receptor
if (!empty($firma_receptor_ips)) {
    $pdf->SetY($bottom_y_right + 2);
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->SetXY($right_x, $pdf->GetY());
    $pdf->Cell($col_width, $title_height, 'Firma Receptor IPS', 1, 0, 'C', true);
    // Dibujar la firma (usar el mismo alto que otras firmas)
    $sig_y = $pdf->GetY() + $title_height;
    drawSignatureImage($pdf, $firma_receptor_ips, $right_x, $sig_y, $col_width, $sig_box_height);
    // Información opcional debajo
    $info_y = $sig_y + $sig_box_height;
    $pdf->SetXY($right_x, $info_y);
    $pdf->SetFont('helvetica', '', 5);
    $pdf->Cell($col_width, $info_height, 'IPS receptora', 1, 0, 'C');
    // Actualizar la base inferior de la columna derecha (parte baja del recuadro IPS)
    $bottom_y_right = $info_y + $info_height;
}

// --- AVANZAR CURSOR PARA LA SIGUIENTE FILA ---
// Calcular la posición de la segunda fila tomando la base más baja entre columnas
$y_fila2 = max($bottom_y_left, $bottom_y_right) + 2; // +2 para un pequeño margen
$pdf->SetY($y_fila2);

// --- FILA INFERIOR (Consentimiento y Desistimiento) ---
$bottom_text_height = 10; // Altura para el texto legal
$bottom_sig_height = 18;  // Altura para el espacio de la firma
$bottom_info_height = 5;  // Altura para las líneas de Nombre/ID
$total_bottom_height = $title_height + $bottom_text_height + $bottom_sig_height + $bottom_info_height; // Altura total de esta caja

$firma_consentimiento = $firma_paciente;
$firma_desistimiento = $firmas['desistimiento'] ?? null;

if (!empty($firma_desistimiento)) {
    // --- Renderizar solo el cuadro de DESISTIMIENTO ---
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(255, 235, 238); // Rojo muy claro    
    $pdf->SetTextColor(211, 47, 47); // Rojo oscuro
    $pdf->Cell($page_width, $title_height, 'DESISTIMIENTO VOLUNTARIO', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', 'I', 5);
    $pdf->SetTextColor(37, 47, 96); // Restaurar color azul para el texto
    $refusal_text = 'Me niego a recibir la atención médica, traslado o internación sugerida por el sistema de emergencia médica. Eximo de toda responsabilidad a ' . $GLOBALS['empresa']['nombre'] . ' de las consecuencias de mi decisión, y asumo los riesgos que mi negativa pueda generar.';
    $pdf->MultiCell($page_width, 10, $refusal_text, 'LR', 'C', false, 1, '', '', true, 0, false, true, 10, 'M');
    
    drawSignatureImage($pdf, $firma_desistimiento, $pdf->GetX(), $pdf->GetY(), $page_width, 25);
    $pdf->SetY($pdf->GetY() + 25);
    // Obtener datos del firmante según haya o no acompañante
    if ($hayAcompanante) {
        $firmante_nombre = $atencion['nombre_acompanante'];
        $firmante_id = $atencion['id_acompanante'] ?? 'N/A';
        $labelNombre = 'Nombre acompañante: ';
        $labelId = 'ID acompañante: ';
    } else {
        $firmante_nombre = $atencion['nombres_paciente'] ?? 'Paciente';
        $firmante_id = $atencion['id_paciente'] ?? 'N/A';
        $labelNombre = 'Nombre paciente: ';
        $labelId = 'ID paciente: ';
    }

    $pdf->MultiCell($page_width / 2, 5, $labelNombre . $firmante_nombre, 1, 'C', false, 0);
    $pdf->MultiCell($page_width / 2, 5, $labelId . $firmante_id, 1, 'C', false, 1);

} else {
    // --- Renderizar el cuadro de CONSENTIMIENTO (por defecto o si se firmó) ---
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(232, 245, 233); // Verde muy claro
    $pdf->SetTextColor(27, 94, 32); // Verde oscuro
    $tituloConsent = $hayAcompanante ? 'CONSENTIMIENTO DEL ACOMPAÑANTE' : 'CONSENTIMIENTO DEL PACIENTE';
    $pdf->Cell($page_width, $title_height, $tituloConsent, 1, 1, 'C', true);

    $pdf->SetFont('helvetica', 'I', 5);
    $pdf->SetTextColor(37, 47, 96); // Restaurar color azul para el texto
    $consent_text = 'Declaro en mis facultades que autorizo mi traslado y atención a ' . $GLOBALS['empresa']['nombre'] . ' en el sistema de emergencias.';
    $pdf->MultiCell($page_width, 10, $consent_text, 'LR', 'C', false, 1, '', '', true, 0, false, true, 10, 'M');
    
    drawSignatureImage($pdf, $firma_consentimiento, $pdf->GetX(), $pdf->GetY(), $page_width, 25);
    $pdf->SetY($pdf->GetY() + 25);
    // Obtener datos del firmante (acompañante o paciente)
    $firmante_nombre = !empty($atencion['nombre_acompanante']) ? $atencion['nombre_acompanante'] : ($atencion['nombres_paciente'] ?? 'Paciente');
    $firmante_id = !empty($atencion['id_acompanante']) ? $atencion['id_acompanante'] : ($atencion['id_paciente'] ?? 'N/A');
    $pdf->MultiCell($page_width / 2, 5, 'Nombre: ' . $firmante_nombre, 1, 'C', false, 0);
    $pdf->MultiCell($page_width / 2, 5, 'ID: ' . $firmante_id, 1, 'C', false, 1);
}

// --- SECCIÓN DE ADJUNTOS ---
// Cargar adjuntos desde atenciones_att (contenido binario + tipo_adjunto)
$stmt_att = $conn->prepare("SELECT tipo_adjunto, contenido, nombre_archivo FROM atenciones_att WHERE atencion_id = ?");
if ($stmt_att) {
    $stmt_att->bind_param('i', $id);
    $stmt_att->execute();
    $res_att = $stmt_att->get_result();
    $attachments = [];
    while ($r = $res_att->fetch_assoc()) { $attachments[] = $r; }
    $stmt_att->close();

    if (!empty($attachments)) {
        // Si estamos muy cerca del final de la página, usar una nueva página; de lo contrario, reutilizar espacio
        if ($pdf->GetY() > ($pdf->getPageHeight() - 60)) {
            $pdf->AddPage();
        } else {
            $pdf->Ln(4);
        }
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(37, 47, 96);
        $pdf->Cell(0, 10, 'Adjuntos del Registro', 0, 1, 'C');
        $pdf->Ln(3);

        $pageW = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $cols = 3; // 3 imágenes por fila para mayor tamaño
        $gap = 4;  // espacio entre columnas
        $cellW = ($pageW - ($gap * ($cols - 1))) / $cols;
        $cellH = 55;

        $colIndex = 0;
        foreach ($attachments as $att) {
            $mime = strtolower($att['tipo_adjunto'] ?? '');
            $bin = $att['contenido'];
            $name = $att['nombre_archivo'] ?? '';

            // Saltar vacíos
            if ($bin === null || $bin === '' ) continue;

            $x = $pdf->GetX();
            $y = $pdf->GetY();

            if (strpos($mime, 'image/') === 0) {
                // Escribir a archivo temporal para usar con TCPDF->Image
                $ext = (strpos($mime, 'png') !== false) ? '.png' : '.jpg';
                $tmp = tempnam(sys_get_temp_dir(), 'att');
                $tmpFile = $tmp . $ext;
                file_put_contents($tmpFile, $bin);

                // Calcular ajuste manteniendo aspecto
                $info = @getimagesize($tmpFile);
                if ($info) {
                    $imgW = $info[0]; $imgH = $info[1];
                    $ratio = $imgW / max($imgH, 1);
                    $dispW = $cellW; $dispH = $dispW / max($ratio, 0.01);
                    if ($dispH > $cellH) { $dispH = $cellH; $dispW = $dispH * $ratio; }
                    $imgX = $x + (($cellW - $dispW) / 2);
                    $imgY = $y + (($cellH - $dispH) / 2);

                    // Marco
                    $pdf->Rect($x, $y, $cellW, $cellH, 'D');
                    // Imagen
                    $pdf->Image($tmpFile, $imgX, $imgY, $dispW, $dispH, '', '', '', false, 300);
                } else {
                    // Si no se pudo leer, solo dibujar marco y nombre
                    $pdf->Rect($x, $y, $cellW, $cellH, 'D');
                    $pdf->SetFont('helvetica', '', 7);
                    $pdf->MultiCell($cellW, $cellH, $name, 0, 'C', false, 1, $x, $y, true);
                }

                // Limpiar tmp
                if (isset($tmpFile) && file_exists($tmpFile)) @unlink($tmpFile);
                if (isset($tmp) && file_exists($tmp)) @unlink($tmp);
            } else {
                // No-imagen: dibujar caja con nombre y MIME
                $pdf->Rect($x, $y, $cellW, $cellH, 'D');
                $pdf->SetFont('helvetica', '', 7);
                $label = ($mime ?: 'archivo') . "\n" . ($name ?: '');
                $pdf->MultiCell($cellW, $cellH, $label, 0, 'C', false, 1, $x, $y, true);
            }

            // Avanzar columna
            $colIndex++;
            if ($colIndex % $cols === 0) {
                $pdf->Ln($cellH + $gap);
            } else {
                $pdf->SetX($pdf->GetX() + $cellW + $gap);
            }

            // Si se acerca al final de la página, saltar de página
            if ($pdf->GetY() > ($pdf->getPageHeight() - 40)) {
                $pdf->AddPage();
                $colIndex = 0;
            }
        }
    }
}

// Salida del PDF
$pdf->Output('registro_atencion_' . $id . '.pdf', 'I');