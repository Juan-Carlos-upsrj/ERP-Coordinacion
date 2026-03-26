<?php
/**
 * app/views/pages/perfil_alumno.php
 * Perfil individual de alumno — historial de faltas, materias afectadas.
 */

require_once 'app/models/AlumnosModel.php';
require_once 'app/core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$alumno_nombre = $_GET['alumno'] ?? '';

if (empty($alumno_nombre)) {
    echo '<div class="p-10 text-center"><h1 class="text-xl font-bold text-red-500">Alumno no especificado</h1><a href="index.php?v=reporte_alumnos" class="text-brand-600 underline mt-4 inline-block">Volver al monitor</a></div>';
    return;
}

// Historial de Faltas
$historial = AlumnosModel::getHistorialFaltas($pdo, $alumno_nombre);

// Agrupar por materia para resumen
$por_materia = [];
foreach ($historial as $h) {
    $mat = $h['materia_nombre'] ?? 'Sin materia';
    if (!isset($por_materia[$mat])) $por_materia[$mat] = 0;
    $por_materia[$mat]++;
}
arsort($por_materia);

// Info general
$total_faltas = count($historial);
$grupo_sql = $pdo->prepare("SELECT grupo_nombre FROM asistencia_clases WHERE alumno_nombre = ? GROUP BY grupo_nombre ORDER BY COUNT(*) DESC LIMIT 1");
$grupo_sql->execute([$alumno_nombre]);
$grupo_principal = $grupo_sql->fetchColumn() ?: 'N/D';
?>

<div class="mb-8">
    <a href="index.php?v=reporte_alumnos" class="inline-flex items-center gap-1 text-sm text-gray-400 hover:text-brand-600 font-bold mb-4 transition-colors">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Volver al Monitor
    </a>
    
    <div class="flex items-center gap-5">
        <div class="w-16 h-16 rounded-2xl bg-brand-100 text-brand-600 flex items-center justify-center text-2xl font-black border-2 border-brand-200 shadow-sm">
            <?php echo strtoupper(substr($alumno_nombre, 0, 2)); ?>
        </div>
        <div>
            <h1 class="text-2xl font-black text-gray-800 tracking-tight"><?php echo htmlspecialchars($alumno_nombre); ?></h1>
            <div class="flex items-center gap-3 mt-1">
                <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-sm font-bold text-gray-600 border border-gray-200"><?php echo htmlspecialchars($grupo_principal); ?></span>
                <span class="text-sm text-gray-400">•</span>
                <span class="text-sm font-bold <?php echo $total_faltas >= 7 ? 'text-red-600' : ($total_faltas >= 4 ? 'text-orange-600' : 'text-yellow-600'); ?>"><?php echo $total_faltas; ?> faltas registradas</span>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Resumen por Materia -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand-500 text-lg">school</span>
            Faltas por Materia
        </h3>
        <div class="space-y-3">
            <?php foreach ($por_materia as $materia => $count): ?>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 truncate max-w-[180px]"><?php echo htmlspecialchars($materia); ?></span>
                <span class="bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full text-xs font-black border border-red-200"><?php echo $count; ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($por_materia)): ?>
                <p class="text-sm text-gray-400 py-4 text-center">Sin faltas registradas</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historial Cronológico -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand-500 text-lg">history</span>
                Historial de Inasistencias
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-50/95 backdrop-blur z-10">
                    <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        <th class="py-3 px-6 text-left">Fecha</th>
                        <th class="py-3 px-6 text-left">Materia</th>
                        <th class="py-3 px-6 text-left">Profesor</th>
                        <th class="py-3 px-6 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($historial as $row): ?>
                    <tr class="hover:bg-red-50/30 transition-colors">
                        <td class="py-3 px-6 font-bold text-gray-800"><?php echo $row['fecha']; ?></td>
                        <td class="py-3 px-6 text-gray-600"><?php echo htmlspecialchars($row['materia_nombre']); ?></td>
                        <td class="py-3 px-6 text-gray-500 text-xs"><?php echo htmlspecialchars($row['profesor_nombre']); ?></td>
                        <td class="py-3 px-6 text-center">
                            <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-[10px] font-black border border-red-200">Ausente</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($historial)): ?>
                    <tr><td colspan="4" class="py-8 text-center text-gray-400 text-sm">Sin registros de inasistencia</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
