<?php
/**
 * app/views/pages/bajas.php
 * Gestión de alumnos dados de baja. Permite ver el historial y registrar nuevas bajas.
 */
require_once 'app/models/AlumnosModel.php';
$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$bajas   = AlumnosModel::getAlumnosDadosDeBaja($pdo);
$total_b = count($bajas);
$msg_ok  = isset($_GET['baja']) ? 'Alumno registrado como baja correctamente.' : '';

// Buscar candidatos (alumnos con ≥ 3 faltas, no dados de baja)
$sql = "SELECT alumno_nombre, alumno_id,
            (SELECT grupo_nombre FROM asistencia_clases ac2 
             WHERE ac2.alumno_nombre = ac1.alumno_nombre 
             GROUP BY grupo_nombre ORDER BY COUNT(*) DESC LIMIT 1) AS grupo,
            COUNT(*) as faltas
        FROM asistencia_clases ac1 WHERE status='Ausente'
        AND alumno_nombre NOT IN (SELECT COALESCE(alumno_nombre,'') FROM alumnos_bajas WHERE alumno_nombre IS NOT NULL)
        GROUP BY alumno_nombre, alumno_id HAVING COUNT(*)>=3 ORDER BY faltas DESC LIMIT 30";
$candidatos = $pdo->query($sql)->fetchAll();
?>

<!-- Encabezado -->
<div class="mb-6">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-200 text-gray-700 text-xs font-bold uppercase mb-3">
        <span class="material-symbols-outlined text-[14px]">person_off</span>
        Registro de Bajas
    </div>
    <h1 class="text-3xl font-black text-gray-800">Alumnos Dados de Baja</h1>
    <p class="text-gray-500 mt-1">Historial de bajas y candidatos de alta ausencia</p>
</div>

<?php if (isset($_GET['baja']) && $_GET['baja'] === 'ok'): ?>
<div class="mb-4 bg-green-50 text-green-700 border border-green-200 rounded-xl px-5 py-3 text-sm font-bold flex items-center gap-2">
    <span class="material-symbols-outlined text-base">check_circle</span>
    Alumno registrado como baja correctamente.
</div>
<?php elseif (isset($_GET['baja']) && $_GET['baja'] === 'error'): ?>
<div class="mb-4 bg-red-50 text-red-700 border border-red-200 rounded-xl px-5 py-3 text-sm font-bold flex items-center gap-2">
    <span class="material-symbols-outlined text-base">error</span>
    Hubo un error al registrar la baja (verifica que el alumno no esté ya dado de baja o que el ID sea único).
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Historial de bajas -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
            <h3 class="text-base font-bold text-gray-800">
                Bajas Registradas <span class="text-gray-400 font-normal">(<?php echo $total_b; ?>)</span>
            </h3>
        </div>
        <?php if ($total_b > 0): ?>
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-400 uppercase border-b border-gray-100 bg-gray-50/30">
                <tr>
                    <th class="px-5 py-3 text-left font-bold">Alumno</th>
                    <th class="px-5 py-3 text-left font-bold">Fecha Baja</th>
                    <th class="px-5 py-3 text-left font-bold">Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bajas as $b): ?>
                <tr class="border-b border-gray-50 hover:bg-red-50/30 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold text-xs uppercase shrink-0">
                                <?php echo substr($b['alumno_nombre'], 0, 2); ?>
                            </div>
                            <div>
                                <p class="font-bold text-gray-700 text-sm"><?php echo htmlspecialchars($b['alumno_nombre']); ?></p>
                                <p class="text-[10px] text-gray-400">ID: <?php echo $b['alumno_id']; ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-500">
                        <?php echo $b['fecha_baja'] ? substr($b['fecha_baja'], 0, 10) : '—'; ?>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-500">
                        <?php echo htmlspecialchars($b['motivo'] ?? '—'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="py-12 text-center text-gray-400">
            <span class="material-symbols-outlined text-4xl mb-2 opacity-30">check_circle</span>
            <p class="text-sm">No hay bajas registradas.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Registrar nueva baja (candidatos con muchas faltas) -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-base font-bold text-gray-800">Candidatos a Baja</h3>
            <p class="text-xs text-gray-400 mt-0.5">Alumnos con ≥3 faltas aún activos</p>
        </div>
        <?php if (count($candidatos) > 0): ?>
        <div class="overflow-y-auto max-h-[500px]">
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-400 uppercase border-b border-gray-100 bg-gray-50/30 sticky top-0">
                <tr>
                    <th class="px-5 py-3 text-left font-bold">Alumno</th>
                    <th class="px-5 py-3 text-center font-bold">Faltas</th>
                    <th class="px-5 py-3 text-center font-bold">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidatos as $c): ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <p class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($c['alumno_nombre']); ?></p>
                        <p class="text-[10px] text-gray-400 font-mono"><?php echo htmlspecialchars($c['grupo'] ?? ''); ?></p>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-black border border-red-200">
                            <?php echo $c['faltas']; ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <button onclick="abrirModalBaja('<?php echo htmlspecialchars(addslashes($c['alumno_nombre'])); ?>','<?php echo htmlspecialchars($c['alumno_id']); ?>')"
                                class="bg-red-50 border border-red-200 text-red-700 hover:bg-red-100 text-xs font-bold px-3 py-1.5 rounded-lg transition-all">
                            Dar de Baja
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="py-12 text-center text-gray-400">
            <span class="material-symbols-outlined text-4xl mb-2 opacity-30">verified</span>
            <p class="text-sm">No hay candidatos a baja.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Baja -->
<div id="modalBaja" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-md border border-gray-100">
        <h3 class="text-lg font-black text-gray-800 mb-1">Registrar Baja</h3>
        <p class="text-sm text-gray-500 mb-4">El alumno quedará marcado como baja y NO aparecerá en reportes de riesgo.</p>
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="registrar_baja">
            <input type="hidden" name="alumno_nombre" id="modal_nombre">
            <input type="hidden" name="alumno_id"     id="modal_id">
            <div class="bg-gray-50 rounded-xl p-3 mb-4 border border-gray-200">
                <p class="text-sm font-bold text-gray-700" id="modal_display"></p>
            </div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Motivo de la baja:</label>
            <textarea name="motivo" rows="3" required placeholder="Describe el motivo de la baja..."
                      class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm resize-none mb-4 focus:ring-2 focus:ring-red-300"></textarea>
            <div class="flex gap-3">
                <button type="button" onclick="cerrarModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 rounded-xl text-sm transition-all">Cancelar</button>
                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl text-sm transition-all shadow-lg shadow-red-500/30">Confirmar Baja</button>
            </div>
        </form>
    </div>
</div>
<script>
function abrirModalBaja(nombre, id) {
    document.getElementById('modal_nombre').value = nombre;
    document.getElementById('modal_id').value = id;
    document.getElementById('modal_display').textContent = nombre + '  (ID: ' + id + ')';
    document.getElementById('modalBaja').classList.remove('hidden');
}
function cerrarModal() {
    document.getElementById('modalBaja').classList.add('hidden');
}
</script>
