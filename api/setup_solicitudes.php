<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection(DB_NAME);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
    CREATE TABLE IF NOT EXISTS hor_solicitudes_justificantes (
        id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        alumno_nombre   VARCHAR(255) NOT NULL,
        matricula       VARCHAR(50) NOT NULL,
        fecha_ausencia  DATE NOT NULL,
        motivo          TEXT NOT NULL,
        archivo_url     VARCHAR(500),
        estado          VARCHAR(50) DEFAULT 'pendiente',
        creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW()
    );
    CREATE INDEX IF NOT EXISTS idx_solicitudes_estado ON hor_solicitudes_justificantes(estado);
    CREATE INDEX IF NOT EXISTS idx_solicitudes_matricula ON hor_solicitudes_justificantes(matricula);
    ";

    $pdo->exec($sql);
    echo "OK: Tabla de solicitudes creada correctamente en la DB compartida.";

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
