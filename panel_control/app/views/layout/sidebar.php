<?php
/**
 * Panel de Control — sidebar.php
 */
$vista_actual = $_GET['v'] ?? 'dashboard';
?>
<aside class="w-64 flex-shrink-0 bg-white border-r border-gray-100 flex flex-col h-full z-20 shadow-sm relative">
    <!-- Header -->
    <div class="h-20 flex items-center gap-3 px-6 border-b border-gray-50 bg-gray-50/50">
        <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center shadow-lg shrink-0 overflow-hidden">
            <img src="../public/img/logo_upsrj_blanco.png" alt="UPSRJ" class="h-5 w-auto">
        </div>
        <div>
            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Administrador</p>
            <h1 class="text-base font-black text-slate-800 tracking-tight leading-none">Panel Control</h1>
        </div>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1 bg-white">
        <?php
        $nav = function($v, $icon, $label) use ($vista_actual) {
            $active = $vista_actual === $v;
            $cls = $active 
                ? 'bg-slate-900 text-white font-bold shadow-lg shadow-slate-100' 
                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 font-medium';
            echo "<a href='index.php?v={$v}' class='flex items-center gap-3 px-4 py-3 rounded-2xl transition-all {$cls}'>";
            echo "  <span class='material-symbols-outlined text-[20px]'>{$icon}</span>";
            echo "  <span class='text-sm'>{$label}</span>";
            echo '</a>';
        };
        ?>

        <p class="text-[9px] font-black uppercase tracking-widest text-slate-300 px-4 pt-2 pb-2">Global</p>
        <?php $nav('dashboard','dashboard','Dashboard Global'); ?>
        <?php $nav('profesores','school','Docentes Autorizados'); ?>
        <?php $nav('coordinadores','badge','Gestión Coordinadores'); ?>
        <?php $nav('logs','history_edu','Logs de Actividad'); ?>
        
        <p class="text-[9px] font-black uppercase tracking-widest text-slate-300 px-4 pt-6 pb-2">Análisis</p>
        <?php $nav('anomalias','bug_report','Anomalías'); ?>
        <?php $nav('duplicados','person_search','Duplicados'); ?>

        <p class="text-[9px] font-black uppercase tracking-widest text-slate-300 px-4 pt-6 pb-2">Sistema</p>
        <?php $nav('borrado_datos','delete_sweep','Borrado de Datos'); ?>
        <?php $nav('settings','settings','Configuración'); ?>
        
        <div class="pt-10">
            <a href="index.php?logout=1" class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-red-50 text-slate-400 hover:text-red-600 transition-all text-sm font-bold">
                <span class="material-symbols-outlined text-[20px]">logout</span>
                Cerrar Sesión
            </a>
        </div>
    </nav>

    <!-- Footer -->
    <div class="px-6 py-4 border-t border-gray-50 bg-gray-50/50">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-900 flex items-center justify-center text-[10px] font-black text-white uppercase shadow-sm">
                <?php echo substr($_SESSION['panel_user'] ?? 'AD', 0, 2); ?>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-black text-slate-800 truncate leading-none mb-1"><?php echo htmlspecialchars($_SESSION['panel_user'] ?? 'Admin Panel'); ?></p>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter truncate">Administración Global</p>
            </div>
        </div>
    </div>
</aside>
