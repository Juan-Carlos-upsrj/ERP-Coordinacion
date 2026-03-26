<?php
/**
 * Panel de Control — duplicados.php
 * Analizador de duplicados: alumnos con múltiples UUIDs en la BD.
 */
$carrera_sigla = $_GET['carrera'] ?? $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// ── Alumnos con múltiples IDs ─────────────────────────────────────────────────
$sql_dups = "SELECT 
    alumno_nombre,
    COUNT(DISTINCT alumno_id) AS total_ids,
    COUNT(*)                  AS total_registros,
    STRING_AGG(DISTINCT grupo_nombre, ', ' ORDER BY grupo_nombre) AS grupos,
    MIN(fecha) AS primera_fecha,
    MAX(fecha) AS ultima_fecha
FROM asistencia_clases
GROUP BY alumno_nombre
HAVING COUNT(DISTINCT alumno_id) > 1
ORDER BY total_ids DESC, alumno_nombre ASC";

$duplicados = $pdo->query($sql_dups)->fetchAll();
$total_dups = count($duplicados);

// ── Resumen global ─────────────────────────────────────────────────────────────
$resumen = $pdo->query("SELECT 
    COUNT(DISTINCT alumno_nombre) AS alumnos_unicos,
    COUNT(DISTINCT alumno_id)     AS total_ids,
    COUNT(DISTINCT alumno_id) - COUNT(DISTINCT alumno_nombre) AS ids_sobrantes
FROM asistencia_clases")->fetch();
?>

<div class="mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase mb-3 border border-indigo-100 shadow-sm">
            <span class="material-symbols-outlined text-[14px]">content_copy</span>
            Análisis de Integridad
        </div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight">Gestión de Duplicados</h1>
        <p class="text-slate-500 mt-1 italic">Detección y unificación de estudiantes con múltiples identidades.</p>
    </div>
    
    <!-- Selector de Carrera -->
    <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-gray-100 shadow-xl shadow-slate-100/50">
        <span class="text-[9px] font-black text-slate-400 uppercase ml-3 tracking-widest">Carrera:</span>
        <div class="flex gap-1">
            <?php foreach ($CARRERAS as $sigla => $c): if(!$c['activa']) continue; ?>
                <a href="index.php?v=duplicados&carrera=<?php echo $sigla; ?>" 
                   class="px-4 py-2 rounded-xl text-[11px] font-black transition-all <?php echo $carrera_sigla === $sigla ? 'bg-slate-900 text-white shadow-lg shadow-slate-200' : 'text-slate-400 hover:bg-slate-50 hover:text-slate-600'; ?>">
                    <?php echo $sigla; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- KPIs Compactos -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
    <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-6 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400">
            <span class="material-symbols-outlined">person</span>
        </div>
        <div>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Alumnos Únicos</p>
            <p class="text-xl font-black text-slate-800"><?php echo number_format($resumen['alumnos_unicos']); ?></p>
        </div>
    </div>
    <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm p-6 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400">
            <span class="material-symbols-outlined">fingerprint</span>
        </div>
        <div>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">IDs Totales</p>
            <p class="text-xl font-black text-slate-800"><?php echo number_format($resumen['total_ids']); ?></p>
        </div>
    </div>
    <div class="bg-slate-900 rounded-[2rem] shadow-xl shadow-slate-200 p-6 flex items-center gap-4 text-white">
        <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-white">
            <span class="material-symbols-outlined">cleaning_services</span>
        </div>
        <div>
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Fragmentación</p>
            <p class="text-xl font-black text-white"><?php echo number_format($resumen['ids_sobrantes']); ?></p>
        </div>
    </div>
</div>

// Sección de Similitud (WIZARD)
$sugerencias = [];
try {
    // Verificamos si la extensión pg_trgm existe
    $ext = $pdo->query("SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm'")->fetch();
    if ($ext) {
        $sql_sim = "SELECT t1.alumno_nombre as nombre1, t2.alumno_nombre as nombre2, 
                           similarity(t1.alumno_nombre, t2.alumno_nombre) as score
                    FROM (SELECT DISTINCT alumno_nombre FROM asistencia_clases) t1
                    JOIN (SELECT DISTINCT alumno_nombre FROM asistencia_clases) t2 
                        ON t1.alumno_nombre < t2.alumno_nombre
                    WHERE (t1.alumno_nombre % t2.alumno_nombre OR similarity(t1.alumno_nombre, t2.alumno_nombre) > 0.2)
                    ORDER BY score DESC LIMIT 5";
        $sugerencias = $pdo->query($sql_sim)->fetchAll();
    }
} catch(Exception $e) { 
    $sugerencias = []; 
}
?>

<?php if (!empty($sugerencias)): ?>
<div class="mb-12">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-1.5 h-6 bg-amber-400 rounded-full"></div>
        <h2 class="text-xl font-black text-slate-800 tracking-tight leading-none uppercase italic text-sm tracking-widest">Limpieza por Similitud</h2>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($sugerencias as $idx => $s): ?>
        <div class="bg-white rounded-[2rem] border border-gray-100 shadow-xl shadow-slate-100/30 p-6 relative overflow-hidden group">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[9px] font-black bg-amber-50 text-amber-600 px-2 py-1 rounded-lg border border-amber-100 uppercase tracking-tighter italic">Confianza: <?php echo round($s['score']*100); ?>%</span>
                <span class="material-symbols-outlined text-slate-200 group-hover:text-amber-400 transition-colors">auto_fix_high</span>
            </div>
            
            <div class="space-y-3 mb-6">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Nombre A</p>
                    <p class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($s['nombre1']); ?></p>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Nombre B</p>
                    <p class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($s['nombre2']); ?></p>
                </div>
            </div>
            
            <a href="../index.php?v=duplicados&carrera=<?php echo $carrera_sigla; ?>" 
               class="block w-full text-center py-3 bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-black transition-all">
                Revisar Registro
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Tabla de identidades múltiples -->
<div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-xl shadow-slate-100/50 overflow-hidden mb-12">
    <div class="px-10 py-8 border-b border-gray-50 flex justify-between items-center bg-gray-50/30">
        <h3 class="font-black text-slate-800 flex items-center gap-3">
            <span class="material-symbols-outlined text-amber-500">warning</span>
            Alumnos con Múltiples Identidades 
            <span class="text-amber-600 bg-amber-50 px-2 py-0.5 rounded-lg text-xs ml-1">(<?php echo $total_dups; ?>)</span>
        </h3>
    </div>
    <?php if ($total_dups > 0): ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[10px] text-slate-400 uppercase tracking-widest bg-gray-50/50 border-b border-gray-100 font-black">
                    <th class="px-10 py-5 text-left font-black">Estudiante</th>
                    <th class="px-10 py-5 text-center font-black">IDs Distintos</th>
                    <th class="px-10 py-5 text-left font-black">Grupos</th>
                    <th class="px-10 py-5 text-center font-black">Registros</th>
                    <th class="px-10 py-5 text-right font-black">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($duplicados as $d): 
                    $ids = (int)$d['total_ids'];
                    $badgeStyle = $ids >= 5 
                        ? 'bg-red-50 text-red-600 border-red-100' 
                        : ($ids >= 3 ? 'bg-amber-50 text-amber-600 border-amber-100' : 'bg-slate-50 text-slate-600 border-slate-100');
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="px-10 py-6">
                        <div class="flex items-center gap-5">
                            <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center font-black text-xs uppercase shrink-0 border border-gray-100 group-hover:bg-white transition-colors">
                                <?php echo substr($d['alumno_nombre'], 0, 2); ?>
                            </div>
                            <p class="font-black text-slate-700"><?php echo htmlspecialchars($d['alumno_nombre']); ?></p>
                        </div>
                    </td>
                    <td class="px-10 py-6 text-center">
                        <span class="px-4 py-2 rounded-xl text-[10px] font-black border <?php echo $badgeStyle; ?> shadow-sm shadow-slate-100 italic">
                            <?php echo $ids; ?> IDENTIDADES
                        </span>
                    </td>
                    <td class="px-10 py-6">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-tighter bg-slate-50 px-3 py-1 rounded-lg border border-slate-100 inline-block">
                            <?php echo htmlspecialchars($d['grupos']); ?>
                        </p>
                    </td>
                    <td class="px-10 py-6 text-center font-black text-slate-800 text-lg">
                        <?php echo number_format($d['total_registros']); ?>
                    </td>
                    <td class="px-10 py-6 text-right">
                        <a href="../index.php?v=perfil_alumno&alumno=<?php echo urlencode($d['alumno_nombre']); ?>&carrera=<?php echo $carrera_sigla; ?>"
                           target="_blank"
                           class="w-12 h-12 rounded-2xl bg-white border border-gray-100 text-slate-400 hover:bg-slate-900 hover:text-white inline-flex items-center justify-center transition-all shadow-sm shadow-slate-100">
                            <span class="material-symbols-outlined text-[20px]">person</span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="py-24 text-center">
        <div class="w-20 h-20 bg-green-50 rounded-[2rem] flex items-center justify-center mx-auto mb-6 shadow-xl shadow-green-100/50">
            <span class="material-symbols-outlined text-green-500 text-4xl">verified_user</span>
        </div>
        <h3 class="text-xl font-black text-slate-800 mb-2 tracking-tight">¡Base de datos íntegra!</h3>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest italic">No se detectaron alumnos con múltiples identidades.</p>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
    <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white shadow-2xl shadow-slate-200 relative overflow-hidden">
        <span class="material-symbols-outlined absolute -right-6 -bottom-6 text-white/5 text-[180px]">engineering</span>
        <h3 class="text-xl font-black mb-8 flex items-center gap-3">
            <span class="material-symbols-outlined text-indigo-400">psychology</span>
            Diagnóstico de Datos
        </h3>
        <p class="text-slate-400 text-sm leading-relaxed mb-8 font-medium italic">
            El sistema detecta una discrepancia del <span class="text-white font-black"><?php echo round(($resumen['ids_sobrantes'] / max(1, $resumen['total_ids'])) * 100, 1); ?>%</span>. Esta fragmentación de datos diluye las estadísticas de asistencia individual y afecta el análisis de riesgo.
        </p>
        <button class="bg-white text-slate-900 px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-50 transition-all shadow-xl">Auditar Registros</button>
    </div>

    <div class="bg-white rounded-[2.5rem] border border-gray-100 p-10 shadow-sm">
        <h3 class="text-sm font-black text-slate-800 mb-8 flex items-center gap-3 uppercase tracking-widest">
            <span class="material-symbols-outlined text-indigo-500">lightbulb</span>
            Estrategias de Limpieza
        </h3>
        <ul class="space-y-6">
            <li class="flex items-start gap-4">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-black shrink-0 mt-0.5 shadow-sm">1</div>
                <div>
                    <p class="text-xs font-black text-slate-800 mb-1 uppercase tracking-tight">Unificación Determinista</p>
                    <p class="text-[11px] text-slate-400 font-bold uppercase leading-tight italic">Consolidar todos los registros históricos bajo el nombre limpio oficial.</p>
                </div>
            </li>
            <li class="flex items-start gap-4">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-black shrink-0 mt-0.5 shadow-sm">2</div>
                <div>
                    <p class="text-xs font-black text-slate-800 mb-1 uppercase tracking-tight">Saneamiento de UUIDs</p>
                    <p class="text-[11px] text-slate-400 font-bold uppercase leading-tight italic">Actualizar la App para usar un método de generación de IDs más estable.</p>
                </div>
            </li>
        </ul>
    </div>
</div>
