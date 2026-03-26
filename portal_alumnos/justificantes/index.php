<?php
/**
 * portal_alumnos/justificantes/index.php
 * Portal móvil enfocado en la solicitud de justificantes.
 */

require_once '../../config.php';
global $CARRERAS;

$vers = '?v=' . time(); 
?>
<!DOCTYPE html>
<html lang="es" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Solicitud de Justificantes | UPSRJ</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    
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
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slideUp { animation: slideUp 0.6s ease-out forwards; }
        
        /* Custom scrollbar for webkit */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #7c3aed; border-radius: 10px; }
        
        input, select, textarea { 
            color: #f8fafc !important; 
            background-color: #1a1a2e !important; 
            border-color: rgba(255,255,255,0.1) !important;
            transition: all 0.2s ease; 
        }
        input:focus, select:focus, textarea:focus {
            box-shadow: 0 0 0 3px rgba(124,58,237,0.25) !important;
            border-color: #7c3aed !important;
        }
        
        .glass-panel { background: rgba(18, 18, 28, 0.95); backdrop-filter: blur(12px); border-color: rgba(255,255,255,0.07); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen relative overflow-x-hidden font-sans text-gray-800">

    <!-- Blobs decorativos -->
    <div class="fixed inset-0 z-0 pointer-events-none opacity-40">
        <div class="absolute top-[-10vh] right-[-10vw] w-[50vw] h-[50vh] bg-blue-400 rounded-full blur-[100px]" style="clip-path: ellipse(40% 50% at 30% 30%);"></div>
        <div class="absolute bottom-[-20vh] left-[-15vw] w-[60vw] h-[60vh] bg-emerald-400 rounded-full blur-[120px]" style="clip-path: ellipse(50% 40% at 40% 70%);"></div>
    </div>

    <!-- Navegación Superior -->
    <nav class="glass-panel sticky top-0 z-50 border-b border-slate-200 shadow-sm px-6 py-4 flex items-center justify-between animate-slideUp">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm border border-slate-200 overflow-hidden shrink-0">
                <img src="../../public/img/logo_up_corto.png" alt="Logo UPSRJ" class="w-full h-full object-contain p-1" onerror="this.src=''; this.className='hidden'; this.parentElement.innerHTML='<span class=\'material-symbols-outlined text-blue-600 font-bold\'>school</span>';">
            </div>
            <div>
                <h1 class="text-lg font-black tracking-tight text-slate-900 leading-none">UPSRJ</h1>
                <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mt-0.5">Justificantes</p>
            </div>
        </div>
        <a href="../" class="text-xs font-bold text-slate-400 hover:text-slate-600 flex items-center gap-1 transition-colors">
            <span class="material-symbols-outlined text-[14px]">arrow_back</span> Volver
        </a>
    </nav>

    <!-- Main Content -->
    <main class="relative z-10 max-w-lg w-full mx-auto px-6 py-8 animate-slideUp" style="animation-delay: 0.1s;">
        
        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200/60 p-6 sm:p-8 relative overflow-hidden">
            <!-- decorative line -->
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-500 to-emerald-400"></div>

            <div class="text-center mb-8 pt-2">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight leading-tight">Solicitud de <span class="text-blue-600">Justificante</span></h2>
                <p class="text-slate-500 text-sm mt-2 leading-relaxed font-medium">Completa el formulario para enviar tu comprobante a la coordinación.</p>
            </div>

            <form id="form-justificante" class="space-y-5" enctype="multipart/form-data">
                
                <!-- Carrera -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Programa Educativo</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors pointer-events-none">school</span>
                        <select name="carrera_sigla" required class="w-full pl-12 pr-10 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white outline-none appearance-none cursor-pointer shadow-sm transition-all">
                            <?php foreach($CARRERAS as $sigla => $info): ?>
                                <option value="<?= $sigla ?>"><?= htmlspecialchars($info['nombre_largo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none group-focus-within:text-blue-500 transition-colors">expand_more</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Matrícula -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Matrícula (Opcional)</label>
                        <div class="relative group">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors pointer-events-none">badge</span>
                            <input type="text" name="matricula" placeholder="Ej: 21350000" class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white outline-none shadow-sm placeholder:text-slate-400 transition-all">
                        </div>
                    </div>

                    <!-- Fecha -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Fecha de Falta</label>
                        <div class="relative group">
                            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors pointer-events-none">calendar_today</span>
                            <input type="date" name="fecha_ausencia" required value="<?= date('Y-m-d') ?>" class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white outline-none shadow-sm cursor-text transition-all">
                        </div>
                    </div>
                </div>

                <!-- Nombre -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Nombre Completo</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-blue-500 transition-colors pointer-events-none">person</span>
                        <input type="text" name="alumno_nombre" required placeholder="Empezando por apellidos..." class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white outline-none shadow-sm placeholder:text-slate-400 pattern='^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$' transition-all">
                    </div>
                </div>

                <!-- Motivo -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Motivo o Razón</label>
                    <textarea name="motivo" required rows="3" placeholder="Describe brevemente el motivo de tu inasistencia..." class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white outline-none shadow-sm resize-none placeholder:text-slate-400 transition-all"></textarea>
                </div>

                <!-- Evidencia -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Archivo Comprobante (Opcional)</label>
                    <div class="relative overflow-hidden w-full bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl text-center hover:bg-blue-50 hover:border-blue-200 focus-within:ring-2 focus-within:ring-blue-500/20 transition-colors cursor-pointer group p-6 shadow-sm">
                        <input type="file" name="archivo" accept=".pdf,image/png,image/jpeg" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-name').textContent = this.files[0]?.name || 'Haz clic o arrastra un archivo aquí (PDF, JPG, PNG)'">
                        <div class="pointer-events-none flex flex-col items-center justify-center gap-3">
                            <div class="w-12 h-12 bg-white text-slate-400 rounded-full flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-blue-100 group-hover:text-blue-600 transition-all group-hover:scale-110">
                                <span class="material-symbols-outlined text-2xl">upload_file</span>
                            </div>
                            <p id="file-name" class="text-xs font-semibold text-slate-500 px-4 group-hover:text-blue-600 transition-colors">Haz clic o arrastra un comprobante aquí (PDF, JPG, PNG)</p>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" id="btn-submit" class="w-full flex justify-center items-center gap-2 py-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-2xl shadow-lg shadow-blue-500/30 transition-all hover:-translate-y-0.5 active:translate-y-0 group">
                        <span id="btn-text">Enviar Solicitud a Coordinación</span>
                        <span class="material-symbols-outlined text-[18px] group-hover:translate-x-1 transition-transform">send</span>
                    </button>
                </div>
            </form>

            <!-- Estado de Respuesta -->
            <div id="status-card" class="hidden mt-6 p-5 rounded-2xl border text-center relative overflow-hidden transition-all"></div>
        </div>
    </main>

    <script>
        document.getElementById('form-justificante').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('btn-submit');
            const txt = document.getElementById('btn-text');
            const status = document.getElementById('status-card');
            
            // UI Loading
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            txt.innerHTML = '<span class="material-symbols-outlined animate-spin text-[18px] align-middle mr-1.5">refresh</span> Procesando...';
            status.classList.add('hidden');

            const formData = new FormData(e.target);

            try {
                const res = await fetch('../../api/solicitudes_estudiantes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                status.classList.remove('hidden', 'bg-red-50', 'border-red-200', 'text-red-700', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
                
                if (data.success) {
                    status.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
                    status.innerHTML = `
                        <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-emerald-100 scale-110">
                            <span class="material-symbols-outlined text-emerald-500 text-3xl">check_circle</span>
                        </div>
                        <h4 class="text-base font-black tracking-tight mb-1">¡Solicitud Enviada!</h4>
                        <p class="text-xs font-medium opacity-90 max-w-[250px] mx-auto">${data.message}</p>
                    `;
                    e.target.reset();
                    document.getElementById('file-name').textContent = 'Haz clic o arrastra un archivo aquí (PDF, JPG, PNG)';
                } else {
                    status.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
                    status.innerHTML = `
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-red-100">
                            <span class="material-symbols-outlined text-red-500 text-2xl">error</span>
                        </div>
                        <h4 class="text-sm font-black mb-1">Error en el envío</h4>
                        <p class="text-xs font-medium opacity-90">${data.error}</p>
                    `;
                }

            } catch (err) {
                status.classList.remove('hidden');
                status.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
                status.innerHTML = `
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-red-100">
                        <span class="material-symbols-outlined text-red-500 text-2xl">wifi_off</span>
                    </div>
                    <h4 class="text-sm font-black mb-1">Error de conexión</h4>
                    <p class="text-xs font-medium opacity-90">Por favor, verifica tu internet e intenta de nuevo.</p>
                `;
            } finally {
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-not-allowed');
                txt.textContent = 'Enviar Solicitud a Coordinación';
            }
        });
    </script>
</body>
</html>
