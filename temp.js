
const API   = 'api/horarios.php';
const DAYS  = ['Lunes','Martes','Miércoles','Jueves','Viernes'];
const HOURS = Array.from({length:14}, (_,i) => i+7); // 7–20

// ── Estado global ─────────────────────────────────────────────────────────────
const state = { 
    docentes:[], materias:[], grupos:[], aulas:[], clases:[], config:{}, planes:[],
    isPlanning: false,
    planningPeriod: ''
};
let modalContext = null;  // {type, id?}   — id referencia a state[...] por id
let dragClase    = null;

// ── API helper ────────────────────────────────────────────────────────────────
async function api(resource, method='GET', body=null, id=null) {
    const url = `${API}?resource=${resource}` + (id ? `&id=${id}` : '');
    const opts = { method, headers:{'Content-Type':'application/json'} };
    if (body) opts.body = JSON.stringify(body);
    const res  = await fetch(url, opts);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Error desconocido');
    return json.data;
}

// ── Módulo principal ──────────────────────────────────────────────────────────
const horarios = {

    async init() {
        await this.loadAll();
        this.switchTab('grid');
    },

    async loadAll() {
        state.docenteMaterias = {}; // Initialize early
        try {
            const params = state.isPlanning ? `&periodo=${state.planningPeriod}` : '';
            const results = await Promise.all([
                api('docentes'), api('materias'), api('grupos'),
                api('aulas'),   api(`clases${params}`),   api('config'),
                api('planes')
            ]);
            [state.docentes, state.materias, state.grupos, state.aulas, state.clases, state.config, state.planes] = results;
            
            // Si estamos en modo planeación, simulamos la promoción de cuatrimestre para la UI
            if (state.isPlanning) {
                state.grupos.forEach(g => {
                    if (g.cuatrimestre < (state.config.max_cuatrimestres || 10)) {
                        g.cuatrimestre = parseInt(g.cuatrimestre) + 1;
                    }
                });
            }

            const cfg = state.config;
            const periodLabel = state.isPlanning ? getPeriodLabel(state.planningPeriod.split('-')[1]) : getPeriodLabel(cfg.cuatrimestre_activo);
            const anioLabel = state.isPlanning ? state.planningPeriod.split('-')[0] : (cfg.anio_activo ?? '');

            document.getElementById('hor-subtitle').textContent = 
                `${anioLabel} · ${periodLabel}${state.isPlanning ? ' (VISTA DE PLANEACIÓN)' : ''}`;
            
            this.populateFilters();
            this.refreshAll();
        } catch(err) {
            console.error(err);
            this.toast('❌ Error al cargar datos: ' + err.message, 'error');
            document.getElementById('hor-subtitle').innerHTML = `<span class="text-rose-500">Error de conexión / Base de datos</span>`;
        }
    },

    populateFilters() {
        const fg = document.getElementById('filter-grupo');
        const fd = document.getElementById('filter-docente');
        const currentG = fg.value, currentD = fd.value;
        fg.innerHTML = '<option value="">Todos los grupos</option>' +
            state.grupos.map(g => {
                const suffix = state.isPlanning ? ` (Proyectado: ${g.cuatrimestre}°)` : ` (${g.cuatrimestre}°)`;
                return `<option value="${g.id}">${esc(g.nombre)}${suffix}</option>`;
            }).join('');
        fd.innerHTML = '<option value="">Todos los docentes</option>' +
            state.docentes.map(d=>`<option value="${d.id}">${d.nombre}</option>`).join('');
        if (currentG) fg.value = currentG;
        if (currentD) fd.value = currentD;
        // Alumnos tab selector
        const ag = document.getElementById('alumnos-grupo-select');
        if (ag) ag.innerHTML = '<option value="">— Selecciona un grupo —</option>' +
            state.grupos.map(g=>{
                const count = Array.isArray(g.alumnos) ? g.alumnos.length : (g.total_alumnos || 0);
                return `<option value="${g.id}">${esc(g.nombre)} (${count} alumnos)</option>`;
            }).join('');
            
        // Plan filter for materias
        const fp = document.getElementById('filter-materia-plan');
        if (fp) {
            const currentPlan = fp.value;
            fp.innerHTML = '<option value="">Todos los planes</option>' +
                state.planes.map(p=>`<option value="${esc(p)}">${esc(p)}</option>`).join('');
            if (currentPlan && state.planes.includes(currentPlan)) fp.value = currentPlan;
        }
    },

    switchTab(tabId) {
        document.querySelectorAll('.hor-panel').forEach(p=>p.classList.add('hidden'));
        document.querySelectorAll('.hor-tab').forEach(t=>t.classList.remove('active'));
        document.getElementById(`panel-${tabId}`)?.classList.remove('hidden');
        document.getElementById(`tab-${tabId}`)?.classList.add('active');
        if (tabId==='grid')       this.renderGrid();
        if (tabId==='analisis')   this.loadAnalisis();
        if (tabId==='proyeccion') this.renderProyeccion();
    },

    // ── GRID ─────────────────────────────────────────────────────────────────
    renderGrid() {
        const grid = document.getElementById('schedule-grid');
        if (state.isPlanning) grid.classList.add('is-planning-grid');
        else grid.classList.remove('is-planning-grid');
        
        const fGrupo   = document.getElementById('filter-grupo').value;
        const fDocente = document.getElementById('filter-docente').value;
        grid.innerHTML = '';
        grid.appendChild(mkEl('div','grid-header','HORA'));
        DAYS.forEach(d => grid.appendChild(mkEl('div','grid-header',d)));

        const sidebar = document.getElementById('unassigned-sidebar');
        const unList = document.getElementById('unassigned-list');
        const clasesHuerfanas = state.clases.filter(c => !c.docente_id);
        if (clasesHuerfanas.length > 0) {
            if (sidebar) sidebar.classList.remove('hidden');
            if (unList) {
                unList.innerHTML = clasesHuerfanas.map(c => `
                    <div class="bg-white border text-left border-rose-200 p-2.5 rounded-xl shadow-[0_2px_8px_rgba(225,29,72,0.1)] cursor-grab hover:shadow-md transition-all hover:border-rose-400 active:cursor-grabbing hover:-translate-y-0.5" 
                         draggable="true" 
                         ondragstart="dragClase=state.clases.find(x=>x.id==='${c.id}'); this.classList.add('opacity-50')" 
                         ondragend="dragClase=null; this.classList.remove('opacity-50')">
                        <div class="text-[10px] font-black text-slate-800 truncate leading-tight">${esc(c.materia_nombre||'Sin materia')}</div>
                        <div class="text-[9px] font-bold text-slate-500 truncate mt-1 flex justify-between items-end">
                            <span>${esc(c.grupo_nombre||'Sin grupo')} · C${c.cuatrimestre||'?'}</span>
                            <div class="flex items-center gap-0.5 bg-rose-50 px-1 py-0.5 rounded text-rose-600">
                                <span class="material-symbols-outlined text-[10px]">schedule</span>
                                <span class="font-black text-[9px]">${c.duracion}</span>
                            </div>
                        </div>
                    </div>`).join('');
            }
        } else {
            if (sidebar) sidebar.classList.add('hidden');
        }

        const fTurno = document.getElementById('filter-turno-view').value;
        let visibleHours = HOURS;
        if (fTurno === 'matutino')   visibleHours = HOURS.filter(h => h >= 7 && h < 16);
        if (fTurno === 'vespertino') visibleHours = HOURS.filter(h => h >= 11 && h < 21);

        let clases = state.clases;
        if (fGrupo)   clases = clases.filter(c => c.grupo_id == fGrupo);
        if (fDocente) clases = clases.filter(c => c.docente_id == fDocente);

        const debugEl = document.getElementById('debug-info');
        if (debugEl) debugEl.textContent = `Total: ${state.clases.length} clases | Mostrando: ${clases.length} (Filtros: ${fGrupo||'Off'} / ${fDocente||'Off'})`;

        HOURS.forEach(h => {
            if (!visibleHours.includes(h)) return;
            const tc = mkEl('div','grid-time',`${h}:00`);
            grid.appendChild(tc);
            DAYS.forEach(d => {
                const cell = document.createElement('div');
                cell.className = 'grid-cell';
                cell.dataset.day  = d;
                cell.dataset.hour = h;

                const diaNorm = norm(d);
                const slotClases = clases.filter(c => {
                    const cDia = (typeof c.dia === 'number' || !isNaN(c.dia)) ? (['','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'][parseInt(c.dia)] || c.dia) : c.dia;
                    return norm(cDia) === diaNorm && (c.hora_inicio == h);
                });
                const n = slotClases.length;

                slotClases.forEach((c, idx) => {
                    const blk = document.createElement('div');
                    blk.className  = 'clase-block';
                    blk.style.background = c.docente_color || '#3b82f6';
                    blk.style.height = `calc(${c.duracion*56}px - 4px)`;
                    blk.style.top = '2px';
                    
                    if (n > 1) {
                        const w = (100 / n);
                        const gap = 1;
                        blk.style.width = `calc(${w}% - ${gap*2}px)`;
                        blk.style.left  = `calc(${w * idx}% + ${gap}px)`;
                        blk.style.zIndex = idx + 1;
                        blk.style.padding = n > 2 ? '2px 4px' : '4px 6px';
                        if (n > 2) blk.style.fontSize = '8px';
                    } else {
                        blk.style.width = 'calc(100% - 4px)';
                        blk.style.left  = '2px';
                    }

                    blk.innerHTML = `<div class="font-black truncate ${n>2?'':'mb-0.5'}">${c.materia_nombre||'Sin materia'}</div>
                        <div class="opacity-90 truncate leading-tight overflow-hidden">${c.grupo_nombre||''} · ${c.aula_nombre||''}</div>
                        <div class="opacity-80 truncate leading-tight overflow-hidden">${c.docente_nombre||''}</div>`;
                    blk.draggable = true;
                    blk.addEventListener('dragstart', ()=>{dragClase=c; blk.classList.add('dragging');});
                    blk.addEventListener('dragend',   ()=>{dragClase=null; blk.classList.remove('dragging');});
                    blk.addEventListener('click', e=>{e.stopPropagation(); this.openModal('clase', c.id);});
                    cell.appendChild(blk);
                });

                cell.addEventListener('dragover', e=>{
                    e.preventDefault();
                    if (!dragClase) return;
                    const conflict = this.checkConflict(dragClase.id, d, h, dragClase.duracion, dragClase.docente_id, dragClase.grupo_id);
                    cell.className = 'grid-cell ' + (conflict ? 'drag-over-err':'drag-over-ok');
                });
                cell.addEventListener('dragleave', ()=>cell.className='grid-cell');
                cell.addEventListener('drop', async e=>{
                    e.preventDefault(); cell.className='grid-cell';
                    if (!dragClase) return;
                    
                    const isDuplicate = e.altKey;
                    const excludeId = isDuplicate ? null : dragClase.id;
                    
                    let targetDocente = dragClase.docente_id;
                    const filtDocente = document.getElementById('filter-docente').value;
                    if (!targetDocente && filtDocente) targetDocente = filtDocente;
                    
                    const c = this.checkConflict(excludeId, d, h, dragClase.duracion, targetDocente, dragClase.grupo_id, dragClase.aula_id);
                    if (c) { this.toast('⚠️ '+c,'error'); return; }
                    try {
                        if (isDuplicate) {
                            const created = await api('clases','POST',{...dragClase, id:null, dia:d, hora_inicio:h, docente_id: targetDocente});
                            state.clases.push(created);
                            this.toast('✅ Clase duplicada');
                        } else {
                            const updated = await api('clases','PUT',{...dragClase, dia:d, hora_inicio:h, docente_id: targetDocente}, dragClase.id);
                            if (!updated) throw new Error("No se pudo obtener la clase actualizada");
                            const idx = state.clases.findIndex(x => x && x.id === dragClase.id);
                            if (idx>=0) state.clases[idx]=updated;
                            this.toast('✅ Clase movida');
                        }
                        this.renderGrid();
                    } catch(err){this.toast('❌ '+err.message,'error');}
                });
                cell.addEventListener('click', ()=>{
                    if (!cell.querySelector('.clase-block')) this.openModal('clase', null, {dia:d, hora_inicio:h});
                });
                grid.appendChild(cell);
            });
        });
    },

    checkConflict(excludeId, dia, horaInicio, duracion, docenteId, grupoId, aulaId) {
        const fin = horaInicio + duracion;
        const dNorm = norm(dia);
        const other = (state.clases || []).filter(c => c && c.id !== excludeId && norm(c.dia) === dNorm);
        
        if (grupoId  && other.some(c=>c.grupo_id===grupoId  && c.hora_inicio<fin && (c.hora_inicio+c.duracion)>horaInicio)) return 'El grupo ya tiene clase en ese horario';
        if (docenteId && other.some(c=>c.docente_id===docenteId && c.hora_inicio<fin && (c.hora_inicio+c.duracion)>horaInicio)) return 'El docente ya tiene clase en ese horario';
        if (aulaId && other.some(c=>c.aula_id===aulaId && c.hora_inicio<fin && (c.hora_inicio+c.duracion)>horaInicio)) return 'El aula ya está ocupada en ese horario';
        
        return null;
    },

    // ── RENDERS CRUD ─────────────────────────────────────────────────────────
    renderDocentes() {
        document.getElementById('docentes-list').innerHTML = state.docentes.map(d=>`
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-full shrink-0" style="background:${d.color_hex}"></div>
                <div class="flex-1 min-w-0">
                    <p class="font-black text-slate-800 text-sm truncate">${esc(d.nombre)}</p>
                    <p class="text-[10px] font-bold ${d.email ? 'text-slate-400' : 'text-amber-500'} uppercase">
                        ${d.email ? esc(d.email) : '⚠️ Hace falta el correo para saber si está autorizado'}
                    </p>
                    <p class="text-[9px] font-bold text-slate-300 uppercase">${d.horas_asesoria}h asesoría</p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button onclick="horarios.openModal('docente','${d.id}')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-blue-100 text-slate-500 hover:text-blue-600 flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                    </button>
                </div>
            </div>`).join('') || '<p class="text-slate-400 text-sm text-center py-8">No hay docentes autorizados para esta carrera. Agrégalos en el menú de "Docentes Autorizados".</p>';
    },

    renderMaterias() {
        const filterPlan = document.getElementById('filter-materia-plan')?.value;
        const onlyActive = document.getElementById('filter-materia-activos')?.checked;
        
        // Determinar planes activos por cuatrimestre basado en los grupos
        const activeByCuatri = {};
        if (onlyActive) {
            state.grupos.forEach(g => {
                const c = g.cuatrimestre || 0;
                if (!activeByCuatri[c]) activeByCuatri[c] = new Set();
                activeByCuatri[c].add(g.plan || 'Plan Regular');
            });
        }

        let matsToRender = state.materias;
        if (filterPlan) matsToRender = matsToRender.filter(m=>m.plan === filterPlan);
        
        if (onlyActive) {
            matsToRender = matsToRender.filter(m => {
                const c = m.cuatrimestre || 0;
                const plans = activeByCuatri[c];
                return plans ? plans.has(m.plan || 'Plan Regular') : false;
            });
        }

        // Group by cuatrimestre
        const byCuatri = {};
        matsToRender.forEach(m=>{
            const k = m.cuatrimestre ?? 0;
            if (!byCuatri[k]) byCuatri[k]=[];
            byCuatri[k].push(m);
        });
        const keys = Object.keys(byCuatri).map(Number).sort((a,b)=>a-b);
        const cuatriLabel = c => c===0 ? 'Todas / Sin cuatrimestre' : `Cuatrimestre ${c}`;

        document.getElementById('materias-tbody').innerHTML = keys.map(k=>{
            const mats = byCuatri[k];
            return `<tr class="bg-slate-50"><td colspan="6" class="px-6 py-2">
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-wide">${cuatriLabel(k)} <span class="ml-1 text-slate-300">(${mats.length})</span></span>
            </td></tr>` + mats.map(m=>`
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4"><div class="flex items-center gap-2">
                    ${m.docente_color?`<span class="w-2 h-2 rounded-full" style="background:${m.docente_color}"></span>`:''}
                    <span class="font-bold text-slate-800">${esc(m.nombre)}</span>
                </div></td>
                <td class="px-6 py-4 text-slate-500 text-xs font-bold">${esc(m.plan || 'Plan Regular')}</td>
                <td class="px-6 py-4 text-slate-500 text-sm">${m.docente_nombre ? esc(m.docente_nombre) : '<em>Sin asignar</em>'}</td>
                <td class="px-6 py-4 text-center font-bold text-slate-700">${m.horas_semanales}h</td>
                <td class="px-6 py-4 text-center">
                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full ${m.es_externa?'bg-amber-50 text-amber-600':'bg-blue-50 text-blue-600'}">${m.es_externa?'Externa':'Regular'}</span>
                </td>
                <td class="px-6 py-4 text-right"><div class="flex gap-2 justify-end">
                    <button onclick="horarios.openModal('materia','${m.id}')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-blue-100 text-slate-500 hover:text-blue-600 flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                    </button>
                    <button onclick="horarios.deleteItem('materias','${m.id}','${esc(m.nombre)}')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                    </button>
                </div></td>
            </tr>`).join('');
        }).join('') || '<tr><td colspan="6" class="px-6 py-8 text-center text-slate-400 text-sm">No hay materias. Añade una o usa "Importar en bloque".</td></tr>';
    },

    renderGrupos() {
        // Group by cuatrimestre
        const byCuatri = {};
        state.grupos.forEach(g=>{
            const k = g.cuatrimestre ?? 1;
            if (!byCuatri[k]) byCuatri[k]=[];
            byCuatri[k].push(g);
        });
        const keys = Object.keys(byCuatri).map(Number).sort((a,b)=>a-b);

        document.getElementById('grupos-list').innerHTML = keys.map(k=>{
            const grps = byCuatri[k];
            return `<div class="col-span-full mb-1"><span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Cuatrimestre ${k}</span></div>` +
                grps.map(g=>`
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <p class="font-black text-slate-800">${esc(g.nombre)}</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">${g.turno} · Cuatri ${g.cuatrimestre}${g.capacidad_maxima ? ` · ${g.capacidad_maxima} max` : ''}</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="horarios.openModal('grupo','${g.id}')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-blue-100 text-slate-500 hover:text-blue-600 flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-[14px]">edit</span>
                            </button>
                            <button onclick="horarios.deleteItem('grupos','${g.id}','${esc(g.nombre)}')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-[14px]">delete</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-50 rounded-xl p-3">
                        <span class="material-symbols-outlined text-slate-400 text-[16px]">group</span>
                        <span class="text-sm font-extrabold text-slate-700">${Array.isArray(g.alumnos) ? g.alumnos.length : (g.total_alumnos || 0)}</span>
                        <span class="text-xs text-slate-400 font-bold">alumnos</span>
                        <button onclick="horarios.switchTab('alumnos'); setTimeout(()=>{ document.getElementById('alumnos-grupo-select').value='${g.id}'; horarios.loadAlumnosGrupo(); },100)"
                            class="ml-auto text-[10px] font-bold text-blue-600 hover:text-blue-800">Cargar alumnos →</button>
                    </div>
                </div>`).join('');
        }).join('') || '<p class="text-slate-400 text-sm text-center py-8">No hay grupos. Usa "Importar desde Asistencia".</p>';
    },

    renderAulas() {
        const icons = {aula:'meeting_room', laboratorio:'science', oficina:'work'};
        document.getElementById('aulas-list').innerHTML = state.aulas.map(a=>`
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
                    <span class="material-symbols-outlined">${icons[a.tipo]||'meeting_room'}</span>
                </div>
                <div class="flex-1">
                    <p class="font-black text-slate-800">${esc(a.nombre)}</p>
                    <p class="text-[10px] font-bold text-slate-400 uppercase">${a.tipo} · Cap. ${a.capacidad}${a.edificio?` · ${esc(a.edificio)}`:''}</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="horarios.openModal('aula','${a.id}')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-blue-100 text-slate-500 hover:text-blue-600 flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                    </button>
                    <button onclick="horarios.deleteItem('aulas','${a.id}','${esc(a.nombre)}')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 flex items-center justify-center transition-colors">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                    </button>
                </div>
            </div>`).join('') || '<p class="text-slate-400 text-sm text-center py-8">No hay aulas registradas.</p>';
    },

    // ── ALUMNOS ──────────────────────────────────────────────────────────────
    loadAlumnosGrupo() {
        const gid  = document.getElementById('alumnos-grupo-select').value;
        const grupo = state.grupos.find(g=>g.id===gid);
        const preview = document.getElementById('alumnos-preview');
        const count   = document.getElementById('alumnos-count');
        if (!grupo) { preview.innerHTML='<p class="text-slate-400 text-xs text-center py-8">Selecciona un grupo</p>'; count.textContent='0 alumnos'; return; }
        const alumnos = Array.isArray(grupo.alumnos) ? grupo.alumnos : [];
        count.textContent = `${alumnos.length} alumnos`;
        preview.innerHTML = alumnos.length ?
            alumnos.map(a=>`<div class="flex items-center gap-3 py-2 border-b border-slate-50">
                <span class="text-[10px] font-mono text-slate-400">${esc(a.matricula||'')}</span>
                <span class="text-sm font-bold text-slate-700 flex-1">${esc(a.name||a.nombre||'')}</span>
            </div>`).join('') :
            '<p class="text-slate-400 text-xs text-center py-8">Este grupo aún no tiene alumnos</p>';
        // Pre-fill textarea
        const ta = document.getElementById('alumnos-textarea');
        ta.value = alumnos.map(a=>`${a.matricula||''} - ${a.name||a.nombre||''}`).join('\n');
    },

    async saveAlumnos() {
        const gid = document.getElementById('alumnos-grupo-select').value;
        if (!gid) { this.toast('Selecciona un grupo primero','error'); return; }
        const lines = document.getElementById('alumnos-textarea').value.split('\n');
        const alumnos = lines.map(l=>{
            const parts = l.split(/\s*[-–]\s*/);
            const mat   = parts[0]?.trim() || '';
            const nom   = parts.slice(1).join(' ').trim() || mat;
            return mat ? { id:mat, matricula:mat, name:nom } : null;
        }).filter(Boolean);

        try {
            const updated = await api('upload_alumnos','POST',{alumnos}, gid);
            const idx = state.grupos.findIndex(g=>g.id===gid);
            if (idx>=0) state.grupos[idx] = {...state.grupos[idx], alumnos: updated.alumnos || alumnos, total_alumnos: alumnos.length};
            this.loadAlumnosGrupo();
            this.populateFilters();
            this.renderGrupos();
            this.toast(`✅ ${alumnos.length} alumnos guardados`);
        } catch(err){ this.toast('❌ '+err.message,'error'); }
    },

    // ── SYNC ─────────────────────────────────────────────────────────────────
    async syncDocentes() {
        try {
            this.toast('⏳ Importando docentes...');
            const profesores = await api('sync_docentes');
            let creados = 0, actualizados = 0;
            for (const p of profesores) {
                const existente = state.docentes.find(d=>d.nombre.toLowerCase()===p.nombre.toLowerCase());
                if (!existente) {
                    const nuevo = await api('docentes','POST',{ nombre:p.nombre, email:p.email||'', horas_asesoria:0, color_hex: randomColor() });
                    state.docentes.push(nuevo);
                    creados++;
                } else if (!existente.email && p.email) {
                    // Si el docente ya existe pero no tiene correo, se lo asignamos desde la lista global
                    const updated = await api('docentes','PUT', { ...existente, email:p.email }, existente.id);
                    const idx = state.docentes.findIndex(d=>d.id===existente.id);
                    if (idx>=0) state.docentes[idx] = updated;
                    actualizados++;
                }
            }
            this.renderDocentes();
            this.populateFilters();
            this.toast(`✅ Sincronización completa: ${creados} nuevos, ${actualizados} correos vinculados`);
        } catch(err){ this.toast('❌ '+err.message,'error'); }
    },

    async syncGrupos() {
        try {
            this.toast('⏳ Importando grupos...');
            const grupos = await api('sync_grupos');
            let creados = 0;
            for (const g of grupos) {
                const existente = state.grupos.find(x=>x.nombre.toLowerCase()===g.nombre.toLowerCase());
                if (!existente) {
                    const nuevo = await api('grupos','POST',{ nombre:g.nombre, cuatrimestre:g.cuatrimestre||1, turno:'matutino', alumnos:[] });
                    state.grupos.push(nuevo);
                    creados++;
                }
            }
            this.renderGrupos();
            this.populateFilters();
            this.toast(`✅ ${creados} grupos nuevos importados (${grupos.length} en sistema)`);
        } catch(err){ this.toast('❌ '+err.message,'error'); }
    },

    // ── MODALES ──────────────────────────────────────────────────────────────
    // Ahora openModal recibe (type, id?) para lookup seguro por ID (sin JSON en onclick)
    openModal(type, id = null, defaults = {}) {
        const resourceMap = { docente:'docentes', materia:'materias', grupo:'grupos', aula:'aulas', clase:'clases' };
        const data = id ? (state[resourceMap[type]]?.find(x=>x.id===id) || {}) : defaults;
        modalContext = { type, id, data };

        const modal  = document.getElementById('hor-modal');
        const title  = document.getElementById('modal-title');
        const body   = document.getElementById('modal-body');
        const isEdit = !!id;

        const forms = {
            docente: ()=>{
                const [user, domain] = (data.email || '').split('@');
                body.innerHTML = `
                    ${f('nombre','Nombre completo',data.nombre)}
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Correo Institucional</label>
                        <div class="flex items-center gap-2">
                            <input type="text" id="f-email-user" value="${user || ''}" placeholder="usuario" 
                                   class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <span class="bg-slate-50 px-3 py-2.5 rounded-xl border border-slate-100 text-xs font-black text-slate-400">@upsrj.edu.mx</span>
                        </div>
                        <p class="mt-1.5 text-[10px] font-bold text-amber-500 bg-amber-50 p-2 rounded-lg border border-amber-100 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">info</span>
                            Solo ingresa el nombre de usuario (sin el @upsrj.edu.mx).
                        </p>
                    </div>
                    ${f('horas_asesoria','Horas de asesoría',data.horas_asesoria??0,'0','number')}
                    <div class="grid grid-cols-2 gap-4">
                        ${f('carga_max_diaria','Carga Máx Diaria (h)',data.carga_max_diaria??8,'8','number')}
                        ${f('carga_max_semanal','Carga Máx Semanal (h)',data.carga_max_semanal??40,'40','number')}
                    </div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Color Identificador</label>
                    <input type="color" id="f-color_hex" value="${data.color_hex||'#3b82f6'}" class="h-10 w-full rounded-xl border border-slate-200 cursor-pointer"></div>
                    <div class="mt-4">
                        <label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Disponibilidad de Horario</label>
                        <p class="text-[10px] text-slate-400 mb-2">Selecciona los bloques en los que el docente PUEDE dar clase.</p>
                        <div id="availability-grid" class="border border-slate-100 rounded-xl overflow-hidden shadow-sm"></div>
                    </div>
                    ${isEdit ? `<hr class="border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-black text-slate-500 uppercase tracking-wide">Materias Preferidas</p>
                        <div id="docente-horas-sum" class="px-2 py-0.5 rounded bg-blue-50 text-blue-600 text-[10px] font-black">Total: 0h/sem</div>
                    </div>
                    <div class="relative mb-2">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm">search</span>
                        <input type="text" id="docente-mat-search" placeholder="Buscar materia..." oninput="horarios.filterDocenteMaterias()"
                               class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div id="docente-materias-checkboxes" class="grid grid-cols-1 gap-0.5 max-h-48 overflow-y-auto border border-slate-50 rounded-xl p-1"><em class="text-xs text-slate-400">Cargando...</em></div>
                    
                    <hr class="border-slate-100 my-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-black text-slate-500 uppercase tracking-wide">Preferencias de Aula/Laboratorio</p>
                    </div>
                    <div class="relative mb-2">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-sm">search</span>
                        <input type="text" id="docente-aula-search" placeholder="Buscar aula..." oninput="horarios.filterDocenteAulas()"
                               class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div id="docente-aulas-checkboxes" class="grid grid-cols-1 gap-0.5 max-h-40 overflow-y-auto border border-slate-50 rounded-xl p-1"><em class="text-xs text-slate-400">Cargando...</em></div>
                    
                    <p class="mt-2 text-[10px] italic text-slate-400 text-center">Las preferencias se guardarán junto con la información del docente.</p>` : ''}`;
                if (isEdit) {
                    horarios.loadDocenteMaterias(id);
                    horarios.loadDocenteAulas(id, data.aulas_preferidas || []);
                }
                setTimeout(() => horarios._renderDocenteAvailability(data.disponibilidad || []), 0);
            },
            bulk_alumnos: ()=>{
                title.textContent = 'Importación Masiva de Alumnos';
                body.innerHTML = `
                    <p class="text-[11px] text-slate-500 mb-3 font-bold bg-blue-50 p-3 rounded-xl">
                        💡 Pega el texto copiado de Excel o PDF. El sistema detectará automáticamente el grupo, cuatrimestre y la lista de alumnos.
                    </p>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        ${f('bulk_grupo','Grupo Detectado','','Nombre del grupo')}
                        ${f('bulk_cuatri','Cuatrimestre',1,'','number')}
                    </div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Pega aquí el contenido</label>
                    <textarea id="f-bulk_alumnos_texto" rows="10" oninput="horarios.previewAlumnosBulk()" placeholder="Pega aquí..." class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs text-slate-700 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea></div>
                    <div id="bulk-alumnos-preview" class="bg-slate-50 rounded-xl p-3 max-h-48 overflow-y-auto hidden"></div>`;
            },
            materia: ()=>{
                title.textContent = isEdit ? 'Editar Materia' : 'Nueva Materia';
                const w = weeks(state.config.fecha_inicio, state.config.fecha_fin);
                const weeksTip = w ? `<span class="text-blue-500 font-bold">(${w} sem)</span>` : '<span class="text-amber-500 font-bold">(Configura las fechas para auto-calcular)</span>';
                
                body.innerHTML = `
                    ${f('nombre','Nombre de la materia',data.nombre)}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Horas Totales</label>
                            <input type="number" id="f-horas_totales" value="${data.horas_totales||''}" placeholder="Ej: 64" oninput="horarios.calcHours('tot')"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Horas Semanales ${weeksTip}</label>
                            <input type="number" id="f-horas_semanales" value="${data.horas_semanales??2}" placeholder="Ej: 4" oninput="horarios.calcHours('sem')"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Cuatrimestre</label>
                        <select id="f-cuatrimestre" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                            <option value="0" ${(!data.cuatrimestre||data.cuatrimestre==0)?'selected':''}>Todas / Sin cuatrimestre</option>
                            ${Array.from({length: state.config.max_cuatrimestres || 10}, (_, i) => i + 1).map(n=>`<option value="${n}" ${data.cuatrimestre==n?'selected':''}>${n}°</option>`).join('')}
                        </select></div>
                        <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Plan de Estudios</label>
                        <div class="relative">
                            <select id="f-plan" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 appearance-none">
                                <option value="Plan Regular" ${data.plan==='Plan Regular'?'selected':''}>Plan Regular</option>
                                ${[...new Set([...state.planes, data.plan])].filter(p => p && p !== 'Plan Regular').map(p=>`<option value="${esc(p)}" ${data.plan===p?'selected':''}>${esc(p)}</option>`).join('')}
                                <option value="__NUEVO__">+ Nuevo Plan...</option>
                            </select>
                            <input type="text" id="f-plan-nuevo" placeholder="Nombre del nuevo plan" class="hidden w-full mt-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div></div>
                    </div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Docente Asignado</label>
                    <select id="f-docente_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        <option value="">Sin asignar</option>
                        ${state.docentes.map(d=>`<option value="${d.id}" ${data.docente_id===d.id?'selected':''}>${esc(d.nombre)}</option>`).join('')}
                    </select></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Prioridad</label>
                    <select id="f-prioridad" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        <option value="Baja" ${data.prioridad==='Baja'?'selected':''}>Baja</option>
                        <option value="Media" ${(data.prioridad==='Media'||!data.prioridad)?'selected':''}>Media</option>
                        <option value="Alta" ${data.prioridad==='Alta'?'selected':''}>Alta</option>
                    </select></div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="f-es_especialidad" ${data.es_especialidad?'checked':''} class="w-4 h-4 rounded">
                        <span class="text-sm font-bold text-slate-700">Es materia de especialidad</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="f-es_externa" ${data.es_externa?'checked':''} class="w-4 h-4 rounded">
                        <span class="text-sm font-bold text-slate-700">Es materia externa</span>
                    </label>`;
                
                // Switch plan select to text input if "New Plan" chosen
                setTimeout(() => {
                    const sel = document.getElementById('f-plan');
                    const inp = document.getElementById('f-plan-nuevo');
                    sel.onchange = () => {
                        if (sel.value === '__NUEVO__') { inp.classList.remove('hidden'); inp.focus(); }
                        else { inp.classList.add('hidden'); }
                    };
                }, 0);
            },
            bulk_materia: ()=>{
                title.textContent = 'Importar Materias en Bloque';
                const fi = state.config.fecha_inicio || '';
                const ff = state.config.fecha_fin    || '';
                const semanas = weeks(fi, ff);
                const preview = semanas ? `<span class="text-green-600">✓ ${semanas} semanas detectadas — las horas totales se convertirán a h/sem automáticamente</span>` : `<span class="text-amber-600">⚠️ Configura las fechas del cuatrimestre para que se calcule h/sem automáticamente</span>`;
                body.innerHTML = `
                    <div class="bg-slate-50 rounded-xl p-3 text-xs font-bold">${preview}</div>
                    <p class="text-xs text-slate-400 font-bold">Formato: <code class="bg-slate-100 px-1 py-0.5 rounded">Nombre, Horas Totales, Cuatrimestre, Plan</code><br>Una materia por línea. Plan es opcional.</p>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Lista de Materias</label>
                    <textarea id="f-bulk_texto" rows="12" oninput="horarios.previewBulk()" placeholder="Álgebra Lineal, 48, 1&#10;Cálculo Diferencial, 64, 1&#10;Programación Básica, 32&#10;Historia" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700 font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea></div>
                    <div id="bulk-preview" class="bg-slate-50 rounded-xl p-3 max-h-48 overflow-y-auto hidden"></div>`;
            },
            grupo: ()=>{
                title.textContent = isEdit ? 'Editar Grupo' : 'Nuevo Grupo';
                body.innerHTML = `
                    ${f('nombre','Nombre del grupo',data.nombre,'Ej: IAEV-41')}
                    ${f('cuatrimestre','Cuatrimestre',data.cuatrimestre??1,'1','number')}
                    ${f('capacidad_maxima','Capacidad Ma\u0301xima',data.capacidad_maxima??30,'30','number')}
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Plan de Estudios</label>
                    <div class="relative">
                        <select id="f-plan" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 appearance-none">
                            <option value="Plan Regular" ${(data.plan==='Plan Regular'||!data.plan)?'selected':''}>Plan Regular</option>
                            ${[...new Set([...state.planes, data.plan])].filter(p=>p && p!=='Plan Regular').map(p=>`<option value="${esc(p)}" ${data.plan===p?'selected':''}>${esc(p)}</option>`).join('')}
                            <option value="__NUEVO__">+ Nuevo Plan...</option>
                        </select>
                        <input type="text" id="f-plan-nuevo" placeholder="Nombre del nuevo plan" class="hidden w-full mt-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Turno</label>
                    <select id="f-turno" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        <option value="matutino"  ${(data.turno==='matutino'||!data.turno)?'selected':''}>Matutino</option>
                        <option value="vespertino" ${data.turno==='vespertino'?'selected':''}>Vespertino</option>
                    </select></div>`;
                
                setTimeout(() => {
                    const sel = document.getElementById('f-plan');
                    const inp = document.getElementById('f-plan-nuevo');
                    if (sel && inp) {
                        sel.onchange = () => {
                            if (sel.value === '__NUEVO__') { inp.classList.remove('hidden'); inp.focus(); }
                            else { inp.classList.add('hidden'); }
                        };
                    }
                }, 0);
            },
            aula: ()=>{
                title.textContent = isEdit ? 'Editar Aula' : 'Nueva Aula';
                body.innerHTML = `
                    ${f('nombre','Nombre del espacio',data.nombre,'Ej: Aula 3')}
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Tipo</label>
                    <select id="f-tipo" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        <option value="aula"        ${(data.tipo==='aula'||!data.tipo)?'selected':''}>Aula</option>
                        <option value="laboratorio"  ${data.tipo==='laboratorio'?'selected':''}>Laboratorio</option>
                        <option value="oficina"      ${data.tipo==='oficina'?'selected':''}>Oficina</option>
                    </select></div>
                    ${f('capacidad','Capacidad',data.capacidad??30,'30','number')}
                    ${f('edificio','Edificio',data.edificio??'','Ej: Edificio A')}` ;
            },
            clase: ()=>{
                title.textContent = isEdit ? 'Editar Clase' : 'Nueva Clase';
                body.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">D\u00eda</label>
                        <select id="f-dia" onchange="horarios._claseWarnings()" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                            ${DAYS.map(d=>`<option value="${d}" ${data.dia===d?'selected':''}>${d}</option>`).join('')}
                        </select></div>
                        ${f('hora_inicio','Hora inicio',data.hora_inicio??7,'7','number')}
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        ${f('duracion','Duraci\u00f3n (horas)',data.duracion??2,'2','number')}
                        <div></div>
                    </div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Docente</label>
                    <select id="f-docente_id" onchange="horarios._updateFilters('docente')" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        <option value="">Sin asignar</option>
                        ${state.docentes.map(d=>`<option value="${d.id}" ${data.docente_id===d.id?'selected':''}>${esc(d.nombre)}</option>`).join('')}
                    </select></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Grupo</label>
                    <select id="f-grupo_id" onchange="horarios._updateFilters('grupo')" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        <option value="">Sin grupo</option>
                        ${state.grupos.map(g=>`<option value="${g.id}" ${data.grupo_id===g.id?'selected':''}>${esc(g.nombre)} (C${g.cuatrimestre})</option>`).join('')}
                    </select></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Materia</label>
                    <select id="f-materia_id" onchange="horarios._updateFilters('materia')" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        ${this._matOpts(data.docente_id, data.materia_id)}
                    </select></div>
                    <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Aula</label>
                    <select id="f-aula_id" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                        <option value="">Sin aula</option>
                        ${state.aulas.map(a=>`<option value="${a.id}" ${data.aula_id===a.id?'selected':''}>${esc(a.nombre)}${a.edificio?' · '+esc(a.edificio):''}</option>`).join('')}
                    </select></div>
                    <div id="clase-warnings" class="hidden"></div>
                    ${isEdit ? `<button onclick="horarios.deleteClaseModal()" class="w-full py-2.5 rounded-xl bg-rose-50 text-rose-600 font-bold text-sm hover:bg-rose-100 transition-colors">Eliminar esta clase</button>` : ''}` ;
                setTimeout(() => horarios._updateFilters(null), 0);
            },
            config: ()=>{
                title.textContent = 'Configuración del Cuatrimestre';
                body.innerHTML = `
                    <div class="grid grid-cols-2 gap-4">
                        ${f('anio_activo','Año activo',state.config.anio_activo??new Date().getFullYear(),'','number')}
                        <div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">Periodo del Año</label>
                        <select id="f-cuatrimestre_activo" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                            ${[1,2,3].map(n=>`<option value="${n}" ${state.config.cuatrimestre_activo==n?'selected':''}>${getPeriodLabel(n)}</option>`).join('')}
                        </select></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        ${f('turno_corte','Corte Turno (Matutino)',state.config.turno_corte??4,'4','number')}
                        ${f('max_cuatrimestres','Máx. Cuatrimestres',state.config.max_cuatrimestres??10,'10','number')}
                    </div>
                    <hr class="border-slate-100">
                    <p class="text-xs font-black text-slate-500 uppercase tracking-wide">Período de clases</p>
                    <p class="text-[11px] text-slate-400">Estas fechas sirven para calcular automáticamente horas semanales al importar materias con horas totales.</p>
                    <div class="grid grid-cols-2 gap-4">
                        ${f('fecha_inicio','Inicio de clases',state.config.fecha_inicio??'','','date')}
                        ${f('fecha_fin','Fin de clases',state.config.fecha_fin??'','','date')}
                    </div>
                    <div id="config-weeks" class="text-xs font-bold text-slate-400 text-center"></div>`;
                // Show weeks after render
                setTimeout(()=>{
                    const fi = document.getElementById('f-fecha_inicio');
                    const ff = document.getElementById('f-fecha_fin');
                    const show = ()=>{ const w=weeks(fi.value,ff.value); document.getElementById('config-weeks').textContent = w ? `= ${w} semanas de clases` : ''; };
                    fi.addEventListener('change',show); ff.addEventListener('change',show); show();
                }, 0);
            },
            generate_config: () => {
                title.textContent = 'Configuración de Generación Automática';
                const docentesHtml = state.docentes.map(d => `
                    <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" value="${d.id}" class="config-docente-check rounded w-3.5 h-3.5" checked>
                        <span class="text-xs font-bold text-slate-700">${esc(d.nombre)}</span>
                    </label>`).join('');
                
                const gruposHtml = state.grupos.map(g => `
                    <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" value="${g.id}" class="config-grupo-check rounded w-3.5 h-3.5" checked>
                        <span class="text-xs font-bold text-slate-700">${esc(g.nombre)} (C${g.cuatrimestre})</span>
                    </label>`).join('');

                const planningBanner = state.isPlanning ? `
                    <div class="bg-amber-100 border-2 border-amber-400 p-4 rounded-xl mb-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-amber-600 text-3xl">auto_fix_high</span>
                        <div>
                            <div class="text-[10px] font-black text-amber-600 uppercase tracking-widest leading-none">Generación para Planeación</div>
                            <div class="text-sm font-black text-amber-900">Periodo: ${state.planningPeriod}</div>
                            <div class="text-[10px] text-amber-700 font-bold mt-1">Solo se reemplazará el horario de los grupos/docentes seleccionados.</div>
                        </div>
                    </div>` : `
                    <div class="bg-amber-50 border border-amber-100 p-4 rounded-xl mb-4">
                        <p class="text-[11px] text-amber-700 font-bold flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">warning</span>
                            Esta acción SOLO reemplazará el horario de los grupos/docentes seleccionados. El resto se mantendrá intacto.
                        </p>
                    </div>`;

                body.innerHTML = `
                    ${planningBanner}
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Docentes</label>
                                <div class="flex gap-2">
                                    <button onclick="document.querySelectorAll('.config-docente-check').forEach(i=>i.checked=true)" class="text-[9px] font-bold text-blue-500 hover:underline">Todos</button>
                                    <button onclick="document.querySelectorAll('.config-docente-check').forEach(i=>i.checked=false)" class="text-[9px] font-bold text-slate-400 hover:underline">Ninguno</button>
                                </div>
                            </div>
                            <div class="max-h-60 overflow-y-auto border border-slate-100 rounded-xl p-1 bg-slate-50/50">
                                ${docentesHtml || '<p class="text-slate-400 text-[10px] p-2">No hay docentes</p>'}
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Grupos</label>
                                <div class="flex gap-2">
                                    <button onclick="document.querySelectorAll('.config-grupo-check').forEach(i=>i.checked=true)" class="text-[9px] font-bold text-blue-500 hover:underline">Todos</button>
                                    <button onclick="document.querySelectorAll('.config-grupo-check').forEach(i=>i.checked=false)" class="text-[9px] font-bold text-slate-400 hover:underline">Ninguno</button>
                                </div>
                            </div>
                            <div class="max-h-60 overflow-y-auto border border-slate-100 rounded-xl p-1 bg-slate-50/50">
                                ${gruposHtml || '<p class="text-slate-400 text-[10px] p-2">No hay grupos</p>'}
                            </div>
                        </div>
                    </div>
                    <button onclick="horarios.finalizeGeneration()" id="btn-run-gen" class="w-full mt-6 bg-slate-900 text-white py-3 rounded-2xl font-black text-sm hover:bg-slate-800 transition-all flex items-center justify-center gap-2 shadow-lg shadow-slate-200">
                        <span class="material-symbols-outlined">auto_fix_high</span> Iniciar Generación Automática
                    </button>`;
                
                document.getElementById('modal-save-btn').classList.add('hidden');
            }
        };

        (forms[type]||(() => {}))();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },

    closeModal() {
        document.getElementById('hor-modal').classList.add('hidden');
        document.getElementById('hor-modal').classList.remove('flex');
        document.getElementById('modal-save-btn').classList.remove('hidden'); // Reset visibility
        modalContext = null;
    },

    openWizard() {
        const modal = document.getElementById('wizard-fullscreen-modal');
        const body  = document.getElementById('wizard-body-content');
        
        let nextCuatri = (state.config.cuatrimestre_activo || 1) + 1;
        let nextAnio   = state.config.anio_activo || new Date().getFullYear();
        if (nextCuatri > 3) { nextCuatri = 1; nextAnio++; }

        const max_cuatri = state.config.max_cuatrimestres || 10;
        
        let groupsAnalysis = '';
        let validGroupsCount = 0;
        
        state.grupos.forEach(g => {
            const target_cuatri = parseInt(g.cuatrimestre) + 1;
            if (target_cuatri > max_cuatri) {
                groupsAnalysis += `<div class="bg-slate-50 border border-slate-100 p-3 rounded-xl flex items-center justify-between opacity-60">
                    <div>
                        <div class="text-[11px] font-black text-slate-700">${esc(g.nombre)} <span class="text-slate-400 font-medium tracking-tight">➔ Termina (C${g.cuatrimestre})</span></div>
                        <div class="text-[9px] font-bold text-slate-400">Grupo excluido por egreso</div>
                    </div>
                </div>`;
            } else {
                const isEstadias = target_cuatri === max_cuatri; // Asumiendo que el último es Estadías (10mo normalmente)
                
                const gPlan = g.plan || 'Plan Regular';
                const materiasFuturas = state.materias.filter(m => {
                    const mPlan = m.plan || 'Plan Regular';
                    return parseInt(m.cuatrimestre) === target_cuatri && mPlan === gPlan;
                });
                
                const mat_count = materiasFuturas.filter(m => m.nombre.toLowerCase().indexOf('estadía') === -1 && m.nombre.toLowerCase().indexOf('estadia') === -1).length;
                
                if (mat_count === 0 && materiasFuturas.length > 0) {
                    // Significa que es bloqueado por ser puras estadías
                    groupsAnalysis += `<div class="bg-amber-50 border border-amber-100 p-3 rounded-xl flex items-center justify-between">
                        <div>
                            <div class="text-[11px] font-black text-amber-900">${esc(g.nombre)} <span class="text-amber-600 font-medium tracking-tight">➔ C${target_cuatri}</span></div>
                            <div class="text-[9px] font-bold text-amber-700">Excluido (Solo Estadías)</div>
                        </div>
                    </div>`;
                } else if (mat_count > 0) {
                    validGroupsCount++;
                    groupsAnalysis += `<div class="bg-white border border-slate-200 shadow-sm p-3 rounded-xl flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-black text-xs">C${target_cuatri}</div>
                            <div>
                                <div class="text-[11px] font-black text-slate-800">${esc(g.nombre)}</div>
                                <div class="text-[9px] font-bold text-slate-500">Promoción Automática</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[11px] font-black text-emerald-600">${mat_count} materias base</div>
                            <div class="text-[9px] font-bold text-slate-400">En plan de estudios</div>
                        </div>
                    </div>`;
                }
            }
        });

        const docentesHtml = state.docentes.map(d => `
            <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 cursor-pointer">
                <input type="checkbox" value="${d.id}" class="config-docente-check rounded w-3.5 h-3.5" checked>
                <span class="text-[11px] font-bold text-slate-700 truncate w-full">${esc(d.nombre)}</span>
            </label>`).join('');
        
        const gruposHtml = state.grupos.map(g => `
            <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 cursor-pointer">
                <input type="checkbox" value="${g.id}" class="config-grupo-check rounded w-3.5 h-3.5" checked>
                <span class="text-[11px] font-bold text-slate-700">${esc(g.nombre)} (C${g.cuatrimestre})</span>
            </label>`).join('');

        body.innerHTML = `
            <!-- Paso 1 -->
            <div id="wp-step-1" class="transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight">Paso 1: Análisis de Promoción Curricular</h3>
                    <div class="px-4 py-1.5 bg-blue-50 text-blue-700 font-bold text-[10px] uppercase tracking-widest rounded-full border border-blue-100">
                        Periodo a planear: ${getPeriodLabel(nextCuatri)} ${nextAnio}
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wide mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-slate-400">school</span>
                                Mapeo Automático de Grupos (${validGroupsCount} válidos)
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[40vh] overflow-y-auto pr-2">
                                ${groupsAnalysis || '<p class="text-xs text-slate-400">Sin grupos activos</p>'}
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="bg-blue-600 rounded-3xl p-6 text-white shadow-lg shadow-blue-200 mb-6 relative overflow-hidden">
                            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-8xl opacity-10">auto_awesome</span>
                            <span class="material-symbols-outlined text-4xl mb-4 text-blue-300">neurology</span>
                            <h4 class="text-lg font-black mb-2">Motor Inteligente CSP</h4>
                            <p class="text-xs text-blue-100 font-medium leading-relaxed mb-6">El motor promoverá virtualmente a estos grupos, saltará a los egresados y eximirá las materias de Estadías Profesionales automáticamente. Además, los turnos (Matutino/Vespertino) ya aplican sobre el nuevo cuatrimestre estimado.</p>
                            <button onclick="document.getElementById('wp-step-1').classList.add('hidden'); document.getElementById('wp-step-2').classList.remove('hidden');" 
                                class="w-full bg-white text-blue-600 hover:bg-blue-50 py-3 rounded-2xl font-black text-sm transition-all shadow-md flex items-center justify-center gap-2">
                                Continuar Configuración <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paso 2 -->
            <div id="wp-step-2" class="hidden transition-all duration-300">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                    <div>
                        <h3 class="text-2xl font-black text-slate-900 tracking-tight">Paso 2: Configurador de Restricciones</h3>
                        <p class="text-xs text-slate-500 font-bold mt-1">Selecciona qué entidades participarán en la auto-generación de horarios para este borrador.</p>
                    </div>
                    <button onclick="document.getElementById('wp-step-2').classList.add('hidden'); document.getElementById('wp-step-1').classList.remove('hidden');" class="text-xs font-bold text-slate-400 hover:text-slate-600 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">arrow_back</span> Volver al Paso 1</button>
                </div>
                
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Docentes Incluidos</label>
                                <div class="flex gap-3">
                                    <button onclick="document.querySelectorAll('.config-docente-check').forEach(i=>i.checked=true)" class="text-[10px] font-bold text-blue-600 hover:underline">Todos</button>
                                    <button onclick="document.querySelectorAll('.config-docente-check').forEach(i=>i.checked=false)" class="text-[10px] font-bold text-slate-400 hover:underline">Ninguno</button>
                                </div>
                            </div>
                            <div class="max-h-[30vh] overflow-y-auto border border-slate-100 rounded-2xl p-2 bg-slate-50 grid grid-cols-1 sm:grid-cols-2 gap-1 content-start">
                                ${docentesHtml || '<p class="text-slate-400 text-[10px] p-2">No hay docentes</p>'}
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Grupos Incluidos</label>
                                <div class="flex gap-3">
                                    <button onclick="document.querySelectorAll('.config-grupo-check').forEach(i=>i.checked=true)" class="text-[10px] font-bold text-blue-600 hover:underline">Todos</button>
                                    <button onclick="document.querySelectorAll('.config-grupo-check').forEach(i=>i.checked=false)" class="text-[10px] font-bold text-slate-400 hover:underline">Ninguno</button>
                                </div>
                            </div>
                            <div class="max-h-[30vh] overflow-y-auto border border-slate-100 rounded-2xl p-2 bg-slate-50 grid grid-cols-1 sm:grid-cols-2 gap-1 content-start">
                                ${gruposHtml || '<p class="text-slate-400 text-[10px] p-2">No hay grupos</p>'}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button onclick="document.getElementById('wizard-fullscreen-modal').classList.add('hidden'); horarios.togglePlanning(true);" class="px-8 py-3 bg-white text-slate-600 border border-slate-200 rounded-2xl font-black text-sm hover:bg-slate-50 transition-all shadow-sm">
                        Solo Activar Borrador Vacío
                    </button>
                    <button onclick="document.getElementById('wizard-fullscreen-modal').classList.add('hidden'); horarios.togglePlanning(true); horarios.finalizeGeneration();" id="btn-run-gen" class="px-8 py-3 bg-slate-900 border border-slate-900 text-white rounded-2xl font-black text-sm hover:bg-black transition-all flex items-center justify-center gap-2 shadow-lg shadow-slate-300">
                        <span class="material-symbols-outlined">auto_fix_high</span> Ejecutar Motor y Generar
                    </button>
                </div>
            </div>
        `;
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },

    _updateFilters(source = null) {
        const dSel = document.getElementById('f-docente_id');
        const gSel = document.getElementById('f-grupo_id');
        const mSel = document.getElementById('f-materia_id');
        if (!mSel || !gSel) return;

        const did = dSel ? dSel.value : null;
        const gid = gSel.value;
        const mid = mSel.value;

        const grp = state.grupos.find(g => g.id === gid);
        const mat = state.materias.find(m => m.id === mid);

        // 1. Available Materias
        let validMats = state.materias;
        if (did) validMats = validMats.filter(m => m.docente_id === did);
        if (gid && grp) validMats = validMats.filter(m => m.cuatrimestre == grp.cuatrimestre);

        // Update Materia dropdown HTML if source is Docente, Grupo, or initial load
        if (!source || source === 'docente' || source === 'grupo') {
            let mHtml = '';
            if (validMats.length > 0) {
                if (!mid) mHtml += '<option value="">Sin materia</option>';
                else if (!validMats.find(m => m.id === mid)) mHtml += '<option value="">Sin materia</option>';
                else if (!did && !gid) mHtml += '<option value="">Todas las materias</option>';
                
                mHtml += validMats.map(m => `<option value="${m.id}" ${m.id===mid?'selected':''}>${esc(m.nombre)}</option>`).join('');
            } else {
                mHtml = '<option value="">No hay materias compatibles</option>';
            }
            mSel.innerHTML = mHtml;
            if (mid && !validMats.find(m => m.id === mid)) mSel.value = '';
        }

        // 2. Available Grupos
        let validGrps = state.grupos;
        // Re-evaluate current selected materia obj in case it was just wiped out
        const currentMateriaObj = state.materias.find(m => m.id === mSel.value);
        if (currentMateriaObj && currentMateriaObj.cuatrimestre) {
            validGrps = validGrps.filter(g => g.cuatrimestre == currentMateriaObj.cuatrimestre);
        }

        // Update Grupo dropdown HTML if source is Materia, Docente (resetting Materia), or initial load
        if (!source || source === 'materia' || source === 'docente') {
            let gHtml = '<option value="">Sin grupo</option>';
            gHtml += validGrps.map(g => `<option value="${g.id}" ${g.id===gid?'selected':''}>${esc(g.nombre)} (C${g.cuatrimestre})</option>`).join('');
            gSel.innerHTML = gHtml;
            if (gid && !validGrps.find(g => g.id === gid)) gSel.value = '';
        }

        this._autoSelectAula();
        this._claseWarnings();
    },

    _autoSelectAula() {
        const did = document.getElementById('f-docente_id').value;
        const mid = document.getElementById('f-materia_id').value;
        const aSel = document.getElementById('f-aula_id');
        
        if (did && aSel) {
            let autoAulaId = null;
            if (mid) {
                const existingClase = state.clases.find(c => c.docente_id == did && c.materia_id == mid && c.aula_id);
                if (existingClase) autoAulaId = existingClase.aula_id;
            }
            if (!autoAulaId) {
                const doc = state.docentes.find(d=>d.id===did);
                if (doc && doc.aulas_preferidas && doc.aulas_preferidas.length > 0) {
                    autoAulaId = doc.aulas_preferidas[0];
                }
            }
            if (autoAulaId) {
                const opt = Array.from(aSel.options).find(o => o.value == autoAulaId);
                if (opt) opt.selected = true;
            }
        }
    },

    _claseWarnings() {
        const warnDiv = document.getElementById('clase-warnings');
        if (!warnDiv) return;
        
        const did = document.getElementById('f-docente_id').value;
        const dia = document.getElementById('f-dia').value;
        const h_ini = parseInt(document.getElementById('f-hora_inicio').value||0);
        const dur = parseInt(document.getElementById('f-duracion').value||1);
        
        let warnings = [];

        if (did && dia) {
            const doc = state.docentes.find(d=>d.id===did);
            if (doc) {
                // Check availability
                const disp = doc.disponibilidad || [];
                const normDiaStr = norm(dia);
                const DAYS_MAP = {'lunes':1, 'martes':2, 'miercoles':3, 'jueves':4, 'viernes':5, 'sabado':6};
                const diaNum = DAYS_MAP[normDiaStr] || 0;
                let coversAll = true;
                for (let h = h_ini; h < h_ini+dur; h++) {
                    if (!disp.some(b => parseInt(b.dia) === diaNum && h >= parseInt(b.inicio) && h <= parseInt(b.fin))) {
                        coversAll = false; break;
                    }
                }
                if (!coversAll && disp.length > 0) {
                    warnings.push(`Fuera de disponibilidad asignada`);
                }

                // Calculate daily hours for this teacher on this day (including this draft if we assume it might overload)
                // Need to fetch current clases first (soft check)
                const docClases = state.clases.filter(c => c.docente_id === did && norm(c.dia) === normDia && c.id !== modalContext?.id);
                const dailySum = docClases.reduce((acc, c) => acc + parseInt(c.duracion||1), 0) + dur;
                if (dailySum > parseInt(doc.carga_max_diaria||8)) {
                    warnings.push(`Excede máxima diaria (${dailySum}h > ${doc.carga_max_diaria}h)`);
                }
                
                // Weekly check
                const weeklyClases = state.clases.filter(c => c.docente_id === did && c.id !== modalContext?.id);
                const weeklySum = weeklyClases.reduce((acc, c) => acc + parseInt(c.duracion||1), 0) + dur;
                if (weeklySum > parseInt(doc.carga_max_semanal||40)) {
                    warnings.push(`Excede máxima semanal (${weeklySum}h > ${doc.carga_max_semanal}h)`);
                }
            }
        }

        if (warnings.length > 0) {
            warnDiv.innerHTML = `<div class="bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-bold p-2.5 rounded-xl col-span-2 flex items-start gap-2">
                <span class="material-symbols-outlined text-[14px]">warning</span>
                <div>${warnings.map(w=>`<div>${w}</div>`).join('')}</div>
            </div>`;
            warnDiv.classList.remove('hidden');
        } else {
            warnDiv.classList.add('hidden');
            warnDiv.innerHTML = '';
        }
    },

    async saveModal() {
        if (!modalContext) return;
        const { type, id } = modalContext;
        const isEdit = !!id;
        const btn = document.getElementById('modal-save-btn');
        btn.disabled = true; btn.textContent = 'Guardando...';

        try {
            const dataToSave = collect(type);
            
            // Conflict check for "clase" before saving
            if (type === 'clase') {
                const conflict = this.checkConflict(id, dataToSave.dia, dataToSave.hora_inicio, dataToSave.duracion, dataToSave.docente_id, dataToSave.grupo_id, dataToSave.aula_id);
                if (conflict) throw new Error(conflict);
            }

            const resourceMap = { docente:'docentes', materia:'materias', grupo:'grupos', aula:'aulas', clase:'clases', config:'config' };
            const resource = resourceMap[type] || type;


            if (type === 'bulk_materia') {
                const texto  = document.getElementById('f-bulk_texto').value;
                const nuevas = await api('bulk_materias','POST',{ texto, fecha_inicio: state.config.fecha_inicio||null, fecha_fin: state.config.fecha_fin||null });
                state.materias.push(...nuevas);
                this.toast(`✅ ${nuevas.length} materias importadas`);
            } else if (type === 'bulk_alumnos') {
                const nombre_grupo = document.getElementById('f-bulk_grupo').value;
                const cuatrimestre = parseInt(document.getElementById('f-bulk_cuatri').value);
                const alumnos = this._lastParsedAlumnos || [];
                if (!nombre_grupo || !alumnos.length) throw new Error("Faltan datos (Grupo o Lista)");
                
                const updatedObj = await api('bulk_alumnos', 'POST', { nombre_grupo, cuatrimestre, alumnos });
                // Actualizar en el estado local
                const idx = state.grupos.findIndex(g=>g.nombre.toLowerCase() === nombre_grupo.toLowerCase());
                if (idx>=0) state.grupos[idx] = updatedObj; else state.grupos.push(updatedObj);
                
                this.toast(`✅ Grupo ${nombre_grupo} actualizado con ${alumnos.length} alumnos`);
            } else if (type === 'config') {
                state.config = await api('config','PUT', dataToSave);
                this.toast('✅ Configuración guardada');
            } else if (isEdit) {
                const updated = await api(resource,'PUT', dataToSave, id);
                if (resource === 'materias') {
                    const doc = state.docentes.find(d => d.id === updated.docente_id);
                    updated.docente_nombre = doc ? doc.nombre : null;
                    updated.docente_color = doc ? doc.color_hex : null;
                }
                const idx = state[resource].findIndex(x=>x.id===id);
                if (idx>=0) state[resource][idx] = updated;
                this.toast('✅ Actualizado');
            } else {
                const created = await api(resource,'POST', dataToSave);
                if (resource === 'materias') {
                    const doc = state.docentes.find(d => d.id === created.docente_id);
                    created.docente_nombre = doc ? doc.nombre : null;
                    created.docente_color = doc ? doc.color_hex : null;
                }
                state[resource].push(created);
                if (type === 'materia' && created.plan && !state.planes.includes(created.plan)) {
                    state.planes.push(created.plan);
                    state.planes.sort();
                }
                this.toast('✅ Creado');
            }

            this.closeModal();
            this.refreshAll();
        } catch(err){ this.toast('❌ '+err.message,'error'); }
        finally { btn.disabled=false; btn.textContent='Guardar'; }
    },

    async deleteClaseModal() {
        if (!modalContext?.id) return;
        if (!confirm('¿Eliminar esta clase del horario?')) return;
        await api('clases','DELETE',null,modalContext.id);
        state.clases = state.clases.filter(c=>c.id!==modalContext.id);
        this.closeModal(); this.renderGrid(); this.toast('✅ Clase eliminada');
    },

    async deleteItem(resource, id, nombre) {
        if (!confirm(`¿Eliminar "${nombre}"? No se puede deshacer.`)) return;
        try {
            await api(resource,'DELETE',null,id);
            state[resource] = state[resource].filter(x=>x.id!==id);
            this.refreshAll(); this.toast('✅ Eliminado');
        } catch(err){ this.toast('❌ '+err.message,'error'); }
    },

    exportExcel() {
        // Generar CSV simple con el horario actual filtrado
        const fGrupo = document.getElementById('filter-grupo').value;
        const grupoNombre = state.grupos.find(g=>g.id===fGrupo)?.nombre || 'General';
        
        let header = "HORA," + DAYS.join(",") + "\n";
        let csv = header;
        
        const fTurno = document.getElementById('filter-turno-view').value;
        let visibleHours = HOURS;
        if (fTurno === 'matutino')   visibleHours = HOURS.filter(h => h >= 7 && h < 16);
        if (fTurno === 'vespertino') visibleHours = HOURS.filter(h => h >= 11 && h < 21);
        
        let clases = state.clases;
        if (fGrupo) clases = clases.filter(c=>c.grupo_id===fGrupo);
        
        visibleHours.forEach(h => {
            let row = [`${h}:00`];
            DAYS.forEach(d => {
                const dNorm = norm(d);
                // The original code used .find, implying one class per slot.
                // If multiple classes are possible, this logic needs to be adjusted to list them all.
                // For now, we'll stick to finding the first one, but with the null guard for 'c'.
                const c = clases.find(x => x && norm(x.dia) === dNorm && parseInt(x.hora_inicio) === h);
                row.push(c ? `"${c.materia_nombre} (${c.docente_nombre})"` : "");
            });
            csv += row.join(",") + "\n";
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Horario_${grupoNombre}_${new Date().toISOString().slice(0,10)}.csv`;
        link.click();
        this.toast('✅ Excel (CSV) generado');
    },

    async loadAnalisis() {
        const container = document.getElementById('analisis-container');
        if (!container) return;
        container.innerHTML = '<p class="text-slate-400 text-sm text-center py-8">⏳ Calculando...</p>';
        try {
            const data = await api('analisis');
            if (!data.length) { container.innerHTML='<p class="text-slate-400 text-sm text-center py-8">Sin datos. Agrega materias y grupos con cuatrimestre asignado.</p>'; return; }
            container.innerHTML = data.map(cuatri=>`
                <div class="mb-8">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center font-black text-xs">${cuatri.cuatrimestre||'?'}</span>
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight">Cuatrimestre ${cuatri.cuatrimestre||'Sin asignar'}</h3>
                        <span class="text-xs font-bold text-slate-400">${cuatri.total_materias} materias</span>
                    </div>
                    ${cuatri.grupos.length ? cuatri.grupos.map(g=>{
                        const pct = g.total ? Math.round(g.cubiertas/g.total*100) : 0;
                        const color = pct===100?'green':pct>=60?'amber':'rose';
                        return `<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-3">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <span class="font-black text-slate-800">${esc(g.nombre)}</span>
                                    <span class="ml-2 text-[10px] font-bold text-slate-400 uppercase">${g.turno}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-black ${pct===100?'text-green-600':pct>=60?'text-amber-600':'text-rose-600'}">${g.cubiertas}/${g.total} materias</span>
                                    <div class="w-24 h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-${color}-500 rounded-full transition-all" style="width:${pct}%"></div>
                                    </div>
                                    <span class="text-xs font-black text-slate-500">${pct}%</span>
                                </div>
                            </div>
                            ${g.faltantes > 0 ? `<div class="flex flex-wrap gap-1.5">
                                ${g.materias.filter(m=>!m.asignada).map(m=>`<span class="text-[10px] font-bold px-2 py-0.5 bg-rose-50 text-rose-600 border border-rose-100 rounded-full">⚠ ${esc(m.nombre)}</span>`).join('')}
                            </div>` : '<p class="text-[11px] text-green-600 font-bold">✓ Todas las materias están programadas</p>'}
                        </div>`;
                    }).join('') : '<p class="text-slate-400 text-xs mb-4">Sin grupos en este cuatrimestre.</p>'}
                </div>`).join('');
        } catch(err){ container.innerHTML=`<p class="text-rose-500 text-sm text-center py-8">❌ ${err.message}</p>`; }
    },

    async loadDocenteMaterias(docenteId) {
        const box = document.getElementById('docente-materias-checkboxes');
        if (!box) return;
        try {
            let prefs = state.docenteMaterias[docenteId];
            if (!prefs) {
                prefs = await api('docente_materias', 'GET', null, docenteId);
                state.docenteMaterias[docenteId] = prefs;
            }
            const prefIds = new Set(prefs.map(p=>p.materia_id||p));
            // Group by cuatrimestre
            const byCuatri = {};
            state.materias.forEach(m=>{ const k=m.cuatrimestre||0; if(!byCuatri[k])byCuatri[k]=[]; byCuatri[k].push(m); });
            const cuatriLabel = c=>c===0?'Sin cuatrimestre':`Cuatrimestre ${c}`;
            box.innerHTML = Object.keys(byCuatri).map(Number).sort().map(k=>`
                <div class="cuatri-group" data-cuatri="${k}">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-2 mb-1">${cuatriLabel(k)}</p>
                    ${byCuatri[k].map(m=>`<label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer materia-item" data-nombre="${m.nombre.toLowerCase()}">
                        <input type="checkbox" value="${m.id}" data-hours="${m.horas_semanales}" ${prefIds.has(m.id)?'checked':''} 
                               class="docente-mat-check rounded w-3.5 h-3.5" onchange="horarios.updateDocenteHoursSum()">
                        <span class="text-xs font-bold text-slate-700 search-text">${esc(m.nombre)}</span>
                        <span class="text-[9px] text-slate-400 ml-auto">${m.horas_semanales}h/sem</span>
                    </label>`).join('')}
                </div>`).join('') || '<p class="text-slate-400 text-xs">No hay materias. Crea algunas primero.</p>';
            this.updateDocenteHoursSum();
        } catch(err){ box.innerHTML=`<p class="text-rose-500 text-xs">${err.message}</p>`; }
    },

    filterDocenteMaterias() {
        const q = document.getElementById('docente-mat-search').value.toLowerCase();
        document.querySelectorAll('.materia-item').forEach(el => {
            const visible = el.dataset.nombre.includes(q);
            el.classList.toggle('hidden', !visible);
        });
        // Hide empty cuatri groups
        document.querySelectorAll('.cuatri-group').forEach(group => {
            const hasVisible = Array.from(group.querySelectorAll('.materia-item')).some(m => !m.classList.contains('hidden'));
            group.classList.toggle('hidden', !hasVisible);
        });
    },

    loadDocenteAulas(docenteId, currentAulas = []) {
        const box = document.getElementById('docente-aulas-checkboxes');
        if (!box) return;
        const prefIds = new Set(currentAulas.map(id => String(id)));
        const icons = {aula:'meeting_room', laboratorio:'science', oficina:'work'};

        box.innerHTML = state.aulas.map(a => `
            <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer aula-item" data-nombre="${a.nombre.toLowerCase()}">
                <input type="checkbox" value="${a.id}" ${prefIds.has(String(a.id))?'checked':''} class="docente-aula-check rounded w-3.5 h-3.5">
                <span class="material-symbols-outlined text-slate-400 text-[14px]">${icons[a.tipo]||'meeting_room'}</span>
                <span class="text-xs font-bold text-slate-700">${esc(a.nombre)}</span>
                <span class="text-[9px] text-slate-400 ml-auto">${esc(a.edificio || '')}</span>
            </label>
        `).join('') || '<p class="text-slate-400 text-xs">No hay aulas registradas.</p>';
    },

    filterDocenteAulas() {
        const q = document.getElementById('docente-aula-search').value.toLowerCase();
        document.querySelectorAll('.aula-item').forEach(el => {
            const visible = el.dataset.nombre.includes(q);
            el.classList.toggle('hidden', !visible);
        });
    },

    updateDocenteHoursSum() {
        const checks = document.querySelectorAll('.docente-mat-check:checked');
        let total = 0;
        checks.forEach(c => total += parseInt(c.dataset.hours || 0));
        const el = document.getElementById('docente-horas-sum');
        if (el) el.textContent = `Total: ${total}h/sem`;
    },

    async saveDocenteMaterias(docenteId) {
        const checks = document.querySelectorAll('.docente-mat-check:checked');
        const ids = Array.from(checks).map(c=>c.value);
        try {
            const result = await api('docente_materias','POST',{materia_ids:ids},docenteId);
            state.docenteMaterias[docenteId] = result;
            this.toast(`✅ ${ids.length} materias preferidas guardadas`);
        } catch(err){ this.toast('❌ '+err.message,'error'); }
    },

    // helper para el onchange del docente en clase modal
    _matOpts(did, mid) {
        if (!did) {
            // Si no hay docente seleccionado, mostramos todas agrupadas por cuatri
            const byCuatri = {};
            state.materias.forEach(m => { const k = m.cuatrimestre || 0; if (!byCuatri[k]) byCuatri[k] = []; byCuatri[k].push(m); });
            return '<option value="">Selecciona un docente primero</option>' + 
                Object.keys(byCuatri).sort((a,b)=>a-b).map(k => `
                    <optgroup label="${k==0?'Sin cuatri':`Cuatrimestre ${k}`}">
                        ${byCuatri[k].map(m => `<option value="${m.id}" ${mid===m.id?'selected':''}>${esc(m.nombre)}</option>`).join('')}
                    </optgroup>
                `).join('');
        }

        const prefs  = state.docenteMaterias[did] || [];
        const prefIds = new Set(prefs.map(p=>p.materia_id||p));
        
        // Materias que YA tiene asignadas en la pestaña "Materias"
        const assignedMats = state.materias.filter(m => m.docente_id === did);
        const assignedIds = new Set(assignedMats.map(m => m.id));

        // Preferidas (que no estén ya en asignadas para no duplicar)
        const prefMats = state.materias.filter(m => prefIds.has(m.id) && !assignedIds.has(m.id));
        const restMats = state.materias.filter(m => !prefIds.has(m.id) && !assignedIds.has(m.id));

        let opts = '<option value="">Sin materia</option>';
        if (assignedMats.length) opts += `<optgroup label="✅ Asignadas a este docente">${assignedMats.map(m=>`<option value="${m.id}" ${mid===m.id?'selected':''}>${esc(m.nombre)} (C${m.cuatrimestre||'?'})</option>`).join('')}</optgroup>`;
        if (prefMats.length)     opts += `<optgroup label="⭐ Preferidas">${prefMats.map(m=>`<option value="${m.id}" ${mid===m.id?'selected':''}>${esc(m.nombre)} (C${m.cuatrimestre||'?'})</option>`).join('')}</optgroup>`;
        
        // El usuario quiere que "solo salgan las que da", pero dejamos el resto abajo o en un colapsable?
        // Vamos a poner "Otras" en un grupo separado al final.
        if (restMats.length) opts += `<optgroup label="Otras materias">${restMats.map(m=>`<option value="${m.id}" ${mid===m.id?'selected':''}>${esc(m.nombre)} (C${m.cuatrimestre||'?'})</option>`).join('')}</optgroup>`;
        
        return opts;
    },

    _renderDocenteAvailability(disp) {
        const grid = document.getElementById('availability-grid');
        if (!grid) return;
        
        let availability = [];
        try {
            availability = Array.isArray(disp) ? disp : (typeof disp === 'string' ? JSON.parse(disp || '[]') : []);
        } catch(e) { availability = []; }
        
        // Days 1-5, Hours 7-20
        const days = [1, 2, 3, 4, 5];
        const dayNames = ['L', 'M', 'X', 'J', 'V'];
        const hours = Array.from({length: 14}, (_, i) => i + 7); // 7 to 20
        
        let html = `<div class="grid grid-cols-6 bg-slate-100 gap-px border-b border-slate-100">
            <div class="bg-slate-50 p-1"></div>
            ${dayNames.map(d => `<div class="bg-slate-50 p-1 text-[10px] font-black text-slate-400 text-center uppercase">${d}</div>`).join('')}
        </div>`;
        
        hours.forEach(h => {
            html += `<div class="grid grid-cols-6 bg-slate-100 gap-px">
                <div class="bg-white p-1 text-[9px] font-bold text-slate-400 text-center flex items-center justify-center">${h}:00</div>
                ${days.map(d => {
                    const active = availability.some(b => parseInt(b.dia) === d && h >= parseInt(b.inicio) && h <= parseInt(b.fin));
                    return `<div onclick="this.classList.toggle('bg-blue-500'); this.classList.toggle('bg-white')" 
                                 data-dia="${d}" data-hora="${h}"
                                 class="avail-cell h-6 cursor-pointer transition-colors ${active ? 'bg-blue-500' : 'bg-white'}"></div>`;
                }).join('')}
            </div>`;
        });
        grid.innerHTML = html;
    },

    _getDocenteAvailability() {
        const cells = document.querySelectorAll('.avail-cell.bg-blue-500');
        if (!cells.length) return [];
        
        const blocks = [];
        // Agrupar celdas por día y encontrar rangos contiguos
        const byDay = {};
        cells.forEach(c => {
            const d = c.dataset.dia;
            const h = parseInt(c.dataset.hora);
            if (!byDay[d]) byDay[d] = [];
            byDay[d].push(h);
        });
        
        for (const d in byDay) {
            const hrs = byDay[d].sort((a,b) => a-b);
            if (!hrs.length) continue;
            
            let start = hrs[0];
            for (let i = 0; i < hrs.length; i++) {
                if (hrs[i+1] !== hrs[i] + 1) {
                    blocks.push({ dia: parseInt(d), inicio: start, fin: hrs[i] + 1 });
                    start = hrs[i+1];
                }
            }
        }
        return blocks;
    },

    generateAutomatico() {
        this.openModal('generate_config');
    },

    async finalizeGeneration() {
        const docenteIds = Array.from(document.querySelectorAll('.config-docente-check:checked')).map(i => i.value);
        const grupoIds = Array.from(document.querySelectorAll('.config-grupo-check:checked')).map(i => i.value);
        
        if (!docenteIds.length || !grupoIds.length) {
            alert('Por favor selecciona al menos un docente y un grupo.');
            return;
        }

        const btn = document.getElementById('btn-run-gen');
        if (btn) {
            btn.disabled = true; 
            btn.innerHTML = '<span class="animate-spin material-symbols-outlined">sync</span> Generando...';
        }
        
        this.toast('⏳ Generando horario...', 'info');
        try {
            const data = await api('generate_horario', 'POST', { 
                dry_run: false,
                docente_ids: docenteIds,
                grupo_ids: grupoIds,
                periodo: state.isPlanning ? state.planningPeriod : null
            });
            
            await this.refreshAll();
            this.closeModal();
            this.showGeneratorResults(data);
            
        } catch(err) {
            this.toast('❌ Error en generación: '+err.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Generar Horario';
            }
        }
    },

    showGeneratorResults(res) {
        const modal = document.getElementById('hor-modal');
        const title = document.getElementById('modal-title');
        const body  = document.getElementById('modal-body');
        
        title.textContent = 'Resultado de Generación';
        const unassigned = res.unassigned || [];
        const hasUnassigned = unassigned.length > 0;
        
        body.innerHTML = `
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-4 rounded-2xl ${hasUnassigned ? 'bg-amber-50 border border-amber-100' : 'bg-green-50 border border-green-100'}">
                    <span class="material-symbols-outlined ${hasUnassigned ? 'text-amber-500' : 'text-green-500'} text-3xl">
                        ${hasUnassigned ? 'warning' : 'check_circle'}
                    </span>
                    <div>
                        <div class="font-bold text-slate-800">${res.assigned} materias asignadas</div>
                        <div class="text-xs text-slate-500">${hasUnassigned ? `Faltaron ${unassigned.length} materias (Sin profesor autorizado o sin horario compatible).` : 'Todo el horario ha sido generado con éxito.'}</div>
                    </div>
                </div>

                ${hasUnassigned ? `
                    <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden">
                        <div class="bg-slate-50 px-4 py-2 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-widest">Materias sin asignar</div>
                        <div class="max-h-64 overflow-y-auto divide-y divide-slate-50">
                            ${unassigned.map(u => `
                                <div class="px-4 py-3 flex justify-between items-center group hover:bg-slate-50 transition-colors">
                                    <div>
                                        <div class="text-sm font-bold text-slate-700">${esc(u.materia)}</div>
                                        <div class="text-[10px] text-slate-400 font-medium tracking-wide uppercase">${esc(u.grupo)} · C${u.cuatrimestre}</div>
                                    </div>
                                    <span class="material-symbols-outlined text-amber-400 opacity-50">block</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}

                <div class="flex justify-end">
                    <button onclick="horarios.closeModal()" class="px-8 py-3 bg-slate-900 hover:bg-black text-white text-sm font-bold rounded-xl transition-all shadow-lg">Entendido</button>
                </div>
            </div>
        `;
        modal.classList.remove('hidden');
    },

    refreshAll() {
        this.populateFilters();
        this.renderDocentes();
        this.renderMaterias();
        this.renderGrupos();
        this.renderAulas();
        if (!document.getElementById('panel-grid').classList.contains('hidden')) this.renderGrid();
    },

    toast(msg, type='success') {
        const t = document.createElement('div');
        t.className = `fixed bottom-6 right-6 z-[9999] px-5 py-3 rounded-2xl shadow-xl font-bold text-sm text-white ${type==='error'?'bg-rose-500':'bg-slate-900'}`;
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(()=>t.remove(), 3000);
    },
    renderProyeccion() {
        const container = document.getElementById('proyeccion-container');
        const subtitle  = document.getElementById('proyeccion-subtitle');
        if (!container) return;

        const depth = parseInt(document.getElementById('proyeccion-depth')?.value || 1);

        // 1. Calcular próximo periodo
        let nextCuatri = (state.config.cuatrimestre_activo || 1) + 1;
        let nextAnio   = state.config.anio_activo || 2026;
        if (nextCuatri > 3) { nextCuatri = 1; nextAnio++; }
        
        let label = (depth === 1) 
            ? `Proyección para: ${getPeriodLabel(nextCuatri)} ${nextAnio}`
            : `Proyección para el periodo: +${depth} cuatrimestres`;
        subtitle.textContent = label;

        // 2. Calcular carga por docente
        const docenteCarga = {};
        state.docentes.forEach(d => {
            docenteCarga[d.id] = { nombre: d.nombre, color: d.color_hex, currentHrs: 0, nextHrs: 0, materias: [] };
        });

        // Horas actuales (excluyendo externas)
        state.materias.forEach(m => {
            if (m.docente_id && docenteCarga[m.docente_id] && !m.es_externa) {
                docenteCarga[m.docente_id].currentHrs += (m.horas_semanales || 0);
            }
        });

        // 3. Proyectar para el nivel de profundidad seleccionado (No acumulado)
        const debugInfo = [];
        const materiasSinDocente = [];
        const maxC = Number(state.config.max_cuatrimestres || 10);

        state.grupos.forEach(g => {
            const targetC = Number(g.cuatrimestre || 1) + depth;
            if (targetC > maxC) return;

            const gPlan = (g.plan || 'Plan Regular').trim().toLowerCase();
            const materiasCuatri = state.materias.filter(m => {
                if (m.es_externa) return false;
                if (Number(m.cuatrimestre || 0) !== targetC) return false;
                const mPlans = (m.plan || 'Plan Regular').toLowerCase().split(',').map(p=>p.trim());
                return mPlans.includes(gPlan) || mPlans.includes('plan regular');
            });

            materiasCuatri.forEach(m => {
                if (m.docente_id && docenteCarga[m.docente_id]) {
                    docenteCarga[m.docente_id].nextHrs += m.horas_semanales;
                    docenteCarga[m.docente_id].materias.push({ nombre: m.nombre, grupo: g.nombre, hrs: m.horas_semanales, targetC });
                } else {
                    materiasSinDocente.push({ nombre: m.nombre, grupo: g.nombre, cuatri: targetC });
                }
            });

            debugInfo.push({ grupo: g.nombre, cuatri: targetC, matched: materiasCuatri });
        });

        const sortedDocentes = Object.values(docenteCarga).sort((a,b) => b.nextHrs - a.nextHrs);

        container.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                ${sortedDocentes.map(d => {
                    const diff = d.nextHrs - d.currentHrs;
                    const diffColor = diff > 0 ? 'text-emerald-600' : (diff < 0 ? 'text-rose-600' : 'text-slate-400');
                    const diffIcon  = diff > 0 ? 'trending_up' : (diff < 0 ? 'trending_down' : 'flatware');
                    
                    return `
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full shrink-0" style="background:${d.color}"></div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-slate-800 text-sm truncate">${esc(d.nombre)}</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Carga Proyectada</p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-black text-slate-900">${d.nextHrs}h</p>
                                <p class="text-[10px] font-black ${diffColor} flex items-center justify-end gap-0.5">
                                    <span class="material-symbols-outlined text-[10px]">${diffIcon}</span>
                                    ${diff > 0 ? '+' : ''}${diff}h
                                </p>
                            </div>
                        </div>
                        
                        <div class="bg-slate-50 rounded-xl p-3 space-y-1 max-h-32 overflow-y-auto">
                            ${d.materias.length ? d.materias.map(m => `
                                <div class="flex justify-between items-center text-[10px] font-bold">
                                    <span class="text-slate-600 truncate mr-2">${esc(m.nombre)} <span class="text-slate-300">(${esc(m.grupo)})</span></span>
                                    <span class="text-slate-400 shrink-0">${m.hrs}h</span>
                                </div>
                            `).join('') : '<p class="text-[10px] text-slate-300 italic">Sin materias proyectadas</p>'}
                        </div>
                    </div>`;
                }).join('')}
            </div>

            ${materiasSinDocente.length ? `
            <div class="mt-8">
                <div class="flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-amber-500">warning</span>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Materias sin docente para el próximo periodo</h3>
                </div>
                <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                    ${materiasSinDocente.map(m => `
                        <div class="bg-white/60 p-2 rounded-lg border border-amber-100">
                            <p class="text-[11px] font-black text-amber-900 truncate">${esc(m.nombre)}</p>
                            <p class="text-[9px] font-bold text-amber-600 uppercase">${esc(m.grupo)} · Cuatri ${m.cuatri}</p>
                        </div>
                    `).join('')}
                </div>
            </div>` : ''}

            <div class="mt-12 pt-8 border-t border-slate-100">
                <details class="group">
                    <summary class="list-none flex items-center gap-2 cursor-pointer text-[10px] font-black text-slate-400 uppercase hover:text-slate-600 transition-colors">
                        <span class="material-symbols-outlined text-sm group-open:rotate-180 transition-transform">expand_more</span>
                        Depuración de Proyección (Sólo lectura)
                    </summary>
                    <div class="mt-4 bg-slate-50 rounded-2xl p-6 overflow-x-auto">
                        <table class="w-full text-[10px] font-bold">
                            <thead>
                                <tr class="text-slate-400 uppercase text-left border-b border-slate-200">
                                    <th class="pb-2">Grupo Actual</th>
                                    <th class="pb-2">Cuatri Destino</th>
                                    <th class="pb-2">Materias Encontradas en Catálogo</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-600">
                                ${debugInfo.map(d => `
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2">${esc(d.grupo)}</td>
                                        <td class="py-2">C${d.cuatri}</td>
                                        <td class="py-2">${d.matched.length ? d.matched.map(m => `${esc(m.nombre)} [${m.docente_nombre || 'Sin docente'}]`).join(', ') : '<span class="text-rose-400 font-black">No se hallaron materias para este cuatri/plan</span>'}</td>
                                    </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        `;
    },

    togglePlanning(active) {
        const btnToggle  = document.getElementById('btn-toggle-planning');
        const btnText    = document.getElementById('btn-toggle-planning-text');
        const btnPromote = document.getElementById('btn-promote-groups');

        if (active) {
            let nextCuatri = (state.config.cuatrimestre_activo || 1) + 1;
            let nextAnio   = state.config.anio_activo || 2026;
            if (nextCuatri > 3) { nextCuatri = 1; nextAnio++; }
            state.planningPeriod = `${nextAnio}-${nextCuatri}`;
            state.isPlanning = true;
            
            document.getElementById('planning-indicator').classList.remove('hidden');
            document.getElementById('planning-period-label').textContent = `Periodo: ${getPeriodLabel(nextCuatri)} ${nextAnio}`;
            
            if (btnToggle) {
                btnToggle.className = "flex items-center gap-2 bg-slate-900 text-white hover:bg-slate-800 px-4 py-2 rounded-xl font-bold text-xs transition-all no-print shadow-sm";
                btnText.textContent = "Salir de Planeación";
            }
            if (btnPromote) btnPromote.classList.remove('hidden');

            this.toast('🎯 Modo Planeación Activado: Construyendo el futuro horario.');
            this.switchTab('grid');
            
            // Highlight Generator Button
            const btnGen = document.querySelector('button[onclick="horarios.generateAutomatico()"]');
            if (btnGen) {
                btnGen.classList.remove('bg-blue-50', 'text-blue-600');
                btnGen.classList.add('bg-amber-500', 'text-white', 'ring-4', 'ring-amber-200', 'animate-pulse');
            }
        } else {
            state.isPlanning = false;
            state.planningPeriod = '';
            document.getElementById('planning-indicator').classList.add('hidden');
            
            // Reset Generator Button
            const btnGen = document.querySelector('button[onclick="horarios.generateAutomatico()"]');
            if (btnGen) {
                btnGen.classList.add('bg-blue-50', 'text-blue-600');
                btnGen.classList.remove('bg-amber-500', 'text-white', 'ring-4', 'ring-amber-200', 'animate-pulse');
            }
            
            if (btnToggle) {
                btnToggle.className = "flex items-center gap-2 bg-blue-50 text-blue-600 hover:bg-blue-100 px-4 py-2 rounded-xl font-bold text-xs transition-all no-print shadow-sm";
                btnText.textContent = "Asistente de Planeación";
            }
            if (btnPromote) btnPromote.classList.add('hidden');

            this.toast('👋 Modo Planeación Desactivado.');
        }
        this.loadAll();
    },

    async copyCurrentPeriod() {
        if (!state.isPlanning) return;
        if (!confirm("⚠️ ¿Deseas copiar el horario actual como base para este nuevo periodo?\n\nEsto borrará lo que hayas hecho en el modo planeación para este periodo.")) return;
        
        try {
            this.toast('⏳ Copiando horario...');
            const res = await api('copy_period', 'POST', { to: state.planningPeriod });
            this.toast(`✅ ¡Copiadas ${res.copied} clases con éxito!`);
            await this.loadAll();
        } catch(err) {
            this.toast('❌ Error al copiar: ' + err.message, 'error');
        }
    },

    async confirmPromoteGroups() {
        if (!confirm("⚠️ ¿Estás SEGURO de querer aplicar la promoción definitiva?\n\nEsto incrementará el cuatrimestre de TODOS los grupos en la base de datos y cambiará el periodo activo del sistema.")) return;
        
        try {
            const res = await api('promote_groups', 'POST', {});
            this.toast('🚀 ¡Grupos promocionados con éxito!');
            state.isPlanning = false;
            document.getElementById('planning-indicator').classList.add('hidden');
            const btnPromote = document.getElementById('btn-promote-groups');
            if (btnPromote) btnPromote.classList.add('hidden');
            await this.init(); // Recargar todo el estado
        } catch(err) {
            this.toast('❌ Error en promoción: ' + err.message, 'error');
        }
    }
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const PERIOD_NAMES = { 1: 'Enero - Abril', 2: 'Mayo - Agosto', 3: 'Septiembre - Diciembre' };
const getPeriodLabel = n => PERIOD_NAMES[n] || `${n}° Cuatrimestre`;

function mkEl(tag, cls, text) {
    const el = document.createElement(tag);
    el.className = cls; el.textContent = text; return el;
}
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function g(id)  { return document.getElementById(`f-${id}`)?.value ?? null; }
function gCheck(id){ return document.getElementById(`f-${id}`)?.checked ?? false; }
function norm(s) { 
    return String(s||'').toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, ""); 
}
function f(id, label, value='', placeholder='', type='text') {
    return `<div><label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">${label}</label>
    <input type="${type}" id="f-${id}" value="${String(value??'').replace(/"/g,'&quot;')}" placeholder="${placeholder}"
           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"></div>`;
}
function collect(type) {
    const m = {
        docente:  ()=>{
            const user = document.getElementById('f-email-user')?.value || '';
            const email = user ? `${user}@upsrj.edu.mx` : '';
            const materia_ids = Array.from(document.querySelectorAll('#docente-materias-checkboxes input:checked')).map(i=>i.value);
            // Capturar disponibilidad (esto se llenará mediante una función específica o grid)
            const disponibilidad = horarios._getDocenteAvailability(); 
            return { 
                nombre:g('nombre'), 
                email, 
                horas_asesoria:parseInt(g('horas_asesoria')||0), 
                color_hex:g('color_hex'), 
                materia_ids,
                disponibilidad,
                carga_max_diaria: parseInt(g('carga_max_diaria')||8),
                carga_max_semanal: parseInt(g('carga_max_semanal')||40),
                aulas_preferidas: Array.from(document.querySelectorAll('.docente-aula-check:checked')).map(i=>i.value)
            };
        },
        grupo:    ()=>({ 
            nombre:g('nombre'), 
            cuatrimestre:parseInt(g('cuatrimestre')||1), 
            turno:g('turno'), 
            plan:g('plan'),
            capacidad_maxima: parseInt(g('capacidad_maxima')||30)
        }),
        aula:     ()=>({ nombre:g('nombre'), tipo:g('tipo'), capacidad:g('capacidad'), edificio:g('edificio') }),
        clase:    ()=>({ dia:g('dia'), hora_inicio:parseInt(g('hora_inicio')), duracion:parseInt(g('duracion')), docente_id:g('docente_id'), grupo_id:g('grupo_id'), materia_id:g('materia_id'), aula_id:g('aula_id') }),
        config:   ()=>({ anio_activo:g('anio_activo'), cuatrimestre_activo:g('cuatrimestre_activo'), turno_corte:g('turno_corte'), max_cuatrimestres:g('max_cuatrimestres'), fecha_inicio:g('fecha_inicio'), fecha_fin:g('fecha_fin') }),
        materia:  ()=>{
            let plan = g('plan');
            if (plan === '__NUEVO__') plan = document.getElementById('f-plan-nuevo')?.value || 'Plan Regular';
            return { 
                nombre:g('nombre'), 
                horas_totales:g('horas_totales')?parseInt(g('horas_totales')):null, 
                horas_semanales:parseInt(g('horas_semanales')||2), 
                cuatrimestre:parseInt(g('cuatrimestre')||0), 
                docente_id:g('docente_id') || null, 
                es_externa:gCheck('es_externa'), 
                plan,
                prioridad: g('prioridad') || 'Media',
                es_especialidad: gCheck('es_especialidad')
            };
        },
    };
    return (m[type]||(() => ({})))();
}
function randomColor() {
    const palette = ['#3b82f6','#8b5cf6','#f43f5e','#10b981','#f59e0b','#06b6d4','#ec4899','#6366f1','#84cc16','#14b8a6'];
    return palette[Math.floor(Math.random()*palette.length)];
}
function weeks(fi, ff) {
    if (!fi || !ff) return null;
    const d = Math.round((new Date(ff) - new Date(fi)) / 86400000);
    return d > 0 ? Math.round(d / 7) : null;
}
// Expose on horarios object
Object.assign(horarios, {
    previewBulk() {
        const raw = document.getElementById('f-bulk_texto')?.value || '';
        const el  = document.getElementById('bulk-preview');
        if (!el) return;
        const w = weeks(state.config.fecha_inicio, state.config.fecha_fin);
        const rows = raw.split('\n').map(l=>{
            const line   = l.trim(); if (!line) return null;
            const parts  = line.split(',').map(s=>s.trim());
            let nombre = parts[0], tot = null, cuatri = null, plan = 'Plan Regular';
            
            if (parts.length >= 4) {
                nombre = parts[0];
                tot    = parseInt(parts[1]);
                cuatri = parseInt(parts[2]);
                plan   = parts[3];
            } else if (parts.length === 3) {
                nombre = parts[0];
                tot    = parseInt(parts[1]);
                cuatri = parseInt(parts[2]);
            } else if (parts.length === 2) {
                nombre = parts[0];
                tot    = parseInt(parts[1]);
            }
            
            const sem = (tot && w) ? Math.ceil(tot/w) : tot;
            return { nombre, tot, cuatri, sem, plan };
        }).filter(Boolean);

        if (!rows.length) { el.classList.add('hidden'); return; }
        el.classList.remove('hidden');
        el.innerHTML = `<table class="w-full text-xs">
            <thead><tr class="text-slate-400 font-bold uppercase">
                <th class="text-left pb-1">Materia</th>
                <th class="text-center pb-1">Cuatri</th>
                <th class="text-center pb-1">Plan</th>
                <th class="text-center pb-1">Hrs totales</th>
                <th class="text-center pb-1">Hrs/sem</th>
            </tr></thead>
            <tbody>${rows.map(r=>`<tr class="border-t border-slate-100">
                <td class="py-1 pr-2 font-bold text-slate-700">${esc(r.nombre)}</td>
                <td class="py-1 text-center font-bold text-slate-500">${r.cuatri?`C${r.cuatri}`:'—'}</td>
                <td class="py-1 text-center text-slate-400 font-bold">${esc(r.plan)}</td>
                <td class="py-1 text-center text-slate-400">${r.tot??'—'}</td>
                <td class="py-1 text-center font-black text-blue-600">${r.sem??'default'}</td>
            </tr>`).join('')}</tbody>
        </table>`;
    },

    previewAlumnosBulk() {
        const raw = document.getElementById('f-bulk_alumnos_texto')?.value || '';
        const el  = document.getElementById('bulk-alumnos-preview');
        const fGrupo = document.getElementById('f-bulk_grupo');
        const fCuatri = document.getElementById('f-bulk_cuatri');
        if (!el) return;

        const result = this._parseAlumnos(raw);
        this._lastParsedAlumnos = result.alumnos;

        if (result.grupo && fGrupo && !fGrupo.value) fGrupo.value = result.grupo;
        if (result.cuatri && fCuatri && (fCuatri.value == 1 || !fCuatri.value)) fCuatri.value = result.cuatri;

        if (!result.alumnos.length) { el.classList.add('hidden'); return; }
        el.classList.remove('hidden');
        el.innerHTML = `
            <p class="text-[10px] font-black text-slate-400 uppercase mb-2">Se detectaron ${result.alumnos.length} alumnos:</p>
            <div class="space-y-1">
                ${result.alumnos.map(a => `
                    <div class="flex items-center gap-2 text-[11px] font-bold text-slate-600 bg-white p-1.5 rounded-lg border border-slate-100 shadow-sm">
                        <span class="text-slate-300 font-mono text-[9px]">${esc(a.matricula)}</span>
                        <span>${esc(a.name)}</span>
                    </div>
                `).join('')}
            </div>
        `;
    },

    _parseAlumnos(text) {
        const lines = text.split('\n');
        let grupo = null;
        let cuatri = null;
        const alumnos = [];

        const pGrupo = /(?:Grupo|Gpo|GRUPO|GPO|Grupo:)\s*([A-Za-z0-9-]+)/i;
        const pCuatri = /([1-9])(?:°|vo|ro|to|vo|mo)?\s*(?:Cuatrimestre|Cuatri|Cuat|Q)/i;

        lines.forEach(line => {
            const l = line.trim();
            if (!l || l.toLowerCase().startsWith('matricula') || l.toLowerCase().startsWith('nombre')) return;

            // Detect Grupo/Cuatri headers
            if (!grupo) { const mg = l.match(pGrupo); if (mg) grupo = mg[1]; }
            if (!cuatri) { const mc = l.match(pCuatri); if (mc) cuatri = mc[1]; }

            // Split by tabs or multiple spaces (usual for copy-paste from Excel/Sheets)
            const parts = l.split(/\t+| {2,}/).map(s => s.trim()).filter(Boolean);
            
            if (parts.length >= 2) {
                // Find which part is the ID (7-10 digits)
                let idIdx = parts.findIndex(p => /^\d{7,11}$/.test(p));
                
                // If not found, maybe it's the first part but has leading junk? 
                // No, usually it's a clean column.
                
                if (idIdx !== -1) {
                    const matricula = parts[idIdx];
                    // Name is usually the NEXT part after ID, or the one before if it's Name-ID
                    // But if there's a leading number (1, 2, 3), the ID might be index 1 and Name index 2.
                    let name = "";
                    
                    // Logic: If there are many parts, try to find a part that looks like a name (no digits, multi-word)
                    const nameParts = parts.filter((p, idx) => idx !== idIdx && !/^\d+$/.test(p) && p.length > 5);
                    if (nameParts.length > 0) {
                        name = nameParts[0]; // Take the first one that looks like a name
                    } else if (parts[idIdx + 1]) {
                        name = parts[idIdx + 1]; // Fallback to next column
                    } else if (parts[idIdx - 1]) {
                        name = parts[idIdx - 1]; // Fallback to previous column
                    }

                    // Also extract group if it's in a column
                    if (!grupo) {
                        const possibleGroup = parts.find(p => p.includes('-PA-') || p.includes('-P-') || (p.length > 5 && p.includes('-')));
                        if (possibleGroup) grupo = possibleGroup;
                    }

                    if (name) {
                        alumnos.push({ id: matricula, matricula, name: name.trim() });
                    }
                }
            } else {
                // Fallback for flat lists: "ID Name" or "Name ID"
                const m1 = l.match(/^(\d{7,11})\s+(.+)$/);
                if (m1) alumnos.push({ id: m1[1], matricula: m1[1], name: m1[2].trim() });
                const m2 = l.match(/^(.+)\s+(\d{7,11})$/);
                if (m2) alumnos.push({ id: m2[2], matricula: m2[2], name: m2[1].trim() });
            }
        });

        return { grupo, cuatri, alumnos };
    },

    calcHours(trigger) {
        const w = weeks(state.config.fecha_inicio, state.config.fecha_fin);
        if (!w) return;
        const tot = document.getElementById('f-horas_totales');
        const sem = document.getElementById('f-horas_semanales');
        if (trigger === 'tot') {
            if (tot.value) sem.value = Math.ceil(parseInt(tot.value) / w);
        } else {
            if (sem.value) tot.value = parseInt(sem.value) * w;
        }
    }
});


document.addEventListener('DOMContentLoaded', ()=>horarios.init());
