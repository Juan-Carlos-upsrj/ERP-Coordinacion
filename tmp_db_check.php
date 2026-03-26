<?php
require_once 'config.php';
$carrera_sigla = 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

header('Content-Type: text/plain');

echo "Checking tables...\n";
try {
    $exts = $pdo->query("SELECT extname FROM pg_extension")->fetchAll(PDO::FETCH_COLUMN);
    echo "Extensions: " . implode(', ', $exts) . "\n\n";

    $tables = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n\n";

    if (in_array('asistencia_clases', $tables)) {
        echo "Columns in asistencia_clases:\n";
        $cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'asistencia_clases'")->fetchAll();
        foreach ($cols as $c) echo "- {$c['column_name']} ({$c['data_type']})\n";
    } else {
        echo "asistencia_clases NOT FOUND in public schema.\n";
    }

    if (in_array('hor_justificaciones', $tables)) {
        echo "\nColumns in hor_justificaciones:\n";
        $cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'hor_justificaciones'")->fetchAll();
        foreach ($cols as $c) echo "- {$c['column_name']} ({$c['data_type']})\n";
    } else {
        echo "\nhor_justificaciones NOT FOUND.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
