-- migrations/horarios_alter_v3.sql
-- Agrega fechas de inicio/fin del cuatrimestre a la configuración.

ALTER TABLE hor_configuracion
    ADD COLUMN IF NOT EXISTS fecha_inicio DATE,
    ADD COLUMN IF NOT EXISTS fecha_fin    DATE;
