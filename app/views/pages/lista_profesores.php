<?php
/**
 * app/views/pages/lista_profesores.php
 * Vista detallada del estado de sincronización de profesores.
 */

require_once 'app/core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Consulta profesores activos hoy
$hoy = date('Y-m-d');
$st = $pdo->prepare("SELECT profesor_nombre, COUNT(*) as registros, MAX(fecha_subida) as ultima_actividad 
                     FROM asistencia_clases 
                     WHERE DATE(fecha) = ?
                     GROUP BY profesor_nombre 
                     ORDER BY ultima_actividad DESC");
$st->execute([$hoy]);
$profesores_hoy = $st->fetchAll();
?>

<div class="mb-10">
    <h1 class="text-4xl font-black text-gray-800 tracking-tight">Panel de Profesores</h1>
</div>

<!-- Accesos Rápidos (HUB) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    
    <a href="index.php?v=autorizar_profesores" class="group bg-white p-5 rounded-[2rem] border border-gray-100 shadow-sm hover:shadow-xl hover:border-emerald-200 transition-all relative overflow-hidden max-w-xs">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-50 rounded-full group-hover:scale-110 transition-transform"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-3 transition-transform group-hover:rotate-12">
                <span class="material-symbols-outlined text-[20px]">verified_user</span>
            </div>
            <h3 class="font-black text-gray-800 text-sm leading-tight">Autorizar<br>Profesores</h3>
            <p class="text-[9px] text-gray-400 mt-1 font-bold uppercase italic tracking-tighter">Gestionar permisos</p>
        </div>
    </a>

</div>

<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-8">
    
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h3 class="text-base font-bold text-gray-800">Profesores Sincronizados Hoy</h3>
        <span class="bg-brand-100 text-brand-700 px-3 py-1 rounded-full text-xs font-bold font-mono border border-brand-200">
            TOTAL: <?php echo count($profesores_hoy); ?>
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-400 uppercase bg-white border-b border-gray-100">
                <tr>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider">Profesor</th>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider">Registros Hoy</th>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider">Última Actividad</th>
                    <th scope="col" class="px-6 py-4 font-bold tracking-wider text-right">Estatus</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($profesores_hoy) > 0): ?>
                    <?php foreach ($profesores_hoy as $prof): ?>
                        <?php
                            // Lógica base de duplicados o posibles errores (si envían solo 1 alumno, es raro)
                            $es_sospechoso = $prof['registros'] < 5;
                        ?>
                        <tr class="bg-white hover:bg-brand-50/50 transition-colors border-b border-gray-50 last:border-0">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center font-bold text-xs uppercase shadow-sm border border-brand-200">
                                        <?php echo substr($prof['profesor_nombre'], 0, 2); ?>
                                    </div>
                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($prof['profesor_nombre']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono bg-gray-100 px-2.5 py-1 rounded text-gray-700 font-bold border border-gray-200">
                                    <?php echo $prof['registros']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-800"><?php echo Utils::tiempoRelativo($prof['ultima_actividad']); ?></span>
                                    <span class="text-[10px] text-gray-400 font-medium"><?php echo $prof['ultima_actividad']; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($es_sospechoso): ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-200">
                                        <span class="material-symbols-outlined text-[14px]">warning</span>
                                        Registros Bajos
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-600 border border-green-200">
                                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                        Normal
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                            <span class="material-symbols-outlined text-4xl mb-3 opacity-30 text-gray-300">cloud_off</span>
                            <p class="font-medium">Aún no hay listas enviadas el día de hoy.</p>
                            <p class="text-xs mt-1">Los profesores deben sincronizar desde la App Móvil.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
