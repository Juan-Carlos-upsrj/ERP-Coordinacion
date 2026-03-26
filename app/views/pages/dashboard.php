<?php
/**
 * app/views/pages/dashboard.php
 * Dashboard principal rediseñado con estética del portal de inspiración (Modern Slate).
 */

require_once 'app/models/DashboardModel.php';
require_once 'app/core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla] ?? $CARRERAS['IAEV'];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Métricas base
$metricas = DashboardModel::getResumenCarrera($pdo);

// Rango cuatri
$mes = (int)date('n');
if ($mes <= 4) { $in = date('Y-01-01'); $fn = date('Y-04-30'); $label_q = 'Enero - Abril'; }
elseif ($mes <= 8) { $in = date('Y-05-01'); $fn = date('Y-08-31'); $label_q = 'Mayo - Agosto'; }
else { $in = date('Y-09-01'); $fn = date('Y-12-31'); $label_q = 'Septiembre - Diciembre'; }

$datos_mensuales = DashboardModel::getResumenMensual($pdo, $in, $fn);
$datos_semanales = DashboardModel::getResumenSemanal($pdo, 4);
$top_ausentismo = DashboardModel::getTopAusentismo($pdo, $in, $fn, 6);

$pct_hoy = $metricas['asistencia_hoy'] ?? 0;
$pct_hist = $metricas['promedio_historico'] ?? 0;
?>

<!-- Contenedor Principal -->
<div class="relative">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Resumen General</h1>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-400 text-sm">event</span>
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider"><?php echo $label_q; ?> <?php echo date('Y'); ?></span>
            </div>
        </div>
    </div>

    <!-- KPI Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        <!-- Columna 1: Estudiantes y Docentes -->
        <div class="flex flex-col gap-6">
            <!-- Total Estudiantes -->
            <a href="index.php?v=lista_alumnos" class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm group hover:shadow-md transition-all flex-1 block">
                <div class="flex justify-between items-start mb-2">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest leading-none">Total Estudiantes</p>
                    <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px]">groups</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-3xl font-black text-slate-900"><?php echo number_format($metricas['total_alumnos']); ?></p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">Matriculados</p>
                </div>
            </a>

            <!-- Profesores -->
            <a href="index.php?v=lista_profesores" class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm group hover:shadow-md transition-all flex-1 block">
                <div class="flex justify-between items-start mb-2">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest leading-none">Personal Docente</p>
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px]">badge</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-3xl font-black text-slate-900"><?php echo number_format($metricas['profesores']); ?></p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">Activos</p>
                </div>
            </a>
        </div>

        <!-- Columna 2: Riesgo y Asistencia -->
        <div class="flex flex-col gap-6">
            <!-- Riesgo Crítico -->
            <a href="index.php?v=top_remediales" class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm group hover:shadow-md transition-all border-b-4 border-b-rose-500 flex-1 block">
                <div class="flex justify-between items-start mb-2">
                    <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest leading-none">Riesgo Crítico</p>
                    <div class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px]">warning</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <p class="text-3xl font-black text-slate-900"><?php echo number_format($metricas['en_riesgo']); ?></p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">Alumnos</p>
                </div>
            </a>

            <!-- Asistencia Hoy -->
            <a href="index.php?v=asistencia_hoy" class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm group hover:shadow-md transition-all flex-1 block">
                <div class="flex justify-between items-start mb-2">
                    <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest leading-none">Asistencia Hoy</p>
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <p class="text-3xl font-black text-slate-900"><?php echo round($pct_hoy); ?><span class="text-lg opacity-20">%</span></p>
                    <div class="flex-1">
                        <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500" style="width: <?php echo $pct_hoy; ?>%"></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Columna 3: Sugerencias de Desarrollo -->
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col items-center justify-center text-center transition-all hover:shadow-lg border-t-4 border-t-blue-600">
            <div class="bg-blue-50 p-4 rounded-full mb-3 group-hover:scale-110 transition-transform">
                <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <h3 class="text-base font-black text-slate-900 uppercase tracking-tight">
                Sugerencias
            </h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1 mb-4">
                Buzón Institucional
            </p>
            <a href="index.php?v=sugerencias" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black py-2.5 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2 uppercase tracking-tighter text-[10px]">
                Ver Todas
                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </div>

        <!-- Columna 4: Gestor de Horarios -->
        <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-6 shadow-lg shadow-blue-500/20 flex flex-col items-center justify-center text-center text-white transition-all hover:shadow-xl hover:-translate-y-1">
            <div class="bg-white/20 p-4 rounded-full mb-3">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3 class="font-black text-base uppercase tracking-tight">
                Horarios
            </h3>
            <p class="text-[10px] font-bold text-white/70 uppercase tracking-widest mt-1 mb-4">
                Configuración
            </p>
            <a href="index.php?v=horarios"
               class="w-full bg-white/20 hover:bg-white/30 transition-colors text-white font-black py-2.5 rounded-xl flex items-center justify-center gap-2 uppercase tracking-tighter text-[10px]">
                Configurar
                <span class="material-symbols-outlined text-[14px]">settings</span>
            </a>
        </div>

    </div>

    <!-- Main Section: Charts + Side Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Análisis de Asistencia (Widget Senior) -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8 flex flex-col h-[450px]">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-sm font-extrabold text-slate-800 tracking-tight uppercase">
                    ANÁLISIS DE ASISTENCIA
                </h3>
                
                <!-- Pill Selector -->
                <div class="flex bg-slate-50 p-1 rounded-full border border-slate-100" id="pillSelector">
                    <button
                        onclick="switchView('semanal')"
                        id="btnSemanal"
                        class="px-4 py-1.5 text-xs font-bold transition-all duration-200 rounded-full text-slate-500 hover:text-slate-700"
                    >
                        Semanal
                    </button>
                    <button
                        onclick="switchView('cuatrimestral')"
                        id="btnCuatrimestral"
                        class="px-4 py-1.5 text-xs font-bold transition-all duration-200 rounded-full bg-blue-600 text-white shadow-sm"
                    >
                        Cuatrimestral
                    </button>
                </div>
            </div>
            
            <div class="flex-1 relative">
                <canvas id="chartTendencia"></canvas>
            </div>
        </div>

        <!-- Middle: Top Ausentismo -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8 flex flex-col h-[450px]">
             <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-2">
                    <h3 class="font-black text-slate-900 text-base uppercase tracking-tight">Top Faltas</h3>
                </div>
                <a href="index.php?v=reporte_alumnos" class="text-blue-600 font-bold text-[10px] hover:underline uppercase decoration-2 underline-offset-4">Ver</a>
            </div>

            <div class="space-y-3 overflow-y-auto pr-2 flex-1 scrollbar-thin scrollbar-thumb-slate-200">
                <?php foreach ($top_ausentismo as $idx => $st): ?>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 group hover:bg-white hover:border-blue-200 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-500 font-black text-[10px] flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <?php echo $idx + 1; ?>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black text-slate-800 leading-none mb-1 group-hover:text-blue-600 truncate"><?php echo htmlspecialchars($st['alumno_nombre']); ?></p>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?php echo htmlspecialchars($st['grupo_mas_comun']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Rendimiento por Grupo -->
        <div class="bg-white rounded-2xl p-8 border border-slate-100 shadow-sm flex flex-col h-[450px] overflow-hidden">
            <header class="flex justify-between items-center mb-8 shrink-0">
                <h3 class="text-sm font-extrabold text-slate-800 tracking-tight uppercase">
                    Rendimiento
                </h3>
                <span class="material-symbols-outlined text-slate-400 text-sm">equalizer</span>
            </header>

            <div class="flex-1 overflow-y-auto pr-2 scrollbar-thin scrollbar-thumb-slate-200 space-y-5">
                <?php
                $grupos_perf = DashboardModel::getResumenGrupos($pdo, $in, $fn);
                foreach ($grupos_perf as $idx => $gp):
                    $perf = (float)$gp['po_asistencia'];
                    $color_bar = $perf >= 85 ? 'bg-blue-600' : ($perf >= 70 ? 'bg-indigo-400' : 'bg-rose-400');
                ?>
                <div class="flex flex-col gap-2 group">
                    <div class="flex justify-between items-end">
                        <span class="text-xs font-bold text-slate-700 tracking-tight group-hover:text-blue-600 transition-colors truncate">
                            <?php echo htmlspecialchars($gp['grupo_nombre']); ?>
                        </span>
                        <span class="text-xs font-extrabold text-slate-900">
                            <?php echo number_format($perf, 0); ?>%
                        </span>
                    </div>
                    <div class="h-1.5 w-full bg-slate-50 rounded-full overflow-hidden relative">
                        <div class="h-full rounded-full <?php echo $color_bar; ?> transition-all duration-1000" 
                             style="width: <?php echo $perf; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartTendencia').getContext('2d');
    
    // Datos inyectados desde PHP
    const dataMensual = {
        labels: [<?php echo implode(',', array_map(fn($m) => '"'.$m['mes_abbr'].'"', $datos_mensuales)); ?>],
        values: [<?php echo implode(',', array_map(fn($m) => round($m['po_asistencia'], 1), $datos_mensuales)); ?>]
    };
    
    const dataSemanal = {
        labels: [<?php echo implode(',', array_map(fn($m) => '"'.$m['semana_label'].'"', $datos_semanales)); ?>],
        values: [<?php echo implode(',', array_map(fn($m) => round($m['po_asistencia'], 1), $datos_semanales)); ?>]
    };

    let chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dataMensual.labels,
            datasets: [{
                label: 'Asistencia %',
                data: dataMensual.values,
                borderColor: '#2563eb',
                borderWidth: 4,
                backgroundColor: 'transparent',
                fill: false,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#1e40af',
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#2563eb',
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                y: {
                    min: 50, max: 100,
                    grid: { color: '#f8fafc', drawBorder: false }, // Muy sutil, solo horizontal
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 11 },
                        stepSize: 10,
                        callback: v => v + '%'
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 11 }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#64748b',
                    titleFont: { weight: 'bold', size: 12 },
                    bodyColor: '#2563eb',
                    bodyFont: { weight: 'extrabold', size: 14 },
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: false,
                    borderColor: '#f1f5f9',
                    borderWidth: 1,
                    shadowBlur: 10,
                    shadowColor: 'rgba(0,0,0,0.1)',
                    callbacks: {
                        title: (items) => items[0].label.toUpperCase(),
                        label: (c) => `  Asistencia: ${c.formattedValue}%`
                    }
                }
            }
        }
    });

    // Función de cambio de vista
    window.switchView = function(view) {
        const isSemanal = view === 'semanal';
        const data = isSemanal ? dataSemanal : dataMensual;
        
        // Actualizar datos
        chartInstance.data.labels = data.labels;
        chartInstance.data.datasets[0].data = data.values;
        chartInstance.update();

        // Actualizar estilos de botones
        const btnSem = document.getElementById('btnSemanal');
        const btnCuatri = document.getElementById('btnCuatrimestral');

        if (isSemanal) {
            btnSem.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
            btnSem.classList.remove('text-slate-500');
            btnCuatri.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
            btnCuatri.classList.add('text-slate-500');
        } else {
            btnCuatri.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
            btnCuatri.classList.remove('text-slate-500');
            btnSem.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
            btnSem.classList.add('text-slate-500');
        }
    };
});
</script>

<style>
/* Custom scrollbar para la lista de ausentismo */
.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); border-radius: 10px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
