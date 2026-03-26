<?php
/**
 * index.php — Controlador Frontal del Panel de Control
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
session_start();

// Middleware de autenticación independiente
if (empty($_SESSION['panel_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Acción de logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$vista = $_GET['v'] ?? 'dashboard';
$vista_saneada = basename($vista);
$archivo_vista = "app/views/pages/{$vista_saneada}.php";

try {
    require_once 'app/views/layout/header.php';

    if (file_exists($archivo_vista)) {
        require_once $archivo_vista;
    } else {
        echo "<div class='p-10 text-center'>";
        echo "<h1 class='text-2xl font-bold text-red-500'>404 — Vista Administrativa no encontrada</h1>";
        echo "<p class='text-gray-500 mt-2'>La sección <code>{$vista_saneada}</code> no existe en el Panel de Control.</p>";
        echo "</div>";
    }

    require_once 'app/views/layout/footer.php';
} catch (Throwable $e) {
    // Si falla el header, al menos mostramos el error con estilos básicos
    ?>
    <div style="font-family: sans-serif; padding: 40px; background: #fff1f2; color: #991b1b; border: 2px solid #fecaca; margin: 20px; border-radius: 20px;">
        <h1 style="margin-top:0">UPSRJ — Error Crítico del Sistema</h1>
        <p>Se ha detectado un error fatal al intentar cargar el panel:</p>
        <div style="background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #fecaca; font-family: monospace; font-size: 13px;">
            <b>Mensaje:</b> <?php echo htmlspecialchars($e->getMessage()); ?><br>
            <b>Archivo:</b> <?php echo htmlspecialchars($e->getFile()); ?><br>
            <b>Línea:</b> <?php echo $e->getLine(); ?>
        </div>
        <p style="margin-bottom:0"><a href="login.php?logout=1" style="color: #ef4444; font-weight: bold;">Cerrar sesión e intentar de nuevo</a></p>
    </div>
    <?php
}
?>
