<?php
require 'config.php';
try {
    $pdo = getConnection(DB_NAME);
    $stmt = $pdo->query("SELECT id, profesor_nombre, carrera_id, fecha_subida FROM asistencia_clases ORDER BY fecha_subida DESC LIMIT 10");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
