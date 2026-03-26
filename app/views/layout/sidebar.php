<?php
/**
 * app/views/layout/sidebar.php
 * Navegación lateral principal
 */
$vista_actual   = $_GET['v'] ?? 'dashboard';
$carrera_activa = $_SESSION['carrera_activa'] ?? 'IAEV';
$carrera_info_s = $CARRERAS[$carrera_activa] ?? $CARRERAS['IAEV'];
$logo_path      = "public/img/" . ($carrera_info_s['logo'] ?? "logo_{$carrera_activa}.png");
$logo_path      = strtolower($logo_path);
?>
<aside class="w-64 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col h-full z-20 shadow-sm relative">

    <!-- Logo / Identidad -->
    <div class="h-20 flex items-center gap-3 px-6 border-b border-gray-100 bg-gray-50/50">
        <div class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shadow-sm border border-gray-200 overflow-hidden shrink-0">
            <img src="<?php echo htmlspecialchars($logo_path); ?>" 
                 alt="Logo <?php echo $carrera_activa; ?>" 
                 class="w-full h-full object-contain p-1" 
                 onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'material-symbols-outlined text-brand-600 font-bold\'>school</span>';">
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-brand-500">Prog. Educativo</p>
            <h1 class="text-xl font-black text-gray-800 tracking-tight leading-none"><?php echo htmlspecialchars($carrera_activa); ?></h1>
        </div>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 overflow-y-auto px-4 py-5 space-y-0.5 bg-white">
        
        <?php
        $nav = function($v, $icon, $label, $badge=null) use ($vista_actual) {
            $active = $vista_actual === $v;
            $cls = $active ? 'bg-brand-50 text-brand-600 font-bold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 font-medium';
            echo "<a href='index.php?v={$v}' class='flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all {$cls}'>";
            echo "  <span class='material-symbols-outlined text-[20px]'." . ($active ? ' filled' : '') . ">{$icon}</span>";
            echo "  <span class='text-sm'>{$label}</span>";
            if ($badge) echo "  <span class='ml-auto text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded-full font-black'>{$badge}</span>";
            echo '</a>';
        };
        ?>

        <!-- ESCRITORIO -->
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-2 pb-1">Escritorio</p>
        <?php $nav('dashboard','dashboard','Vista General'); ?>
        <?php $nav('asistencia_hoy','today','Asistencia Hoy'); ?>
        <?php $nav('sugerencias','mark_unread_chat_alt','Buzón de Sugerencias'); ?>

        <!-- POBLACIÓN -->
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-4 pb-1">Población</p>
        <?php $nav('lista_alumnos','group','Estudiantes'); ?>
        <?php $nav('vista_grupo','groups','Grupos'); ?>
        <?php $nav('calificaciones','grade','Calificaciones'); ?>

        <!-- ACADEMIA -->
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-4 pb-1">Academia</p>
        <?php $nav('lista_profesores','badge','Profesores'); ?>
        <?php $nav('bajas','person_off','Bajas'); ?>
        <?php $nav('justificantes','description','Justificantes'); ?>
        <?php $nav('asignar_tutor','support_agent','Tutores'); ?>
        <?php $nav('horarios','calendar_month','Gestor de Horarios'); ?>


        <!-- ANÁLISIS -->
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-4 pb-1">Análisis</p>
        <?php $nav('reportes','description','Reporte'); ?>
        <?php $nav('graficos','monitoring','Gráficos'); ?>

        <?php if ($carrera_activa === 'IAEV'): ?>
        <!-- HERRAMIENTAS IAEV -->
        <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-400 px-3 pt-4 pb-1">Herramientas IAEV</p>
        <?php $nav('preview_iaev','extension','Titulaciones IAEV'); ?>
        <?php endif; ?>

        <!-- ACCESO Y CREDENCIALES -->
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-3 pt-4 pb-1">Acceso y Credenciales</p>
        <?php $nav('nfc_export','qr_code_scanner','Exportar QR / NFC'); ?>

        
    </nav>

    <!-- Switcher de Carrera (Condicional) + Cerrar Sesión -->
    <div class="p-4 border-t border-gray-100 bg-gray-50 space-y-2">
        <?php if ($carrera_info_s['permitir_cambio'] ?? false): ?>
            <!-- Career switcher -->
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 px-1 mb-1">Cambiar Carrera</p>
            <div class="flex flex-wrap gap-1.5 mb-2">
                <?php foreach ($CARRERAS as $sigla => $c): if (!$c['activa']) continue; ?>
                <a href="index.php?cambiar_carrera=<?php echo $sigla; ?>"
                
                   class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold transition-all
                          <?php echo $carrera_activa === $sigla 
                            ? 'text-white shadow-sm'
                            : 'bg-gray-100 text-gray-500 hover:bg-gray-200'; ?>"
                   style="<?php echo $carrera_activa === $sigla ? 'background-color: ' . $c['color_hex'] . ';' : ''; ?>">
                    <?php echo htmlspecialchars($sigla); ?>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <!-- Mi Perfil -->
        <?php $nav('mi_perfil','person_outline','Mi Perfil'); ?>

        <!-- Logout -->
        <a href="index.php?logout=1" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-red-50 hover:text-red-600 text-gray-500 transition-colors font-medium">
            <span class="material-symbols-outlined">logout</span>
            <span class="text-sm">Cerrar Sesión</span>
        </a>
    </div>

</aside>
