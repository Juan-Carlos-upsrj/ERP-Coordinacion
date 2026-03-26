<?php
/**
 * app/views/pages/mapa_calor.php
 * Visualización tipo Heatmap (calendario) para el rendimiento de asistencia.
 */

require_once 'app/models/DashboardModel.php';
require_once 'app/core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Handle month filter
$mes_actual = $_GET['mes'] ?? date('Y-m');
$timestamp_mes = strtotime($mes_actual . '-01');
$dias_en_mes = date('t', $timestamp_mes);
$primer_dia_semana = date('w', $timestamp_mes); // 0 (Dom) to 6 (Sab)

$mes_anterior = date('Y-m', strtotime('-1 month', $timestamp_mes));
$mes_siguiente = date('Y-m', strtotime('+1 month', $timestamp_mes));

// Fetch data
$datos_heatmap = DashboardModel::getMapaCalor($pdo, $mes_actual);
// Transform for easy access by date
$datos_dict = [];
foreach ($datos_heatmap as $row) {
    $datos_dict[$row['fecha']] = $row;
}
?>

<div class="mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-black text-gray-800 tracking-tight">Mapa de Calor</h1>
        <p class="text-gray-500 mt-1">Patrones de asistencia organizados por calendario mensual</p>
    </div>
    
    <div class="flex items-center bg-white rounded-xl shadow-sm border border-gray-200 p-1">
        <a href="index.php?v=mapa_calor&mes=<?php echo $mes_anterior; ?>" class="px-3 py-2 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors flex items-center">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
        </a>
        <div class="px-4 py-2 font-bold text-brand-600 uppercase text-sm w-36 text-center">
            <?php 
                $meses_es = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                echo $meses_es[(int)date('n', $timestamp_mes)] . ' ' . date('Y', $timestamp_mes);
            ?>
        </div>
        <a href="index.php?v=mapa_calor&mes=<?php echo $mes_siguiente; ?>" class="px-3 py-2 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors flex items-center">
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
    </div>
</div>

<div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 overflow-hidden">
    
    <!-- Legend -->
    <div class="flex flex-wrap gap-4 text-xs font-bold text-gray-500 mb-8 border-b border-gray-100 pb-4">
        <div class="flex items-center gap-2"><span class="w-4 h-4 rounded-sm bg-green-500"></span> Excelencia (95%+)</div>
        <div class="flex items-center gap-2"><span class="w-4 h-4 rounded-sm bg-green-400"></span> Bueno (80-94%)</div>
        <div class="flex items-center gap-2"><span class="w-4 h-4 rounded-sm bg-yellow-400"></span> Aceptable (70-79%)</div>
        <div class="flex items-center gap-2"><span class="w-4 h-4 rounded-sm bg-orange-500"></span> Regular (60-69%)</div>
        <div class="flex items-center gap-2"><span class="w-4 h-4 rounded-sm bg-red-600"></span> Crítico (< 60%)</div>
        <div class="flex items-center gap-2"><span class="w-4 h-4 rounded-sm bg-gray-100 border border-gray-200"></span> Sin Registro</div>
    </div>

    <!-- Grid Header -->
    <div class="grid grid-cols-7 gap-2 mb-2 text-center text-xs font-black uppercase tracking-wider text-gray-400">
        <?php foreach (['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'] as $dia): ?>
            <div><?php echo $dia; ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Calendar Grid -->
    <div class="grid grid-cols-7 gap-2 auto-rows-fr">
        <?php
        // Espacios vacíos
        for ($i = 0; $i < $primer_dia_semana; $i++) {
            echo '<div class="h-28 bg-transparent"></div>';
        }

        // Días
        for ($d = 1; $d <= $dias_en_mes; $d++) {
            $fecha_iter = date('Y-m-d', strtotime($mes_actual . '-' . sprintf('%02d', $d)));
            $info = $datos_dict[$fecha_iter] ?? null;
            
            $bg_class = 'bg-gray-50 border-gray-200 text-gray-400';
            $porcentaje = 0;
            $txt_opacity = 'opacity-50';

            if ($info && $info['total_clases'] > 0) {
                $porcentaje = ($info['asistencias'] / $info['total_clases']) * 100;
                $txt_opacity = 'text-white';
                if ($porcentaje >= 95) $bg_class = 'bg-green-500 border-green-600 text-white';
                elseif ($porcentaje >= 80) $bg_class = 'bg-green-400 border-green-500 text-white';
                elseif ($porcentaje >= 70) $bg_class = 'bg-yellow-400 border-yellow-500 text-white';
                elseif ($porcentaje >= 60) $bg_class = 'bg-orange-500 border-orange-600 text-white';
                else $bg_class = 'bg-red-600 border-red-700 text-white';
            }

            if ($fecha_iter === date('Y-m-d')) {
                $bg_class .= ' ring-4 ring-brand-300 ring-offset-2 z-10';
            }

            echo '
            <div class="relative group h-28 rounded-xl border border-b-4 flex flex-col justify-between p-3 transition-transform hover:scale-105 hover:z-20 hover:shadow-lg ' . $bg_class . '">
                <span class="text-sm font-black ' . $txt_opacity . '">' . $d . '</span>';
            
            if ($info && $info['total_clases'] > 0) {
                echo '
                <div class="text-right">
                    <span class="block text-2xl font-black leading-none drop-shadow-sm">' . number_format($porcentaje, 0) . '%</span>
                    <span class="block text-[10px] font-bold opacity-80 uppercase mt-1">' . $info['total_clases'] . ' regs</span>
                </div>';
            }

            echo '</div>';
        }
        ?>
    </div>
</div>
