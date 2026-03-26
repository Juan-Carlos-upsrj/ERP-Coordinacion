<?php
/**
 * index.php — Controlador Frontal de Coordinación Académica
 * Punto de entrada único para la gestión de carreras.
 */
session_start();
require_once 'config.php';

// ─── 0. AISLAMIENTO DE CARPETAS ESTÁTICAS ────────────────────────────────────
// Si la URL apunta a la carpeta de descarga, dejamos que Apache/PHP maneje el index.php de allí.
if (strpos($_SERVER['REQUEST_URI'], '/descarga/') !== false) {
    if (file_exists(__DIR__ . '/descarga/index.php')) {
        return false; // Permite que el servidor sirva el archivo estático/directorio
    }
}

// ─── 1. LOGOUT ────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ─── 2. CAMBIO DE CARRERA ─────────────────────────────────────────────────────
if (isset($_GET['cambiar_carrera']) && !empty($_SESSION['logged_in'])) {
    $nueva = $_GET['cambiar_carrera'];
    if (array_key_exists($nueva, $CARRERAS) && $CARRERAS[$nueva]['activa']) {
        if (!empty($_SESSION['carreras_autorizadas'][$nueva])) {
            $_SESSION['carrera_activa'] = $nueva;
            $_SESSION['usuario_nombre'] = $_SESSION['carreras_autorizadas'][$nueva]['nombre'];
            $_SESSION['usuario_rol']    = $_SESSION['carreras_autorizadas'][$nueva]['rol'];
        } else {
            // Requiere autenticación fresca para esa carrera
            session_destroy();
            session_start();
            header("Location: index.php?preselect={$nueva}");
            exit;
        }
    }
    header('Location: index.php?v=dashboard');
    exit;
}

// ─── 3. LOGIN POR CARRERA ─────────────────────────────────────────────────────
if (isset($_POST['password'])) {
    $pass_ingresada = $_POST['password'];
    $carrera_sel    = $_POST['carrera'] ?? 'IAEV';
    
    if (isset($CARRERAS[$carrera_sel]) && $CARRERAS[$carrera_sel]['activa']) {
        try {
            $pdo_auth = getConnection(DB_NAME);
            $stmt = $pdo_auth->prepare("SELECT id, nombre, rol, carrera_sigla FROM coordinadores WHERE carrera_sigla = ? AND password_hash = ?");
            $stmt->execute([$carrera_sel, $pass_ingresada]);
            $usuario_encontrado = $stmt->fetch();

            if ($usuario_encontrado) {
                $_SESSION['logged_in']      = true;
                $_SESSION['usuario_id']     = $usuario_encontrado['id'];
                $_SESSION['carrera_activa'] = $carrera_sel;
                $_SESSION['usuario_nombre'] = $usuario_encontrado['nombre'];
                $_SESSION['usuario_rol']    = $usuario_encontrado['rol'];
                $_SESSION['carreras_autorizadas'][$carrera_sel] = $usuario_encontrado;
                header('Location: index.php?v=dashboard');
                exit;
            } else {
                $login_error = "Contraseña incorrecta para " . htmlspecialchars($carrera_sel) . ".";
            }
        } catch (Exception $e) {
            $login_error = "Error sistema: " . $e->getMessage();
        }
    }
}

// ─── 4. ACCIONES DE COORDINACIÓN (POST) ───────────────────────────────────────
if (isset($_POST['action']) && !empty($_SESSION['logged_in'])) {
    try {
        require_once 'app/models/AlumnosModel.php';
        $carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
        $carrera_info  = $CARRERAS[$carrera_sigla];
        
        switch ($_POST['action']) {
            case 'registrar_baja':
                $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
                $exito = AlumnosModel::registrarBaja($pdo, trim($_POST['alumno_nombre']??''), trim($_POST['alumno_id']??''), trim($_POST['motivo']??'Sin especificar'), $carrera_info['carrera_id']);
                if ($exito) {
                    header('Location: index.php?v=bajas&baja=ok');
                } else {
                    header('Location: index.php?v=bajas&baja=error');
                }
                exit;
            case 'procesar_justificacion':
                $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
                $ids = $_POST['ids'] ?? []; if (!is_array($ids)) $ids = [$ids];
                $sol_id = !empty($_POST['solicitud_id']) ? $_POST['solicitud_id'] : null;
                $exito = AlumnosModel::procesarJustificacion($pdo, $ids, trim($_POST['motivo']??'Justificado por Coordinación'), $_SESSION['usuario_nombre']??'Administrador', $sol_id);
                header('Location: index.php?v=justificantes&res=' . ($exito ? 'ok' : 'error')); exit;
            case 'rechazar_solicitud':
                $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
                $sol_id = $_POST['solicitud_id'] ?? null;
                if ($sol_id) AlumnosModel::rechazarSolicitud($pdo, $sol_id);
                header('Location: index.php?v=justificantes'); exit;
            case 'consolidar_alumno':
                $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
                AlumnosModel::consolidarAlumno($pdo, trim($_POST['alumno_nombre']??''));
                header('Location: index.php?v=duplicados&unificado=ok'); exit;
            case 'consolidar_todos':
                $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
                $cantidad = AlumnosModel::consolidarTodos($pdo);
                header("Location: index.php?v=duplicados&masivo=ok&cant={$cantidad}"); exit;
            case 'normalizar_nombres':
                $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
                AlumnosModel::normalizarNombres($pdo);
                header('Location: index.php?v=duplicados&normalizado=ok'); exit;
            case 'fusionar_alumnos':
                $pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
                AlumnosModel::fusionarNombres($pdo, trim($_POST['origen']??''), trim($_POST['destino']??''));
                header("Location: index.php?v=duplicados&fusionado=ok"); exit;
            case 'cambiar_password':
                $nueva = $_POST['nueva_pass'] ?? '';
                $actual = $_POST['current_pass'] ?? '';
                $uid = $_SESSION['usuario_id'] ?? null;
                
                if (!$uid) {
                    die("Error: No se encontró ID de usuario en la sesión. Por favor, cierra sesión e inicia de nuevo.");
                }

                if (!empty($nueva)) {
                    $pdo = getConnection(DB_NAME);
                    // Verificar pass actual
                    $st = $pdo->prepare("SELECT id FROM coordinadores WHERE id = ? AND password_hash = ?");
                    $st->execute([$uid, $actual]);
                    if ($st->fetch()) {
                        $upd = $pdo->prepare("UPDATE coordinadores SET password_hash = ? WHERE id = ?");
                        $upd->execute([$nueva, $uid]);
                        header('Location: index.php?v=mi_perfil&saved=ok'); exit;
                    } else {
                        header('Location: index.php?v=mi_perfil&err=pass_incorrecta'); exit;
                    }
                }
                break;
        }
    } catch (Exception $e) {
        die("<h1>Error en Acción</h1><p>" . htmlspecialchars($e->getMessage()) . "</p><a href='index.php'>Volver</a>");
    }
}

// ─── RUTAS PUBLICAS (Profesores) ──────────────────────────────────────────────
if (isset($_GET['public']) && $_GET['public'] === 'preferencias') {
    $carrera_sigla = $_GET['c'] ?? 'IAEV';
    if (!isset($CARRERAS[$carrera_sigla])) die("Carrera inválida");
    $carrera_info = $CARRERAS[$carrera_sigla];
    require_once 'app/views/pages/preferencias_docente.php';
    exit;
}

// ─── 5. VERIFICACIÓN DE SESIÓN ──────────────────────────────────────────────
if (empty($_SESSION['logged_in'])) {
    $preselect = $_GET['preselect'] ?? '';
    require_once 'app/views/auth/login.php';
    exit;
}

// ─── 6. ENRUTADOR DE VISTAS ─────────────────────────────────────────────────
$vista = $_GET['v'] ?? 'dashboard';
$vista_saneada = basename($vista);
$archivo_vista = "app/views/pages/{$vista_saneada}.php";

// Vistas sin Layout (Exportaciones)
$vistas_raw = ['exportar_riesgo', 'exportar_reporte'];
if (in_array($vista_saneada, $vistas_raw) && file_exists($archivo_vista)) {
    require_once $archivo_vista;
    exit;
}

// Carga de Layout Principal
$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];

require_once 'app/views/layout/header.php';
if (file_exists($archivo_vista)) {
    require_once $archivo_vista;
} else {
    echo "<div class='p-10 text-center'><h1 class='text-2xl font-bold text-red-500'>404 — Vista no encontrada</h1></div>";
}
require_once 'app/views/layout/footer.php';
