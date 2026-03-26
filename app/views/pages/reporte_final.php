<?php
/**
 * app/views/pages/reporte_final.php
 * Reporte de cierre de período: resumen integral de la carrera.
 */
require_once 'app/models/ReportesModel.php';
require_once 'app/models/AlumnosModel.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Período por defecto: cuatrimestre actual
$mes_actual = (int)date('n');
if ($mes_actual >= 1 && $mes_actual <= 4)      { $inicio_def = date('Y-01-01'); $fin_def = date('Y-04-30'); $periodo_label = 'Enero - Abril ' . date('Y'); }
elseif ($mes_actual >= 5 && $mes_actual <= 8)  { $inicio_def = date('Y-05-01'); $fin_def = date('Y-08-31'); $periodo_label = 'Mayo - Agosto ' . date('Y'); }
else                                            { $inicio_def = date('Y-09-01'); $fin_def = date('Y-12-31'); $periodo_label = 'Septiembre - Diciembre ' . date('Y'); }

$inicio = $_GET['inicio'] ?? $inicio_def;
$fin    = $_GET['fin']    ?? $fin_def;

// Datos del período
$resumen     = ReportesModel::getResumenFinalPeriodo($pdo, $inicio, $fin);
$por_materia = ReportesModel::getAsistenciaPorMateria($pdo, $inicio, $fin);
$por_mes     = ReportesModel::getResumenMensualGlobal($pdo, $inicio, $fin);
$top_faltas  = ReportesModel::getTopAlumnosConFaltas($pdo, $inicio, $fin, 10);
$en_riesgo   = AlumnosModel::getAlumnosEnRiesgo($pdo, 100);

// Exportar como CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_final_' . $carrera_sigla . '_' . date('Ymd') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($fp, ['=== REPORTE FINAL DE PERÍODO ===']);
    fputcsv($fp, ['Carrera', $carrera_info['nombre_largo']]);
    fputcsv($fp, ['Período', $inicio . ' a ' . $fin]);
    fputcsv($fp, ['Total Alumnos', $resumen['total_alumnos'] ?? 0]);
    fputcsv($fp, ['Total Grupos', $resumen['total_grupos'] ?? 0]);
    fputcsv($fp, ['Total Profesores', $resumen['total_profesores'] ?? 0]);
    fputcsv($fp, ['% Asistencia Global', $resumen['pct_asistencia'] ?? 0]);
    fputcsv($fp, ['Días con Clase', $resumen['dias_con_clase'] ?? 0]);
    fputcsv($fp, ['']);
    fputcsv($fp, ['=== ALUMNOS EN RIESGO ===']);
    fputcsv($fp, ['Nombre', 'Grupo', 'Total Faltas']);
    foreach ($en_riesgo as $a) fputcsv($fp, [$a['alumno_nombre'], $a['grupo_principal'] ?? '?', $a['total_faltas']]);
    fputcsv($fp, ['']);
    fputcsv($fp, ['=== ASISTENCIA POR MATERIA ===']);
    fputcsv($fp, ['Materia', 'Profesor', 'Grupo', 'Días', 'Alumnos', '% Asistencia']);
    foreach ($por_materia as $m) fputcsv($fp, [$m['materia_nombre'], $m['profesor_nombre'], $m['grupo_nombre'], $m['dias_impartida'], $m['total_alumnos'], $m['pct_asistencia']]);
    fclose($fp); exit;
}
?>

<div class="mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold uppercase mb-3">
            <span class="material-symbols-outlined text-[14px]">summarize</span>
            Reporte Final
        </div>
        <h1 class="text-3xl font-black text-gray-800 tracking-tight">Reporte de Cierre de Período</h1>
        <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($carrera_info['nombre_largo']); ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <form method="GET" action="index.php" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="v" value="reporte_final">
            <input type="date" name="inicio" value="<?php echo $inicio; ?>"
                   class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-green-400">
            <span class="text-gray-400 font-bold">→</span>
            <input type="date" name="fin" value="<?php echo $fin; ?>"
                   class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-green-400">
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all">Buscar</button>
        </form>
        <a href="index.php?v=reporte_final&inicio=<?php echo $inicio; ?>&fin=<?php echo $fin; ?>&export=1"
           class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">download</span> CSV
        </a>
    </div>
</div>

<!-- RESUMEN HERO -->
<div class="bg-gradient-to-r from-green-600 to-emerald-700 rounded-2xl p-6 text-white shadow-lg mb-6">
    <div class="flex flex-col md:flex-row justify-between gap-4">
        <div>
            <p class="text-green-200 text-xs font-bold uppercase tracking-widest">Período Analizado</p>
            <h2 class="text-2xl font-black mt-1"><?php echo $inicio; ?> → <?php echo $fin; ?></h2>
            <p class="text-green-200 text-sm mt-1"><?php echo htmlspecialchars($carrera_sigla); ?> · Generado <?php echo date('d/m/Y H:i'); ?></p>
        </div>
        <div class="flex gap-4 flex-wrap">
            <?php
            $hero_stats = [
                ['v' => $resumen['total_alumnos']    ?? 0, 'l' => 'Alumnos'],
                ['v' => $resumen['total_grupos']     ?? 0, 'l' => 'Grupos'],
                ['v' => $resumen['total_profesores'] ?? 0, 'l' => 'Profesores'],
                ['v' => number_format((float)($resumen['pct_asistencia'] ?? 0), 1) . '%', 'l' => 'Asistencia'],
                ['v' => $resumen['dias_con_clase']   ?? 0, 'l' => 'Días de Clase'],
            ];
            foreach ($hero_stats as $hs):
            ?>
            <div class="bg-white/15 backdrop-blur-sm rounded-xl px-4 py-3 text-center border border-white/20 min-w-[90px]">
                <p class="text-2xl font-black"><?php echo $hs['v']; ?></p>
                <p class="text-green-200 text-[10px] uppercase tracking-wide font-bold mt-0.5"><?php echo $hs['l']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Evolución mensual -->
    <?php if (!empty($por_mes)): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-green-500 text-lg">trending_up</span>
                Evolución Mensual
            </h3>
        </div>
        <div class="p-6 space-y-3">
            <?php
            $max_pct = max(array_column($por_mes, 'pct_asistencia'));
            foreach ($por_mes as $pm):
                $pct = (float)$pm['pct_asistencia'];
                $barColor = $pct >= 85 ? 'bg-green-500' : ($pct >= 70 ? 'bg-yellow-500' : 'bg-red-500');
                $pctColor = $pct >= 85 ? 'text-green-600' : ($pct >= 70 ? 'text-yellow-600' : 'text-red-600');
                $bar_width = $max_pct > 0 ? round($pct / 100 * 100) : 0;
            ?>
            <div class="flex items-center gap-4">
                <span class="w-20 text-xs font-bold text-gray-600 shrink-0"><?php echo htmlspecialchars($pm['mes_abbr']); ?> <?php echo htmlspecialchars($pm['anio']); ?></span>
                <div class="flex-1 h-6 bg-gray-100 rounded-lg overflow-hidden">
                    <div class="h-full <?php echo $barColor; ?> rounded-lg transition-all flex items-center justify-end pr-2"
                         style="width: <?php echo $bar_width; ?>%">
                        <span class="text-white text-[10px] font-black"><?php echo $pct; ?>%</span>
                    </div>
                </div>
                <span class="w-16 text-xs text-gray-400 shrink-0"><?php echo $pm['dias_con_clase']; ?> días</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top 10 con más faltas -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-500 text-lg">crisis_alert</span>
                Top 10 con Mayor Ausentismo
            </h3>
        </div>
        <div class="divide-y divide-gray-50">
            <?php if (empty($top_faltas)): ?>
            <p class="p-6 text-center text-gray-400 text-sm">Sin datos en el período</p>
            <?php else: ?>
            <?php $rank = 1; foreach ($top_faltas as $al):
                $pct = (float)$al['pct_asistencia'];
                $pctColor = $pct < 60 ? 'text-red-600' : ($pct < 70 ? 'text-orange-600' : 'text-yellow-600');
            ?>
            <div class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50/50 transition-colors">
                <span class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-[10px] font-black text-gray-500 shrink-0"><?php echo $rank++; ?></span>
                <div class="flex-1 min-w-0">
                    <a href="index.php?v=perfil_alumno&alumno=<?php echo urlencode($al['alumno_nombre']); ?>"
                       class="font-bold text-gray-800 text-sm hover:text-brand-600 transition-colors truncate block">
                        <?php echo htmlspecialchars($al['alumno_nombre']); ?>
                    </a>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($al['grupo_principal'] ?? '?'); ?></p>
                </div>
                <div class="text-right shrink-0">
                    <span class="font-black <?php echo $pctColor; ?>"><?php echo $pct; ?>%</span>
                    <p class="text-xs text-red-400 font-bold"><?php echo $al['total_faltas_periodo']; ?> faltas</p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($top_faltas)): ?>
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/30">
            <a href="index.php?v=top_remediales&inicio=<?php echo $inicio; ?>&fin=<?php echo $fin; ?>"
               class="text-xs font-bold text-brand-600 hover:underline">Ver lista completa de remediales →</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabla de asistencia por materia -->
<?php if (!empty($por_materia)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
            <span class="material-symbols-outlined text-green-500 text-lg">school</span>
            Asistencia por Materia
        </h3>
        <span class="text-xs text-gray-400"><?php echo count($por_materia); ?> materias</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-[10px] text-gray-400 uppercase bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left font-bold">Materia</th>
                    <th class="px-5 py-3 text-left font-bold">Profesor</th>
                    <th class="px-5 py-3 text-left font-bold">Grupo</th>
                    <th class="px-5 py-3 text-center font-bold">Días</th>
                    <th class="px-5 py-3 text-center font-bold">Alumnos</th>
                    <th class="px-5 py-3 text-center font-bold">Ausencias</th>
                    <th class="px-5 py-3 text-center font-bold">% Asistencia</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($por_materia as $m):
                    $pct = (float)$m['pct_asistencia'];
                    $badgeColor = $pct >= 85 ? 'bg-green-100 text-green-700' : ($pct >= 70 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3.5 font-bold text-gray-800 max-w-[200px] truncate"><?php echo htmlspecialchars($m['materia_nombre'] ?? '—'); ?></td>
                    <td class="px-5 py-3.5 text-gray-500 text-xs"><?php echo htmlspecialchars($m['profesor_nombre'] ?? '—'); ?></td>
                    <td class="px-5 py-3.5">
                        <a href="index.php?v=vista_grupo&grupo=<?php echo urlencode($m['grupo_nombre']); ?>"
                           class="font-mono bg-purple-50 text-purple-700 px-2 py-0.5 rounded border border-purple-100 text-xs font-bold hover:bg-purple-100">
                            <?php echo htmlspecialchars($m['grupo_nombre']); ?>
                        </a>
                    </td>
                    <td class="px-5 py-3.5 text-center text-gray-600"><?php echo $m['dias_impartida']; ?></td>
                    <td class="px-5 py-3.5 text-center text-gray-600"><?php echo $m['total_alumnos']; ?></td>
                    <td class="px-5 py-3.5 text-center text-red-500 font-bold"><?php echo $m['total_ausencias']; ?></td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="<?php echo $badgeColor; ?> px-2.5 py-1 rounded-full text-xs font-black">
                            <?php echo $pct; ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
