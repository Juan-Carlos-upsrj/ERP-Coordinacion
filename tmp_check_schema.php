<?php
require_once 'config.php';
$pdo = getConnection(DB_NAME);

$tables = ['hor_docentes', 'hor_materias', 'hor_grupos'];

foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$table'");
    while ($row = $stmt->fetch()) {
        echo "{$row['column_name']} ({$row['data_type']})\n";
    }
    echo "\n";
}
