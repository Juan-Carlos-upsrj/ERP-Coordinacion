-- migration/horarios_plan_v6.sql
-- Agregar soporte para Plan de Estudios

ALTER TABLE hor_materias ADD COLUMN plan VARCHAR(100) DEFAULT 'Plan Regular';
CREATE INDEX idx_hor_materias_plan ON hor_materias(carrera_id, plan);

-- Comentario para el usuario
-- Esta migración permite diferenciar materias de distintos planes de estudios
-- dentro de la misma carrera (ej: Animación 2018 vs Animación 2024).
