<?php
/**
 * app/models/AlumnosModel.php
 * Consultas enfocadas en alumnos: riesgo, bajas, perfiles.
 */

class AlumnosModel {

    /**
     * Alumnos con más faltas — excluyendo dados de baja (RLS activo).
     */
    public static function getAlumnosEnRiesgo(PDO $pdo, int $limite = 100): array {
        $sql = "SELECT 
                    alumno_nombre,
                    alumno_id,
                    COUNT(*) as total_faltas,
                    MAX(fecha) as ultima_falta,
                    (SELECT grupo_nombre 
                     FROM asistencia_clases ac2 
                     WHERE ac2.alumno_nombre = ac1.alumno_nombre 
                     GROUP BY grupo_nombre ORDER BY COUNT(*) DESC LIMIT 1
                    ) as grupo_principal
                FROM asistencia_clases ac1
                WHERE status = 'Ausente' 
                  AND alumno_nombre NOT IN (
                      SELECT COALESCE(alumno_nombre, '') FROM alumnos_bajas WHERE alumno_nombre IS NOT NULL
                  )
                GROUP BY alumno_nombre, alumno_id
                HAVING COUNT(*) >= 3
                ORDER BY total_faltas DESC
                LIMIT :limite";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista de alumnos dados de baja.
     */
    public static function getAlumnosDadosDeBaja(PDO $pdo): array {
        try {
            $sql = "SELECT alumno_nombre, alumno_id, fecha_baja, motivo 
                    FROM alumnos_bajas 
                    ORDER BY fecha_baja DESC";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Registrar a un alumno como dado de baja.
     */
    public static function registrarBaja(PDO $pdo, string $alumno_nombre, string $alumno_id, string $motivo, int $carrera_id): bool {
        try {
            // Verificamos si ya existe el registro (evita errores de ON CONFLICT si falta el indice unico)
            $stmt = $pdo->prepare("SELECT id FROM alumnos_bajas WHERE alumno_nombre = ? LIMIT 1");
            $stmt->execute([$alumno_nombre]);
            $baja_id = $stmt->fetchColumn();

            if ($baja_id) {
                $sql = "UPDATE alumnos_bajas SET fecha_baja = NOW(), motivo = ?, alumno_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$motivo, $alumno_id, $baja_id]);
            } else {
                $sql = "INSERT INTO alumnos_bajas (alumno_id, alumno_nombre, motivo, carrera_id, fecha_baja) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$alumno_id, $alumno_nombre, $motivo, $carrera_id]);
            }
            return true;
        } catch (PDOException $e) {
            file_put_contents('debug_error.log', date('Y-m-d H:i:s') . " - Error registrar baja: " . $e->getMessage() . "\n", FILE_APPEND);
            error_log("Error registrar baja: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Historial de faltas de un alumno específico.
     */
    public static function getHistorialFaltas(PDO $pdo, string $alumno_nombre): array {
        $sql = "SELECT id, fecha, materia_nombre, profesor_nombre, status
                FROM asistencia_clases
                WHERE alumno_nombre = ? AND status = 'Ausente'
                ORDER BY fecha DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$alumno_nombre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todas las asistencias de un alumno en una fecha específica.
     */
    public static function getAsistenciasAlumnoFecha(PDO $pdo, string $alumno_nombre, string $fecha): array {
        $sql = "SELECT id, materia_nombre, profesor_nombre, status, fecha_subida
                FROM asistencia_clases
                WHERE alumno_nombre = ? AND fecha = ?
                ORDER BY fecha_subida ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$alumno_nombre, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Procesar la justificación de una o varias faltas.
     */
    public static function procesarJustificacion(PDO $pdo, array $ids, string $motivo, string $usuario, ?string $solicitud_id = null): bool {
        if (empty($ids)) return false;
        try {
            $pdo->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // 1. Actualizar status en asistencia_clases
            $sql = "UPDATE asistencia_clases SET status = 'Justificado' WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);

            // 2. Registrar en tabla de auditoría (hor_justificaciones)
            $sql_audit = "INSERT INTO hor_justificaciones (asistencia_id, motivo, usuario_nombre) VALUES (?, ?, ?)";
            $stmt_audit = $pdo->prepare($sql_audit);
            foreach ($ids as $id) {
                $stmt_audit->execute([$id, $motivo, $usuario]);
            }

            // 3. Si viene de una solicitud del portal, la marcamos como autorizada
            if ($solicitud_id) {
                $stmt_sol = $pdo->prepare("UPDATE hor_solicitudes_justificantes SET estado = 'autorizado' WHERE id = ?");
                $stmt_sol->execute([$solicitud_id]);
            }

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            file_put_contents('debug_error.log', date('Y-m-d H:i:s') . " - Error justificación: " . $e->getMessage() . "\n", FILE_APPEND);
            error_log("Error procesando justificación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todas las solicitudes de justificantes enviadas por los alumnos.
     */
    public static function getPendingSolicitudes(PDO $pdo): array {
        try {
            // Intentamos buscar la tabla, si no existe (no ha migrado) devolvemos array vacío para evitar crash
            $sql = "SELECT id, alumno_nombre, matricula, fecha_ausencia, motivo, archivo_url, creado_en 
                    FROM hor_solicitudes_justificantes 
                    WHERE estado = 'pendiente' 
                    ORDER BY creado_en DESC";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return []; // La tabla aún no existe o no hay registros
        }
    }

    /**
     * Rechazar una solicitud de justificante.
     */
    public static function rechazarSolicitud(PDO $pdo, string $solicitud_id): bool {
        try {
            $stmt = $pdo->prepare("UPDATE hor_solicitudes_justificantes SET estado = 'rechazado' WHERE id = ?");
            return $stmt->execute([$solicitud_id]);
        } catch (PDOException $e) {
            error_log("Error al rechazar solicitud: " . $e->getMessage());
            return false;
        }
    }

    /**
     * UNIFICACIÓN DE IDENTIDADES:
     * Toma todos los registros de un alumno y los pone bajo el mismo alumno_id.
     */
    public static function consolidarAlumno(PDO $pdo, string $alumno_nombre): bool {
        try {
            // 1. Encontrar el alumno_id más frecuente para este nombre (el que probablemente sea el correcto)
            $stmt = $pdo->prepare("SELECT alumno_id FROM asistencia_clases WHERE alumno_nombre = ? GROUP BY alumno_id ORDER BY COUNT(*) DESC LIMIT 1");
            $stmt->execute([$alumno_nombre]);
            $primary_id = $stmt->fetchColumn();

            if (!$primary_id) return false;

            // 2. Actualizar todos los registros de este nombre con ese ID primario
            $stmt = $pdo->prepare("UPDATE asistencia_clases SET alumno_id = ? WHERE alumno_nombre = ?");
            $stmt->execute([$primary_id, $alumno_nombre]);

            return true;
        } catch (PDOException $e) {
            error_log("Error consolidando alumno: " . $e->getMessage());
            return false;
        }
    }

    /**
     * DETALLE PROFUNDO DE DOPPLEGANGERS:
     * Regresa el desglose de cada ID detectado para un nombre.
     */
    public static function getDetalleDopplegangers(PDO $pdo, string $alumno_nombre): array {
        $sql = "SELECT 
                    alumno_id,
                    COUNT(*) as total_registros,
                    STRING_AGG(DISTINCT profesor_nombre, ', ') as profesores,
                    STRING_AGG(DISTINCT grupo_nombre, ', ') as grupos,
                    MIN(fecha) as primera_vez,
                    MAX(fecha) as ultima_vez
                FROM asistencia_clases
                WHERE alumno_nombre = ?
                GROUP BY alumno_id
                ORDER BY total_registros DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$alumno_nombre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SANEAMIENTO MASIVO:
     * Unifica a TODOS los alumnos que tienen más de un ID.
     */
    public static function consolidarTodos(PDO $pdo): int {
        try {
            // 1. Encontrar todos los nombres que tienen duplicados
            $sql_find = "SELECT alumno_nombre FROM asistencia_clases GROUP BY alumno_nombre HAVING COUNT(DISTINCT alumno_id) > 1";
            $nombres = $pdo->query($sql_find)->fetchAll(PDO::FETCH_COLUMN);
            
            $count = 0;
            foreach ($nombres as $nombre) {
                if (self::consolidarAlumno($pdo, $nombre)) {
                    $count++;
                }
            }
            return $count;
        } catch (PDOException $e) {
            error_log("Error en consolidación masiva: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * NORMALIZACIÓN DE NOMBRES:
     * Convierte todos los nombres a MAYÚSCULAS para evitar duplicados por capitalización.
     */
    public static function normalizarNombres(PDO $pdo): bool {
        try {
            // Normalización agresiva: UPPER + TRIM + Eliminación de Acentos comunes en SQL para velocidad.
            $sql = "UPDATE asistencia_clases SET alumno_nombre = 
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        UPPER(TRIM(alumno_nombre)), 
                    'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N')
                    WHERE alumno_nombre IS NOT NULL";
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Error en normalización de nombres: " . $e->getMessage());
            return false;
        }
    }

    /**
     * DETECCIÓN DE SIMILITUDES (FUZZY MATCHING):
     * Encuentra pares de nombres que son altamente similares y están en el mismo grupo.
     */
    public static function getSugerenciasFusion(PDO $pdo, int $carrera_id): array {
        try {
            // Verificamos si la extensión pg_trgm existe
            $ext = $pdo->query("SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm'")->fetch();
            if (!$ext) {
                error_log("ADVERTENCIA: La extensión pg_trgm no está instalada.");
                return [];
            }
            // Buscamos pares de alumnos con similitud alta.
            // Eliminamos la restricción de grupo para encontrar duplicados entre grupos diferentes.
            $sql = "SELECT 
                        t1.alumno_nombre as nombre1, 
                        t2.alumno_nombre as nombre2, 
                        similarity(t1.alumno_nombre, t2.alumno_nombre) as score,
                        STRING_AGG(DISTINCT t1.grupo_nombre, ', ') as grupos1,
                        STRING_AGG(DISTINCT t2.grupo_nombre, ', ') as grupos2,
                        (SELECT count(*) FROM asistencia_clases ac1 WHERE ac1.alumno_nombre = t1.alumno_nombre) as registros1,
                        (SELECT count(*) FROM asistencia_clases ac2 WHERE ac2.alumno_nombre = t2.alumno_nombre) as registros2
                    FROM (SELECT DISTINCT alumno_nombre, grupo_nombre FROM asistencia_clases) t1
                    JOIN (SELECT DISTINCT alumno_nombre, grupo_nombre FROM asistencia_clases) t2 
                        ON t1.alumno_nombre < t2.alumno_nombre
                    LEFT JOIN alumnos_similitudes_ignoradas asi 
                        ON asi.nombre1 = t1.alumno_nombre 
                        AND asi.nombre2 = t2.alumno_nombre 
                        AND asi.carrera_id = ?
                    WHERE (t1.alumno_nombre % t2.alumno_nombre OR similarity(t1.alumno_nombre, t2.alumno_nombre) > 0.2)
                      AND asi.id IS NULL
                    GROUP BY t1.alumno_nombre, t2.alumno_nombre
                    ORDER BY score DESC LIMIT 20";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$carrera_id]);
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Post-procesamiento para añadir el 'grupo' (usamos el primero de la lista para compatibilidad UI)
            foreach ($res as &$r) {
                // Combinamos grupos si son diferentes para que el usuario vea el contexto
                $g1 = $r['grupos1'] ?? '';
                $g2 = $r['grupos2'] ?? '';
                $r['grupo'] = ($g1 === $g2) ? $g1 : "$g1 / $g2";
            }
            return $res;
        } catch (PDOException $e) {
            error_log("Error en detección de similitudes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * FUSIÓN DE NOMBRES:
     * Cambia un nombre por otro en todos los registros de la base de datos.
     */
    public static function fusionarNombres(PDO $pdo, string $origen, string $destino): bool {
        try {
            $pdo->beginTransaction();
            
            // 1. Actualizar asistencia_clases
            $stmt = $pdo->prepare("UPDATE asistencia_clases SET alumno_nombre = ? WHERE alumno_nombre = ?");
            $stmt->execute([$destino, $origen]);
            
            // 2. Consolidar IDs automáticamente para el nuevo nombre unificado
            self::consolidarAlumno($pdo, $destino);
            
            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Error fusionando nombres: " . $e->getMessage());
            return false;
        }
    }

    /**
     * IGNORAR SIMILITUD:
     * Registra que dos nombres no deben ser sugeridos para fusión.
     */
    public static function ignorarFusion(PDO $pdo, string $nombre1, string $nombre2, int $carrera_id): bool {
        try {
            // Siempre ordenamos para que search sea consistente
            $n1 = min($nombre1, $nombre2);
            $n2 = max($nombre1, $nombre2);
            
            $sql = "INSERT INTO alumnos_similitudes_ignoradas (nombre1, nombre2, carrera_id) 
                    VALUES (?, ?, ?)
                    ON CONFLICT DO NOTHING";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$n1, $n2, $carrera_id]);
        } catch (PDOException $e) {
            error_log("Error ignorando fusión: " . $e->getMessage());
            return false;
        }
    }
}
