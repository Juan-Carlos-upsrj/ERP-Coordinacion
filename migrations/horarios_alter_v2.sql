-- ============================================================
-- migrations/horarios_alter_v2.sql
-- Agrega columna 'edificio' a hor_aulas (si no existe).
-- ============================================================

ALTER TABLE hor_aulas ADD COLUMN IF NOT EXISTS edificio VARCHAR(100);
