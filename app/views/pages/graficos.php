<?php
/**
 * app/views/pages/graficos.php
 * Generador de reportes PDF con vista previa interactiva y DATOS REALES.
 */

require_once 'app/models/DashboardModel.php';
require_once 'app/models/AnomaliasModel.php';
require_once 'app/models/ReportesModel.php';
require_once 'app/core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Datos Reales para Vista Previa
$resumen_carrera = DashboardModel::getResumenCarrera($pdo);
$resumen_grupos = DashboardModel::getResumenGrupos($pdo, date('Y-m-01'), date('Y-m-t'));
// Calcular faltas reales totales del periodo para el preview
$resumen_global = ReportesModel::getResumenMensualGlobal($pdo, date('Y-m-01'), date('Y-m-t'));
$resumen_anomalias = AnomaliasModel::getResumenAnomalias($pdo);
$total_alertas = $resumen_carrera['en_riesgo'] + $resumen_anomalias['listas_un_alumno'] + ($resumen_anomalias['ghost_uploads'] ?? 0);

$faltas_hoy = 0;
foreach($resumen_global as $rg) $faltas_hoy += (int)$rg['total_faltas_reales'];

// Control Docente (Profesores con clases en este periodo)
$sql_profes = "SELECT profesor_nombre, COUNT(*) as total_registros, 
                SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) as faltas
               FROM asistencia_clases 
               WHERE fecha BETWEEN ? AND ?
               GROUP BY profesor_nombre 
               ORDER BY profesor_nombre ASC";
$stmt_profes = $pdo->prepare($sql_profes);
$stmt_profes->execute([date('Y-m-01'), date('Y-m-t')]);
$profesores_activos = $stmt_profes->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="mb-10">
    <h1 class="text-4xl font-black text-gray-800 tracking-tight">Exportación Personalizada</h1>
</div>

<div class="flex flex-col lg:flex-row gap-8 items-start">
    
    <!-- LADO IZQUIERDO: VISTA PREVIA PDF -->
    <div class="flex-1 w-full">
        <div class="bg-gray-200 p-8 rounded-[2.5rem] shadow-inner border border-gray-300 min-h-[700px] flex items-center justify-center relative overflow-hidden">
            
            <!-- Document Paper -->
            <div id="pdf-preview" class="bg-white w-full max-w-[500px] aspect-[1/1.41] shadow-2xl rounded-sm p-10 flex flex-col transition-all duration-500 hover:scale-[1.01]">
                
                <!-- PDF Header -->
                <div class="flex justify-between items-start border-b-4 border-gray-900 pb-5 mb-8">
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 leading-none">REPORTE EJECUTIVO</h2>
                        <p class="text-[10px] font-black text-gray-400 mt-1 uppercase tracking-widest"><?php echo $_SESSION['carrera_activa']; ?> · COORDINACIÓN</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center overflow-hidden">
                        <img src="public/img/logo_<?php echo strtolower($carrera_sigla); ?>.png" class="w-full h-full object-contain p-1 opacity-20">
                    </div>
                </div>

                <!-- PDF Content Slots -->
                <div class="space-y-8 flex-1">
                    
                    <!-- Slot: Resumen -->
                    <div id="slot-resumen" class="transition-all duration-300 opacity-100">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">KPIs de Asistencia</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                                <p class="text-[8px] font-black text-blue-400 uppercase">Asistencia Periodo</p>
                                <p class="text-2xl font-black text-blue-700"><?php echo $resumen_carrera['promedio_historico']; ?>%</p>
                            </div>
                            <div class="bg-rose-50/50 p-4 rounded-xl border border-rose-100">
                                <p class="text-[8px] font-black text-rose-400 uppercase">Faltas Reales (Mes)</p>
                                <p class="text-2xl font-black text-rose-700"><?php echo $faltas_hoy; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Slot: Análisis Mensual -->
                    <div id="slot-mensual" class="transition-all duration-300 opacity-100">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Análisis Mensual Detallado</h4>
                        <div class="grid grid-cols-3 gap-2">
                            <?php foreach(array_slice($resumen_global, 0, 3) as $rm): ?>
                            <div class="p-3 rounded-xl border border-gray-100 bg-gray-50/30 text-center">
                                <p class="text-[8px] font-black text-gray-400 uppercase mb-1"><?php echo $rm['mes_abbr']; ?></p>
                                <p class="text-sm font-black text-gray-800 leading-none"><?php echo number_format($rm['pct_asistencia'], 1); ?>%</p>
                                <p class="text-[7px] font-bold text-rose-400 mt-1"><?php echo $rm['total_faltas_reales']; ?> FALTAS</p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Slot: Detalles -->
                    <div id="slot-detalles" class="transition-all duration-300 opacity-100">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Rendimiento por Grupo</h4>
                        <div class="space-y-1">
                            <?php foreach(array_slice($resumen_grupos, 0, 6) as $g): ?>
                            <div class="flex items-center justify-between py-1 border-b border-gray-50">
                                <span class="text-[11px] font-bold text-gray-600"><?php echo $g['grupo_nombre']; ?></span>
                                <span class="text-[11px] font-black text-gray-800"><?php echo $g['po_asistencia']; ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Slot: Alertas -->
                    <div id="slot-alertas" class="transition-all duration-300 opacity-100">
                        <h4 class="text-[10px] font-black text-rose-500 uppercase tracking-widest mb-3">Monitor de Riesgo y Anomalías</h4>
                        <div class="p-4 bg-rose-50/50 rounded-2xl border border-rose-100 border-dashed space-y-2">
                            <?php if ($resumen_carrera['en_riesgo'] > 0): ?>
                                <p class="text-[10px] font-bold text-rose-800 flex items-center gap-1">
                                    <span class="w-1 h-1 rounded-full bg-rose-600"></span>
                                    Alumnos en Riesgo Crítico: <span class="font-black"><?php echo $resumen_carrera['en_riesgo']; ?></span>
                                </p>
                            <?php endif; ?>
                            <?php if ($resumen_anomalias['listas_un_alumno'] > 0): ?>
                                <p class="text-[10px] font-bold text-rose-800 flex items-center gap-1">
                                    <span class="w-1 h-1 rounded-full bg-rose-600"></span>
                                    Listas con un solo alumno: <span class="font-black"><?php echo $resumen_anomalias['listas_un_alumno']; ?></span>
                                </p>
                            <?php endif; ?>
                            <?php if (($resumen_anomalias['ghost_uploads'] ?? 0) > 0): ?>
                                <p class="text-[10px] font-bold text-rose-800 flex items-center gap-1">
                                    <span class="w-1 h-1 rounded-full bg-rose-600"></span>
                                    Cargas sin datos (Ghost): <span class="font-black"><?php echo $resumen_anomalias['ghost_uploads']; ?></span>
                                </p>
                            <?php endif; ?>
                            <?php if ($total_alertas == 0): ?>
                                <p class="text-[10px] font-bold text-emerald-600">✓ No se detectaron anomalías en el sistema.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Slot: Docente -->
                    <div id="slot-docente" class="transition-all duration-300 opacity-0 hidden">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Resumen de Actividad Docente</h4>
                        <div class="grid grid-cols-1 gap-2 text-[10px]">
                            <?php foreach(array_slice($profesores_activos, 0, 3) as $p): ?>
                            <div class="flex justify-between items-center bg-gray-50 p-2 rounded-lg">
                                <span class="font-bold text-gray-700"><?php echo $p['profesor_nombre']; ?></span>
                                <span class="text-gray-400 font-black">Activo</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <!-- PDF Footer -->
                <div class="mt-8 pt-5 border-t border-gray-100 flex justify-between items-center opacity-30 italic text-[10px] font-bold">
                    <span>Generado el <?php echo date('d/m/Y'); ?></span>
                    <span>Página 1 de 1</span>
                </div>

            </div>

            <!-- Hint -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-[14px]">visibility</span>
                Vista Previa Dinámica
            </div>
        </div>
    </div>

    <!-- LADO DERECHO: CONTROLES -->
    <div class="w-full lg:w-[400px] space-y-6">
        
        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-200 shadow-sm">
            <h2 class="text-xl font-black text-gray-800 mb-6 flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg bg-gray-900 text-white flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">tune</span>
                </span>
                Configurar Reporte
            </h2>

            <!-- Inspirado en el sitio: selección por bloques -->
            <div class="space-y-3">
                <?php
                $item = function($id, $label, $desc, $checked=false) {
                    $checkedAttr = $checked ? 'checked' : '';
                    echo "
                    <label class='flex items-start gap-4 p-4 rounded-[1.5rem] border border-gray-50 hover:bg-gray-50 cursor-pointer transition-all group'>
                        <div class='relative flex items-center justify-center mt-1'>
                            <input type='checkbox' id='{$id}' {$checkedAttr} onchange='updatePreview(\"{$id}\")'
                                   class='peer appearance-none w-5 h-5 rounded-md border-2 border-gray-200 checked:bg-gray-900 checked:border-gray-900 transition-all'>
                            <span class='material-symbols-outlined absolute text-white text-[14px] opacity-0 peer-checked:opacity-100 pointer-events-none'>check</span>
                        </div>
                        <div class='flex-1'>
                            <p class='text-sm font-black text-gray-700 group-hover:text-black transition-colors'>{$label}</p>
                            <p class='text-[10px] font-bold text-gray-400 uppercase tracking-tighter'>{$desc}</p>
                        </div>
                    </label>
                    ";
                };

                $item('check-resumen', 'Resumen Ejecutivo', 'Asistencia % y Faltas Reales', true);
                $item('check-mensual', 'Resumen Mensual', 'Tarjetas de análisis por mes', true);
                $item('check-detalles', 'Listado de Grupos', 'Desglose de rendimiento mensual', true);
                $item('check-alertas', 'Alertas y Anomalías', 'Riesgo y errores detectados', true);
                $item('check-docente', 'Control Docente', 'Estatus de sincronización');
                ?>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 flex flex-col gap-3">
                <button onclick="window.print()" class="w-full bg-brand-600 hover:bg-brand-500 text-white py-4 rounded-2xl font-black text-sm flex items-center justify-center gap-3 transition-all shadow-lg active:scale-95">
                    <span class="material-symbols-outlined">description</span>
                    GENERAR PDF
                </button>
                <div class="text-center text-[9px] font-bold text-gray-400 uppercase tracking-widest">
                    Todos los datos son en tiempo real
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function updatePreview(id) {
    const slotId = id.replace('check-', 'slot-');
    const slot = document.getElementById(slotId);
    const checkbox = document.getElementById(id);
    
    if (!slot) return;

    if (checkbox.checked) {
        slot.classList.remove('hidden');
        setTimeout(() => {
            slot.classList.remove('opacity-0');
            slot.classList.add('opacity-100');
        }, 10);
    } else {
        slot.classList.add('opacity-0');
        slot.classList.remove('opacity-100');
        setTimeout(() => {
            slot.classList.add('hidden');
        }, 300);
    }
}
</script>
