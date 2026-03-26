-- ============================================================
-- migrations/asistencia_justificaciones.sql
-- Fase 13: Soporte para Justificantes Médicos/Administrativos
-- ============================================================

-- 0. Asegurar extensión pgcrypto para gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 1. Manejar el conflicto de la columna 'id'
-- Resulta que ya existe una columna 'id' de tipo INTEGER. 
-- Vamos a renombrarla para no perder datos y crear nuestra UUID PK.

DO $$ 
BEGIN
    -- 1.1 Renombrar id existente si es integer
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='asistencia_clases' AND column_name='id' AND data_type='integer') THEN
        ALTER TABLE asistencia_clases RENAME COLUMN id TO id_legacy;
    END IF;

    -- 1.2 Agregar nueva columna UUID si no existe
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='asistencia_clases' AND column_name='id') THEN
        ALTER TABLE asistencia_clases ADD COLUMN id UUID DEFAULT gen_random_uuid();
    END IF;
END $$;

-- 1.3 Llenar vacíos si los hay
UPDATE asistencia_clases SET id = gen_random_uuid() WHERE id IS NULL;

-- 1.4 Asegurar que sea la Primary Key (opcional pero recomendado para hor_justificaciones)
-- Nota: Esto podría fallar si ya hay otra PK, por eso lo hacemos con cuidado
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE table_name='asistencia_clases' AND constraint_type='PRIMARY KEY') THEN
        ALTER TABLE asistencia_clases ADD PRIMARY KEY (id);
    END IF;
EXCEPTION WHEN others THEN 
    RAISE NOTICE 'No se pudo establecer PK automática, procediendo...';
END $$;

-- 2. Creamos la tabla de justificaciones para auditoría
CREATE TABLE IF NOT EXISTS hor_justificaciones (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    asistencia_id   UUID NOT NULL, -- Referencia al ID de asistencia_clases
    motivo          TEXT NOT NULL,
    usuario_nombre  VARCHAR(255),  -- Quién justificó (de la sesión)
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Index para búsqueda rápida
CREATE INDEX IF NOT EXISTS idx_justificaciones_asistencia ON hor_justificaciones(asistencia_id);
