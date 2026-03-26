<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Panel de Control — config.php
 * Configuración central para la administración global de todas las carreras.
 */

// --- ZONA HORARIA ---
date_default_timezone_set('America/Mexico_City');

// --- SEGURIDAD DEL PANEL ---
// Define aquí la contraseña de acceso al panel de control
define('PANEL_PASSWORD', 'admin2026'); // El usuario puede cambiarla aquí

// Configuramos un nombre de sesión único para el panel para evitar conflictos con Coordinación
session_name('UPSRJ_PANEL');

require_once __DIR__ . '/../config.php'; // Reusamos la configuración de base de datos de Coordinación

// FORZAR ERRORES (debe ir después del config global que los apaga)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- CARRERAS (Extendido desde el config principal) ---
// Aquí podemos agregar configuraciones extra específicas para el panel si es necesario.
// El array $CARRERAS ya viene del config.php de nivel superior.

/**
 * Función para obtener conexión PDO para una carrera específica (PostgreSQL con RLS).
 */
function getPanelConnection($db_name, $carrera_id) {
    return getConnection($db_name, $carrera_id);
}

/**
 * Función para obtener estadísticas globales de todas las carreras.
 */
function getGlobalStats($pdo_map) {
    $stats = [
        'total_alumnos' => 0,
        'total_asistencias_hoy' => 0,
        'carreras_activas' => 0,
    ];
    // Lógica para consolidar datos...
    return $stats;
}
?>
