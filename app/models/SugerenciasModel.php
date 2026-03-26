<?php
/**
 * SugerenciasModel.php
 * Modelo para las peticiones a la tabla de sugerencias.
 */

class SugerenciasModel {
    /**
     * Devuelve todas las sugerencias de la carrera, ordenadas de más recientes a antiguas.
     */
    public static function getAll(PDO $pdo): array {
        try {
            $stmt = $pdo->query("SELECT * FROM sugerencias ORDER BY fecha_creacion DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en SugerenciasModel::getAll: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crea una nueva sugerencia.
     */
    public static function create(PDO $pdo, string $titulo, string $desc, string $prio, string $cat, string $autor): bool {
        try {
            $sql = "INSERT INTO sugerencias (titulo, descripcion, prioridad, categoria, enviado_por) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$titulo, $desc, $prio, $cat, $autor]);
        } catch (PDOException $e) {
            error_log("Error en SugerenciasModel::create: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina una sugerencia (solo útil si el admin quiere limpiar, pero por ahora se puede agregar).
     */
    public static function delete(PDO $pdo, int $id): bool {
        try {
            $stmt = $pdo->prepare("DELETE FROM sugerencias WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
