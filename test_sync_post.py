import requests

url = 'https://gestionacademica.tailaf0046.ts.net/coordinacion/api/sync.php'
headers = {'X-API-KEY': 'UPSRJ_2025_SECURE_SYNC', 'Content-Type': 'application/json'}
payload = [
    {
        "profesor_nombre": "JUAN FRANCISCO ZAMORA FLORES",
        "materia_nombre": "Animación 3D",
        "grupo_id": "xyz",
        "grupo_nombre": "IAEV-PA-07",
        "alumno_id": "123",
        "alumno_nombre": "TEST ALUMNO",
        "fecha": "2026-03-17",
        "status": "Presente"
    }
]

r = requests.post(url, headers=headers, json=payload)
print(r.status_code)
print(r.text)
