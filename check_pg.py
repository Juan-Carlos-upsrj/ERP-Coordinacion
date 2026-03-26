import subprocess
cmd = ["ssh", "yeici@gestionacademica.tailaf0046.ts.net", "sudo -u postgres psql erp_academico -c \"SELECT fecha, COUNT(*) FROM asistencia_clases WHERE profesor_nombre ILIKE '%JUAN FRANCISCO%' GROUP BY fecha ORDER BY fecha DESC;\""]
proc = subprocess.run(cmd, capture_output=True, text=True)
print(proc.stdout)
print(proc.stderr)
