<?php
// Iniciar la sesión al principio de todo para que las variables de sesión estén disponibles.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'conn.php';
require 'access_control.php'; // Asegurarse de que el usuario tiene sesión iniciada
require_once __DIR__ . '/titulos.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);


if (!isset($conn) || $conn->connect_error) {
    echo '<div class="alert alert-danger">Error de conexión a la base de datos: ' . ($conn->connect_error ?? 'Variable $conn no definida') . '</div>';
    exit;
}

$idAtencion = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
if ($idAtencion === false || $idAtencion <= 0) {
    echo '<div class="alert alert-danger">ID de atención inválido</div>';
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando la consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $idAtencion);
    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando la consulta: " . $stmt->error);
    }

    $meta = $stmt->result_metadata();
    $variables = [];
    $data = [];
    while ($campo = $meta->fetch_field()) {
        $variables[] = &$data[$campo->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $variables);
    $stmt->fetch();
    $stmt->close();

    if (empty($data)) {
        echo '<div class="alert alert-warning">No se encontró la atención con ID: ' . $idAtencion . '</div>';
        exit;
    }
    $bodyClass = 'detalle-atencion'; 

    // Definir el título dinámico y luego incluir el header
    $pageTitle = "Detalle de Atención | Registro #" . htmlspecialchars($data['registro']);
    include 'header.php';

    // --- NUEVA ESTRUCTURA DE SECCIONES ---
    $secciones = [
        'Información Interna' => [
            'fecha', 'ambulancia', 'servicio', 'pagador', 'quien_informo', 'hora_despacho', 'hora_llegada', 'hora_ingreso', 'hora_final', 'conductor', 'cc_conductor', 'tripulante', 'cc_tripulante', 'medico_tripulante', 'cc_medico', 'direccion_servicio', 'localizacion', 'ips_destino', 'tipo_traslado'
        ],
        'Datos para Aseguradora' => [
            'eps_nombre', 'aseguradora_soat', 'ips2', 'ips3', 'ips4'
        ],
        'Identificación del Paciente' => [
            'nombres_paciente', 'tipo_identificacion', 'id_paciente', 'genero_nacer', 'fecha_nacimiento', 'direccion_domicilio', 'telefono_paciente', 'barrio_paciente', 'ciudad', 'atencion_en', 'etnia', 'especificar_otra', 'discapacidad'
        ],
        'Datos del Acompañante' => [
            'nombre_acompanante', 'parentesco_acompanante', 'id_acompanante'
        ],
        'Contexto Clínico' => [
            'diagnostico_principal', 'motivo_traslado'
        ],
        'Datos Clínicos' => [
            'frecuencia_cardiaca', 'frecuencia_respiratoria', 'spo2', 'tension_arterial', 'glucometria', 'temperatura', 'rh', 'llenado_capilar', 'peso', 'talla', 'escala_glasgow'
        ],
        'Escala de Riesgo de Caídas (Downton)' => [
            'downton_total'
        ],
        'Oxigenoterapia' => [
            'oxigeno_dispositivo', 'oxigeno_flujo', 'oxigeno_fio2'
        ],
        'Antecedentes Médicos' => [
            'ant_patologicos_sn', 'ant_patologicos_cual', 'ant_alergicos_sn', 'ant_alergicos_cual', 'ant_quirurgicos_sn', 'ant_quirurgicos_cual', 'ant_traumatologicos_sn', 'ant_traumatologicos_cual', 'ant_toxicologicos_sn', 'ant_toxicologicos_cual', 'ant_ginecoobstetricos_sn', 'ant_ginecoobstetricos_cual', 'ant_familiares_sn', 'ant_familiares_cual'
        ],
        'Examen Físico y Procedimientos' => [
            'examen_fisico', 'procedimientos', 'consumo_servicio'
        ],
        'Medicamentos Aplicados' => [
            'medicamentos_aplicados'
        ],
        'Firmas y Adjuntos' => [
            'nombre_medico_receptor', 'id_medico_receptor', 'firma_paramedico', 'firma_medico', 'firma_paciente', 'firma_medico_receptor', 'firma_desistimiento', 'adjuntos'
        ]
    ];

    // Función para renderizar un campo
    function renderizar_campo($campo, $data, $titulos, $class = 'col-md-4') {
        $valor = $data[$campo] ?? null;
        // No renderizar campos completamente vacíos, excepto firmas y adjuntos
        if (empty($valor) && !in_array($campo, ['firma_paramedico', 'firma_medico', 'firma_paciente', 'firma_medico_receptor', 'firma_desistimiento', 'adjuntos', 'medicamentos_aplicados'])) {
            return;
        }

        echo "<div class='{$class} mb-3'>";
        echo "<div class='field-label'>" . ($titulos[$campo] ?? ucfirst(str_replace('_', ' ', $campo))) . ":</div>";
        echo "<div class='field-value'>";

        if (in_array($campo, ['firma_paramedico', 'firma_medico', 'firma_paciente', 'firma_medico_receptor', 'firma_desistimiento'])) {
            if (!empty($valor)) {
                if ($campo === 'firma_paciente') {
                    echo "<p class='text-muted small fst-italic'>" . htmlspecialchars($GLOBALS['empresa']['declaracion_legal'] ?? '') . "</p>";
                }
                echo "<img src='" . htmlspecialchars($valor ?? '') . "' alt='" . ($titulos[$campo] ?? $campo) . "' style='max-width: 100%; height: auto; border: 1px solid #ccc; padding: 5px; background-color: #fff;'>";
            } else {
                echo '<span class="text-muted fst-italic">Sin firma</span>';
            }
        } elseif ($campo === 'adjuntos') {
            if (!empty($valor)) {
                $adjuntos_array = json_decode($valor, true);
                if (is_array($adjuntos_array) && !empty($adjuntos_array)) {
                    echo '<div class="d-flex flex-wrap">';
                    foreach ($adjuntos_array as $adjunto) {
                        echo '<a href="' . htmlspecialchars($adjunto) . '" target="_blank" class="me-2 mb-2">
                                <img src="' . htmlspecialchars($adjunto) . '" alt="Adjunto" style="max-width: 100px; height: auto; border: 1px solid #ccc; border-radius: 4px;">
                              </a>';
                    }
                    echo '</div>';
                } else {
                    // No muestra nada si el JSON está vacío o es inválido
                }
            }
            // No muestra "Sin adjuntos" si el campo está vacío
        } elseif ($campo === 'medicamentos_aplicados') {
            if (!empty($valor)) {
                $medicamentos = json_decode($valor, true);
                if (is_array($medicamentos) && !empty($medicamentos)) {
                    echo '<table class="table table-sm table-bordered mt-2">';
                    echo '<thead><tr><th>Hora</th><th>Nombre</th><th>Dosis</th><th>Vía</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($medicamentos as $med) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($med['hora'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($med['nombre'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($med['dosis'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($med['via'] ?? '') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            }
        } elseif ($campo === 'downton_total') {
            if ($valor !== null && $valor !== '') {
                $riesgo = ($valor >= 2) ? 'Riesgo Alto' : 'Riesgo Bajo';
                echo htmlspecialchars($valor) . ' puntos (<span style="color:' . ($valor >= 2 ? 'red' : 'green') . ';">' . $riesgo . '</span>)';
            } else {
                echo '<span class="text-muted fst-italic">No evaluado</span>';
            }
        } else {
            echo htmlspecialchars($valor ?? 'Sin datos');
        }

        echo "</div></div>";
    }

    ?>
    <style>
        /* Estilo específico para reducir el tamaño del título en la vista de detalle */
        .form-section-view .section-header h3 {
            font-size: 1rem; /* Tamaño de fuente más pequeño */
            margin-bottom: 0; /* Eliminar margen inferior */
        }
    </style>
    <div class="d-flex justify-content-between mb-3">
        <div>
            <a href="consulta_atenciones.php" class="btn btn-secondary">← Volver</a>
            <a href="index.php" class="btn btn-success">Nuevo Registro</a>
        </div>
        <div>
            <a href="generar_pdf.php?id=<?php echo $idAtencion; ?>" class="btn btn-outline-danger" target="_blank">🖨️ Generar PDF</a>
        </div>
    </div>

    <div class="document-view p-4" style="background-color: #fff; border: 1px solid #dee2e6; border-radius: .25rem;">
        <h2 class="section-header d-flex justify-content-between align-items-center">
            <span>Detalle de Atención</span>
            <span style="color: red; font-weight: bold;">Registro: <?php echo htmlspecialchars($data['registro']); ?></span>
        </h2>

        <?php foreach ($secciones as $titulo_seccion => $campos_seccion): ?>
            <div class="form-section-view mb-4">
                <div class="section-header">
                    <h3><?php echo $titulo_seccion; ?></h3>
                </div>
                <div class="row">
                    <?php foreach ($campos_seccion as $campo): ?>
                        <?php renderizar_campo($campo, $data, $titulos, (in_array($campo, ['examen_fisico', 'procedimientos', 'consumo_servicio', 'diagnostico_principal', 'motivo_traslado', 'adjuntos', 'medicamentos_aplicados']) ? 'col-md-12' : 'col-md-4')); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
} finally {
    if (isset($result)) $result->close();
    if (isset($conn)) $conn->close();
}

include 'footer.php';