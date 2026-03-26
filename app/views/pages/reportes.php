<?php
/**
 * app/views/pages/reportes.php
 * Análisis unificado rediseñado con estética Slate moderna e inspiración del portal externo.
 */

require_once 'app/models/DashboardModel.php';
require_once 'app/models/ReportesModel.php';
require_once 'app/core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Filtros de Período
$mes_actual = (int)date('n');
if ($mes_actual >= 1 && $mes_actual <= 4) {
    $inicio_def = date('Y-01-01'); $fin_def = date('Y-04-30');
} elseif ($mes_actual >= 5 && $mes_actual <= 8) {
    $inicio_def = date('Y-05-01'); $fin_def = date('Y-08-31');
} else {
    $inicio_def = date('Y-09-01'); $fin_def = date('Y-12-31');
}
$filtro_inicio = $_GET['fecha_inicio'] ?? ($_GET['inicio'] ?? $inicio_def);
$filtro_fin = $_GET['fecha_fin'] ?? ($_GET['fin'] ?? $fin_def);

// Datos
$resumen_mensual_grafico = DashboardModel::getResumenMensual($pdo, $filtro_inicio, $filtro_fin);
$resumen_grupos = DashboardModel::getResumenGrupos($pdo, $filtro_inicio, $filtro_fin);
$top_ausentismo = DashboardModel::getTopAusentismo($pdo, $filtro_inicio, $filtro_fin, 12);
$resumen_mensual_global = ReportesModel::getResumenMensualGlobal($pdo, $filtro_inicio, $filtro_fin);
$detalle_grupos_mensual  = ReportesModel::getAsistenciaMensualPorGrupo($pdo, $filtro_inicio, $filtro_fin);

// Nuevos datos de Faltas Reales Consolidadas
$total_faltas_reales = ReportesModel::getFaltasRealesAcademia($pdo, $filtro_inicio, $filtro_fin);
$faltas_reales_mensual = ReportesModel::getFaltasRealesMensual($pdo, $filtro_inicio, $filtro_fin);

// Indexar faltas por mes_key
$faltas_por_mes = [];
foreach ($faltas_reales_mensual as $fm) {
    $faltas_por_mes[$fm['mes_key']] = $fm;
}

// Promedios y Totales
$suma = 0; $cnt = count($resumen_grupos);
foreach ($resumen_grupos as $g) $suma += $g['po_asistencia'];
$promedio_global = $cnt > 0 ? $suma / $cnt : 0;

// Matriz
$meses_matrix = []; $grupos_set = []; $matrix = []; 
foreach ($detalle_grupos_mensual as $d) {
    if (!in_array($d['mes_key'], $meses_matrix)) $meses_matrix[] = $d['mes_key'];
    if (!in_array($d['grupo_nombre'], $grupos_set)) $grupos_set[] = $d['grupo_nombre'];
    $matrix[$d['mes_key']][$d['grupo_nombre']] = $d;
}
sort($meses_matrix); sort($grupos_set);

$meses_es = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="bg-slate-50 -m-8 p-8 min-h-screen">

    <!-- Header Section -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-10 gap-6">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Centro de Inteligencia</h1>
            <p class="text-slate-500 font-medium text-sm mt-1 uppercase tracking-widest text-[11px]">Reporte de Aprovechamiento y Auditoría</p>
        </div>
        
        <form method="GET" action="index.php" class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
            <input type="hidden" name="v" value="reportes">
            <div class="flex items-center gap-4 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex-1 lg:flex-none">
                <div class="flex flex-col">
                    <label class="text-[9px] font-black text-slate-400 uppercase px-2 mb-0.5">Inicio Periodo</label>
                    <input type="date" name="fecha_inicio" value="<?php echo $filtro_inicio; ?>" class="bg-transparent border-none text-xs focus:ring-0 text-slate-800 font-bold py-0">
                </div>
                <div class="w-px h-8 bg-slate-100"></div>
                <div class="flex flex-col">
                    <label class="text-[9px] font-black text-slate-400 uppercase px-2 mb-0.5">Fin Periodo</label>
                    <input type="date" name="fecha_fin" value="<?php echo $filtro_fin; ?>" class="bg-transparent border-none text-xs focus:ring-0 text-slate-800 font-bold py-0">
                </div>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-4 rounded-2xl font-black text-xs flex items-center gap-2 shadow-lg shadow-blue-100 transition-all active:scale-95">
                <span class="material-symbols-outlined text-sm">refresh</span>
                ACTUALIZAR DATOS
            </button>
        </form>
    </div>

    <!-- KPIs Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Promedio General -->
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Asistencia Media</p>
            <div class="flex items-end gap-2">
                <p class="text-4xl font-black text-slate-900"><?php echo number_format($promedio_global, 1); ?>%</p>
                <div class="h-8 w-1 <?php echo $promedio_global >= 85 ? 'bg-emerald-500' : ($promedio_global >= 70 ? 'bg-amber-500' : 'bg-rose-500'); ?> rounded-full mb-1"></div>
            </div>
        </div>

        <!-- FALTAS REALES (The Focus!) -->
        <div class="bg-white rounded-2xl p-6 border border-rose-100 shadow-sm border-l-4 border-l-rose-500">
            <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest mb-2">Faltas Reales</p>
            <p class="text-4xl font-black text-rose-600"><?php echo number_format($total_faltas_reales); ?></p>
            <p class="text-[9px] text-slate-400 font-bold mt-2 uppercase tracking-tight">Días de inasistencia total</p>
        </div>

        <!-- Días con Clase -->
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Días Lectivos</p>
            <p class="text-4xl font-black text-indigo-600">
                <?php 
                $total_dias = 0;
                foreach($resumen_mensual_global as $rm) $total_dias += $rm['dias_con_clase'];
                echo $total_dias;
                ?>
            </p>
            <p class="text-[9px] text-slate-400 font-bold mt-2 uppercase tracking-tight">En el rango seleccionado</p>
        </div>

        <!-- Matrícula -->
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Población Activa</p>
            <p class="text-4xl font-black text-blue-600">
                <?php 
                $max_alumnos = 0;
                foreach($resumen_mensual_global as $rm) $max_alumnos = max($max_alumnos, $rm['total_alumnos_activos']);
                echo $max_alumnos;
                ?>
            </p>
            <p class="text-[9px] text-slate-400 font-bold mt-2 uppercase tracking-tight">Alumnos en listas</p>
        </div>
    </div>

    <!-- Estadísticas Mensuales: Asistencia + Faltas Reales -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8">
        <div class="px-8 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600 text-lg">calendar_month</span>
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Desglose Mensual</h3>
            </div>
            <!-- Config Toggles -->
            <div class="flex items-center gap-1 bg-white p-1 rounded-xl border border-slate-200">
                <span class="text-[9px] font-black text-slate-400 px-3 uppercase">Ver Faltas como:</span>
                <button onclick="switchFaltasMode('total')" id="btn-mode-total" class="faltas-mode-btn active-mode px-4 py-2 rounded-lg text-[10px] font-black transition-all">
                    TOTAL
                </button>
                <button onclick="switchFaltasMode('promedio')" id="btn-mode-promedio" class="faltas-mode-btn px-4 py-2 rounded-lg text-[10px] font-black transition-all">
                    PROM/ALUMNO
                </button>
                <button onclick="switchFaltasMode('porcentaje')" id="btn-mode-porcentaje" class="faltas-mode-btn px-4 py-2 rounded-lg text-[10px] font-black transition-all">
                    % AUSENCIA
                </button>
                <button onclick="document.getElementById('help-faltas').classList.toggle('hidden')" class="ml-1 w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center hover:bg-blue-50 transition-colors" title="Ayuda">
                    <span class="text-[12px] font-black text-slate-400">?</span>
                </button>
            </div>
        </div>

        <!-- Help tooltip visibility toggle -->
        <div id="help-faltas" class="hidden px-8 py-4 border-b border-slate-100 bg-blue-50/30">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-[11px] text-slate-600">
                <div class="flex flex-col gap-1">
                    <span class="font-black text-blue-600 uppercase">Total:</span> 
                    <p class="font-medium">Días donde el alumno tuvo 100% de ausencia en todas sus materias registradas.</p>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="font-black text-blue-600 uppercase">Prom/Alumno:</span> 
                    <p class="font-medium">Total de faltas reales ÷ número de alumnos activos. Impacto promedio por cabeza.</p>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="font-black text-blue-600 uppercase">% Ausencia:</span> 
                    <p class="font-medium">Faltas reales sobre el total de asistencias posibles (alumnos × días hábiles).</p>
                </div>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-4 lg:grid-cols-<?php echo min(5, count($resumen_mensual_global) + 1); ?> gap-4">
            <?php foreach ($resumen_mensual_global as $rm): 
                $nombre_mes = $meses_es[$rm['mes_num']] ?? $rm['mes_abbr'];
                $total_alumnos = $rm['total_alumnos_activos'];
                
                // Faltas Reales del mes
                $fr_data = $faltas_por_mes[$rm['mes_key']] ?? null;
                $total_fr = $fr_data ? $fr_data['total_faltas_reales'] : 0;
                
                // Cálculos adicionales
                $promedio_fr = $total_alumnos > 0 ? $total_fr / $total_alumnos : 0;
                $dias_habiles = $rm['dias_con_clase']; 
                $total_posible = $total_alumnos * $dias_habiles;
                $pct_ausencia = $total_posible > 0 ? ($total_fr / $total_posible) * 100 : 0;
                
                $color_border = $rm['pct_asistencia'] >= 85 ? 'border-emerald-200' : ($rm['pct_asistencia'] >= 75 ? 'border-amber-200' : 'border-rose-200');
                $color_text = $rm['pct_asistencia'] >= 85 ? 'text-emerald-600' : ($rm['pct_asistencia'] >= 75 ? 'text-amber-600' : 'text-rose-600');
            ?>
                <div class="p-5 rounded-2xl border-2 <?php echo $color_border; ?> bg-white faltas-card hover:shadow-md transition-all group"
                     data-total="<?php echo $total_fr; ?>"
                     data-promedio="<?php echo number_format($promedio_fr, 1); ?>"
                     data-porcentaje="<?php echo number_format($pct_ausencia, 1); ?>">
                    <p class="text-[10px] font-black text-slate-400 uppercase mb-4 text-center tracking-widest group-hover:text-slate-600 transition-colors"><?php echo $nombre_mes; ?></p>
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-center flex-1">
                            <p class="text-[9px] font-black text-slate-300 mb-1">ASISTENCIA</p>
                            <span class="text-xl font-black <?php echo $color_text; ?> leading-none"><?php echo number_format($rm['pct_asistencia'], 1); ?>%</span>
                        </div>
                        <div class="w-px h-10 bg-slate-100"></div>
                        <div class="text-center flex-1">
                            <p class="text-[9px] font-black text-slate-300 mb-1 faltas-label uppercase">Faltas</p>
                            <span class="text-xl font-black text-rose-600 leading-none faltas-value"><?php echo number_format($total_fr); ?></span>
                            <span class="faltas-suffix text-[8px] font-bold text-slate-400 block mt-1 uppercase">reales</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Tarjeta Promedio del Rango -->
            <div class="p-5 rounded-2xl bg-slate-900 border-2 border-slate-900 flex flex-col justify-center">
                <div class="space-y-4">
                    <div class="text-center">
                        <p class="text-[9px] font-black text-slate-400 mb-1">MEDIA PERIODO</p>
                        <span class="text-2xl font-black text-white leading-none"><?php echo number_format($promedio_global, 1); ?>%</span>
                    </div>
                    <div class="h-px bg-slate-800"></div>
                    <div class="flex items-center justify-around">
                        <div class="text-center">
                            <p class="text-[8px] font-black text-slate-500 mb-0.5">ALUMNOS</p>
                            <span class="text-sm font-black text-blue-400 leading-none"><?php echo $max_alumnos; ?></span>
                        </div>
                        <div class="text-center">
                            <p class="text-[8px] font-black text-slate-500 mb-0.5">FALTAS T.</p>
                            <span class="text-sm font-black text-rose-400 leading-none"><?php echo number_format($total_faltas_reales); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8 h-[350px] flex flex-col">
            <h3 class="font-black text-slate-900 text-sm uppercase tracking-widest mb-6 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                Histórico de Asistencia
            </h3>
            <div class="flex-1 relative">
                <canvas id="chartTendencia"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8 h-[350px] flex flex-col">
            <h3 class="font-black text-slate-900 text-sm uppercase tracking-widest mb-6 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                Ranking por Grupo
            </h3>
            <div class="flex-1 relative">
                <canvas id="chartGrupos"></canvas>
            </div>
        </div>
    </div>

    <!-- Matriz de Cierre (Lindo Style) -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="font-black text-slate-900 text-lg uppercase tracking-tight">Desglose de Aprovechamiento</h3>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Matriz mensual consolidada</p>
            </div>
            <button onclick="exportCSV()" class="bg-slate-900 hover:bg-slate-800 text-white px-5 py-3 rounded-xl text-xs font-black flex items-center gap-2 transition-all shadow-lg active:scale-95">
                <span class="material-symbols-outlined text-[18px]">table_view</span>
                EXPORTAR CSV
            </button>
        </div>
        <div class="overflow-x-auto p-2">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                        <th class="py-5 px-6 text-left sticky left-0 bg-white border-r border-slate-50">Grupo</th>
                        <?php foreach ($meses_matrix as $mk):
                            $parts = explode('-', $mk);
                            $mes_nombre = $meses_es[$parts[1]] ?? $parts[1];
                        ?>
                        <th class="px-4 py-5 text-center"><?php echo $mes_nombre; ?> <span class="opacity-30"><?php echo $parts[0]; ?></span></th>
                        <?php endforeach; ?>
                        <th class="px-4 py-5 font-black text-rose-500 border-l border-slate-50">Total Faltas</th>
                        <th class="px-4 py-5 text-center bg-slate-50/50">Media %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($grupos_set as $gn):
                        $suma_pct = 0; $cant = 0; $faltas_grupo = 0;
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-6 py-5 sticky left-0 bg-white group-hover:bg-slate-50 transition-colors border-r border-slate-50">
                            <span class="font-black text-slate-800 tracking-tight"><?php echo htmlspecialchars($gn); ?></span>
                        </td>
                        <?php foreach ($meses_matrix as $mk):
                            $cell = $matrix[$mk][$gn] ?? null;
                            if ($cell) {
                                $pct = (float)$cell['pct_asistencia'];
                                $fr = (int)$cell['faltas_reales'];
                                $suma_pct += $pct; $cant++; $faltas_grupo += $fr;
                                $pill = $pct >= 85 ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : ($pct >= 70 ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-rose-50 text-rose-700 border-rose-100');
                            }
                        ?>
                        <td class="px-4 py-5 text-center">
                            <?php if ($cell): ?>
                            <div class="flex flex-col items-center">
                                <span class="<?php echo $pill; ?> px-2 py-0.5 rounded-lg text-[10px] font-black border">
                                    <?php echo number_format($pct, 1); ?>%
                                </span>
                                <span class="text-[9px] font-bold text-slate-400 mt-1"><?php echo $fr; ?> faltas</span>
                            </div>
                            <?php else: ?>
                            <span class="text-slate-200">...</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="px-4 py-5 text-center border-l border-slate-50">
                            <span class="text-rose-600 font-black tracking-tight"><?php echo $faltas_grupo; ?></span>
                        </td>
                        <td class="px-4 py-5 text-center bg-slate-50/30">
                            <?php
                            $prom = $cant > 0 ? round($suma_pct / $cant, 1) : null;
                            if ($prom !== null) {
                                $cls = $prom >= 85 ? 'text-emerald-600' : ($prom >= 70 ? 'text-amber-600' : 'text-rose-600');
                                echo "<span class='font-black {$cls}'>{$prom}%</span>";
                            } else echo '<span class="text-slate-200">—</span>';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Critical Students (Grid style) -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8">
        <h3 class="font-black text-slate-900 text-lg uppercase tracking-tight mb-8 flex items-center gap-3">
            <span class="material-symbols-outlined text-rose-500">warning</span>
            Monitor de Ausentismo Crítico
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($top_ausentismo as $idx => $st): ?>
            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 group hover:border-blue-200 transition-all">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-[10px] font-black text-slate-300 uppercase">Top #<?php echo $idx + 1; ?></span>
                    <span class="bg-rose-100 text-rose-600 px-2 py-0.5 rounded-md text-[9px] font-black"><?php echo $st['total_ausencias_clase']; ?> FALTAS</span>
                </div>
                <p class="font-black text-slate-800 text-sm leading-tight group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($st['alumno_nombre']); ?></p>
                <p class="text-[10px] font-bold text-slate-400 uppercase mt-1"><?php echo htmlspecialchars($st['grupo_mas_comun']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart Tendencia
    new Chart(document.getElementById('chartTendencia').getContext('2d'), {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(fn($m) => '"'.$m['mes_abbr'].'"', $resumen_mensual_grafico)); ?>],
            datasets: [{
                label: 'Asistencia %',
                data: [<?php echo implode(',', array_map(fn($m) => number_format($m['po_asistencia'], 1), $resumen_mensual_grafico)); ?>],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 100, 235, 0.05)',
                fill: true,
                tension: 0.4,
                borderWidth: 4,
                pointRadius: 5,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#2563eb',
                pointBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                y: { min: 20, max: 100, grid: { color: '#f1f5f9', borderDash:[5,5] }, ticks: { font: { weight: 'bold', size: 10 }, color: '#94a3b8', callback: v => v+'%' } },
                x: { grid: { display: false }, ticks: { font: { weight: 'bold' }, color: '#64748b' } }
            }
        }
    });

    // Chart Grupos
    const gData = [<?php echo implode(',', array_map(fn($g) => number_format($g['po_asistencia'], 1), $resumen_grupos)); ?>];
    new Chart(document.getElementById('chartGrupos').getContext('2d'), {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(fn($g) => '"'.$g['grupo_nombre'].'"', $resumen_grupos)); ?>],
            datasets: [{ 
                data: gData, 
                backgroundColor: gData.map(v => v >= 85 ? '#10b981' : (v >= 70 ? '#f59e0b' : '#ef4444')),
                borderRadius: 8,
                barThickness: 12
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                x: { min: 40, max: 100, grid: { color: '#f1f5f9' }, ticks: { font: { weight: 'bold', size: 10 }, color: '#94a3b8' } },
                y: { grid: { display: false }, ticks: { font: { weight: 'bold', size: 11 }, color: '#1e293b' } }
            }
        }
    });
});

function exportCSV() {
    window.location.href = "index.php?v=reporte_mensual&export=1&inicio=<?php echo $filtro_inicio; ?>&fin=<?php echo $filtro_fin; ?>";
}

function switchFaltasMode(mode) {
    document.querySelectorAll('.faltas-mode-btn').forEach(b => b.classList.remove('active-mode'));
    document.getElementById('btn-mode-' + mode).classList.add('active-mode');
    
    document.querySelectorAll('.faltas-card').forEach(card => {
        const val = card.querySelector('.faltas-value');
        const label = card.querySelector('.faltas-label');
        const suffix = card.querySelector('.faltas-suffix');
        
        if (mode === 'total') {
            val.textContent = parseInt(card.dataset.total).toLocaleString();
            label.textContent = 'FALTAS';
            suffix.textContent = 'reales';
        } else if (mode === 'promedio') {
            val.textContent = card.dataset.promedio;
            label.textContent = 'ALUMNO';
            suffix.textContent = 'promedio';
        } else {
            val.textContent = card.dataset.porcentaje + '%';
            label.textContent = 'AUSENCIA';
            suffix.textContent = 'del periodo';
        }
    });
}
</script>

<style>
.faltas-mode-btn { color: #94a3b8; background: transparent; }
.faltas-mode-btn.active-mode { color: white; background: #2563eb; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
.faltas-mode-btn:hover:not(.active-mode) { color: #475569; background: #f8fafc; }
</style>
