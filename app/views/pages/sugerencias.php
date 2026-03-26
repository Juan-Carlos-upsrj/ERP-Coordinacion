<?php
/**
 * sugerencias.php
 * Vista donde los coordinadores pueden enviar buzón de sugerencias a SuperAdmin
 * y revisar el estado de sus sugerencias anteriores.
 */

require_once 'app/models/SugerenciasModel.php';
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$msg_ok    = null;
$msg_error = null;

// Acciones POST (Crear sugerencia)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'nueva_sugerencia') {
        $titulo = trim($_POST['titulo'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        $prio   = $_POST['prioridad'] ?? 'Normal';
        $cat    = $_POST['categoria'] ?? 'Otro';
        $autor  = $_SESSION['usuario_nombre'] ?? 'Desconocido';

        if (empty($titulo) || empty($desc)) {
            $msg_error = "El título y la descripción son obligatorios.";
        } else {
            $exito = SugerenciasModel::create($pdo, $titulo, $desc, $prio, $cat, $autor);
            if ($exito) {
                // Prevenir re-envío redirigiendo (PRG pattern)
                header("Location: index.php?v=sugerencias&ok=1");
                exit;
            } else {
                $msg_error = "Error al intentar guardar la sugerencia. Es posible que aún no exista la tabla en la base de datos.";
            }
        }
    }
}

// PRG Redirect message
if (isset($_GET['ok'])) {
    $msg_ok = "Tu sugerencia ha sido enviada con éxito. Será revisada a la brevedad.";
}

// Obtener todas mis sugerencias
$sugerencias = SugerenciasModel::getAll($pdo);

// Paletas de diseño adaptadas a Coordinación
$estado_styles = [
    'Pendiente'   => 'bg-amber-100 text-amber-700 border-amber-200',
    'Vista'       => 'bg-blue-100 text-blue-700 border-blue-200',
    'Resuelta'    => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'Descartada'  => 'bg-slate-100 text-slate-500 border-slate-200',
];
$prioridad_styles = [
    'Urgente' => 'text-rose-600 bg-rose-50 border-rose-200',
    'Alta'    => 'text-orange-600 bg-orange-50 border-orange-200',
    'Normal'  => 'text-blue-600 bg-blue-50 border-blue-200',
    'Baja'    => 'text-slate-500 bg-slate-50 border-slate-200',
];
$cat_icons = [
    'Academica'      => 'school',
    'Infraestructura'=> 'home_repair_service',
    'Sistema'        => 'bug_report',
    'Otro'           => 'chat_bubble',
];

?>

<!-- Header -->
<div class="mb-8">
    <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <span class="material-symbols-outlined text-[20px]">mark_unread_chat_alt</span>
        </div>
        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Sugerencias de Desarrollo</h1>
    </div>
    <p class="text-slate-500 font-medium text-sm max-w-2xl">Buzón de comunicación directa con el equipo directivo/técnico. Puedes enviar propuestas de mejora, ideas para nuevas funcionalidades o reportar incidencias en tu infraestructura.</p>
</div>

<!-- Mensajes -->
<?php if ($msg_ok): ?>
<div class="mb-6 p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-700 flex items-center gap-3">
    <span class="material-symbols-outlined">check_circle</span>
    <span class="text-sm font-bold"><?php echo htmlspecialchars($msg_ok); ?></span>
</div>
<?php endif; ?>

<?php if ($msg_error): ?>
<div class="mb-6 p-4 rounded-xl border bg-rose-50 border-rose-200 text-rose-700 flex items-center gap-3">
    <span class="material-symbols-outlined">error</span>
    <span class="text-sm font-bold"><?php echo htmlspecialchars($msg_error); ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
    
    <!-- Columna Izquierda: Formulario -->
    <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm sticky top-6">
        <h2 class="text-lg font-black text-slate-800 mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-slate-400">add_comment</span>
            Nueva Sugerencia
        </h2>
        
        <form method="POST" action="index.php?v=sugerencias" class="space-y-5">
            <input type="hidden" name="action" value="nueva_sugerencia">

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Título Corto</label>
                <input type="text" name="titulo" required placeholder="Ej. Filtro por cuatrimestre..."
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Categoría</label>
                    <div class="relative">
                        <select name="categoria" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-800 appearance-none focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-pointer">
                            <option value="Sistema">💻 Sistema</option>
                            <option value="Academica">🎓 Académica</option>
                            <option value="Infraestructura">🏗️ Infraestructura</option>
                            <option value="Otro">💬 Otro</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-2.5 text-slate-400 pointer-events-none">expand_more</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Prioridad</label>
                    <div class="relative">
                        <select name="prioridad" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-800 appearance-none focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all cursor-pointer">
                            <option value="Baja">⚪ Baja</option>
                            <option value="Normal" selected>🔵 Normal</option>
                            <option value="Alta">🟠 Alta</option>
                            <option value="Urgente">🔴 Urgente</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-2.5 text-slate-400 pointer-events-none">expand_more</span>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Descripción Detallada</label>
                <textarea name="descripcion" required rows="4" placeholder="Describe tu idea, justificación o el problema encontrado..."
                          class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all resize-none"></textarea>
            </div>

            <button type="submit" class="w-full flex justify-center items-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold rounded-xl shadow-lg shadow-blue-500/30 transition-all">
                <span class="material-symbols-outlined text-[20px]">send</span>
                Enviar Sugerencia
            </button>
        </form>
    </div>

    <!-- Columna Derecha: Historial -->
    <div class="lg:col-span-2">
        <?php if (empty($sugerencias)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 border-dashed p-12 text-center flex flex-col items-center">
            <span class="material-symbols-outlined text-6xl text-slate-200 mb-4">forum</span>
            <h3 class="text-slate-800 font-black text-lg mb-1">Tu historial está vacío.</h3>
            <p class="text-sm text-slate-500">Envía tu primera sugerencia utilizando el formulario que aparece a tu izquierda.</p>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($sugerencias as $s): 
                $est = $s['estado'];
                $pri = $s['prioridad'];
                $cat = $s['categoria'];
                $icon = $cat_icons[$cat] ?? 'chat_bubble';
            ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow group">
                <div class="flex items-start gap-4">
                    
                    <!-- Icono circular -->
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center shrink-0 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors">
                        <span class="material-symbols-outlined"><?php echo $icon; ?></span>
                    </div>

                    <!-- Contenido -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <h3 class="text-base font-black text-slate-900"><?php echo htmlspecialchars($s['titulo']); ?></h3>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border <?php echo $prioridad_styles[$pri] ?? ''; ?>">
                                <?php echo $pri; ?>
                            </span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border <?php echo $estado_styles[$est] ?? ''; ?>">
                                <?php echo $est; ?>
                            </span>
                        </div>
                        
                        <p class="text-sm text-slate-600 leading-relaxed mb-3 mt-2 pr-4">
                            <?php echo nl2br(htmlspecialchars($s['descripcion'])); ?>
                        </p>

                        <div class="flex items-center gap-4 text-[11px] font-medium text-slate-400">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">event</span>
                                <?php echo date('d M, Y', strtotime($s['fecha_creacion'])); ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">person</span>
                                Enviado por ti (<?php echo htmlspecialchars($s['enviado_por']); ?>)
                            </span>
                        </div>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
