<?php
/**
 * app/views/pages/detalle_duplicado.php
 * Diagnóstico profundo de identidades para un alumno.
 */
$alumno_nombre = $_GET['alumno'] ?? null;
if (!$alumno_nombre) {
    header('Location: index.php?v=duplicados');
    exit;
}

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

require_once 'app/models/AlumnosModel.php';
$ids = AlumnosModel::getDetalleDopplegangers($pdo, $alumno_nombre);
?>

<div class="mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="index.php?v=duplicados" class="w-10 h-10 rounded-2xl bg-white border border-gray-100 shadow-sm flex items-center justify-center text-gray-400 hover:text-indigo-600 hover:border-indigo-100 transition-all">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-50 text-amber-600 text-[10px] font-black uppercase mb-1 border border-amber-100 shadow-sm">
                <span class="material-symbols-outlined text-[14px]">psychology</span>
                Diagnóstico de Fragmentación
            </div>
            <h1 class="text-3xl font-black text-gray-800 tracking-tight leading-none"><?php echo htmlspecialchars($alumno_nombre); ?></h1>
        </div>
    </div>

    <form method="POST" onsubmit="return confirm('¿Unificar todas estas identidades ahora?')">
        <input type="hidden" name="action" value="consolidar_alumno">
        <input type="hidden" name="alumno_nombre" value="<?php echo htmlspecialchars($alumno_nombre); ?>">
        <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-indigo-100 hover:bg-indigo-700 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">auto_fix_high</span>
            Consolidar Expediente
        </button>
    </form>
</div>

<div class="grid grid-cols-1 gap-6 mb-12">
    <?php foreach ($ids as $idx => $item): ?>
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden group hover:shadow-md transition-all">
        <div class="p-6 md:p-8 flex flex-col md:flex-row gap-8">
            <!-- Sidebar del ID -->
            <div class="md:w-64 border-b md:border-b-0 md:border-r border-gray-50 pb-6 md:pb-0 md:pr-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-8 h-8 rounded-xl <?php echo $idx === 0 ? 'bg-indigo-600' : 'bg-gray-800'; ?> text-white flex items-center justify-center font-black text-xs lowercase">
                        id<?php echo $idx + 1; ?>
                    </div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Identidad Detectada</p>
                </div>
                <code class="text-[10px] font-mono text-gray-400 bg-gray-50 p-2 rounded-lg border border-gray-100 block break-all mb-4">
                    <?php echo htmlspecialchars($item['alumno_id']); ?>
                </code>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black text-gray-400 uppercase">Registros</span>
                    <span class="text-lg font-black text-gray-800"><?php echo number_format($item['total_registros']); ?></span>
                </div>
            </div>

            <!-- Contenido del Diagnóstico -->
            <div class="flex-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h4 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">person_search</span>
                            Origen (Profesores)
                        </h4>
                        <p class="text-sm font-bold text-gray-700 leading-relaxed italic">
                            Este doppelgänger viene de las listas de: <span class="text-gray-900 not-italic font-black"><?php echo htmlspecialchars($item['profesores']); ?></span>.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-[10px] font-black text-amber-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">history</span>
                            Línea de Tiempo
                        </h4>
                        <p class="text-sm font-bold text-gray-600 leading-relaxed italic">
                            Apareció por primera vez el <span class="text-gray-900 not-italic font-black"><?php echo date('d/m/Y', strtotime($item['primera_vez'])); ?></span> 
                            y su último registro fue el <span class="text-gray-900 not-italic font-black"><?php echo date('d/m/Y', strtotime($item['ultima_vez'])); ?></span>.
                        </p>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-gray-300 text-sm">groups</span>
                        <span class="text-[10px] font-black text-gray-400 uppercase">Grupos vinculados:</span>
                        <span class="text-xs font-bold text-gray-600 italic"><?php echo htmlspecialchars($item['grupos']); ?></span>
                    </div>
                    <?php if ($idx === 0): ?>
                    <span class="px-3 py-1 rounded-lg bg-indigo-50 text-indigo-600 text-[10px] font-black border border-indigo-100 uppercase tracking-tighter">Probable ID Primario</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-indigo-900 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
    <span class="material-symbols-outlined absolute -right-4 -top-4 text-white/5 text-9xl">biotech</span>
    <h3 class="text-xl font-black mb-4">¿Qué hay de diferencia?</h3>
    <p class="text-indigo-200 text-sm leading-relaxed max-w-3xl font-medium italic">
        Cada "Doppelgänger" representa una vida técnica distinta del alumno en la base de datos. Las diferencias clave radican en **quién tomó la asistencia** (profesores) y **cuándo** ocurrió. Notarás que algunos IDs se usaron solo durante una semana o por un profesor específico, mientras que otros cargan con el historial más pesado. La unificación colapsará todas estas realidades en el ID primario detectado.
    </p>
</div>
