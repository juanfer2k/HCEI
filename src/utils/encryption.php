<?php
function cifrarDato($dato, $clave) {
    $iv = random_bytes(16);
    $cifrado = openssl_encrypt($dato, 'AES-256-CBC', $clave, 0, $iv);
    return base64_encode($iv . $cifrado);
}

function descifrarDato($datoCifrado, $clave) {
    $dato = base64_decode($datoCifrado);
    $iv = substr($dato, 0, 16);
    $cifrado = substr($dato, 16);
    return openssl_decrypt($cifrado, 'AES-256-CBC', $clave, 0, $iv);
}