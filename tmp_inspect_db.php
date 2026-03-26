<?php
require_once 'config.php';

try {
    $pdo = getConnection(DB_NAME);
    echo "<h1>Inspección de Base de Datos</h1>";
    
    $table = 'asistencia_clases';
    $stmt = $pdo->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)");
    $stmt->execute([$table]);
    $exists = $stmt->fetchColumn();
    
    if ($exists) {
        echo "<p>✅ La tabla <code>{$table}</code> existe.</p>";
        $q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '{$table}'");
        echo "<ul>";
        while($r = $q->fetch()) {
            echo "<li>{$r['column_name']} ({$r['data_type']})</li>";
        }
        echo "</ul>";
        
        $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "<p>Total registros: {$count}</p>";
        
        // Verificación de sesión
        session_start();
        echo "<h2>Sesión Actual</h2>";
        echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    } else {
        echo "<p>❌ La tabla <code>{$table}</code> NO existe.</p>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
