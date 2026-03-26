import subprocess

cmd_ssh = [
    "ssh", "yeici@gestionacademica.tailaf0046.ts.net",
    "sudo -u postgres psql erp_academico -x -c 'SELECT id, profesor_nombre, carrera_id FROM asistencia_clases ORDER BY fecha_subida DESC LIMIT 1;'"
]
proc = subprocess.run(cmd_ssh, capture_output=True, text=True)
print(proc.stdout)
