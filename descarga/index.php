<?php
// Portal de descarga — acceso público
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descarga — Gestión Académica UPSRJ</title>
    <meta name="description" content="Portal de descarga exclusivo para docentes de la UPSRJ. Descarga la aplicación de gestión académica.">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'sans': ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb',
                            700: '#1d4ed8', 900: '#1e3a8a',
                        }
                    },
                    animation: {
                        'fade-up': 'fadeUp 0.5s ease forwards',
                    },
                    keyframes: {
                        fadeUp: {
                            '0%': { opacity: '0', transform: 'translateY(16px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <style>
        .card-shadow { box-shadow: 0 4px 24px -4px rgba(37, 99, 235, 0.12); }
        .step-line::after {
            content: '';
            position: absolute;
            left: 15px;
            top: 32px;
            width: 2px;
            height: calc(100% + 16px);
            background: linear-gradient(to bottom, #bfdbfe, transparent);
        }
        .step-item:last-child .step-line::after { display: none; }
        .chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
    </style>
</head>
<body class="min-h-full font-sans text-slate-800 antialiased bg-slate-50">



<!-- ======= AUTHENTICATED PORTAL ======= -->

<!-- NAV -->
<nav class="bg-white/80 backdrop-blur border-b border-gray-100 sticky top-0 z-10">
    <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 bg-brand-600 rounded-lg flex items-center justify-center text-white">
                <span class="material-symbols-outlined text-[16px]">school</span>
            </div>
            <span class="font-black text-slate-900 text-sm tracking-tight">Gestión Académica <span class="text-brand-600">UPSRJ</span></span>
        </div>
        <span class="chip bg-brand-50 text-brand-700 border border-brand-100">
                <span class="material-symbols-outlined text-[12px]">school</span>
                Docentes UPSRJ
            </span>
    </div>
</nav>

<main class="max-w-5xl mx-auto px-6 py-14">

    <!-- HERO -->
    <div class="text-center mb-14">
        <div class="chip bg-brand-50 text-brand-700 border border-brand-100 mb-5">
            <span class="material-symbols-outlined text-[12px]">verified</span>
            Versión Estable · v3.3.90
        </div>
        <h1 class="text-4xl md:text-5xl font-black text-slate-900 tracking-tight mb-4 text-balance leading-tight">
            Portal de Descarga<br><span class="text-brand-600">para Docentes</span>
        </h1>
        <p class="text-slate-500 text-lg font-medium max-w-xl mx-auto leading-relaxed">
            Herramienta oficial de gestión académica: horarios, asistencias y reportes desde tu escritorio.
        </p>
    </div>

    <!-- MAIN GRID -->
    <div class="grid md:grid-cols-3 gap-6 mb-12">

        <!-- DOWNLOAD CARD (col-span-2) -->
        <div class="md:col-span-2 bg-white rounded-3xl p-8 card-shadow border border-gray-100 flex flex-col">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h2 class="text-xl font-black text-slate-900 tracking-tight mb-1">Software para Windows</h2>
                    <p class="text-sm text-slate-400 font-medium">Instalador de escritorio (.exe) · 64-bit</p>
                </div>
                <div class="w-12 h-12 bg-brand-50 rounded-2xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-brand-600 text-[24px]">desktop_windows</span>
                </div>
            </div>

            <!-- Features -->
            <ul class="grid grid-cols-2 gap-3 mb-8">
                <li class="flex items-center gap-2.5 bg-slate-50 rounded-xl p-3">
                    <span class="material-symbols-outlined text-brand-500 text-[18px]">visibility</span>
                    <div>
                        <p class="text-xs font-bold text-slate-700">Interfaz Nativa</p>
                        <p class="text-[11px] text-slate-400">Rápida y sin distracciones</p>
                    </div>
                </li>
                <li class="flex items-center gap-2.5 bg-slate-50 rounded-xl p-3">
                    <span class="material-symbols-outlined text-brand-500 text-[18px]">sync</span>
                    <div>
                        <p class="text-xs font-bold text-slate-700">Sincronización Real</p>
                        <p class="text-[11px] text-slate-400">Datos institucionales en vivo</p>
                    </div>
                </li>
                <li class="flex items-center gap-2.5 bg-slate-50 rounded-xl p-3">
                    <span class="material-symbols-outlined text-brand-500 text-[18px]">group</span>
                    <div>
                        <p class="text-xs font-bold text-slate-700">Gestión de Grupos</p>
                        <p class="text-[11px] text-slate-400">Pase de lista por materia</p>
                    </div>
                </li>
                <li class="flex items-center gap-2.5 bg-slate-50 rounded-xl p-3">
                    <span class="material-symbols-outlined text-brand-500 text-[18px]">update</span>
                    <div>
                        <p class="text-xs font-bold text-slate-700">Auto-actualizable</p>
                        <p class="text-[11px] text-slate-400">Siempre en la última versión</p>
                    </div>
                </li>
            </ul>

            <!-- Download Button -->
            <a href="../updates/Gestion-Docente-UPSRJ-Setup-3.3.90.exe"
               class="group w-full bg-brand-600 hover:bg-brand-700 active:scale-[0.99] text-white font-bold py-4 px-6 rounded-2xl flex items-center justify-center gap-3 transition-all shadow-lg shadow-brand-500/25 text-sm tracking-wide mt-auto">
                <span class="material-symbols-outlined text-[20px] group-hover:animate-bounce">download</span>
                Descargar Gestión Académica
            </a>
            <p class="text-center text-[11px] text-slate-400 mt-3 font-medium">
                Compatible con Windows 10 / 11 · Requiere conexión a internet
            </p>
        </div>

        <!-- SUPPORT CARD -->
        <div class="bg-brand-900 rounded-3xl p-7 text-white flex flex-col">
            <div class="w-10 h-10 bg-brand-800 rounded-xl flex items-center justify-center mb-5">
                <span class="material-symbols-outlined text-brand-300 text-[20px]">support_agent</span>
            </div>
            <h3 class="font-black text-base tracking-tight mb-3">¿Necesitas ayuda?</h3>
            <p class="text-sm text-brand-200 leading-relaxed mb-6">
                Esta herramienta es exclusiva para docentes activos de la UPSRJ. Si tienes problemas con la instalación o acceso, contacta directamente a tu coordinador de área.
            </p>
            <div class="mt-auto space-y-3">
                <?php foreach ($SUPPORT_CONTACTS as $contact): ?>
                <div class="bg-brand-800/60 rounded-xl p-3">
                    <p class="text-[10px] text-brand-400 uppercase font-bold tracking-widest mb-0.5"><?php echo htmlspecialchars($contact['label']); ?></p>
                    <p class="text-xs font-semibold text-white"><?php echo htmlspecialchars($contact['valor']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ============================= -->
    <!-- CONFIGURACIÓN INICIAL SECTION -->
    <!-- ============================= -->
    <div class="mb-12">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 bg-amber-50 border border-amber-100 rounded-2xl flex items-center justify-center">
                <span class="material-symbols-outlined text-amber-500 text-[20px]">settings_suggest</span>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-900 tracking-tight">Guía de Configuración Inicial</h2>
                <p class="text-sm text-slate-400 font-medium">Sigue estos pasos la primera vez que instales la aplicación</p>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">

            <!-- PHASES: Left column (steps 1-3) -->
            <div class="space-y-4">

                <!-- Step 1: Install -->
                <div class="step-item relative">
                    <div class="step-line">
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 card-shadow transition-all hover:border-brand-200 hover:shadow-brand-100">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center font-black text-sm shrink-0">1</div>
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900 text-sm mb-1 flex items-center gap-2">
                                        Descarga e Instalación
                                        <span class="chip bg-slate-100 text-slate-500 border-0">~5 min</span>
                                    </p>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        Descarga el instalador desde el botón de arriba. Ejecútalo y si aparece un aviso de <strong>Windows SmartScreen</strong>, haz clic en <em>"Más información"</em> → <em>"Ejecutar de todas formas"</em>. La aplicación se instalará automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="h-4"></div>
                </div>

                <!-- Step 2: Google login -->
                <div class="step-item relative">
                    <div class="step-line">
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 card-shadow transition-all hover:border-brand-200 hover:shadow-brand-100">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center font-black text-sm shrink-0">2</div>
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900 text-sm mb-1 flex items-center gap-2">
                                        Inicio de Sesión con Google
                                        <span class="chip bg-blue-50 text-blue-600 border border-blue-100">Obligatorio</span>
                                    </p>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        Al abrir la app, se mostrará un botón de <strong>Iniciar sesión con Google</strong>. Usa tu <strong>correo institucional</strong> de la UPSRJ. El sistema verificará que estés registrado como docente autorizado.
                                    </p>
                                    <div class="mt-3 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2 flex items-start gap-2">
                                        <span class="material-symbols-outlined text-amber-500 text-[14px] mt-0.5">warning</span>
                                        <p class="text-[11px] text-amber-700 font-medium">Usa solo tu correo <strong>@upsrj.edu.mx</strong>. Las cuentas personales no tienen acceso.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="h-4"></div>
                </div>

                <!-- Step 3: Verify schedule -->
                <div class="step-item relative">
                    <div class="step-line">
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 card-shadow transition-all hover:border-brand-200 hover:shadow-brand-100">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center font-black text-sm shrink-0">3</div>
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900 text-sm mb-1">Verifica tu Horario</p>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        Una vez dentro, verás la pantalla principal con tu <strong>horario semanal</strong> y tus grupos asignados. Confirma que los grupos y materias corresponden a tu carga académica actual. Si hay alguna discrepancia, notifica a tu coordinador.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="h-4"></div>
                </div>

            </div>

            <!-- PHASES: Right column (steps 4-6) -->
            <div class="space-y-4">

                <!-- Step 4: Take attendance -->
                <div class="step-item relative">
                    <div class="step-line">
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 card-shadow transition-all hover:border-brand-200 hover:shadow-brand-100">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-slate-700 text-white flex items-center justify-center font-black text-sm shrink-0">4</div>
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900 text-sm mb-1">Pase de Lista</p>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        Selecciona el grupo y la materia de la clase que vas a impartir. Se cargará la lista de alumnos. Marca la asistencia de cada alumno — la información se sincroniza automáticamente con el servidor institucional.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="h-4"></div>
                </div>

                <!-- Step 5: Session persistence -->
                <div class="step-item relative">
                    <div class="step-line">
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 card-shadow transition-all hover:border-brand-200 hover:shadow-brand-100">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-slate-700 text-white flex items-center justify-center font-black text-sm shrink-0">5</div>
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900 text-sm mb-1 flex items-center gap-2">
                                        Sesión Persistente
                                        <span class="chip bg-green-50 text-green-600 border border-green-100">Automático</span>
                                    </p>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        La sesión de Google <strong>se mantiene guardada</strong> entre cierres de la aplicación. No necesitas iniciar sesión cada vez que abras el programa. Solo cierra sesión cuando cambies de computadora.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="h-4"></div>
                </div>

                <!-- Step 6: Updates -->
                <div class="step-item relative">
                    <div class="step-line">
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 card-shadow transition-all hover:border-brand-200 hover:shadow-brand-100">
                            <div class="flex items-start gap-4">
                                <div class="w-8 h-8 rounded-full bg-slate-700 text-white flex items-center justify-center font-black text-sm shrink-0">6</div>
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900 text-sm mb-1">Actualizaciones Automáticas</p>
                                    <p class="text-xs text-slate-500 leading-relaxed">
                                        La aplicación verifica automáticamente si hay actualizaciones al iniciar. Si hay una versión nueva disponible, te mostrará una notificación y podrás actualizarla con un solo clic. <strong>No es necesario desinstalar</strong> la versión anterior.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- FAQ / PROBLEMAS COMUNES -->
    <div class="bg-white rounded-3xl p-8 card-shadow border border-gray-100 mb-10">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-500 text-[18px]">help</span>
            </div>
            <h3 class="font-black text-slate-900 tracking-tight">Problemas Frecuentes</h3>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="bg-slate-50 rounded-2xl p-4">
                <p class="text-xs font-black text-slate-700 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-red-400 text-[14px]">block</span>
                    "No autorizado" al entrar
                </p>
                <p class="text-xs text-slate-500 leading-relaxed">Tu correo no está registrado en el sistema. Pide a tu coordinador que te agregue a la lista de docentes autorizados.</p>
            </div>
            <div class="bg-slate-50 rounded-2xl p-4">
                <p class="text-xs font-black text-slate-700 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-400 text-[14px]">wifi_off</span>
                    No carga el horario
                </p>
                <p class="text-xs text-slate-500 leading-relaxed">La aplicación necesita conexión a internet para sincronizar datos. Verifica tu conexión y vuelve a intentarlo. Los datos no se muestran sin conexión.</p>
            </div>
            <div class="bg-slate-50 rounded-2xl p-4">
                <p class="text-xs font-black text-slate-700 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-blue-400 text-[14px]">update</span>
                    La app no actualiza
                </p>
                <p class="text-xs text-slate-500 leading-relaxed">Cierra completamente la aplicación y vuelve a abrirla. Si persiste, descarga el instalador más reciente desde esta página y reinstala.</p>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="border-t border-gray-100 pt-8 text-center">
        <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-widest">
            UPSRJ · Servicios Académicos · 2026
        </p>
    </footer>

</main>


</body>
</html>
