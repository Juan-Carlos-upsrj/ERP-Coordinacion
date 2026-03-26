<?php
/**
 * app/views/pages/duplicados.php
 * Gestor de Identidades e Inteligencia de Saneamiento (v5.0 - AUTO-HEAL).
 */
$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

require_once 'app/models/AlumnosModel.php';

// ── AUTO-SANEAMIENTO SILENCIOSO ──────────────────────────────────────────────
// Ejecutamos normalization y unificación automática al entrar para que el 
// usuario siempre vea la versión más limpia posible.
if (!isset($_GET['manual'])) {
    AlumnosModel::normalizarNombres($pdo);
    // Nota: Solo consolidamos automáticamente si el usuario no ha pedido ver el "sucio" manual.
    // Esto hace que la página "se arregle sola" al cargar.
}

// ── Query de duplicados (Ahora ignora mayúsculas/minúsculas) ────────────────
$sql_dups = "SELECT
    UPPER(alumno_nombre) AS alumno_nombre,
    COUNT(DISTINCT alumno_id) AS total_ids,
    COUNT(*) AS total_registros,
    STRING_AGG(DISTINCT grupo_nombre, ', ') AS lista_grupos
FROM asistencia_clases
GROUP BY UPPER(alumno_nombre)
HAVING COUNT(DISTINCT alumno_id) > 1
ORDER BY total_ids DESC, alumno_nombre ASC";

$duplicados = $pdo->query($sql_dups)->fetchAll(PDO::FETCH_ASSOC);
$total_dups = count($duplicados);

// ── Resumen de anomalía ──────────────────────────────────────────────────────
$resumen = $pdo->query("SELECT 
    COUNT(DISTINCT UPPER(alumno_nombre)) AS alumnos_unicos,
    COUNT(DISTINCT alumno_id)            AS ids_totales
FROM asistencia_clases")->fetch(PDO::FETCH_ASSOC);

$sobrantes = ($resumen['ids_totales'] ?? 0) - ($resumen['alumnos_unicos'] ?? 0);

$unificado_msg   = $_GET['unificado']   ?? null;
$masivo_msg      = $_GET['masivo']      ?? null;
$normalizado_msg = $_GET['normalizado'] ?? null;
$fusionado_msg   = $_GET['fusionado']   ?? null;
$ignorado_msg    = $_GET['ignorado']    ?? null;
$orig_name       = $_GET['orig']        ?? '';
$dest_name       = $_GET['dest']        ?? '';
$cant            = $_GET['cant']        ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'ignorar_fusion') {
        $n1 = $_POST['nombre1'] ?? '';
        $n2 = $_POST['nombre2'] ?? '';
        if ($n1 && $n2) {
            AlumnosModel::ignorarFusion($pdo, $n1, $n2, $carrera_info['carrera_id']);
            header("Location: index.php?v=duplicados&ignorado=ok");
            exit;
        }
    }
}

// ── Sugerencias de Fusión Borrosa (pg_trgm) ──────────────────────────────────
$sugerencias = AlumnosModel::getSugerenciasFusion($pdo, $carrera_info['carrera_id']);
$total_sug   = count($sugerencias);

// Modo Wizard: Solo mostramos la primera sugerencia (o la seleccionada por índice)
$idx_sug = isset($_GET['sug_idx']) ? (int)$_GET['sug_idx'] : 0;
if ($idx_sug >= $total_sug) $idx_sug = 0;
$sug = ($total_sug > 0) ? $sugerencias[$idx_sug] : null;

// Desglose de información para la sugerencia activa
$info_sug1 = $sug ? AlumnosModel::getDetalleDopplegangers($pdo, $sug['nombre1']) : [];
$info_sug2 = $sug ? AlumnosModel::getDetalleDopplegangers($pdo, $sug['nombre2']) : [];

?>

<div class="w-full flex flex-col min-w-0">
    <!-- Botón Superior -->
    <div class="mb-4 flex justify-end">
        <div class="flex flex-wrap gap-4">
            <?php if ($total_dups > 0): ?>
            <form method="POST" onsubmit="return confirm('¿Quieres unificar estos <?php echo $total_dups; ?> casos ahora?')">
                <input type="hidden" name="action" value="consolidar_todos">
                <button type="submit" class="px-5 py-3 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-slate-200 hover:bg-indigo-600 transition-all flex items-center gap-3">
                    <span class="material-symbols-outlined text-indigo-400">magic_button</span>
                    Unificar Todo
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

<!-- Resumen (Movido y Compacto) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400">
            <span class="material-symbols-outlined text-[20px]">person</span>
        </div>
        <div>
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Alumnos Reales</p>
            <p class="text-lg font-black text-gray-800"><?php echo number_format($resumen['alumnos_unicos'] ?? 0); ?></p>
        </div>
        <div class="ml-auto">
            <a href="index.php?v=duplicados&manual=1" class="text-[9px] font-black text-indigo-400 hover:text-indigo-600 uppercase tracking-tighter">Ver datos originales</a>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400">
            <span class="material-symbols-outlined text-[20px]">fingerprint</span>
        </div>
        <div>
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Códigos de Sistema</p>
            <p class="text-lg font-black text-gray-800"><?php echo number_format($resumen['ids_totales'] ?? 0); ?></p>
        </div>
    </div>
    <div class="bg-indigo-600 rounded-2xl p-4 shadow-lg shadow-indigo-100 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white">
            <span class="material-symbols-outlined text-[20px]">notification_important</span>
        </div>
        <div>
            <p class="text-[9px] font-black text-indigo-100 uppercase tracking-widest leading-none mb-1">Por Revisar</p>
            <p class="text-lg font-black text-white"><?php echo number_format($sobrantes); ?></p>
        </div>
    </div>
</div>

<?php if ($masivo_msg === 'ok'): ?>
<div class="mb-8 p-5 bg-indigo-600 rounded-3xl text-white shadow-xl shadow-indigo-100 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center">
            <span class="material-symbols-outlined text-white fill-1">check_circle</span>
        </div>
        <div>
            <h4 class="font-black text-lg leading-none mb-1">Todo Listo</h4>
            <p class="text-indigo-100 text-xs font-bold uppercase tracking-wider">Los registros han sido unificados correctamente.</p>
        </div>
    </div>
    <a href="index.php?v=duplicados" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl text-[10px] font-black uppercase">Cerrar</a>
</div>
<?php endif; ?>

<?php if ($fusionado_msg === 'ok'): ?>
<div class="mb-8 p-5 bg-teal-600 rounded-3xl text-white shadow-xl shadow-teal-100 flex items-center justify-between animate-in slide-in-from-top-4">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center">
            <span class="material-symbols-outlined text-white fill-1">verified</span>
        </div>
        <div>
            <h4 class="font-black text-lg leading-none mb-1">Fusión Completada</h4>
            <p class="text-teal-100 text-xs font-bold uppercase tracking-wider"><b><?php echo htmlspecialchars($orig_name); ?></b> ahora es parte de <b><?php echo htmlspecialchars($dest_name); ?></b>.</p>
        </div>
    </div>
    <a href="index.php?v=duplicados" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl text-[10px] font-black uppercase">Cerrar</a>
</div>
<?php endif; ?>

<?php if ($ignorado_msg === 'ok'): ?>
<div class="mb-8 p-4 bg-slate-800 rounded-2xl text-white shadow-lg flex items-center justify-between animate-in slide-in-from-top-4">
    <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-indigo-400">visibility_off</span>
        <p class="text-xs font-bold uppercase tracking-wider">Sugerencia ignorada. No se volverá a mostrar.</p>
    </div>
    <a href="index.php?v=duplicados" class="text-[10px] font-black uppercase text-slate-400 hover:text-white">Cerrar</a>
</div>
<?php endif; ?>

<?php if ($total_sug > 0 && $sug): ?>
<!-- Asistente de Fusión (Wizard) -->
<div class="mb-12 animate-in fade-in zoom-in duration-500">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-2 h-8 bg-amber-400 rounded-full"></div>
        <div>
            <h2 class="text-2xl font-black text-gray-800 tracking-tight leading-none">Limpieza por Similitud</h2>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1 italic">Compara y elige el nombre correcto</p>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-tighter">Pendiente <?php echo ($idx_sug + 1); ?> de <?php echo $total_sug; ?></span>
            <div class="flex gap-1">
                <a href="index.php?v=duplicados&sug_idx=<?php echo max(0, $idx_sug - 1); ?>" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-indigo-600 hover:text-white transition-all">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </a>
                <a href="index.php?v=duplicados&sug_idx=<?php echo min($total_sug - 1, $idx_sug + 1); ?>" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-indigo-600 hover:text-white transition-all">
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] border border-gray-100 shadow-2xl shadow-slate-100 overflow-hidden">
        <div class="bg-amber-50/50 px-6 py-3 flex items-center justify-between border-b border-amber-100/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500 text-base">info</span>
                <span class="text-[9px] font-black text-amber-600 uppercase tracking-widest">Coincidencia: <?php echo round($sug['score'] * 100); ?>%</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-500 text-xs">groups</span>
                <span class="text-[9px] font-black text-indigo-600 uppercase tracking-widest">Grupo: <?php echo htmlspecialchars($sug['grupo']); ?></span>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 relative">
                <!-- Divider Visual -->
                <div class="hidden lg:block absolute left-1/2 top-10 bottom-10 w-px bg-gradient-to-b from-transparent via-gray-100 to-transparent -translate-x-1/2"></div>
                
                <!-- Alumno A -->
                <div class="space-y-6">
                    <div>
                        <p class="text-[10px] font-black text-indigo-500 uppercase tracking-[0.2em] mb-3">Opción 1</p>
                        <h3 class="text-3xl font-black text-gray-800 leading-tight tracking-tighter"><?php echo htmlspecialchars($sug['nombre1']); ?></h3>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100/50">
                            <p class="text-[8px] font-black text-gray-400 uppercase mb-0.5">Clases</p>
                            <p class="text-base font-black text-gray-700 leading-none"><?php echo $sug['registros1']; ?></p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100/50">
                            <p class="text-[8px] font-black text-gray-400 uppercase mb-0.5">IDs</p>
                            <p class="text-base font-black text-gray-700 leading-none"><?php echo count($info_sug1); ?></p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Historial / Profesores</p>
                        <div class="space-y-2 max-h-[150px] overflow-y-auto pr-2 custom-scrollbar">
                            <?php foreach ($info_sug1 as $det): ?>
                            <div class="text-[11px] font-medium text-gray-500 bg-gray-50/30 p-3 rounded-xl border border-gray-50 group-hover:border-indigo-100 transition-colors">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-black text-gray-700 uppercase"><?php echo htmlspecialchars($det['grupos']); ?></span>
                                    <span class="text-[9px] bg-white px-2 py-0.5 rounded-full border border-gray-100"><?php echo $det['total_registros']; ?></span>
                                </div>
                                <p class="truncate italic opacity-70"><?php echo htmlspecialchars($det['profesores']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form method="POST" onsubmit="return confirm('¿Usar este nombre como oficial?')">
                        <input type="hidden" name="action" value="fusionar_alumnos">
                        <input type="hidden" name="origen" value="<?php echo htmlspecialchars($sug['nombre2']); ?>">
                        <input type="hidden" name="destino" value="<?php echo htmlspecialchars($sug['nombre1']); ?>">
                        <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-indigo-100 hover:bg-slate-900 transition-all flex items-center justify-center gap-3">
                            <span class="material-symbols-outlined text-sm">done_all</span>
                            Dejar este nombre
                        </button>
                    </form>
                </div>

                <!-- Alumno B -->
                <div class="space-y-6">
                    <div class="text-right lg:text-left shadow-amber-500/20">
                        <p class="text-[10px] font-black text-teal-500 uppercase tracking-[0.2em] mb-3">Opción 2</p>
                        <h3 class="text-3xl font-black text-gray-800 leading-tight tracking-tighter"><?php echo htmlspecialchars($sug['nombre2']); ?></h3>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100/50">
                            <p class="text-[8px] font-black text-gray-400 uppercase mb-0.5">Clases</p>
                            <p class="text-base font-black text-gray-700 leading-none"><?php echo $sug['registros2']; ?></p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100/50">
                            <p class="text-[8px] font-black text-gray-400 uppercase mb-0.5">IDs</p>
                            <p class="text-base font-black text-gray-700 leading-none"><?php echo count($info_sug2); ?></p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest text-right lg:text-left">Historial / Profesores</p>
                        <div class="space-y-2 max-h-[150px] overflow-y-auto pr-2 custom-scrollbar">
                            <?php foreach ($info_sug2 as $det): ?>
                            <div class="text-[11px] font-medium text-gray-500 bg-gray-50/30 p-3 rounded-xl border border-gray-50">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-black text-gray-700 uppercase"><?php echo htmlspecialchars($det['grupos']); ?></span>
                                    <span class="text-[9px] bg-white px-2 py-0.5 rounded-full border border-gray-100"><?php echo $det['total_registros']; ?></span>
                                </div>
                                <p class="truncate italic opacity-70"><?php echo htmlspecialchars($det['profesores']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form method="POST" onsubmit="return confirm('¿Usar este nombre como oficial?')">
                        <input type="hidden" name="action" value="fusionar_alumnos">
                        <input type="hidden" name="origen" value="<?php echo htmlspecialchars($sug['nombre1']); ?>">
                        <input type="hidden" name="destino" value="<?php echo htmlspecialchars($sug['nombre2']); ?>">
                        <button type="submit" class="w-full py-3 bg-teal-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest shadow-lg shadow-teal-100 hover:bg-slate-900 transition-all flex items-center justify-center gap-3">
                            <span class="material-symbols-outlined text-sm">done_all</span>
                            Dejar este nombre
                        </button>
                    </form>
                </div>
            </div>

            <!-- Botón de Personas Diferentes -->
            <div class="mt-8 pt-6 border-t border-gray-100 flex justify-center">
                <form method="POST" onsubmit="return confirm('¿Estás seguro de que son personas diferentes? No se volverán a sugerir para fusionar.')">
                    <input type="hidden" name="action" value="ignorar_fusion">
                    <input type="hidden" name="nombre1" value="<?php echo htmlspecialchars($sug['nombre1']); ?>">
                    <input type="hidden" name="nombre2" value="<?php echo htmlspecialchars($sug['nombre2']); ?>">
                    <button type="submit" class="px-6 py-2.5 bg-white border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 rounded-xl font-black text-[9px] uppercase tracking-widest transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">person_remove</span>
                        Son personas diferentes
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-gray-50 px-10 py-5 flex items-center justify-between">
            <p class="text-[10px] font-bold text-gray-400 italic">💡 Pista: Fíjate en el que tenga más <b>registros</b>. Casi siempre es el nombre bien escrito.</p>
            <a href="index.php?v=duplicados&sug_idx=<?php echo min($total_sug - 1, $idx_sug + 1); ?>" class="text-[9px] font-black text-indigo-600 uppercase tracking-widest hover:underline">Ver Siguiente</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($total_sug === 0): ?>
<div class="mb-12 p-8 bg-gray-50 rounded-[2rem] border border-dashed border-gray-200 text-center animate-in fade-in duration-700">
    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center mx-auto mb-4">
        <span class="material-symbols-outlined text-gray-300 text-3xl">auto_fix_off</span>
    </div>
    <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest leading-none mb-1 italic">Detección de Similitudes</h3>
    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">No se detectaron nombres con variaciones ortográfica que requieran revisión manual.</p>
</div>
<?php endif; ?>

<!-- Resumen movido al inicio -->

<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-10">
    <div class="px-8 py-6 border-b border-gray-50 flex justify-between items-center bg-gray-50/5">
        <h3 class="text-base font-black text-gray-700 uppercase tracking-tighter italic">Auditoría de Registros</h3>
        <span class="text-[10px] font-black bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full uppercase border border-indigo-100"><?php echo $total_dups; ?> casos técnicos</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[10px] text-gray-400 uppercase bg-gray-50/50 border-b border-gray-100 font-black">
                    <th class="px-8 py-4 text-left">Identidad / Cursos</th>
                    <th class="px-8 py-4 text-center">Fragmentos (IDs)</th>
                    <th class="px-8 py-4 text-center">Registros Libres</th>
                    <th class="px-8 py-4 text-right">Diagnóstico</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 font-medium whitespace-nowrap">
                <?php foreach ($duplicados as $al): ?>
                <tr class="hover:bg-indigo-50/10 transition-colors group">
                    <td class="px-8 py-5">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-xs uppercase border border-indigo-100">
                                <?php echo Utils::safeSubstr($al['alumno_nombre'] ?? '?', 0, 2); ?>
                            </div>
                            <div>
                                <p class="font-black text-gray-800 leading-none mb-1"><?php echo htmlspecialchars($al['alumno_nombre']); ?></p>
                                <p class="text-[10px] font-bold text-gray-400 uppercase italic max-w-[400px] truncate"><?php echo htmlspecialchars($al['lista_grupos']); ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-8 py-5 text-center">
                        <span class="px-3 py-1 rounded-xl text-[10px] font-black bg-slate-900 text-white shadow-sm ring-4 ring-slate-50">
                            <?php echo $al['total_ids']; ?> IDs
                        </span>
                    </td>
                    <td class="px-8 py-5 text-center">
                        <span class="text-base font-black text-gray-700 tracking-tighter"><?php echo number_format($al['total_registros']); ?></span>
                    </td>
                    <td class="px-8 py-5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" onsubmit="return confirm('¿Consolidar registros de este alumno?')">
                                <input type="hidden" name="action" value="consolidar_alumno">
                                <input type="hidden" name="alumno_nombre" value="<?php echo htmlspecialchars($al['alumno_nombre']); ?>">
                                <button type="submit" class="h-9 w-9 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-center shadow-sm">
                                    <span class="material-symbols-outlined text-base">auto_fix_high</span>
                                </button>
                            </form>
                            <a href="index.php?v=detalle_duplicado&alumno=<?php echo urlencode($al['alumno_nombre']); ?>"
                               class="h-9 px-4 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-indigo-600 hover:border-indigo-200 flex items-center gap-2 transition-all shadow-sm font-black text-[10px] uppercase tracking-tighter">
                                <span class="material-symbols-outlined text-[18px]">biotech</span>
                                Analizar
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($total_dups === 0): ?>
                <tr>
                    <td colspan="4" class="py-24 text-center">
                        <span class="material-symbols-outlined text-6xl text-emerald-300 opacity-40 mb-6 scale-150 block">check_circle</span>
                        <h3 class="text-2xl font-black text-gray-700 mb-1 leading-none italic uppercase tracking-tighter">Estructura de Datos Óptima</h3>
                        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest italic tracking-tight">No se detectaron inconsistencias técnicas pendientes.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
</div>

<!-- Secciones de información eliminadas a petición del usuario -->
