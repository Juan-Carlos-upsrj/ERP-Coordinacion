<?php
/**
 * api/migrate_db.php
 * Ejecuta la migración de base de datos para el Generador Automático.
 */
require_once __DIR__ . '/../config.php';

// Seguridad: solo con key
if (($_GET['key'] ?? '') !== 'upsrj_gen_2026') {
    die("No autorizado");
}

try {
    $pdo = getConnection(DB_NAME);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
    -- 1. Docentes
    ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS disponibilidad JSONB DEFAULT '[]';
    ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS carga_max_diaria INT DEFAULT 8;
    ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS carga_max_semanal INT DEFAULT 40;

    -- 2. Materias
    ALTER TABLE hor_materias ADD COLUMN IF NOT EXISTS prioridad TEXT DEFAULT 'Media';
    ALTER TABLE hor_materias ADD COLUMN IF NOT EXISTS es_especialidad BOOLEAN DEFAULT FALSE;

    -- 3. Grupos
    ALTER TABLE hor_grupos ADD COLUMN IF NOT EXISTS capacidad_maxima INT DEFAULT 30;
    
    -- 4. Portal de Alumnos: Solicitudes de Justificantes
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
    echo json_encode(['success' => true, 'message' => 'Esquema actualizado correctamente']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
