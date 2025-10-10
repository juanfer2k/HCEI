<?php
$servername = "localhost"; // Usar 127.0.0.1 en lugar de localhost
$username   = "elcerrit_firma3"; // Nombre de usuario correcto (sin espacios)
$password   = "LN3PfQTQ(%D&";
$dbname     = "elcerrit_firma3";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Error de conexion: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

