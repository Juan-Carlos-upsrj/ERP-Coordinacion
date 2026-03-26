<?php
require_once 'config.php';
try {
    $pdo = getConnection(DB_NAME);
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'coordinadores'");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
