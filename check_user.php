<?php
require_once 'config.php';
try {
    $pdo = getConnection(DB_NAME);
    $email = 'yeici@test.com'; // I'll search for this or partial
    $stmt = $pdo->prepare("SELECT * FROM profesores WHERE email ILIKE ?");
    $stmt->execute(['%yeici%']);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
