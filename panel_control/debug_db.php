<?php
require_once 'config.php';

echo "<h1>Diagnóstico de Conexión y Extensiones</h1>";

foreach ($CARRERAS as $sigla => $c) {
    if (!$c['activa']) continue;
    
    echo "<h2>Carrera: $sigla</h2>";
    try {
        $pdo = getConnection($c['db_name'], $c['carrera_id']);
        echo "<p style='color:green'>[OK] Conexión establecida</p>";
        
        // Verificar extensión pg_trgm
        $ext = $pdo->query("SELECT installed_version FROM pg_available_extensions WHERE name = 'pg_trgm'")->fetch();
        if ($ext && $ext['installed_version']) {
            echo "<p style='color:green'>[OK] Extensión pg_trgm instalada (v{$ext['installed_version']})</p>";
        } else {
            echo "<p style='color:red'>[ERROR] Extensión pg_trgm NO instalada. La búsqueda por similitud fallará.</p>";
        }
        
        // Verificar tabla asistencia_clases
        $table = $pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_name = 'asistencia_clases'")->fetchColumn();
        if ($table > 0) {
            echo "<p style='color:green'>[OK] Tabla asistencia_clases existe</p>";
            $count = $pdo->query("SELECT count(*) FROM asistencia_clases")->fetchColumn();
            echo "<p>[INFO] Registros encontrados: $count</p>";
        } else {
            echo "<p style='color:red'>[ERROR] Tabla asistencia_clases NO existe en esta base de datos.</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color:red'>[CRITICAL ERROR] " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "<hr>";
}
?>
