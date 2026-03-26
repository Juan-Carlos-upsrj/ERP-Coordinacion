<?php
/**
 * portal_alumnos/resultado.php
 * Busca a un estudiante a lo largo de TODAS las carreras disponibles
 * y calcula su asistencia.
 */

require_once '../config.php';
require_once '../app/core/Utils.php';


$input       = trim($_POST['id'] ?? $_GET['id'] ?? '');

// Fallback para mb_strtoupper si la extensión no está instalada
if (function_exists('mb_strtoupper')) {
    $input_clean = mb_strtoupper(preg_replace('/\s+/', '', $input), 'UTF-8');
} else {
    $input_clean = strtoupper(preg_replace('/\s+/', '', $input));
}

if (empty($input)) {
    header('Location: index.php');
    exit;
}

$alumno_encontrado  = null;
$carrera_encontrada = null;
$error = null;

// 1. Buscar en TODAS las carreras
foreach ($CARRERAS as $sigla => $carrera) {
    if (!$carrera['activa']) continue;

    try {
        $pdo = getConnection($carrera['db_name'], $carrera['carrera_id']);
        
        // 1.1 Intentar por Matrícula
        $stmt = $pdo->prepare("SELECT DISTINCT alumno_nombre, alumno_id FROM asistencia_clases WHERE alumno_id = ? LIMIT 1");
        $stmt->execute([$input]);
        $al = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($al) {
            $alumno_encontrado = $al;
            $carrera_encontrada = $carrera;
            break; // Encontrado!
        }

        // 1.2 Intentar por Nombre + Grupo (Búsqueda Fuzzy)
        $stmt = $pdo->query("SELECT DISTINCT alumno_nombre, alumno_id, grupo_nombre FROM asistencia_clases WHERE alumno_nombre IS NOT NULL");
        $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $search_str = preg_replace('/[^A-Z0-9]/', '', $input_clean);

        foreach ($todos as $st) {
            $name  = $st['alumno_nombre'];
            $group = $st['grupo_nombre'] ?? '';
            
            // Iniciales
            $words = explode(' ', trim($name));
            $initials = '';
            foreach ($words as $w) {
                if (!empty($w)) {
                    if (function_exists('mb_substr')) {
                        $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
                    } else {
                        $initials .= strtoupper(substr($w, 0, 1));
                    }
                }
            }
            
            if (function_exists('mb_strtoupper')) {
                $group_clean   = mb_strtoupper(preg_replace('/\s+/', '', $group), 'UTF-8');
            } else {
                $group_clean   = strtoupper(preg_replace('/\s+/', '', $group));
            }
            $candidate_str = preg_replace('/[^A-Z0-9]/', '', $initials . $group_clean);
            
            if (!empty($candidate_str) && !empty($search_str) && $candidate_str === $search_str) {
                $alumno_encontrado  = $st;
                $carrera_encontrada = $carrera;
                break 2; // Rompe el foreach de alumnos y el de carreras
            }
        }
    } catch (Exception $e) {
        // Ignorar si una BD falla y seguir con la siguiente
        continue;
    }
}


// 2. Extraer datos si lo encontró
if (!$alumno_encontrado) {
    $error = "No encontramos a ningún estudiante con matrícula o código: " . htmlspecialchars($input) . " en nuestras bases de datos.";
} else {
    $nombre_al = $alumno_encontrado['alumno_nombre'];
    $matri_al  = $alumno_encontrado['alumno_id'];

    try {
        // Reconectar si la conexión se perdió por scope
        $pdo = getConnection($carrera_encontrada['db_name'], $carrera_encontrada['carrera_id']);
        
        // Intenta obtener fechas de parciales de la configuración general
        $partial_1_end = date('Y') . '-02-28'; // Default razonable si falla
        $settings_path = dirname(__DIR__, 2) . '/AdminUPSRJ/settings_data.json';
        if (file_exists($settings_path)) {
            $set = json_decode(file_get_contents($settings_path), true);
            if (isset($set['PARTIAL_1_END'])) {
                $partial_1_end = $set['PARTIAL_1_END'];
            }
        }

        $sql = "SELECT materia_nombre, fecha, status FROM asistencia_clases 
                WHERE alumno_id = ? OR alumno_nombre = ? 
                ORDER BY materia_nombre ASC, fecha ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$matri_al, $nombre_al]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $materias_agg = [];
        foreach ($registros as $reg) {
            $mat = $reg['materia_nombre'];
            if (!isset($materias_agg[$mat])) {
                $materias_agg[$mat] = [
                    'materia_nombre' => $mat,
                    'total_clases' => 0,
                    'asistencias' => 0,
                    'faltas' => 0,
                    'fechas_p1' => [],
                    'fechas_p2' => []
                ];
            }
            $materias_agg[$mat]['total_clases']++;
            
            if (in_array($reg['status'], ['Presente', 'Retardo', 'Justificado', 'Intercambio'])) {
                $materias_agg[$mat]['asistencias']++;
            } elseif ($reg['status'] === 'Ausente') {
                $materias_agg[$mat]['faltas']++;
                if ($reg['fecha'] <= $partial_1_end) {
                    $materias_agg[$mat]['fechas_p1'][] = $reg['fecha'];
                } else {
                    $materias_agg[$mat]['fechas_p2'][] = $reg['fecha'];
                }
            }
        }
        $materias = array_values($materias_agg);

        $tot_clase = 0;
        $tot_asist = 0;
        foreach ($materias as $m) {
            $tot_clase += $m['total_clases'];
            $tot_asist += $m['asistencias'];
        }
        $porcentaje_global = ($tot_clase > 0) ? round(($tot_asist / $tot_clase) * 100, 1) : 0;
    } catch (Exception $e) {
        $error = "Error al obtener materias.";
    }
}
?>
<!DOCTYPE html>
<html lang="es" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Asistencia | Estudiante</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'sans': ['Inter', 'sans-serif'] },
                    colors: {
                        white: '#12121c',
                        black: '#ffffff',
                        slate: {
                            50: '#09090f', 100: '#1a1a2e', 200: 'rgba(255,255,255,0.07)', 300: 'rgba(255,255,255,0.15)',
                            400: '#64748b', 500: '#94a3b8', 600: '#cbd5e1', 700: '#e2e8f0', 800: '#f1f5f9', 900: '#ffffff',
                        },
                        gray: {
                            50: '#1a1a2e', 100: 'rgba(255,255,255,0.07)', 200: 'rgba(255,255,255,0.1)',
                            300: '#64748b', 400: '#94a3b8', 500: '#94a3b8', 600: '#cbd5e1', 700: '#e2e8f0', 800: '#f8fafc', 900: '#ffffff',
                        },
                        blue: {
                            50: 'rgba(124,58,237,0.1)', 100: 'rgba(124,58,237,0.2)', 200: 'rgba(124,58,237,0.3)',
                            300: '#a78bfa', 400: '#8b5cf6', 500: '#7c3aed', 600: '#7c3aed', 700: '#6d28d9', 800: '#5b21b6', 900: '#4c1d95',
                        },
                        emerald: {
                            50: 'rgba(6,182,212,0.1)', 100: 'rgba(6,182,212,0.2)', 200: 'rgba(6,182,212,0.3)',
                            300: '#67e8f9', 400: '#22d3ee', 500: '#06b6d4', 600: '#0891b2', 700: '#0e7490', 800: '#155e75', 900: '#164e63',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slideDown { animation: slideDown 0.4s ease-out forwards; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen relative font-sans text-slate-800">

    <!-- Header Fijo (Navegación Móvil) -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 text-slate-500 hover:text-blue-600 font-bold text-sm transition-colors p-2 -ml-2 rounded-lg hover:bg-blue-50">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                <span class="hidden sm:inline">Nueva Búsqueda</span>
            </a>
            
            <?php if ($carrera_encontrada): ?>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold px-2.5 py-1 text-white rounded-lg shadow-sm" style="background-color: <?php echo $carrera_encontrada['color_hex']; ?>;">
                    <?php echo htmlspecialchars($carrera_encontrada['nombre_corto']); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10 animate-slideDown">

        <?php if ($error): ?>
            <div class="bg-white rounded-3xl shadow-lg border border-slate-200 p-8 text-center max-w-md mx-auto relative overflow-hidden mt-10">
                <div class="absolute top-0 left-0 w-full h-1.5 bg-red-500"></div>
                <div class="w-16 h-16 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mx-auto mb-5 border border-red-100">
                    <span class="material-symbols-outlined text-[32px]">error</span>
                </div>
                <h2 class="text-xl font-black text-slate-900 mb-2">No encontrado</h2>
                <p class="text-sm text-slate-500 mb-8 leading-relaxed"><?php echo $error; ?></p>
                <a href="index.php" class="flex justify-center items-center gap-2 w-full px-6 py-3.5 bg-slate-900 focus:ring-4 focus:ring-slate-900/20 hover:bg-slate-800 text-white text-sm font-bold rounded-xl transition-all">
                    Volver a intentar
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                </a>
            </div>
        <?php else: ?>
            
            <!-- Dashboard Stats -->
            <div class="bg-white rounded-3xl shadow-md border border-slate-100 p-6 md:p-8 mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1.5" style="background: <?php echo $carrera_encontrada['color_hex']; ?>;"></div>
                
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                        </span>
                        <h4 class="text-[11px] font-bold text-slate-400 text-slate-500 uppercase tracking-widest">
                            Alumno Activo
                        </h4>
                    </div>
                    
                    <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-none mb-4"><?php echo htmlspecialchars($nombre_al); ?></h1>
                    
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-lg flex items-center gap-1.5 border border-slate-200">
                            <span class="material-symbols-outlined text-[14px]">badge</span> 
                            <?php echo htmlspecialchars($matri_al ?: 'Sin matrícula'); ?>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-6 md:border-l md:border-slate-100 md:pl-8 mt-4 md:mt-0 pt-4 md:pt-0 border-t border-slate-100 md:border-t-0 justify-between md:justify-end">
                    <div class="text-center md:text-left">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Total Clases</p>
                        <p class="text-3xl font-black text-slate-800"><?php echo $tot_clase; ?></p>
                    </div>
                    <div class="w-px h-12 bg-slate-200 hidden md:block"></div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Asistencia Global</p>
                        <?php $color_global = ($porcentaje_global >= 85) ? 'text-emerald-500' : 'text-red-500'; ?>
                        <p class="text-4xl font-black <?php echo $color_global; ?> tracking-tighter"><?php echo $porcentaje_global; ?>%</p>
                    </div>
                </div>
            </div>

            <!-- Warning Global (si es bajo) -->
            <?php if ($porcentaje_global < 85 && $tot_clase > 0): ?>
            <div class="mb-8 bg-red-50 border border-red-200 rounded-2xl p-4 flex items-start sm:items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white text-red-500 flex items-center justify-center shrink-0 border border-red-100 shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">warning</span>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-red-800">Riesgo Académico</h4>
                    <p class="text-xs text-red-600 mt-0.5">Tu asistencia global está por debajo del 85% mínimo requerido.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Header de Materias -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 px-1 gap-2">
                <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-slate-400 text-[20px]">analytics</span>
                    Desglose por Materia
                </h2>
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm"></span> <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Regular</span></span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500 shadow-sm"></span> <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Riesgo (&lt;85%)</span></span>
                </div>
            </div>
            
            <!-- Lista de Materias en Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pb-20">
                <?php foreach ($materias as $index => $m): 
                    $pct = ($m['total_clases'] > 0) ? round(($m['asistencias'] / $m['total_clases']) * 100, 1) : 0;
                    $is_danger = ($pct < 85);
                    $anim_delay = $index * 0.05;
                    $border_color = $is_danger ? 'border-red-200' : 'border-slate-100';
                ?>
                    <div class="bg-white rounded-2xl shadow-sm border <?php echo $border_color; ?> overflow-hidden group hover:shadow-md transition-all sm:animate-slideDown" style="animation-delay: <?php echo $anim_delay; ?>s;">
                        
                        <!-- Header / Botón de la tarjeta -->
                        <button onclick="toggleDetails(<?php echo $index; ?>)" class="w-full text-left p-5 flex items-center justify-between outline-none">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl <?php echo $is_danger ? 'bg-red-50 text-red-500' : 'bg-emerald-50 text-emerald-500'; ?> flex shrink-0 items-center justify-center">
                                    <span class="material-symbols-outlined text-[20px]"><?php echo $is_danger ? 'warning' : 'check_circle'; ?></span>
                                </div>
                                <div class="pr-2">
                                    <h3 class="font-bold text-slate-800 text-sm leading-snug">
                                        <?php echo htmlspecialchars($m['materia_nombre']); ?>
                                    </h3>
                                    <p class="text-[11px] font-medium text-slate-500 mt-1.5 flex flex-wrap gap-2 items-center">
                                        <span>Asistió: <strong class="text-slate-700"><?php echo $m['asistencias']; ?></strong> / <?php echo $m['total_clases']; ?></span>
                                        <span class="text-slate-300">|</span> 
                                        <span>Faltas: <strong class="text-red-500"><?php echo $m['faltas']; ?></strong></span>
                                        <?php if(isset($m['fechas_p1'])): ?>
                                        <span class="text-[10px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">P1: <?php echo count($m['fechas_p1']); ?></span>
                                        <span class="text-[10px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">P2: <?php echo count($m['fechas_p2']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="text-right shrink-0 flex items-center gap-2">
                                <div class="text-xl font-black tracking-tighter <?php echo $is_danger ? 'text-red-500' : 'text-slate-800'; ?>">
                                    <?php echo $pct; ?><span class="text-[13px] opacity-60 ml-0.5">%</span>
                                </div>
                                <span class="material-symbols-outlined text-slate-400 transition-transform duration-300" id="icon-<?php echo $index; ?>">expand_more</span>
                            </div>
                        </button>
                        
                        <!-- Detalle Expandible -->
                        <div id="details-<?php echo $index; ?>" class="hidden border-t border-slate-100 bg-slate-50 p-5">
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Fechas de Inasistencia</h4>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Parcial 1 -->
                                <div>
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <span class="text-xs font-bold text-slate-700">1er Parcial</span>
                                        <span class="text-[10px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded">(<?php echo count($m['fechas_p1'] ?? []); ?> Faltas)</span>
                                    </div>
                                    <?php if (!empty($m['fechas_p1'])): ?>
                                        <ul class="space-y-1.5 pl-1">
                                            <?php foreach($m['fechas_p1'] as $f): ?>
                                                <li class="text-[11px] font-medium text-slate-600 flex items-center gap-2">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400 shadow-sm"></span> 
                                                    <?php echo date('d M Y', strtotime($f)); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-[11px] text-slate-400 italic pl-1">Sin inasistencias.</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Parcial 2 -->
                                <div>
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <span class="text-xs font-bold text-slate-700">2do Parcial</span>
                                        <span class="text-[10px] font-bold text-red-500 bg-red-50 px-1.5 py-0.5 rounded">(<?php echo count($m['fechas_p2'] ?? []); ?> Faltas)</span>
                                    </div>
                                    <?php if (!empty($m['fechas_p2'])): ?>
                                        <ul class="space-y-1.5 pl-1">
                                            <?php foreach($m['fechas_p2'] as $f): ?>
                                                <li class="text-[11px] font-medium text-slate-600 flex items-center gap-2">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400 shadow-sm"></span> 
                                                    <?php echo date('d M Y', strtotime($f)); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-[11px] text-slate-400 italic pl-1">Sin inasistencias.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

    <script>
        function toggleDetails(idx) {
            const el = document.getElementById('details-' + idx);
            const icon = document.getElementById('icon-' + idx);
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                icon.style.transform = 'rotate(180deg)';
            } else {
                el.classList.add('hidden');
                icon.style.transform = 'rotate(0deg)';
            }
        }
    </script>
</body>
</html>
