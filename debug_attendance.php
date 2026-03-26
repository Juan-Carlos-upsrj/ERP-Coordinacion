<?php
require_once 'config.php';
$carrera_sigla = 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

header('Content-Type: text/plain');

echo "Checking today's records (2026-03-16)...\n";
try {
    $sql = "SELECT profesor_nombre, grupo_nombre, materia_nombre, fecha, fecha_subida contents 
            FROM asistencia_clases 
            WHERE fecha = '2026-03-16' 
            ORDER BY fecha_subida DESC LIMIT 10";
    // Actually, I want to see the distinct combinations for the dashboard view
    $sql = "SELECT profesor_nombre, grupo_nombre, materia_nombre, fecha, MAX(fecha_subida) as max_subida, COUNT(*) as count 
            FROM asistencia_clases 
            WHERE fecha = '2026-03-16' 
            GROUP BY profesor_nombre, grupo_nombre, materia_nombre, fecha
            ORDER BY max_subida DESC";
    
    $stmt = $pdo->query($sql);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($records) . " records for today.\n";
    foreach ($records as $r) {
        echo "Profesor: {$r['profesor_nombre']} | Grupo: {$r['grupo_nombre']} | Materia: {$r['materia_nombre']} | Fecha: {$r['fecha']} | Max Subida: {$r['max_subida']} | Count: {$r['count']}\n";
    }

    echo "\nChecking records uploaded TODAY (regardless of class date)...\n";
    // Assuming fecha_subida is TIMESTAMPTZ or TIMESTAMP
    $sql = "SELECT profesor_nombre, grupo_nombre, materia_nombre, fecha, fecha_subida 
            FROM asistencia_clases 
            WHERE fecha_subida >= CURRENT_DATE 
            ORDER BY fecha_subida DESC LIMIT 20";
    $stmt = $pdo->query($sql);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as $r) {
        echo "Profesor: {$r['profesor_nombre']} | Grupo: {$r['grupo_nombre']} | Fecha Clase: {$r['fecha']} | Subida: {$r['fecha_subida']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
