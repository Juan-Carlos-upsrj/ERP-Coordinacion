<?php
/**
 * api/attendance_detalle.php
 * Retorna el desglose de alumnos para una clase específica.
 */
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$fecha    = $_GET['fecha']   ?? null;
$grupo    = $_GET['grupo']   ?? null;
$profesor = $_GET['profesor'] ?? null;

if (!$fecha || !$grupo || !$profesor) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
    exit;
}

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

try {
    $stmt = $pdo->prepare("
        SELECT alumno_nombre, status, fecha_subida
        FROM asistencia_clases
        WHERE fecha = ? AND grupo_nombre = ? AND profesor_nombre = ? AND carrera_id = ?
        ORDER BY alumno_nombre ASC
    ");
    $stmt->execute([$fecha, $grupo, $profesor, $carrera_info['carrera_id']]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $alumnos
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
