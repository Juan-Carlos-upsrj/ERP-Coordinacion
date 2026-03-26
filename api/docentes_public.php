<?php
/**
 * api/docentes_public.php
 * Endpoint para que los docentes guarden sus preferencias de horarios sin requerir sesión del ERP.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/models/HorariosModel.php';

header('Content-Type: application/json; charset=utf-8');

$carrera_sigla = $_GET['c'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla] ?? null;

if (!$carrera_info) {
    http_response_code(400); 
    echo json_encode(['error' => 'Carrera inválida']); 
    exit;
}

$carrera_id = $carrera_info['carrera_id'];
$pdo = getConnection($carrera_info['db_name'], $carrera_id);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$docente_id = $_GET['id'] ?? null;

// Obtener detalles de un docente específico para cargar su grid en UI
if ($method === 'GET' && $action === 'get') {
    $docentes = HorariosModel::getDocentes($pdo, $carrera_id);
    $d = array_filter($docentes, function($x) use ($docente_id) { 
        return (string)$x['id'] === (string)$docente_id; 
    });
    
    if (empty($d)) { 
        http_response_code(404); 
        echo json_encode(['error'=>'Docente no encontrado']); 
        exit; 
    }
    
    $docenteData = array_values($d)[0];
    $materiasAsignadas = HorariosModel::getDocenteMaterias($pdo, $docenteData['id']);
    $docenteData['materia_ids'] = array_column($materiasAsignadas, 'materia_id');
    
    echo json_encode(['success' => true, 'data' => $docenteData]);
    exit;
}

// Obtener listado minimizado de docentes (para el dropdown inicial)
if ($method === 'GET' && $action === 'list') {
    $docentes = HorariosModel::getDocentes($pdo, $carrera_id);
    $safe = array_map(function($d) { 
        return ['id' => $d['id'], 'nombre' => $d['nombre']]; 
    }, $docentes);
    
    echo json_encode(['success' => true, 'data' => $safe]);
    exit;
}

// Obtener catálogo de materias y aulas
if ($method === 'GET' && $action === 'catalog') {
    $materias = HorariosModel::getMaterias($pdo, $carrera_id);
    $aulas = HorariosModel::getAulas($pdo, $carrera_id);
    
    // Filtrar materias para que solo dejen las que NO son externas
    $materiasFiltradas = array_values(array_filter($materias, function($m) {
        return empty($m['es_externa']);
    }));
    
    // Retornamos las materias y las aulas
    echo json_encode([
        'success' => true, 
        'data' => [
            'materias' => $materiasFiltradas,
            'aulas' => $aulas
        ]
    ]);
    exit;
}

// Guardar nueva disponibilidad
if ($method === 'POST' && $action === 'save' && $docente_id) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    
    $disp = json_encode($body['disponibilidad'] ?? []);
    $aulas = json_encode($body['aulas_preferidas'] ?? []);
    $materia_ids = $body['materia_ids'] ?? [];
    
    try {
        $stmt = $pdo->prepare("UPDATE hor_docentes SET disponibilidad = ?, aulas_preferidas = ?::jsonb WHERE id = ? AND carrera_id = ?");
        $stmt->execute([$disp, $aulas, $docente_id, $carrera_id]);
    } catch (\Exception $e) {
        // Fallback if aulas_preferidas column doesn't exist
        $stmt = $pdo->prepare("UPDATE hor_docentes SET disponibilidad = ? WHERE id = ? AND carrera_id = ?");
        $stmt->execute([$disp, $docente_id, $carrera_id]);
    }
    
    HorariosModel::setDocenteMaterias($pdo, $docente_id, $materia_ids);
    
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400); 
echo json_encode(['error' => 'Bad Request']);
