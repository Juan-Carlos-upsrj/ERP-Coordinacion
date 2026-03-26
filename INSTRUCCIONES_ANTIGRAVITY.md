# Contexto del Proyecto Coordinación (Panel Administrador UPSRJ)

¡Hola Antigravity! Si estás leyendo esto, el usuario ha migrado su entorno de trabajo a otra computadora y necesita que retomes el proyecto exactamente donde lo dejamos. 

Aquí tienes el contexto vital para operar:

## 1. ¿Qué es este proyecto?
Es un panel web basado en PHP vanilla (patrón MVC simple) construido para coordinadores académicos de la UPSRJ. Su principal objetivo es consolidar asistencias de estudiantes recolectadas a través de una App móvil (TestListasv2) por los profesores, y generar métricas de rendimiento y alertas de anomalías.

## 2. Arquitectura
- **Root:** `c:\Coordinacion`
- **Controlador Principal:** `index.php` (maneja el enrutamiento y las acciones POST mediante query params ej. `?v=duplicados`).
- **Modelos:** `app/models/` (destacan `AlumnosModel.php`, `ReportesModel.php`, `Utils.php`).
- **Vistas:** `app/views/layout/` (sidebar, header, footer) y `app/views/pages/` (las distintas pantallas).
- **API Sincronización:** `api/sync.php` (Recibe los JSON de carga masiva de la app móvil).
- **Estilos:** Tailwind CSS vía CDN.

## 3. Últimos Logros Críticos (Versión 5.7)
Hemos estado trabajando duro en el **Saneamiento de Base de Datos** (eliminación de alumnos fantasma y unificación de historiales de estudiantes duplicados por errores de dedo en la app).

Implementamos:
1.  **Auto-Heal en API:** Ahora `api/sync.php` limpia los nombres al recibirlos (los pasa a UPPERCASE, remueve dobles espacios y quita acentos usando `Utils::eliminarAcentos`).
2.  **Consolidación Inteligente (Fuzzy Merge):** En `duplicados.php` y `AlumnosModel.php`, implementamos un sistema que detecta alumnos que comparten grupo y tienen nombres muy similares (usando la extensión **`pg_trgm`** de PostgreSQL).
3.  **UI de Asistente de Fusión:** El usuario ya no ve una lista fea; ve un "Wizard" (Asistente Individual) en `duplicados.php` que le muestra los alumnos similares uno a uno, desplegando con qué profesores han tomado clase para ayudarle a decidir el nombre correcto y unificarlos.
4.  **Tono Natural:** Quitamos la jerga de "Inteligencia Artificial" de la UI. Ahora se lee como una herramienta administrativa normal.

## 4. Estructura de Base de Datos Base
Usamos **PostgreSQL**. La tabla core es `asistencia_clases`.
- Campos críticos: `alumno_id` (fragmentado), `alumno_nombre` (la clave base actual para agrupar), `grupo_nombre`, `profesor_nombre`, `fecha_hora`.

## 5. Próximos Pasos (Dependiendo de lo que pida el usuario)
- Seguro querrá probar la Fusión Borrosa en entorno de producción o seguir afinando el "Panel de Anomalías".
- Mantener siempre presente no romper las vistas que usan `GROUP BY UPPER(alumno_nombre)`.

¡Buena suerte retomando el control!
