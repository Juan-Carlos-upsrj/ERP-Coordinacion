<?php
/**
 * tmp_run_migration.php
 * Ejecuta la migración de sincronización de docentes en todas las bases de datos necesarias.
 */
require_once 'config.php';

try {
    echo "Iniciando migración...\n";

    // 1. Base de datos global (erp_academico)
    echo "Aplicando a erp_academico (global)...\n";
    $pdo_global = getConnection(DB_NAME);
    $sql = file_get_contents('migrations/docentes_sync_v1.sql');
    
    // Separar las dos partes de la migración ya que una es para global y otra para carrera
    $parts = explode('-- 2.', $sql);
    
    try {
        $pdo_global->exec($parts[0]);
        echo "✓ erp_academico: OK\n";
    } catch (Exception $e) {
        echo "⚠ erp_academico: " . $e->getMessage() . " (Probablemente ya aplicado)\n";
    }

    // 2. Bases de datos de carreras
    foreach ($CARRERAS as $sigla => $c) {
        echo "Aplicando a carrera {$sigla} ({$c['db_name']})...\n";
        try {
            $pdo_c = getConnection($c['db_name'], $c['carrera_id']);
            $pdo_c->exec("ALTER TABLE hor_docentes ADD CONSTRAINT unique_hor_docente_carrera_email UNIQUE (carrera_id, email)");
            echo "✓ {$sigla}: OK\n";
        } catch (Exception $e) {
            echo "⚠ {$sigla}: " . $e->getMessage() . " (Probablemente ya aplicado)\n";
        }
    }

    echo "Migración finalizada.\n";

} catch (Exception $e) {
    echo "ERROR CRITICO: " . $e->getMessage() . "\n";
}
