<?php
/**
 * app/views/pages/exportar_riesgo.php
 * Genera un archivo CSV con los alumnos en riesgo crítico.
 */

require_once 'app/models/AlumnosModel.php';

// Limpiar buffer HTML previo
if (ob_get_length()) ob_clean();

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];

try {
    $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
    $alumnos_riesgo = AlumnosModel::getAlumnosEnRiesgo($pdo, 500); // Hasta 500 para el reporte
} catch (Exception $e) {
    die("Error conectando a la base de datos.");
}

// Configurar Headers para descarga CSV
$filename = "Reporte_Riesgo_{$carrera_sigla}_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Añadir BOM para que Excel respete el UTF-8
echo "\xEF\xBB\xBF";

// Abrir salida
$salida = fopen('php://output', 'w');

// Encabezados
fputcsv($salida, [
    'Matricula (ID)',
    'Nombre del Estudiante',
    'Grupo Principal',
    'Faltas Acumuladas',
    'Ultima Falta Registrada',
    'Nivel de Severidad'
]);

// Filas
foreach ($alumnos_riesgo as $st) {
    $faltas = (int)$st['total_faltas'];
    $severidad = 'Amarilla';
    if ($faltas >= 7) $severidad = 'Roja (Critico)';
    elseif ($faltas >= 5) $severidad = 'Naranja (Peligro)';

    fputcsv($salida, [
        $st['alumno_id'],
        $st['alumno_nombre'],
        $st['grupo_principal'],
        $faltas,
        $st['ultima_falta'],
        $severidad
    ]);
}

fclose($salida);
exit;
