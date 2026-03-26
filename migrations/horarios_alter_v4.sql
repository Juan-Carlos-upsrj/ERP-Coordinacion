-- migrations/horarios_alter_v4.sql
-- Añade cuatrimestre a materias y tabla de materias preferidas por docente.

-- 1. Columna cuatrimestre en materias (0 = aplica a todos)
ALTER TABLE hor_materias ADD COLUMN IF NOT EXISTS cuatrimestre SMALLINT NOT NULL DEFAULT 0;

-- 2. Tabla de materias preferidas por docente
CREATE TABLE IF NOT EXISTS hor_docente_materias (
    docente_id  UUID NOT NULL REFERENCES hor_docentes(id) ON DELETE CASCADE,
    materia_id  UUID NOT NULL REFERENCES hor_materias(id) ON DELETE CASCADE,
    PRIMARY KEY (docente_id, materia_id)
);
