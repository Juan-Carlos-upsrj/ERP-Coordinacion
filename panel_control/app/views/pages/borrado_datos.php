<?php
/**
 * Panel de Control — borrado_datos.php
 * Herramienta de destrucción controlada de datos.
 */

$carrera_sigla = $_GET['carrera'] ?? '';
$mensaje = '';
$tipo_mensaje = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar_borrado'])) {
    $carrera_dest = $_POST['carrera_id'] ?? '';
    $filtro = $_POST['filtro'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';

    if (!isset($CARRERAS[$carrera_dest])) {
        $mensaje = "Carrera no válida.";
        $tipo_mensaje = 'error';
    } else {
        try {
            $c_info = $CARRERAS[$carrera_dest];
            $pdo_dest = getConnection($c_info['db_name'], $c_info['carrera_id']);
            
            $sql = "DELETE FROM asistencia_clases WHERE 1=1";
            $params = [];

            if ($filtro === 'fecha') {
                $sql .= " AND fecha BETWEEN ? AND ?";
                $params = [$fecha_inicio, $fecha_fin];
            } elseif ($filtro === 'grupo') {
                $sql .= " AND grupo_nombre = ?";
                $params = [$valor];
            } elseif ($filtro === 'alumno') {
                $sql .= " AND alumno_nombre ILIKE ?";
                $params = ["%$valor%"];
            } elseif ($filtro === 'todo') {
                // Borrado total (Peligroso)
                $sql = "DELETE FROM asistencia_clases";
            }

            $stmt = $pdo_dest->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->rowCount();

            $mensaje = "Se han eliminado <b>$count</b> registros exitosamente de la carrera <b>$carrera_dest</b>.";
            $tipo_mensaje = 'success';
        } catch (Exception $e) {
            $mensaje = "Error al borrar datos: " . $e->getMessage();
            $tipo_mensaje = 'error';
        }
    }
}
?>

<div class="mb-8">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-50 text-red-600 text-[10px] font-black uppercase mb-3 border border-red-100 shadow-sm">
        <span class="material-symbols-outlined text-[14px]">warning</span>
        Herramienta Crítica
    </div>
    <h1 class="text-3xl font-black text-slate-800 tracking-tight">Borrado de Datos</h1>
    <p class="text-slate-500 mt-1 italic">Elimina de forma permanente registros incorrectos o redundantes de las bases de datos.</p>
</div>

<?php if ($mensaje): ?>
    <div class="mb-8 p-4 rounded-2xl border <?php 
        echo $tipo_mensaje === 'success' ? 'bg-green-50 border-green-100 text-green-700' : 
            ($tipo_mensaje === 'error' ? 'bg-red-50 border-red-100 text-red-700' : 'bg-blue-50 border-blue-100 text-blue-700'); 
    ?> flex items-center gap-4">
        <span class="material-symbols-outlined">
            <?php echo $tipo_mensaje === 'success' ? 'check_circle' : ($tipo_mensaje === 'error' ? 'error' : 'info'); ?>
        </span>
        <p class="text-sm font-bold uppercase tracking-tight"><?php echo $mensaje; ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Configuración del Borrado -->
    <div class="lg:col-span-2 space-y-6">
        <form method="POST" class="bg-white rounded-[2.5rem] border border-gray-100 shadow-xl shadow-slate-100/50 overflow-hidden">
            <div class="p-8 md:p-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                    <!-- Carrera -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Carrera Destino</label>
                        <select name="carrera_id" required class="w-full bg-gray-50 border border-gray-100 rounded-2xl py-4 px-5 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-slate-50 transition-all cursor-pointer">
                            <option value="">Selecciona una carrera...</option>
                            <?php foreach ($CARRERAS as $sigla => $c): if(!$c['activa']) continue; ?>
                                <option value="<?php echo $sigla; ?>" <?php echo $carrera_sigla === $sigla ? 'selected' : ''; ?>>
                                    <?php echo $sigla; ?> — <?php echo htmlspecialchars($c['nombre_largo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tipo de Filtro -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Filtrar por</label>
                        <select name="filtro" id="filtroSelect" onchange="updateFiltroUI()" required class="w-full bg-gray-50 border border-gray-100 rounded-2xl py-4 px-5 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-slate-50 transition-all cursor-pointer">
                            <option value="fecha">Rango de Fechas</option>
                            <option value="grupo">Nombre de Grupo</option>
                            <option value="alumno">Nombre de Alumno</option>
                            <option value="todo">BORRAR TODO (Vaciado)</option>
                        </select>
                    </div>
                </div>

                <!-- Inputs Dinámicos -->
                <div id="ui-fecha" class="filto-ui mb-10">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" class="w-full bg-gray-50 border border-gray-100 rounded-2xl py-4 px-5 text-sm font-bold text-slate-700 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Fecha Fin</label>
                            <input type="date" name="fecha_fin" class="w-full bg-gray-50 border border-gray-100 rounded-2xl py-4 px-5 text-sm font-bold text-slate-700 outline-none">
                        </div>
                    </div>
                </div>

                <div id="ui-valor" class="filto-ui mb-10 hidden">
                    <label id="valorLabel" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Valor a buscar</label>
                    <input type="text" name="valor" placeholder="..." class="w-full bg-gray-50 border border-gray-100 rounded-2xl py-4 px-5 text-sm font-bold text-slate-700 outline-none">
                    <p class="text-[10px] text-slate-400 mt-2 font-bold italic uppercase tracking-tighter">Se borrarán todos los registros que coincidan exactamente con este valor.</p>
                </div>

                <div class="bg-amber-50 rounded-3xl p-6 border border-amber-100 mb-10">
                    <div class="flex items-start gap-4">
                        <span class="material-symbols-outlined text-amber-500">priority_high</span>
                        <div>
                            <p class="text-xs font-black text-amber-800 uppercase mb-1">Confirmación de Seguridad</p>
                            <p class="text-[11px] text-amber-700 font-medium italic">Esta acción es IRREVERSIBLE. Una vez eliminados, los datos no podrán ser recuperados. Por favor, asegúrate de haber seleccionado los filtros correctos.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" name="confirmar_borrado" required class="w-6 h-6 rounded-lg border-gray-200 text-red-600 focus:ring-red-500 transition-all">
                            <span class="text-xs font-black text-slate-600 uppercase group-hover:text-red-600 transition-colors">Entiendo los riesgos y deseo proceder</span>
                        </label>
                    </div>
                    <button type="submit" class="bg-red-600 hover:bg-black text-white font-black px-8 py-4 rounded-2xl transition-all shadow-xl shadow-red-100 uppercase tracking-widest text-xs">
                        Ejecutar Purga
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Guía y Ayuda -->
    <div class="space-y-6">
        <div class="bg-white rounded-[2rem] border border-gray-100 p-8 shadow-sm">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-500">info</span>
                Casos de Uso
            </h3>
            <ul class="space-y-6">
                <li>
                    <p class="text-[10px] font-black text-indigo-500 uppercase mb-1 tracking-tighter italic">Error de Carga masiva</p>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">Si se importaron registros de un día erróneo, usa el filtro de <b>Rango de Fechas</b>.</p>
                </li>
                <li>
                    <p class="text-[10px] font-black text-indigo-500 uppercase mb-1 tracking-tighter italic">Grupos Inexistentes</p>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">Si hay grupos que se crearon por error en la app móvil, bórralos usando el filtro de <b>Nombre de Grupo</b>.</p>
                </li>
                <li>
                    <p class="text-[10px] font-black text-indigo-500 uppercase mb-1 tracking-tighter italic">Alumnos de Prueba</p>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">Limpia registros generados durante periodos de prueba filtrando por el <b>Nombre del Alumno</b>.</p>
                </li>
            </ul>
        </div>

        <div class="bg-slate-900 rounded-[2rem] p-8 text-white shadow-xl shadow-slate-200 relative overflow-hidden">
             <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-white/5 text-9xl">database</span>
             <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Estado del Servidor</p>
             <div class="flex items-center gap-3 mb-6">
                 <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                 <p class="text-sm font-black">PostgreSQL Online</p>
             </div>
             <p class="text-[11px] text-slate-400 italic leading-relaxed">Todas las transacciones de purga se ejecutan mediante RLS (Row Level Security) garantizando la integridad de cada esquema de carrera.</p>
        </div>
    </div>
</div>

<script>
function updateFiltroUI() {
    const filtro = document.getElementById('filtroSelect').value;
    const uiFecha = document.getElementById('ui-fecha');
    const uiValor = document.getElementById('ui-valor');
    const valorLabel = document.getElementById('valorLabel');

    uiFecha.classList.add('hidden');
    uiValor.classList.add('hidden');

    if (filtro === 'fecha') {
        uiFecha.classList.remove('hidden');
    } else if (filtro === 'grupo') {
        uiValor.classList.remove('hidden');
        valorLabel.innerText = 'Nombre exacto del Grupo';
    } else if (filtro === 'alumno') {
        uiValor.classList.remove('hidden');
        valorLabel.innerText = 'Nombre o parte del nombre del Alumno';
    }
}
</script>
