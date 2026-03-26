<?php
/**
 * Panel de Control — logs.php
 * Log de actividad: sincronizaciones de profesores, auditoría de todas las carreras.
 */
require_once 'app/models/LogsModel.php';

$carrera_sigla = $_GET['carrera'] ?? $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$tab    = $_GET['tab']     ?? 'actividad';
$dias   = (int)($_GET['dias']  ?? 30);
$buscar = trim($_GET['q']      ?? '');
$prof_filtro = trim($_GET['profesor'] ?? '');

$actividad    = [];
$syncs_stats  = [];
$prof_detalle = [];

if ($tab === 'actividad') {
    $actividad = LogsModel::getActividadProfesores($pdo, $dias, 200);
    if ($buscar) {
        $actividad = array_filter($actividad, fn($a) =>
            stripos($a['profesor_nombre'], $buscar) !== false ||
            stripos($a['grupo_nombre'], $buscar) !== false
        );
    }
}
elseif ($tab === 'stats') {
    $syncs_stats = LogsModel::getEstadisticasSyncDiaria($pdo, $dias);
}
elseif ($tab === 'profesor' && $prof_filtro) {
    $prof_detalle = LogsModel::getActividadProfesor($pdo, $prof_filtro);
}
?>

<div class="mb-6 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black uppercase mb-3 border border-slate-200">
            <span class="material-symbols-outlined text-[14px]">history_edu</span>
            Auditoría Global
        </div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight">Log de Actividad</h1>
        <p class="text-slate-500 mt-1">Registro de todas las sincronizaciones del sistema.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <!-- Selector de Carrera -->
        <div class="flex items-center gap-1 bg-white p-1.5 rounded-2xl border border-slate-100 shadow-sm">
            <?php foreach ($CARRERAS as $sigla => $c): if(!$c['activa']) continue; ?>
                <a href="index.php?v=logs&carrera=<?php echo $sigla; ?>&tab=<?php echo $tab; ?>&dias=<?php echo $dias; ?>" 
                   class="px-3 py-1.5 rounded-xl text-[10px] font-black transition-all <?php echo $carrera_sigla === $sigla ? 'bg-slate-800 text-white shadow-lg' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <?php echo $sigla; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" action="index.php" class="flex items-center gap-2">
            <input type="hidden" name="v" value="logs">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <input type="hidden" name="carrera" value="<?php echo htmlspecialchars($carrera_sigla); ?>">
            
            <select name="dias" class="pl-4 pr-10 py-2 rounded-2xl border border-slate-100 text-xs font-black bg-white focus:ring-2 focus:ring-indigo-500 shadow-sm appearance-none cursor-pointer">
                <?php foreach ([7, 14, 30, 60, 90] as $d): ?>
                <option value="<?php echo $d; ?>" <?php echo $dias == $d ? 'selected' : ''; ?>>Últimos <?php echo $d; ?> días</option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($tab === 'actividad'): ?>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                <input type="text" name="q" value="<?php echo htmlspecialchars($buscar); ?>"
                       placeholder="Profesor/Grupo..."
                       class="pl-9 pr-4 py-2 bg-white border border-slate-100 rounded-2xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 w-40 shadow-sm">
            </div>
            <?php endif; ?>
            
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-2xl text-xs font-black shadow-lg shadow-indigo-100 transition-all uppercase leading-none h-[38px]">Filtrar</button>
        </form>
    </div>
</div>

<!-- Tabs -->
<div class="flex border-b border-slate-100 mb-6 bg-white rounded-t-3xl overflow-hidden shadow-sm">
    <?php
    $tabs = [
        'actividad' => ['label' => 'Actividad de Profesores', 'icon' => 'fact_check'],
        'stats'     => ['label' => 'Estadísticas por Día',    'icon' => 'bar_chart'],
    ];
    if ($prof_filtro) $tabs['profesor'] = ['label' => 'Detalle: ' . substr($prof_filtro, 0, 15), 'icon' => 'person'];
    foreach ($tabs as $t_key => $t_info): $active = $tab === $t_key;
    ?>
    <a href="index.php?v=logs&carrera=<?php echo $carrera_sigla; ?>&tab=<?php echo $t_key; ?>&dias=<?php echo $dias; ?><?php echo $prof_filtro ? '&profesor=' . urlencode($prof_filtro) : ''; ?>"
       class="flex items-center gap-2 px-8 py-5 text-xs font-black uppercase transition-all border-b-4 <?php echo $active ? 'border-indigo-500 text-indigo-600 bg-indigo-50/20' : 'border-transparent text-slate-400 hover:text-slate-600 hover:bg-slate-50'; ?>">
        <span class="material-symbols-outlined text-[18px]"><?php echo $t_info['icon']; ?></span>
        <?php echo $t_info['label']; ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-b-3xl border-x border-b border-slate-100 shadow-sm overflow-hidden mb-12">
    <?php if ($tab === 'actividad'): ?>
    <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
        <table class="w-full text-sm">
            <thead class="sticky top-0 text-[10px] text-slate-400 uppercase bg-slate-50 border-b border-slate-100 z-10 font-black">
                <tr>
                    <th class="px-8 py-4 text-left">Profesor</th>
                    <th class="px-8 py-4 text-left">Grupo</th>
                    <th class="px-8 py-4 text-center">Clave Fecha</th>
                    <th class="px-8 py-4 text-center">Alumnos</th>
                    <th class="px-8 py-4 text-center">Veces Sync</th>
                    <th class="px-8 py-4 text-center">Última Sync</th>
                    <th class="px-8 py-4 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 font-medium">
                <?php if (empty($actividad)): ?>
                <tr><td colspan="7" class="py-20 text-center text-slate-300">
                    <span class="material-symbols-outlined text-5xl mb-3">inbox</span>
                    <p class="text-xs font-black uppercase tracking-widest">Sin actividad para mostrar</p>
                </td></tr>
                <?php else: ?>
                <?php foreach ($actividad as $a): ?>
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="px-8 py-5">
                        <span class="font-black text-slate-700 block"><?php echo htmlspecialchars($a['profesor_nombre']); ?></span>
                    </td>
                    <td class="px-8 py-5">
                        <span class="inline-block px-2 py-1 rounded bg-purple-50 text-purple-700 text-[10px] font-black border border-purple-100">
                            <?php echo htmlspecialchars($a['grupo_nombre']); ?>
                        </span>
                    </td>
                    <td class="px-8 py-5 text-center text-slate-500 font-mono text-xs"><?php echo $a['fecha']; ?></td>
                    <td class="px-8 py-5 text-center">
                        <span class="font-black text-slate-800 text-lg"><?php echo $a['alumnos_en_lista']; ?></span>
                    </td>
                    <td class="px-8 py-5 text-center">
                        <?php if ((int)$a['veces_sincronizado'] > 1): ?>
                        <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-black border border-amber-200">
                            <?php echo $a['veces_sincronizado']; ?>x
                        </span>
                        <?php else: ?>
                        <span class="text-slate-400 text-[10px] font-bold">1x</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-8 py-5 text-center text-[10px] text-slate-400 font-bold uppercase">
                        <?php echo $a['ultima_sync'] ? date('d/m/Y H:i', strtotime($a['ultima_sync'])) : '—'; ?>
                    </td>
                    <td class="px-8 py-5 text-right">
                        <a href="index.php?v=logs&carrera=<?php echo $carrera_sigla; ?>&tab=profesor&profesor=<?php echo urlencode($a['profesor_nombre']); ?>&dias=<?php echo $dias; ?>"
                           class="w-10 h-10 rounded-2xl bg-slate-50 hover:bg-indigo-600 hover:text-white text-slate-400 inline-flex items-center justify-center transition-all border border-slate-100 group-hover:shadow-lg">
                            <span class="material-symbols-outlined text-[18px]">manage_search</span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'stats'): ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-[10px] text-slate-400 uppercase bg-slate-50 border-b border-slate-100 font-black">
                <tr>
                    <th class="px-8 py-4 text-left">Día</th>
                    <th class="px-8 py-4 text-center">Total Registros</th>
                    <th class="px-8 py-4 text-center">Profesores Activos</th>
                    <th class="px-8 py-4 text-center">Grupos Sync</th>
                    <th class="px-8 py-4 text-left">Distribución</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($syncs_stats)): ?>
                <tr><td colspan="5" class="py-20 text-center text-slate-300 font-black uppercase">Sin estadísticas disponibles.</td></tr>
                <?php else:
                $max_registros = max(array_column($syncs_stats, 'total_registros'));
                foreach ($syncs_stats as $s):
                    $bar_pct = $max_registros > 0 ? round($s['total_registros'] / $max_registros * 100) : 0;
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-8 py-6 font-black text-slate-700 font-mono text-xs"><?php echo $s['dia_sync']; ?></td>
                    <td class="px-8 py-6 text-center font-black text-slate-800 text-lg"><?php echo number_format($s['total_registros']); ?></td>
                    <td class="px-8 py-6 text-center text-indigo-600 font-black uppercase text-xs"><?php echo $s['profesores_activos']; ?> Prof.</td>
                    <td class="px-8 py-6 text-center text-purple-600 font-black uppercase text-xs"><?php echo $s['grupos_sync']; ?> Grp.</td>
                    <td class="px-8 py-6">
                        <div class="flex items-center gap-3">
                            <div class="h-2 bg-slate-200 rounded-full flex-1 max-w-[200px] overflow-hidden">
                                <div class="h-full bg-slate-800 rounded-full" style="width: <?php echo $bar_pct; ?>%"></div>
                            </div>
                            <span class="text-[10px] font-black text-slate-400 uppercase"><?php echo $bar_pct; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($tab === 'profesor' && $prof_filtro): ?>
    <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
        <table class="w-full text-sm font-medium">
            <thead class="sticky top-0 text-[10px] text-slate-400 uppercase bg-slate-50 border-b border-slate-100 font-black">
                <tr>
                    <th class="px-8 py-4 text-center">Fecha</th>
                    <th class="px-8 py-4 text-left">Grupo</th>
                    <th class="px-8 py-4 text-center">Total Alumnos</th>
                    <th class="px-8 py-4 text-center">Presentes</th>
                    <th class="px-8 py-4 text-center">Ausentes</th>
                    <th class="px-8 py-4 text-right">Última Sync</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($prof_detalle)): ?>
                <tr><td colspan="6" class="py-12 text-center text-slate-300 font-black uppercase">Sin registros.</td></tr>
                <?php else: ?>
                <?php foreach ($prof_detalle as $d):
                    $total = $d['alumnos_en_lista'];
                    $sospechoso = $total <= 2;
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors <?php echo $sospechoso ? 'bg-red-50/20' : ''; ?>">
                    <td class="px-8 py-4 text-center font-black font-mono text-xs text-slate-700 tracking-tight">
                        <?php echo $d['fecha']; ?>
                        <?php if ($sospechoso): ?><span class="ml-2 text-red-500 font-black">⚠</span><?php endif; ?>
                    </td>
                    <td class="px-8 py-4">
                        <span class="px-2 py-1 rounded bg-indigo-50 text-indigo-700 text-[10px] font-black border border-indigo-100">
                            <?php echo htmlspecialchars($d['grupo_nombre']); ?>
                        </span>
                    </td>
                    <td class="px-8 py-4 text-center font-black <?php echo $sospechoso ? 'text-red-600' : 'text-slate-800'; ?>">
                        <?php echo $d['alumnos_en_lista']; ?>
                    </td>
                    <td class="px-8 py-4 text-center text-green-600 font-bold"><?php echo $d['presentes']; ?></td>
                    <td class="px-8 py-4 text-center text-red-500 font-bold"><?php echo $d['ausentes']; ?></td>
                    <td class="px-8 py-4 text-right text-[10px] text-slate-400 font-bold uppercase font-mono">
                        <?php echo $d['fecha_sync'] ? date('d/m H:i', strtotime($d['fecha_sync'])) : '—'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
