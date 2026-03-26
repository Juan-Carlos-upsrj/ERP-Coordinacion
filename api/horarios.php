<?php
/**
 * api/horarios.php
 * REST API para el Gestor de Horarios.
 * Reemplaza Firebase como capa de datos.
 * 
 * Rutas:
 *   GET    ?resource=docentes|materias|grupos|aulas|clases|config
 *   POST   ?resource=... (body JSON = crear)
 *   PUT    ?resource=...&id=UUID (body JSON = actualizar)
 *   DELETE ?resource=...&id=UUID
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/models/HorariosModel.php';
require_once __DIR__ . '/../app/services/ScheduleGeneratorService.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Autenticación: debe haber sesión activa
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla] ?? $CARRERAS['IAEV'];
$carrera_id    = $carrera_info['carrera_id'];
$pdo = getConnection($carrera_info['db_name'], $carrera_id);

$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? '';
$id       = $_GET['id'] ?? null;

// Leer body JSON para POST/PUT
$body = [];
if (in_array($method, ['POST', 'PUT'])) {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

try {
    $result = match ($resource) {
        'docentes'       => handleDocentes($pdo, $carrera_id, $method, $id, $body),
        'materias'       => handleMaterias($pdo, $carrera_id, $method, $id, $body),
        'grupos'         => handleGrupos($pdo, $carrera_id, $method, $id, $body),
        'aulas'          => handleAulas($pdo, $carrera_id, $method, $id, $body),
        'clases'         => handleClases($pdo, $carrera_id, $method, $id, $body),
        'config'         => handleConfig($pdo, $carrera_id, $method, $body),
        'bulk_materias'  => handleBulkMaterias($pdo, $carrera_id, $body),
        'sync_docentes'  => handleSyncDocentes($pdo, $carrera_id),
        'sync_grupos'    => handleSyncGrupos($pdo, $carrera_id),
        'upload_alumnos' => handleUploadAlumnos($pdo, $carrera_id, $id, $body),
        'docente_materias'=> handleDocenteMaterias($pdo, $carrera_id, $method, $id, $body),
        'bulk_alumnos'   => handleBulkAlumnos($pdo, $carrera_id, $body),
        'analisis'       => handleAnalisis($pdo, $carrera_id),
        'planes'         => handlePlanes($pdo, $carrera_id),
        'debug_schema'   => handleDebugSchema($pdo),
        'generate_horario' => handleGenerateHorario($pdo, $carrera_id, $body),
        'promote_groups'   => HorariosModel::promoteGroups($pdo, $carrera_id),
        'copy_period'      => HorariosModel::copyPeriod($pdo, $carrera_id, $body['from'] ?? null, $body['to'] ?? null),
        default          => throw new InvalidArgumentException("Recurso desconocido: {$resource}")
    };

    echo json_encode(['success' => true, 'data' => $result]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ─── HANDLERS ─────────────────────────────────────────────────────────────────

function handleDocentes(PDO $pdo, int $carrera_id, string $method, ?string $id, array $body): mixed {
    return match ($method) {
        'GET'    => HorariosModel::getDocentes($pdo, $carrera_id),
        'POST'   => HorariosModel::createDocente($pdo, $carrera_id, $body),
        'PUT'    => HorariosModel::updateDocente($pdo, requireId($id), $carrera_id, $body),
        'DELETE' => ['deleted' => HorariosModel::deleteDocente($pdo, requireId($id), $carrera_id)],
        default  => throw new \Exception("Método no soportado")
    };
}

function handleMaterias(PDO $pdo, int $carrera_id, string $method, ?string $id, array $body): mixed {
    return match ($method) {
        'GET'    => HorariosModel::getMaterias($pdo, $carrera_id),
        'POST'   => HorariosModel::createMateria($pdo, $carrera_id, $body),
        'PUT'    => HorariosModel::updateMateria($pdo, requireId($id), $carrera_id, $body),
        'DELETE' => ['deleted' => HorariosModel::deleteMateria($pdo, requireId($id), $carrera_id)],
        default  => throw new \Exception("Método no soportado")
    };
}

function handleGrupos(PDO $pdo, int $carrera_id, string $method, ?string $id, array $body): mixed {
    return match ($method) {
        'GET'    => HorariosModel::getGrupos($pdo, $carrera_id),
        'POST'   => HorariosModel::upsertGrupo($pdo, $carrera_id, $body),
        'PUT'    => HorariosModel::upsertGrupo($pdo, $carrera_id, $body),
        'DELETE' => ['deleted' => HorariosModel::deleteGrupo($pdo, requireId($id), $carrera_id)],
        default  => throw new \Exception("Método no soportado")
    };
}

function handleAulas(PDO $pdo, int $carrera_id, string $method, ?string $id, array $body): mixed {
    return match ($method) {
        'GET'    => HorariosModel::getAulas($pdo, $carrera_id),
        'POST'   => HorariosModel::createAula($pdo, $carrera_id, $body),
        'PUT'    => HorariosModel::updateAula($pdo, requireId($id), $carrera_id, $body),
        'DELETE' => ['deleted' => HorariosModel::deleteAula($pdo, requireId($id), $carrera_id)],
        default  => throw new \Exception("Método no soportado")
    };
}

function handleClases(PDO $pdo, int $carrera_id, string $method, ?string $id, array $body): mixed {
    return match ($method) {
        'GET'    => HorariosModel::getClases($pdo, $carrera_id, $_GET['periodo'] ?? null),
        'POST'   => HorariosModel::createClase($pdo, $carrera_id, $body),
        'PUT'    => HorariosModel::updateClase($pdo, requireId($id), $carrera_id, $body),
        'DELETE' => ['deleted' => HorariosModel::deleteClase($pdo, requireId($id), $carrera_id)],
        default  => throw new \Exception("Método no soportado")
    };
}

function handleConfig(PDO $pdo, int $carrera_id, string $method, array $body): mixed {
    return match ($method) {
        'GET' => HorariosModel::getConfig($pdo, $carrera_id),
        'PUT', 'POST' => HorariosModel::updateConfig($pdo, $carrera_id, $body),
        default => throw new \Exception("Método no soportado")
    };
}

function handleBulkMaterias(PDO $pdo, int $carrera_id, array $body): array {
    $raw   = $body['texto']      ?? '';
    $fi    = $body['fecha_inicio'] ?? null;
    $ff    = $body['fecha_fin']    ?? null;

    // Parsear líneas: "Nombre de Materia, 48, 1" o "Nombre, 48" o "Nombre"
    $filas = [];
    foreach (preg_split('/[\r\n]+/', $raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        
        $parts = array_map('trim', explode(',', $line));
        
        if (count($parts) >= 4) {
            // Nombre, Horas, Cuatri, Plan
            $plan   = array_pop($parts);
            $cuatri = array_pop($parts);
            $horas  = array_pop($parts);
            $nombre = implode(',', $parts);
            $filas[] = ['nombre' => $nombre, 'horas_totales' => (int)$horas, 'cuatrimestre' => (int)$cuatri, 'plan' => $plan];
        } elseif (count($parts) === 3) {
            // Nombre, Horas, Cuatri
            $cuatri = array_pop($parts);
            $horas  = array_pop($parts);
            $nombre = $parts[0];
            $filas[] = ['nombre' => $nombre, 'horas_totales' => (int)$horas, 'cuatrimestre' => (int)$cuatri];
        } elseif (count($parts) === 2) {
            $horas  = array_pop($parts);
            $nombre = $parts[0];
            $filas[] = ['nombre' => $nombre, 'horas_totales' => (int)$horas];
        } else {
            $filas[] = ['nombre' => $line];
        }
    }
    return HorariosModel::bulkCreateMaterias($pdo, $carrera_id, $filas, $fi ?: null, $ff ?: null);
}

function handleSyncDocentes(PDO $pdo, int $carrera_id): array {
    $asistencia = HorariosModel::getProfesoresFromAsistencia($pdo);
    
    // También traer de la lista global (Autorizados)
    require_once __DIR__ . '/../app/models/AdminModel.php';
    global $CARRERAS;
    
    // Buscar la sigla de la carrera_id actual
    $sigla = 'IAEV';
    foreach ($CARRERAS as $s => $c) {
        if ($c['carrera_id'] === $carrera_id) { $sigla = $s; break; }
    }
    
    $pdo_global = getConnection(DB_NAME);
    $globales = AdminModel::getGlobalTeachersByCarrera($pdo_global, $sigla);
    
    // Mapear globales al formato esperado (solo nombre y email para el sync)
    foreach ($globales as $g) {
        $nombre = trim($g['nombre'] . ' ' . $g['apellidos']);
        // Verificar si ya está en la lista de asistencia para no duplicar en el retorno
        $exists = false;
        foreach ($asistencia as $a) {
            if (mb_strtolower($a['nombre']) === mb_strtolower($nombre)) { $exists = true; break; }
        }
        if (!$exists) {
            $asistencia[] = [
                'nombre' => $nombre,
                'email'  => $g['email'],
                'source' => 'global'
            ];
        } else {
            // Si ya existe por nombre, le inyectamos el email de la tabla global si el de asistencia estaba vacío
            foreach ($asistencia as &$a) {
                if (mb_strtolower($a['nombre']) === mb_strtolower($nombre)) {
                    $a['email'] = $g['email'];
                    $a['source'] = 'both';
                }
            }
        }
    }
    
    return $asistencia;
}

function handleSyncGrupos(PDO $pdo, int $carrera_id): array {
    return HorariosModel::getGruposFromAsistencia($pdo);
}

function handleUploadAlumnos(PDO $pdo, int $carrera_id, ?string $grupo_id, array $body): array {
    if (empty($grupo_id)) throw new \InvalidArgumentException("Se requiere grupo_id");
    $alumnos = $body['alumnos'] ?? [];
    $stmt = $pdo->prepare("
        UPDATE hor_grupos SET alumnos = ?::jsonb, actualizado_en = NOW()
        WHERE id = ? AND carrera_id = ?
        RETURNING *
    ");
    $stmt->execute([json_encode($alumnos), $grupo_id, $carrera_id]);
    $row = $stmt->fetch();
    if ($row) $row['alumnos'] = json_decode($row['alumnos'] ?? '[]', true);
    return $row ?: [];
}

function handleDocenteMaterias(PDO $pdo, int $carrera_id, string $method, ?string $docente_id, array $body): mixed {
    if (empty($docente_id)) throw new \InvalidArgumentException("Se requiere docente_id");
    if ($method === 'GET') {
        return HorariosModel::getDocenteMaterias($pdo, $docente_id);
    }
    // POST / PUT: reemplazar preferencias
    $ids = $body['materia_ids'] ?? [];
    HorariosModel::setDocenteMaterias($pdo, $docente_id, $ids);
    return HorariosModel::getDocenteMaterias($pdo, $docente_id);
}

function handleAnalisis(PDO $pdo, int $carrera_id): array {
    // Materias agrupadas por cuatrimestre
    $materias = HorariosModel::getMaterias($pdo, $carrera_id);
    // Grupos agrupados por cuatrimestre
    $grupos   = HorariosModel::getGrupos($pdo, $carrera_id);
    // Clases programadas: grupo_id → [materia_id, ...]
    $clases   = HorariosModel::getClases($pdo, $carrera_id);

    $asignadas = []; // grupo_id => Set de materia_id
    foreach ($clases as $c) {
        if ($c['grupo_id'] && $c['materia_id']) {
            $asignadas[$c['grupo_id']][$c['materia_id']] = true;
        }
    }

    // Agrupar materias por cuatrimestre
    $matsByCuatri = [];
    foreach ($materias as $m) {
        $cuatri = $m['cuatrimestre'] ?? 0;
        $matsByCuatri[$cuatri][] = $m;
    }

    // Agrupar grupos por cuatrimestre
    $gruposByCuatri = [];
    foreach ($grupos as $g) {
        $cuatri = $g['cuatrimestre'] ?? 1;
        $gruposByCuatri[$cuatri][] = $g;
    }

    // Construir análisis
    $resultado = [];
    $allCuatris = array_unique(array_merge(array_keys($matsByCuatri), array_keys($gruposByCuatri)));
    sort($allCuatris);

    foreach ($allCuatris as $cuatri) {
        $mats   = $matsByCuatri[$cuatri]  ?? [];
        $grps   = $gruposByCuatri[$cuatri] ?? [];

        $gruposAnalisis = [];
        foreach ($grps as $g) {
            $asig = $asignadas[$g['id']] ?? [];
            $materiasGrupo = [];
            foreach ($mats as $m) {
                $materiasGrupo[] = [
                    'materia_id'  => $m['id'],
                    'nombre'      => $m['nombre'],
                    'horas_semanales' => $m['horas_semanales'],
                    'asignada'    => isset($asig[$m['id']])
                ];
            }
            $total   = count($materiasGrupo);
            $cubiert = count(array_filter($materiasGrupo, fn($x)=>$x['asignada']));
            $gruposAnalisis[] = [
                'grupo_id'    => $g['id'],
                'nombre'      => $g['nombre'],
                'turno'       => $g['turno'],
                'materias'    => $materiasGrupo,
                'total'       => $total,
                'cubiertas'   => $cubiert,
                'faltantes'   => $total - $cubiert
            ];
        }
        $resultado[] = [
            'cuatrimestre' => $cuatri,
            'grupos'       => $gruposAnalisis,
            'total_materias' => count($mats)
        ];
    }
    return $resultado;
}

function handlePlanes(PDO $pdo, int $carrera_id): array {
    return HorariosModel::getPlanes($pdo, $carrera_id);
}

function requireId(?string $id): string {
    if (empty($id)) throw new \InvalidArgumentException("Se requiere un ID");
    return $id;
}
function handleBulkAlumnos(PDO $pdo, int $carrera_id, array $body): array {
    $nombreGrupo = $body['nombre_grupo'] ?? null;
    if (!$nombreGrupo) throw new \InvalidArgumentException("Nombre de grupo requerido");

    // Upsert the group first
    $grupoData = [
        'nombre' => $nombreGrupo,
        'cuatrimestre' => $body['cuatrimestre'] ?? 1,
        'turno' => $body['turno'] ?? 'matutino',
        'alumnos' => $body['alumnos'] ?? []
    ];
    
    return HorariosModel::upsertGrupo($pdo, $carrera_id, $grupoData);
}
function handleGenerateHorario(PDO $pdo, int $carrera_id, array $body): array {
    $dry_run = $body['dry_run'] ?? true;
    $filters = [
        'docente_ids' => $body['docente_ids'] ?? [],
        'grupo_ids'   => $body['grupo_ids']   ?? [],
        'periodo'     => $body['periodo']     ?? null
    ];
    
    $service = new ScheduleGeneratorService($pdo, $carrera_id);
    return $service->generate($dry_run, $filters);
}

function handleDebugSchema(PDO $pdo): array {
    $res = [];
    // 1. Columnas de hor_clases
    $stmt = $pdo->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'hor_clases'");
    $stmt->execute();
    $res['columns'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 2. Dueño de la tabla
    $stmt = $pdo->prepare("SELECT tableowner FROM pg_tables WHERE tablename = 'hor_clases'");
    $stmt->execute();
    $res['owner'] = $stmt->fetchColumn();
    
    // 3. Usuario actual
    $stmt = $pdo->prepare("SELECT CURRENT_USER");
    $stmt->execute();
    $res['current_user'] = $stmt->fetchColumn();
    
    return $res;
}
