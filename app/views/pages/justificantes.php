<?php
/**
 * app/views/pages/justificantes.php
 * Panel de gestión de justificantes de faltas.
 * Búsqueda de alumno -> Selección de fecha -> Listado de clases -> Justificación.
 */

require_once 'app/models/AlumnosModel.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$alumno_query = $_GET['alumno'] ?? '';
$fecha_sel    = $_GET['fecha']  ?? date('Y-m-d');

// 1. Obtener lista de alumnos para el autocompletado/datalist
$sql_alumnos = "SELECT DISTINCT alumno_nombre FROM asistencia_clases ORDER BY alumno_nombre";
$todos_alumnos = $pdo->query($sql_alumnos)->fetchAll(PDO::FETCH_COLUMN);

// 2. Si hay búsqueda, obtener asistencias del día
$clases_dia = [];
if ($alumno_query) {
    $clases_dia = AlumnosModel::getAsistenciasAlumnoFecha($pdo, $alumno_query, $fecha_sel);
}

$status_res = $_GET['res'] ?? null;
$solicitud_id_activa = $_GET['solicitud_id'] ?? null;
$pendientes = AlumnosModel::getPendingSolicitudes($pdo);
?>

<div class="mb-8">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold uppercase mb-3">
        <span class="material-symbols-outlined text-[14px]">verified</span>
        Control Administrativo
    </div>
    <h1 class="text-3xl font-black text-gray-800 tracking-tight">Justificación de Inasistencias</h1>
    <p class="text-gray-500 mt-1">Busca un estudiante y selecciona el día para gestionar sus faltas.</p>
</div>

<?php if ($status_res === 'ok'): ?>
<div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3 animate-bounce">
    <span class="material-symbols-outlined">check_circle</span>
    <p class="font-bold text-sm">¡Justificación registrada con éxito! Los cambios se han aplicado al historial del alumno.</p>
</div>
<?php elseif ($status_res === 'error'): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3">
    <span class="material-symbols-outlined">error</span>
    <p class="font-bold text-sm">Hubo un error al procesar la justificación. Intente de nuevo.</p>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- Buscador Lateral -->
    <div class="lg:col-span-4 space-y-6">
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest mb-6 border-b border-gray-50 pb-3">Filtros de Búsqueda</h3>
            
            <form method="GET" action="index.php" class="space-y-4">
                <input type="hidden" name="v" value="justificantes">
                
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-2">Nombre del Alumno</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">person</span>
                        <input type="text" name="alumno" value="<?php echo htmlspecialchars($alumno_query); ?>" 
                               list="alumnos_list" 
                               placeholder="Buscar estudiante..." 
                               required
                               class="w-full pl-12 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-2xl text-sm font-bold focus:ring-4 focus:ring-indigo-100 transition-all outline-none">
                        <datalist id="alumnos_list">
                            <?php foreach($todos_alumnos as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-gray-400 uppercase ml-2">Fecha de la Falta</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">calendar_today</span>
                        <input type="date" name="fecha" value="<?php echo htmlspecialchars($fecha_sel); ?>" 
                               class="w-full pl-12 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-2xl text-sm font-bold focus:ring-4 focus:ring-indigo-100 transition-all outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-black text-white py-4 rounded-2xl font-black text-sm shadow-lg shadow-indigo-100 transition-all transform hover:-translate-y-1">
                    Buscar Registros
                </button>
            </form>
        </div>

        <?php if ($alumno_query): ?>
        <div class="bg-indigo-600 rounded-3xl p-6 text-white shadow-xl">
            <h4 class="text-indigo-200 font-bold text-[10px] uppercase tracking-widest mb-1">Resumen Selección</h4>
            <p class="text-xl font-black leading-tight"><?php echo htmlspecialchars($alumno_query); ?></p>
            <p class="text-sm font-bold opacity-80 mt-1"><?php echo $fecha_sel; ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Resultados y Formulario -->
    <div class="lg:col-span-8">
        <?php if ($alumno_query): ?>
            <form action="index.php" method="POST" id="form-justificar">
                <input type="hidden" name="action" value="procesar_justificacion">
                <?php if ($solicitud_id_activa): ?>
                    <input type="hidden" name="solicitud_id" value="<?php echo htmlspecialchars($solicitud_id_activa); ?>">
                <?php endif; ?>
                
                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-6">
                    <div class="px-8 py-5 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
                        <div>
                            <h3 class="text-base font-black text-gray-800 italic">Clases Registradas</h3>
                            <p class="text-xs text-gray-400 font-bold uppercase tracking-tighter">Selecciona las inasistencias a justificar</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="selectAll(true)" class="text-[10px] font-black text-indigo-600 hover:text-indigo-800 px-2 py-1 bg-indigo-50 rounded-lg">TODAS</button>
                            <button type="button" onclick="selectAll(false)" class="text-[10px] font-black text-gray-400 hover:text-gray-600 px-2 py-1 bg-gray-100 rounded-lg">NINGUNA</button>
                        </div>
                    </div>
                    
                    <div class="divide-y divide-gray-50">
                        <?php if (count($clases_dia) > 0): ?>
                            <?php foreach ($clases_dia as $c): 
                                $isAusente = ($c['status'] === 'Ausente');
                            ?>
                            <label class="flex items-center gap-5 p-6 hover:bg-gray-50/50 transition-colors cursor-pointer <?php echo !$isAusente ? 'opacity-50' : ''; ?>">
                                <div class="relative">
                                    <input type="checkbox" name="ids[]" value="<?php echo $c['id']; ?>" 
                                           <?php echo !$isAusente ? 'disabled' : ''; ?>
                                           class="w-6 h-6 rounded-lg text-indigo-600 border-gray-200 focus:ring-indigo-500 transition-all">
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-black text-gray-800"><?php echo htmlspecialchars($c['materia_nombre']); ?></span>
                                        <?php if ($c['status'] === 'Justificado'): ?>
                                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-black border border-green-200 uppercase">Justificada</span>
                                        <?php elseif ($c['status'] === 'Presente'): ?>
                                            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black border border-blue-200 uppercase tracking-tighter">Presente</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-black border border-red-200 uppercase">Falta</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-gray-400 font-bold">
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">person_check</span>
                                            <?php echo htmlspecialchars($c['profesor_nombre']); ?>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">schedule</span>
                                            <?php echo substr($c['fecha_subida'], 11, 5); ?> hrs
                                        </span>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="py-20 text-center space-y-3">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="material-symbols-outlined text-gray-300 text-4xl">inventory_2</span>
                                </div>
                                <h4 class="text-gray-400 font-black text-lg">Sin registros</h4>
                                <p class="text-gray-400 text-sm max-w-[300px] mx-auto">No se encontraron clases para este alumno en la fecha seleccionada.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($clases_dia) > 0): ?>
                <!-- Formulario de Justificación -->
                <div class="bg-indigo-50 rounded-[2.5rem] p-8 border border-indigo-100 shadow-inner">
                    <h3 class="text-xl font-black text-indigo-900 mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined bg-indigo-600 text-white rounded-lg p-1 text-[18px]">verified</span>
                        Detalles de Justificación
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-indigo-400 uppercase ml-4">Motivo / Observaciones</label>
                            <textarea name="motivo" rows="4" required
                                      placeholder="Ej: Presentó justificante médico institucional por cita en el IMSS..."
                                      class="w-full p-6 bg-white border border-indigo-200 rounded-[2rem] text-sm font-medium focus:ring-4 focus:ring-indigo-200 transition-all outline-none resize-none shadow-sm"></textarea>
                        </div>
                        
                        <div class="flex items-center gap-4 p-4 bg-white/50 border border-white rounded-2xl">
                            <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white shrink-0">
                                <span class="material-symbols-outlined">shield_person</span>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Coordinador Autorizante</p>
                                <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></p>
                            </div>
                        </div>

                        <button type="submit" id="btn-submit" disabled
                                class="w-full bg-black text-white py-5 rounded-[2rem] font-black text-lg shadow-xl hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-30 disabled:pointer-events-none mt-4">
                            Autorizar Justificación
                        </button>
                    </div>
                </div>
                <?php endif; ?>

            </form>
        <?php else: ?>
            <?php if (count($pendientes) > 0): ?>
                <div class="mb-6">
                    <h3 class="text-xl font-black text-slate-800 tracking-tight flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-indigo-600">inbox</span> Solicitudes Pendientes de Alumnos
                        <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo count($pendientes); ?></span>
                    </h3>
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach($pendientes as $p): ?>
                            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-start justify-between gap-4 hover:shadow-md transition-shadow">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h4 class="text-base font-black text-slate-900"><?php echo htmlspecialchars($p['alumno_nombre']); ?></h4>
                                        <span class="text-xs font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-lg"><?php echo htmlspecialchars($p['matricula']); ?></span>
                                    </div>
                                    <p class="text-sm font-bold text-rose-600 mb-2 border-l-2 border-rose-500 pl-2">Fecha Solicitada: <?php echo htmlspecialchars($p['fecha_ausencia']); ?></p>
                                    <div class="bg-slate-50 border border-slate-100 p-3 rounded-2xl">
                                        <p class="text-xs text-slate-600 italic">"<?php echo htmlspecialchars($p['motivo']); ?>"</p>
                                    </div>
                                    <?php if (!empty($p['archivo_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($p['archivo_url']); ?>" target="_blank" class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 mt-3 bg-indigo-50 px-3 py-1.5 rounded-xl transition-colors">
                                            <span class="material-symbols-outlined text-[14px]">attachment</span> Ver Documento Adjunto
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col gap-2 min-w-[140px]">
                                    <a href="index.php?v=justificantes&alumno=<?php echo urlencode($p['alumno_nombre']); ?>&fecha=<?php echo $p['fecha_ausencia']; ?>&solicitud_id=<?php echo $p['id']; ?>" class="bg-indigo-600 text-white text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-black transition-colors text-center shadow-lg shadow-indigo-200 flex items-center justify-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">check_circle</span> Atender
                                    </a>
                                    <form action="index.php" method="POST" onsubmit="return confirm('¿Seguro que deseas rechazar y descartar esta solicitud de justificación de <?php echo addslashes($p['alumno_nombre']); ?>?');">
                                        <input type="hidden" name="action" value="rechazar_solicitud">
                                        <input type="hidden" name="solicitud_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="w-full bg-white text-slate-500 border border-slate-200 text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-rose-50 hover:text-rose-600 transition-colors flex items-center justify-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">cancel</span> Rechazar
                                        </button>
                                    </form>
                                    <p class="text-[10px] text-slate-400 text-center mt-1 font-medium">Recibida: <?php echo substr($p['creado_en'], 0, 10); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-indigo-50/50 border-2 border-dashed border-indigo-100 rounded-[3rem] p-20 text-center space-y-6">
                    <div class="w-24 h-24 bg-white rounded-3xl shadow-xl shadow-indigo-100/50 flex items-center justify-center mx-auto rotate-6 transform hover:rotate-0 transition-transform cursor-help">
                        <span class="material-symbols-outlined text-indigo-600 text-5xl">manage_search</span>
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-gray-800 tracking-tight mb-2">Bandeja Vacía</h3>
                        <p class="text-gray-500 max-w-[350px] mx-auto font-medium">No hay solicitudes pendientes. Usa los filtros del panel izquierdo para buscar faltas de alumnos de manera manual.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function selectAll(val) {
    document.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach(cb => cb.checked = val);
    updateButton();
}

function updateButton() {
    const selected = document.querySelectorAll('input[name="ids[]"]:checked').length;
    const btn = document.getElementById('btn-submit');
    if (btn) btn.disabled = (selected === 0);
}

document.addEventListener('change', (e) => {
    if (e.target.name === 'ids[]') updateButton();
});

// Validación al enviar
document.getElementById('form-justificar')?.addEventListener('submit', (e) => {
    const selected = document.querySelectorAll('input[name="ids[]"]:checked').length;
    if (selected === 0) {
        e.preventDefault();
        alert('Por favor selecciona al menos una clase.');
    } else {
        if (!confirm(`¿Estás seguro de justificar ${selected} falta(s)? Esta acción quedará registrada en tu auditoría.`)) {
            e.preventDefault();
        }
    }
});
</script>
