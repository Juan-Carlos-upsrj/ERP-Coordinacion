<?php
/**
 * app/views/pages/reporte_general.php
 * Análisis completo de asistencia con filtros de fecha, gráficos y tablas.
 */

require_once 'app/models/DashboardModel.php';
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
$filtro_inicio = $_GET['fecha_inicio'] ?? $inicio_def;
$filtro_fin = $_GET['fecha_fin'] ?? $fin_def;

// Datos
$resumen_mensual = DashboardModel::getResumenMensual($pdo, $filtro_inicio, $filtro_fin);
$resumen_grupos = DashboardModel::getResumenGrupos($pdo, $filtro_inicio, $filtro_fin);
$top_ausentismo = DashboardModel::getTopAusentismo($pdo, $filtro_inicio, $filtro_fin, 10);

// Promedio global
$suma = 0; $cnt = count($resumen_grupos);
foreach ($resumen_grupos as $g) $suma += $g['po_asistencia'];
$promedio_global = $cnt > 0 ? $suma / $cnt : 0;
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-6">

    <!-- Header + Filtros -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-gray-800 tracking-tight flex items-center gap-2">
                <span class="material-symbols-outlined text-brand-500">analytics</span>
                Análisis de Asistencia
            </h1>
            <p class="text-xs text-gray-500 mt-1 uppercase tracking-widest font-bold">Estadísticas y Tendencias del Periodo</p>
        </div>
        
        <form method="GET" action="index.php" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="v" value="reporte_general">
            <div class="flex items-center gap-2 bg-gray-50 p-1.5 rounded-xl border border-gray-200">
                <div class="flex flex-col">
                    <label class="text-[9px] font-bold text-gray-400 uppercase px-2">Inicio</label>
                    <input type="date" name="fecha_inicio" value="<?php echo $filtro_inicio; ?>" class="bg-transparent border-none text-xs focus:ring-0 text-gray-700 font-bold">
                </div>
                <div class="w-px h-6 bg-gray-300"></div>
                <div class="flex flex-col">
                    <label class="text-[9px] font-bold text-gray-400 uppercase px-2">Fin</label>
                    <input type="date" name="fecha_fin" value="<?php echo $filtro_fin; ?>" class="bg-transparent border-none text-xs focus:ring-0 text-gray-700 font-bold">
                </div>
            </div>
            <button type="submit" class="bg-brand-600 hover:bg-brand-500 text-white px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 shadow-md shadow-brand-500/20 transition-all">
                <span class="material-symbols-outlined text-sm">filter_list</span>
                FILTRAR
            </button>
        </form>
    </div>

    <!-- Tarjetas Resumen -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Asistencia Promedio</p>
            <p class="text-4xl font-black <?php echo $promedio_global >= 85 ? 'text-green-600' : ($promedio_global >= 70 ? 'text-amber-600' : 'text-red-600'); ?>"><?php echo number_format($promedio_global, 1); ?>%</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Grupos Analizados</p>
            <p class="text-4xl font-black text-brand-600"><?php echo $cnt; ?></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Top Ausentes</p>
            <p class="text-4xl font-black text-red-500"><?php echo count($top_ausentismo); ?></p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tendencia Mensual -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand-500 text-lg">show_chart</span>
                Tendencia Mensual
            </h3>
            <div class="h-64 relative">
                <canvas id="chartTendenciaReporte"></canvas>
            </div>
        </div>

        <!-- Rendimiento por Grupo (Horizontal Bars) -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand-500 text-lg">bar_chart</span>
                Rendimiento por Grupo
            </h3>
            <div class="h-64 relative">
                <canvas id="chartGruposReporte"></canvas>
            </div>
        </div>
    </div>

    <!-- Tabla Detallada de Grupos -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand-500 text-lg">table_chart</span>
                Desglose por Grupo
            </h3>
            <a href="index.php?v=exportar_riesgo" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 transition-all shadow-md shadow-green-500/20">
                <span class="material-symbols-outlined text-sm">file_download</span>
                DESCARGAR CSV
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 bg-gray-50/50">
                        <th class="py-3 px-6 text-left">Grupo</th>
                        <th class="py-3 px-6 text-center">Asistencia (%)</th>
                        <th class="py-3 px-6 text-center">Estado</th>
                        <th class="py-3 px-6 text-right">Indicador</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($resumen_grupos as $g): 
                        $pct = (float)$g['po_asistencia'];
                        if ($pct >= 90) { $badge = 'bg-green-100 text-green-700 border-green-200'; $label = 'Excelente'; }
                        elseif ($pct >= 80) { $badge = 'bg-blue-100 text-blue-700 border-blue-200'; $label = 'Bueno'; }
                        elseif ($pct >= 70) { $badge = 'bg-yellow-100 text-yellow-700 border-yellow-200'; $label = 'Aceptable'; }
                        else { $badge = 'bg-red-100 text-red-700 border-red-200'; $label = 'Crítico'; }
                    ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="py-3 px-6 font-bold text-gray-800"><?php echo htmlspecialchars($g['grupo_nombre']); ?></td>
                        <td class="py-3 px-6 text-center">
                            <span class="text-lg font-black <?php echo $pct >= 85 ? 'text-green-600' : ($pct >= 70 ? 'text-amber-600' : 'text-red-600'); ?>"><?php echo number_format($pct, 1); ?>%</span>
                        </td>
                        <td class="py-3 px-6 text-center">
                            <span class="inline-flex px-3 py-1 rounded-full text-xs font-black border <?php echo $badge; ?>"><?php echo $label; ?></span>
                        </td>
                        <td class="py-3 px-6 text-right">
                            <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden ml-auto">
                                <div class="h-full rounded-full <?php echo $pct >= 85 ? 'bg-green-500' : ($pct >= 70 ? 'bg-amber-400' : 'bg-red-500'); ?>" style="width: <?php echo min($pct, 100); ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Ausentismo -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-red-500 text-lg">warning</span>
            Top 10 Alumnos con Mayor Ausentismo
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php foreach ($top_ausentismo as $idx => $st): ?>
            <div class="flex items-center justify-between p-4 rounded-xl bg-red-50/30 border border-red-100 hover:bg-red-50 transition-colors">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xs font-black border border-red-200">#<?php echo $idx + 1; ?></span>
                    <div>
                        <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($st['alumno_nombre']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($st['grupo_mas_comun']); ?></p>
                    </div>
                </div>
                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-black border border-red-200"><?php echo $st['total_ausencias_clase']; ?> faltas</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tendencia
    new Chart(document.getElementById('chartTendenciaReporte').getContext('2d'), {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(fn($m) => '"'.$m['mes_abbr'].'"', $resumen_mensual)); ?>],
            datasets: [{
                label: 'Asistencia %',
                data: [<?php echo implode(',', array_map(fn($m) => number_format($m['po_asistencia'], 1), $resumen_mensual)); ?>],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,0.08)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
                pointRadius: 5,
                pointBackgroundColor: '#fff',
                pointBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { min: 50, max: 100, ticks: { callback: v => v+'%' } }, x: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });

    // Grupos
    const gruposData = [<?php echo implode(',', array_map(fn($g) => number_format($g['po_asistencia'], 1), $resumen_grupos)); ?>];
    const gruposLabels = [<?php echo implode(',', array_map(fn($g) => '"'.$g['grupo_nombre'].'"', $resumen_grupos)); ?>];
    const gruposColors = gruposData.map(v => v >= 85 ? '#10b981' : (v >= 70 ? '#f59e0b' : '#ef4444'));
    
    new Chart(document.getElementById('chartGruposReporte').getContext('2d'), {
        type: 'bar',
        data: {
            labels: gruposLabels,
            datasets: [{ data: gruposData, backgroundColor: gruposColors, borderRadius: 6, barThickness: 20 }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { min: 50, max: 100, ticks: { callback: v => v+'%' } }, y: { grid: { display: false } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
