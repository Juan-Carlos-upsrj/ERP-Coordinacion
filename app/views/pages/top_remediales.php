<?php
/**
 * app/views/pages/top_remediales.php
 * Lista priorizada de alumnos en situación remedial (alto ausentismo).
 */
require_once 'app/models/ReportesModel.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$mes_actual = (int)date('n');
if ($mes_actual >= 1 && $mes_actual <= 4)      { $inicio_def = date('Y-01-01'); $fin_def = date('Y-04-30'); }
elseif ($mes_actual >= 5 && $mes_actual <= 8)  { $inicio_def = date('Y-05-01'); $fin_def = date('Y-08-31'); }
else                                            { $inicio_def = date('Y-09-01'); $fin_def = date('Y-12-31'); }

$inicio = $_GET['inicio'] ?? $inicio_def;
$fin    = $_GET['fin']    ?? $fin_def;
$umbral_pct = (float)($_GET['umbral'] ?? 75);

$top = ReportesModel::getTopAlumnosConFaltas($pdo, $inicio, $fin, 50);

// Filtrar por umbral de asistencia
$top_filtrado = array_filter($top, fn($a) => (float)$a['pct_asistencia'] < $umbral_pct);

// Estadísticas rápidas
$criticos   = count(array_filter($top_filtrado, fn($a) => (float)$a['pct_asistencia'] < 60));
$riesgo_alt = count(array_filter($top_filtrado, fn($a) => (float)$a['pct_asistencia'] >= 60 && (float)$a['pct_asistencia'] < 70));
$en_riesgo  = count(array_filter($top_filtrado, fn($a) => (float)$a['pct_asistencia'] >= 70));
?>

<div class="mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-bold uppercase mb-3">
            <span class="material-symbols-outlined text-[14px]">emergency_heat</span>
            Top Remediales
        </div>
        <h1 class="text-3xl font-black text-gray-800 tracking-tight">Alumnos en Situación Remedial</h1>
        <p class="text-gray-500 mt-1">Ordenados por ausentismo · <?php echo htmlspecialchars($carrera_info['nombre_largo']); ?></p>
    </div>
    <form method="GET" action="index.php" class="flex items-center gap-2 flex-wrap">
        <input type="hidden" name="v" value="top_remediales">
        <input type="date" name="inicio" value="<?php echo $inicio; ?>"
               class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-orange-400">
        <span class="text-gray-400 text-sm">→</span>
        <input type="date" name="fin" value="<?php echo $fin; ?>"
               class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-orange-400">
        <select name="umbral" class="px-3 py-2 rounded-xl border border-gray-200 text-sm font-medium bg-white focus:ring-2 focus:ring-orange-400">
            <?php foreach ([60, 65, 70, 75, 80] as $u): ?>
            <option value="<?php echo $u; ?>" <?php echo $umbral_pct == $u ? 'selected' : ''; ?>>Umbral: &lt;<?php echo $u; ?>%</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all">Filtrar</button>
    </form>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-red-600 text-white rounded-2xl p-5 shadow-lg shadow-red-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-3xl font-black"><?php echo $criticos; ?></p>
                <p class="text-red-200 text-xs font-bold uppercase tracking-wide mt-1">Críticos (&lt;60%)</p>
            </div>
            <span class="material-symbols-outlined text-4xl text-red-200">crisis_alert</span>
        </div>
    </div>
    <div class="bg-orange-500 text-white rounded-2xl p-5 shadow-lg shadow-orange-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-3xl font-black"><?php echo $riesgo_alt; ?></p>
                <p class="text-orange-200 text-xs font-bold uppercase tracking-wide mt-1">Alto Riesgo (60–69%)</p>
            </div>
            <span class="material-symbols-outlined text-4xl text-orange-200">warning</span>
        </div>
    </div>
    <div class="bg-yellow-500 text-white rounded-2xl p-5 shadow-lg shadow-yellow-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-3xl font-black"><?php echo $en_riesgo; ?></p>
                <p class="text-yellow-200 text-xs font-bold uppercase tracking-wide mt-1">En Riesgo (70–<?php echo $umbral_pct - 1; ?>%)</p>
            </div>
            <span class="material-symbols-outlined text-4xl text-yellow-200">error_outline</span>
        </div>
    </div>
</div>

<!-- Tabla de remediales -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
            <span class="material-symbols-outlined text-orange-500 text-lg">emergency_heat</span>
            <span class="text-orange-600 font-black"><?php echo count($top_filtrado); ?></span> alumnos bajo <?php echo $umbral_pct; ?>% de asistencia
        </h3>
        <p class="text-xs text-gray-400"><?php echo $inicio; ?> → <?php echo $fin; ?></p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-[10px] text-gray-400 uppercase bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-center w-12">#</th>
                    <th class="px-5 py-3 text-left font-bold">Alumno</th>
                    <th class="px-5 py-3 text-left font-bold">Grupo</th>
                    <th class="px-5 py-3 text-center font-bold">% Asistencia</th>
                    <th class="px-5 py-3 text-center font-bold">Faltas (período)</th>
                    <th class="px-5 py-3 text-center font-bold">Faltas (total histórico)</th>
                    <th class="px-5 py-3 text-right font-bold">Ver</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($top_filtrado)):
                $rank = 1;
                foreach ($top_filtrado as $al):
                    $pct = (float)$al['pct_asistencia'];
                    if ($pct < 60)       { $rowBg = 'bg-red-50/30'; $badge = 'bg-red-600 text-white'; $icon = 'crisis_alert'; }
                    elseif ($pct < 70)   { $rowBg = 'bg-orange-50/30'; $badge = 'bg-orange-500 text-white'; $icon = 'warning'; }
                    else                 { $rowBg = 'bg-yellow-50/20'; $badge = 'bg-yellow-400 text-yellow-900'; $icon = 'error_outline'; }
            ?>
            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors <?php echo $rowBg; ?>">
                <td class="px-5 py-3.5 text-center">
                    <span class="text-xs font-black text-gray-400"><?php echo $rank++; ?></span>
                </td>
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center font-black text-xs text-gray-600 shrink-0">
                            <?php echo Utils::safeSubstr($al['alumno_nombre'], 0, 2); ?>
                        </div>
                        <span class="font-bold text-gray-800"><?php echo htmlspecialchars($al['alumno_nombre']); ?></span>
                    </div>
                </td>
                <td class="px-5 py-3.5">
                    <a href="index.php?v=vista_grupo&grupo=<?php echo urlencode($al['grupo_principal']); ?>"
                       class="font-mono bg-purple-50 text-purple-700 px-2 py-0.5 rounded border border-purple-100 text-xs font-bold hover:bg-purple-100">
                        <?php echo htmlspecialchars($al['grupo_principal'] ?? '?'); ?>
                    </a>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <div class="inline-flex flex-col items-center gap-1.5 min-w-[100px]">
                        <span class="<?php echo $badge; ?> px-3 py-1 rounded-xl text-xs font-black shadow-sm ring-1 ring-white/20">
                            <span class="material-symbols-outlined text-[10px] mr-1" style="vertical-align:middle"><?php echo $icon; ?></span>
                            <?php echo $pct; ?>%
                        </span>
                        <div class="w-full h-2 bg-gray-100 dark:bg-slate-700/50 rounded-full overflow-hidden border border-gray-200/50">
                            <div class="h-full rounded-full <?php echo $pct >= 85 ? 'bg-emerald-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-rose-500'); ?>" 
                                 style="width:<?php echo min(100, $pct); ?>%"></div>
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <span class="text-red-600 font-black text-lg"><?php echo $al['total_faltas_periodo']; ?></span>
                </td>
                <td class="px-5 py-3.5 text-center text-gray-500 font-medium">
                    <?php echo $al['total_faltas_historico'] ?? '—'; ?>
                </td>
                <td class="px-5 py-3.5 text-right">
                    <a href="index.php?v=perfil_alumno&alumno=<?php echo urlencode($al['alumno_nombre']); ?>"
                       class="w-8 h-8 rounded-lg bg-white border border-gray-200 hover:border-orange-400 hover:text-orange-600 text-gray-400 inline-flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                    </a>
                </td>
            </tr>
            <?php endforeach;
            else: ?>
            <tr><td colspan="7" class="py-16 text-center text-gray-400">
                <span class="material-symbols-outlined text-5xl mb-3 text-green-300">verified</span>
                <p class="text-sm font-bold text-green-600">¡Excelente! Ningún alumno está por debajo del umbral de <?php echo $umbral_pct; ?>%</p>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
