<?php
/**
 * app/models/GruposModel.php
 * Consultas enfocadas en grupos: lista, detalle y asistencia por grupo.
 */

class GruposModel {

    /**
     * Lista de todos los grupos con sus estadísticas de asistencia.
     */
    public static function getResumenGrupos(PDO $pdo, string $inicio = '', string $fin = ''): array {
        $where = '';
        $params = [];
        if ($inicio && $fin) {
            $where = 'WHERE fecha BETWEEN ? AND ?';
            $params = [$inicio, $fin];
        }
        $sql = "SELECT
                    grupo_nombre,
                    COUNT(DISTINCT alumno_nombre) AS total_alumnos,
                    COUNT(DISTINCT profesor_nombre) AS total_profesores,
                    COUNT(DISTINCT fecha) AS dias_con_clase,
                    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS total_presentes,
                    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS total_ausentes,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                         / NULLIF(COUNT(*), 0)) * 100, 1
                    ) AS pct_asistencia,
                    MAX(fecha) AS ultima_clase
                FROM asistencia_clases
                {$where}
                GROUP BY grupo_nombre
                ORDER BY
                    CASE WHEN grupo_nombre LIKE 'PA%' THEN 0 ELSE 1 END,
                    grupo_nombre ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Alumnos de un grupo específico con sus estadísticas individuales.
     */
    public static function getAlumnosDeGrupo(PDO $pdo, string $grupo, string $inicio = '', string $fin = ''): array {
        $extraWhere = '';
        $params = [$grupo];
        if ($inicio && $fin) {
            $extraWhere = 'AND fecha BETWEEN ? AND ?';
            $params[] = $inicio;
            $params[] = $fin;
        }
        $sql = "SELECT
                    alumno_nombre,
                    COUNT(*) AS total_registros,
                    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS total_presentes,
                    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS total_faltas,
                    SUM(CASE WHEN status = 'Retardo' THEN 1 ELSE 0 END) AS total_retardos,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                         / NULLIF(COUNT(*), 0)) * 100, 1
                    ) AS pct_asistencia,
                    MAX(fecha) AS ultima_clase
                FROM asistencia_clases
                WHERE grupo_nombre = ?
                {$extraWhere}
                GROUP BY alumno_nombre
                ORDER BY pct_asistencia ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Materias impartidas en un grupo con su asistencia promedio.
     */
    public static function getMateriasDeGrupo(PDO $pdo, string $grupo): array {
        $sql = "SELECT
                    materia_nombre,
                    profesor_nombre,
                    COUNT(DISTINCT fecha) AS dias_impartida,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                         / NULLIF(COUNT(*), 0)) * 100, 1
                    ) AS pct_asistencia
                FROM asistencia_clases
                WHERE grupo_nombre = ?
                GROUP BY materia_nombre, profesor_nombre
                ORDER BY pct_asistencia ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$grupo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asistencia por día del mes para un grupo (para el mapa de calor del grupo).
     */
    public static function getAsistenciaDiariaGrupo(PDO $pdo, string $grupo, string $mes_yyyy_mm): array {
        $sql = "SELECT
                    fecha,
                    COUNT(*) AS total_clases,
                    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS asistencias
                FROM asistencia_clases
                WHERE grupo_nombre = ?
                  AND TO_CHAR(fecha, 'YYYY-MM') = ?
                GROUP BY fecha
                ORDER BY fecha ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$grupo, $mes_yyyy_mm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
