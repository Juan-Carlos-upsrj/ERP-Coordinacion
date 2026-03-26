<?php
/**
 * config.php — Sistema de Coordinación UPSRJ
 * Configuración global. Sin ofuscación. Limpio y mantenible.
 */

// ─── 1. CARGAR .env ───────────────────────────────────────────────────────────
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$name, $value] = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
        $_ENV[trim($name)] = trim($value);
    }
}

// ─── 2. CREDENCIALES BD ───────────────────────────────────────────────────────
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'admin_erp');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'erp_academico');

// ─── 3. INSTITUCIÓN ───────────────────────────────────────────────────────────
define('INSTITUCION_NOMBRE_CORTO', 'UPSRJ');
define('INSTITUCION_NOMBRE_LARGO', 'Universidad Politécnica de Santa Rosa Jáuregui');

// ─── 4. SEGURIDAD API ──────────────────────────────────────────────────────────
// Esta es la "contraseña" que debe configurarse en la aplicación móvil/escritorio.
define('API_KEY', 'UPSRJ_2025_SECURE_SYNC');
define('DOCENTE_PASSWORD', 'UPSRJ_Docente_2026'); // Contraseña para la página de descarga

// ─── 4. CATÁLOGO DE CARRERAS CON PASSWORDS INDIVIDUALES ──────────────────────
// Cada carrera tiene su propio listado de usuarios/contraseñas.
// Esto garantiza que el coordinador de IAEV no pueda ver datos de LTF.
//
// carrera_id: ID en tabla `carreras` de Postgres (usado para activar RLS).
// usuarios: Lista de usuarios que pueden acceder a esta carrera específica.
//
$CARRERAS = [

    'IAEV' => [
        'nombre_corto' => 'IAEV',
        'nombre_largo' => 'Ingeniería en Animación y Efectos Visuales',
        'db_name'      => DB_NAME,
        'carrera_id'   => 1,
        'color_hex'    => '#EAB308',
        'icono'        => 'animation',
        'logo'         => 'logo_iaev.png',
        'activa'       => true,
        'permitir_cambio' => false
    ],

    'LTF' => [
        'nombre_corto' => 'LTF',
        'nombre_largo' => 'Licenciatura en Terapia Física',
        'db_name'      => DB_NAME,
        'carrera_id'   => 3,
        'color_hex'    => '#10B981',
        'icono'        => 'favorite',
        'logo'         => 'logo_ltf.png',
        'activa'       => true,
        'permitir_cambio' => false
    ],

    'ISW' => [
        'nombre_corto' => 'ISW',
        'nombre_largo' => 'Ingeniería en Software',
        'db_name'      => DB_NAME,
        'carrera_id'   => 4,
        'color_hex'    => '#6366F1',
        'icono'        => 'code',
        'logo'         => 'logo_isw.png',
        'activa'       => true,
        'permitir_cambio' => false
    ],

    'IMI' => [
        'nombre_corto' => 'IMI',
        'nombre_largo' => 'Ingeniería en Metrología Industrial',
        'db_name'      => DB_NAME,
        'carrera_id'   => 5,
        'color_hex'    => '#F97316',
        'icono'        => 'straighten',
        'logo'         => 'logo_imi.png',
        'activa'       => true,
        'permitir_cambio' => false
    ],

    'IRC' => [
        'nombre_corto' => 'IRC',
        'nombre_largo' => 'Ingeniería en Robótica Computacional',
        'db_name'      => DB_NAME,
        'carrera_id'   => 6,
        'color_hex'    => '#8B5CF6',
        'icono'        => 'memory',
        'logo'         => 'logo_irc.png',
        'activa'       => true,
        'permitir_cambio' => false
    ],

    'ISA' => [
        'nombre_corto' => 'ISA',
        'nombre_largo' => 'Ingeniería en Sistemas Automotrices',
        'db_name'      => DB_NAME,
        'carrera_id'   => 7,
        'color_hex'    => '#EF4444',
        'icono'        => 'directions_car',
        'logo'         => 'log_isa.png',
        'activa'       => true,
        'permitir_cambio' => false
    ],
];

// ─── 5. CONTACTOS DE SOPORTE (Sección Apoyo) ──────────────────────────────────
$SUPPORT_CONTACTS = [
    [
        'label' => 'Soporte',
        'valor' => 'Coordinación Académica'
    ],
    [
        'label' => 'Acceso',
        'valor' => 'Cuenta institucional Google'
    ]
];

// ─── 6. CONFIGURACIÓN GENERAL ─────────────────────────────────────────────────
date_default_timezone_set('America/Mexico_City');
ini_set('display_errors', 0); // OFF en producción — errores van a log
error_reporting(E_ALL);

// ─── 6. FUNCIÓN DE CONEXIÓN A BD (activa RLS automáticamente) ─────────────────
/**
 * Crea PDO y activa Row Level Security para la carrera indicada.
 * @param string $db_name    Nombre de la base de datos
 * @param int    $carrera_id ID de la carrera para el filtro RLS
 */
function getConnection(string $db_name, int $carrera_id = 0): PDO {
    $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . $db_name;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Activar RLS: filtra los datos al carrera_id dado
        if ($carrera_id > 0) {
            $pdo->exec("SET app.carrera_id = '{$carrera_id}'");
        }
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Error de conexión a PostgreSQL ({$db_name}): " . $e->getMessage());
    }
}
