<?php
/**
 * app/views/pages/asignar_tutor.php
 * Asignación de tutores a alumnos en riesgo.
 */
require_once 'app/models/AlumnosModel.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$msg = '';
$msg_type = '';

// Verificar si existe la tabla tutores, crearla si no
try {
    $pdo->query("SELECT 1 FROM tutores LIMIT 1");
} catch (PDOException $e) {
    // Crear tabla tutores
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tutores (
            id SERIAL PRIMARY KEY,
            alumno_nombre TEXT NOT NULL,
            tutor_nombre TEXT NOT NULL,
            tutor_email TEXT DEFAULT '',
            tutor_telefono TEXT DEFAULT '',
            carrera_id INT DEFAULT 0,
            nota TEXT DEFAULT '',
            fecha_asignacion TIMESTAMP DEFAULT NOW(),
            UNIQUE(alumno_nombre)
        )");
    } catch (PDOException $e2) {
        error_log("asignar_tutor: No se pudo crear tabla tutores: " . $e2->getMessage());
    }
}

// Procesar formulario de asignación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'asignar') {
        $alumno  = trim($_POST['alumno_nombre'] ?? '');
        $tutor   = trim($_POST['tutor_nombre']  ?? '');
        $email   = trim($_POST['tutor_email']   ?? '');
        $tel     = trim($_POST['tutor_tel']     ?? '');
        $nota    = trim($_POST['nota']          ?? '');
        if ($alumno && $tutor) {
            try {
                $stmt = $pdo->prepare("INSERT INTO tutores (alumno_nombre, tutor_nombre, tutor_email, tutor_telefono, carrera_id, nota, fecha_asignacion)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON CONFLICT (alumno_nombre) DO UPDATE SET
                        tutor_nombre = EXCLUDED.tutor_nombre,
                        tutor_email = EXCLUDED.tutor_email,
                        tutor_telefono = EXCLUDED.tutor_telefono,
                        nota = EXCLUDED.nota,
                        fecha_asignacion = NOW()");
                $stmt->execute([$alumno, $tutor, $email, $tel, $carrera_info['carrera_id'], $nota]);
                $msg = "✓ Tutor asignado correctamente a $alumno";
                $msg_type = 'green';
            } catch (PDOException $e) {
                $msg = "Error: " . $e->getMessage();
                $msg_type = 'red';
            }
        }
    } elseif ($accion === 'eliminar') {
        $alumno = trim($_POST['alumno_nombre'] ?? '');
        if ($alumno) {
            try {
                $pdo->prepare("DELETE FROM tutores WHERE alumno_nombre = ?")->execute([$alumno]);
                $msg = "✓ Tutor desasignado de $alumno";
                $msg_type = 'yellow';
            } catch (PDOException $e) {
                $msg = "Error: " . $e->getMessage();
                $msg_type = 'red';
            }
        }
    }
}

// Alumnos en riesgo (para mostrar en la lista)
$alumnos_riesgo = AlumnosModel::getAlumnosEnRiesgo($pdo, 100);

// Tutores ya asignados
$tutores_asignados = [];
try {
    $tutores_asignados = $pdo->query("SELECT * FROM tutores ORDER BY fecha_asignacion DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Indexar tutores por alumno
$tutor_map = [];
foreach ($tutores_asignados as $t) $tutor_map[$t['alumno_nombre']] = $t;

// Alumno seleccionado
$alumno_sel = $_GET['alumno'] ?? '';
?>

<div class="mb-6">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-100 text-teal-700 text-xs font-bold uppercase mb-3">
        <span class="material-symbols-outlined text-[14px]">support_agent</span>
        Tutorías
    </div>
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-800 tracking-tight">Asignación de Tutores</h1>
            <p class="text-gray-500 mt-1">Vincula tutores con alumnos en situación de riesgo · <?php echo htmlspecialchars($carrera_info['nombre_largo']); ?></p>
        </div>
    </div>
</div>

<?php if ($msg): ?>
<div class="mb-6 p-4 rounded-2xl border-2 border-<?php echo $msg_type; ?>-100 bg-<?php echo $msg_type; ?>-50 text-<?php echo $msg_type; ?>-700 flex items-center gap-3 font-black text-sm animate-in slide-in-from-top-2 duration-300">
    <span class="material-symbols-outlined fill-1"><?php echo $msg_type === 'green' ? 'check_circle' : ($msg_type === 'yellow' ? 'info' : 'error'); ?></span>
    <?php echo htmlspecialchars($msg); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Lista de alumnos en riesgo -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-400 text-lg">warning</span>
                Alumnos en Riesgo (<?php echo count($alumnos_riesgo); ?>)
            </h3>
        </div>
        <div class="overflow-y-auto max-h-[600px] divide-y divide-gray-50">
            <?php if (empty($alumnos_riesgo)): ?>
            <div class="p-8 text-center text-gray-400">
                <span class="material-symbols-outlined text-4xl text-green-300 mb-2">verified</span>
                <p class="text-sm">¡Ningún alumno en riesgo!</p>
            </div>
            <?php else: ?>
            <?php foreach ($alumnos_riesgo as $al):
                $tiene_tutor = isset($tutor_map[$al['alumno_nombre']]);
                $isSelected  = $al['alumno_nombre'] === $alumno_sel;
            ?>
            <a href="index.php?v=asignar_tutor&alumno=<?php echo urlencode($al['alumno_nombre']); ?>"
               class="flex items-center gap-3 px-5 py-3 hover:bg-teal-50/50 transition-colors <?php echo $isSelected ? 'bg-teal-50 border-l-4 border-teal-500' : ''; ?>">
                <div class="w-9 h-9 rounded-full <?php echo $tiene_tutor ? 'bg-teal-100 text-teal-700' : 'bg-red-100 text-red-600'; ?> flex items-center justify-center font-black text-xs shrink-0">
                    <?php echo Utils::safeSubstr($al['alumno_nombre'], 0, 2); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($al['alumno_nombre']); ?></p>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($al['grupo_principal'] ?? '?'); ?> · <?php echo $al['total_faltas']; ?> faltas</p>
                </div>
                <div class="shrink-0">
                    <?php if ($tiene_tutor): ?>
                    <span class="bg-teal-100 text-teal-700 text-[10px] font-black px-2 py-0.5 rounded-full border border-teal-200">Asignado</span>
                    <?php else: ?>
                    <span class="material-symbols-outlined text-gray-300 text-[18px]">chevron_right</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulario de asignación -->
    <div class="space-y-4">
        <?php
        $alumno_info = null;
        foreach ($alumnos_riesgo as $al) {
            if ($al['alumno_nombre'] === $alumno_sel) { $alumno_info = $al; break; }
        }
        $tutor_actual = $tutor_map[$alumno_sel] ?? null;
        ?>

        <?php if ($alumno_sel && $alumno_info): ?>
        <!-- Info del alumno seleccionado -->
        <div class="bg-white rounded-2xl border border-teal-200 shadow-sm p-6">
            <div class="flex items-center gap-4 mb-5">
                <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center font-black text-xl border-2 border-red-200">
                    <?php echo Utils::safeSubstr($alumno_sel, 0, 2); ?>
                </div>
                <div>
                    <h2 class="text-xl font-black text-gray-800"><?php echo htmlspecialchars($alumno_sel); ?></h2>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-xs text-gray-600 border border-gray-200"><?php echo htmlspecialchars($alumno_info['grupo_principal'] ?? '?'); ?></span>
                        <span class="text-xs text-red-600 font-bold"><?php echo $alumno_info['total_faltas']; ?> faltas</span>
                        <a href="index.php?v=perfil_alumno&alumno=<?php echo urlencode($alumno_sel); ?>" class="text-xs text-brand-600 font-bold hover:underline">Ver perfil →</a>
                    </div>
                </div>
            </div>

            <form method="POST" action="index.php?v=asignar_tutor&alumno=<?php echo urlencode($alumno_sel); ?>" class="space-y-4">
                <input type="hidden" name="accion" value="asignar">
                <input type="hidden" name="alumno_nombre" value="<?php echo htmlspecialchars($alumno_sel); ?>">

                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Nombre del Tutor *</label>
                    <input type="text" name="tutor_nombre" required
                           value="<?php echo htmlspecialchars($tutor_actual['tutor_nombre'] ?? ''); ?>"
                           placeholder="Ej: Lic. María García"
                           class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 font-medium">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Email</label>
                        <input type="email" name="tutor_email"
                               value="<?php echo htmlspecialchars($tutor_actual['tutor_email'] ?? ''); ?>"
                               placeholder="email@ups.edu.mx"
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 font-medium">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Teléfono</label>
                        <input type="tel" name="tutor_tel"
                               value="<?php echo htmlspecialchars($tutor_actual['tutor_telefono'] ?? ''); ?>"
                               placeholder="442 123 4567"
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 font-medium">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide block mb-1.5">Nota de Seguimiento</label>
                    <textarea name="nota" rows="3" placeholder="Observaciones, plan de acción, compromisos..."
                              class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-400 font-medium resize-none"><?php echo htmlspecialchars($tutor_actual['nota'] ?? ''); ?></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-teal-600 hover:bg-teal-700 text-white py-2.5 px-4 rounded-xl font-bold text-sm transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">save</span>
                        <?php echo $tutor_actual ? 'Actualizar Tutor' : 'Asignar Tutor'; ?>
                    </button>
                    <?php if ($tutor_actual): ?>
                    <form method="POST" action="index.php?v=asignar_tutor" style="display:inline" onsubmit="return confirm('¿Desasignar tutor?')">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="alumno_nombre" value="<?php echo htmlspecialchars($alumno_sel); ?>">
                        <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-600 py-2.5 px-4 rounded-xl font-bold text-sm transition-all">
                            <span class="material-symbols-outlined text-sm">person_remove</span>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php elseif ($alumno_sel): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-center">
            <span class="material-symbols-outlined text-yellow-400 text-3xl">search</span>
            <p class="text-yellow-700 font-bold mt-2">Alumno no encontrado en la lista de riesgo.</p>
        </div>

        <?php else: ?>
        <!-- Estado inicial: instrucciones -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center">
            <span class="material-symbols-outlined text-5xl text-teal-200 mb-3">support_agent</span>
            <h3 class="font-bold text-gray-700 mb-2">Selecciona un alumno</h3>
            <p class="text-gray-400 text-sm">Haz clic en un alumno de la lista de la izquierda para asignarle o actualizar su tutor de seguimiento.</p>
        </div>
        <?php endif; ?>

        <!-- Tutores ya asignados -->
        <?php if (!empty($tutores_asignados)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-teal-500 text-lg">group</span>
                    Asignaciones Actuales (<?php echo count($tutores_asignados); ?>)
                </h3>
            </div>
            <div class="divide-y divide-gray-50 max-h-[300px] overflow-y-auto">
                <?php foreach ($tutores_asignados as $t): ?>
                <div class="px-5 py-3 flex items-start gap-3">
                    <span class="material-symbols-outlined text-teal-400 text-lg mt-0.5">person_check</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($t['alumno_nombre']); ?></p>
                        <p class="text-xs text-teal-600 font-bold"><?php echo htmlspecialchars($t['tutor_nombre']); ?></p>
                        <?php if ($t['tutor_email']): ?>
                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($t['tutor_email']); ?></p>
                        <?php endif; ?>
                        <?php if ($t['nota']): ?>
                        <p class="text-xs text-gray-500 mt-1 italic truncate"><?php echo htmlspecialchars($t['nota']); ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="index.php?v=asignar_tutor&alumno=<?php echo urlencode($t['alumno_nombre']); ?>"
                       class="text-gray-300 hover:text-teal-500 transition-colors shrink-0">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
