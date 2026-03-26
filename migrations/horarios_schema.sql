-- ============================================================
-- migrations/horarios_schema.sql
-- Esquema de tablas para el Gestor de Horarios (migración desde Firebase)
-- Compatible con RLS existente de la plataforma.
-- ============================================================

-- Crear schema si no existe
CREATE SCHEMA IF NOT EXISTS horarios;

-- ─── 1. DOCENTES ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hor_docentes (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    carrera_id  INTEGER NOT NULL DEFAULT 0,       -- Para filtro multi-carrera
    nombre      VARCHAR(255) NOT NULL,
    email       VARCHAR(255),
    horas_asesoria INTEGER NOT NULL DEFAULT 0,    -- Horas de asesoría obligatorias
    color_hex   VARCHAR(7) NOT NULL DEFAULT '#3b82f6',  -- Color ID en la cuadrícula
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_hor_docentes_carrera ON hor_docentes(carrera_id);

-- ─── 2. MATERIAS ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hor_materias (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    carrera_id      INTEGER NOT NULL DEFAULT 0,
    nombre          VARCHAR(255) NOT NULL,
    horas_semanales INTEGER NOT NULL DEFAULT 2,
    docente_id      UUID REFERENCES hor_docentes(id) ON DELETE SET NULL,
    es_externa      BOOLEAN NOT NULL DEFAULT FALSE,   -- true = materia de intercambio/ext
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actualizado_en  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_hor_materias_carrera ON hor_materias(carrera_id);
CREATE INDEX IF NOT EXISTS idx_hor_materias_docente ON hor_materias(docente_id);

-- ─── 3. GRUPOS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hor_grupos (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    carrera_id      INTEGER NOT NULL DEFAULT 0,
    nombre          VARCHAR(100) NOT NULL,          -- Ej: IAEV-41
    cuatrimestre    INTEGER NOT NULL DEFAULT 1,
    turno           VARCHAR(20) DEFAULT 'matutino', -- matutino / vespertino
    alumnos         JSONB DEFAULT '[]',              -- [{id, name, matricula}]
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actualizado_en  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (carrera_id, nombre)
);

CREATE INDEX IF NOT EXISTS idx_hor_grupos_carrera ON hor_grupos(carrera_id);

-- ─── 4. AULAS / ESPACIOS ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hor_aulas (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    carrera_id      INTEGER NOT NULL DEFAULT 0,
    nombre          VARCHAR(100) NOT NULL,
    tipo            VARCHAR(20) NOT NULL DEFAULT 'aula',  -- aula / laboratorio / oficina
    capacidad       INTEGER NOT NULL DEFAULT 30,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actualizado_en  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_hor_aulas_carrera ON hor_aulas(carrera_id);

-- ─── 5. CLASES / BLOQUE DE HORARIO ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hor_clases (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    carrera_id      INTEGER NOT NULL DEFAULT 0,
    dia             VARCHAR(15) NOT NULL,           -- Lunes, Martes, … Viernes, Sábado
    hora_inicio     INTEGER NOT NULL,               -- Hora en formato 24h: 7-21
    duracion        INTEGER NOT NULL DEFAULT 1,     -- Horas de duración
    docente_id      UUID REFERENCES hor_docentes(id) ON DELETE CASCADE,
    grupo_id        UUID REFERENCES hor_grupos(id) ON DELETE CASCADE,
    materia_id      UUID REFERENCES hor_materias(id) ON DELETE CASCADE,
    aula_id         UUID REFERENCES hor_aulas(id) ON DELETE SET NULL,
    es_asesoria     BOOLEAN NOT NULL DEFAULT FALSE, -- true = es bloque de asesoría
    notas           TEXT,
    creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actualizado_en  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_hor_clases_carrera ON hor_clases(carrera_id);
CREATE INDEX IF NOT EXISTS idx_hor_clases_docente ON hor_clases(docente_id);
CREATE INDEX IF NOT EXISTS idx_hor_clases_grupo   ON hor_clases(grupo_id);

-- ─── 6. CONFIGURACIÓN GLOBAL ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hor_configuracion (
    id              SERIAL PRIMARY KEY,
    carrera_id      INTEGER NOT NULL UNIQUE DEFAULT 0,
    turno_corte     INTEGER NOT NULL DEFAULT 4,     -- Cuatrimestre donde empieza turno vespertino
    anio_activo     INTEGER NOT NULL DEFAULT 2025,
    cuatrimestre_activo INTEGER NOT NULL DEFAULT 1,
    actualizado_en  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Insertar configuración por defecto si no existe
INSERT INTO hor_configuracion (carrera_id, turno_corte, anio_activo, cuatrimestre_activo)
VALUES (1, 4, 2025, 1)
ON CONFLICT (carrera_id) DO NOTHING;

-- ─── 7. TRIGGER: actualizar timestamps automáticamente ───────────────────────
CREATE OR REPLACE FUNCTION update_hor_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.actualizado_en = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_docentes_ts') THEN
        CREATE TRIGGER trg_docentes_ts BEFORE UPDATE ON hor_docentes FOR EACH ROW EXECUTE FUNCTION update_hor_timestamp();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_materias_ts') THEN
        CREATE TRIGGER trg_materias_ts BEFORE UPDATE ON hor_materias FOR EACH ROW EXECUTE FUNCTION update_hor_timestamp();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_grupos_ts') THEN
        CREATE TRIGGER trg_grupos_ts BEFORE UPDATE ON hor_grupos FOR EACH ROW EXECUTE FUNCTION update_hor_timestamp();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_aulas_ts') THEN
        CREATE TRIGGER trg_aulas_ts BEFORE UPDATE ON hor_aulas FOR EACH ROW EXECUTE FUNCTION update_hor_timestamp();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_clases_ts') THEN
        CREATE TRIGGER trg_clases_ts BEFORE UPDATE ON hor_clases FOR EACH ROW EXECUTE FUNCTION update_hor_timestamp();
    END IF;
END $$;
