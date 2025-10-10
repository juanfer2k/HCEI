﻿<?php
require_once __DIR__ . '/inventario_config.php';

// Devuelve encabezado e items de un inventario.
// @return array{0: ?array, 1: array}
function obtenerInventario(mysqli $conn, string $tabla, string $formUuid): array
{
    $stmt = $conn->prepare("SELECT * FROM {$tabla} WHERE form_uuid = ? ORDER BY id ASC");
    if ($stmt === false) {
        throw new RuntimeException('No se pudo preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param('s', $formUuid);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    $header = null;
    while ($row = $result->fetch_assoc()) {
        if ($header === null) {
            $header = [
                'form_uuid' => $row['form_uuid'],
                'ambulancia' => $row['ambulancia'],
                'placa' => $row['placa'],
                'fecha' => $row['fecha'],
                'responsable_general' => $row['responsable_general'],
                'auditor' => $row['auditor'],
                'created_at' => $row['created_at'],
                'created_by' => $row['created_by']
            ];
        }

        $items[] = [
            'seccion' => $row['seccion'],
            'codigo' => $row['item_codigo'],
            'nombre' => $row['item_nombre'],
            'cantidad' => (int)$row['cantidad'],
            'estado' => $row['estado'],
            'observaciones' => $row['observaciones'],
            'serial' => $row['serial'],
            'ubicacion' => $row['ubicacion'],
            'registro_invima' => $row['registro_invima'] ?? null,
            'lote' => $row['lote'] ?? null,
            'fecha_vencimiento' => $row['fecha_vencimiento'] ?? null,
            'fecha_revision' => $row['fecha_revision'],
            'responsable' => $row['responsable_item']
        ];
    }

    $stmt->close();

    return [$header, $items];
}

// Genera tabla HTML para PDF o exportes.
function generarHtmlInventario(string $titulo, array $header, array $items): string
{
    $logoPath = realpath(__DIR__ . '/ambulance.png');
    if ($logoPath === false) {
        $logoPath = 'ambulance.png';
    }
    $logoSrc = str_replace('\\', '/', $logoPath);

    $htmlHeader = sprintf(
        '<table style="width:100%%; font-size:10px;">
            <tr>
                <td style="width:20%%;">
                    <img src="%s" alt="Logo" style="height:50px;">
                </td>
                <td style="width:60%%; text-align:center; font-weight:bold; font-size:16px;">
                    %s
                </td>
                <td style="width:20%%; font-size:9px; text-align:right;">
                    Fecha: %s<br>Placa: %s
                </td>
            </tr>
            <tr>
                <td colspan="3" style="font-size:10px;">
                    Ambulancia: %s<br>
                    Responsable: %s<br>
                    Auditor interno: %s
                </td>
            </tr>
        </table>',
        htmlspecialchars($logoSrc),
        htmlspecialchars($titulo),
        htmlspecialchars($header['fecha'] ?? ''),
        htmlspecialchars($header['placa'] ?? ''),
        htmlspecialchars($header['ambulancia'] ?? ''),
        htmlspecialchars($header['responsable_general'] ?? ''),
        htmlspecialchars($header['auditor'] ?? '')
    );

    $htmlTable = '<table border="1" cellpadding="3" cellspacing="0" style="width:100%; font-size:9px;">'
        . '<thead>'
        . '<tr style="background-color:#f0f0f0;">
                <th>Seccion</th>
                <th>Codigo</th>
                <th>Item</th>
                <th>Cantidad</th>
                <th>Estado</th>
                <th>Serial</th>
                <th>Ubicacion</th>
                <th>Fecha ultima revision</th>
                <th>Responsable</th>
                <th>Observaciones</th>
            </tr>'
        . '</thead><tbody>';

    foreach ($items as $item) {
        $htmlTable .= '<tr>'
            . '<td>' . htmlspecialchars($item['seccion']) . '</td>'
            . '<td>' . htmlspecialchars($item['codigo']) . '</td>'
            . '<td>' . htmlspecialchars($item['nombre']) . '</td>'
            . '<td style="text-align:center;">' . (int)$item['cantidad'] . '</td>'
            . '<td style="text-transform:uppercase;">' . htmlspecialchars($item['estado']) . '</td>'
            . '<td>' . htmlspecialchars($item['serial'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($item['ubicacion'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($item['fecha_revision'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($item['responsable'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($item['observaciones'] ?? '') . '</td>'
            . '</tr>';
    }

    $htmlTable .= '</tbody></table>';

    $htmlFooter = '<table style="width:100%; margin-top:20px; font-size:10px; text-align:center;">
        <tr>
            <td>_____________________________<br>Responsable de ambulancia</td>
            <td>_____________________________<br>Auditor interno</td>
            <td>_____________________________<br>Coordinador de habilitacion</td>
        </tr>
    </table>';

    return $htmlHeader . '<br>' . $htmlTable . '<br>' . $htmlFooter;
}
?>
