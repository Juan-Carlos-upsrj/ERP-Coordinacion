<?php
require_once 'config.php';
require_once 'app/models/AlumnosModel.php';
$carrera_sigla = 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
try {
    $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
    $sugs = AlumnosModel::getSugerenciasFusion($pdo);
    echo "COUNT: " . count($sugs) . "\n";
    foreach ($sugs as $s) {
        echo "- {$s['nombre1']} vs {$s['nombre2']} (Score: {$s['score']})\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
