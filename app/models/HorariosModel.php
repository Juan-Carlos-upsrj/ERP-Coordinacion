<?php
require_once __DIR__ . '/AdminModel.php';

class HorariosModel {
    private static $hasPlan = null;
    private static function checkPlan(PDO $pdo) {
        if (self::$hasPlan !== null) return self::$hasPlan;
        try {
            $pdo->query("SELECT plan FROM hor_materias LIMIT 1");
            self::$hasPlan = true;
        } catch (Exception $e) { self::$hasPlan = false; }
        return self::$hasPlan;
    }

    // ─── DOCENTES ────────────────────────────────────────────────────────────

    public static function getDocentes(PDO $pdo, int $carrera_id): array {
        self::syncDocentesAutorizados($pdo, $carrera_id);

        try {
            $stmt = $pdo->prepare("
                SELECT id, nombre, email, horas_asesoria, color_hex, disponibilidad, carga_max_diaria, carga_max_semanal, activo, aulas_preferidas
                FROM hor_docentes
                WHERE carrera_id = ? AND activo = TRUE
                ORDER BY nombre ASC
            ");
            $stmt->execute([$carrera_id]);
        } catch (\Exception $e) {
            // Si la columna aulas_preferidas no existe aún, fallamos a la versión básica
            $stmt = $pdo->prepare("
                SELECT id, nombre, email, horas_asesoria, color_hex, disponibilidad, carga_max_diaria, carga_max_semanal, activo
                FROM hor_docentes
                WHERE carrera_id = ? AND activo = TRUE
                ORDER BY nombre ASC
            ");
            $stmt->execute([$carrera_id]);
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['disponibilidad'] = json_decode($r['disponibilidad'] ?? '[]', true);
            $r['aulas_preferidas'] = json_decode($r['aulas_preferidas'] ?? '[]', true);
        }
        return $rows;
    }

    public static function syncDocentesAutorizados(PDO $pdo, int $carrera_id) {
        $carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
        
        try {
            // 1. Obtener autorizados de la BD Global
            $pdo_global = getConnection(DB_NAME);
            $stmt = $pdo_global->prepare("SELECT nombre, apellidos, email FROM profesores WHERE carrera_sigla = ?");
            $stmt->execute([$carrera_sigla]);
            $autorizados = $stmt->fetchAll();

            if (empty($autorizados)) return;

            // 2. Asegurar que existan en hor_docentes
            $emails_autorizados = [];
            foreach ($autorizados as $auth) {
                $nombre_completo = trim($auth['nombre'] . ' ' . $auth['apellidos']);
                $email = strtolower(trim($auth['email']));
                $emails_autorizados[] = $email;

                // Verificar si ya existe
                $check = $pdo->prepare("SELECT id FROM hor_docentes WHERE carrera_id = ? AND LOWER(email) = ?");
                $check->execute([$carrera_id, $email]);
                $doc = $check->fetch();

                if (!$doc) {
                    // Crear con valores por defecto
                    $color = '#' . substr(md5($email), 0, 6); // Color semi-aleatorio basado en email
                    $ins = $pdo->prepare("
                        INSERT INTO hor_docentes (carrera_id, nombre, email, color_hex, activo)
                        VALUES (?, ?, ?, ?, TRUE)
                    ");
                    $ins->execute([$carrera_id, $nombre_completo, $email, $color]);
                } else {
                    // Asegurar que esté activo y el nombre esté actualizado
                    $upd = $pdo->prepare("UPDATE hor_docentes SET activo = TRUE, nombre = ? WHERE id = ?");
                    $upd->execute([$nombre_completo, $doc['id']]);
                }
            }

            // 3. Desactivar los que ya no están autorizados (opcional, pero recomendado por el usuario)
            if (!empty($emails_autorizados)) {
                $placeholders = implode(',', array_fill(0, count($emails_autorizados), '?'));
                $sql_deact = "UPDATE hor_docentes SET activo = FALSE WHERE carrera_id = ? AND LOWER(email) NOT IN ($placeholders)";
                $params = array_merge([$carrera_id], $emails_autorizados);
                $pdo->prepare($sql_deact)->execute($params);
            }

        } catch (Exception $e) {
            // Log error or ignore if global DB fails
            error_log("Error sincronizando docentes: " . $e->getMessage());
        }
    }

    public static function createDocente(PDO $pdo, int $carrera_id, array $data): array {
        $aulas = json_encode($data['aulas_preferidas'] ?? []);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO hor_docentes (carrera_id, nombre, email, horas_asesoria, color_hex, disponibilidad, carga_max_diaria, carga_max_semanal, aulas_preferidas)
                VALUES (?, ?, ?, ?, ?, ?::jsonb, ?, ?, ?::jsonb)
                RETURNING *
            ");
            $stmt->execute([
                $carrera_id,
                trim($data['nombre']),
                trim($data['email'] ?? ''),
                (int)($data['horas_asesoria'] ?? 0),
                $data['color_hex'] ?? '#3b82f6',
                json_encode($data['disponibilidad'] ?? []),
                (int)($data['carga_max_diaria'] ?? 8),
                (int)($data['carga_max_semanal'] ?? 40),
                $aulas
            ]);
        } catch (\Exception $e) {
            // Re-intento sin la columna aulas_preferidas
            $stmt = $pdo->prepare("
                INSERT INTO hor_docentes (carrera_id, nombre, email, horas_asesoria, color_hex, disponibilidad, carga_max_diaria, carga_max_semanal)
                VALUES (?, ?, ?, ?, ?, ?::jsonb, ?, ?)
                RETURNING *
            ");
            $stmt->execute([
                $carrera_id,
                trim($data['nombre']),
                trim($data['email'] ?? ''),
                (int)($data['horas_asesoria'] ?? 0),
                $data['color_hex'] ?? '#3b82f6',
                json_encode($data['disponibilidad'] ?? []),
                (int)($data['carga_max_diaria'] ?? 8),
                (int)($data['carga_max_semanal'] ?? 40)
            ]);
        }
        $res = $stmt->fetch();
        if ($res && !empty($res['email'])) {
            // Sincronizar con lista global (Admin Panel)
            $carrera_activa = $_SESSION['carrera_activa'] ?? 'IAEV';
            AdminModel::syncCareerToGlobal($pdo, $res['email'], $carrera_activa);
        }
        return $res;
    }

    public static function updateDocente(PDO $pdo, string $id, int $carrera_id, array $data): array {
        $pdo->beginTransaction();
        try {
            try {
                $stmt = $pdo->prepare("
                    UPDATE hor_docentes SET nombre=?, email=?, horas_asesoria=?, color_hex=?, disponibilidad=?::jsonb, carga_max_diaria=?, carga_max_semanal=?, aulas_preferidas=?::jsonb
                    WHERE id=? AND carrera_id=?
                    RETURNING *
                ");
                $stmt->execute([
                    trim($data['nombre']),
                    trim($data['email'] ?? ''),
                    (int)($data['horas_asesoria'] ?? 0),
                    $data['color_hex'] ?? '#3b82f6',
                    json_encode($data['disponibilidad'] ?? []),
                    (int)($data['carga_max_diaria'] ?? 8),
                    (int)($data['carga_max_semanal'] ?? 40),
                    json_encode($data['aulas_preferidas'] ?? []),
                    $id,
                    $carrera_id
                ]);
            } catch (\Exception $e) {
                $stmt = $pdo->prepare("
                    UPDATE hor_docentes SET nombre=?, email=?, horas_asesoria=?, color_hex=?, disponibilidad=?::jsonb, carga_max_diaria=?, carga_max_semanal=?
                    WHERE id=? AND carrera_id=?
                    RETURNING *
                ");
                $stmt->execute([
                    trim($data['nombre']),
                    trim($data['email'] ?? ''),
                    (int)($data['horas_asesoria'] ?? 0),
                    $data['color_hex'] ?? '#3b82f6',
                    json_encode($data['disponibilidad'] ?? []),
                    (int)($data['carga_max_diaria'] ?? 8),
                    (int)($data['carga_max_semanal'] ?? 40),
                    $id,
                    $carrera_id
                ]);
            }
            $res = $stmt->fetch() ?: [];

            // Sincronizar preferencias si se envían
            if (isset($data['materia_ids'])) {
                self::setDocenteMaterias($pdo, $id, $data['materia_ids']);
            }

            if ($res && !empty($res['email'])) {
                $carrera_activa = $_SESSION['carrera_activa'] ?? 'IAEV';
                AdminModel::syncCareerToGlobal($pdo, $res['email'], $carrera_activa);
            }
            $pdo->commit();
            return $res;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteDocente(PDO $pdo, string $id, int $carrera_id): bool {
        $stmt = $pdo->prepare("UPDATE hor_docentes SET activo=FALSE WHERE id=? AND carrera_id=?");
        $stmt->execute([$id, $carrera_id]);
        return $stmt->rowCount() > 0;
    }

    // ─── MATERIAS ─────────────────────────────────────────────────────────────

    public static function getMaterias(PDO $pdo, int $carrera_id): array {
        $planCol = self::checkPlan($pdo) ? "m.plan" : "'Plan Regular' as plan";
        $stmt = $pdo->prepare("
            SELECT m.id, m.nombre, m.horas_semanales, m.horas_totales, m.cuatrimestre, m.docente_id, m.es_externa, m.prioridad, m.es_especialidad, $planCol,
                   d.nombre as docente_nombre, d.color_hex as docente_color
            FROM hor_materias m
            LEFT JOIN hor_docentes d ON d.id = m.docente_id
            WHERE m.carrera_id = ?
            ORDER BY m.cuatrimestre ASC, m.nombre ASC
        ");
        $stmt->execute([$carrera_id]);
        return $stmt->fetchAll();
    }

    public static function createMateria(PDO $pdo, int $carrera_id, array $data): array {
        $hasPlan = self::checkPlan($pdo);
        $cols = "carrera_id, nombre, horas_semanales, horas_totales, docente_id, es_externa, cuatrimestre, prioridad, es_especialidad" . ($hasPlan ? ", plan" : "");
        $vals = "?, ?, ?, ?, ?, ?, ?, ?, ?" . ($hasPlan ? ", ?" : "");
        
        $stmt = $pdo->prepare("INSERT INTO hor_materias ($cols) VALUES ($vals) RETURNING *");
        
        $docente_id = $data['docente_id'] ?: null;
        $params = [
            $carrera_id, trim($data['nombre']), (int)($data['horas_semanales'] ?? 2),
            empty($data['horas_totales']) ? null : (int)$data['horas_totales'],
            $docente_id, ($data['es_externa'] ?? false) ? 'true' : 'false',
            (int)($data['cuatrimestre'] ?? 0),
            $data['prioridad'] ?? 'Media',
            ($data['es_especialidad'] ?? false) ? 'true' : 'false'
        ];
        if ($hasPlan) $params[] = $data['plan'] ?? 'Plan Regular';
        
        $stmt->execute($params);
        $res = $stmt->fetch();

        // Sincronizar preferencia si hay docente
        if ($res && $docente_id) {
            $check = $pdo->prepare("SELECT 1 FROM hor_docente_materias WHERE docente_id=? AND materia_id=?");
            $check->execute([$docente_id, $res['id']]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO hor_docente_materias (docente_id, materia_id) VALUES (?,?)")
                    ->execute([$docente_id, $res['id']]);
            }
        }

        if (!$hasPlan && $res) $res['plan'] = 'Plan Regular';
        return $res;
    }

    public static function bulkCreateMaterias(PDO $pdo, int $carrera_id, array $filas, ?string $fecha_inicio = null, ?string $fecha_fin = null): array {
        $semanas = null;
        if ($fecha_inicio && $fecha_fin) {
            $ini = new \DateTime($fecha_inicio);
            $fin = new \DateTime($fecha_fin);
            $dias = (int)$ini->diff($fin)->days;
            if ($dias > 0) $semanas = (int)ceil($dias / 7);
        }

        $hasPlan = self::checkPlan($pdo);
        $cols = "carrera_id, nombre, horas_semanales, horas_totales, cuatrimestre" . ($hasPlan ? ", plan" : "");
        $vals = "?, ?, ?, ?, ?" . ($hasPlan ? ", ?" : "");
        $stmt = $pdo->prepare("INSERT INTO hor_materias ($cols) VALUES ($vals) RETURNING *");
        
        $created = [];
        foreach ($filas as $fila) {
            $nombre = trim($fila['nombre'] ?? '');
            if (empty($nombre)) continue;
            
            $horasTotales  = isset($fila['horas_totales']) ? (int)$fila['horas_totales'] : null;
            $horasSemanales = isset($fila['horas_semanales']) ? (int)$fila['horas_semanales'] : 2;
            $cuatrimestre  = isset($fila['cuatrimestre']) ? (int)$fila['cuatrimestre'] : 0;

            if ($horasTotales !== null && $semanas) {
                $horasSemanales = (int)ceil($horasTotales / $semanas);
            } elseif ($horasTotales !== null) {
                $horasSemanales = isset($fila['horas_semanales']) ? (int)$fila['horas_semanales'] : $horasTotales;
            }

            $params = [$carrera_id, $nombre, max(1, $horasSemanales), $horasTotales, max(0, $cuatrimestre)];
            if ($hasPlan) $params[] = $fila['plan'] ?? 'Plan Regular';

            $stmt->execute($params);
            $row = $stmt->fetch();
            if ($row) {
                // Sincronizar preferencia si hay docente (aunque en bulkCreate original no se pasaba docente_id, lo dejamos listo)
                if (!empty($fila['docente_id'])) {
                    $pdo->prepare("INSERT INTO hor_docente_materias (docente_id, materia_id) VALUES (?,?) ON CONFLICT DO NOTHING")
                        ->execute([$fila['docente_id'], $row['id']]);
                }
                if (!$hasPlan) $row['plan'] = 'Plan Regular';
                $created[] = $row;
            }
        }
        return $created;
    }

    // ── SYNC DESDE ASISTENCIA_CLASES ─────────────────────────────────────────

    public static function getProfesoresFromAsistencia(PDO $pdo): array {
        $stmt = $pdo->query("
            SELECT DISTINCT profesor_nombre as nombre
            FROM asistencia_clases
            WHERE profesor_nombre IS NOT NULL AND TRIM(profesor_nombre) != ''
            ORDER BY profesor_nombre ASC
        ");
        return $stmt->fetchAll();
    }

    public static function getGruposFromAsistencia(PDO $pdo): array {
        $stmt = $pdo->query("
            SELECT DISTINCT grupo_nombre as nombre
            FROM asistencia_clases
            WHERE grupo_nombre IS NOT NULL AND TRIM(grupo_nombre) != ''
            ORDER BY grupo_nombre ASC
        ");
        return $stmt->fetchAll();
    }

    public static function updateMateria(PDO $pdo, string $id, int $carrera_id, array $data): array {
        $hasPlan = self::checkPlan($pdo);
        $set = "nombre=?, horas_semanales=?, horas_totales=?, docente_id=?, es_externa=?, cuatrimestre=?, prioridad=?, es_especialidad=?" . ($hasPlan ? ", plan=?" : "");
        $stmt = $pdo->prepare("UPDATE hor_materias SET $set WHERE id=? AND carrera_id=? RETURNING *");
        
        $docente_id = $data['docente_id'] ?: null;
        $params = [
            trim($data['nombre']), (int)($data['horas_semanales'] ?? 2),
            empty($data['horas_totales']) ? null : (int)$data['horas_totales'],
            $docente_id, ($data['es_externa'] ?? false) ? 'true' : 'false',
            (int)($data['cuatrimestre'] ?? 0),
            $data['prioridad'] ?? 'Media',
            ($data['es_especialidad'] ?? false) ? 'true' : 'false'
        ];
        if ($hasPlan) $params[] = $data['plan'] ?? 'Plan Regular';
        $params[] = $id; $params[] = $carrera_id;

        $stmt->execute($params);
        $res = $stmt->fetch();

        // Si se asignó un docente, asegurar que la materia esté en sus preferencias
        if ($res && $docente_id) {
            $check = $pdo->prepare("SELECT 1 FROM hor_docente_materias WHERE docente_id=? AND materia_id=?");
            $check->execute([$docente_id, $id]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare("INSERT INTO hor_docente_materias (docente_id, materia_id) VALUES (?, ?)");
                $ins->execute([$docente_id, $id]);
            }
        }

        if (!$hasPlan && $res) $res['plan'] = 'Plan Regular';
        return $res ?: [];
    }

    public static function deleteMateria(PDO $pdo, string $id, int $carrera_id): bool {
        $stmt = $pdo->prepare("DELETE FROM hor_materias WHERE id=? AND carrera_id=?");
        $stmt->execute([$id, $carrera_id]);
        return $stmt->rowCount() > 0;
    }

    public static function getPlanes(PDO $pdo, int $carrera_id): array {
        $planes = ['Plan Regular'];
        if (self::checkPlan($pdo)) {
            $stmt = $pdo->prepare("SELECT DISTINCT plan FROM hor_materias WHERE carrera_id = ? ORDER BY plan ASC");
            $stmt->execute([$carrera_id]);
            $res = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($res)) $planes = array_unique(array_merge($planes, $res));
        }
        return $planes;
    }

    // ─── GRUPOS ───────────────────────────────────────────────────────────────

    public static function getGrupos(PDO $pdo, int $carrera_id): array {
        // Intentar asegurar que existe la columna plan (requiere ser dueño de la tabla)
        try { 
            $pdo->query("SELECT plan FROM hor_grupos LIMIT 1"); 
        } catch (Exception $e) { 
            try {
                $pdo->exec("ALTER TABLE hor_grupos ADD COLUMN plan TEXT DEFAULT 'Plan Regular'"); 
            } catch (Exception $e2) {
                // Si falla por privilegios, ignoramos el error aquí y manejamos la ausencia del campo en el loop
            }
        }

        $stmt = $pdo->prepare("
            SELECT id, nombre, cuatrimestre, turno, alumnos,
                   (SELECT 1 FROM information_schema.columns WHERE table_name='hor_grupos' AND column_name='plan') as has_plan_col
            FROM hor_grupos
            WHERE carrera_id = ?
            ORDER BY cuatrimestre ASC, nombre ASC
        ");
        // Nota: Si has_plan_col es null, el SELECT siguiente fallará, así que mejor usamos una aproximación más segura:
        
        $sql = "SELECT id, nombre, cuatrimestre, turno, alumnos, capacidad_maxima";
        $hasPlan = false;
        try {
            $pdo->query("SELECT plan FROM hor_grupos LIMIT 1");
            $sql .= ", plan";
            $hasPlan = true;
        } catch(Exception $e) {}
        
        $sql .= " FROM hor_grupos WHERE carrera_id = ? ORDER BY cuatrimestre ASC, nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$carrera_id]);
        $rows = $stmt->fetchAll();
        
        // Decodificar JSONB
        foreach ($rows as &$r) {
            $r['alumnos'] = json_decode($r['alumnos'] ?? '[]', true);
            if (!$hasPlan || empty($r['plan'])) $r['plan'] = 'Plan Regular';
        }
        return $rows;
    }

    public static function upsertGrupo(PDO $pdo, int $carrera_id, array $data): array {
        // Verificar si la columna plan existe
        $hasPlan = false;
        try {
            $pdo->query("SELECT plan FROM hor_grupos LIMIT 1");
            $hasPlan = true;
        } catch(Exception $e) {}

        if ($hasPlan) {
            $stmt = $pdo->prepare("
                INSERT INTO hor_grupos (carrera_id, nombre, cuatrimestre, turno, alumnos, plan, capacidad_maxima)
                VALUES (?, ?, ?, ?, ?::jsonb, ?, ?)
                ON CONFLICT (carrera_id, nombre)
                DO UPDATE SET cuatrimestre=EXCLUDED.cuatrimestre, turno=EXCLUDED.turno, alumnos=EXCLUDED.alumnos, plan=EXCLUDED.plan, capacidad_maxima=EXCLUDED.capacidad_maxima, actualizado_en=NOW()
                RETURNING *
            ");
            $stmt->execute([
                $carrera_id, trim($data['nombre']), (int)($data['cuatrimestre'] ?? 1),
                $data['turno'] ?? 'matutino', json_encode($data['alumnos'] ?? []),
                $data['plan'] ?? 'Plan Regular',
                (int)($data['capacidad_maxima'] ?? 30)
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO hor_grupos (carrera_id, nombre, cuatrimestre, turno, alumnos, capacidad_maxima)
                VALUES (?, ?, ?, ?, ?::jsonb, ?)
                ON CONFLICT (carrera_id, nombre)
                DO UPDATE SET cuatrimestre=EXCLUDED.cuatrimestre, turno=EXCLUDED.turno, alumnos=EXCLUDED.alumnos, capacidad_maxima=EXCLUDED.capacidad_maxima, actualizado_en=NOW()
                RETURNING *
            ");
            $stmt->execute([
                $carrera_id, trim($data['nombre']), (int)($data['cuatrimestre'] ?? 1),
                $data['turno'] ?? 'matutino', json_encode($data['alumnos'] ?? []),
                (int)($data['capacidad_maxima'] ?? 30)
            ]);
        }

        $row = $stmt->fetch();
        $row['alumnos'] = json_decode($row['alumnos'] ?? '[]', true);
        if (!$hasPlan) $row['plan'] = 'Plan Regular';
        return $row;
    }

    public static function deleteGrupo(PDO $pdo, string $id, int $carrera_id): bool {
        $stmt = $pdo->prepare("DELETE FROM hor_grupos WHERE id=? AND carrera_id=?");
        $stmt->execute([$id, $carrera_id]);
        return $stmt->rowCount() > 0;
    }

    // ─── AULAS ────────────────────────────────────────────────────────────────

    public static function getAulas(PDO $pdo, int $carrera_id): array {
        $stmt = $pdo->prepare("
            SELECT id, nombre, tipo, capacidad, edificio
            FROM hor_aulas
            WHERE carrera_id = ?
            ORDER BY edificio NULLS LAST, nombre ASC
        ");
        $stmt->execute([$carrera_id]);
        return $stmt->fetchAll();
    }

    public static function createAula(PDO $pdo, int $carrera_id, array $data): array {
        $stmt = $pdo->prepare("
            INSERT INTO hor_aulas (carrera_id, nombre, tipo, capacidad, edificio)
            VALUES (?, ?, ?, ?, ?)
            RETURNING *
        ");
        $stmt->execute([
            $carrera_id,
            trim($data['nombre']),
            $data['tipo'] ?? 'aula',
            (int)($data['capacidad'] ?? 30),
            trim($data['edificio'] ?? '')
        ]);
        return $stmt->fetch();
    }

    public static function updateAula(PDO $pdo, string $id, int $carrera_id, array $data): array {
        $stmt = $pdo->prepare("
            UPDATE hor_aulas SET nombre=?, tipo=?, capacidad=?, edificio=?
            WHERE id=? AND carrera_id=?
            RETURNING *
        ");
        $stmt->execute([
            trim($data['nombre']),
            $data['tipo'] ?? 'aula',
            (int)($data['capacidad'] ?? 30),
            trim($data['edificio'] ?? ''),
            $id, $carrera_id
        ]);
        return $stmt->fetch() ?: [];
    }

    public static function deleteAula(PDO $pdo, string $id, int $carrera_id): bool {
        $stmt = $pdo->prepare("DELETE FROM hor_aulas WHERE id=? AND carrera_id=?");
        $stmt->execute([$id, $carrera_id]);
        return $stmt->rowCount() > 0;
    }

    // ─── HORARIO (CLASES) ─────────────────────────────────────────────────────

    /**
     * Convierte el valor de 'dia' a entero 1-indexado (Lunes=1 … Viernes=5).
     * Si ya es numérico lo devuelve tal cual.
     */
    private static function parseDia($dia): int {
        if (is_numeric($dia)) return (int)$dia;
        // Normalizar: strip common UTF-8 accented vowels then lowercase
        $s = strtolower(trim((string)$dia));
        $s = str_replace(
            ["\xc3\xa9", "\xc3\xa1", "\xc3\xad", "\xc3\xb3", "\xc3\xba"],
            ['e',         'a',        'i',        'o',        'u'], $s
        );
        $map = ['lunes'=>1,'martes'=>2,'miercoles'=>3,'jueves'=>4,'viernes'=>5,'sabado'=>6,'domingo'=>7];
        return $map[$s] ?? 1;
    }


    public static function getClases(PDO $pdo, int $carrera_id, ?string $periodo = null): array {
        try {
            $sql = "SELECT c.*, d.nombre as docente_nombre, d.color_hex as docente_color,
                           g.nombre as grupo_nombre, m.nombre as materia_nombre, a.nombre as aula_nombre
                    FROM hor_clases c
                    LEFT JOIN hor_docentes d ON d.id = c.docente_id
                    LEFT JOIN hor_grupos g   ON g.id = c.grupo_id
                    LEFT JOIN hor_materias m ON m.id = c.materia_id
                    LEFT JOIN hor_aulas a    ON a.id = c.aula_id
                    WHERE c.carrera_id = ?";
            $params = [$carrera_id];
            
            if ($periodo) {
                $sql .= " AND c.periodo = ?";
                $params[] = $periodo;
            }
            $sql .= " ORDER BY c.dia, c.hora_inicio";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\Exception $e) {
            // Fallback si la columna periodo no existe
            $stmt = $pdo->prepare("
                SELECT c.*, d.nombre as docente_nombre, d.color_hex as docente_color,
                       g.nombre as grupo_nombre, m.nombre as materia_nombre, a.nombre as aula_nombre
                FROM hor_clases c
                LEFT JOIN hor_docentes d ON d.id = c.docente_id
                LEFT JOIN hor_grupos g   ON g.id = c.grupo_id
                LEFT JOIN hor_materias m ON m.id = c.materia_id
                LEFT JOIN hor_aulas a    ON a.id = c.aula_id
                WHERE c.carrera_id = ?
                ORDER BY c.dia, c.hora_inicio
            ");
            $stmt->execute([$carrera_id]);
        }
        return $stmt->fetchAll();
    }

    public static function createClase(PDO $pdo, int $carrera_id, array $data): array {
        $periodo = $data['periodo'] ?? null;
        if (!$periodo) {
            $cfg = self::getConfig($pdo, $carrera_id);
            $periodo = $cfg['anio_activo'] . '-' . $cfg['cuatrimestre_activo'];
        }

        // Verificar conflictos
        $conflict = self::checkConflict($pdo, $carrera_id, $data, null, $periodo);
        if ($conflict) {
            throw new \Exception("Conflicto de horario: " . $conflict);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO hor_clases (carrera_id, dia, hora_inicio, duracion, docente_id, grupo_id, materia_id, aula_id, es_asesoria, notas, periodo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $stmt->execute([
                $carrera_id, self::parseDia($data['dia']), (int)$data['hora_inicio'], (int)($data['duracion'] ?? 1),
                $data['docente_id'] ?: null, $data['grupo_id'] ?: null, $data['materia_id'] ?: null, 
                $data['aula_id'] ?: null, ($data['es_asesoria'] ?? false) ? 'true' : 'false',
                $data['notas'] ?? null, $periodo
            ]);
        } catch (\Exception $e) {
            // Fallback sin periodo
            $stmt = $pdo->prepare("
                INSERT INTO hor_clases (carrera_id, dia, hora_inicio, duracion, docente_id, grupo_id, materia_id, aula_id, es_asesoria, notas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $stmt->execute([
                $carrera_id, self::parseDia($data['dia']), (int)$data['hora_inicio'], (int)($data['duracion'] ?? 1),
                $data['docente_id'] ?: null, $data['grupo_id'] ?: null, $data['materia_id'] ?: null, 
                $data['aula_id'] ?: null, ($data['es_asesoria'] ?? false) ? 'true' : 'false',
                $data['notas'] ?? null
            ]);
        }
        $id = $stmt->fetchColumn();
        return self::getClaseById($pdo, $id, $carrera_id);
    }

    public static function updateClase(PDO $pdo, string $id, int $carrera_id, array $data): array {
        $periodo = $data['periodo'] ?? null;
        if (!$periodo) {
            $cfg = self::getConfig($pdo, $carrera_id);
            $periodo = $cfg['anio_activo'] . '-' . $cfg['cuatrimestre_activo'];
        }

        // Verificar conflictos
        $conflict = self::checkConflict($pdo, $carrera_id, $data, $id, $periodo);
        if ($conflict) {
            throw new \Exception("Conflicto de horario: " . $conflict);
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE hor_clases SET dia=?, hora_inicio=?, duracion=?, docente_id=?, grupo_id=?, materia_id=?, aula_id=?, es_asesoria=?, notas=?, periodo=?
                WHERE id=? AND carrera_id=?
            ");
            $stmt->execute([
                self::parseDia($data['dia']), (int)$data['hora_inicio'], (int)($data['duracion'] ?? 1),
                $data['docente_id'] ?: null, $data['grupo_id'] ?: null, $data['materia_id'] ?: null, 
                $data['aula_id'] ?: null, ($data['es_asesoria'] ?? false) ? 'true' : 'false',
                $data['notas'] ?? null, $periodo, $id, $carrera_id
            ]);
        } catch (\Exception $e) {
            // Fallback sin periodo
            $stmt = $pdo->prepare("
                UPDATE hor_clases SET dia=?, hora_inicio=?, duracion=?, docente_id=?, grupo_id=?, materia_id=?, aula_id=?, es_asesoria=?, notas=?
                WHERE id=? AND carrera_id=?
            ");
            $stmt->execute([
                self::parseDia($data['dia']), (int)$data['hora_inicio'], (int)($data['duracion'] ?? 1),
                $data['docente_id'] ?: null, $data['grupo_id'] ?: null, $data['materia_id'] ?: null, 
                $data['aula_id'] ?: null, ($data['es_asesoria'] ?? false) ? 'true' : 'false',
                $data['notas'] ?? null, $id, $carrera_id
            ]);
        }
        return self::getClaseById($pdo, $id, $carrera_id);
    }

    public static function deleteClase(PDO $pdo, string $id, int $carrera_id): bool {
        $stmt = $pdo->prepare("DELETE FROM hor_clases WHERE id=? AND carrera_id=?");
        $stmt->execute([$id, $carrera_id]);
        return $stmt->rowCount() > 0;
    }

    private static function getClaseById(PDO $pdo, string $id, int $carrera_id): array {
        $stmt = $pdo->prepare("
            SELECT c.*, d.nombre as docente_nombre, d.color_hex as docente_color,
                   g.nombre as grupo_nombre, m.nombre as materia_nombre, a.nombre as aula_nombre
            FROM hor_clases c
            LEFT JOIN hor_docentes d ON d.id = c.docente_id
            LEFT JOIN hor_grupos g   ON g.id = c.grupo_id
            LEFT JOIN hor_materias m ON m.id = c.materia_id
            LEFT JOIN hor_aulas a    ON a.id = c.aula_id
            WHERE c.id=? AND c.carrera_id=?
        ");
        $stmt->execute([$id, $carrera_id]);
        return $stmt->fetch() ?: [];
    }

    private static function checkConflict(PDO $pdo, int $carrera_id, array $data, ?string $excludeId = null, ?string $periodo = null): ?string {
        $hora_fin = (int)$data['hora_inicio'] + (int)($data['duracion'] ?? 1);
        $diaInt   = self::parseDia($data['dia']);
        
        $periodoPart = "";
        $periodoParam = [];
        if ($periodo) {
            // Intentar detectar si la columna periodo existe para usarla en el filtro de conflictos
            try {
                $pdo->query("SELECT periodo FROM hor_clases LIMIT 1");
                $periodoPart = " AND periodo = ?";
                $periodoParam = [$periodo];
            } catch (\Exception $e) { $periodoPart = ""; }
        }

        // Conflicto grupo
        if (!empty($data['grupo_id'])) {
            $sql = "SELECT COUNT(*) FROM hor_clases 
                    WHERE carrera_id=? AND dia=? AND grupo_id=?
                    AND hora_inicio < ? AND (hora_inicio + duracion) > ?" . $periodoPart;
            $excludePart = $excludeId ? " AND id != ?" : "";
            $p = array_merge([$carrera_id, $diaInt, $data['grupo_id'], $hora_fin, (int)$data['hora_inicio']], $periodoParam);
            if ($excludeId) $p[] = $excludeId;
            $r = $pdo->prepare($sql . $excludePart);
            $r->execute($p);
            if ((int)$r->fetchColumn() > 0) return "El grupo ya tiene clase en ese horario";
        }

        // Conflicto docente
        if (!empty($data['docente_id'])) {
            $sql = "SELECT COUNT(*) FROM hor_clases 
                    WHERE carrera_id=? AND dia=? AND docente_id=?
                    AND hora_inicio < ? AND (hora_inicio + duracion) > ?" . $periodoPart;
            $excludePart = $excludeId ? " AND id != ?" : "";
            $p = array_merge([$carrera_id, $diaInt, $data['docente_id'], $hora_fin, (int)$data['hora_inicio']], $periodoParam);
            if ($excludeId) $p[] = $excludeId;
            $r = $pdo->prepare($sql . $excludePart);
            $r->execute($p);
            if ((int)$r->fetchColumn() > 0) return "El docente ya tiene clase en ese horario";
        }

        // Conflicto aula
        if (!empty($data['aula_id'])) {
            $sql = "SELECT COUNT(*) FROM hor_clases 
                    WHERE carrera_id=? AND dia=? AND aula_id=?
                    AND hora_inicio < ? AND (hora_inicio + duracion) > ?" . $periodoPart;
            $excludePart = $excludeId ? " AND id != ?" : "";
            $p = array_merge([$carrera_id, $diaInt, $data['aula_id'], $hora_fin, (int)$data['hora_inicio']], $periodoParam);
            if ($excludeId) $p[] = $excludeId;
            $r = $pdo->prepare($sql . $excludePart);
            $r->execute($p);
            if ((int)$r->fetchColumn() > 0) return "El aula ya está ocupada en ese horario";
        }

        return null;
    }

    // ─── MATERIAS PREFERIDAS DE DOCENTE ───────────────────────────────────────

    public static function getDocenteMaterias(PDO $pdo, string $docente_id): array {
        $stmt = $pdo->prepare("
            SELECT dm.materia_id, m.nombre, m.cuatrimestre, m.horas_semanales
            FROM hor_docente_materias dm
            JOIN hor_materias m ON m.id = dm.materia_id
            WHERE dm.docente_id = ?
            ORDER BY m.cuatrimestre, m.nombre
        ");
        $stmt->execute([$docente_id]);
        return $stmt->fetchAll();
    }

    public static function getDocenteMateriasByCarrera(PDO $pdo, int $carrera_id): array {
        $stmt = $pdo->prepare("
            SELECT dm.docente_id, dm.materia_id
            FROM hor_docente_materias dm
            JOIN hor_docentes d ON d.id = dm.docente_id
            WHERE d.carrera_id = ?
        ");
        $stmt->execute([$carrera_id]);
        $rows = $stmt->fetchAll();
        
        $map = [];
        foreach ($rows as $r) {
            $map[$r['materia_id']][] = $r['docente_id'];
        }
        return $map;
    }

    public static function setDocenteMaterias(PDO $pdo, string $docente_id, array $materia_ids): void {
        // Reemplazar preferencias (delete + insert)
        $pdo->prepare("DELETE FROM hor_docente_materias WHERE docente_id = ?")->execute([$docente_id]);
        if (empty($materia_ids)) return;
        $stmt = $pdo->prepare("INSERT INTO hor_docente_materias (docente_id, materia_id) VALUES (?,?) ON CONFLICT DO NOTHING");
        foreach ($materia_ids as $mid) {
            if (!empty($mid)) $stmt->execute([$docente_id, $mid]);
        }
    }

    // ─── CONFIGURACIÓN ────────────────────────────────────────────────────────

    public static function getConfig(PDO $pdo, int $carrera_id): array {
        $stmt = $pdo->prepare("SELECT * FROM hor_configuracion WHERE carrera_id=?");
        $stmt->execute([$carrera_id]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->prepare("INSERT INTO hor_configuracion (carrera_id) VALUES (?) ON CONFLICT DO NOTHING")
                ->execute([$carrera_id]);
            return ['turno_corte'=>4, 'anio_activo'=>date('Y'), 'cuatrimestre_activo'=>1, 'fecha_inicio'=>null, 'fecha_fin'=>null, 'max_cuatrimestres' => 10];
        }
        if (!isset($row['max_cuatrimestres'])) $row['max_cuatrimestres'] = 10;
        return $row;
    }

    public static function updateConfig(PDO $pdo, int $carrera_id, array $data): array {
        $pdo->prepare("
            INSERT INTO hor_configuracion (carrera_id, turno_corte, anio_activo, cuatrimestre_activo, fecha_inicio, fecha_fin, max_cuatrimestres)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (carrera_id)
            DO UPDATE SET turno_corte=EXCLUDED.turno_corte, anio_activo=EXCLUDED.anio_activo,
                          cuatrimestre_activo=EXCLUDED.cuatrimestre_activo,
                          fecha_inicio=EXCLUDED.fecha_inicio, fecha_fin=EXCLUDED.fecha_fin,
                          max_cuatrimestres=EXCLUDED.max_cuatrimestres,
                          actualizado_en=NOW()
        ")->execute([
            $carrera_id,
            (int)($data['turno_corte'] ?? 4),
            (int)($data['anio_activo'] ?? date('Y')),
            (int)($data['cuatrimestre_activo'] ?? 1),
            !empty($data['fecha_inicio']) ? $data['fecha_inicio'] : null,
            !empty($data['fecha_fin'])    ? $data['fecha_fin']    : null,
            (int)($data['max_cuatrimestres'] ?? 10)
        ]);
        
        $cfg = self::getConfig($pdo, $carrera_id);
        
        // RECALCULAR HORAS SEMANALES de materias que tengan horas_totales
        if (!empty($cfg['fecha_inicio']) && !empty($cfg['fecha_fin'])) {
            $start = strtotime($cfg['fecha_inicio']);
            $end = strtotime($cfg['fecha_fin']);
            if ($start && $end && $end > $start) {
                $dias = round(($end - $start) / 86400);
                $semanas = max(1, round($dias / 7));
                $pdo->prepare("
                    UPDATE hor_materias 
                    SET horas_semanales = CEIL(horas_totales::numeric / ?)
                    WHERE carrera_id = ? AND horas_totales IS NOT NULL AND horas_totales > 0
                ")->execute([$semanas, $carrera_id]);
            }
        }
        
        return $cfg;
    }

    public static function promoteGroups(PDO $pdo, int $carrera_id): array {
        $cfg = self::getConfig($pdo, $carrera_id);
        $anio = (int)$cfg['anio_activo'];
        $cuatri = (int)$cfg['cuatrimestre_activo'];
        
        // Calcular siguiente periodo
        $nextCuatri = $cuatri + 1;
        $nextAnio = $anio;
        if ($nextCuatri > 3) {
            $nextCuatri = 1;
            $nextAnio++;
        }
        
        $pdo->beginTransaction();
        try {
            // 1. Promocionar grupos activos
            $pdo->prepare("
                UPDATE hor_grupos 
                SET cuatrimestre = cuatrimestre + 1 
                WHERE carrera_id = ? AND cuatrimestre < ?
            ")->execute([$carrera_id, (int)($cfg['max_cuatrimestres'] ?? 10)]);

            // 2. Actualizar configuración global
            $pdo->prepare("
                UPDATE hor_configuracion 
                SET anio_activo = ?, cuatrimestre_activo = ?, actualizado_en = NOW()
                WHERE carrera_id = ?
            ")->execute([$nextAnio, $nextCuatri, $carrera_id]);

            $pdo->commit();
            
            return [
                'success' => true,
                'nuevo_periodo' => $nextAnio . '-' . $nextCuatri,
                'anio' => $nextAnio,
                'cuatri' => $nextCuatri
            ];
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
