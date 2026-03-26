<?php
/**
 * app/views/pages/preferencias_docente.php
 * Portal autónomo para que los docentes elijan sus horarios de clase.
 * Se carga sin el Layout principal del ERP, ya que es sólo para los profesores.
 */
$carrera_sigla = $_GET['c'] ?? 'IAEV';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disponibilidad Docente - UPSRJ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .grid-cell { transition: all 0.2s; cursor: pointer; }
        .grid-cell.active { background-color: #3b82f6; border-color: #2563eb; }
        .grid-cell.inactive { background-color: #f1f5f9; border-color: #e2e8f0; }
        .grid-cell:hover { transform: scale(0.95); }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-blue-200">

<div class="max-w-4xl mx-auto p-6 md:p-12 space-y-8">
    
    <!-- HEADER -->
    <div class="bg-white rounded-[2rem] p-8 md:p-12 shadow-sm border border-slate-100 text-center relative overflow-hidden">
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-blue-50 rounded-full blur-3xl opacity-50"></div>
        <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-indigo-50 rounded-full blur-3xl opacity-50"></div>
        
        <span class="material-symbols-outlined text-blue-600 text-5xl mb-4 relative z-10">event_available</span>
        <h1 class="text-3xl font-black text-slate-900 tracking-tight relative z-10">Preferencias de Horario</h1>
        <p class="text-slate-500 text-sm font-medium mt-3 max-w-xl mx-auto relative z-10">
            Ayúdanos a armar el horario para el próximo cuatrimestre seleccionado tu nombre y pintando los bloques donde 
            <strong class="text-slate-800">SÍ tienes disponibilidad</strong> para impartir clases en la Universidad.
        </p>
    </div>

    <!-- SECCIÓN: LOGIN DOCENTE -->
    <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-slate-100" id="login-section">
        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Identifícate como Docente</label>
        <select id="docente-select" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold text-slate-700 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all cursor-pointer">
            <option value="">Cargando docentes...</option>
        </select>
        <button onclick="loadDisponibilidad()" class="mt-6 w-full bg-slate-900 text-white font-black text-sm py-4 rounded-2xl hover:bg-slate-800 transition-all shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:shadow-[0_8px_30px_rgb(0,0,0,0.2)] flex items-center justify-center gap-2">
            Continuar al Calendario <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
    </div>

    <!-- SECCIÓN: CALENDARIO -->
    <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-slate-100 hidden animate-[fadeIn_0.5s_ease-out]" id="calendar-section">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4 border-b border-slate-100 pb-6">
            <div>
                <h2 class="text-2xl font-black text-slate-900 tracking-tight" id="docente-name">Docente</h2>
                <div class="flex items-center gap-2 mt-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Modo de Edición Activo</p>
                </div>
            </div>
            <button onclick="location.reload()" class="text-xs font-bold text-slate-500 hover:text-slate-800 hover:underline px-4 py-2 bg-slate-50 rounded-xl transition-all">
                No soy yo, cambiar
            </button>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 mb-6 flex items-start gap-4">
            <span class="material-symbols-outlined text-blue-500 mt-0.5">touch_app</span>
            <div>
                <h4 class="text-xs font-black text-blue-900 uppercase tracking-wider mb-1">Instrucciones</h4>
                <p class="text-xs text-blue-800 leading-relaxed font-medium">Haz clic en los cuadros para colorearlos de <strong>azul</strong> indicando que SÍ estás disponible en esa hora. Los espacios en <strong>gris</strong> significan que NO estás disponible.</p>
            </div>
        </div>

        <div class="overflow-x-auto pb-4">
            <div class="min-w-[600px]" id="calendar-grid">
                <!-- Javascript will render grid -->
            </div>
        </div>

        <!-- MATERIAS PREFERIDAS -->
        <div class="mt-8 bg-slate-50 border border-slate-100 rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-indigo-500">menu_book</span>
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">¿Qué materias prefieres impartir?</h3>
            </div>
            <p class="text-xs text-slate-500 mb-6">Selecciona las materias de tu preferencia ordenadas por cuatrimestre.</p>
            <div id="materias-container" class="space-y-6">
                <!-- Rendered by JS -->
            </div>
        </div>

        <!-- AULAS PREFERIDAS -->
        <div class="mt-8 bg-slate-50 border border-slate-100 rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-emerald-500">meeting_room</span>
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">¿Qué espacios locales prefieres?</h3>
            </div>
            <p class="text-xs text-slate-500 mb-6">Selecciona los laboratorios o salones que prefieres ocupar en tus clases.</p>
            <div id="aulas-container" class="flex flex-wrap gap-3">
                <!-- Rendered by JS -->
            </div>
        </div>

        <div class="mt-10 flex flex-col sm:flex-row items-center justify-end gap-4">
            <button onclick="clearGrid()" class="px-6 py-3.5 bg-slate-100 text-slate-600 rounded-2xl font-black text-sm hover:bg-slate-200 transition-all w-full sm:w-auto">
                Limpiar Todo
            </button>
            <button onclick="saveDisponibilidad()" id="btn-save" class="px-8 py-3.5 bg-blue-600 text-white rounded-2xl font-black text-sm hover:bg-blue-700 transition-all flex items-center justify-center gap-2 shadow-[0_8px_30px_rgb(37,99,235,0.24)] w-full sm:w-auto">
                <span class="material-symbols-outlined text-[18px]">save</span> Guardar Mis Preferencias
            </button>
        </div>
    </div>
    
    <div class="text-center pb-8 mt-12">
        <p class="text-[10px] font-bold text-slate-400">Plataforma de Gestión Académica &copy; <?= date('Y') ?></p>
    </div>

</div>

<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
    const C = new URLSearchParams(window.location.search).get('c') || '<?= htmlspecialchars($carrera_sigla, ENT_QUOTES) ?>';
    const API = `api/docentes_public.php?c=${C}`;
    let currentDocenteId = null;
    let catalogMaterias = [];
    let catalogAulas = [];
    let selectedMaterias = new Set();
    let selectedAulas = new Set();
    
    // Iniciar peticion de lista y catálogo
    Promise.all([
        fetch(API + '&action=list').then(r => r.json()),
        fetch(API + '&action=catalog').then(r => r.json())
    ]).then(([listRes, catalogRes]) => {
        const sel = document.getElementById('docente-select');
        // Sort by name
        listRes.data.sort((a,b) => a.nombre.localeCompare(b.nombre));
        
        sel.innerHTML = '<option value="">-- Selecciona tu nombre en la lista --</option>' + 
            listRes.data.map(d => `<option value="${d.id}">${d.nombre}</option>`).join('');

        // Populate catalogs
        catalogMaterias = catalogRes.data?.materias || [];
        catalogAulas = catalogRes.data?.aulas || [];
        renderCatalogUI();
            
    }).catch(e => {
        const sel = document.getElementById('docente-select');
        sel.innerHTML = '<option value="">Error al cargar datos iniciales.</option>';
    });

    function renderCatalogUI() {
        // Render Aulas
        const aulasHtml = catalogAulas.map(a => {
            return `<label class="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2.5 rounded-xl cursor-pointer hover:border-emerald-300 transition-colors">
                <input type="checkbox" value="${a.id}" class="aula-cb w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500" onchange="toggleAula('${a.id}', this.checked)">
                <span class="text-xs font-bold text-slate-700">${a.nombre} <span class="text-slate-400 font-normal">(${a.tipo})</span></span>
            </label>`;
        }).join('');
        document.getElementById('aulas-container').innerHTML = aulasHtml;

        // Render Materias grouped by cuatrimestre
        const grouped = {};
        catalogMaterias.forEach(m => {
            const c = m.cuatrimestre || 0;
            if(!grouped[c]) grouped[c] = [];
            grouped[c].push(m);
        });
        
        const cuatrimestres = Object.keys(grouped).sort((a,b) => parseInt(a) - parseInt(b));
        const materiasHtml = cuatrimestres.map(c => {
            const items = grouped[c].map(m => {
                return `<label class="flex items-start gap-2 p-2 hover:bg-slate-100 rounded-lg cursor-pointer transition-colors">
                    <input type="checkbox" value="${m.id}" class="materia-cb mt-0.5 w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" onchange="toggleMateria('${m.id}', this.checked)">
                    <div>
                        <p class="text-xs font-bold text-slate-700 leading-tight">${m.nombre}</p>
                        <p class="text-[10px] text-slate-500">${m.horas_semanales} hrs/sem</p>
                    </div>
                </label>`;
            }).join('');

            return `<div>
                <h4 class="text-xs font-bold text-slate-400 mb-2 uppercase tracking-wide border-b border-slate-200 pb-1">Cuatrimestre ${c}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    ${items}
                </div>
            </div>`;
        }).join('');
        document.getElementById('materias-container').innerHTML = materiasHtml;
    }

    function toggleAula(id, checked) {
        if(checked) selectedAulas.add(id);
        else selectedAulas.delete(id);
    }

    function toggleMateria(id, checked) {
        if(checked) selectedMaterias.add(id);
        else selectedMaterias.delete(id);
    }

    async function loadDisponibilidad() {
        const id = document.getElementById('docente-select').value;
        if(!id) return alert("Por favor, selecciona tu nombre de la lista para continuar.");
        
        currentDocenteId = id;
        const btn = document.querySelector('#login-section button');
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[18px]">sync</span> Cargando...';
        
        try {
            const res = await fetch(API + '&action=get&id=' + id);
            const data = await res.json();
            
            if(!data.success) throw new Error(data.error);
            
            document.getElementById('docente-name').textContent = data.data.nombre;
            
            // Inicializar state vacío
            dispState = {1:{}, 2:{}, 3:{}, 4:{}, 5:{}};
            const dbDisp = typeof data.data.disponibilidad === 'string' 
                            ? JSON.parse(data.data.disponibilidad || '[]') 
                            : (data.data.disponibilidad || []);
            
            dbDisp.forEach(b => {
                const d = parseInt(b.dia);
                for(let h = parseInt(b.inicio); h <= parseInt(b.fin); h++) {
                    if (dispState[d]) dispState[d][h] = true;
                }
            });

            // Set selections
            selectedAulas = new Set((data.data.aulas_preferidas || []).map(String));
            selectedMaterias = new Set((data.data.materia_ids || []).map(String));

            // Populate checkboxes
            document.querySelectorAll('.aula-cb').forEach(cb => {
                cb.checked = selectedAulas.has(String(cb.value));
            });
            document.querySelectorAll('.materia-cb').forEach(cb => {
                cb.checked = selectedMaterias.has(String(cb.value));
            });

            renderGrid();
            
            document.getElementById('login-section').classList.add('hidden');
            document.getElementById('calendar-section').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch(e) {
            btn.innerHTML = 'Continuar al Calendario <span class="material-symbols-outlined text-[18px]">arrow_forward</span>';
            alert("Error al obtener la disponibilidad: " + e.message);
        }
    }

    function renderGrid() {
        const dias = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
        const horas = Array.from({length:14}, (_,i) => i+7); // 7 to 20
        
        // Evitamos forzar renders innecesarios usando un fragmento si se volviera lento, 
        // pero 14x5=70 celdas es instantáneo.
        let html = '<div class="grid grid-cols-[60px_repeat(5,1fr)] gap-2">';
        
        // Header de días
        html += '<div class="flex items-center justify-center font-black text-[9px] text-slate-400 uppercase">Hora</div>';
        dias.forEach(d => {
            html += `<div class="text-center font-black text-[11px] text-slate-600 uppercase py-3 bg-slate-50 rounded-xl border border-slate-100">${d}</div>`;
        });
        
        horas.forEach(h => {
            // Columna hora
            const displayHora = h > 12 ? (h-12)+':00 PM' : h+':00 '+(h==12?'PM':'AM');
            html += `<div class="flex items-center justify-end pr-2 font-black text-[10px] text-slate-400 py-3">${displayHora}</div>`;
            
            // Celdas por día
            dias.forEach((_, i) => {
                const dia = i + 1;
                const active = !!dispState[dia][h];
                const css = active ? 'active text-blue-100' : 'inactive text-transparent hover:border-slate-300';
                
                html += `<div onclick="toggleCell(${dia}, ${h})" 
                            class="grid-cell ${css} rounded-xl h-12 flex items-center justify-center border-2 border-transparent">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                </div>`;
            });
        });
        html += '</div>';
        document.getElementById('calendar-grid').innerHTML = html;
    }

    function toggleCell(dia, hora) {
        dispState[dia][hora] = !dispState[dia][hora];
        renderGrid();
    }
    
    function clearGrid() {
        if(!confirm("¿Deseas borrar toda la disponibilidad actual?")) return;
        dispState = {1:{}, 2:{}, 3:{}, 4:{}, 5:{}};
        renderGrid();
    }

    async function saveDisponibilidad() {
        const btn = document.getElementById('btn-save');
        const origHTML = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[18px]">sync</span> Guardando...'; 
        btn.disabled = true;
        
        // Analizar matriz a bloques consolidados (optimizacion de BD)
        const blocks = [];
        for (let d = 1; d <= 5; d++) {
            let inicio = null;
            let fin = null;
            for (let h = 7; h <= 21; h++) {
                if (dispState[d][h]) {
                    if (inicio === null) inicio = h;
                    fin = h;
                } else {
                    if (inicio !== null) {
                        blocks.push({ dia: d, inicio: inicio, fin: fin });
                        inicio = null; fin = null;
                    }
                }
            }
        }

        try {
            const req = await fetch(API + '&action=save&id=' + currentDocenteId, {
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    disponibilidad: blocks,
                    aulas_preferidas: Array.from(selectedAulas),
                    materia_ids: Array.from(selectedMaterias)
                })
            });
            const res = await req.json();
            
            if(!res.success) throw new Error(res.error);
            
            btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check</span> ¡Preferencias Guardadas!';
            btn.classList.replace('bg-blue-600', 'bg-emerald-500');
            
            setTimeout(() => {
                btn.innerHTML = origHTML;
                btn.classList.replace('bg-emerald-500', 'bg-blue-600');
                btn.disabled = false;
            }, 3000);
            
        } catch(e) { 
            alert("Ocurrió un error al guardar: " + e.message); 
            btn.innerHTML = origHTML;
            btn.disabled = false; 
        }
    }
</script>
</body>
</html>
