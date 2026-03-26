<?php
/**
 * Panel de Control — settings.php
 * Configuración técnica y estado global del ecosistema.
 */

$carrera_sigla = $_GET['carrera'] ?? $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info  = $CARRERAS[$carrera_sigla];

// Estado de todas las carreras
$carreras_status = [];
foreach ($CARRERAS as $sigla => $c) {
    $ok = false;
    $error = null;
    $count = 0;
    if ($c['activa']) {
        try {
            $p = getConnection($c['db_name'], $c['carrera_id']);
            $ok = true;
            $count = (int)$p->query("SELECT COUNT(*) FROM asistencia_clases")->fetchColumn();
        } catch (Exception $e) {
            $ok = false;
            $error = $e->getMessage();
        }
    }
    $carreras_status[$sigla] = ['ok' => $ok, 'error' => $error, 'registros' => $count];
}

// Info de la carrera seleccionada
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);
$bd_version = '—';
try {
    $bd_version = $pdo->query("SELECT version()")->fetchColumn();
    $bd_version = explode(' ', $bd_version)[0] . ' ' . (explode(' ', $bd_version)[1] ?? '');
} catch (Exception $e) {}

?>

<div class="mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div>
        <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Centro de Configuración</h1>
        <p class="text-slate-500 mt-1">Monitoreo técnico de la infraestructura de Coordinación.</p>
    </div>
    
    <div class="bg-indigo-600 text-white px-6 py-3 rounded-2xl shadow-xl shadow-indigo-100 flex items-center gap-3">
        <span class="material-symbols-outlined text-indigo-200">shield_person</span>
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-300 leading-none mb-1">Usuario Admin</p>
            <p class="text-sm font-bold leading-none"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin'); ?></p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Columna Izquierda: Ecosistema -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Estado de Conexiones -->
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-50 flex items-center justify-between">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2 italic">
                    <span class="material-symbols-outlined text-indigo-500">hub</span>
                    Ecosistema de Bases de Datos
                </h2>
                <span class="text-[10px] font-black text-slate-400">POSTGRESQL CLUSTER</span>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($CARRERAS as $sigla => $c): 
                    $st = $carreras_status[$sigla];
                ?>
                <div class="px-8 py-5 flex items-center gap-6 group hover:bg-slate-50/50 transition-colors">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-lg group-hover:scale-110 transition-transform"
                         style="background-color: <?php echo $c['color_hex']; ?>20">
                        <span class="material-symbols-outlined" style="color: <?php echo $c['color_hex']; ?>"><?php echo $c['icono']; ?></span>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="font-black text-slate-800"><?php echo $sigla; ?></h3>
                            <span class="text-[10px] font-black italic text-slate-400 uppercase tracking-tighter"><?php echo htmlspecialchars($c['nombre_largo']); ?></span>
                        </div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase mt-1">DB: <?php echo $c['db_name']; ?> · RLS: <?php echo $c['carrera_id']; ?></p>
                    </div>
                    <div class="text-right">
                        <?php if ($st['ok']): ?>
                            <p class="text-lg font-black text-slate-700"><?php echo number_format($st['registros']); ?></p>
                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-[10px] font-black border border-green-200">ONLINE</span>
                        <?php else: ?>
                            <p class="text-sm font-black text-red-500 uppercase italic">Error Conexión</p>
                            <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-[10px] font-black border border-red-200">OFFLINE</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Métricas de Servidor -->
        <div class="bg-slate-900 rounded-3xl p-8 text-white relative overflow-hidden shadow-2xl">
            <span class="material-symbols-outlined absolute -right-8 -bottom-8 text-white/5 text-[150px]">dns</span>
            <h2 class="text-lg font-black mb-8 flex items-center gap-3">
                <span class="material-symbols-outlined text-indigo-400">terminal</span>
                Entorno de Ejecución
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">PHP Version</p>
                    <p class="font-black text-xl tracking-tight"><?php echo PHP_VERSION; ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Web Server</p>
                    <p class="font-black text-xl tracking-tight">Apache/2.4</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">DB Engine</p>
                    <p class="font-black text-xl tracking-tight"><?php echo htmlspecialchars($bd_version); ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">System OS</p>
                    <p class="font-black text-xl tracking-tight"><?php echo PHP_OS; ?></p>
                </div>
            </div>
            
            <div class="mt-12 pt-8 border-t border-white/10 flex flex-wrap gap-4">
                <div class="bg-white/5 px-4 py-2 rounded-xl border border-white/10">
                    <p class="text-[10px] font-black text-slate-500 uppercase leading-none mb-1">Zona Horaria</p>
                    <p class="text-xs font-bold"><?php echo date_default_timezone_get(); ?></p>
                </div>
                <div class="bg-white/5 px-4 py-2 rounded-xl border border-white/10">
                    <p class="text-[10px] font-black text-slate-500 uppercase leading-none mb-1">Memory Limit</p>
                    <p class="text-xs font-bold"><?php echo ini_get('memory_limit'); ?></p>
                </div>
                <div class="bg-white/5 px-4 py-2 rounded-xl border border-white/10">
                    <p class="text-[10px] font-black text-slate-500 uppercase leading-none mb-1">Upload Max</p>
                    <p class="text-xs font-bold"><?php echo ini_get('upload_max_filesize'); ?></p>
                </div>
            </div>
        </div>

    </div>

    <!-- Columna Derecha: Acciones y Auditoría -->
    <div class="space-y-8">
        
        <!-- Acciones de Mantenimiento -->
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-50 bg-slate-50/30">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest italic">Mantenimiento</h3>
            </div>
            <div class="p-6 space-y-3">
                <button class="w-full flex items-center justify-between p-4 rounded-2xl bg-slate-50 hover:bg-indigo-600 group transition-all duration-300">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-indigo-500 group-hover:text-white">cleaning_services</span>
                        <span class="text-xs font-black text-slate-700 group-hover:text-white uppercase">Limpiar Caché</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-300 group-hover:text-white text-sm">arrow_forward_ios</span>
                </button>
                <button class="w-full flex items-center justify-between p-4 rounded-2xl bg-slate-50 hover:bg-slate-800 group transition-all duration-300">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-slate-400 group-hover:text-white">database</span>
                        <span class="text-xs font-black text-slate-700 group-hover:text-white uppercase">Sincronizar DBs</span>
                    </div>
                    <span class="material-symbols-outlined text-slate-300 group-hover:text-white text-sm">arrow_forward_ios</span>
                </button>
                <button class="w-full flex items-center justify-between p-4 rounded-2xl bg-red-50 hover:bg-red-600 group transition-all duration-300">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-red-400 group-hover:text-white">lock</span>
                        <span class="text-xs font-black text-red-700 group-hover:text-white uppercase">Hard Lockdown</span>
                    </div>
                    <span class="material-symbols-outlined text-red-200 group-hover:text-white text-sm">priority_high</span>
                </button>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="bg-indigo-50 rounded-3xl p-6 border border-indigo-100">
            <h3 class="text-xs font-black text-indigo-700 uppercase tracking-widest mb-4 italic">Seguridad</h3>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-green-500 text-lg">verified_user</span>
                    <div>
                        <p class="text-[10px] font-black text-indigo-900 leading-none mb-1 uppercase tracking-tight">RLS NATIVO</p>
                        <p class="text-[11px] text-indigo-500 font-medium">El aislamiento por carrera se aplica forzosamente a nivel de base de datos Postgres.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-indigo-400 text-lg">history</span>
                    <div>
                        <p class="text-[10px] font-black text-indigo-900 leading-none mb-1 uppercase tracking-tight">AUDITORÍA ACTIVA</p>
                        <p class="text-[11px] text-indigo-500 font-medium">Todas las consultas administrativas son registradas para análisis forense si es necesario.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
