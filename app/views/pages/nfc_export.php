<?php
/**
 * nfc_export.php
 * Script for Coordinadores to list students and their unique NFC-ready URLs and QRs.
 */

require_once 'app/models/AnomaliasModel.php'; 
$pdo = getConnection($carrera_info['db_name'], $carrera_info['carrera_id']);

// Obtener todos los alumnos activos y sus grupos
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
$base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$base_url = "https://" . $_SERVER['HTTP_HOST'] . $base_path . "/portal_alumnos/resultado.php?id=";
?>

<style>
    @media print {
        .no-print { display: none !important; }
        body { background: white; }
        .card { border: none; box-shadow: none; break-inside: avoid; }
        .sidebar { display: none !important; }
        nav { display: none !important; }
        main { margin: 0 !important; padding: 0 !important; }
    }
</style>

<!-- QRCode.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="max-w-7xl mx-auto p-4 md:p-8">
    <div class="flex items-center justify-between mb-8 pb-6 border-b border-slate-200 flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Exportación NFC / QR</h1>
            <p class="text-sm font-medium text-slate-500 mt-2">URLs únicas preparadas para grabar en etiquetas NFC o credenciales.</p>
        </div>
        <div class="no-print flex items-center gap-3">
            <a href="index.php?v=nfc_writer" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-colors shadow-lg shadow-blue-200">
                <span class="material-symbols-outlined text-[20px]">contactless</span>
                Grabar NFC Masivo
            </a>
            <button onclick="window.print()" class="flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[20px]">print</span>
                Imprimir Credenciales
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($alumnos as $index => $al): 
            $unique_id = $al['alumno_id'];
            $url = $base_url . $unique_id;
            $qr_container_id = "qrcode_" . $index;
        ?>
            <div class="card bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col items-center text-center">
                
                <div class="w-full flex justify-between items-start mb-4">
                    <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded-md border border-slate-200 uppercase tracking-widest">
                        <?php echo htmlspecialchars($al['grupo_nombre']); ?>
                    </span>
                    <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-md border border-blue-100 uppercase tracking-widest">
                        Estudiante
                    </span>
                </div>

                <h3 class="font-bold text-sm text-slate-800 mb-6 flex-1"><?php echo htmlspecialchars($al['nombre']); ?></h3>
                
                <!-- Contenedor del QR -->
                <div id="<?php echo $qr_container_id; ?>" class="mb-6 p-2 bg-white border border-slate-200 rounded-xl shadow-sm flex items-center justify-center min-h-[136px]"></div>

                <!-- Botón para copiar enlace NFC -->
                <button onclick="copyToClipboard('<?php echo $url; ?>')" class="no-print w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-lg border border-blue-200 transition-colors text-xs group">
                    <span class="material-symbols-outlined text-[18px]">nfc</span>
                    Copiar Enlace NFC
                </button>

                <script>
                    // Generar QR para este alumno (retrasado un poco para asegurar render)
                    setTimeout(() => {
                        new QRCode(document.getElementById("<?php echo $qr_container_id; ?>"), {
                            text: "<?php echo $url; ?>",
                            width: 120,
                            height: 120,
                            colorDark : "#0f172a",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.M
                        });
                    }, 50);
                </script>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert("Enlace copiado al portapapeles. Listo para grabar en NFC Tools.");
        }).catch(err => {
            console.error('Error al copiar: ', err);
        });
    }
</script>
