import subprocess

def run_ssh_psql(sql):
    cmd = ["ssh", "yeici@gestionacademica.tailaf0046.ts.net", f"sudo -u postgres psql erp_academico -c \"{sql}\""]
    proc = subprocess.run(cmd, capture_output=True, text=True)
    return proc.stdout, proc.stderr

# Check schema
sql_check = "SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_name IN ('hor_docentes', 'hor_materias', 'hor_grupos') ORDER BY table_name, ordinal_position;"
stdout, stderr = run_ssh_psql(sql_check)
print("STDOUT:\n", stdout)
print("STDERR:\n", stderr)

# Migration SQL
migration_sql = """
-- Agregar disponibilidad y carga horaria a docentes
ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS disponibilidad JSONB DEFAULT '[]';
ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS carga_max_diaria INT DEFAULT 8;
ALTER TABLE hor_docentes ADD COLUMN IF NOT EXISTS carga_max_semanal INT DEFAULT 40;

-- Agregar prioridad y especialidad a materias
ALTER TABLE hor_materias ADD COLUMN IF NOT EXISTS prioridad TEXT DEFAULT 'Media';
ALTER TABLE hor_materias ADD COLUMN IF NOT EXISTS es_especialidad BOOLEAN DEFAULT FALSE;

-- Agregar capacidad máxima a grupos
ALTER TABLE hor_grupos ADD COLUMN IF NOT EXISTS capacidad_maxima INT DEFAULT 30;
"""

# Run migration (one by one to be safe)
for line in migration_sql.strip().split(';'):
    line = line.strip()
    if line:
        print(f"Executing: {line}")
        stdout, stderr = run_ssh_psql(line)
        print(stdout, stderr)
