<?php
/**
 * api/sync.php
 * Super-Debug Version
 */

// Errores a pantalla y log para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY, X-CARRERA, x-api-key, x-carrera');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';
require_once '../app/core/Utils.php';

// Helper de limpieza
function cleanNameSync($name) {
    if (!$name) return 'SIN NOMBRE';
    $name = trim($name);
    if (function_exists('mb_strtoupper')) {
        $name = mb_strtoupper($name, 'UTF-8');
    } else {
        $name = strtoupper($name);
    }
    $search  = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
    $replace = ['A', 'E', 'I', 'O', 'U', 'N'];
    return str_replace($search, $replace, $name);
}

try {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided_key = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_api_key'] ?? null;

    if (!$provided_key || $provided_key !== API_KEY) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'API KEY inválida']);
        exit;
    }

    $json_data = file_get_contents('php://input');
    $input = json_decode($json_data, true);

    if (!$input && $_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
        exit;
    }

    // --- 0. AUDITORÍA DE SINCRONIZACIÓN ---
    $audit_dir = __DIR__ . '/../logs';
    if (!is_dir($audit_dir)) @mkdir($audit_dir, 0777, true);
    $audit_file = $audit_dir . '/sync_audit.log';
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $action_id = $input['action'] ?? (isset($input['data']) ? 'sync-attendance' : 'unknown');
    $prof_id = $input['profesor_nombre'] ?? ($input['data'][0]['profesor_nombre'] ?? 'N/A');
    
    $log_line = sprintf("[%s] IP: %s | Prof: %s | Action: %s | UA: %s\n", 
        date('Y-m-d H:i:s'), $ip, $prof_id, $action_id, $ua);
    @file_put_contents($audit_file, $log_line, FILE_APPEND);

    $header_carrera = $_SERVER['HTTP_X_CARRERA'] ?? $headers['X-CARRERA'] ?? $headers['X-Carrera'] ?? $headers['x-carrera'] ?? null;
    $carrera_sigla = strtoupper($header_carrera ?? $input['carrera'] ?? 'IAEV');
    $carrera_info = $CARRERAS[$carrera_sigla] ?? $CARRERAS['IAEV'];
    $carrera_id = $carrera_info['carrera_id'];

    $pdo = getConnection($carrera_info['db_name'], $carrera_id);
    $action = $input['action'] ?? null;

    // --- CASO 1: ASISTENCIA (Auto-detección de columnas) ---
    $lista_asistencia = $input['data'] ?? (is_array($input) && isset($input[0]['status']) ? $input : null);
    if ($lista_asistencia && !$action) {
        // Verificar columnas reales para no fallar
        $stmt_cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'asistencia_clases'");
        $existing_cols = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);
        
        $has_cid = in_array('carrera_id', $existing_cols);
        $has_gid = in_array('grupo_id', $existing_cols);
        $has_mid = in_array('materia_id', $existing_cols);

        $pdo->beginTransaction();
        try {
            if ($has_mid) {
                $sqlDelete = "DELETE FROM asistencia_clases WHERE alumno_id = :aid AND fecha = :f AND materia_id = :mid";
            } else {
                $sqlDelete = "DELETE FROM asistencia_clases WHERE alumno_id = :aid AND fecha = :f AND materia_nombre = :mn";
            }
            $stmtDel = $pdo->prepare($sqlDelete);

            $cols_q = ["profesor_nombre", "grupo_nombre", "materia_nombre", "alumno_id", "alumno_nombre", "fecha", "status"];
            $vals_q = [":pn", ":gn", ":mn", ":aid", ":an", ":f", ":s"];
            
            if ($has_cid) { $cols_q[] = "carrera_id"; $vals_q[] = ":cid"; }
            if ($has_gid) { $cols_q[] = "grupo_id"; $vals_q[] = ":gid"; }
            if ($has_mid) { $cols_q[] = "materia_id"; $vals_q[] = ":mid"; }

            $sqlInsert = "INSERT INTO asistencia_clases (" . implode(',', $cols_q) . ") VALUES (" . implode(',', $vals_q) . ")";
            $stmtIns = $pdo->prepare($sqlInsert);

            $procesados = 0;
            $materia_cache = [];
            foreach ($lista_asistencia as $reg) {
                if (!empty($reg['fecha']) && isset($reg['status'])) {
                    $m_name = $reg['materia_nombre'] ?? 'Sin Materia';
                    $m_id = $reg['materia_id'] ?? '';

                    if ($has_mid && empty($m_id)) {
                        if (isset($materia_cache[$m_name])) {
                            $m_id = $materia_cache[$m_name];
                        } else {
                            $stmtFind = $pdo->prepare("SELECT id FROM hor_materias WHERE nombre = ? AND carrera_id = ? LIMIT 1");
                            $stmtFind->execute([$m_name, $carrera_id]);
                            $found = $stmtFind->fetchColumn();
                            $m_id = $found ?: '0';
                            $materia_cache[$m_name] = $m_id;
                        }
                    }

                    if ($has_mid) {
                        $stmtDel->execute([
                            ':aid' => $reg['alumno_id'] ?? '0',
                            ':f'   => $reg['fecha'],
                            ':mid' => $m_id
                        ]);
                    } else {
                        $stmtDel->execute([
                            ':aid' => $reg['alumno_id'] ?? '0',
                            ':f'   => $reg['fecha'],
                            ':mn'  => $m_name
                        ]);
                    }

                    $params = [
                        ':pn'  => $reg['profesor_nombre'] ?? 'Desconocido',
                        ':gn'  => $reg['grupo_nombre'] ?? 'Sin Grupo',
                        ':mn'  => $m_name,
                        ':aid' => $reg['alumno_id'] ?? '0',
                        ':an'  => cleanNameSync($reg['alumno_nombre']),
                        ':f'   => $reg['fecha'],
                        ':s'   => $reg['status']
                    ];
                    if ($has_cid) $params[':cid'] = $carrera_id;
                    if ($has_gid) $params[':gid'] = $reg['grupo_id'] ?? '0';
                    if ($has_mid) $params[':mid'] = $m_id;

                    $stmtIns->execute($params);
                    $procesados++;
                }
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'asistencias_guardadas' => $procesados]);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // --- CASO 2: GET ASISTENCIAS ---
    if ($action === 'get-asistencias') {
        $profesor = $input['profesor_nombre'] ?? '';
        $sql = "SELECT alumno_id, alumno_nombre, grupo_nombre, materia_nombre, fecha, status 
                FROM asistencia_clases WHERE profesor_nombre = ? ORDER BY fecha DESC LIMIT 2000";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$profesor]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        exit;
    }

    // --- CASO 3: CALIFICACIONES (Auto-detección) ---
    if ($action === 'sync-calificaciones' && isset($input['data'])) {
        $stmt_cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'calificaciones_finales'");
        $existing_cols = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);
        
        $has_cid = in_array('carrera_id', $existing_cols);
        $has_mid = in_array('materia_id', $existing_cols);

        $pdo->beginTransaction();
        try {
            $cols_q = ["profesor_nombre", "grupo_id", "grupo_nombre", "materia_nombre", "alumno_id", "alumno_nombre", "alumno_matricula", "evaluacion_nombre", "parcial", "calificacion"];
            $vals_q = [":pn", ":gid", ":gn", ":mn", ":aid", ":an", ":am", ":en", ":p", ":c"];
            
            if ($has_cid) { $cols_q[] = "carrera_id"; $vals_q[] = ":cid"; }
            if ($has_mid) { $cols_q[] = "materia_id"; $vals_q[] = ":mid"; }

            $sql = "INSERT INTO calificaciones_finales (" . implode(',', $cols_q) . ") VALUES (" . implode(',', $vals_q) . ") 
                    ON CONFLICT (alumno_id, " . ($has_mid ? "materia_id" : "materia_nombre") . ", parcial) 
                    DO UPDATE SET calificacion = EXCLUDED.calificacion, updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $pdo->prepare($sql);
            $count = 0;
            foreach ($input['data'] as $reg) {
                $params = [
                    ':pn'  => $reg['profesor_nombre'] ?? '',
                    ':gid' => $reg['grupo_id'] ?? '',
                    ':gn'  => $reg['grupo_nombre'] ?? '',
                    ':mn'  => $reg['materia_nombre'] ?? '',
                    ':aid' => $reg['alumno_id'] ?? '',
                    ':an'  => cleanNameSync($reg['alumno_nombre']),
                    ':am'  => $reg['alumno_matricula'] ?? '',
                    ':en'  => $reg['evaluacion_nombre'] ?? '',
                    ':p'   => $reg['parcial'] ?? 1,
                    ':c'   => $reg['calificacion'] ?? 0
                ];
                if ($has_cid) $params[':cid'] = $carrera_id;
                if ($has_mid) $params[':mid'] = $reg['materia_id'] ?? '0';

                $stmt->execute($params);
                $count++;
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'procesados' => $count]);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // --- CASO 4: TUTOREO ---
    if ($action === 'get-tutoreo') {
        $profesor = $input['profesor_nombre'] ?? '';
        $sql = "SELECT alumno_id, alumno_nombre, grupo_nombre, fortalezas, oportunidades, resumen 
                FROM fichas_tutoreo WHERE profesor_nombre = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$profesor]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'sync-tutoreo' && isset($input['data'])) {
        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO fichas_tutoreo 
                        (alumno_id, alumno_nombre, grupo_nombre, profesor_nombre, fortalezas, oportunidades, resumen, carrera_id)
                    VALUES 
                        (:aid, :an, :gn, :pn, :f, :o, :r, :cid)
                    ON CONFLICT (alumno_id) 
                    DO UPDATE SET 
                        fortalezas = EXCLUDED.fortalezas, 
                        oportunidades = EXCLUDED.oportunidades, 
                        resumen = EXCLUDED.resumen,
                        fecha_actualizacion = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            foreach ($input['data'] as $reg) {
                $stmt->execute([
                    ':aid' => $reg['alumno_id'],
                    ':an'  => cleanNameSync($reg['alumno_nombre']),
                    ':gn'  => $reg['grupo_nombre'] ?? 'Sin Grupo',
                    ':pn'  => $input['profesor_nombre'] ?? 'Desconocido',
                    ':f'   => $reg['fortalezas'] ?? '',
                    ':o'   => $reg['oportunidades'] ?? '',
                    ':r'   => $reg['resumen'] ?? '',
                    ':cid' => $carrera_id
                ]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // --- CASO 5: GET-CONFIG ---
    if ($action === 'get-config') {
        $email = $input['email'] ?? null;
        if (!$email) {
            echo json_encode(['status' => 'error', 'message' => 'Email no proporcionado']);
            exit;
        }

        $pdo_global = getConnection(DB_NAME); 
        $stmt = $pdo_global->prepare("SELECT nombre, apellidos, carrera_sigla FROM profesores WHERE email = ? AND carrera_sigla = ? LIMIT 1");
        $stmt->execute([$email, $carrera_sigla]);
        $profesor = $stmt->fetch();

        if ($profesor) {
            $sigla = $profesor['carrera_sigla'];
            $c_info = $CARRERAS[$sigla] ?? null;
            
            $config = [];
            if ($c_info) {
                require_once '../app/models/HorariosModel.php';
                $pdo_carrera = getConnection($c_info['db_name'], $c_info['carrera_id']);
                $config = HorariosModel::getConfig($pdo_carrera, $c_info['carrera_id']);
            }

            echo json_encode([
                'status' => 'success',
                'profesor' => [
                    'nombre' => $profesor['nombre'] . ' ' . $profesor['apellidos'],
                    'email' => $email,
                    'carrera_sigla' => $sigla,
                    'carrera_nombre' => $c_info ? $c_info['nombre_largo'] : 'Carrera no configurada'
                ],
                'config' => $config
            ]);
        } else {
            error_log("SYNC: get-config failed for email={$email}, carrera_sigla={$carrera_sigla}");
            echo json_encode(['status' => 'error', 'message' => 'Docente no autorizado.', 'debug_server' => $_SERVER, 'debug_headers' => $headers]);
        }
        exit;
    }

    // --- CASO 6: GET-FULL-SCHEDULE ---
    if ($action === 'get-full-schedule') {
        $profesor_nombre = $input['profesor_nombre'] ?? null;
        if (!$profesor_nombre) {
            echo json_encode(['status' => 'error', 'message' => 'Nombre no proporcionado']);
            exit;
        }

        require_once '../app/models/HorariosModel.php';
        $clases = HorariosModel::getClases($pdo, $carrera_id);
        $grupos = HorariosModel::getGrupos($pdo, $carrera_id);

        $horario_filtrado = array_filter($clases, function($c) use ($profesor_nombre) {
            return stripos($c['docente_nombre'], $profesor_nombre) !== false;
        });

        echo json_encode([
            'status' => 'success',
            'data' => array_values($horario_filtrado),
            'grupos' => $grupos
        ]);
        exit;
    }

    // --- CASO 2: OBTENER ASISTENCIAS EXISTENTES PARA COMPARAR ---
    if ($action === 'get-asistencias') {
        $fecha = $input['fecha'] ?? null;
        if (!$fecha) { echo json_encode(['status' => 'error', 'message' => 'Falta fecha']); exit; }
        
        $sql = "SELECT ac.id, ac.alumno_id, ac.status, ac.fecha 
                FROM asistencia_clases ac 
                WHERE ac.fecha = ? AND ac.carrera_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fecha, $carrera_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- CASO EXTRA: STATUS DE HOY ---
    if ($action === 'fetch-today-sync') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, MAX(fecha_subida) as ultima 
                               FROM asistencia_clases 
                               WHERE fecha = CURRENT_DATE AND carrera_id = ?");
        $stmt->execute([$carrera_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetch()]);
        exit;
    }

    // --- CASO 7: GET-TODAY-SYNC ---
    if ($action === 'get-today-sync') {
        $profesor = $input['profesor_nombre'] ?? '';
        $fecha = $input['fecha'] ?? date('Y-m-d');
        
        $sql = "SELECT DISTINCT grupo_nombre, materia_nombre 
                FROM asistencia_clases 
                WHERE profesor_nombre = ? AND fecha = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$profesor, $fecha]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Acción no soportada en modo debug o no reconocida']);

} catch (Throwable $t) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $t->getMessage(),
        'file' => $t->getFile(),
        'line' => $t->getLine(),
        'trace' => $t->getTraceAsString()
    ]);
}
