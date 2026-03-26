-- ============================================================
-- migrations/horarios_config_max.sql
-- Fase 15: Configuración dinámica de cuatrimestres
-- ============================================================

ALTER TABLE hor_configuracion ADD COLUMN IF NOT EXISTS max_cuatrimestres INTEGER DEFAULT 10;
