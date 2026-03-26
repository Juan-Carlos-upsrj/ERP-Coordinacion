<?php
/**
 * app/views/pages/preview_iaev.php
 * Vista exclusiva para IAEV que embebe el Google Apps Script proporcionado por el usuario.
 */

// Doble validación de seguridad (además de que el botón no aparezca, evitamos acceso directo por URL)
if (($_SESSION['carrera_activa'] ?? '') !== 'IAEV') {
    echo "<div class='p-10 text-center mt-10'>
            <div class='w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4'>
                <span class='material-symbols-outlined text-4xl'>block</span>
            </div>
            <h1 class='text-2xl font-black text-gray-800'>Acceso Denegado</h1>
            <p class='text-gray-500 mt-2'>Esta herramienta es exclusiva para la carrera de IAEV.</p>
          </div>";
    return;
}
?>

<div class="h-[calc(100vh-120px)] w-full relative bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-200 flex flex-col mb-8">
    <div class="px-3 py-2 border-b border-gray-200 bg-gray-50/80 flex justify-end items-center gap-2 shrink-0">
        <a href="https://docs.google.com/forms/d/1zKkjAweN1iEh-dOSYQTzUrgccZNhFUcEnUySHZZC1tI/edit?hl=es&pli=1" 
           target="_blank" 
           class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-bold rounded-lg hover:bg-gray-50 hover:text-indigo-600 flex items-center gap-1.5 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[16px]">edit_document</span> Editar formulario
        </a>
        <button type="button" 
                onclick="navigator.clipboard.writeText('https://docs.google.com/forms/d/e/1FAIpQLSdGpi4X4-tQqucesrAgd74b5WjUfr4UK2LEjKXwBmosBJjXpg/viewform?usp=sharing').then(() => { let o = this.innerHTML; this.innerHTML = '<span class=\'material-symbols-outlined text-[16px]\'>check</span><span class=\'btn-text\'>¡Copiado!</span>'; this.classList.add('!text-green-600'); setTimeout(() => { this.innerHTML = o; this.classList.remove('!text-green-600'); }, 2000); })"
                class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-bold rounded-lg hover:bg-gray-50 hover:text-indigo-600 flex items-center gap-1.5 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[16px]">content_copy</span> <span class="btn-text">Compartir formulario</span>
        </button>
        <a href="https://script.google.com/a/macros/upsrj.edu.mx/s/AKfycbzBmhGWYVX8NFjTjxEtMNo37s6ZdLb4mK6m4j4YG63EA7zcH6zzxM_M1FUpJonIdRLSnA/exec" 
           target="_blank" 
           class="px-3 py-1.5 bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold rounded-lg hover:bg-indigo-100 flex items-center gap-1.5 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[16px]">open_in_new</span> Abrir Externo
        </a>
    </div>
    
    <div class="flex-1 w-full bg-slate-50 relative pointer-events-auto">
        <!-- El sandbox permite scripts, formularios y abrir popups, que es lo estándar requerido por Google Scripts -->
        <iframe src="https://script.google.com/a/macros/upsrj.edu.mx/s/AKfycbzBmhGWYVX8NFjTjxEtMNo37s6ZdLb4mK6m4j4YG63EA7zcH6zzxM_M1FUpJonIdRLSnA/exec" 
                class="absolute inset-0 w-full h-full border-0" 
                sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-top-navigation-by-user-activation" 
                allowfullscreen>
        </iframe>
    </div>
</div>
