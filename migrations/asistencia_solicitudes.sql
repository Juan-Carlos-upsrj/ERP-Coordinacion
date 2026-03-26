-- ============================================================
-- migrations/asistencia_solicitudes.sql
-- Fase 14: Bandeja de entrada para solicitudes de justificantes de alumnos
-- ============================================================

-- Creamos la tabla de solicitudes de justificaciones (para los alumnos)
CREATE TABLE IF NOT EXISTS hor_solicitudes_justificantes (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    alumno_nombre   VARCHAR(255) NOT NULL,
    matricula       VARCHAR(50) NOT NULL,
    fecha_ausencia  DATE NOT NULL,
    motivo          TEXT NOT NULL,
    archivo_url     VARCHAR(500),  -- Ruta al archivo adjunto (PDF/Imagen)
    estado          VARCHAR(50) DEFAULT 'pendiente', -- 'pendiente', 'autorizado', 'rechazado'
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Index para búsquedas rápidas en el dashboard del coordinador
CREATE INDEX IF NOT EXISTS idx_solicitudes_estado ON hor_solicitudes_justificantes(estado);
CREATE INDEX IF NOT EXISTS idx_solicitudes_matricula ON hor_solicitudes_justificantes(matricula);
