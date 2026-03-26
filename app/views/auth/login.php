<?php
/**
 * app/views/auth/login.php
 * Vista de acceso profesional y personalizada.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso — Coordinación UPSRJ</title>
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
                        blue: {
                            50: 'rgba(124,58,237,0.1)', 100: 'rgba(124,58,237,0.2)', 200: 'rgba(124,58,237,0.3)',
                            300: '#a78bfa', 400: '#8b5cf6', 500: '#7c3aed', 600: '#7c3aed', 700: '#6d28d9', 800: '#5b21b6', 900: '#4c1d95',
                        },
                        brand: {
                            50: 'rgba(6,182,212,0.1)', 100: 'rgba(6,182,212,0.2)', 200: 'rgba(6,182,212,0.3)',
                            300: '#67e8f9', 400: '#22d3ee', 500: '#06b6d4', 600: '#06b6d4', 700: '#0891b2', 800: '#155e75', 900: '#164e63',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        
        /* Ocultar el icono nativo de "ver contraseña" de los navegadores (Edge/Chrome/Safari) */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }
        input::-webkit-contacts-auto-fill-button,
        input::-webkit-credentials-auto-fill-button {
            display: none !important;
            visibility: hidden;
            pointer-events: none;
        }

        /* Animación para el carrusel de fondo */
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .carousel-track {
            display: flex;
            width: calc(250px * 12); /* 6 logos * 2 sets */
            animation: scroll 40s linear infinite;
        }
        .carousel-item {
            width: 250px;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0.15;
            filter: grayscale(100%);
            padding: 0 40px;
        }
        .carousel-item img {
            max-width: 120px;
            max-height: 80px;
            object-fit: contain;
        }
        /* Decoraciones de GAMES (Blobs) */
        .games-blob {
            position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.15; pointer-events: none; z-index: -1;
        }
    </style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen relative overflow-hidden text-gray-800">

    <!-- GAMES Blobs Decorativos -->
    <div class="games-blob" style="width: 50vw; height: 50vh; background: #7c3aed; top: -10vh; right: -10vw;"></div>
    <div class="games-blob" style="width: 40vw; height: 40vh; background: #06b6d4; bottom: -10vh; left: -10vw;"></div>

    <!-- Fondo Dinámico: Carrusel Infinito -->
    <div class="fixed inset-0 w-full h-full flex items-center -z-10 bg-slate-50 overflow-hidden">
        <div class="carousel-track">
            <?php 
            global $CARRERAS;
            $items = array_filter($CARRERAS, fn($c) => $c['activa']);
            // Repetimos los logos para el efecto infinito
            for ($i = 0; $i < 2; $i++):
                foreach ($items as $sigla => $carrera):
            ?>
                <div class="carousel-item">
                    <img src="public/img/<?php echo htmlspecialchars($carrera['logo']); ?>" alt="<?php echo $sigla; ?>">
                </div>
            <?php 
                endforeach;
            endfor; 
            ?>
        </div>
    </div>

    <!-- Contenedor Principal (Tarjeta) -->
    <div class="w-full max-w-md bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100 p-10 relative z-10 mx-4">
        
        <!-- Cabecera Central con Logo -->
        <div class="flex flex-col items-center mb-10">
            <div id="logoContainer" class="mb-6 bg-blue-600 p-4 rounded-3xl shadow-xl shadow-blue-500/20 transition-all duration-500">
                <img src="public/img/logo_upsrj_blanco.png" alt="UPSRJ" class="h-12 w-auto">
            </div>
            <h1 class="text-2xl font-black text-center text-gray-800 mb-2">Portal de Coordinación</h1>
            <p class="text-sm text-center text-gray-500 max-w-[250px]">Ingresa tus credenciales para administrar múltiples carreras.</p>
        </div>

        <?php if (!empty($login_error)): ?>
            <div class="mb-6 bg-red-50 text-red-600 p-4 rounded-2xl text-sm text-center border border-red-100 font-bold">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" id="loginForm">
            <!-- Selector Interactivo de Carrera -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <label class="text-sm font-bold text-gray-700">Carrera a Administrar:</label>
                </div>
                
                <input type="hidden" name="carrera" id="carreraInput" value="IAEV">
                <div class="grid grid-cols-3 gap-2" id="careerToggleGroup">
                    <?php foreach ($items as $sigla => $carrera): ?>
                        <button type="button" 
                                onclick="selectCareer('<?php echo $sigla; ?>')"
                                id="btn-<?php echo $sigla; ?>"
                                data-color="<?php echo $carrera['color_hex']; ?>"
                                class="career-btn py-3 px-1 rounded-xl text-xs font-black transition-all border border-transparent
                                       <?php echo $sigla === 'IAEV' ? 'active-career text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'; ?>"
                                style="<?php echo $sigla === 'IAEV' ? 'background-color: '.$carrera['color_hex'].'; box-shadow: 0 10px 15px -3px '.$carrera['color_hex'].'4d;' : ''; ?>">
                            <?php echo $sigla; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Campo de Contraseña -->
            <div class="mb-10">
                <label for="password" class="block text-sm font-bold text-gray-700 mb-3">Contraseña Global:</label>
                <div class="relative group">
                    <input type="password" name="password" id="password" required
                           class="w-full px-5 py-4 rounded-2xl border border-gray-100 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all bg-gray-50 text-gray-700 placeholder:text-gray-300 pr-12"
                           placeholder="••••••••">
                    <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600 transition-colors p-1">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: block;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg id="eyeSlashIcon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.047m4.522-2.23a9.459 9.459 0 013.458-.723c4.478 0 8.268 2.943 9.542 7a10.004 10.004 0 01-2.25 3.513M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" id="mainSubmitBtn" class="w-full bg-blue-600 hover:brightness-110 text-white font-black py-4 rounded-2xl transition-all shadow-xl transform active:scale-[0.98] uppercase tracking-wider text-sm transition-all duration-300 border-none">
                Iniciar Sesión
            </button>
        </form>

    </div>

    <script>
        function selectCareer(sigla) {
            const input = document.getElementById('carreraInput');
            const submitBtn = document.getElementById('mainSubmitBtn');
            const logoContainer = document.getElementById('logoContainer');
            input.value = sigla;

            document.querySelectorAll('.career-btn').forEach(btn => {
                btn.style.backgroundColor = '';
                btn.style.boxShadow = '';
                btn.classList.remove('text-white', 'active-career');
                btn.classList.add('bg-gray-100', 'text-gray-500', 'hover:bg-gray-200');
            });

            const activeBtn = document.getElementById('btn-' + sigla);
            const color = activeBtn.getAttribute('data-color');

            activeBtn.classList.remove('bg-gray-100', 'text-gray-500', 'hover:bg-gray-200');
            activeBtn.classList.add('text-white', 'active-career');
            activeBtn.style.backgroundColor = color;
            activeBtn.style.boxShadow = `0 10px 15px -3px ${color}4d`;

            // Actualizar botón principal y contenedor de logo
            if (submitBtn) {
                submitBtn.style.backgroundColor = color;
                submitBtn.style.boxShadow = `0 15px 20px -3px ${color}66`;
            }
            
            if (logoContainer) {
                logoContainer.style.backgroundColor = color;
                logoContainer.style.boxShadow = `0 10px 20px -3px ${color}4d`;
            }
        }

        function togglePassword() {
            const input = document.getElementById('password');
            const eye = document.getElementById('eyeIcon');
            const eyeSlash = document.getElementById('eyeSlashIcon');

            if (input.type === 'password') {
                input.type = 'text';
                eye.style.display = 'none';
                eyeSlash.style.display = 'block';
            } else {
                input.type = 'password';
                eye.style.display = 'block';
                eyeSlash.style.display = 'none';
            }
        }

        window.onload = () => {
            selectCareer('IAEV');
        };
    </script>
</body>
</html>
