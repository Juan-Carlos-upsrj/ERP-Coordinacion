<?php
/**
 * app/views/pages/vista_grupo.php
 * Vista detallada de un grupo: alumnos, materias, asistencia.
 */
require_once 'app/models/GruposModel.php';
require_once 'app/models/AlumnosModel.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Si no se especificó grupo → mostrar lista de todos los grupos
$grupo = $_GET['grupo'] ?? '';

// Fechas de filtro
$mes_actual = (int)date('n');
if ($mes_actual >= 1 && $mes_actual <= 4)      { $inicio_def = date('Y-01-01'); $fin_def = date('Y-04-30'); }
elseif ($mes_actual >= 5 && $mes_actual <= 8)  { $inicio_def = date('Y-05-01'); $fin_def = date('Y-08-31'); }
else                                            { $inicio_def = date('Y-09-01'); $fin_def = date('Y-12-31'); }

$inicio = $_GET['inicio'] ?? $inicio_def;
$fin    = $_GET['fin']    ?? $fin_def;

// Lista de grupos disponibles
$grupos_disponibles = GruposModel::getResumenGrupos($pdo, $inicio, $fin);

if (empty($grupo) && !empty($grupos_disponibles)) {
    $grupo = $grupos_disponibles[0]['grupo_nombre'];
}

// Datos del grupo seleccionado
$alumnos_grupo  = $grupo ? GruposModel::getAlumnosDeGrupo($pdo, $grupo, $inicio, $fin) : [];
$materias_grupo = $grupo ? GruposModel::getMateriasDeGrupo($pdo, $grupo) : [];

// Stats del grupo
$grupo_stats = null;
foreach ($grupos_disponibles as $g) {
    if ($g['grupo_nombre'] === $grupo) { $grupo_stats = $g; break; }
}

$total_alumnos_grupo = count($alumnos_grupo);
$en_riesgo_grupo = count(array_filter($alumnos_grupo, fn($a) => (float)$a['pct_asistencia'] < 80));
?>

<div class="mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-bold uppercase mb-3">
            <span class="material-symbols-outlined text-[14px]">groups</span>
            Grupos
        </div>
        <h1 class="text-3xl font-black text-gray-800 tracking-tight">Vista por Grupo</h1>
        <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($carrera_info['nombre_largo']); ?></p>
    </div>
    <!-- Filtro de fechas -->
    <form method="GET" action="index.php" class="flex items-center gap-2 flex-wrap">
        <input type="hidden" name="v" value="vista_grupo">
        <input type="hidden" name="grupo" value="<?php echo htmlspecialchars($grupo); ?>">
        <input type="date" name="inicio" value="<?php echo htmlspecialchars($inicio); ?>"
               class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-purple-400">
        <span class="text-gray-400 text-sm font-bold">→</span>
        <input type="date" name="fin" value="<?php echo htmlspecialchars($fin); ?>"
               class="bg-white border border-gray-200 px-3 py-2 rounded-xl text-sm font-medium focus:ring-2 focus:ring-purple-400">
        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all">Filtrar</button>
    </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- Sidebar: lista de grupos -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Grupos (<?php echo count($grupos_disponibles); ?>)</h3>
            </div>
            <nav class="p-2 space-y-0.5">
                <?php foreach ($grupos_disponibles as $g):
                    $isActive = $g['grupo_nombre'] === $grupo;
                    $pct = (float)($g['pct_asistencia'] ?? 0);
                    $dotColor = $pct >= 85 ? 'bg-green-500' : ($pct >= 70 ? 'bg-yellow-500' : 'bg-red-500');
                ?>
                <a href="index.php?v=vista_grupo&grupo=<?php echo urlencode($g['grupo_nombre']); ?>&inicio=<?php echo $inicio; ?>&fin=<?php echo $fin; ?>"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo $isActive ? 'bg-purple-50 text-purple-700 font-bold' : 'hover:bg-gray-50 text-gray-600'; ?>">
                    <span class="w-2.5 h-2.5 rounded-full <?php echo $dotColor; ?> shrink-0"></span>
                    <span class="text-sm flex-1 truncate font-medium"><?php echo htmlspecialchars($g['grupo_nombre']); ?></span>
                    <span class="text-xs font-black <?php echo $pct >= 85 ? 'text-green-600' : ($pct >= 70 ? 'text-yellow-600' : 'text-red-600'); ?>">
                        <?php echo $pct; ?>%
                    </span>
                </a>
                <?php endforeach; ?>
                <?php if (empty($grupos_disponibles)): ?>
                <p class="px-3 py-4 text-sm text-gray-400 text-center">Sin grupos en el período</p>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- Contenido principal del grupo -->
    <div class="lg:col-span-3 space-y-6">

        <?php if ($grupo && $grupo_stats): ?>
        <!-- Cabecera del grupo -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-6 text-white shadow-lg">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <p class="text-purple-200 text-xs font-bold uppercase tracking-widest mb-1">Grupo seleccionado</p>
                    <h2 class="text-2xl font-black"><?php echo htmlspecialchars($grupo); ?></h2>
                    <p class="text-purple-200 text-sm mt-1">
                        <?php echo $inicio; ?> → <?php echo $fin; ?>
                    </p>
                </div>
                <div class="flex gap-4 flex-wrap">
                    <div class="bg-white/15 backdrop-blur-sm rounded-xl px-5 py-3 text-center border border-white/20">
                        <span class="block text-3xl font-black"><?php echo $grupo_stats['total_alumnos']; ?></span>
                        <span class="text-xs text-purple-200 uppercase tracking-wider font-bold">Alumnos</span>
                    </div>
                    <div class="bg-white/15 backdrop-blur-sm rounded-xl px-5 py-3 text-center border border-white/20">
                        <span class="block text-3xl font-black"><?php echo number_format((float)$grupo_stats['pct_asistencia'], 1); ?>%</span>
                        <span class="text-xs text-purple-200 uppercase tracking-wider font-bold">Asistencia</span>
                    </div>
                    <div class="bg-white/15 backdrop-blur-sm rounded-xl px-5 py-3 text-center border border-white/20">
                        <span class="block text-3xl font-black <?php echo $en_riesgo_grupo > 0 ? 'text-yellow-200' : ''; ?>"><?php echo $en_riesgo_grupo; ?></span>
                        <span class="text-xs text-purple-200 uppercase tracking-wider font-bold">En Riesgo</span>
                    </div>
                    <div class="bg-white/15 backdrop-blur-sm rounded-xl px-5 py-3 text-center border border-white/20">
                        <span class="block text-3xl font-black"><?php echo $grupo_stats['dias_con_clase']; ?></span>
                        <span class="text-xs text-purple-200 uppercase tracking-wider font-bold">Días de Clase</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materias del grupo -->
        <?php if (!empty($materias_grupo)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-purple-500 text-lg">school</span>
                    Materias del Grupo
                </h3>
            </div>
            <div class="divide-y divide-gray-50">
                <?php foreach ($materias_grupo as $m):
                    $pct = (float)$m['pct_asistencia'];
                    $barColor = $pct >= 85 ? 'bg-green-500' : ($pct >= 70 ? 'bg-yellow-500' : 'bg-red-500');
                    $pctColor = $pct >= 85 ? 'text-green-600' : ($pct >= 70 ? 'text-yellow-600' : 'text-red-600');
                ?>
                <div class="px-6 py-3 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($m['materia_nombre'] ?? '—'); ?></p>
                        <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($m['profesor_nombre'] ?? '—'); ?> · <?php echo $m['dias_impartida']; ?> días</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full <?php echo $barColor; ?>" style="width:<?php echo min(100, $pct); ?>%"></div>
                        </div>
                        <span class="text-sm font-black w-12 text-right <?php echo $pctColor; ?>"><?php echo $pct; ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alumnos del grupo -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-purple-500 text-lg">person</span>
                    Alumnos del Grupo — <span class="text-purple-600 font-black ml-0.5"><?php echo $total_alumnos_grupo; ?></span>
                </h3>
                <div class="flex items-center gap-3 text-xs text-gray-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span>En riesgo (&lt;80%)</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[10px] text-gray-400 uppercase bg-gray-50/30 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-left font-bold">Alumno</th>
                            <th class="px-5 py-3 text-center font-bold">% Asistencia</th>
                            <th class="px-5 py-3 text-center font-bold">Presentes</th>
                            <th class="px-5 py-3 text-center font-bold">Faltas</th>
                            <th class="px-5 py-3 text-center font-bold">Retardos</th>
                            <th class="px-5 py-3 text-right font-bold">Perfil</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($alumnos_grupo)): ?>
                        <?php foreach ($alumnos_grupo as $al):
                            $pct = (float)$al['pct_asistencia'];
                            $pctColor = $pct >= 85 ? 'text-green-600' : ($pct >= 70 ? 'text-yellow-600' : 'text-red-600');
                            $barColor = $pct >= 85 ? 'bg-green-500' : ($pct >= 70 ? 'bg-yellow-500' : 'bg-red-500');
                            $enRiesgo = $pct < 80;
                        ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors <?php echo $enRiesgo ? 'bg-red-50/20' : ''; ?>">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <?php if ($enRiesgo): ?>
                                    <span class="material-symbols-outlined text-red-400 text-[14px]">warning</span>
                                    <?php endif; ?>
                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($al['alumno_nombre']); ?></span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <div class="inline-flex flex-col items-center gap-1">
                                    <span class="text-sm font-black <?php echo $pctColor; ?>"><?php echo $pct; ?>%</span>
                                    <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full <?php echo $barColor; ?>" style="width:<?php echo min(100, $pct); ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-green-700 font-bold text-sm"><?php echo $al['total_presentes']; ?></span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if ((int)$al['total_faltas'] > 0): ?>
                                <span class="bg-red-50 text-red-600 border border-red-200 px-2.5 py-0.5 rounded-full text-xs font-black">
                                    <?php echo $al['total_faltas']; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-green-500 font-black">✓</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if ((int)$al['total_retardos'] > 0): ?>
                                <span class="text-yellow-600 font-bold text-sm"><?php echo $al['total_retardos']; ?></span>
                                <?php else: ?>
                                <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="index.php?v=perfil_alumno&alumno=<?php echo urlencode($al['alumno_nombre']); ?>"
                                   class="w-8 h-8 rounded-lg bg-white border border-gray-200 hover:border-purple-400 hover:text-purple-600 text-gray-400 inline-flex items-center justify-center transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">visibility</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-12 text-center text-gray-400 text-sm">Sin alumnos en este período.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif (empty($grupos_disponibles)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
            <span class="material-symbols-outlined text-5xl text-gray-200 mb-3">groups</span>
            <p class="text-gray-500 font-medium">No hay datos de grupos en el período seleccionado.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
