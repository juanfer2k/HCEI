<?php
require 'conn.php';

echo "<h1>Database Fixer (Full Schema)</h1>";

// Check connection
if ($conn->connect_error) {
    die("<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>");
} else {
    echo "<p style='color:green'>Database connected successfully.</p>";
}

// --- 1. Table: tripulacion ---
$sql_tripulacion = "CREATE TABLE IF NOT EXISTS tripulacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100),
    apellidos VARCHAR(100),
    id_cc VARCHAR(20),
    id_registro VARCHAR(50),
    usuario VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    rol VARCHAR(20)
)";
if ($conn->query($sql_tripulacion) === TRUE) {
    echo "<p>Table 'tripulacion': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'tripulacion' error: " . $conn->error . "</p>";
}

// --- 2. Table: atenciones ---
$sql_atenciones = "CREATE TABLE IF NOT EXISTS atenciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora_atencion DATETIME,
    fecha DATE,
    hora_despacho TIME,
    hora_ingreso TIME,
    hora_llegada TIME,
    hora_final TIME,
    nombres_paciente VARCHAR(255),
    primer_nombre_paciente VARCHAR(100),
    segundo_nombre_paciente VARCHAR(100),
    primer_apellido_paciente VARCHAR(100),
    segundo_apellido_paciente VARCHAR(100),
    tipo_identificacion VARCHAR(10),
    id_paciente VARCHAR(50),
    genero_nacer VARCHAR(20),
    fecha_nacimiento DATE,
    rh VARCHAR(5),
    eps_nombre VARCHAR(100),
    servicio VARCHAR(100),
    tipo_traslado VARCHAR(100),
    traslado_tipo VARCHAR(100),
    pagador VARCHAR(100),
    quien_informo VARCHAR(100),
    atencion_en VARCHAR(255),
    direccion_servicio VARCHAR(255),
    localizacion VARCHAR(255),
    ips_destino VARCHAR(255),
    nombre_ips_receptora VARCHAR(255),
    nit_ips_receptora VARCHAR(50),
    ambulancia VARCHAR(50),
    conductor VARCHAR(100),
    tripulante VARCHAR(100),
    medico_tripulante VARCHAR(100),
    municipio_empresa VARCHAR(100),
    cod_ciudad_recogida VARCHAR(20),
    cod_ciudad_ips VARCHAR(20),
    municipio VARCHAR(100),
    ciudad VARCHAR(100),
    estado_registro VARCHAR(50) DEFAULT 'ACTIVA',
    tension_arterial VARCHAR(20),
    frecuencia_cardiaca VARCHAR(20),
    frecuencia_respiratoria VARCHAR(20),
    spo2 VARCHAR(20),
    temperatura VARCHAR(20),
    procedimientos TEXT,
    medicamentos_aplicados TEXT,
    diagnostico_principal TEXT,
    motivo_traslado TEXT,
    examen_fisico TEXT,
    consumo_servicio TEXT,
    escena_paciente TEXT,
    direccion_domicilio VARCHAR(255),
    oxigeno_dispositivo VARCHAR(100),
    oxigeno_flujo VARCHAR(50),
    oxigeno_fio2 VARCHAR(50),
    causa_externa_codigo VARCHAR(50),
    causa_externa_categoria VARCHAR(100),
    causa_externa_detalle TEXT,
    causa_externa TEXT,
    
    -- New fields from PDF requirements
    tipo_vehiculo_accidente VARCHAR(100),
    placa_vehiculo_involucrado VARCHAR(20),
    conductor_accidente VARCHAR(100),
    documento_conductor_accidente VARCHAR(50),
    aseguradora_soat VARCHAR(100),
    numero_poliza VARCHAR(100),
    codigo_reps_destino VARCHAR(50),
    nombre_medico_receptor VARCHAR(100),
    tipo_id_medico_receptor VARCHAR(20),
    id_medico_receptor VARCHAR(50),
    id_medico_receptor VARCHAR(50),
    registro_md_receptor VARCHAR(50),
    registro VARCHAR(50),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_atenciones) === TRUE) {
    echo "<p>Table 'atenciones': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'atenciones' error: " . $conn->error . "</p>";
}

// --- 3. Table: atenciones_extra ---
$sql_atenciones_extra = "CREATE TABLE IF NOT EXISTS atenciones_extra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    atencion_id INT,
    triage_escena VARCHAR(50),
    furips_reportado TINYINT(1),
    furips_fecha_reporte DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    diagnostico_principal TEXT,
    motivo_traslado TEXT,
    examen_fisico TEXT,
    procedimientos TEXT,
    consumo_servicio TEXT,
    medicamentos_aplicados TEXT,
    nombres_paciente VARCHAR(255),
    direccion_domicilio VARCHAR(255),
    escena_paciente TEXT,
    antecedentes TEXT,
    ant_alergicos_sn VARCHAR(5),
    ant_alergicos_cual TEXT,
    ant_ginecoobstetricos_sn VARCHAR(5),
    ant_ginecoobstetricos_cual TEXT,
    ant_patologicos_sn VARCHAR(5),
    ant_patologicos_cual TEXT,
    ant_quirurgicos_sn VARCHAR(5),
    ant_quirurgicos_cual TEXT,
    ant_traumatologicos_sn VARCHAR(5),
    ant_traumatologicos_cual TEXT,
    ant_toxicologicos_sn VARCHAR(5),
    ant_toxicologicos_cual TEXT,
    ant_familiares_sn VARCHAR(5),
    ant_familiares_cual TEXT,
    INDEX (atencion_id)
)";
if ($conn->query($sql_atenciones_extra) === TRUE) {
    echo "<p>Table 'atenciones_extra': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'atenciones_extra' error: " . $conn->error . "</p>";
}

// --- 4. Table: atenciones_sig ---
$sql_atenciones_sig = "CREATE TABLE IF NOT EXISTS atenciones_sig (
    id INT AUTO_INCREMENT PRIMARY KEY,
    atencion_id INT,
    tipo_firma VARCHAR(50),
    contenido LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (atencion_id)
)";
if ($conn->query($sql_atenciones_sig) === TRUE) {
    echo "<p>Table 'atenciones_sig': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'atenciones_sig' error: " . $conn->error . "</p>";
}

// --- 5. Default User (Master) ---
$result = $conn->query("SELECT * FROM tripulacion WHERE rol = 'Master'");
if ($result->num_rows == 0) {
    $nombres = 'Admin';
    $apellidos = 'Master';
    $usuario = 'master';
    $clave = password_hash('master123', PASSWORD_DEFAULT);
    $rol = 'Master';
    $id_cc = '0000000000';
    $id_registro = '000000';

    $stmt = $conn->prepare("INSERT INTO tripulacion (nombres, apellidos, id_cc, id_registro, usuario, clave, rol) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $nombres, $apellidos, $id_cc, $id_registro, $usuario, $clave, $rol);

    if ($stmt->execute()) {
        echo "<p style='color:green'>Default Master user created (master / master123).</p>";
    } else {
        echo "<p style='color:red'>Error creating default user: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    echo "<p>Master user already exists.</p>";
}

// --- 6. Table: departamentos ---
$sql_departamentos = "CREATE TABLE IF NOT EXISTS departamentos (
    codigo_departamento VARCHAR(5) PRIMARY KEY,
    nombre_departamento VARCHAR(100) NOT NULL
)";
if ($conn->query($sql_departamentos) === TRUE) {
    echo "<p>Table 'departamentos': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'departamentos' error: " . $conn->error . "</p>";
}

// --- 7. Table: municipios ---
$sql_municipios = "CREATE TABLE IF NOT EXISTS municipios (
    codigo_municipio VARCHAR(10) PRIMARY KEY,
    nombre_municipio VARCHAR(100) NOT NULL,
    codigo_departamento VARCHAR(5),
    FOREIGN KEY (codigo_departamento) REFERENCES departamentos(codigo_departamento)
)";
if ($conn->query($sql_municipios) === TRUE) {
    echo "<p>Table 'municipios': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'municipios' error: " . $conn->error . "</p>";
}

// --- 8. Populate Data (Basic Set) ---
// Check if data exists to avoid duplicates
$check_dept = $conn->query("SELECT COUNT(*) as count FROM departamentos");
$row_dept = $check_dept->fetch_assoc();

if ($row_dept['count'] == 0) {
    $conn->query("INSERT INTO departamentos (codigo_departamento, nombre_departamento) VALUES 
        ('05', 'ANTIOQUIA'),
        ('08', 'ATLANTICO'),
        ('11', 'BOGOTA D.C.'),
        ('13', 'BOLIVAR'),
        ('15', 'BOYACA'),
        ('17', 'CALDAS'),
        ('18', 'CAQUETA'),
        ('19', 'CAUCA'),
        ('20', 'CESAR'),
        ('23', 'CORDOBA'),
        ('25', 'CUNDINAMARCA'),
        ('27', 'CHOCO'),
        ('41', 'HUILA'),
        ('44', 'LA GUAJIRA'),
        ('47', 'MAGDALENA'),
        ('50', 'META'),
        ('52', 'NARIÑO'),
        ('54', 'NORTE DE SANTANDER'),
        ('63', 'QUINDIO'),
        ('66', 'RISARALDA'),
        ('68', 'SANTANDER'),
        ('70', 'SUCRE'),
        ('73', 'TOLIMA'),
        ('76', 'VALLE DEL CAUCA')
    ");
    echo "<p>Inserted basic Departments.</p>";
}

$check_mun = $conn->query("SELECT COUNT(*) as count FROM municipios");
$row_mun = $check_mun->fetch_assoc();

if ($row_mun['count'] == 0) {
    // Insert major cities/capitals
    $conn->query("INSERT INTO municipios (codigo_municipio, nombre_municipio, codigo_departamento) VALUES 
        ('05001', 'MEDELLIN', '05'),
        ('08001', 'BARRANQUILLA', '08'),
        ('11001', 'BOGOTA D.C.', '11'),
        ('13001', 'CARTAGENA', '13'),
        ('15001', 'TUNJA', '15'),
        ('17001', 'MANIZALES', '17'),
        ('18001', 'FLORENCIA', '18'),
        ('19001', 'POPAYAN', '19'),
        ('20001', 'VALLEDUPAR', '20'),
        ('23001', 'MONTERIA', '23'),
        ('25001', 'AGUA DE DIOS', '25'),
        ('25126', 'CAJICA', '25'),
        ('25175', 'CHIA', '25'),
        ('25290', 'FUSAGASUGA', '25'),
        ('25307', 'GIRARDOT', '25'),
        ('25754', 'SOACHA', '25'),
        ('25899', 'ZIPAQUIRA', '25'),
        ('27001', 'QUIBDO', '27'),
        ('41001', 'NEIVA', '41'),
        ('44001', 'RIOHACHA', '44'),
        ('47001', 'SANTA MARTA', '47'),
        ('50001', 'VILLAVICENCIO', '50'),
        ('52001', 'PASTO', '52'),
        ('54001', 'CUCUTA', '54'),
        ('63001', 'ARMENIA', '63'),
        ('66001', 'PEREIRA', '66'),
        ('68001', 'BUCARAMANGA', '68'),
        ('70001', 'SINCELEJO', '70'),
        ('73001', 'IBAGUE', '73'),
        ('76001', 'CALI', '76')
    ");
    echo "<p>Inserted basic Municipalities.</p>";
}

// --- 9. Table: categoriascie10 ---
$sql_cat_cie10 = "CREATE TABLE IF NOT EXISTS categoriascie10 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL
)";
if ($conn->query($sql_cat_cie10) === TRUE) {
    echo "<p>Table 'categoriascie10': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'categoriascie10' error: " . $conn->error . "</p>";
}

// --- 10. Table: diagnosticoscie10 ---
$sql_diag_cie10 = "CREATE TABLE IF NOT EXISTS diagnosticoscie10 (
    clave VARCHAR(10) PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL,
    idCategoria INT,
    FOREIGN KEY (idCategoria) REFERENCES categoriascie10(id)
)";
if ($conn->query($sql_diag_cie10) === TRUE) {
    echo "<p>Table 'diagnosticoscie10': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'diagnosticoscie10' error: " . $conn->error . "</p>";
}

// --- 11. Table: ips_receptora ---
$sql_ips = "CREATE TABLE IF NOT EXISTS ips_receptora (
    ips_nit VARCHAR(20) PRIMARY KEY,
    ips_nombre VARCHAR(255) NOT NULL,
    ips_ciudad VARCHAR(100)
)";
if ($conn->query($sql_ips) === TRUE) {
    echo "<p>Table 'ips_receptora': <strong>OK</strong></p>";
} else {
    echo "<p style='color:red'>Table 'ips_receptora' error: " . $conn->error . "</p>";
}

// --- 12. Populate CIE-10 and IPS Data (Sample) ---
$check_cat = $conn->query("SELECT COUNT(*) as count FROM categoriascie10");
$row_cat = $check_cat->fetch_assoc();

if ($row_cat['count'] == 0) {
    $conn->query("INSERT INTO categoriascie10 (id, descripcion) VALUES 
        (1, 'Traumatismos'),
        (2, 'Enfermedades del sistema circulatorio'),
        (3, 'Enfermedades del sistema respiratorio'),
        (4, 'Enfermedades infecciosas y parasitarias')
    ");
    
    $conn->query("INSERT INTO diagnosticoscie10 (clave, descripcion, idCategoria) VALUES 
        ('S069', 'Traumatismo intracraneal, no especificado', 1),
        ('S009', 'Traumatismo superficial de la cabeza, parte no especificada', 1),
        ('I10X', 'Hipertension esencial (primaria)', 2),
        ('I219', 'Infarto agudo del miocardio, sin otra especificacion', 2),
        ('J459', 'Asma, no especificada', 3),
        ('J189', 'Neumonia, no especificada', 3),
        ('A09X', 'Diarrea y gastroenteritis de presunto origen infeccioso', 4)
    ");
    echo "<p>Inserted sample CIE-10 data.</p>";
}

$check_ips = $conn->query("SELECT COUNT(*) as count FROM ips_receptora");
$row_ips = $check_ips->fetch_assoc();

if ($row_ips['count'] == 0) {
    $conn->query("INSERT INTO ips_receptora (ips_nit, ips_nombre, ips_ciudad) VALUES 
        ('890900286', 'HOSPITAL PABLO TOBON URIBE', 'MEDELLIN'),
        ('890900050', 'HOSPITAL GENERAL DE MEDELLIN', 'MEDELLIN'),
        ('860003020', 'CLINICA DEL COUNTRY', 'BOGOTA D.C.'),
        ('860006656', 'FUNDACION SANTA FE DE BOGOTA', 'BOGOTA D.C.'),
        ('800130907', 'CLINICA IMBANACO', 'CALI'),
        ('890300227', 'HOSPITAL UNIVERSITARIO DEL VALLE', 'CALI')
    ");
    echo "<p>Inserted sample IPS data.</p>";
}

$conn->close();
?>
