<?php
/**
 * Panel de Control — anomalias.php
 * Detección de anomalías en todas las carreras.
 */
require_once 'app/models/AnomaliasModel.php';

$carrera_sigla = $_GET['carrera'] ?? $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];

// Intentar conexión (PostgreSQL con RLS)
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$tab = $_GET['tab'] ?? 'ghost';

// Cargar datos según tab activo
$ghost_uploads   = [];
$mismo_timestamp = [];
$listas_un_alumno= [];

if ($tab === 'ghost')      $ghost_uploads    = AnomaliasModel::getGhostUploads($pdo);
elseif ($tab === 'ts')     $mismo_timestamp  = AnomaliasModel::getSyncsMismoTimestamp($pdo);
elseif ($tab === 'single') $listas_un_alumno = AnomaliasModel::getListasUnAlumno($pdo);

// Resumen general (siempre)
$resumen = AnomaliasModel::getResumenAnomalias($pdo);
?>

<div class="mb-6">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-bold uppercase mb-3">
        <span class="material-symbols-outlined text-[14px]">bug_report</span>
        Análisis de Anomalías Global
    </div>
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Detección de Anomalías</h1>
            <p class="text-slate-500 mt-1">Sincronizaciones sospechosas y ghost uploads.</p>
        </div>
        
        <!-- Selector de Carrera -->
        <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] font-black text-slate-400 uppercase ml-2">Carrera:</span>
            <?php foreach ($CARRERAS as $sigla => $c): if(!$c['activa']) continue; ?>
                <a href="index.php?v=anomalias&carrera=<?php echo $sigla; ?>&tab=<?php echo $tab; ?>" 
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?php echo $carrera_sigla === $sigla ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200' : 'text-slate-500 hover:bg-slate-50'; ?>">
                    <?php echo $sigla; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Stats rápidos de la carrera seleccionada -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-red-50 shadow-sm p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-red-500 text-xl font-bold font-bold">person</span>
        </div>
        <div>
            <p class="text-xl font-black text-red-600"><?php echo $resumen['listas_un_alumno']; ?></p>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Listas de 1 Alumno</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-amber-50 shadow-sm p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-amber-500 text-xl font-bold font-bold">schedule</span>
        </div>
        <div>
            <p class="text-xl font-black text-amber-600">Revisar</p>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Syncs Mismo TS</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-50 shadow-sm p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-slate-400 text-xl font-bold font-bold">school</span>
        </div>
        <div>
            <p class="text-xl font-black text-slate-700"><?php echo $carrera_sigla; ?></p>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Programa Analizado</p>
        </div>
    </div>
</div>

<!-- Tabs y Tabla -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex border-b border-slate-50 bg-slate-50/50">
        <?php
        $tabs = [
            'ghost'  => ['label' => 'Ghost Uploads',         'icon' => 'trending_down'],
            'ts'     => ['label' => 'Mismo Timestamp',       'icon' => 'schedule'],
            'single' => ['label' => 'Listas de 1 Alumno',   'icon' => 'person'],
        ];
        foreach ($tabs as $t_key => $t_info): $active = $tab === $t_key;
        ?>
        <a href="index.php?v=anomalias&carrera=<?php echo $carrera_sigla; ?>&tab=<?php echo $t_key; ?>"
           class="flex items-center gap-2 px-6 py-4 text-xs font-black transition-all border-b-2 <?php echo $active ? 'border-red-500 text-red-600 bg-white shadow-sm' : 'border-transparent text-slate-400 hover:text-slate-600'; ?>">
            <span class="material-symbols-outlined text-[18px]"><?php echo $t_info['icon']; ?></span>
            <?php echo strtoupper($t_info['label']); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="overflow-x-auto">
        <?php if ($tab === 'ghost'): ?>
        <table class="w-full text-sm">
            <thead class="text-[10px] text-slate-400 uppercase bg-slate-50/30 border-b border-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left font-black">Profesor</th>
                    <th class="px-6 py-3 text-left font-black">Grupo</th>
                    <th class="px-6 py-3 text-center font-black">Fecha</th>
                    <th class="px-6 py-3 text-center font-black">Alumnos</th>
                    <th class="px-6 py-3 text-center font-black">Ratio</th>
                    <th class="px-6 py-3 text-center font-black">Sync</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($ghost_uploads)): ?>
                <tr><td colspan="6" class="py-12 text-center text-slate-300">
                    <span class="material-symbols-outlined text-4xl mb-2">verified</span>
                    <p class="text-xs font-bold uppercase">Sin ghost uploads detectados</p>
                </td></tr>
                <?php else: ?>
                <?php foreach ($ghost_uploads as $g):
                    $ratio = (float)$g['ratio_pct'];
                    $severity = $ratio < 20 ? 'bg-red-100 text-red-700 border-red-200' : 'bg-amber-100 text-amber-700 border-amber-200';
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors group">
                    <td class="px-6 py-4 font-bold text-slate-700"><?php echo htmlspecialchars($g['profesor_nombre']); ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-block px-2 py-1 rounded bg-indigo-50 text-indigo-700 text-[10px] font-black border border-indigo-100"><?php echo htmlspecialchars($g['grupo_nombre']); ?></span>
                    </td>
                    <td class="px-6 py-4 text-center text-slate-500 font-medium"><?php echo $g['fecha']; ?></td>
                    <td class="px-6 py-4 text-center font-black text-slate-800"><?php echo $g['alumnos_en_lista']; ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="<?php echo $severity; ?> px-2 py-0.5 rounded-full text-[10px] font-black border">
                            <?php echo $ratio; ?>%
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center text-[10px] text-slate-400 font-bold uppercase">
                        <?php echo $g['fecha_subida'] ? date('d/m H:i', strtotime($g['fecha_subida'])) : '—'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($tab === 'ts'): ?>
        <table class="w-full text-sm">
            <thead class="text-[10px] text-slate-400 uppercase bg-slate-50/30 border-b border-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left font-black">Profesor</th>
                    <th class="px-6 py-3 text-center font-black">Timestamp Sync</th>
                    <th class="px-6 py-3 text-center font-black">Fechas Distintas</th>
                    <th class="px-6 py-3 text-center font-black">Total Registros</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($mismo_timestamp)): ?>
                <tr><td colspan="4" class="py-12 text-center text-slate-300 text-xs font-bold uppercase">No se detectaron masivos.</td></tr>
                <?php else: ?>
                <?php foreach ($mismo_timestamp as $s): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700"><?php echo htmlspecialchars($s['profesor_nombre']); ?></td>
                    <td class="px-6 py-4 text-center font-mono text-[11px] text-slate-500 bg-slate-50/50"><?php echo $s['fecha_subida']; ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full text-[10px] font-black">
                            <?php echo $s['fechas_distintas_en_sync']; ?> FECHAS
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center font-black text-slate-700"><?php echo $s['total_registros']; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($tab === 'single'): ?>
        <table class="w-full text-sm">
            <thead class="text-[10px] text-slate-400 uppercase bg-slate-50/30 border-b border-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left font-black">Profesor</th>
                    <th class="px-6 py-3 text-left font-black">Grupo</th>
                    <th class="px-6 py-3 text-center font-black">Fecha</th>
                    <th class="px-6 py-3 text-left font-black">Alumno</th>
                    <th class="px-6 py-3 text-center font-black">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($listas_un_alumno)): ?>
                <tr><td colspan="5" class="py-12 text-center text-slate-300 text-xs font-bold uppercase">Sin registros.</td></tr>
                <?php else: ?>
                <?php foreach ($listas_un_alumno as $l):
                    $statusColor = $l['status'] === 'Presente' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700"><?php echo htmlspecialchars($l['profesor_nombre']); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded bg-purple-50 text-purple-700 text-[10px] font-black border border-purple-100"><?php echo htmlspecialchars($l['grupo_nombre']); ?></span>
                    </td>
                    <td class="px-6 py-4 text-center text-slate-500 font-medium"><?php echo $l['fecha']; ?></td>
                    <td class="px-6 py-4 font-bold text-slate-600"><?php echo htmlspecialchars($l['alumno_nombre']); ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="<?php echo $statusColor; ?> px-2 py-0.5 rounded-full text-[10px] font-black">
                            <?php echo strtoupper($l['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
