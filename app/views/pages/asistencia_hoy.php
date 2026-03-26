<?php
/**
 * app/views/pages/asistencia_hoy.php
 * Vista detallada de asistencia del día actual, agrupada por grupo y materia.
 */
$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$fecha_hoy = date('Y-m-d');
$fecha_sel = $_GET['fecha'] ?? $fecha_hoy;

// ── Resumen del día ────────────────────────────────────────────────────────────
$sql_resumen = "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS presentes,
    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS ausentes,
    SUM(CASE WHEN status = 'Retardo' THEN 1 ELSE 0 END) AS retardos,
    SUM(CASE WHEN status = 'Justificado' THEN 1 ELSE 0 END) AS justificados,
    COUNT(DISTINCT grupo_nombre) AS grupos_activos,
    COUNT(DISTINCT profesor_nombre) AS profesores_activos
FROM asistencia_clases WHERE fecha = ? AND carrera_id = ?";
$stmt = $pdo->prepare($sql_resumen);
$stmt->execute([$fecha_sel, $carrera_info['carrera_id']]);
$resumen = $stmt->fetch();

$total   = (int)($resumen['total'] ?? 0);
$pct_asis = $total > 0 ? round($resumen['presentes'] / $total * 100, 1) : 0;

// ── Detalle por grupo ──────────────────────────────────────────────────────────
$sql_grupos = "SELECT 
    grupo_nombre,
    profesor_nombre,
    COUNT(*) AS total,
    SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS presentes,
    SUM(CASE WHEN status = 'Ausente' THEN 1 ELSE 0 END) AS ausentes,
    ROUND((1.0 * SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0))*100, 1) AS pct,
    MAX(fecha_subida) AS hora_subida
FROM asistencia_clases WHERE fecha = ? AND carrera_id = ?
GROUP BY grupo_nombre, profesor_nombre
ORDER BY grupo_nombre, hora_subida DESC";
$stmt = $pdo->prepare($sql_grupos);
$stmt->execute([$fecha_sel, $carrera_info['carrera_id']]);
$grupos_hoy = $stmt->fetchAll();

// ── Lista de fechas recientes ─────────────────────────────────────────────────
$fechas_recientes = $pdo->prepare(
    "SELECT DISTINCT fecha FROM asistencia_clases WHERE carrera_id = ? ORDER BY fecha DESC LIMIT 14"
);
$fechas_recientes->execute([$carrera_info['carrera_id']]);
$fechas_recientes = $fechas_recientes->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Encabezado -->
<div class="mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-bold uppercase mb-3">
            <span class="material-symbols-outlined text-[14px]">today</span>
            Asistencia Diaria
        </div>
        <h1 class="text-3xl font-black text-gray-800">Asistencia del Día</h1>
        <p class="text-gray-500 mt-1">Detalle de registros · <span class="font-bold text-gray-700"><?php echo $fecha_sel; ?></span></p>
    </div>
    <!-- Acciones -->
    <div class="flex items-center gap-3">
        <a href="index.php?v=mapa_calor" class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-black transition-all shadow-lg shadow-indigo-200 uppercase tracking-tighter">
            <span class="material-symbols-outlined text-[18px]">calendar_month</span>
            Mapa de Calor
        </a>

        <!-- Selector de fecha -->
        <form method="GET" action="index.php" class="flex gap-2">
            <input type="hidden" name="v" value="asistencia_hoy">
            <select name="fecha" onchange="this.form.submit()" class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-green-400">
                <?php foreach ($fechas_recientes as $f): ?>
                <option value="<?php echo $f; ?>" <?php echo $f === $fecha_sel ? 'selected' : ''; ?>>
                    <?php echo $f; ?> <?php echo $f === $fecha_hoy ? '(Hoy)' : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- KPI del día -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $kpis = [
        ['label'=>'Total Registros',   'val'=>$total,                    'icon'=>'assignment',    'color'=>'blue'],
        ['label'=>'Asistencia %',      'val'=>$pct_asis.'%',              'icon'=>'check_circle',  'color'=>'green'],
        ['label'=>'Ausencias',         'val'=>$resumen['ausentes']??0,    'icon'=>'cancel',        'color'=>'red'],
        ['label'=>'Grupos Activos',    'val'=>$resumen['grupos_activos']??0,'icon'=>'groups',       'color'=>'purple'],
    ];
    $colMap = ['blue'=>'bg-blue-100 text-blue-600','green'=>'bg-green-100 text-green-600','red'=>'bg-red-100 text-red-600','purple'=>'bg-purple-100 text-purple-600'];
    foreach ($kpis as $k):
    ?>
    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
        <div class="flex items-center gap-2 mb-2">
            <div class="w-9 h-9 rounded-xl <?php echo $colMap[$k['color']]; ?> flex items-center justify-center">
                <span class="material-symbols-outlined text-[18px]"><?php echo $k['icon']; ?></span>
            </div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider"><?php echo $k['label']; ?></p>
        </div>
        <p class="text-3xl font-black text-gray-800"><?php echo $k['val']; ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabla por grupo -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
        <h3 class="text-base font-bold text-gray-800">Desglose por Grupo</h3>
        <p class="text-xs text-gray-400 mt-0.5"><?php echo count($grupos_hoy); ?> registros del día</p>
    </div>
    <?php if (count($grupos_hoy) > 0): ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-xs text-gray-400 uppercase border-b border-gray-100 bg-gray-50/30">
            <tr>
                <th class="px-5 py-3 text-left w-10"></th>
                <th class="px-5 py-3 text-left font-bold">Grupo</th>
                <th class="px-5 py-3 text-left font-bold">Profesor</th>
                <th class="px-5 py-3 text-center font-bold">Total</th>
                <th class="px-5 py-3 text-center font-bold">Presentes</th>
                <th class="px-5 py-3 text-center font-bold">Ausentes</th>
                <th class="px-5 py-3 text-center font-bold">% Asistencia</th>
                <th class="px-5 py-3 text-center font-bold">Enviado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos_hoy as $g):
                $pct_g = (float)$g['pct'];
                $pctC  = $pct_g >= 85 ? 'text-green-600 bg-green-50' : ($pct_g >= 70 ? 'text-yellow-600 bg-yellow-50' : 'text-red-600 bg-red-50');
            ?>
            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors cursor-pointer group" 
                onclick="toggleAsistenciaDetalle(this, '<?php echo addslashes($g['grupo_nombre']); ?>', '<?php echo addslashes($g['profesor_nombre']); ?>', '<?php echo $fecha_sel; ?>')">
                <td class="px-5 py-3.5 text-center text-gray-300 group-hover:text-blue-500 transition-colors">
                    <span class="material-symbols-outlined transition-transform duration-200 chevron-icon">chevron_right</span>
                </td>
                <td class="px-5 py-3.5">
                    <span class="font-mono bg-gray-100 text-gray-700 px-2.5 py-1 rounded-lg text-xs font-bold border border-gray-200">
                        <?php echo htmlspecialchars($g['grupo_nombre']); ?>
                    </span>
                </td>
                <td class="px-5 py-3.5 text-gray-700 font-medium text-sm"><?php echo htmlspecialchars($g['profesor_nombre']); ?></td>
                <td class="px-5 py-3.5 text-center font-bold text-gray-800"><?php echo $g['total']; ?></td>
                <td class="px-5 py-3.5 text-center text-green-600 font-bold"><?php echo $g['presentes']; ?></td>
                <td class="px-5 py-3.5 text-center">
                    <?php if ($g['ausentes'] > 0): ?>
                    <span class="text-red-600 font-black"><?php echo $g['ausentes']; ?></span>
                    <?php else: ?>
                    <span class="text-green-500 font-bold">0</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3.5 text-center">
                    <span class="px-3 py-0.5 rounded-full text-xs font-black <?php echo $pctC; ?> border">
                        <?php echo number_format($pct_g, 1); ?>%
                    </span>
                </td>
                <td class="px-5 py-3.5 text-center text-xs text-gray-400">
                    <?php echo $g['hora_subida'] ? substr($g['hora_subida'], 11, 5) : '—'; ?>
                </td>
            </tr>
            <tr class="detail-row hidden bg-slate-50/50">
                <td colspan="7" class="px-10 py-4 detail-content">
                    <div class="flex items-center justify-center py-4">
                        <span class="animate-spin material-symbols-outlined text-gray-300">sync</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="py-16 text-center text-gray-400">
        <span class="material-symbols-outlined text-5xl mb-3 opacity-30">event_busy</span>
        <h3 class="text-base font-bold text-gray-700 mb-1">Sin registros para esta fecha</h3>
        <p class="text-sm">No se subió asistencia el <?php echo $fecha_sel; ?>.</p>
    </div>
    <?php endif; ?>
</div>

<script>
async function toggleAsistenciaDetalle(row, grupo, profesor, fecha) {
    const detailRow = row.nextElementSibling;
    const content = detailRow.querySelector('.detail-content');
    const chevron = row.querySelector('.chevron-icon');

    // Cerrar otros
    document.querySelectorAll('.detail-row').forEach(r => {
        if (r !== detailRow) {
            r.classList.add('hidden');
            r.previousElementSibling.querySelector('.chevron-icon').style.transform = 'rotate(0deg)';
        }
    });

    if (!detailRow.classList.contains('hidden')) {
        detailRow.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
        return;
    }

    detailRow.classList.remove('hidden');
    chevron.style.transform = 'rotate(90deg)';

    // Cargar si no está cargado
    if (content.dataset.loaded !== 'true') {
        try {
            const res = await fetch(`api/attendance_detalle.php?fecha=${fecha}&grupo=${encodeURIComponent(grupo)}&profesor=${encodeURIComponent(profesor)}`);
            const json = await res.json();

            if (json.success) {
                let html = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">`;
                json.data.forEach(al => {
                    const statusClass = {
                        'Presente': 'bg-green-100 text-green-700',
                        'Ausente': 'bg-red-100 text-red-700',
                        'Retardo': 'bg-yellow-100 text-yellow-700',
                        'Justificado': 'bg-blue-100 text-blue-700',
                        'Intercambio': 'bg-indigo-100 text-indigo-700'
                    }[al.status] || 'bg-gray-100 text-gray-700';

                    html += `
                        <div class="flex items-center justify-between p-2 rounded-xl bg-white border border-gray-100 shadow-sm">
                            <span class="text-xs font-bold text-gray-700 truncate mr-2">${al.alumno_nombre}</span>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase ${statusClass}">${al.status}</span>
                        </div>`;
                });
                html += `</div>`;
                content.innerHTML = html;
                content.dataset.loaded = 'true';
            } else {
                content.innerHTML = `<p class="text-xs text-red-500 font-bold">Error: ${json.error}</p>`;
            }
        } catch (e) {
            content.innerHTML = `<p class="text-xs text-red-500 font-bold">Error de red</p>`;
        }
    }
}
</script>
