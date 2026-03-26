-- ============================================================
-- migrations/horarios_alter_v5.sql
-- Añadir horas_totales a hor_materias para cálculo dinámico
-- ============================================================

ALTER TABLE hor_materias
ADD COLUMN IF NOT EXISTS horas_totales INTEGER NULL;
