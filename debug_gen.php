<?php
require_once 'config.php';
require_once 'app/models/HorariosModel.php';
require_once 'app/services/ScheduleGeneratorService.php';

$carrera_sigla = 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$carrera_id    = $carrera_info['carrera_id'];
$pdo = getConnection($carrera_info['db_name'], $carrera_id);

echo "--- SCHEMA AUDIT ---\n";
try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'hor_clases'");
    $cols = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    print_r($cols);
} catch (Exception $e) {
    echo "Error checking schema: " . $e->getMessage() . "\n";
}

echo "\n--- GENERATOR TEST ---\n";
$service = new ScheduleGeneratorService($pdo, $carrera_id);
$filters = [
    'periodo' => '2026-1',
    'grupo_ids' => [], // All
    'docente_ids' => [] // All
];

// Corregimos periodos si es necesario
echo "Current Period in Service: " . $filters['periodo'] . "\n";

$result = $service->generate(false, $filters); // false = SAVE to DB
echo "Result:\n";
print_r($result);

echo "\n--- CLASES IN 2026-1 AFTER GEN ---\n";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM hor_clases WHERE carrera_id = ? AND periodo = ?");
$stmt->execute([$carrera_id, '2026-1']);
echo "Count: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->prepare("SELECT dia, hora_inicio, grupo_id FROM hor_clases WHERE carrera_id = ? AND periodo = ? LIMIT 5");
$stmt->execute([$carrera_id, '2026-1']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
