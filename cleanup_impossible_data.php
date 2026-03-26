<?php
/**
 * cleanup_impossible_data.php
 * Script para eliminar registros de asistencia de fechas festivas o futuras subidos por error.
 */
require_once 'config.php';

echo "<pre>";
echo "--- INICIANDO LIMPIEZA DE ASISTENCIAS IMPOSIBLES ---\n\n";

try {
    // Rango de fechas a eliminar (Hoy 16 de Marzo y cualquier fecha futura)
    $fecha_limite = '2026-03-16';

    foreach ($CARRERAS as $sigla => $info) {
        if (!$info['activa']) continue;
        
        $pdo = getConnection($info['db_name'], $info['carrera_id']);
        
        // 1. Consultar cuántos hay
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM asistencia_clases WHERE fecha >= ? AND carrera_id = ?");
        $stmt->execute([$fecha_limite, $info['carrera_id']]);
        $total = $stmt->fetchColumn();

        if ($total > 0) {
            echo "CARRERA [$sigla]: Se encontraron $total registros para la fecha $fecha_limite o posterior.\n";
            
            if (isset($_GET['confirm']) && $_GET['confirm'] == '1') {
                $del = $pdo->prepare("DELETE FROM asistencia_clases WHERE fecha >= ? AND carrera_id = ?");
                $del->execute([$fecha_limite, $info['carrera_id']]);
                echo "CARRERA [$sigla]: REGISTROS ELIMINADOS CORRECTAMENTE.\n";
            } else {
                echo "CARRERA [$sigla]: Para eliminar estos registros, agrega ?confirm=1 a la URL.\n";
            }
        } else {
            echo "CARRERA [$sigla]: No se encontraron registros imposibles.\n";
        }
        echo "--------------------------------------------------\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- PROCESO FINALIZADO ---";
echo "</pre>";
