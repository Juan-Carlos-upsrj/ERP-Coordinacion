import subprocess

# Upload the file
cmd_scp = ["scp", "tmp_inspect_db.php", "yeici@gestionacademica.tailaf0046.ts.net:/home/yeici/tmp_inspect_db.php"]
subprocess.run(cmd_scp, check=True)

# Run the uploaded PHP file
cmd_ssh = [
    "ssh", "yeici@gestionacademica.tailaf0046.ts.net",
    "cd /var/www/html/coordinacion && php /home/yeici/tmp_inspect_db.php"
]
proc = subprocess.run(cmd_ssh, capture_output=True, text=True)
print(proc.stdout)
if proc.stderr:
    print("ERROR:", proc.stderr)
