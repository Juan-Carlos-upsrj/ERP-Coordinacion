<?php
/**
 * app/api/get_notifications.php
 * Endpoint JSON para las últimas notificaciones de sincronización.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../models/LogsModel.php';
require_once __DIR__ . '/../core/Utils.php';

session_start();

if (empty($_SESSION['logged_in'])) {
    echo json_encode(['error' => 'No session']);
    exit;
}

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];

try {
    $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
    $notificaciones = LogsModel::getNotificacionesSync($pdo, 8);
    
    // Formatear la fecha para que sea legible o enviarla relativa
    foreach ($notificaciones as &$n) {
        $n['fecha_formateada'] = date('d/m/Y H:i:s', strtotime($n['fecha_sync']));
        $n['hace_cuanto'] = Utils::tiempoRelativo($n['fecha_sync']);
    }

    echo json_encode([
        'success' => true,
        'data' => $notificaciones,
        'count' => count($notificaciones)
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
