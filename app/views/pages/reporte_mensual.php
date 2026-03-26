<?php
/**
 * app/views/pages/reporte_mensual.php
 * Reporte de asistencia mes a mes, desglosado por grupo.
 */
require_once 'app/models/ReportesModel.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Rango por defecto: año en curso
$mes_actual = (int)date('n');
if ($mes_actual >= 1 && $mes_actual <= 4)      { $inicio_def = date('Y-01-01'); $fin_def = date('Y-04-30'); }
elseif ($mes_actual >= 5 && $mes_actual <= 8)  { $inicio_def = date('Y-05-01'); $fin_def = date('Y-08-31'); }
else                                            { $inicio_def = date('Y-09-01'); $fin_def = date('Y-12-31'); }

$inicio = $_GET['inicio'] ?? $inicio_def;
$fin    = $_GET['fin']    ?? $fin_def;

// Exportar CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_mensual_' . date('Ymd') . '.csv"');
    $rows = ReportesModel::getAsistenciaMensualPorGrupo($pdo, $inicio, $fin);
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($fp, ['Mes', 'Grupo', 'Alumnos', 'Días Clase', 'Presentes', 'Ausentes', '% Asistencia']);
    foreach ($rows as $r) fputcsv($fp, [$r['mes_label'], $r['grupo_nombre'], $r['total_alumnos'],
        $r['dias_con_clase'], $r['total_asistencias'] ?? $r['total_presentes'],
        $r['total_ausentes'], $r['pct_asistencia']]);
    fclose($fp); exit;
}

$resumen_mensual = ReportesModel::getResumenMensualGlobal($pdo, $inicio, $fin);
$detalle_grupos  = ReportesModel::getAsistenciaMensualPorGrupo($pdo, $inicio, $fin);

// Organizar datos: meses × grupos
$meses = [];
$grupos_set = [];
$matrix = []; // $matrix[mes_key][grupo_nombre] = pct

foreach ($detalle_grupos as $d) {
    $mk = $d['mes_key'];
    $gn = $d['grupo_nombre'];
    if (!in_array($mk, $meses)) $meses[] = $mk;
    if (!in_array($gn, $grupos_set)) $grupos_set[] = $gn;
    $matrix[$mk][$gn] = $d;
}
sort($meses);
sort($grupos_set);

$meses_es = [
    '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
    '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
];
?>

<div class="mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold uppercase mb-3">
            <span class="material-symbols-outlined text-[14px]">calendar_month</span>
            Reporte Mensual
        </div>
        <h1 class="text-3xl font-black text-gray-800 tracking-tight">Asistencia por Mes</h1>
        <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($carrera_info['nombre_largo']); ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <form method="GET" action="index.php" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="v" value="reporte_mensual">
            <input type="date" name="inicio" value="<?php echo $inicio; ?>"
                   class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-400">
            <span class="text-gray-400 text-sm font-bold">→</span>
            <input type="date" name="fin" value="<?php echo $fin; ?>"
                   class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-400">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all">Filtrar</button>
        </form>
        <a href="index.php?v=reporte_mensual&inicio=<?php echo $inicio; ?>&fin=<?php echo $fin; ?>&export=1"
           class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">download</span> CSV
        </a>
    </div>
</div>

<!-- Resumen mensual global (tarjetas) -->
<?php if (!empty($resumen_mensual)): ?>
<div class="mb-6">
    <h3 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-3">Evolución Mensual</h3>
    <div class="flex gap-3 overflow-x-auto pb-2">
        <?php foreach ($resumen_mensual as $rm):
            $pct = (float)$rm['pct_asistencia'];
            $borderColor = $pct >= 85 ? 'border-green-200 bg-green-50/50' : ($pct >= 70 ? 'border-yellow-200 bg-yellow-50/50' : 'border-red-200 bg-red-50/50');
            $pctColor    = $pct >= 85 ? 'text-green-600' : ($pct >= 70 ? 'text-yellow-600' : 'text-red-600');
            $mes_num_str = str_pad($rm['mes_num'], 2, '0', STR_PAD_LEFT);
            $mes_nombre  = $meses_es[$mes_num_str] ?? $rm['mes_abbr'];
        ?>
        <div class="shrink-0 bg-white border-2 <?php echo $borderColor; ?> rounded-2xl px-5 py-4 text-center min-w-[120px]">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"><?php echo strtoupper($mes_nombre); ?></p>
            <p class="text-2xl font-black <?php echo $pctColor; ?> mt-1"><?php echo $pct; ?>%</p>
            <p class="text-xs text-gray-400 mt-1"><?php echo $rm['dias_con_clase']; ?> días</p>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Matriz grupos × meses -->
<?php if (!empty($meses) && !empty($grupos_set)): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
            <span class="material-symbols-outlined text-indigo-500 text-lg">grid_on</span>
            Detalle por Grupo y Mes
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-[10px] text-gray-400 uppercase bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left font-bold sticky left-0 bg-gray-50">Grupo</th>
                    <?php foreach ($meses as $mk):
                        $parts = explode('-', $mk);
                        $mes_nombre = $meses_es[$parts[1]] ?? $parts[1];
                    ?>
                    <th class="px-4 py-3 text-center font-bold"><?php echo $mes_nombre; ?><br><span class="font-normal normal-case"><?php echo $parts[0]; ?></span></th>
                    <?php endforeach; ?>
                    <th class="px-4 py-3 text-center font-bold">Promedio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($grupos_set as $gn):
                    $suma_pct = 0; $cant = 0;
                ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-5 py-3.5 sticky left-0 bg-white">
                        <a href="index.php?v=vista_grupo&grupo=<?php echo urlencode($gn); ?>"
                           class="font-mono bg-purple-50 text-purple-700 px-2 py-0.5 rounded border border-purple-100 text-xs font-bold hover:bg-purple-100">
                            <?php echo htmlspecialchars($gn); ?>
                        </a>
                    </td>
                    <?php foreach ($meses as $mk):
                        $cell = $matrix[$mk][$gn] ?? null;
                        if ($cell) {
                            $pct = (float)$cell['pct_asistencia'];
                            $suma_pct += $pct; $cant++;
                            $bg = $pct >= 85 ? 'bg-green-100 text-green-700' : ($pct >= 70 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                        }
                    ?>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($cell): ?>
                        <span class="<?php echo $bg; ?> px-2.5 py-1 rounded-lg text-xs font-black">
                            <?php echo $pct; ?>%
                        </span>
                        <?php else: ?>
                        <span class="text-gray-200">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="px-4 py-3.5 text-center font-black text-gray-700">
                        <?php
                        $promedio_grupo = $cant > 0 ? round($suma_pct / $cant, 1) : null;
                        if ($promedio_grupo !== null) {
                            $bg = $promedio_grupo >= 85 ? 'text-green-600' : ($promedio_grupo >= 70 ? 'text-yellow-600' : 'text-red-600');
                            echo "<span class='{$bg}'>{$promedio_grupo}%</span>";
                        } else echo '—';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
    <span class="material-symbols-outlined text-5xl text-gray-200 mb-3">calendar_month</span>
    <p class="text-gray-500 font-medium">No hay datos en el período seleccionado.</p>
    <p class="text-gray-400 text-sm mt-1">Prueba con un rango de fechas diferente.</p>
</div>
<?php endif; ?>
