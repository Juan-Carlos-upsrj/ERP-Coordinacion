-- migrations/docentes_sync_v1.sql
-- Garantizar unicidad de emails para sincronización bidireccional

-- 1. Tabla global de erp_academico
ALTER TABLE profesores ADD CONSTRAINT unique_profesor_email UNIQUE (email);

-- 2. Tabla local de gestor de horarios (por carrera)
-- Nota: Como hor_docentes usa RLS por carrera_id, el email es único POR CARRERA.
ALTER TABLE hor_docentes ADD CONSTRAINT unique_hor_docente_carrera_email UNIQUE (carrera_id, email);
