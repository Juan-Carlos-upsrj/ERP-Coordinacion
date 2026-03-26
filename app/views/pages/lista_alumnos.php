<?php
/**
 * app/views/pages/lista_alumnos.php
 * Lista de alumnos ÚNICOS (agrupados por nombre) inscritos en la carrera.
 * Nota: el conteo desde asistencia_clases puede diferir del número real de alumnos
 * porque la app móvil genera múltiples UIDs para el mismo alumno.
 * Aquí mostramos alumnos únicos por nombre con sus estadísticas agregadas.
 */
$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

$filtro_grupo  = $_GET['grupo']  ?? '';
$filtro_buscar = $_GET['q']      ?? '';

// Grupos disponibles
$grupos = $pdo->query(
    "SELECT DISTINCT grupo_nombre FROM asistencia_clases WHERE grupo_nombre IS NOT NULL ORDER BY grupo_nombre"
)->fetchAll(PDO::FETCH_COLUMN);

// ── Query Optimizada con CTEs (PostgreSQL) ────────────────────────────────────
// Usamos CTEs para normalizar nombres y calcular grupos principales una sola vez.
$sql = "WITH raw_data AS (
            SELECT 
                *,
                REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(alumno_nombre)), 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N') AS alumno_nombre_norm
            FROM asistencia_clases
        ),
        student_stats AS (
            SELECT 
                alumno_nombre_norm,
                MAX(alumno_nombre)          AS alumno_nombre,
                COUNT(DISTINCT alumno_id)   AS total_ids,
                COUNT(*)                    AS total_registros,
                SUM(CASE WHEN status = 'Ausente'  THEN 1 ELSE 0 END) AS total_faltas,
                SUM(CASE WHEN status IN ('Presente','Retardo','Justificado','Intercambio') THEN 1 ELSE 0 END) AS total_presentes,
                MAX(fecha)                  AS ultima_clase
            FROM raw_data
            GROUP BY alumno_nombre_norm
        ),
        group_counts AS (
            SELECT 
                alumno_nombre_norm, 
                grupo_nombre, 
                COUNT(*) as qty,
                ROW_NUMBER() OVER(PARTITION BY alumno_nombre_norm ORDER BY COUNT(*) DESC) as rn
            FROM raw_data
            GROUP BY alumno_nombre_norm, grupo_nombre
        )
        SELECT 
            s.*,
            g.grupo_nombre AS grupo_principal,
            ROUND((1.0 * s.total_presentes / NULLIF(s.total_registros, 0)) * 100, 1) AS pct_asistencia
        FROM student_stats s
        LEFT JOIN group_counts g ON s.alumno_nombre_norm = g.alumno_nombre_norm AND g.rn = 1
        WHERE 1=1";

$params = [];

if ($filtro_grupo) {
    // Para el filtro de grupo, buscamos en group_counts si el alumno pertenece a ese grupo
    $sql .= " AND s.alumno_nombre_norm IN (SELECT x.alumno_nombre_norm FROM group_counts x WHERE x.grupo_nombre = ?)";
    $params[] = $filtro_grupo;
}
if ($filtro_buscar) {
    $sql .= " AND s.alumno_nombre ILIKE ?";
    $params[] = "%{$filtro_buscar}%";
}

$sql .= " ORDER BY s.alumno_nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alumnos = $stmt->fetchAll();
$total   = count($alumnos);

// Alumnos con más de 1 ID (duplicados detectados)
$con_duplicados = array_filter($alumnos, fn($a) => (int)$a['total_ids'] > 1);
$num_duplicados = count($con_duplicados);
?>

<div class="mb-10">
    <h1 class="text-4xl font-black text-gray-800 tracking-tight">Población Estudiantil</h1>
</div>

<!-- Accesos Rápidos (HUB) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
    
    <a href="index.php?v=duplicados" class="group bg-white p-4 rounded-[2rem] border border-gray-100 shadow-sm hover:shadow-xl hover:border-amber-200 transition-all relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-amber-50 rounded-full group-hover:scale-110 transition-transform"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mb-3 transition-transform group-hover:rotate-12">
                <span class="material-symbols-outlined text-[20px]">cleaning_services</span>
            </div>
            <h3 class="font-black text-gray-800 text-base leading-tight">Saneamiento<br>de Duplicados</h3>
            <p class="text-xs text-gray-400 mt-2 font-bold uppercase italic tracking-tighter">Limpiar base de datos</p>
            <?php if ($num_duplicados > 0): ?>
            <div class="mt-4 inline-flex items-center gap-2 px-2 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-black">
                <?php echo $num_duplicados; ?> PENDIENTES
            </div>
            <?php endif; ?>
        </div>
    </a>

    <a href="index.php?v=reporte_alumnos" class="group bg-white p-4 rounded-[2rem] border border-gray-100 shadow-sm hover:shadow-xl hover:border-rose-200 transition-all relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-rose-50 rounded-full group-hover:scale-110 transition-transform"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mb-3 transition-transform group-hover:rotate-12">
                <span class="material-symbols-outlined text-[20px]">warning</span>
            </div>
            <h3 class="font-black text-gray-800 text-base leading-tight">Monitor de<br>Riesgo Crítico</h3>
            <p class="text-xs text-gray-400 mt-2 font-bold uppercase italic tracking-tighter">Alumnos con alto ausentismo</p>
        </div>
    </a>

    <a href="index.php?v=top_remediales" class="group bg-white p-4 rounded-[2rem] border border-gray-100 shadow-sm hover:shadow-xl hover:border-indigo-200 transition-all relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-indigo-50 rounded-full group-hover:scale-110 transition-transform"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-3 transition-transform group-hover:rotate-12">
                <span class="material-symbols-outlined text-[20px]">emergency_heat</span>
            </div>
            <h3 class="font-black text-gray-800 text-base leading-tight">Top<br>Remediales</h3>
            <p class="text-xs text-gray-400 mt-2 font-bold uppercase italic tracking-tighter">Seguimiento académico</p>
        </div>
    </a>

</div>


<!-- Filtros -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
    <form method="GET" action="index.php" class="flex flex-wrap gap-3">
        <input type="hidden" name="v" value="lista_alumnos">
        <div class="relative flex-1 min-w-[200px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
            <input type="text" name="q" value="<?php echo htmlspecialchars($filtro_buscar); ?>" 
                   placeholder="Buscar por nombre..." 
                   class="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-400 font-medium">
        </div>
        <select name="grupo" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-400 min-w-[160px]">
            <option value="">Todos los grupos</option>
            <?php foreach ($grupos as $g): ?>
            <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $filtro_grupo === $g ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($g); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all">Filtrar</button>
        <?php if ($filtro_buscar || $filtro_grupo): ?>
        <a href="index.php?v=lista_alumnos" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2.5 rounded-xl text-sm font-bold transition-all">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h3 class="text-sm font-bold text-gray-600">
            Mostrando <span class="text-blue-600 font-black"><?php echo $total; ?></span> alumnos únicos
            <?php if ($filtro_grupo): ?>(Grupo: <?php echo htmlspecialchars($filtro_grupo); ?>)<?php endif; ?>
        </h3>
        <span class="text-xs text-gray-400">Agrupados por nombre · Sin duplicados</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-400 uppercase bg-gray-50/30 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-4 text-left font-bold">Alumno</th>
                    <th class="px-5 py-4 text-left font-bold">Grupo Principal</th>
                    <th class="px-5 py-4 text-center font-bold">% Asistencia</th>
                    <th class="px-5 py-4 text-center font-bold">Faltas</th>
                    <th class="px-5 py-4 text-center font-bold">Última Clase</th>
                    <th class="px-5 py-4 text-right font-bold">Perfil</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($total > 0): ?>
                <?php foreach ($alumnos as $al):
                    $pct = (float)$al['pct_asistencia'];
                    $pctColor  = $pct >= 85 ? 'text-green-600' : ($pct >= 70 ? 'text-yellow-600' : 'text-red-600');
                    $barColor  = $pct >= 85 ? 'bg-green-500'   : ($pct >= 70 ? 'bg-yellow-500'   : 'bg-red-500');
                    $isDup     = (int)$al['total_ids'] > 1;
                ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors <?php echo $isDup ? 'bg-amber-50/20' : ''; ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-black text-xs uppercase shrink-0">
                                <?php echo Utils::safeSubstr($al['alumno_nombre'], 0, 2); ?>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($al['alumno_nombre']); ?></p>
                                <?php if ($isDup): ?>
                                <span class="text-[10px] font-bold text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded-full">
                                    <?php echo $al['total_ids']; ?> IDs
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-600 text-xs font-bold border border-gray-200">
                            <?php echo htmlspecialchars($al['grupo_principal'] ?? '—'); ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <div class="inline-flex flex-col items-center gap-1">
                            <span class="text-sm font-black <?php echo $pctColor; ?>"><?php echo number_format($pct, 1); ?>%</span>
                            <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full rounded-full <?php echo $barColor; ?>" style="width:<?php echo min(100, $pct); ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <?php if ((int)$al['total_faltas'] > 0): ?>
                        <span class="bg-red-50 text-red-600 border border-red-200 px-2.5 py-0.5 rounded-full text-xs font-black">
                            <?php echo $al['total_faltas']; ?>
                        </span>
                        <?php else: ?>
                        <span class="text-green-500 font-black text-sm">✓</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-center text-xs text-gray-400 font-medium">
                        <?php echo $al['ultima_clase'] ?? '—'; ?>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <a href="index.php?v=perfil_alumno&alumno=<?php echo urlencode($al['alumno_nombre']); ?>"
                           class="w-8 h-8 rounded-lg bg-white border border-gray-200 hover:border-blue-400 hover:text-blue-600 text-gray-400 inline-flex items-center justify-center transition-colors">
                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="py-16 text-center text-gray-400">
                    <span class="material-symbols-outlined text-5xl mb-3 opacity-30">school</span>
                    <p class="text-sm">Sin alumnos registrados aún.</p>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
