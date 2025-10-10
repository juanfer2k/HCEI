<?php
// Incluye el archivo conn.php
include 'conn.php';

// Verifica si la conexión fue exitosa
if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
} else {
    echo "Conexión exitosa<br>";

    // Verifica si la base de datos usa InnoDB
    $result = mysqli_query($conn, "SELECT @@default_storage_engine");
    $row = mysqli_fetch_assoc($result);
    if ($row['@@default_storage_engine'] == 'InnoDB') {
        echo "Motor de almacenamiento: InnoDB<br>";
        // Desactiva autocommit para usar transacciones
        mysqli_autocommit($conn, false);
    } else {
        echo "Motor de almacenamiento: " . $row['@@default_storage_engine'] . "<br>";
    }

    // Datos de ejemplo para la inserción
    $registro = "REG-123";
    $fecha = date("Y-m-d");
    $ambulancia = "Móvil 1 JYQ-312"; // Valor predeterminado
    $tipo_traslado = "Urgencia";
    $hora_despacho = "10:00:00";
    $hora_llegada = "10:30:00";
    $hora_ingreso = "11:00:00";
    $hora_final = "12:00:00";
    $conductor = "Juan Pérez";
    $tripulante = "María García";
    $nombres_paciente = "Carlos Rodríguez";
    $tipo_identificacion = "CC";
    $id_paciente = "123456789";
    $genero_nacer = "Masculino";
    $ciudad = "Bogotá";

    // Consulta SQL para la inserción
    $sql = "INSERT INTO atenciones (registro, fecha, ambulancia, tipo_traslado, hora_despacho, hora_llegada, hora_ingreso, hora_final, conductor, tripulante, nombres_paciente, tipo_identificacion, id_paciente, genero_nacer, ciudad) VALUES ('$registro', '$fecha', '$ambulancia', '$tipo_traslado', '$hora_despacho', '$hora_llegada', '$hora_ingreso', '$hora_final', '$conductor', '$tripulante', '$nombres_paciente', '$tipo_identificacion', '$id_paciente', '$genero_nacer', '$ciudad')";

    // Intenta insertar el registro
    if (mysqli_query($conn, $sql)) {
        echo "Registro insertado correctamente<br>";
        if ($row['@@default_storage_engine'] == 'InnoDB') {
            mysqli_commit($conn);
            echo "Transacción confirmada<br>";
        }
    } else {
        echo "Error al insertar: " . mysqli_error($conn) . "<br>";
        if ($row['@@default_storage_engine'] == 'InnoDB') {
            mysqli_rollback($conn);
            echo "Transacción revertida<br>";
        }
    }

    // Verifica el estado de autocommit
    $result = mysqli_query($conn, "SELECT @@autocommit");
    $row = mysqli_fetch_assoc($result);
    echo "Autocommit está: " . ($row['@@autocommit'] == 1 ? "activado" : "desactivado") . "<br>";
}

// Cierra la conexión
mysqli_close($conn);
?>