<?php
/**
 * Panel de Control — login.php
 * Interfaz de acceso protegida.
 */
require_once 'config.php';
// session_start() ya viene implícito si usamos session_name en config.php y luego session_start
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === PANEL_PASSWORD) {
        $_SESSION['panel_logged_in'] = true;
        $_SESSION['panel_user'] = 'Administrador Global';
        header('Location: index.php');
        exit;
    } else {
        $error = 'Contraseña incorrecta. Acceso denegado.';
    }
}

// Si ya está logueado, ir al dashboard
if (!empty($_SESSION['panel_logged_in'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Panel de Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .carousel-track {
            display: flex;
            width: calc(250px * 12);
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
    </style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen relative overflow-hidden">

    <!-- Fondo Dinámico: Carrusel Infinito -->
    <div class="fixed inset-0 w-full h-full flex items-center -z-10 bg-slate-50 overflow-hidden">
        <div class="carousel-track">
            <?php
            global $CARRERAS;
            $items = array_filter($CARRERAS, fn($c) => $c['activa']);
            for ($i = 0; $i < 2; $i++):
                foreach ($items as $sigla => $carrera):
            ?>
                <div class="carousel-item">
                    <img src="../public/img/<?php echo htmlspecialchars($carrera['logo']); ?>" alt="<?php echo $sigla; ?>">
                </div>
            <?php
                endforeach;
            endfor;
            ?>
        </div>
    </div>

    <div class="relative z-10 w-full max-w-md px-6 mx-4">
        <div class="bg-white rounded-[2.5rem] p-10 shadow-2xl border border-gray-100">
            <!-- Header -->
            <div class="text-center mb-10">
                <div class="w-20 h-20 bg-slate-900 rounded-3xl mx-auto flex items-center justify-center shadow-xl shadow-slate-200 mb-6">
                    <img src="../public/img/logo_upsrj_blanco.png" alt="UPSRJ" class="h-10 w-auto">
                </div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight leading-none">Panel de Control</h1>
                <p class="text-slate-400 mt-3 font-bold uppercase tracking-widest text-[9px]">Administración Global — UPSRJ</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-100 rounded-2xl p-4 mb-8 flex items-center gap-3">
                <span class="material-symbols-outlined text-red-500 text-lg">warning</span>
                <p class="text-xs font-bold text-red-600"><?php echo $error; ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Clave de Acceso Maestro</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 text-lg">lock</span>
                        <input type="password" name="password" required autofocus
                               placeholder="••••••••••••"
                               class="w-full bg-gray-50 border border-gray-100 rounded-2xl py-4 pl-14 pr-4 text-slate-700 placeholder:text-gray-300 focus:border-slate-300 focus:ring-4 focus:ring-slate-100 transition-all outline-none font-medium">
                    </div>
                </div>

                <button type="submit" class="w-full bg-slate-900 hover:bg-black text-white font-black py-4 rounded-2xl shadow-xl shadow-slate-200 transition-all flex items-center justify-center gap-2 group tracking-widest text-xs">
                    ENTRAR AL SISTEMA
                    <span class="material-symbols-outlined text-sm transition-transform group-hover:translate-x-1">arrow_forward</span>
                </button>
            </form>

            <div class="mt-10 text-center">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-tighter italic opacity-50">SISTEMA DE GESTIÓN ACADÉMICA V2.5</p>
            </div>
        </div>
    </div>
</body>
</html>
