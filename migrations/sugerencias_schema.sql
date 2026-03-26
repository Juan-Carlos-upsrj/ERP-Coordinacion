-- migración para sugerencias (se ejecuta en cada base de datos de carrera)
CREATE TABLE IF NOT EXISTS sugerencias (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    prioridad VARCHAR(20) NOT NULL DEFAULT 'Normal', -- 'Baja', 'Normal', 'Alta', 'Urgente'
    categoria VARCHAR(50) NOT NULL DEFAULT 'Otro',    -- 'Academica', 'Infraestructura', 'Sistema', 'Otro'
    estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',  -- 'Pendiente', 'Vista', 'Resuelta', 'Descartada'
    enviado_por VARCHAR(100) NOT NULL,                -- El nombre del coordinador que la subió
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
