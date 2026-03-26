<?php
/**
 * app/views/pages/autorizar_profesores.php
 * Gestión de docentes autorizados para la carrera actual.
 */

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info = $CARRERAS[$carrera_sigla];
$pdo = getConnection(DB_NAME); // Conexión a la base global erp_academico

$mensaje = '';
$tipo_mensaje = 'success';

// 1. PROCESAR ACCIONES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $nombre = trim($_POST['nombre']);
                $apellidos = trim($_POST['apellidos']);
                $email_user = trim($_POST['email']);
                $email = $email_user . "@upsrj.edu.mx";
                $carrera = $carrera_sigla;

                $sql = "INSERT INTO profesores (nombre, apellidos, email, carrera_sigla) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $apellidos, $email, $carrera]);
                $mensaje = "Docente <b>{$nombre}</b> autorizado.";
            }

            if ($_POST['action'] === 'edit') {
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $apellidos = trim($_POST['apellidos']);
                $email_user = trim($_POST['email']);
                $email = $email_user . "@upsrj.edu.mx";

                $sql = "UPDATE profesores SET nombre=?, apellidos=?, email=? WHERE id=? AND carrera_sigla=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $apellidos, $email, $id, $carrera_sigla]);
                $mensaje = "Docente actualizado correctamente.";
            }

            if ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                $sql = "DELETE FROM profesores WHERE id = ? AND carrera_sigla = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id, $carrera_sigla]);
                $mensaje = "Autorización eliminada.";
            }
        } catch (Exception $e) {
            $tipo_mensaje = 'error';
            $mensaje = "Error: " . $e->getMessage();
        }
    }
}

// 2. OBTENER LISTA DE DOCENTES DE ESTA CARRERA
$sql = "SELECT * FROM profesores WHERE carrera_sigla = ? ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$carrera_sigla]);
$profesores = $stmt->fetchAll();

?>

<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-black text-gray-800 tracking-tight">Autorizar Profesores</h1>
        <p class="text-gray-500 mt-1">Gestiona los docentes que pueden sincronizar datos para <b><?php echo $carrera_sigla; ?></b>.</p>
    </div>
    <button onclick="openAuthModal()" 
            class="bg-brand-600 hover:bg-brand-700 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-lg shadow-brand-100 transition-all flex items-center gap-2">
        <span class="material-symbols-outlined">verified_user</span>
        AUTORIZAR DOCENTE
    </button>
</div>

<?php if ($mensaje): ?>
    <div class="mb-6 p-4 rounded-2xl border <?php echo $tipo_mensaje === 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> flex items-center gap-3 font-bold text-sm">
        <span class="material-symbols-outlined"><?php echo $tipo_mensaje === 'success' ? 'check_circle' : 'error'; ?></span>
        <?php echo $mensaje; ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-gray-50/50 border-b border-gray-100">
            <tr>
                <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre del Docente</th>
                <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Correo Institucional</th>
                <th class="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php if (count($profesores) > 0): ?>
                <?php foreach ($profesores as $p): 
                    $email_short = str_replace('@upsrj.edu.mx', '', $p['email']);
                ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-8 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center font-bold text-xs uppercase">
                                <?php echo substr($p['nombre'], 0, 1) . substr($p['apellidos']??'', 0, 1); ?>
                            </div>
                            <span class="font-bold text-gray-700 uppercase"><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellidos']); ?></span>
                        </div>
                    </td>
                    <td class="px-8 py-4 text-gray-500 font-medium italic">
                        <?php echo htmlspecialchars($p['email']); ?>
                    </td>
                    <td class="px-8 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="openAuthModal(<?php echo htmlspecialchars(json_encode($p)); ?>)" 
                                    class="w-8 h-8 rounded-lg bg-slate-50 text-slate-400 hover:bg-brand-500 hover:text-white transition-all flex items-center justify-center">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('¿Quitar autorización a este docente?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <button class="w-8 h-8 rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[18px]">person_remove</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="px-8 py-12 text-center text-gray-400 italic">
                        No hay docentes autorizados específicamente para esta carrera.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Agregar/Editar -->
<div id="modal-auth" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="p-8 border-b border-gray-100 bg-gray-50/50">
            <h2 id="modal-title" class="text-xl font-black text-gray-800 tracking-tight text-uppercase">Autorizar Docente</h2>
            <p class="text-sm text-gray-500 mt-1">Habilita la autoconfiguración de la App para un profesor.</p>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <input type="hidden" name="action" id="modal-action" value="add">
            <input type="hidden" name="id" id="modal-id" value="">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Nombre(s)</label>
                    <input type="text" name="nombre" id="modal-nombre" required placeholder="Ej: Juan" 
                           class="w-full p-3 border-2 border-gray-100 rounded-xl bg-gray-50 text-sm font-bold focus:border-brand-500 focus:bg-white transition-all outline-none uppercase">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Apellidos</label>
                    <input type="text" name="apellidos" id="modal-apellidos" required placeholder="Ej: Pérez" 
                           class="w-full p-3 border-2 border-gray-100 rounded-xl bg-gray-50 text-sm font-bold focus:border-brand-500 focus:bg-white transition-all outline-none uppercase">
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Correo Institucional</label>
                <div class="flex items-center gap-2">
                    <input type="text" name="email" id="modal-email" required placeholder="ejemplo" 
                           class="flex-1 p-3 border-2 border-gray-100 rounded-xl bg-gray-50 text-sm font-bold focus:border-brand-500 focus:bg-white transition-all outline-none">
                    <span class="bg-gray-100 px-4 py-3 rounded-xl border border-gray-200 text-xs font-black text-gray-400">@upsrj.edu.mx</span>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeAuthModal()" 
                        class="flex-1 px-4 py-3 rounded-xl border border-gray-200 text-gray-400 font-bold hover:bg-gray-50 transition-all">Cancelar</button>
                <button type="submit" 
                        class="flex-1 px-4 py-3 rounded-xl bg-brand-600 text-white font-black shadow-lg shadow-brand-100 hover:bg-brand-700 transition-all uppercase">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAuthModal(profesor = null) {
    const modal = document.getElementById('modal-auth');
    const title = document.getElementById('modal-title');
    const action = document.getElementById('modal-action');
    const id = document.getElementById('modal-id');
    const nombre = document.getElementById('modal-nombre');
    const apellidos = document.getElementById('modal-apellidos');
    const email = document.getElementById('modal-email');

    if (profesor) {
        title.innerText = 'Editar Docente';
        action.value = 'edit';
        id.value = profesor.id;
        nombre.value = profesor.nombre;
        apellidos.value = profesor.apellidos;
        email.value = profesor.email.replace('@upsrj.edu.mx', '');
    } else {
        title.innerText = 'Autorizar Nuevo Docente';
        action.value = 'add';
        id.value = '';
        nombre.value = '';
        apellidos.value = '';
        email.value = '';
    }

    modal.classList.remove('hidden');
}

function closeAuthModal() {
    document.getElementById('modal-auth').classList.add('hidden');
}
</script>
