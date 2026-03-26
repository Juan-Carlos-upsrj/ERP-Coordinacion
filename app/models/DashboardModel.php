<?php
/**
 * app/models/DashboardModel.php
 * Consultas para el Dashboard general. Usa `asistencia_clases` como fuente principal
 * ya que las tablas `alumnos` y `profesores` pueden estar vacías en instalaciones nuevas.
 */

class DashboardModel {

    /**
     * Resumen general de la carrera activa.
     * Obtiene contadores reales desde asistencia_clases.
     */
    public static function getResumenCarrera(PDO $pdo): array {
        $res = [
            'total_alumnos'      => 0,
            'profesores'         => 0,
            'en_riesgo'          => 0,
            'asistencia_hoy'     => null,
            'promedio_historico' => null,
            'ultima_sync'        => null,
        ];

        try {
            // Total alumnos únicos registrados
            $r = $pdo->query("SELECT COUNT(DISTINCT alumno_nombre) FROM asistencia_clases");
            $res['total_alumnos'] = (int)$r->fetchColumn();

            // Total profesores únicos
            $r = $pdo->query("SELECT COUNT(DISTINCT profesor_nombre) FROM asistencia_clases WHERE profesor_nombre IS NOT NULL AND profesor_nombre != ''");
            $res['profesores'] = (int)$r->fetchColumn();

            // Última sync
            $r = $pdo->query("SELECT MAX(fecha_subida) FROM asistencia_clases");
            $res['ultima_sync'] = $r->fetchColumn();

            // Asistencia de hoy
            $r = $pdo->query("SELECT 
                ROUND(
                    (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                    / NULLIF(COUNT(*), 0)) * 100, 1
                ) 
                FROM asistencia_clases WHERE fecha = CURRENT_DATE");
            $hoy = $r->fetchColumn();
            $res['asistencia_hoy'] = $hoy !== false ? (float)$hoy : null;

            // Promedio últimos 30 días
            $r = $pdo->query("SELECT 
                ROUND(
                    (1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END)
                    / NULLIF(COUNT(*), 0)) * 100, 1
                ) 
                FROM asistencia_clases WHERE fecha >= CURRENT_DATE - INTERVAL '30 days'");
            $res['promedio_historico'] = (float)($r->fetchColumn() ?? 0);

            // Alumnos en riesgo (≥3 faltas, no dados de baja)
            // La tabla alumnos_bajas usa RLS — ya está activo en la conexión
            $r = $pdo->query("SELECT COUNT(DISTINCT alumno_nombre) FROM asistencia_clases 
                WHERE status = 'Ausente'
                AND alumno_nombre NOT IN (
                    SELECT COALESCE(alumno_nombre, '') FROM alumnos_bajas WHERE alumno_nombre IS NOT NULL
                )
                GROUP BY alumno_nombre
                HAVING COUNT(*) >= 3");
            $res['en_riesgo'] = count($r->fetchAll());

        } catch (PDOException $e) {
            // Retorna defaults on error
            error_log("DashboardModel error: " . $e->getMessage());
        }

        return $res;
    }

    public static function getResumenMensual(PDO $pdo, string $inicio, string $fin): array {
        $sql = "SELECT 
                    TO_CHAR(fecha, 'YYYY-MM') as mes_key,
                    TO_CHAR(fecha, 'Mon') as mes_abbr,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente', 'Retardo', 'Justificado', 'Intercambio') THEN 1 ELSE 0 END)
                        / NULLIF(COUNT(*), 0)) * 100, 1
                    ) as po_asistencia
                FROM asistencia_clases
                WHERE fecha BETWEEN ? AND ?
                GROUP BY mes_key, mes_abbr
                ORDER BY mes_key ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        return $stmt->fetchAll();
    }

    public static function getResumenSemanal(PDO $pdo, int $limite = 4): array {
        $sql = "SELECT 
                    TO_CHAR(fecha, 'IYYY-IW') as semana_key,
                    'Sem ' || TO_CHAR(fecha, 'IW') as semana_label,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente', 'Retardo', 'Justificado', 'Intercambio') THEN 1 ELSE 0 END)
                        / NULLIF(COUNT(*), 0)) * 100, 1
                    ) as po_asistencia,
                    MIN(fecha) as fecha_referencia
                FROM asistencia_clases
                WHERE fecha >= CURRENT_DATE - INTERVAL '60 days'
                GROUP BY semana_key, semana_label
                ORDER BY semana_key DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limite]);
        $rows = $stmt->fetchAll();
        return array_reverse($rows); // Retornar en orden cronológico
    }

    public static function getResumenGrupos(PDO $pdo, string $inicio, string $fin): array {
        $sql = "SELECT 
                    grupo_nombre,
                    ROUND(
                        (1.0 * SUM(CASE WHEN status IN ('Presente', 'Retardo', 'Justificado', 'Intercambio') THEN 1 ELSE 0 END)
                        / NULLIF(COUNT(*), 0)) * 100, 1
                    ) as po_asistencia
                FROM asistencia_clases
                WHERE fecha BETWEEN ? AND ?
                GROUP BY grupo_nombre
                ORDER BY 
                    CASE WHEN grupo_nombre LIKE 'PA%' THEN 0 ELSE 1 END,
                    grupo_nombre ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        return $stmt->fetchAll();
    }

    public static function getTopAusentismo(PDO $pdo, string $inicio, string $fin, int $limit = 5): array {
        $sql = "SELECT 
                    alumno_nombre, 
                    COUNT(*) as total_ausencias_clase,
                    (SELECT grupo_nombre FROM asistencia_clases ac2 
                     WHERE ac2.alumno_nombre = ac1.alumno_nombre 
                     GROUP BY grupo_nombre ORDER BY COUNT(*) DESC LIMIT 1) as grupo_mas_comun
                FROM asistencia_clases ac1
                WHERE fecha BETWEEN ? AND ?
                AND status = 'Ausente'
                AND alumno_nombre NOT IN (
                    SELECT COALESCE(alumno_nombre, '') FROM alumnos_bajas WHERE alumno_nombre IS NOT NULL
                )
                GROUP BY alumno_nombre
                ORDER BY total_ausencias_clase DESC 
                LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        return $stmt->fetchAll();
    }

    public static function getProfesoresActivos(PDO $pdo): array {
        $sql = "SELECT profesor_nombre, MAX(fecha) as ultima_fecha,
                    COUNT(DISTINCT fecha) as dias_activo
                FROM asistencia_clases
                WHERE fecha >= CURRENT_DATE - INTERVAL '7 days'
                AND profesor_nombre IS NOT NULL AND profesor_nombre != ''
                GROUP BY profesor_nombre
                ORDER BY ultima_fecha DESC
                LIMIT 8";
        return $pdo->query($sql)->fetchAll();
    }

    public static function getMapaCalor(PDO $pdo, string $mes_yyyy_mm): array {
        $sql = "SELECT 
                    fecha, 
                    COUNT(*) as total_clases, 
                    SUM(CASE WHEN status IN ('Presente', 'Retardo', 'Justificado', 'Intercambio') THEN 1 ELSE 0 END) as asistencias 
                FROM asistencia_clases 
                WHERE TO_CHAR(fecha, 'YYYY-MM') = ? 
                GROUP BY fecha";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mes_yyyy_mm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
