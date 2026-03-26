import subprocess

# Script to query today's data directly
cmd_ssh = [
    "ssh", "yeici@gestionacademica.tailaf0046.ts.net",
    "sudo -u postgres psql erp_academico -x -c \"SELECT * FROM asistencia_clases WHERE fecha = CURRENT_DATE AND (profesor_nombre ILIKE '%JUAN FRANCISCO%' OR profesor_nombre ILIKE '%ZAMORA%');\""
]
proc = subprocess.run(cmd_ssh, capture_output=True, text=True)
print("--- DATOS DE HOY PARA JUAN FRANCISCO ZAMORA ---")
print(proc.stdout)

cmd_ssh_all = [
    "ssh", "yeici@gestionacademica.tailaf0046.ts.net",
    "sudo -u postgres psql erp_academico -c \"SELECT fecha, COUNT(*) FROM asistencia_clases WHERE (profesor_nombre ILIKE '%JUAN FRANCISCO%' OR profesor_nombre ILIKE '%ZAMORA%') GROUP BY fecha ORDER BY fecha DESC LIMIT 5;\""
]
proc2 = subprocess.run(cmd_ssh_all, capture_output=True, text=True)
print("--- HISTORICO DE FECHAS DE JUAN FRANCISCO ZAMORA ---")
print(proc2.stdout)
