<?php
require_once __DIR__ . '/config.php';

echo "<h1>Migración de Base de Datos — Horarios</h1>";

try {
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "<h3>⏳ Iniciando actualizaciones estructurales...</h3>";
    
    // 1. Aulas Preferidas
    echo "<p>📦 Agregando columna <code>aulas_preferidas</code> a <code>hor_docentes</code>...</p>";
    $sql1 = "ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS aulas_preferidas JSONB DEFAULT '[]'::jsonb";
    $pdo->exec($sql1);
    echo "✅ OK.";

    // 2. Columna de Período para Soporte Multi-histórico
    echo "<p>📅 Agregando columna <code>periodo</code> a <code>hor_clases</code>...</p>";
    // Primero obtenemos la configuración actual para saber qué valor poner por defecto
    $stmt_config = $pdo->query("SELECT anio_activo, cuatrimestre_activo FROM hor_configuracion LIMIT 1");
    $config = $stmt_config->fetch();
    $default_period = ($config['anio_activo'] ?? 2026) . '-' . ($config['cuatrimestre_activo'] ?? 1);

    $sql2 = "ALTER TABLE hor_clases ADD COLUMN IF NOT EXISTS periodo VARCHAR(20) DEFAULT '{$default_period}'";
    $pdo->exec($sql2);
    echo "✅ OK. (Default: {$default_period})";

    echo "<h2 style='color:green;'>🎉 ¡Migración completada con éxito!</h2>";
    echo "<p>Ya puedes cerrar esta pestaña y borrar este archivo <code>migrate_db.php</code> por seguridad.</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Falló la migración</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p style='background:#fff3f3; padding:10px; border:1px solid #ffcccc;'>";
    echo "<strong>Causa probable:</strong> El usuario de la base de datos no tiene permisos de modificación (ALTER TABLE).<br><br>";
    echo "Pide a sistemas que ejecute este comando como <b>postgres</b> (owner):<br>";
    echo "<code style='display:block; background:#eee; padding:5px; margin-top:5px;'>ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS aulas_preferidas JSONB DEFAULT '[]'::jsonb;<br>ALTER TABLE hor_clases ADD COLUMN IF NOT EXISTS periodo VARCHAR(20) DEFAULT '2026-1';</code>";
    echo "</p>";
}
?>
