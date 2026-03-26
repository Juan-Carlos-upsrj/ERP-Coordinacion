<?php
/**
 * app/models/AnomaliasModel.php
 * Requerido por NFC y Gráficos.
 */

class AnomaliasModel {

    public static function getGhostUploads(PDO $pdo, float $umbral_ratio = 0.4, int $limit = 100): array {
        $sql = "WITH promedio_grupo AS (
                    SELECT grupo_nombre,
                           AVG(alumnos_por_sync) AS promedio_alumnos
                    FROM (
                        SELECT grupo_nombre, fecha, fecha_subida,
                               COUNT(DISTINCT alumno_nombre) AS alumnos_por_sync
                        FROM asistencia_clases
                        GROUP BY grupo_nombre, fecha, fecha_subida
                    ) inner_q
                    GROUP BY grupo_nombre
                ),
                syncs AS (
                    SELECT ac.profesor_nombre, ac.grupo_nombre, ac.fecha,
                           ac.fecha_subida,
                           COUNT(DISTINCT ac.alumno_nombre) AS alumnos_en_lista,
                           COUNT(*) AS total_registros
                    FROM asistencia_clases ac
                    GROUP BY ac.profesor_nombre, ac.grupo_nombre, ac.fecha, ac.fecha_subida
                )
                SELECT s.profesor_nombre, s.grupo_nombre, s.fecha,
                       s.fecha_subida, s.alumnos_en_lista, s.total_registros,
                       pg.promedio_alumnos,
                       ROUND((s.alumnos_en_lista::numeric / NULLIF(pg.promedio_alumnos, 0)) * 100, 1) AS ratio_pct
                FROM syncs s
                JOIN promedio_grupo pg ON s.grupo_nombre = pg.grupo_nombre
                WHERE s.alumnos_en_lista < (pg.promedio_alumnos * :umbral)
                  AND pg.promedio_alumnos >= 3
                ORDER BY s.fecha_subida DESC
                LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':umbral', $umbral_ratio);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getListasUnAlumno(PDO $pdo): array {
        $sql = "SELECT
                    profesor_nombre,
                    grupo_nombre,
                    fecha,
                    fecha_subida,
                    alumno_nombre,
                    status
                FROM asistencia_clases ac1
                WHERE (
                    SELECT COUNT(DISTINCT alumno_nombre)
                    FROM asistencia_clases ac2
                    WHERE ac2.profesor_nombre = ac1.profesor_nombre
                      AND ac2.grupo_nombre    = ac1.grupo_nombre
                      AND ac2.fecha           = ac1.fecha
                      AND ac2.fecha_subida    = ac1.fecha_subida
                ) = 1
                AND profesor_nombre IS NOT NULL AND profesor_nombre != ''
                ORDER BY fecha_subida DESC
                LIMIT 200";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getResumenAnomalias(PDO $pdo): array {
        $res = [
            'ghost_uploads'       => 0,
            'sync_mismo_ts'       => 0,
            'listas_un_alumno'    => 0,
        ];
        try {
            $r = $pdo->query("SELECT COUNT(*) FROM (
                SELECT ac.profesor_nombre, ac.grupo_nombre, ac.fecha, ac.fecha_subida
                FROM asistencia_clases ac
                GROUP BY ac.profesor_nombre, ac.grupo_nombre, ac.fecha, ac.fecha_subida
                HAVING COUNT(DISTINCT ac.alumno_nombre) = 1
            ) t");
            $res['listas_un_alumno'] = (int)$r->fetchColumn();
        } catch (PDOException $e) {
            error_log("AnomaliasModel: " . $e->getMessage());
        }
        return $res;
    }
}
