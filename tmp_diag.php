<?php
require_once 'config.php';
require_once 'app/models/AlumnosModel.php';
$carrera_sigla = 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
try {
    $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
    
    // Check if pg_trgm is active
    $ext = $pdo->query("SELECT * FROM pg_extension WHERE extname = 'pg_trgm'")->fetch();
    if (!$ext) {
        echo "ERROR: pg_trgm extension is NOT active.\n";
    } else {
        echo "SUCCESS: pg_trgm is active.\n";
    }

    // Check similarity threshold
    $threshold = $pdo->query("show pg_trgm.similarity_threshold")->fetchColumn();
    echo "Threshold: $threshold\n";

    // Re-check count
    $sugs = AlumnosModel::getSugerenciasFusion($pdo, $carrera_info['carrera_id']);
    echo "Fuzzy match count (AlumnosModel): " . count($sugs) . "\n";

    // Manual check for similar names without group constraint
    $sql_test = "SELECT t1.alumno_nombre as n1, t2.alumno_nombre as n2, similarity(t1.alumno_nombre, t2.alumno_nombre) as score
                 FROM (SELECT DISTINCT alumno_nombre FROM asistencia_clases) t1
                 JOIN (SELECT DISTINCT alumno_nombre FROM asistencia_clases) t2 
                    ON t1.alumno_nombre < t2.alumno_nombre
                 WHERE t1.alumno_nombre % t2.alumno_nombre
                 ORDER BY score DESC LIMIT 5";
    $raw_sugs = $pdo->query($sql_test)->fetchAll();
    echo "Raw fuzzy matches (no group constraint): " . count($raw_sugs) . "\n";
    foreach ($raw_sugs as $r) {
        echo "- {$r['n1']} vs {$r['n2']} (Score: {$r['score']})\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
