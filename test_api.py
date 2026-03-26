import requests
import json

url = "https://gestionacademica.tailaf0046.ts.net/coordinacion/api/sync.php"
headers = {"X-API-KEY": "UPSRJ_2025_SECURE_SYNC", "Content-Type": "application/json"}

print("Test 1: get-today-sync for JUAN FRANCISCO")
r1 = requests.post(url, headers=headers, json={"action": "get-today-sync", "profesor_nombre": "JUAN FRANCISCO ZAMORA FLORES"})
print(f"Status: {r1.status_code}")
print(f"Len: {len(r1.content)}")
print(r1.text[:200])

print("\nTest 2: get-asistencias for JUAN FRANCISCO")
r2 = requests.post(url, headers=headers, json={"action": "get-asistencias", "profesor_nombre": "JUAN FRANCISCO ZAMORA FLORES"})
print(f"Status: {r2.status_code}")
print(f"Len: {len(r2.content)}")
print(r2.text[:200])
