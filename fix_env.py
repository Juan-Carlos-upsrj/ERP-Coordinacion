import subprocess
import getpass
import sys

print("===============================================")
print("  HOTFIX — RESTAURANDO ACCESO A LA BD")
print("===============================================")
print("\nEl deploy anterior de seguridad borró el archivo .env de contraseñas.")
print("Por favor, ingresa tu contraseña de SUDO para restaurarlo ahora mismo.\n")

sudo_pass = getpass.getpass("Contraseña SUDO: ")

commands = [
    "sudo -S mv /home/yeici/env_tmp.txt /var/www/html/coordinacion/.env",
    "sudo -S chown www-data:www-data /var/www/html/coordinacion/.env",
    "sudo -S chmod 644 /var/www/html/coordinacion/.env",
    "echo '===HOTFIX_OK==='"
]
remote_logic = f"echo '{sudo_pass}' | " + f" && echo '{sudo_pass}' | ".join(commands)

ssh_cmd = ["ssh", "yeici@gestionacademica.tailaf0046.ts.net", remote_logic]
try:
    proc = subprocess.Popen(ssh_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    stdout, stderr = proc.communicate()
    out = stdout.decode('utf-8', errors='replace')
    
    if "===HOTFIX_OK===" in out:
        print("\n✅ ¡Listo! Archivo .env restaurado correctamente.")
        print("🌍 Ya puedes actualizar la página web y el panel cargará bien.")
    else:
        print("\n❌ Hubo un error:")
        print(stderr.decode('utf-8', errors='replace'))
except Exception as e:
    print(f"\n❌ Excepción: {e}")
