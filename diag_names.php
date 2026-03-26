<?php
require_once 'config.php';
require_once 'app/models/AlumnosModel.php';
$carrera_sigla = 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
try {
    $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
    
    // Check similarity threshold
    $threshold = $pdo->query("show pg_trgm.similarity_threshold")->fetchColumn();
    echo "Threshold (DB): $threshold\n";

    // Test a few specific similarities if the user thinks there are some
    $sql_test = "SELECT t1.alumno_nombre as n1, t2.alumno_nombre as n2, similarity(t1.alumno_nombre, t2.alumno_nombre) as score
                 FROM (SELECT DISTINCT alumno_nombre FROM asistencia_clases) t1
                 JOIN (SELECT DISTINCT alumno_nombre FROM asistencia_clases) t2 
                    ON t1.alumno_nombre < t2.alumno_nombre
                 WHERE similarity(t1.alumno_nombre, t2.alumno_nombre) > 0.1
                 ORDER BY score DESC LIMIT 20";
    $raw_sugs = $pdo->query($sql_test)->fetchAll();
    echo "Similarities > 0.1 Found: " . count($raw_sugs) . "\n";
    foreach ($raw_sugs as $r) {
        echo "- {$r['n1']} vs {$r['n2']} (Score: {$r['score']})\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
