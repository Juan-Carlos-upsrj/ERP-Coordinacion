-- migración: create_coordinadores.sql
-- Crear tabla para coordinadores en la base de datos erp_academico

CREATE TABLE IF NOT EXISTS coordinadores (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    carrera_sigla VARCHAR(10) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Poblamiento inicial basado en config.php (Contraseñas en texto plano por ahora para no romper el acceso inmediato, 
-- pero se recomienda cambiarlas pronto para usar password_hash en PHP)
INSERT INTO coordinadores (nombre, carrera_sigla, password_hash, rol) VALUES
('Coordinador IAEV', 'IAEV', 'AsisIAEV', 'admin'),
('Apoyo IAEV', 'IAEV', 'ApoyoIAEV2025', 'apoyo'),
('Coordinador LTF', 'LTF', 'AsisLTF', 'admin'),
('Coordinador ISW', 'ISW', 'AsisISW', 'admin'),
('Coordinador IMI', 'IMI', 'AsisIMI', 'admin'),
('Coordinador IRC', 'IRC', 'AsisIRC', 'admin'),
('Coordinador ISA', 'ISA', 'AsisISA', 'admin')
ON CONFLICT DO NOTHING;

-- Otorgar permisos al usuario de la aplicación
GRANT ALL ON TABLE coordinadores TO admin_erp;
GRANT USAGE, SELECT ON SEQUENCE coordinadores_id_seq TO admin_erp;
