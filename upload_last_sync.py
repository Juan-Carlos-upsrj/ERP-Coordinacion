import subprocess

subprocess.run(["scp", "tmp_last_sync.php", "yeici@gestionacademica.tailaf0046.ts.net:/home/yeici/tmp_last_sync.php"], check=True)
proc = subprocess.run([
    "ssh", "yeici@gestionacademica.tailaf0046.ts.net",
    "sudo mv /home/yeici/tmp_last_sync.php /var/www/html/coordinacion/tmp_last_sync.php && sudo chown www-data:www-data /var/www/html/coordinacion/tmp_last_sync.php"
], capture_output=True, text=True)
print(proc.stderr)
