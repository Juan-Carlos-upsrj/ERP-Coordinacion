<?php
/**
 * Panel de Control — dashboard.php
 * Vista resumida de todas las carreras con datos 100% reales de la BD.
 */
require_once 'app/models/LogsModel.php';
require_once 'app/models/AnomaliasModel.php';

$datos_carreras = [];
foreach ($CARRERAS as $sigla => $c) {
    if (!$c['activa']) {
        $datos_carreras[] = [
            'sigla'  => $sigla,
            'nombre' => $c['nombre_largo'],
            'online' => false,
            'color'  => $c['color_hex'],
            'icono'  => $c['icono'],
        ];
        continue;
    }

    try {
        $pdo_c = getConnection($c['db_name'], $c['carrera_id']);

        // — Alumnos únicos con al menos 1 registro en este cuatrimestre
        $stmt = $pdo_c->query("SELECT COUNT(DISTINCT alumno_id) AS total FROM asistencia_clases");
        $total_alumnos = (int)($stmt->fetchColumn() ?: 0);

        // — Porcentaje de asistencia global (Presente + Retardo + Justificado / Total)
        $stmt = $pdo_c->query("
            SELECT
                SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS asist,
                COUNT(*) AS total
            FROM asistencia_clases
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $pct_asistencia = ($row['total'] > 0)
            ? round($row['asist'] / $row['total'] * 100, 1)
            : null;

        // — Anomalías reales: ghost uploads + listas de 1 alumno
        $resumen_anom = AnomaliasModel::getResumenAnomalias($pdo_c);
        $total_anom = (int)($resumen_anom['listas_un_alumno'] ?? 0);

        // — Última sincronización
        $stmt = $pdo_c->query("SELECT MAX(fecha_subida) FROM asistencia_clases");
        $ultima_sync = $stmt->fetchColumn();

        $datos_carreras[] = [
            'sigla'        => $sigla,
            'nombre'       => $c['nombre_largo'],
            'online'       => true,
            'color'        => $c['color_hex'],
            'icono'        => $c['icono'],
            'alumnos'      => $total_alumnos,
            'asistencia'   => $pct_asistencia,
            'anomalias'    => $total_anom,
            'ultima_sync'  => $ultima_sync,
        ];
    } catch (Throwable $e) {
        $datos_carreras[] = [
            'sigla'  => $sigla,
            'nombre' => $c['nombre_largo'],
            'online' => false,
            'color'  => $c['color_hex'],
            'icono'  => $c['icono'],
            'error'  => $e->getMessage(),
        ];
    }
}

// — Actividad reciente REAL: últimas 8 entradas de la carrera activa
$carrera_activa_sigla = $_SESSION['carrera_activa'] ?? array_key_first($CARRERAS);
$carrera_activa_info  = $CARRERAS[$carrera_activa_sigla];
$pdo_activa = getConnection($carrera_activa_info['db_name'], $carrera_activa_info['carrera_id']);
$actividad_reciente = LogsModel::getActividadProfesores($pdo_activa, 30, 8);
?>

<div class="mb-8">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase mb-3 border border-indigo-100 shadow-sm">
        <span class="material-symbols-outlined text-[14px]">monitoring</span>
        Monitoreo Global
    </div>
    <h1 class="text-3xl font-black text-slate-800 tracking-tight">Estado del Sistema</h1>
    <p class="text-slate-500 mt-1 italic">Resumen consolidado en tiempo real de todos los programas educativos activos.</p>
</div>

<!-- Grid de Carreras -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
    <?php foreach ($datos_carreras as $d): ?>
    <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-xl shadow-slate-100/50 overflow-hidden flex flex-col group hover:shadow-2xl hover:shadow-slate-200/40 transition-all duration-500">
        <div class="p-8 flex-1">
            <div class="flex items-center justify-between mb-8">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-500"
                     style="background-color: <?php echo $d['color']; ?>18">
                    <span class="material-symbols-outlined text-[28px]" style="color: <?php echo $d['color']; ?>"><?php echo $d['icono']; ?></span>
                </div>
                <?php if ($d['online']): ?>
                    <span class="bg-green-50 text-green-600 px-3 py-1 rounded-full text-[10px] font-black border border-green-100 flex items-center gap-1.5 shadow-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> ONLINE
                    </span>
                <?php else: ?>
                    <span class="bg-slate-50 text-slate-400 px-3 py-1 rounded-full text-[10px] font-black border border-slate-100">OFFLINE</span>
                <?php endif; ?>
            </div>

            <h3 class="text-2xl font-black text-slate-800 leading-none mb-2"><?php echo htmlspecialchars($d['sigla']); ?></h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-8 italic"><?php echo htmlspecialchars($d['nombre']); ?></p>

            <?php if ($d['online']): ?>
            <div class="space-y-4">

                <!-- Alumnos registrados -->
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Alumnos Registrados</span>
                    <span class="text-lg font-black text-slate-800">
                        <?php echo $d['alumnos'] > 0 ? number_format($d['alumnos']) : '—'; ?>
                    </span>
                </div>

                <!-- Asistencia global -->
                <?php if ($d['asistencia'] !== null): ?>
                <?php
                    $pct = $d['asistencia'];
                    $color_bar = $pct >= 85 ? '#10B981' : ($pct >= 70 ? '#F59E0B' : '#EF4444');
                    $color_text = $pct >= 85 ? 'text-emerald-600' : ($pct >= 70 ? 'text-amber-500' : 'text-red-500');
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Asistencia Global</span>
                        <span class="text-lg font-black <?php echo $color_text; ?>"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: <?php echo min($pct, 100); ?>%; background-color: <?php echo $color_bar; ?>"></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Asistencia Global</span>
                    <span class="text-[10px] font-bold text-slate-300 italic">Sin registros</span>
                </div>
                <?php endif; ?>

                <!-- Anomalías -->
                <?php if ($d['anomalias'] > 0): ?>
                <div class="flex items-center gap-3 p-3 bg-red-50/60 rounded-2xl border border-red-100">
                    <span class="material-symbols-outlined text-red-500 text-[18px]">bug_report</span>
                    <div>
                        <span class="text-[11px] font-black text-red-600 uppercase tracking-tight block"><?php echo $d['anomalias']; ?> listas sospechosas</span>
                        <a href="index.php?v=anomalias&carrera=<?php echo $d['sigla']; ?>" class="text-[10px] text-red-400 hover:underline">Ver detalle →</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="flex items-center gap-2 text-[10px] font-bold text-emerald-600">
                    <span class="material-symbols-outlined text-[16px]">verified</span>
                    Sin anomalías detectadas
                </div>
                <?php endif; ?>

                <!-- Última sync -->
                <?php if (!empty($d['ultima_sync'])): ?>
                <p class="text-[10px] text-slate-300 font-bold uppercase tracking-wider text-right">
                    Última sync: <?php echo date('d/m/Y H:i', strtotime($d['ultima_sync'])); ?>
                </p>
                <?php endif; ?>

            </div>
            <?php elseif (!empty($d['error'])): ?>
            <div class="flex flex-col items-center justify-center py-8 text-red-300">
                <span class="material-symbols-outlined text-4xl mb-2 opacity-50">cloud_off</span>
                <p class="text-[10px] font-black uppercase tracking-widest italic">Error de conexión</p>
                <p class="text-[9px] text-slate-300 mt-1 text-center"><?php echo htmlspecialchars(substr($d['error'], 0, 80)); ?></p>
            </div>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center py-10 text-slate-300">
                <span class="material-symbols-outlined text-5xl mb-2 opacity-30">cloud_off</span>
                <p class="text-[10px] font-black uppercase tracking-widest italic">Sin conexión a DB</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($d['online']): ?>
        <a href="../index.php?cambiar_carrera=<?php echo $d['sigla']; ?>"
           class="bg-slate-50/50 py-5 text-center text-[10px] font-black text-slate-400 hover:bg-slate-900 hover:text-white border-t border-slate-50 transition-all uppercase tracking-widest">
            Ir a Gestión — <?php echo $d['sigla']; ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Actividad Reciente REAL -->
<div class="mt-16 bg-white rounded-[2.5rem] border border-gray-100 shadow-xl shadow-slate-100/50 overflow-hidden">
    <div class="px-10 py-8 border-b border-gray-50 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-black text-slate-800 flex items-center gap-3">
                <span class="material-symbols-outlined text-indigo-500">history</span>
                Actividad Reciente — <?php echo htmlspecialchars($carrera_activa_sigla); ?>
            </h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Últimas sincronizaciones registradas en la carrera activa</p>
        </div>
        <a href="index.php?v=logs" class="bg-indigo-50 text-indigo-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all">Ver Historial Completo</a>
    </div>
    <div class="divide-y divide-gray-50">
        <?php if (empty($actividad_reciente)): ?>
        <div class="px-10 py-16 text-center text-slate-300">
            <span class="material-symbols-outlined text-5xl mb-3 block">inbox</span>
            <p class="text-xs font-black uppercase tracking-widest">Sin actividad reciente</p>
        </div>
        <?php else: ?>
        <?php foreach ($actividad_reciente as $a): ?>
        <div class="px-10 py-5 flex items-center gap-6 hover:bg-slate-50/50 transition-colors">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-indigo-500 text-[20px]">sync</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-slate-700 truncate">
                    <span class="font-black text-slate-800"><?php echo htmlspecialchars($a['profesor_nombre']); ?></span>
                    — <?php echo htmlspecialchars($a['grupo_nombre']); ?>
                </p>
                <p class="text-[10px] text-slate-400 font-bold mt-0.5">
                    <?php echo $a['alumnos_en_lista']; ?> alumnos · Fecha clase: <?php echo $a['fecha']; ?>
                </p>
            </div>
            <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest italic shrink-0">
                <?php echo $a['ultima_sync'] ? date('d/m H:i', strtotime($a['ultima_sync'])) : '—'; ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php // End of file ?>
