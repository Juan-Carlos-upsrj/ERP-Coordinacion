<?php
// Script temporal para extraer datos de Juan Carlos y Melanie para auditoría
require_once 'config.php';

try {
    $carrera_sigla = 'IAEV'; // Podemos probar con varias si es necesario
    $carrera_info = $CARRERAS[$carrera_sigla];
    $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
    
    // Desactivar RLS momentáneamente (si es posible con este usuario de DB) 
    // o simplemente consultar lo que es visible para este carrera_id.
    
    $sql = "SELECT id, profesor_nombre, grupo_nombre, materia_nombre, fecha, status, fecha_subida, carrera_id 
            FROM asistencia_clases 
            WHERE profesor_nombre LIKE '%Juan Carlos%' OR profesor_nombre LIKE '%Melanie%'
            ORDER BY fecha_subida DESC LIMIT 200";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    file_put_contents('db_dump.json', json_encode($data, JSON_PRETTY_PRINT));
    echo "Dump completado en db_dump.json\n";

} catch (Exception $e) {
    file_put_contents('db_dump_error.log', $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
