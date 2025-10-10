<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/titulos.php';
// Helpers para nombres del conductor (prefill desde at['conductor'])
$nombre_conductor = preg_split('/\\s+/', (string)($_POST['conductor'] ?? ''));
$primer_nombre_conductor = $nombre_conductor[0] ?? '';
$segundo_nombre_conductor = $nombre_conductor[1] ?? '';
$primer_apellido_conductor = $nombre_conductor[2] ?? '';
$segundo_apellido_conductor = $nombre_conductor[3] ?? '';

$rol = $_SESSION['usuario_rol'] ?? '';
if (!in_array(strtolower($rol), ['administrativo', 'master', 'secretaria'], true)) {
    die('Acceso no autorizado.');
}

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo 'ID invalido.';
    exit;
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function v($data, $key, $default = '-') {
    return !empty($data[$key]) ? h($data[$key]) : $default;
}

function v_emp($data, $key, $default = '-') {
    global $empresa;
    return !empty($data[$key]) ? h($data[$key]) : (isset($empresa[$key]) ? h($empresa[$key]) : $default);
}

function fv($data, $key) {
    return isset($data[$key]) && $data[$key] !== null ? h($data[$key]) : '';
}

function fv_decimal($data, $key) {
    if (!isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
        return '';
    }
    return h(number_format((float) $data[$key], 2, '.', ''));
}

function fv_time($data, $key) {
    if (!isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
        return '';
    }
    return h(substr((string) $data[$key], 0, 5));
}

function requiereFurtranPorPagador($pagador) {
    $pagador = strtoupper(trim((string) $pagador));
    if ($pagador === '') {
        return false;
    }
    foreach (['SOAT', 'ADRES'] as $keyword) {
        if (strpos($pagador, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

function obtenerAtencionPorId($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM atenciones WHERE id = ? LIMIT 1");
    if (!$stmt) {
        die('Error en la preparacion de la consulta: ' . h($conn->error));
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $stmt->close();
        return null;
    }
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row;
}

function aplicarValoresFurtranPorDefecto(array &$at) {
    if (empty($at['tipo_id_prestador'])) {
        $at['tipo_id_prestador'] = 'NIT';
    }
    if (empty($at['id_prestador'])) {
        $at['id_prestador'] = '900981955';
    }
    if (!isset($at['dv_prestador']) || $at['dv_prestador'] === '') {
        $at['dv_prestador'] = '1';
    }
}

$docIdOptions = [
    'NIT' => 'NIT - Numero de Identificacion Tributaria',
    'CC'  => 'CC - Cedula de ciudadania',
    'CE'  => 'CE - Cedula de extranjeria',
    'CN'  => 'CN - Certificado de nacido vivo',
    'PA'  => 'PA - Pasaporte',
    'RC'  => 'RC - Registro civil',
    'TI'  => 'TI - Tarjeta de identidad',
    'AS'  => 'AS - Adulto sin identificar',
    'MS'  => 'MS - Menor sin identificar',
    'CD'  => 'CD - Carne diplomatico',
    'SC'  => 'SC - Salvoconducto',
    'PE'  => 'PE - Permiso especial de permanencia',
    'DE'  => 'DE - Documento extranjero',
    'PT'  => 'PT - Permiso por proteccion temporal',
];

$formSections = [
    'Informacion del reclamo' => [
        ['name' => 'no_radicado_furtran', 'label' => 'No. radicado FURTRAN', 'input' => 'text'],
        ['name' => 'numero_factura', 'label' => 'Numero de factura', 'input' => 'text', 'attributes' => ['required' => 'required']],
        ['name' => 'total_folios', 'label' => 'Total folios', 'input' => 'number', 'attributes' => ['min' => '0', 'step' => '1']],
        ['name' => 'valor_facturado', 'label' => 'Valor facturado', 'input' => 'number', 'attributes' => ['min' => '0', 'step' => '0.01', 'required' => 'required'], 'formatter' => 'decimal'],
        ['name' => 'valor_reclamado', 'label' => 'Valor reclamado', 'input' => 'number', 'attributes' => ['min' => '0', 'step' => '0.01', 'required' => 'required'], 'formatter' => 'decimal'],
    ],
    'Prestador del servicio' => [
        ['name' => 'tipo_id_prestador', 'label' => 'Tipo de documento prestador', 'input' => 'select', 'options' => $docIdOptions, 'attributes' => ['required' => 'required']],
        ['name' => 'id_prestador', 'label' => 'Numero de documento prestador', 'input' => 'text', 'attributes' => ['required' => 'required']],
        ['name' => 'dv_prestador', 'label' => 'Digito de verificacion', 'input' => 'text', 'attributes' => ['required' => 'required']],
        ['name' => 'nombre_empresa_transportador', 'label' => 'Razon social del prestador', 'input' => 'text', 'attributes' => ['required' => 'required']],
        ['name' => 'codigo_habilitacion_empresa', 'label' => 'Codigo de habilitacion', 'input' => 'text', 'attributes' => ['required' => 'required']],
        ['name' => 'telefono_transportador', 'label' => 'Telefono de contacto', 'input' => 'text'],
    ],
    'Paciente' => [
        ['name' => 'tipo_identificacion', 'label' => 'Tipo de documento paciente', 'input' => 'select', 'options' => $docIdOptions, 'attributes' => ['required' => 'required']],
        ['name' => 'id_paciente', 'label' => 'Numero de documento paciente', 'input' => 'text', 'attributes' => ['required' => 'required']],
        ['name' => 'nombres_paciente', 'label' => 'Nombre completo del paciente', 'input' => 'text', 'attributes' => ['required' => 'required']],
        ['name' => 'fecha_nacimiento', 'label' => 'Fecha de nacimiento', 'input' => 'date'],
        ['name' => 'genero_nacer', 'label' => 'Genero', 'input' => 'select', 'options' => ['Masculino' => 'Masculino', 'Femenino' => 'Femenino']],
        ['name' => 'telefono_paciente', 'label' => 'Telefono del paciente', 'input' => 'text'],
    ],
    'Servicio prestado' => [
        ['name' => 'fecha', 'label' => 'Fecha del servicio', 'input' => 'date', 'attributes' => ['required' => 'required']],
        ['name' => 'hora_despacho', 'label' => 'Hora despacho', 'input' => 'time', 'formatter' => 'time', 'attributes' => ['required' => 'required']],
        ['name' => 'hora_llegada', 'label' => 'Hora llegada', 'input' => 'time', 'formatter' => 'time'],
        ['name' => 'hora_ingreso', 'label' => 'Hora ingreso', 'input' => 'time', 'formatter' => 'time'],
        ['name' => 'hora_final', 'label' => 'Hora finalizacion', 'input' => 'time', 'formatter' => 'time'],
        ['name' => 'direccion_servicio', 'label' => 'Direccion del servicio', 'input' => 'text'],
        ['name' => 'ciudad', 'label' => 'Ciudad', 'input' => 'text'],
        ['name' => 'tipo_traslado', 'label' => 'Tipo de traslado', 'input' => 'text'],
        ['name' => 'pagador', 'label' => 'Pagador', 'input' => 'text'],
        ['name' => 'aseguradora_soat', 'label' => 'Aseguradora / SOAT', 'input' => 'text'],
    ],
];

$decimalFields = ['valor_facturado', 'valor_reclamado'];
$uppercaseFields = ['tipo_id_prestador', 'id_prestador', 'dv_prestador', 'tipo_identificacion', 'id_paciente'];
$enumOptions = [
    'tipo_id_prestador' => array_keys($docIdOptions),
    'tipo_identificacion' => array_keys($docIdOptions),
    'genero_nacer' => ['Masculino', 'Femenino'],
];
$timeFields = ['hora_despacho', 'hora_llegada', 'hora_ingreso', 'hora_final'];

$tableColumns = [];
$tableColumnsQuery = $conn->query('SHOW COLUMNS FROM atenciones');
if ($tableColumnsQuery) {
    while ($column = $tableColumnsQuery->fetch_assoc()) {
        $tableColumns[$column['Field']] = true;
    }
    $tableColumnsQuery->free();
}

$filterExistingColumns = static function (array $fields) use ($tableColumns) {
    return array_values(array_filter($fields, static function ($field) use ($tableColumns) {
        return isset($tableColumns[$field]);
    }));
};

$processedSections = [];
$fieldOrder = [];
foreach ($formSections as $sectionTitle => $fields) {
    $sectionFields = [];
    foreach ($fields as $fieldConfig) {
        $fieldName = $fieldConfig['name'];
        if (isset($tableColumns[$fieldName])) {
            $sectionFields[] = $fieldConfig;
            $fieldOrder[] = $fieldName;
        }
    }
    if ($sectionFields) {
        $processedSections[$sectionTitle] = $sectionFields;
    }
}
$formSections = $processedSections;

$fieldOrder = array_values(array_unique($fieldOrder));
$fieldOrder = $filterExistingColumns($fieldOrder);
$decimalFields = $filterExistingColumns($decimalFields);
$uppercaseFields = $filterExistingColumns($uppercaseFields);
$timeFields = $filterExistingColumns($timeFields);

foreach (array_keys($enumOptions) as $enumField) {
    if (!isset($tableColumns[$enumField])) {
        unset($enumOptions[$enumField]);
    }
}

$mensaje = '';

$at = obtenerAtencionPorId($conn, $id);
if (!$at) {
    echo 'No se encontro el registro.';
    exit;
}
aplicarValoresFurtranPorDefecto($at);

$pagadorTexto = trim((string)($at['pagador'] ?? ''));
$requiereFurtran = requiereFurtranPorPagador($pagadorTexto);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_furtran'])) {
    if (!$requiereFurtran) {
        $mensaje = '<div class="alert alert-warning mb-3">Esta atencion tiene pagador ' . h($pagadorTexto !== '' ? $pagadorTexto : 'No definido') . ' y no requiere diligenciar FURTRAN.</div>';
    } else {
        $updateParts = [];
        $paramValues = [];
        $errors = [];

        foreach ($fieldOrder as $field) {
            $updateParts[] = "$field = ?";
            $rawValue = $_POST[$field] ?? '';
            if (is_array($rawValue)) {
                $rawValue = '';
            }
            $value = trim((string) $rawValue);

            if ($value === '') {
                $paramValues[$field] = null;
                continue;
            }

            if (isset($enumOptions[$field])) {
                if (!in_array($value, $enumOptions[$field], true)) {
                    $errors[] = "Valor no valido para {$field}.";
                    $paramValues[$field] = null;
                    continue;
                }
            }

            if (in_array($field, $uppercaseFields, true)) {
                $value = strtoupper($value);
            }

            if (in_array($field, $decimalFields, true)) {
                $normalized = str_replace(' ', '', $value);
                $normalized = str_replace(',', '.', $normalized);
                if (substr_count($normalized, '.') > 1) {
                    $lastDot = strrpos($normalized, '.');
                    $normalized = str_replace('.', '', substr($normalized, 0, $lastDot)) . substr($normalized, $lastDot);
                }
                $normalized = preg_replace('/[^0-9.\-]/', '', $normalized);
                $value = $normalized === '' ? null : number_format((float) $normalized, 2, '.', '');
            }

            if ($field === 'total_folios') {
                $numeric = preg_replace('/\D+/', '', $value);
                $value = $numeric === '' ? null : $numeric;
            }

            if (in_array($field, $timeFields, true)) {
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                    $value = null;
                } else {
                    $value = substr($value, 0, 8);
                }
            }

            $paramValues[$field] = $value === '' ? null : $value;
        }

        if ($errors) {
            $mensaje = '<div class="alert alert-danger mb-3">' . implode('<br>', array_map('h', $errors)) . '</div>';
        } else {
            $placeholders = implode(",
                        ", array_map(function ($f) {
                return "$f = ?";
            }, $fieldOrder));
            $types = str_repeat('s', count($fieldOrder)) . 'i';
            $stmt = $conn->prepare("UPDATE atenciones SET 
                        {$placeholders}
                     WHERE id = ?");
            if ($stmt) {
                $bindArgs = [];

                $bindArgs[] = $types;
                foreach ($fieldOrder as $field) {
                    $bindArgs[] = &$paramValues[$field];

                }
                $idParam = $id;
                $bindArgs[] = &$idParam;

                call_user_func_array([$stmt, 'bind_param'], $bindArgs);

                if ($stmt->execute()) {
                    $mensaje = '<div class="alert alert-success mb-3">Datos del FURTRAN actualizados correctamente.</div>';
                    $atRefreshed = obtenerAtencionPorId($conn, $id);
                    if ($atRefreshed) {
                        $at = $atRefreshed;
                        aplicarValoresFurtranPorDefecto($at);
                        $pagadorTexto = trim((string)($at['pagador'] ?? ''));
                        $requiereFurtran = requiereFurtranPorPagador($pagadorTexto);
                    }
                } else {
                    $mensaje = '<div class="alert alert-danger mb-3">Error al actualizar los datos: ' . h($stmt->error) . '</div>';
                }
                $stmt->close();
            } else {
                $mensaje = '<div class="alert alert-danger mb-3">Error al preparar la consulta: ' . h($conn->error) . '</div>';
            }
        }
    }
}
$pageTitle = 'Detalle FURTRAN - Registro #' . h($at['registro'] ?? '');
include 'header.php';

$pagadorTexto = trim((string)($at['pagador'] ?? ''));
?>
<div class="container my-4">
    <?php if ($requiereFurtran): ?>
        <form method="POST" action="obtener_detalle_furtran.php?id=<?= $id ?>">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Editar FURTRAN</h4>
                    <div>
                        <button type="submit" name="guardar_furtran" class="btn btn-success"><i class="bi bi-save"></i> Guardar Cambios</button>
                        <a href="generar_FURTRAN.php?id=<?= $id ?>" class="btn btn-primary" target="_blank">
                            <i class="bi bi-file-pdf"></i> Generar PDF
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?= $mensaje ?>

                    <?php if (empty($formSections)): ?>
                        <div class="alert alert-warning">No hay campos configurados para editar.</div>
                    <?php else: ?>
                        <?php $sectionIndex = 0; foreach ($formSections as $sectionTitle => $fields): ?>
                            <h5 class="card-title<?= $sectionIndex > 0 ? ' mt-4' : '' ?>"><?= h($sectionTitle) ?></h5>
                            <div class="detalle-furtran">
                                <?php foreach ($fields as $field): ?>
                                    <?php
                                    $name = $field['name'];
                                    $inputType = $field['input'] ?? 'text';
                                    $currentValue = isset($at[$name]) ? (string) $at[$name] : '';
                                    $formatter = $field['formatter'] ?? 'text';
                                    $displayValue = '';
                                    switch ($formatter) {
                                        case 'decimal':
                                            $displayValue = fv_decimal($at, $name);
                                            break;
                                        case 'time':
                                            $displayValue = fv_time($at, $name);
                                            break;
                                        default:
                                            $displayValue = fv($at, $name);
                                    }
                                    $attributes = $field['attributes'] ?? [];
                                    $attrHtml = '';
                                    foreach ($attributes as $attrName => $attrValue) {
                                        $attrHtml .= ' ' . $attrName . '="' . h($attrValue) . '"';
                                    }
                                    ?>
                                    <div class="detalle-row">
                                        <div class="campo"><?= h($field['label']) ?></div>
                                        <div class="valor">
                                            <?php if ($inputType === 'select'): ?>
                                                <select class="form-select form-select-sm" name="<?= h($name) ?>"<?= $attrHtml ?>>
                                                    <option value="">Seleccione</option>
                                                    <?php if (!empty($field['options']) && is_array($field['options'])): ?>
                                                        <?php foreach ($field['options'] as $optionValue => $optionLabel): ?>
                                                            <?php $optionValueStr = (string) $optionValue; ?>
                                                            <option value="<?= h($optionValueStr) ?>" <?= strtoupper($currentValue) === strtoupper($optionValueStr) ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="<?= h($inputType) ?>" class="form-control form-control-sm" name="<?= h($name) ?>" value="<?= $displayValue ?>"<?= $attrHtml ?>>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php $sectionIndex++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php else: ?>
        <?= $mensaje ?>
        <div class="alert alert-info">
            Esta atencion tiene pagador <?= h($pagadorTexto !== '' ? $pagadorTexto : 'no definido') ?> y no requiere generar FURTRAN.
        </div>
    <?php endif; ?>
</div>

<style>
.detalle-furtran {
  font-size: 0.95rem;
  line-height: 1.5;
}
.detalle-row {
  display: flex;
  padding: 12px 16px;
  border-bottom: 1px solid #e9ecef;
  background-color: var(--bs-body-bg);
}
.detalle-row:last-child {
  border-bottom: none;
}
.detalle-row:nth-child(odd) {
  background-color: var(--bs-tertiary-bg);
}
.campo {
  flex: 0 0 260px;
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--bs-secondary-color);
}
.valor {
  flex: 1;
  color: var(--bs-body-color);
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  font-size: 0.95rem;
}
.detalle-furtran .form-control,
.detalle-furtran .form-select {
  width: 100%;
}
@media (max-width: 576px) {
  .detalle-row {
    flex-direction: column;
    gap: 0.5rem;
    padding: 10px 12px;
  }
  .campo {
    flex: 0 0 auto;
    width: 100%;
  }
  .valor {
    width: 100%;
  }
}
</style>
<?php include 'footer.php'; $conn->close(); ?>
























