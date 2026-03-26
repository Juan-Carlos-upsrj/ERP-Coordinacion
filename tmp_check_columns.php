<?php
require_once 'config.php';
try {
    $pdo = getConnection(DB_NAME);
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'hor_grupos'");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) { echo $e->getMessage(); }
