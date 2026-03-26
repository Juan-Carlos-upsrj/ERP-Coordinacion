<?php
/**
 * nfc_writer.php
 * Herramienta de grabación masiva usando Web NFC API adaptada para el portal Coordinación.
 * Solo compatible con Chrome en Android y requiere HTTPS.
 */

require_once 'app/models/AnomaliasModel.php'; // Usa la misma DB de estudiantes
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

try {
    $sql = "SELECT DISTINCT alumno_nombre as nombre, alumno_id, grupo_nombre
            FROM asistencia_clases
            WHERE alumno_nombre IS NOT NULL AND alumno_id IS NOT NULL
            ORDER BY grupo_nombre ASC, alumno_nombre ASC";
    $stmt = $pdo->query($sql);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching database: " . $e->getMessage());
}

// Apunta al nuevo Portal de Alumnos de esta misma plataforma ERP
// Se usa dirname($_SERVER['PHP_SELF']) para obtener '/coordinacion' dinámicamente
$base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$base_url = "https://" . $_SERVER['HTTP_HOST'] . $base_path . "/portal_alumnos/resultado.php?id=";

// Preparar los datos en JSON para consumirlos con JavaScript
$alumnos_json = [];
foreach ($alumnos as $al) {
    $alumnos_json[] = [
        'nombre' => $al['nombre'],
        'grupo' => $al['grupo_nombre'],
        'url' => $base_url . $al['alumno_id']
    ];
}
$alumnos_json_str = json_encode($alumnos_json, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>

<style>
    /* Pulse Animation for Scanning State */
    @keyframes pulse-ring {
        0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 20px rgba(59, 130, 246, 0); }
        100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    }
    .scanning-active .nfc-icon-wrapper { animation: pulse-ring 2s infinite; background-color: #3b82f6; color: white; }
</style>

<div class="p-4 md:p-8 flex items-center justify-center min-h-[calc(100vh-100px)]">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden relative">
        
        <!-- Header -->
        <div class="bg-slate-900 px-6 py-5 text-white flex justify-between items-center">
            <div>
                <h1 class="text-lg font-black tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-400">contactless</span>
                    NFC Auto-Writer
                </h1>
                <p class="text-xs font-medium text-slate-400 mt-0.5">Requiere Android + Chrome</p>
            </div>
            <div class="text-right">
                <span class="text-2xl font-black" id="counter">1</span>
                <span class="text-xs text-slate-400">/ <?php echo count($alumnos_json); ?></span>
            </div>
        </div>

        <!-- Alerta SSL -->
        <div id="ssl-warning" class="hidden bg-red-50 border-l-4 border-red-500 p-4 m-4 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <span class="material-symbols-outlined text-red-500">lock_error</span>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700 font-bold">Protocolo Inseguro</p>
                    <p class="text-xs text-red-600 mt-1">Web NFC <b>solo funciona en páginas web seguras (https://)</b>. Si estás migrando el servidor, asegúrate de instalar el certificado SSL primero.</p>
                </div>
            </div>
        </div>

        <!-- Alerta Compatibilidad -->
        <div id="nfc-warning" class="hidden bg-amber-50 border-l-4 border-amber-500 p-4 m-4 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <span class="material-symbols-outlined text-amber-500">warning</span>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-amber-700 font-bold">Web NFC No Soportado</p>
                    <p class="text-xs text-amber-600 mt-1">Este dispositivo no soporta Web NFC. Causas comunes: estás usando iOS/iPhone, no es Chrome, o el hardware NFC está apagado.</p>
                </div>
            </div>
        </div>

        <!-- Main Content (Student Card) -->
        <div class="p-6 md:p-8 text-center flex flex-col items-center">
            
            <div id="nfc-status-ring" class="nfc-icon-wrapper w-24 h-24 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-6 transition-all duration-300">
                <span class="material-symbols-outlined text-[48px]" id="nfc-icon">nfc</span>
            </div>

            <div class="w-full">
                <span id="student-group" class="inline-block px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-lg mb-3">
                    Cargando...
                </span>
                <h2 id="student-name" class="text-xl font-black text-slate-800 leading-tight mb-2">
                    Preparando lista...
                </h2>
                <p id="system-status" class="text-sm font-medium text-slate-500 h-6">Presiona Iniciar para comenzar</p>
            </div>
        </div>

        <!-- Controls -->
        <div class="bg-slate-50 border-t border-slate-100 p-6">
            <button id="btn-start" class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/30 transition-all flex justify-center items-center gap-2">
                <span class="material-symbols-outlined">play_circle</span>
                Iniciar Grabación
            </button>
            <div class="flex justify-between mt-4">
                <button id="btn-prev" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 text-sm font-bold rounded-lg hover:bg-slate-50 disabled:opacity-50 transition-colors">
                    &larr; Anterior
                </button>
                <button id="btn-next" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 text-sm font-bold rounded-lg hover:bg-slate-50 disabled:opacity-50 transition-colors">
                    Saltar &rarr;
                </button>
            </div>
        </div>
    </div>

</div>

<!-- JavaScript Logic para Web NFC -->
<script>
    const alumnosList = <?php echo $alumnos_json_str; ?>;
    let currentIndex = 0;
    let isWriting = false;
    let ndef = null;

    // UI Elements
    const elName = document.getElementById('student-name');
    const elGroup = document.getElementById('student-group');
    const elCounter = document.getElementById('counter');
    const elStatus = document.getElementById('system-status');
    const elIconRing = document.getElementById('nfc-status-ring');
    const elIcon = document.getElementById('nfc-icon');
    
    const btnStart = document.getElementById('btn-start');
    const btnNext = document.getElementById('btn-next');
    const btnPrev = document.getElementById('btn-prev');

    // Initial Checks
    if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
        document.getElementById('ssl-warning').classList.remove('hidden');
        btnStart.disabled = true;
    } else if (!('NDEFReader' in window)) {
        document.getElementById('nfc-warning').classList.remove('hidden');
        btnStart.disabled = true;
    }

    // Render Current Student
    function updateUI() {
        if (alumnosList.length === 0) return;
        const current = alumnosList[currentIndex];
        elName.textContent = current.nombre;
        elGroup.textContent = current.grupo;
        elCounter.textContent = currentIndex + 1;
    }

    window.onload = updateUI;

    // Modificador de estado visual
    function setUIState(state, message) {
        elStatus.textContent = message;
        
        if (state === 'scanning') {
            elIconRing.classList.add('scanning-active');
            elIconRing.style.backgroundColor = '';
            elIconRing.classList.remove('bg-emerald-500', 'bg-red-500');
            elIcon.textContent = 'sensors';
            btnStart.innerHTML = '<span class="material-symbols-outlined">stop_circle</span> Detener';
            btnStart.classList.replace('bg-blue-600', 'bg-slate-800');
            btnStart.classList.replace('hover:bg-blue-700', 'hover:bg-slate-900');
            btnStart.classList.replace('shadow-blue-500/30', 'shadow-slate-900/30');
        } else if (state === 'success') {
            elIconRing.classList.remove('scanning-active');
            elIconRing.classList.add('bg-emerald-500', 'text-white');
            elIcon.textContent = 'check_circle';
            
            // Vibración de éxito (si está soportado)
            if(navigator.vibrate) navigator.vibrate([100, 50, 100]);

        } else if (state === 'error') {
            elIconRing.classList.remove('scanning-active');
            elIconRing.classList.add('bg-red-500', 'text-white');
            elIcon.textContent = 'error';
            
            if(navigator.vibrate) navigator.vibrate([300]);
        } else {
            // Idle
            elIconRing.classList.remove('scanning-active', 'bg-emerald-500', 'bg-red-500', 'text-white');
            elIconRing.classList.add('bg-slate-100', 'text-slate-400');
            elIcon.textContent = 'nfc';
            btnStart.innerHTML = '<span class="material-symbols-outlined">play_circle</span> Iniciar Grabación';
            btnStart.classList.replace('bg-slate-800', 'bg-blue-600');
            btnStart.classList.replace('hover:bg-slate-900', 'hover:bg-blue-700');
            btnStart.classList.replace('shadow-slate-900/30', 'shadow-blue-500/30');
        }
    }

    // Web NFC Logic
    async function startScanning() {
        try {
            ndef = new NDEFReader();
            await ndef.scan();
            
            setUIState('scanning', 'Acerca una tarjeta NFC en blanco...');
            isWriting = true;

            // Event listener strictly for writing when a tag is discovered
            ndef.onreading = async event => {
                if(!isWriting) return;
                
                const currentStudent = alumnosList[currentIndex];
                const record = {
                    recordType: "url", 
                    data: currentStudent.url
                };

                try {
                    // Intentar escribir el URL
                    await ndef.write({ records: [record] });
                    
                    setUIState('success', '¡Grabado Exitoso!');
                    
                    // Esperar 1.5s y pasar al siguiente
                    setTimeout(() => {
                        if (currentIndex < alumnosList.length - 1) {
                            currentIndex++;
                            updateUI();
                            setUIState('scanning', 'Listo para el siguiente. Acerca tarjeta...');
                        } else {
                            setUIState('idle', '¡Lista completada!');
                            isWriting = false;
                            document.getElementById('btn-next').disabled = true;
                        }
                    }, 1500);

                } catch (error) {
                    setUIState('error', 'Error al grabar. Intenta de nuevo.');
                    setTimeout(() => {
                        setUIState('scanning', 'Acerca la tarjeta nuevamente...');
                    }, 2000);
                }
            };

            ndef.onreadingerror = () => {
                setUIState('error', 'Error de lectura. Separa y vuelve a acercar.');
                setTimeout(() => {
                    setUIState('scanning', 'Acerca la tarjeta nuevamente...');
                }, 2000);
            };

        } catch (error) {
            setUIState('error', 'Permiso denegado o error de hardware.');
            console.error("Error iniciando NFC:", error);
            isWriting = false;
        }
    }

    function stopScanning() {
        setUIState('idle', 'Grabación detenida. Presiona Iniciar.');
        isWriting = false;
    }

    // Button Listeners
    btnStart.addEventListener('click', async () => {
        if (isWriting) stopScanning();
        else await startScanning();
    });

    btnNext.addEventListener('click', () => {
        if (currentIndex < alumnosList.length - 1) {
            currentIndex++;
            updateUI();
            if(isWriting) setUIState('scanning', 'Saltado. Acerca tarjeta para el alumno visible...');
        }
    });

    btnPrev.addEventListener('click', () => {
        if (currentIndex > 0) {
            currentIndex--;
            updateUI();
            if(isWriting) setUIState('scanning', 'Regresó. Acerca tarjeta para el alumno visible...');
        }
    });

</script>
