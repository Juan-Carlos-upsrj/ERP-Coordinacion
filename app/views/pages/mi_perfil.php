<?php
/**
 * app/views/pages/mi_perfil.php
 * Vista para que el coordinador cambie su contraseña.
 */

$saved = isset($_GET['saved']) && $_GET['saved'] === 'ok';
$err   = $_GET['err'] ?? '';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-10">
        <h1 class="text-4xl font-black text-gray-800 tracking-tight">Mi Perfil</h1>
        <p class="text-gray-500 mt-2">Gestiona tu información personal y seguridad de cuenta.</p>
    </div>

    <!-- Mensajes de Estado -->
    <?php if ($saved): ?>
        <div class="mb-8 bg-emerald-50 border border-emerald-200 text-emerald-700 p-5 rounded-3xl flex items-center gap-4 animate-in fade-in slide-in-from-top-4">
            <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600">
                <span class="material-symbols-outlined">check_circle</span>
            </div>
            <div>
                <p class="font-bold">¡Contraseña actualizada!</p>
                <p class="text-xs opacity-80">Tus cambios se han guardado correctamente en la base de datos.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($err === 'pass_incorrecta'): ?>
        <div class="mb-8 bg-red-50 border border-red-200 text-red-700 p-5 rounded-3xl flex items-center gap-4">
            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600">
                <span class="material-symbols-outlined">error</span>
            </div>
            <div>
                <p class="font-bold">Error de verificación</p>
                <p class="text-xs opacity-80">La contraseña actual que ingresaste no es correcta.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Info Card -->
        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm col-span-1">
            <div class="flex flex-col items-center text-center">
                <div class="w-24 h-24 bg-brand-100 rounded-full flex items-center justify-center text-brand-600 text-3xl font-black mb-4 border-4 border-white shadow-lg">
                    <?php echo substr($_SESSION['usuario_nombre'], 0, 2); ?>
                </div>
                <h2 class="text-xl font-black text-gray-800"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h2>
                <span class="mt-1 px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-[10px] font-bold uppercase tracking-wider">
                    <?php echo htmlspecialchars($_SESSION['usuario_rol']); ?>
                </span>
                
                <div class="w-full h-px bg-gray-50 my-6"></div>
                
                <div class="w-full text-left space-y-4">
                    <div>
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Carrera Activa</label>
                        <p class="text-sm font-bold text-gray-700"><?php echo $_SESSION['carrera_activa']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Password Form -->
        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm col-span-2">
            <h3 class="text-lg font-black text-gray-800 mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-500">lock</span>
                Cambiar Contraseña
            </h3>
            
            <form action="index.php" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="cambiar_password">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2 px-1 uppercase tracking-wider">Contraseña Actual</label>
                    <input type="password" name="current_pass" required
                           class="w-full px-5 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-mono"
                           placeholder="••••••••">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-2 px-1 uppercase tracking-wider">Nueva Contraseña</label>
                        <input type="password" name="nueva_pass" required id="pass1"
                               class="w-full px-5 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-mono"
                               placeholder="Min. 8 caracteres">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-2 px-1 uppercase tracking-wider">Confirmar Nueva</label>
                        <input type="password" name="confirm_pass" required id="pass2"
                               class="w-full px-5 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-mono"
                               placeholder="••••••••">
                    </div>
                </div>

                <div id="matchError" class="hidden text-xs text-red-500 font-bold px-1 bg-red-50 p-2 rounded-lg">
                    Las contraseñas no coinciden.
                </div>

                <div class="pt-4">
                    <button type="submit" id="submitPassBtn" disabled
                            class="w-full md:w-auto px-10 py-4 bg-gray-200 text-gray-400 cursor-not-allowed font-black rounded-2xl transition-all uppercase tracking-wider text-sm">
                        Actualizar Contraseña
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    const p1 = document.getElementById('pass1');
    const p2 = document.getElementById('pass2');
    const err = document.getElementById('matchError');
    const btn = document.getElementById('submitPassBtn');

    function validate() {
        if (p1.value.length > 0 && p1.value === p2.value) {
            err.classList.add('hidden');
            btn.disabled = false;
            btn.classList.remove('bg-gray-200', 'text-gray-400', 'cursor-not-allowed');
            btn.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700', 'shadow-xl', 'shadow-blue-500/30');
        } else {
            if (p2.value.length > 0) err.classList.remove('hidden');
            btn.disabled = true;
            btn.classList.add('bg-gray-200', 'text-gray-400', 'cursor-not-allowed');
            btn.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700', 'shadow-xl', 'shadow-blue-500/30');
        }
    }

    p1.addEventListener('input', validate);
    p2.addEventListener('input', validate);
</script>
