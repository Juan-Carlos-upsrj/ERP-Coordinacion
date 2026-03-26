<?php
/**
 * portal_alumnos/index.php
 * Página pública de inicio para que los estudiantes busquen su matrícula manualmente.
 */

require_once '../config.php';
$carreras_disponibles = [];
foreach ($CARRERAS as $c) {
    if ($c['activa']) $carreras_disponibles[] = $c['nombre_corto'];
}
?>
<!DOCTYPE html>
<html lang="es" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Alumnos | Consulta de Asistencia</title>
    
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
        
        /* Fix inputs text color */
        input { color: #f8fafc !important; }
        input:focus { border-color: #7c3aed !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center relative overflow-hidden font-sans text-slate-800">

    <!-- Blobs decorativos -->
    <div class="fixed inset-0 z-0 pointer-events-none opacity-40">
        <div class="absolute top-[-10vh] right-[-10vw] w-[50vw] h-[50vh] bg-blue-400 rounded-full blur-[100px]" style="clip-path: ellipse(40% 50% at 30% 30%);"></div>
        <div class="absolute bottom-[-20vh] left-[-15vw] w-[60vw] h-[60vh] bg-emerald-400 rounded-full blur-[120px]" style="clip-path: ellipse(50% 40% at 40% 70%);"></div>
    </div>

    <div class="z-10 w-full max-w-md px-6 py-12 animate-slideUp">
        
        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-200/60 p-8 relative overflow-hidden">
            <!-- decorative line -->
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-500 to-emerald-400"></div>

            <div class="text-center mb-8 pt-2">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-blue-100 shadow-inner">
                    <span class="material-symbols-outlined text-[32px]">school</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight mb-2">Portal de Estudiantes</h1>
                <p class="text-sm text-slate-500 font-medium">Consulta tu resumen de asistencia de forma rápida y segura.</p>
            </div>

            <form action="resultado.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">Buscar Alumno</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-3.5 text-slate-400 group-focus-within:text-blue-500 transition-colors">badge</span>
                        <input type="text" name="id" required placeholder="Tu Matrícula o Iniciales+Grupo..." 
                               class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-slate-900 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all shadow-sm">
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2 ml-1 font-medium">Puedes buscar por matrícula (Ej. 192345) o por nombre y grupo (Ej. JLD IAEV-2).</p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full flex justify-center items-center gap-2 py-3.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-2xl shadow-lg shadow-blue-500/30 transition-all hover:-translate-y-0.5 active:translate-y-0">
                        Consultar Resumen
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                </div>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Carreras Disponibles</p>
                <div class="flex flex-wrap justify-center gap-1.5">
                    <?php foreach($carreras_disponibles as $cd): ?>
                        <span class="px-2 py-1 bg-slate-50 border border-slate-200 text-slate-500 text-[10px] font-bold rounded-lg"><?php echo htmlspecialchars($cd); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="mt-6 text-center">
            <p class="text-[11px] font-medium text-slate-500">¿Requieres un justificante? Contacta a tu coordinador.</p>
        </div>

    </div>

</body>
</html>
