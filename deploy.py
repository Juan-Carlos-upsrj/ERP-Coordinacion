import os
import subprocess
import getpass
import sys

# === CONFIGURACION DE DESPLIEGUE ===
LOCAL_DIR = r"C:\Coordinacion"
LOCAL_APP_RELEASE = r"C:\Coordinacion\updates"
REMOTE_SERVER = "yeici@gestionacademica.tailaf0046.ts.net"
REMOTE_PATH = "/var/www/html/coordinacion"
REMOTE_UPDATES_PATH = "/var/www/html/coordinacion/updates"
REMOTE_TEMP_PATH = "/home/yeici/coordinacion_deploy"

# Archivos y carpetas a EXCLUIR del deploy (logs, temporales, scripts locales)
EXCLUDE = [
    ".git",
    "node_modules",
    "*.log",
    "tmp_*.php",
    "test_*.php",
    "debug_*.php",
    "dump_*.php",
    "check_*.php",
    "diag_*.php",
    "cleanup_*.php",
    "run_migration.php",
    "deploy.py",
    "INSTRUCCIONES_ANTIGRAVITY.md",
    "test_payload.json",
]

print("===============================================")
print("  DESPLIEGUE — COORDINACION (SECURE)")
print("===============================================")

# Pedir la contraseña de SUDO una sola vez al inicio
print("\n[CONFIG] Ingresa la contraseña de SUDO del servidor remoto.")
sudo_pass = getpass.getpass("Contraseña SUDO: ")

if not sudo_pass:
    print("❌ Error: Se requiere la contraseña de SUDO.")
    sys.exit(1)

# Construir los argumentos --exclude para tar
exclude_args = []
for ex in EXCLUDE:
    exclude_args += ["--exclude", f"./{ex}"]

# --- FASE 1: DESPLIEGUE DE API/WEB ---
print("\n[1/2] 📦 Empaquetando y desplegando archivos de Coordinación...")

commands = [
    f"rm -rf {REMOTE_TEMP_PATH}",
    f"mkdir -p {REMOTE_TEMP_PATH}",
    f"tar -xf - -C {REMOTE_TEMP_PATH}",
    f"echo '{sudo_pass}' | sudo -S chown -R www-data:www-data {REMOTE_PATH}",
    f"echo '{sudo_pass}' | sudo -S chmod -R 755 {REMOTE_PATH}",
    # Borrar contenido viejo (excepto la carpeta updates y el .env)
    f"echo '{sudo_pass}' | sudo -S find {REMOTE_PATH} -mindepth 1 -maxdepth 1 ! -name updates ! -name .env -exec rm -rf {{}} \\;",
    # Copiar archivos nuevos
    f"echo '{sudo_pass}' | sudo -S cp -r {REMOTE_TEMP_PATH}/. {REMOTE_PATH}/",
    f"echo '{sudo_pass}' | sudo -S chown -R www-data:www-data {REMOTE_PATH}",
    f"echo '{sudo_pass}' | sudo -S chmod -R 755 {REMOTE_PATH}",
    f"echo '{sudo_pass}' | sudo -S systemctl reload apache2",
    f"rm -rf {REMOTE_TEMP_PATH}",
    "echo '===DEPLOY_OK==='"
]
remote_logic = " && ".join(commands)

try:
    tar_cmd = ["tar", "-cf", "-", "-C", LOCAL_DIR] + exclude_args + ["."]
    ssh_cmd = ["ssh", REMOTE_SERVER, remote_logic]

    print("   → Iniciando transferencia SSH...")
    tar_proc = subprocess.Popen(tar_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    ssh_proc = subprocess.Popen(ssh_cmd, stdin=tar_proc.stdout, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    tar_proc.stdout.close()

    stdout, stderr = ssh_proc.communicate()
    tar_proc.wait()

    output = stdout.decode("utf-8", errors="replace")
    errors = stderr.decode("utf-8", errors="replace")

    if "===DEPLOY_OK===" in output:
        print("✅ Web/API desplegada correctamente en el servidor.")
    else:
        print("❌ El deploy terminó pero no se confirmó el éxito.")
        print("   STDOUT:", output[-500:] if output else "(vacío)")
        if errors:
            print("   STDERR:", errors[-500:])
        sys.exit(1)

    if errors and "sudo" not in errors.lower():
        print("   ⚠️  Advertencias SSH:", errors[:300])

except FileNotFoundError:
    print("❌ Error: No se encontró el comando 'tar' o 'ssh' en el PATH.")
    print("   Asegúrate de tener Git Bash o WSL en el PATH.")
    sys.exit(1)
except Exception as e:
    print(f"❌ Error inesperado en despliegue Web: {str(e)}")
    sys.exit(1)

# --- FASE 2: DESPLIEGUE DE ACTUALIZACIONES (OPCIONAL) ---
if os.path.exists(LOCAL_APP_RELEASE):
    resp = input("\n[2/2] ¿Deseas subir las actualizaciones de la App (folder release)? (s/n): ")
    if resp.lower() == 's':
        print("🚀 Subiendo binarios de actualización...")

        update_commands = [
            f"rm -rf {REMOTE_TEMP_PATH}",
            f"mkdir -p {REMOTE_TEMP_PATH}",
            f"tar -xf - -C {REMOTE_TEMP_PATH}",
            f"echo '{sudo_pass}' | sudo -S mkdir -p {REMOTE_UPDATES_PATH}",
            f"echo '{sudo_pass}' | sudo -S cp -r {REMOTE_TEMP_PATH}/. {REMOTE_UPDATES_PATH}/",
            f"echo '{sudo_pass}' | sudo -S chown -R www-data:www-data {REMOTE_UPDATES_PATH}",
            f"echo '{sudo_pass}' | sudo -S chmod -R 755 {REMOTE_UPDATES_PATH}",
            f"rm -rf {REMOTE_TEMP_PATH}",
            "echo '===UPDATES_OK==='"
        ]
        update_logic = " && ".join(update_commands)

        try:
            files_to_upload = []
            found_critical = False

            for f in os.listdir(LOCAL_APP_RELEASE):
                if f == "latest.yml":
                    files_to_upload.append(f)
                    found_critical = True
                elif f.endswith(".exe") or f.endswith(".blockmap") or f.endswith(".builder.yml") or f == ".htaccess":
                    files_to_upload.append(f)

            if not found_critical:
                print("⚠️  Advertencia: No se encontró 'latest.yml'. La App no detectará la actualización.")

            if not files_to_upload:
                print("❌ Error: No hay archivos para subir en la carpeta release.")
            else:
                tar_app_cmd = ["tar", "-cf", "-", "-C", LOCAL_APP_RELEASE] + files_to_upload
                ssh_app_cmd = ["ssh", REMOTE_SERVER, update_logic]

                tar_app_proc = subprocess.Popen(tar_app_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                ssh_app_proc = subprocess.Popen(ssh_app_cmd, stdin=tar_app_proc.stdout, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                tar_app_proc.stdout.close()

                stdout_u, stderr_u = ssh_app_proc.communicate()
                tar_app_proc.wait()

                out_u = stdout_u.decode("utf-8", errors="replace")
                if "===UPDATES_OK===" in out_u:
                    print(f"✅ Se subieron {len(files_to_upload)} archivos de actualización.")
                else:
                    print("❌ No se confirmó la subida de actualizaciones.")
                    print("   Output:", out_u[-300:])
        except Exception as e:
            print(f"❌ Error subiendo actualizaciones: {str(e)}")
else:
    print("\n[INFO] No se encontró la carpeta 'release' de la App. Saltando fase 2.")

print("\n\n✨ PROCESO FINALIZADO ✨")
print(f"🌍 Web:     https://gestionacademica.tailaf0046.ts.net/coordinacion/")
print(f"📦 Updates: https://gestionacademica.tailaf0046.ts.net/coordinacion/updates/latest.yml")
