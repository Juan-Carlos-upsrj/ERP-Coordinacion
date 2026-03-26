<?php
/**
 * run_migration.php
 * Script temporal para ejecutar la migración de base de datos desde el navegador.
 * ELIMINAR DESPUÉS DE USAR.
 */
require_once 'config.php';

// Verificación básica de seguridad: Solo si se pasa una llave o si estamos en localhost
if (empty($_GET['key']) || $_GET['key'] !== 'system_migration_2025') {
    die("Acceso denegado. Se requiere ?key=system_migration_2025");
}

try {
    $pdo = getConnection(DB_NAME);
    $sql_file = __DIR__ . '/migrations/create_coordinadores.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("No se encontró el archivo de migración: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    $pdo->exec($sql);
    
    echo "<h1>✅ Migración completada con éxito</h1>";
    echo "<p>La tabla <code>coordinadores</code> ha sido creada y poblada.</p>";
    echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo (<code>run_migration.php</code>) de tu servidor por seguridad.</p>";
    
} catch (Exception $e) {
    echo "<h1>❌ Error en la migración</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
