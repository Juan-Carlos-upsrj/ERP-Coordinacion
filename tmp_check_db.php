<?php
require_once 'config.php';
header('Content-Type: text/plain');

try {
    $carreras = ['IAEV' => 1]; // Let's check IAEV
    foreach ($CARRERAS as $sigla => $info) {
        if (!$info['activa']) continue;
        echo "--- CARRERA: $sigla ---\n";
        $pdo = getConnection($info['db_name']);
        
        $tables = ['asistencia_clases', 'calificaciones_finales'];
        foreach ($tables as $table) {
            echo "Table: $table\n";
            $q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$table'");
            foreach ($q->fetchAll() as $row) {
                echo "  - {$row['column_name']} ({$row['data_type']})\n";
            }
            
            echo "Constraints:\n";
            $cq = $pdo->query("SELECT conname, pg_get_constraintdef(c.oid) FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE nspname = 'public' AND contype IN ('p', 'u') AND conrelid = '$table'::regclass");
            foreach ($cq->fetchAll() as $crow) {
                echo "  - {$crow['conname']}: {$crow['pg_get_constraintdef']}\n";
            }
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
