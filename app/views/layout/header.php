<?php
/**
 * app/views/layout/header.php
 * Plantilla Global - Cabecera y Configuración UI.
 */
require_once __DIR__ . '/../../models/LogsModel.php';
require_once __DIR__ . '/../../core/Utils.php';

$carrera_sigla = $_SESSION['carrera_activa'] ?? 'IAEV';
$c_info = $CARRERAS[$carrera_sigla];
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinación — UPSRJ</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'sans': ['Montserrat', 'sans-serif'] },
                    colors: {
                        white: '#12121c', /* --surface */
                        black: '#ffffff',
                        slate: {
                            50: '#09090f', 100: '#1a1a2e', 200: 'rgba(255,255,255,0.07)', 300: 'rgba(255,255,255,0.15)',
                            400: '#64748b', 500: '#94a3b8', 600: '#cbd5e1', 700: '#e2e8f0', 800: '#f1f5f9', 900: '#ffffff',
                        },
                        gray: {
                            50: '#1a1a2e', 100: 'rgba(255,255,255,0.07)', 200: 'rgba(255,255,255,0.1)',
                            300: '#64748b', 400: '#94a3b8', 500: '#94a3b8', 600: '#cbd5e1', 700: '#e2e8f0', 800: '#f8fafc', 900: '#ffffff',
                        },
                        blue: { /* Mapped to GAMES Violet */
                            50: 'rgba(124,58,237,0.1)', 100: 'rgba(124,58,237,0.2)', 200: 'rgba(124,58,237,0.3)',
                            300: '#a78bfa', 400: '#8b5cf6', 500: '#7c3aed', 600: '#7c3aed', 700: '#6d28d9', 800: '#5b21b6', 900: '#4c1d95',
                        },
                        brand: { /* Mapped to GAMES Cyan */
                            50: 'rgba(6,182,212,0.1)', 100: 'rgba(6,182,212,0.2)', 200: 'rgba(6,182,212,0.3)',
                            300: '#67e8f9', 400: '#22d3ee', 500: '#06b6d4', 600: '#06b6d4', 700: '#0891b2', 800: '#155e75', 900: '#164e63',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- GAMES Global Estética -->
    <style>
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg, #09090f); }
        ::-webkit-scrollbar-thumb { background: #7c3aed; border-radius: 10px; }
        ::-webkit-scrollbar-corner { background: transparent; }

        /* General fixes for inverted theme */
        input:not([type="checkbox"]):not([type="radio"]), select, textarea {
            color: #f8fafc !important;
            background-color: #1a1a2e !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        input:focus, select:focus, textarea:focus {
            box-shadow: 0 0 0 3px rgba(124,58,237,0.25) !important;
            border-color: #7c3aed !important;
        }
        
        /* Efectos de brillo / Shadows */
        .shadow-sm, .shadow, .shadow-md, .shadow-lg, .shadow-xl, .shadow-2xl {
            box-shadow: 0 10px 40px rgba(0,0,0,0.6) !important;
        }
        
        /* Decoraciones de GAMES (Blobs) */
        .games-blob {
            position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.2; pointer-events: none; z-index: -1;
        }
    </style>
</head>
<body class="h-full w-full flex overflow-hidden text-gray-800 antialiased bg-slate-50">
    
    <!-- Incluir la barra lateral estructurada -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Contenedor Principal (Derecho) -->
    <div class="flex-1 flex flex-col min-w-0 w-full bg-slate-50 overflow-hidden">
        
        <!-- TOP NAV Básico -->
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 shrink-0 z-10">
            <div class="flex items-center gap-4">
                <h2 class="text-xl font-bold tracking-tight text-gray-800">
                    Panel de Control 
                    <span class="text-gray-400 font-medium mx-2">—</span> 
                    <span class="text-brand-600"><?php echo $c_info['nombre_largo']; ?></span>
                </h2>
            </div>
            
            <div class="flex items-center gap-5">
                <!-- Notificaciones de Sync -->
                <?php
                $pdo_notif = getConnection($c_info['db_name'], $c_info['carrera_id']);
                $recent_syncs = LogsModel::getNotificacionesSync($pdo_notif, 5);
                ?>
                <div class="relative group" id="notif-dropdown">
                    <button class="relative p-2 text-gray-400 hover:text-brand-600 transition-colors bg-gray-50 rounded-xl border border-gray-100">
                        <span class="material-symbols-outlined text-[22px]">notifications</span>
                        <?php if (count($recent_syncs) > 0): ?>
                        <span class="absolute top-1.5 right-1.5 w-3.5 h-3.5 bg-red-500 border-2 border-white rounded-full flex items-center justify-center text-[7px] font-black text-white animate-bounce-slow" id="notif-badge">
                            <?php echo count($recent_syncs); ?>
                        </span>
                        <?php endif; ?>
                    </button>

                    <!-- Mega Dropdown de Notificaciones -->
                    <div class="absolute right-0 mt-3 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform origin-top-right scale-95 group-hover:scale-100 z-50">
                        <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between bg-gray-50/50 rounded-t-2xl">
                            <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest italic">Sincronizaciones</h3>
                            <span class="text-[10px] bg-brand-100 text-brand-700 font-bold px-2 py-0.5 rounded-full uppercase">Reciente</span>
                        </div>
                        <div class="max-h-[350px] overflow-y-auto divide-y divide-gray-50" id="notif-list">
                            <?php foreach ($recent_syncs as $rs): ?>
                            <div class="px-5 py-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-[16px] text-green-600">sync</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-black text-gray-800 leading-tight">Prof. <?php echo htmlspecialchars($rs['profesor_nombre']); ?></p>
                                        <p class="text-[10px] text-gray-400 font-bold mt-0.5 uppercase tracking-tighter"><?php echo htmlspecialchars($rs['grupo_nombre']); ?> · <?php echo $rs['total_alumnos']; ?> alumnos</p>
                                        <p class="text-[9px] text-brand-500 font-black mt-1 uppercase italic tracking-widest"><?php echo Utils::tiempoRelativo($rs['fecha_sync']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-3 border-t border-gray-50 text-center">
                            <a href="index.php?v=logs" class="text-[10px] font-black text-brand-600 hover:text-brand-800 uppercase tracking-widest transition-colors mb-1 inline-block">Ver todos los reportes</a>
                        </div>
                    </div>
                </div>

                <!-- Carrera activa -->
                <div class="px-3 py-1 bg-brand-50 text-brand-600 rounded-lg text-sm font-bold flex items-center gap-2 border border-brand-100">
                    <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span>
                    <?php echo htmlspecialchars($_SESSION['carrera_activa'] ?? 'IAEV'); ?>
                </div>
                <!-- Usuario logueado -->
                <a href="index.php?v=mi_perfil" class="flex items-center gap-2 bg-gray-100 text-gray-700 hover:bg-gray-200 px-3 py-1.5 rounded-lg text-sm font-bold border border-gray-200 transition-colors">
                    <span class="material-symbols-outlined text-[16px] text-gray-500">account_circle</span>
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?>
                </a>
            </div>
        </header>

        <!-- Polling Script para Notificaciones -->
        <script>
        function checkNotifications() {
            fetch('app/api/get_notifications.php')
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        const list = document.getElementById('notif-list');
                        const badge = document.getElementById('notif-badge');
                        
                        // Actualizar contador
                        if (badge) badge.innerText = res.count;

                        // Actualizar lista (demo simplificada)
                        let html = '';
                        res.data.forEach(n => {
                            html += `
                                <div class="px-5 py-4 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-[16px] text-green-600">sync</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-black text-gray-800 leading-tight">Prof. ${n.profesor_nombre}</p>
                                            <p class="text-[10px] text-gray-400 font-bold mt-0.5 uppercase tracking-tighter">${n.grupo_nombre} · ${n.total_alumnos} alumnos</p>
                                            <p class="text-[9px] text-brand-500 font-black mt-1 uppercase italic tracking-widest">${n.hace_cuanto}</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        list.innerHTML = html;
                    }
                });
        }
        // Poll cada 60 segundos
        setInterval(checkNotifications, 60000);
        </script>

        <!-- ÁREA SCROLLEABLE DE LA VISTA -->
        <main class="flex-1 overflow-y-auto overflow-x-hidden p-6 md:p-8 relative">
            <!-- Decorative blur bg (GAMES aesthetic) -->
            <div class="games-blob" style="width: 40vw; height: 40vh; background: #7c3aed; top: -10vh; right: -5vw;"></div>
            <div class="games-blob" style="width: 30vw; height: 30vh; background: #06b6d4; bottom: 10vh; left: -10vw;"></div>
            
            <!-- EL CONTENIDO DE LA VISTA SE INYECTA AQUÍ ABAJO -->
