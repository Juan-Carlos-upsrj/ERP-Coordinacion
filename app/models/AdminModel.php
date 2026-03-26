<?php
/**
 * app/models/AdminModel.php
 * Modelo para gestión administrativa global (Panel de Control)
 * y sincronización entre la lista autorizada y el gestor de horarios.
 */

class AdminModel {

    /**
     * Sincroniza un docente desde la lista global hacia el gestor de horarios de su carrera.
     */
    public static function syncGlobalToCareer(PDO $pdo_global, string $email) {
        require_once __DIR__ . '/../../config.php';
        global $CARRERAS;

        // 1. Obtener datos globales
        $stmt = $pdo_global->prepare("SELECT * FROM profesores WHERE email = ?");
        $stmt->execute([$email]);
        $p = $stmt->fetch();
        if (!$p) return;

        $sigla = $p['carrera_sigla'];
        $c_info = $CARRERAS[$sigla] ?? null;
        if (!$c_info) return;

        // 2. Conectar a la base de la carrera
        $pdo_career = getConnection($c_info['db_name'], $c_info['carrera_id']);
        
        // 3. Upsert manual en hor_docentes
        $nombre_completo = trim($p['nombre'] . ' ' . $p['apellidos']);
        
        $stmt_check = $pdo_career->prepare("SELECT id FROM hor_docentes WHERE email = ?");
        $stmt_check->execute([$email]);
        $exists = $stmt_check->fetch();

        if ($exists) {
            $sql = "UPDATE hor_docentes SET nombre = ?, activo = TRUE WHERE id = ?";
            $pdo_career->prepare($sql)->execute([$nombre_completo, $exists['id']]);
        } else {
            $sql = "INSERT INTO hor_docentes (carrera_id, nombre, email, activo) VALUES (?, ?, ?, TRUE)";
            $pdo_career->prepare($sql)->execute([$c_info['carrera_id'], $nombre_completo, $email]);
        }
    }

    /**
     * Sincroniza un docente desde el gestor de horarios hacia la lista global autorizada.
     */
    public static function syncCareerToGlobal(PDO $pdo_career, string $email, string $carrera_sigla) {
        $pdo_global = getConnection(DB_NAME);

        // 1. Obtener datos de la carrera
        $stmt = $pdo_career->prepare("SELECT * FROM hor_docentes WHERE email = ?");
        $stmt->execute([$email]);
        $d = $stmt->fetch();
        if (!$d) return;

        // 2. Separar nombre y apellidos (heurística simple)
        $parts = explode(' ', trim($d['nombre']));
        $nombre = $parts[0];
        $apellidos = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        // 3. Upsert manual en tabla global profesores
        $stmt_check = $pdo_global->prepare("SELECT id FROM profesores WHERE email = ?");
        $stmt_check->execute([$email]);
        $exists = $stmt_check->fetch();

        if ($exists) {
            $sql = "UPDATE profesores SET nombre = ?, apellidos = ?, carrera_sigla = ? WHERE id = ?";
            $pdo_global->prepare($sql)->execute([$nombre, $apellidos, $carrera_sigla, $exists['id']]);
        } else {
            $sql = "INSERT INTO profesores (nombre, apellidos, email, carrera_sigla) VALUES (?, ?, ?, ?)";
            $pdo_global->prepare($sql)->execute([$nombre, $apellidos, $email, $carrera_sigla]);
        }
    }

    /**
     * Obtiene los docentes de la lista global que pertenecen a una carrera.
     */
    public static function getGlobalTeachersByCarrera(PDO $pdo_global, string $carrera_sigla): array {
        $stmt = $pdo_global->prepare("SELECT * FROM profesores WHERE carrera_sigla = ? ORDER BY nombre ASC");
        $stmt->execute([$carrera_sigla]);
        return $stmt->fetchAll();
    }
}
