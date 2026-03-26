<?php
/**
 * app/models/ReportesModel.php
 * Consultas para reportes mensuales, finales y top remediales.
 */

class ReportesModel {

    /**
     * Asistencia desglosada por mes y por grupo.
     */
    public static function getAsistenciaMensualPorGrupo(PDO $pdo, string $inicio, string $fin): array {
        $sql = "SELECT
                    TO_CHAR(fecha, 'YYYY-MM')   AS mes_key,
                    TO_CHAR(fecha, 'Mon YYYY')  AS mes_label,
                    EXTRACT(MONTH FROM fecha)   AS mes_num,
                    grupo_nombre,
                    COUNT(DISTINCT alumno_nombre)  AS total_alumnos,
                    COUNT(DISTINCT fecha)           AS dias_con_clase,
                    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS total_presentes,
                    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS total_ausentes,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                         / NULLIF(COUNT(*), 0)) * 100, 1
                    ) AS pct_asistencia
                FROM asistencia_clases
                WHERE fecha BETWEEN ? AND ?
                GROUP BY mes_key, mes_label, mes_num, grupo_nombre
                ORDER BY mes_key ASC, grupo_nombre ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen mensual global de toda la carrera.
     */
    public static function getResumenMensualGlobal(PDO $pdo, string $inicio, string $fin): array {
        $sql = "SELECT
                    TO_CHAR(fecha, 'YYYY-MM')  AS mes_key,
                    TO_CHAR(fecha, 'Mon')      AS mes_abbr,
                    TO_CHAR(fecha, 'YYYY')     AS anio,
                    EXTRACT(MONTH FROM fecha)  AS mes_num,
                    COUNT(DISTINCT alumno_nombre)  AS total_alumnos_activos,
                    COUNT(DISTINCT grupo_nombre)   AS total_grupos,
                    COUNT(DISTINCT fecha)           AS dias_con_clase,
                    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS total_asistencias,
                    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS total_ausencias,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                         / NULLIF(COUNT(*), 0)) * 100, 1
                    ) AS pct_asistencia
                FROM asistencia_clases
                WHERE fecha BETWEEN ? AND ?
                GROUP BY mes_key, mes_abbr, anio, mes_num
                ORDER BY mes_key ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Faltas reales acumuladas por alumno en un rango dado — para top remediales.
     */
    public static function getTopAlumnosConFaltas(PDO $pdo, string $inicio, string $fin, int $limit = 30): array {
        $sql = "SELECT
                    a.alumno_nombre,
                    COUNT(*) AS total_faltas_periodo,
                    (SELECT COUNT(*) FROM asistencia_clases WHERE alumno_nombre = a.alumno_nombre AND status = 'Ausente') AS total_faltas_historico,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                         / NULLIF(COUNT(*), 0)) * 100, 1
                    ) AS pct_asistencia,
                    (SELECT ac2.grupo_nombre FROM asistencia_clases ac2
                     WHERE ac2.alumno_nombre = a.alumno_nombre
                     GROUP BY ac2.grupo_nombre ORDER BY COUNT(*) DESC LIMIT 1) AS grupo_principal,
                    MAX(a.fecha) AS ultima_clase
                FROM asistencia_clases a
                WHERE a.fecha BETWEEN ? AND ?
                  AND a.status = 'Ausente'
                  AND a.alumno_nombre NOT IN (
                      SELECT COALESCE(alumno_nombre, '') FROM alumnos_bajas WHERE alumno_nombre IS NOT NULL
                  )
                GROUP BY a.alumno_nombre
                ORDER BY total_faltas_periodo DESC
                LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asistencia por materia en un período (para reporte final).
     */
    public static function getAsistenciaPorMateria(PDO $pdo, string $inicio, string $fin): array {
        $sql = "SELECT
                    materia_nombre,
                    profesor_nombre,
                    grupo_nombre,
                    COUNT(DISTINCT fecha)       AS dias_impartida,
                    COUNT(DISTINCT alumno_nombre) AS total_alumnos,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                         / NULLIF(COUNT(*), 0)) * 100, 1
                    ) AS pct_asistencia,
                    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS total_ausencias
                FROM asistencia_clases
                WHERE fecha BETWEEN ? AND ?
                  AND materia_nombre IS NOT NULL AND materia_nombre != ''
                GROUP BY materia_nombre, profesor_nombre, grupo_nombre
                ORDER BY pct_asistencia ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resumen global del período para el reporte final.
     */
    public static function getResumenFinalPeriodo(PDO $pdo, string $inicio, string $fin): array {
        $res = [
            'total_alumnos'   => 0,
            'total_grupos'    => 0,
            'total_profesores'=> 0,
            'pct_asistencia'  => null,
            'total_ausencias' => 0,
            'dias_con_clase'  => 0,
        ];
        try {
            $sql = "SELECT
                        COUNT(DISTINCT alumno_nombre)   AS total_alumnos,
                        COUNT(DISTINCT grupo_nombre)    AS total_grupos,
                        COUNT(DISTINCT profesor_nombre) AS total_profesores,
                        COUNT(DISTINCT fecha)           AS dias_con_clase,
                        SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS total_ausencias,
                        ROUND(
                            (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                             / NULLIF(COUNT(*), 0)) * 100, 1
                        ) AS pct_asistencia
                    FROM asistencia_clases
                    WHERE fecha BETWEEN ? AND ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicio, $fin]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $res = array_merge($res, $row);
        } catch (PDOException $e) {
            error_log("ReportesModel::getResumenFinalPeriodo: " . $e->getMessage());
        }
        return $res;
    }
}
