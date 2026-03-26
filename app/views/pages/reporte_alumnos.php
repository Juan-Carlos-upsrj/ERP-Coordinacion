<?php
/**
 * app/views/pages/reporte_alumnos.php
 * Monitor de Riesgo Crítico / Bajas. Muestra los alumnos con altas inasistencias.
 */

require_once 'app/models/AlumnosModel.php';
require_once 'app/core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
// Cache riesgo count in session for sidebar badge
$alumnos_riesgo = AlumnosModel::getAlumnosEnRiesgo($pdo, 100);
$total_estudiantes_en_riesgo = count($alumnos_riesgo);
$_SESSION['riesgo_count'] = $total_estudiantes_en_riesgo;
// Alumnos dados de baja
$alumnos_bajas = AlumnosModel::getAlumnosDadosDeBaja($pdo);
?>

<div class="mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-100 text-red-600 text-xs font-bold uppercase mb-3">
            <span class="material-symbols-outlined text-[16px]">emergency</span>
            Monitor Crítico Activo
        </div>
        <h1 class="text-3xl font-black text-gray-800 tracking-tight">Riesgo de Baja</h1>
        <p class="text-gray-500 mt-1">Alumnos con 3 o más inasistencias acumuladas</p>
    </div>
    <div class="flex gap-3">
        <a href="index.php?v=dashboard" class="bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 text-sm font-bold rounded-xl flex items-center gap-2 transition-all shadow-sm">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            Volver
        </a>
    </div>
</div>

<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-red-50/10">
        <h3 class="text-base font-bold text-gray-800">Listado de Alarmas (<span class="text-red-500"><?php echo $total_estudiantes_en_riesgo; ?></span>)</h3>
        <div class="relative w-64">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
            <input type="text" id="searchInput" placeholder="Buscar alumno..." class="w-full pl-9 pr-4 py-2 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-brand-500 font-medium">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-500" id="alumnosTable">
            <thead class="text-xs text-gray-400 uppercase bg-gray-50/50 border-b border-gray-100">
                <tr>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider">Estudiante</th>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider">Grupo</th>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider">Faltas Acumuladas</th>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider">Última Falta</th>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider text-right">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_estudiantes_en_riesgo > 0): ?>
                    <?php foreach ($alumnos_riesgo as $st): ?>
                        <?php 
                            // Level of severity
                            $faltas = (int)$st['total_faltas'];
                            $severityClass_bg = $faltas >= 7 ? 'bg-red-100 text-red-700 border-red-300' : ($faltas >= 5 ? 'bg-orange-100 text-orange-700 border-orange-300' : 'bg-yellow-100 text-yellow-700 border-yellow-300');
                        ?>
                        <tr class="bg-white hover:bg-gray-50/50 transition-colors border-b border-gray-50 last:border-0 student-row">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-black text-xs uppercase shadow-sm border border-gray-200">
                                        <?php echo substr($st['alumno_nombre'], 0, 2); ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800 student-name"><?php echo htmlspecialchars($st['alumno_nombre']); ?></p>
                                        <p class="text-[10px] uppercase font-bold text-gray-400">ID: <?php echo htmlspecialchars((string)$st['alumno_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono bg-gray-100 px-2 py-1 rounded text-gray-600 font-bold border border-gray-200 student-group">
                                    <?php echo htmlspecialchars($st['grupo_principal']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black border <?php echo $severityClass_bg; ?>">
                                    <?php echo $faltas; ?> Faltas
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-800"><?php echo Utils::tiempoRelativo($st['ultima_falta']); ?></span>
                                    <span class="text-[10px] text-gray-400 font-medium"><?php echo $st['ultima_falta']; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="index.php?v=perfil_alumno&alumno=<?php echo urlencode($st['alumno_nombre']); ?>" class="bg-white border border-gray-200 hover:border-brand-500 hover:text-brand-600 text-gray-400 w-8 h-8 rounded-lg inline-flex items-center justify-center transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center text-gray-400">
                            <span class="material-symbols-outlined text-5xl mb-4 opacity-30 text-green-500">verified</span>
                            <h3 class="text-lg font-black text-gray-800 mb-1">¡Excelente Estado!</h3>
                            <p class="font-medium text-sm">No hay alumnos con 3 o más inasistencias en este momento.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        const term = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.student-row');
        
        rows.forEach(row => {
            const name = row.querySelector('.student-name').textContent.toLowerCase();
            const group = row.querySelector('.student-group').textContent.toLowerCase();
            if (name.includes(term) || group.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>
