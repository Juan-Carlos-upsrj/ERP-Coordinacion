<?php
/**
 * api/solicitudes_estudiantes.php
 * Endpoint público/semi-público para que los estudiantes suban solicitudes de justificantes.
 */

require_once '../config.php';

// Devolver JSON siempre
header('Content-Type: application/json');

// Permitir peticiones desde el mismo dominio o controlar CORS si es necesario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

// 1. Recibir datos del formulario
$matricula     = trim($_POST['matricula'] ?? '');
$alumno_nombre = trim($_POST['alumno_nombre'] ?? '');
$carrera_sigla = trim($_POST['carrera_sigla'] ?? 'IAEV');
$fecha_ausencia= trim($_POST['fecha_ausencia'] ?? '');
$motivo        = trim($_POST['motivo'] ?? '');

$faltantes = [];
if (empty($alumno_nombre)) $faltantes[] = 'Nombre';
if (empty($carrera_sigla)) $faltantes[] = 'Programa Educativo';
if (empty($fecha_ausencia)) $faltantes[] = 'Fecha';
if (empty($motivo)) $faltantes[] = 'Motivo';

if (!empty($faltantes)) {
    http_response_code(400);
    // Verificar si $_POST está vacío pero hay content-length (archivo muy grande)
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        echo json_encode(['success' => false, 'error' => 'El archivo adjunto es demasiado grande y el servidor rechazó la solicitud.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios: ' . implode(', ', $faltantes)]);
    }
    exit;
}

// 2. Gestionar la subida del archivo (opcional pero muy recomendado)
$archivo_url = null;
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    // Definir y crear carpeta destino si no existe
    $upload_dir = __DIR__ . '/../uploads/justificantes/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_info = pathinfo($_FILES['archivo']['name']);
    $ext = strtolower($file_info['extension']);
    
    // Validar extensiones (Imágenes y PDF)
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Sube un PDF o una foto (JPG/PNG).']);
        exit;
    }

    // Nombre único para el archivo
    $identificador = !empty($matricula) ? $matricula : uniqid('req_');
    $new_filename = 'justificante_' . $identificador . '_' . time() . '.' . $ext;
    $destination = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['archivo']['tmp_name'], $destination)) {
        // Guardamos la ruta relativa para accederla desde la app
        $archivo_url = 'uploads/justificantes/' . $new_filename;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al subir el archivo de evidencia.']);
        exit;
    }
}

// 3. Conexión a la base de datos de la carrera
global $CARRERAS;
if (!isset($CARRERAS[$carrera_sigla])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Carrera no válida.']);
    exit;
}

$carrera_info = $CARRERAS[$carrera_sigla];

try {
    $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
    
    // 4. Insertar la solicitud en la base de datos
    $sql = "INSERT INTO hor_solicitudes_justificantes (
                alumno_nombre, matricula, fecha_ausencia, motivo, archivo_url, estado
            ) VALUES (
                :alumno_nombre, :matricula, :fecha_ausencia, :motivo, :archivo_url, 'pendiente'
            )";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':alumno_nombre'  => $alumno_nombre,
        ':matricula'      => $matricula,
        ':fecha_ausencia' => $fecha_ausencia,
        ':motivo'         => $motivo,
        ':archivo_url'    => $archivo_url
    ]);

    echo json_encode(['success' => true, 'message' => 'Solicitud enviada correctamente. El coordinador la revisará pronto.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error insertando solicitud de justificante: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno en la base de datos.', 'details' => $e->getMessage()]);
}
