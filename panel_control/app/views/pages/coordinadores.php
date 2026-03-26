<?php
/**
 * app/views/pages/coordinadores.php
 * Gestión de coordinadores con acceso al portal de cada carrera.
 */

$pdo = getConnection(DB_NAME); // Conexión a erp_academico

$mensaje = '';
$tipo_mensaje = 'success';

// 1. OBTENER LISTA DE COORDINADORES
$sql = "SELECT * FROM coordinadores ORDER BY carrera_sigla ASC, nombre ASC";
$coordinadores = $pdo->query($sql)->fetchAll();

// 2. PROCESAR ACCIONES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $nombre = trim($_POST['nombre']);
                $carrera = $_POST['carrera_sigla'];
                $password = $_POST['password'];
                $rol = $_POST['rol'];

                $sql = "INSERT INTO coordinadores (nombre, carrera_sigla, password_hash, rol) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $carrera, $password, $rol]);
                
                $mensaje = "Coordinador <b>{$nombre}</b> agregado correctamente.";
            }

            if ($_POST['action'] === 'edit') {
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $carrera = $_POST['carrera_sigla'];
                $password = $_POST['password'];
                $rol = $_POST['rol'];

                // Si la contraseña no está vacía, se actualiza
                if (!empty($password)) {
                    $sql = "UPDATE coordinadores SET nombre = ?, carrera_sigla = ?, password_hash = ?, rol = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $carrera, $password, $rol, $id]);
                } else {
                    $sql = "UPDATE coordinadores SET nombre = ?, carrera_sigla = ?, rol = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $carrera, $rol, $id]);
                }
                
                $mensaje = "Coordinador <b>{$nombre}</b> actualizado correctamente.";
            }

            if ($_POST['action'] === 'delete') {
                $id = $_POST['id'];
                $sql = "DELETE FROM coordinadores WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $mensaje = "Coordinador eliminado correctamente.";
            }

            // Recargar lista después de acción
            $coordinadores = $pdo->query("SELECT * FROM coordinadores ORDER BY carrera_sigla ASC, nombre ASC")->fetchAll();
            
        } catch (Exception $e) {
            $tipo_mensaje = 'error';
            $mensaje = "Error: " . $e->getMessage();
        }
    }
}

?>
<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Gestión de Coordinadores</h1>
        <p class="text-slate-500 mt-1">Control de acceso al portal web para cada carrera.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openAddModal()" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl font-black text-sm shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined">person_add</span>
            AGREGAR COORDINADOR
        </button>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="mb-6 p-4 rounded-2xl border <?php echo $tipo_mensaje === 'success' ? 'bg-green-50 border-green-100 text-green-700' : 'bg-red-50 border-red-100 text-red-700'; ?> flex items-center gap-3 font-bold text-sm slide-in">
        <span class="material-symbols-outlined"><?php echo $tipo_mensaje === 'success' ? 'check_circle' : 'error'; ?></span>
        <?php echo $mensaje; ?>
    </div>
<?php endif; ?>

<!-- Tabla de Coordinadores -->
<div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
    <table class="w-full text-left border-collapse">
        <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Carrera</th>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nombre Coordinador</th>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Rol</th>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contraseña (Hash/Plain)</th>
                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php foreach ($coordinadores as $c): 
                $cinfo = $CARRERAS[$c['carrera_sigla']] ?? null;
                $color = $cinfo ? $cinfo['color_hex'] : '#94a3b8';
            ?>
            <tr class="hover:bg-slate-50/50 transition-colors group">
                <td class="px-8 py-4">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black border" 
                          style="background-color: <?php echo $color; ?>10; border-color: <?php echo $color; ?>40; color: <?php echo $color; ?>">
                        <?php echo htmlspecialchars($c['carrera_sigla']); ?>
                    </span>
                </td>
                <td class="px-8 py-4 font-bold text-slate-700">
                    <?php echo htmlspecialchars($c['nombre']); ?>
                </td>
                <td class="px-8 py-4">
                    <span class="text-[10px] font-black uppercase <?php echo $c['rol'] === 'admin' ? 'text-blue-600' : 'text-slate-400'; ?>">
                        <?php echo htmlspecialchars($c['rol']); ?>
                    </span>
                </td>
                <td class="px-8 py-4 text-slate-400 text-xs font-mono">
                    ••••••••
                </td>
                <td class="px-8 py-4 text-right">
                    <div class="flex justify-end gap-2">
                        <button onclick='openEditModal(<?php echo json_encode($c); ?>)' 
                                class="w-8 h-8 rounded-lg bg-blue-50 text-blue-400 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar acceso a este coordinador?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
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
<div id="modal-coordinador" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden scale-in">
        <div class="p-8 border-b border-slate-50 bg-slate-50/50">
            <h2 id="modal-title" class="text-xl font-black text-slate-800">Registrar Nuevo Coordinador</h2>
            <p id="modal-subtitle" class="text-sm text-slate-500 mt-1">Define el acceso al portal de coordinación.</p>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <input type="hidden" name="action" id="modal-action" value="add">
            <input type="hidden" name="id" id="modal-id" value="">
            
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nombre Completo / Etiqueta</label>
                <input type="text" name="nombre" id="modal-nombre" required placeholder="Ej: Coordinador ISW" 
                       class="w-full p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-blue-500 focus:bg-white transition-all outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Carrera</label>
                    <select name="carrera_sigla" id="modal-carrera" class="w-full p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-blue-500 focus:bg-white transition-all outline-none cursor-pointer">
                        <?php foreach ($CARRERAS as $sigla => $c): ?>
                            <option value="<?php echo $sigla; ?>"><?php echo $sigla; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Rol</label>
                    <select name="rol" id="modal-rol" class="w-full p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-blue-500 focus:bg-white transition-all outline-none cursor-pointer">
                        <option value="admin">Administrador</option>
                        <option value="apoyo">Apoyo / Lectura</option>
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Contraseña Global</label>
                <input type="password" name="password" id="modal-password" placeholder="••••••••" 
                       class="w-full p-3 border-2 border-slate-100 rounded-xl bg-slate-50 text-sm font-bold focus:border-blue-500 focus:bg-white transition-all outline-none">
                <p id="password-hint" class="text-[10px] text-slate-400 italic mt-1 hidden">* Deja vacío para mantener la actual.</p>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" 
                        class="flex-1 px-4 py-3 rounded-xl border border-slate-200 text-slate-400 font-bold hover:bg-slate-50 transition-all">Cancelar</button>
                <button type="submit" id="modal-submit"
                        class="flex-1 px-4 py-3 rounded-xl bg-blue-600 text-white font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all">GUARDAR</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modal-action').value = 'add';
    document.getElementById('modal-id').value = '';
    document.getElementById('modal-nombre').value = '';
    document.getElementById('modal-password').value = '';
    document.getElementById('modal-password').required = true;
    document.getElementById('password-hint').classList.add('hidden');
    document.getElementById('modal-title').textContent = 'Registrar Nuevo Coordinador';
    document.getElementById('modal-submit').textContent = 'GUARDAR';
    document.getElementById('modal-coordinador').classList.remove('hidden');
}

function openEditModal(c) {
    document.getElementById('modal-action').value = 'edit';
    document.getElementById('modal-id').value = c.id;
    document.getElementById('modal-nombre').value = c.nombre;
    document.getElementById('modal-carrera').value = c.carrera_sigla;
    document.getElementById('modal-rol').value = c.rol;
    document.getElementById('modal-password').value = '';
    document.getElementById('modal-password').required = false;
    document.getElementById('password-hint').classList.remove('hidden');
    document.getElementById('modal-title').textContent = 'Editar Coordinador';
    document.getElementById('modal-submit').textContent = 'ACTUALIZAR';
    document.getElementById('modal-coordinador').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modal-coordinador').classList.add('hidden');
}
</script>

<style>
    .scale-in { animation: scaleIn 0.2s ease-out; }
    .slide-in { animation: slideIn 0.3s ease-out; }
    @keyframes scaleIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
