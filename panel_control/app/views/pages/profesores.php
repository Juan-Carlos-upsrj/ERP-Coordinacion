<?php
/**
 * app/views/pages/profesores.php
 * Gestión de docentes autorizados y sus carreras.
 */

$pdo = getConnection(DB_NAME); // Conexión a erp_academico
require_once __DIR__ . '/../../../../app/models/AdminModel.php';

$mensaje = '';
$tipo_mensaje = 'success';

// 1. OBTENER LISTA DE DOCENTES (necesaria para sync_all)
$sql = "SELECT * FROM profesores ORDER BY carrera_sigla ASC, nombre ASC";
$profesores = $pdo->query($sql)->fetchAll();

// 2. PROCESAR ACCIONES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $nombre = strtoupper(trim($_POST['nombre']));
                $apellidos = strtoupper(trim($_POST['apellidos']));
                $email_user = trim($_POST['email']);
                $email = $email_user . "@upsrj.edu.mx";
                $carrera = $_POST['carrera_sigla'];

                $sql = "INSERT INTO profesores (nombre, apellidos, email, carrera_sigla) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $apellidos, $email, $carrera]);
                
                // Sincronizar con el Gestor de Horarios de la carrera
                AdminModel::syncGlobalToCareer($pdo, $email);
                
                $mensaje = "Docente <b>{$nombre}</b> agregado correctamente y sincronizado con el Gestor de Horarios.";
            }

            if ($_POST['action'] === 'edit') {
                $id = $_POST['id'];
                $nombre = strtoupper(trim($_POST['nombre']));
                $apellidos = strtoupper(trim($_POST['apellidos']));
                $email_user = trim($_POST['email']);
                $email = $email_user . "@upsrj.edu.mx";
                $carrera = $_POST['carrera_sigla'];

                $sql = "UPDATE profesores SET nombre = ?, apellidos = ?, email = ?, carrera_sigla = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $apellidos, $email, $carrera, $id]);
                
                // Sincronizar cambios hacia el Gestor de Horarios
                AdminModel::syncGlobalToCareer($pdo, $email);
                
                $mensaje = "Docente <b>{$nombre}</b> actualizado y sincronizado.";
            }

            if ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                $sql = "DELETE FROM profesores WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $mensaje = "Registro eliminado.";
            }
            if ($_POST['action'] === 'sync_all') {
                $count = 0;
                foreach ($profesores as $p) {
                    AdminModel::syncGlobalToCareer($pdo, $p['email']);
                    $count++;
                }
                $mensaje = "Sincronización completa: <b>{$count}</b> docentes actualizados en sus respectivas carreras.";
            }
        } catch (Exception $e) {
            $tipo_mensaje = 'error';
            $mensaje = "Error: " . $e->getMessage();
        }
    }
}

?>
<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight">Docentes Autorizados</h1>
        <p class="text-slate-500 mt-1">Gestión centralizada de accesos y asignación de carreras para la App.</p>
    </div>
    <div class="flex gap-3">
        <form method="POST" class="inline">
            <input type="hidden" name="action" value="sync_all">
            <button type="submit" class="bg-indigo-50 border border-indigo-200 text-indigo-600 px-6 py-3 rounded-2xl font-black text-sm hover:bg-indigo-100 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined">sync</span>
                SINCRONIZAR TODO
            </button>
        </form>
        <button onclick="openAddModal()" 
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-lg shadow-indigo-200 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined">person_add</span>
            AGREGAR DOCENTE
        </button>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="mb-6 p-4 rounded-2xl border <?php echo $tipo_mensaje === 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> flex items-center gap-3 font-bold text-sm slide-in">
        <span class="material-symbols-outlined"><?php echo $tipo_mensaje === 'success' ? 'check_circle' : 'error'; ?></span>
        <?php echo $mensaje; ?>
    </div>
<?php endif; ?>

<!-- Tabla de Docentes -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Carrera</th>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nombre</th>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Correo Institucional</th>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php foreach ($profesores as $p): 
                $c = $CARRERAS[$p['carrera_sigla']] ?? null;
                $color = $c ? $c['color_hex'] : '#94a3b8';
            ?>
            <tr class="hover:bg-slate-50/50 transition-colors group">
                <td class="px-8 py-4">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black border" 
                          style="background-color: <?php echo $color; ?>10; border-color: <?php echo $color; ?>40; color: <?php echo $color; ?>">
                        <?php echo htmlspecialchars($p['carrera_sigla']); ?>
                    </span>
                </td>
                <td class="px-8 py-4 font-bold text-slate-700">
                    <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellidos']); ?>
                </td>
                <td class="px-8 py-4 text-slate-500 font-medium italic">
                    <?php echo htmlspecialchars($p['email']); ?>
                </td>
                <td class="px-8 py-4 text-right">
                    <div class="flex justify-end gap-2">
                        <button onclick='openEditModal(<?php echo json_encode($p); ?>)' 
                                class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-400 hover:bg-indigo-500 hover:text-white transition-all flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar acceso a este docente?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button class="w-8 h-8 rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Agregar/Editar -->
<div id="modal-docente" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden scale-in">
        <div class="p-8 border-b border-slate-50 bg-slate-50/50">
            <h2 id="modal-title" class="text-xl font-black text-slate-800">Registrar Nuevo Docente</h2>
            <p id="modal-subtitle" class="text-sm text-slate-500 mt-1">Autoriza el acceso a la App de un profesor.</p>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <input type="hidden" name="action" id="modal-action" value="add">
            <input type="hidden" name="id" id="modal-id" value="">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nombre(s)</label>
                    <input type="text" name="nombre" id="modal-nombre" required placeholder="Ej: Juan" 
                           class="w-full p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-indigo-500 focus:bg-white transition-all outline-none">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Apellidos</label>
                    <input type="text" name="apellidos" id="modal-apellidos" required placeholder="Ej: Pérez" 
                           class="w-full p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-indigo-500 focus:bg-white transition-all outline-none">
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Correo Institucional</label>
                <div class="flex items-center gap-2">
                    <input type="text" name="email" id="modal-email" required placeholder="ejemplo" 
                           class="flex-1 p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-indigo-500 focus:bg-white transition-all outline-none">
                    <span class="bg-gray-100 px-4 py-3 rounded-xl border border-gray-200 text-xs font-black text-gray-400">@upsrj.edu.mx</span>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Carrera Asignada</label>
                <select name="carrera_sigla" id="modal-carrera" class="w-full p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-indigo-500 focus:bg-white transition-all outline-none cursor-pointer">
                    <?php foreach ($CARRERAS as $sigla => $c): ?>
                        <option value="<?php echo $sigla; ?>"><?php echo $sigla; ?> — <?php echo $c['nombre_largo']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" 
                        class="flex-1 px-4 py-3 rounded-xl border border-slate-200 text-slate-400 font-bold hover:bg-slate-50 transition-all">Cancelar</button>
                <button type="submit" id="modal-submit"
                        class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 text-white font-black shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">GUARDAR DOCENTE</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modal-action').value = 'add';
    document.getElementById('modal-id').value = '';
    document.getElementById('modal-nombre').value = '';
    document.getElementById('modal-apellidos').value = '';
    document.getElementById('modal-email').value = '';
    document.getElementById('modal-title').textContent = 'Registrar Nuevo Docente';
    document.getElementById('modal-subtitle').textContent = 'Autoriza el acceso a la App de un profesor.';
    document.getElementById('modal-submit').textContent = 'GUARDAR DOCENTE';
    document.getElementById('modal-docente').classList.remove('hidden');
}

function openEditModal(docente) {
    document.getElementById('modal-action').value = 'edit';
    document.getElementById('modal-id').value = docente.id;
    document.getElementById('modal-nombre').value = docente.nombre;
    document.getElementById('modal-apellidos').value = docente.apellidos;
    document.getElementById('modal-email').value = docente.email.split('@')[0];
    document.getElementById('modal-carrera').value = docente.carrera_sigla;
    document.getElementById('modal-title').textContent = 'Editar Docente';
    document.getElementById('modal-subtitle').textContent = 'Modifica los datos del docente autorizado.';
    document.getElementById('modal-submit').textContent = 'ACTUALIZAR DOCENTE';
    document.getElementById('modal-docente').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modal-docente').classList.add('hidden');
}
</script>

<style>
    .scale-in { animation: scaleIn 0.2s ease-out; }
    .slide-in { animation: slideIn 0.3s ease-out; }
    @keyframes scaleIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
