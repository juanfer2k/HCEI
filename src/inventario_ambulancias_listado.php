<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/titulos.php';

$ambulancias = [];
if (!empty($empresa['ambulancias']) && is_array($empresa['ambulancias'])) {
    foreach ($empresa['ambulancias'] as $registro) {
        $texto = trim((string)$registro);
        if ($texto === '') {
            continue;
        }
        $placa = '';
        if (preg_match('/([A-Z]{3}\s*-?\d{2,3})$/i', $texto, $matches)) {
            $placa = strtoupper(str_replace([' ', '-'], '', $matches[1]));
        } else {
            $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $texto));
            $placa = substr($placa, -6);
        }
        $ambulancias[] = [
            'placa' => $placa,
            'descripcion' => $texto
        ];
    }
}

echo json_encode([
    'ok' => true,
    'ambulancias' => $ambulancias
], JSON_UNESCAPED_UNICODE);
?>
