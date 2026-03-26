<?php
/**
 * app/views/pages/calificaciones.php
 * Vista de calificaciones finales de los alumnos.
 * Usa la tabla `calificaciones_finales` — si está vacía, muestra estado vacío claro.
 */
$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// ── Verificar que la tabla tenga datos ────────────────────────────────────────
try {
    $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'calificaciones_finales' AND table_schema = 'public' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
    
    $filtro_q   = $_GET['q']      ?? '';
    $filtro_mat = $_GET['materia'] ?? '';
    
    $sql = "SELECT * FROM calificaciones_finales WHERE 1=1";
    $params = [];
    
    if ($filtro_q) {
        $sql .= " AND (alumno_nombre ILIKE ? OR alumno_id::text ILIKE ?)";
        $params[] = "%$filtro_q%";
        $params[] = "%$filtro_q%";
    }
    if ($filtro_mat) {
        $sql .= " AND materia_nombre ILIKE ?";
        $params[] = "%$filtro_mat%";
    }
    $sql .= " ORDER BY alumno_nombre, materia_nombre LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $califs = $stmt->fetchAll();
    $total  = count($califs);
    
    // Materias únicas para el filtro
    $materias = $pdo->query("SELECT DISTINCT materia_nombre FROM calificaciones_finales ORDER BY materia_nombre")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $califs  = [];
    $total   = 0;
    $cols    = [];
    $materias = [];
}
?>

<div class="mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold uppercase mb-3">
            <span class="material-symbols-outlined text-[14px]">grade</span>
            Evaluación Académica
        </div>
        <h1 class="text-3xl font-black text-gray-800">Calificaciones Finales</h1>
        <p class="text-gray-500 mt-1">
            <?php echo $total > 0 ? "<span class='font-bold text-gray-700'>{$total}</span> registros cargados" : "Sin datos disponibles aún"; ?>
        </p>
    </div>
</div>

<?php if ($total === 0): ?>
<!-- Estado vacío -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
    <div class="w-20 h-20 bg-indigo-50 rounded-3xl flex items-center justify-center mx-auto mb-5">
        <span class="material-symbols-outlined text-4xl text-indigo-300">grade</span>
    </div>
    <h2 class="text-2xl font-black text-gray-700 mb-2">Sin Calificaciones Registradas</h2>
    <p class="text-gray-400 max-w-md mx-auto text-sm">
        La tabla <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs font-mono">calificaciones_finales</code> está vacía.
        Las calificaciones se cargan desde la aplicación móvil al finalizar el período.
    </p>
    <div class="mt-6 inline-flex items-center gap-2 bg-indigo-50 text-indigo-600 px-4 py-2 rounded-xl text-sm font-bold">
        <span class="material-symbols-outlined text-base">info</span>
        Las calificaciones aparecerán aquí una vez que sean sincronizadas.
    </div>
</div>

<?php else: ?>
<!-- Filtros -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
    <form method="GET" action="index.php" class="flex flex-wrap gap-3">
        <input type="hidden" name="v" value="calificaciones">
        <div class="relative flex-1 min-w-[200px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
            <input type="text" name="q" value="<?php echo htmlspecialchars($filtro_q); ?>" placeholder="Buscar alumno..."
                   class="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-400 focus:border-transparent">
        </div>
        <?php if (count($materias) > 0): ?>
        <select name="materia" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-indigo-400 min-w-[160px]">
            <option value="">Todas las materias</option>
            <?php foreach ($materias as $m): ?>
            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $filtro_mat === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all">Filtrar</button>
    </form>
</div>

<!-- Tabla de calificaciones -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-xs text-gray-400 uppercase border-b border-gray-100 bg-gray-50/30">
            <tr>
                <th class="px-5 py-4 text-left font-bold">Alumno</th>
                <?php foreach ($cols as $col): 
                    if (in_array($col, ['id','alumno_id','alumno_nombre','carrera_id','grupo_id','materia_id'])) continue; ?>
                <th class="px-5 py-4 text-center font-bold"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($col))); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($califs as $c): ?>
            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs uppercase shrink-0">
                            <?php echo substr($c['alumno_nombre'] ?? '??', 0, 2); ?>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800"><?php echo htmlspecialchars($c['alumno_nombre'] ?? '—'); ?></p>
                            <p class="text-[10px] text-gray-400">ID: <?php echo htmlspecialchars((string)($c['alumno_id'] ?? '')); ?></p>
                        </div>
                    </div>
                </td>
                <?php foreach ($cols as $col): 
                    if (in_array($col, ['id','alumno_id','alumno_nombre','carrera_id','grupo_id','materia_id'])) continue;
                    $val = $c[$col] ?? '—';
                    $numColor = '';
                    if (is_numeric($val)) {
                        $numColor = $val >= 70 ? 'text-green-600' : ($val >= 60 ? 'text-yellow-600' : 'text-red-600');
                    }
                ?>
                <td class="px-5 py-3.5 text-center <?php echo $numColor; ?> font-bold"><?php echo htmlspecialchars((string)$val); ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
