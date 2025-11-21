<?php
/**
 * Panel Administrativo FURIPS
 * Interfaz para generar reportes FURIPS 1
 */

require_once '../access_control.php';

// Solo administradores (Master, Administrador, Administrativo)
$rol = $_SESSION['rol'] ?? $_SESSION['usuario_rol'] ?? $_SESSION['role'] ?? '';
$rolesPermitidos = ['Master', 'Administrador', 'Administrativo', 'master', 'administrador', 'administrativo'];

if (!in_array($rol, $rolesPermitidos)) {
    echo '<div class="alert alert-danger">Acceso denegado. Solo administradores pueden acceder a esta sección.</div>';
    exit;
}

require_once '../header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="bi bi-file-earmark-text"></i> Generador de Reportes FURIPS</h2>
            <p class="text-muted">Formato Único de Reclamación - Circular ADRES 0008/2023</p>
            <hr>
        </div>
    </div>

    <!-- Formulario de generación -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Generar Nuevo Reporte FURIPS</h5>
                </div>
                <div class="card-body">
                    <form id="formGenerarFURIPS">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="codigo_habilitacion" class="form-label">Código de Habilitación</label>
                                <input type="text" class="form-control" id="codigo_habilitacion" name="codigo_habilitacion" 
                                       placeholder="Ej: 76001234567890" maxlength="12">
                                <small class="text-muted">Código asignado por la Dirección Departamental de Salud</small>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Nota:</strong> 
                            Se generará un archivo de texto plano con todas las atenciones de accidentes de tránsito 
                            y eventos catastróficos que no hayan sido reportadas previamente.
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-file-earmark-arrow-down"></i> Generar Archivo FURIPS
                        </button>
                    </form>

                    <div id="resultado" class="mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Información</h6>
                </div>
                <div class="card-body">
                    <h6>Plazo de Reporte</h6>
                    <p class="small">Los accidentes de tránsito deben reportarse a ADRES dentro de los <strong>2 días hábiles</strong> siguientes a la atención.</p>

                    <hr>

                    <h6>Formato del Archivo</h6>
                    <ul class="small">
                        <li>Tipo: Texto plano (.txt)</li>
                        <li>Separador: Coma (,)</li>
                        <li>Campos: 93</li>
                        <li>Nombre: FURIPS1{CÓDIGO}{FECHA}.txt</li>
                    </ul>

                    <hr>

                    <h6>Campos Incluidos</h6>
                    <ul class="small">
                        <li>Datos de la víctima</li>
                        <li>Datos del evento</li>
                        <li>Datos del vehículo</li>
                        <li>Datos de la atención</li>
                        <li>Datos del conductor</li>
                        <li>Transporte y movilización</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de atenciones pendientes -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Atenciones Pendientes de Reporte</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tablaPendientes">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Paciente</th>
                                    <th>Tipo de Evento</th>
                                    <th>Condición Víctima</th>
                                    <th>Días Pendiente</th>
                                </tr>
                            </thead>
                            <tbody id="bodyPendientes">
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        Cargando atenciones pendientes...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de reportes generados -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-check-circle"></i> Historial de Reportes Generados</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="tablaHistorial">
                            <thead>
                                <tr>
                                    <th>Fecha Generación</th>
                                    <th>Archivo</th>
                                    <th>Registros</th>
                                    <th>Rango de Fechas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="bodyHistorial">
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No hay reportes generados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Cargar atenciones pendientes
    cargarAtencionesPendientes();

    // Manejar envío del formulario
    $('#formGenerarFURIPS').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Mostrar loading
        $('#resultado').html(`
            <div class="alert alert-info">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Generando archivo FURIPS...
            </div>
        `).show();
        
        $.ajax({
            url: 'generar_furips.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    $('#resultado').html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle"></i> <strong>Error:</strong> ${response.error}
                        </div>
                    `);
                } else {
                    $('#resultado').html(`
                        <div class="alert alert-success">
                            <h5><i class="bi bi-check-circle"></i> Archivo generado exitosamente</h5>
                            <p class="mb-2"><strong>Archivo:</strong> ${response.archivo}</p>
                            <p class="mb-2"><strong>Total de registros:</strong> ${response.total_registros}</p>
                            <a href="${response.ruta}" class="btn btn-success btn-sm" download>
                                <i class="bi bi-download"></i> Descargar Archivo
                            </a>
                        </div>
                    `);
                    
                    // Recargar tabla de pendientes
                    cargarAtencionesPendientes();
                }
            },
            error: function(xhr, status, error) {
                $('#resultado').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle"></i> <strong>Error:</strong> ${error}
                    </div>
                `);
            }
        });
    });
});

function cargarAtencionesPendientes() {
    $.ajax({
        url: 'listar_pendientes_furips.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.length === 0) {
                $('#bodyPendientes').html(`
                    <tr>
                        <td colspan="6" class="text-center text-success">
                            <i class="bi bi-check-circle"></i> No hay atenciones pendientes de reporte
                        </td>
                    </tr>
                `);
            } else {
                let html = '';
                data.forEach(function(item) {
                    const diasPendiente = calcularDiasPendiente(item.fecha);
                    const claseFila = diasPendiente > 2 ? 'table-danger' : '';
                    
                    html += `
                        <tr class="${claseFila}">
                            <td>${item.id}</td>
                            <td>${item.fecha}</td>
                            <td>${item.nombres_paciente}</td>
                            <td>${item.tipo_evento}</td>
                            <td>${item.condicion_victima || 'N/A'}</td>
                            <td>
                                ${diasPendiente} día(s)
                                ${diasPendiente > 2 ? '<i class="bi bi-exclamation-triangle text-danger"></i>' : ''}
                            </td>
                        </tr>
                    `;
                });
                $('#bodyPendientes').html(html);
            }
        },
        error: function() {
            $('#bodyPendientes').html(`
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        Error al cargar atenciones pendientes
                    </td>
                </tr>
            `);
        }
    });
}

function calcularDiasPendiente(fecha) {
    const fechaAtencion = new Date(fecha);
    const hoy = new Date();
    const diferencia = hoy - fechaAtencion;
    return Math.floor(diferencia / (1000 * 60 * 60 * 24));
}
</script>

<?php require_once '../footer.php'; ?>
