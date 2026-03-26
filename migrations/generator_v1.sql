-- Migración para el Generador Automático (2026-03-18)
-- 1. Docentes: Disponibilidad (bloques JSON) y Cargas Máximas
ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS disponibilidad JSONB DEFAULT '[]';
ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS carga_max_diaria INT DEFAULT 8;
ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS carga_max_semanal INT DEFAULT 40;

-- 2. Materias: Prioridad y Etiqueta Especialidad
ALTER TABLE hor_materias ADD COLUMN IF NOT EXISTS prioridad TEXT DEFAULT 'Media';
ALTER TABLE hor_materias ADD COLUMN IF NOT EXISTS es_especialidad BOOLEAN DEFAULT FALSE;

-- 3. Grupos: Capacidad Máxima
ALTER TABLE hor_grupos ADD COLUMN IF NOT EXISTS capacidad_maxima INT DEFAULT 30;
