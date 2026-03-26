<?php
/**
 * app/models/LogsModel.php
 * Consultas de auditoría y actividad del sistema.
 * Usa asistencia_clases como fuente de actividad ya que es la tabla principal de sync.
 */

class LogsModel {

    /**
     * Actividad reciente de profesores (sincronizaciones de listas).
     */
    public static function getActividadProfesores(PDO $pdo, int $dias = 30, int $limit = 100): array {
        $sql = "SELECT
                    profesor_nombre,
                    grupo_nombre,
                    fecha,
                    COUNT(DISTINCT alumno_nombre)  AS alumnos_en_lista,
                    COUNT(*)                        AS total_registros,
                    MAX(fecha_subida)               AS ultima_sync,
                    MIN(fecha_subida)               AS primera_sync,
                    COUNT(DISTINCT fecha_subida)    AS veces_sincronizado
                FROM asistencia_clases
                WHERE fecha_subida >= NOW() - INTERVAL '{$dias} days'
                  AND profesor_nombre IS NOT NULL AND profesor_nombre != ''
                GROUP BY profesor_nombre, grupo_nombre, fecha
                ORDER BY ultima_sync DESC
                LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Historial completo de actividad por profesor específico.
     */
    public static function getActividadProfesor(PDO $pdo, string $profesor_nombre): array {
        $sql = "SELECT
                    fecha,
                    grupo_nombre,
                    COUNT(DISTINCT alumno_nombre) AS alumnos_en_lista,
                    COUNT(*)                       AS total_registros,
                    MAX(fecha_subida)              AS fecha_sync,
                    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS ausentes,
                    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS presentes
                FROM asistencia_clases
                WHERE profesor_nombre = ?
                GROUP BY fecha, grupo_nombre
                ORDER BY fecha DESC, grupo_nombre ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$profesor_nombre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Syncronizaciones con muy pocos alumnos (posibles anomalías).
     */
    public static function getSyncsSospechosas(PDO $pdo, int $min_alumnos = 3): array {
        $sql = "SELECT
                    profesor_nombre,
                    grupo_nombre,
                    fecha,
                    fecha_subida,
                    COUNT(DISTINCT alumno_nombre) AS alumnos_en_lista,
                    COUNT(*)                       AS total_registros
                FROM asistencia_clases
                WHERE profesor_nombre IS NOT NULL AND profesor_nombre != ''
                GROUP BY profesor_nombre, grupo_nombre, fecha, fecha_subida
                HAVING COUNT(DISTINCT alumno_nombre) <= :min_alumnos
                ORDER BY fecha_subida DESC
                LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':min_alumnos', $min_alumnos, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas de sincronización por día.
     */
    public static function getEstadisticasSyncDiaria(PDO $pdo, int $dias = 60): array {
        $sql = "SELECT
                    DATE(fecha_subida)     AS dia_sync,
                    COUNT(*)               AS total_registros,
                    COUNT(DISTINCT profesor_nombre) AS profesores_activos,
                    COUNT(DISTINCT grupo_nombre)    AS grupos_sync
                FROM asistencia_clases
                WHERE fecha_subida >= NOW() - INTERVAL '{$dias} days'
                  AND fecha_subida IS NOT NULL
                GROUP BY dia_sync
                ORDER BY dia_sync DESC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener las últimas sincronizaciones para el sistema de notificaciones.
     */
    public static function getNotificacionesSync(PDO $pdo, int $limit = 8): array {
        $sql = "SELECT 
                    profesor_nombre, 
                    grupo_nombre, 
                    MAX(fecha_subida) as fecha_sync,
                    COUNT(DISTINCT alumno_nombre) as total_alumnos
                FROM asistencia_clases
                WHERE fecha_subida IS NOT NULL
                GROUP BY profesor_nombre, grupo_nombre
                ORDER BY fecha_sync DESC
                LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
