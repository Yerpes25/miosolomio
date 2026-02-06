// ============================================
// MÓDULO DE SEDES
// Sistema de carga dinámica
// ============================================
(function () {
    'use strict';

    if (!window.CHEAdmin) {
        console.error('CHEAdmin no está definido');
        return;
    }

    window.CHEModules = window.CHEModules || {};

    const apiUrl = window.CHEAdmin.apiUrl;
    const setMessage = window.CHEAdmin.setMessage;

    // Estado del módulo
    let state = {
        todosLosWorkers: null,
        sedeCurrentWorkers: [],
        sedeCurrentPartes: [],
        sedeCurrentVacaciones: [],
        eventHandlers: {}
    };

    // Referencias a elementos DOM
    let elements = {};

    /**
     * Inicializar referencias a elementos DOM
     */
    function initElements() {
        elements = {
            sedesTitle: document.querySelector('.h2-sedes'),
            sedesToolbar: document.querySelector('.che-admin-sedes-toolbar'),
            sedesBody: document.getElementById('che-admin-sedes-body'),
            sedeWorkersWrapper: document.getElementById('che-admin-sede-workers-wrapper'),
            sedeWorkersBody: document.getElementById('che-admin-sede-workers-body'),
            sedeWorkersTitleEl: document.getElementById('che-admin-sede-workers-title'),
            backWorkersBtn: document.getElementById('che-admin-sede-workers-back-btn'),
            newSedeBtn: document.getElementById('che-admin-sede-new-btn'),
            createSedeWrapper: document.getElementById('che-admin-create-sede-wrapper'),
            createSedeForm: document.getElementById('che-admin-create-sede'),
            sedeMsgBox: document.getElementById('che-admin-sede-message'),
            closeSedeBtn: document.getElementById('che-admin-sede-close-btn'),
            cancelSedeBtn: document.getElementById('che-admin-sede-cancel-btn'),
            sedeDetalleWrapper: document.getElementById('che-admin-sede-detalle-wrapper'),
            sedeDetalleTitleEl: document.getElementById('che-admin-sede-detalle-title'),
            sedeDetalleTrabEl: document.getElementById('che-admin-sede-detalle-trabajadores'),
            sedeDetallePartesEl: document.getElementById('che-admin-sede-detalle-partes'),
            sedeDetalleHorasEl: document.getElementById('che-admin-sede-detalle-horas'),
            sedeDetallePartesBody: document.getElementById('che-admin-sede-detalle-partes-body'),
            backDetalleBtn: document.getElementById('che-admin-sede-detalle-back-btn'),
            trabajadoresFilter: document.getElementById('che-admin-sede-trabajadores-filter'),
            partesFilter: document.getElementById('che-admin-sede-partes-filter'),
            vacacionesFilter: document.getElementById('che-admin-sede-vacaciones-filter')
        };
    }

    /**
     * Cargar todos los workers (con caché)
     */
    async function cargarTodosLosWorkers() {
        if (state.todosLosWorkers) return state.todosLosWorkers;

        try {
            const url = apiUrl + 'admin/workers';
            const res = await fetch(url, { method: 'GET' });
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al cargar usuarios');
            }

            state.todosLosWorkers = data.users || [];
            return state.todosLosWorkers;
        } catch (e) {
            console.error(e);
            state.todosLosWorkers = [];
            return state.todosLosWorkers;
        }
    }

    /**
     * Renderizar listado de sedes
     */
    function renderSedes(sedes) {
        
        if (!elements.sedesBody) {
            console.error('ERROR: elements.sedesBody no existe!');
            return;
        }
        
        elements.sedesBody.innerHTML = '';
       
        // Manejar tanto objetos como arrays
        let entries;
        if (Array.isArray(sedes)) {
            // Si es un array, convertir a entries [index, sede]
            entries = sedes.map((sede, index) => [sede.ID || index, sede]);
        } else {
            // Si es un objeto, usar Object.entries
            entries = Object.entries(sedes || {});
        }
        
   
        
        if (!entries.length) {
            const empty = document.createElement('p');
            empty.textContent = 'No hay sedes creadas.';
            elements.sedesBody.appendChild(empty);
            return;
        }

        entries.forEach(([id, data]) => {
           
            const card = document.createElement('div');
            card.className = 'che-sede-card';

            card.innerHTML = `
                <div class="che-sede-card__header">
                    <h3 class="che-sede-card__title">${data.nombre || ''}</h3>
                    <span class="che-sede-card__Geolocalizacion">
                    ${data.lat && data.lng ? `  
                    <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(data.lat + ',' + data.lng)}" target="_blank" class="che-sede-card__map-link" title="Ver en Google Maps">
                        📍
                    </a>` : ''}
                    </span>
                    
                </div>
                <div class="che-sede-card__body">
                    <p><strong>Ubicación:</strong> ${data.direccion || 'Sin dirección'}</p>
                    <p><strong>Código postal:</strong> ${data.cp || 'No indicado'}</p>
                    <p><strong>Encargado:</strong> ${data.encargado || 'Sin encargado'}</p>
                    <p><strong>Teléfono:</strong> ${data.telefono || 'Sin teléfono'}</p>
                </div>
                <div class="che-sede-card__footer">
                    <button type="button"
                            class="che-admin-sede-ver-detalle success"
                            id="all-btn"
                            data-sede-id="${id}">
                        Detalles
                    </button>
                    <button type="button"
                            class="che-admin-sede-delete-btn danger"
                            data-sede-id="${id}">
                        Eliminar
                    </button>
                </div>
            `;
            elements.sedesBody.appendChild(card);
        
        });
    }

    /**
     * Cargar listado de sedes
     */
    async function cargarSedesListado() {
        if (!elements.sedesBody) return;

        try {
            const res = await fetch(apiUrl + 'admin/sedes', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                }
            });
            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.error || 'Error al cargar sedes');
            }

            renderSedes(data.sedes);

            if (window.CHEAdmin && typeof window.CHEAdmin.rellenarSelectsSede === 'function') {
                window.CHEAdmin.rellenarSelectsSede(data.sedes);
            }
        } catch (e) {
            console.error(e);
        }
    }

    /**
     * Convertir tiempo a segundos
     */
    function parseTiempoToSeconds(str) {
        if (!str) return 0;
        const parts = String(str).split(':').map(n => parseInt(n, 10));

        if (parts.length === 3) {
            const [h, m, s] = parts;
            return (isNaN(h) ? 0 : h) * 3600 +
                   (isNaN(m) ? 0 : m) * 60 +
                   (isNaN(s) ? 0 : s);
        }
        if (parts.length === 2) {
            const [m, s] = parts;
            return (isNaN(m) ? 0 : m) * 60 + (isNaN(s) ? 0 : s);
        }

        const v = parseInt(str, 10);
        return isNaN(v) ? 0 : v;
    }

    /**
     * Mostrar detalle completo de una sede
     */
    async function mostrarDetalleSede(sedeId) {
        if (!elements.sedeDetalleWrapper || !elements.sedesBody) return;

        try {
            // 1) Cargar trabajadores de la sede
            const urlWorkers = new URL(apiUrl + 'admin/usuarios-por-sede');
            urlWorkers.searchParams.set('sede', sedeId);

            const resWorkers = await fetch(urlWorkers.toString(), { method: 'GET' });
            const dataWorkers = await resWorkers.json();

            const usersInSede = (resWorkers.ok && dataWorkers.success) ? (dataWorkers.users || []) : [];
            const numTrabajadores = usersInSede.length;

            // 2) Cargar partes de todos los trabajadores
            const partes = [];
            for (const user of usersInSede) {
                try {
                    const url = new URL(apiUrl + 'admin/partes-por-usuario');
                    url.searchParams.set('user_id', user.ID);

                    const res = await fetch(url.toString(), { method: 'GET' });
                    const data = await res.json();

                    if (res.ok && data.success && Array.isArray(data.partes)) {
                        data.partes.forEach(p => {
                            partes.push({
                                ...p,
                                worker_name: p.worker_name || user.display_name || user.user_email || '',
                                worker_id: user.ID
                            });
                        });
                    }
                } catch (e) {
                    console.error('Error cargando partes de usuario', user.ID, e);
                }
            }

            // 2b) Cargar vacaciones de la sede
            let vacaciones = [];
            try {
                const urlVacaciones = new URL(apiUrl + 'admin/vacaciones');
                urlVacaciones.searchParams.set('sede_id', sedeId);

                const resVacaciones = await fetch(urlVacaciones.toString(), { method: 'GET' });
                const dataVacaciones = await resVacaciones.json();

                if (resVacaciones.ok && dataVacaciones.success) {
                    vacaciones = dataVacaciones.vacaciones || [];
                }
            } catch (e) {
                console.error('Error cargando vacaciones de la sede:', e);
            }

            // 3) Calcular totales
            const numPartes = partes.length;
            const totalSegundos = partes.reduce((acc, p) => {
                const t = parseTiempoToSeconds(p.tiempo_total);
                return acc + t;
            }, 0);

            const horas = Math.floor(totalSegundos / 3600);
            const minutos = Math.floor((totalSegundos % 3600) / 60);

            // 4) Pintar KPIs
            if (elements.sedeDetalleTrabEl) elements.sedeDetalleTrabEl.textContent = String(numTrabajadores);
            if (elements.sedeDetallePartesEl) elements.sedeDetallePartesEl.textContent = String(numPartes);
            if (elements.sedeDetalleHorasEl) {
                elements.sedeDetalleHorasEl.textContent = `${horas} h ${minutos} min`;
            }

            // Guardar datos para filtrado
            state.sedeCurrentWorkers = usersInSede;
            state.sedeCurrentPartes = partes;
            state.sedeCurrentVacaciones = vacaciones;

            // Exponer en window.CHEAdmin para compatibilidad
            window.CHEAdmin.sedeCurrentWorkers = usersInSede;
            window.CHEAdmin.sedeCurrentPartes = partes;
            window.CHEAdmin.sedeCurrentVacaciones = vacaciones;

            // 5) Rellenar selects de filtro
            if (elements.partesFilter) {
                elements.partesFilter.innerHTML = '<option value="">Todos los trabajadores</option>';
                usersInSede.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.ID;
                    option.textContent = user.display_name || user.user_email;
                    elements.partesFilter.appendChild(option);
                });
            }

            if (elements.vacacionesFilter) {
                elements.vacacionesFilter.innerHTML = '<option value="">Todos los trabajadores</option>';
                usersInSede.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.ID;
                    option.textContent = user.display_name || user.user_email;
                    elements.vacacionesFilter.appendChild(option);
                });
            }

            // 6) Renderizar
            renderTrabajadores(usersInSede);
            renderPartes(partes);
            renderVacaciones(vacaciones);

            // 7) Configurar filtros
            setupSedeFilters();

            // Título con nombre de la sede
            const cardTitle = elements.sedesBody
                .querySelector(`.che-admin-sede-ver-detalle[data-sede-id="${sedeId}"]`)
                ?.closest('.che-sede-card')
                ?.querySelector('.che-sede-card__title')
                ?.textContent || 'Sede';

            if (elements.sedeDetalleTitleEl) {
                elements.sedeDetalleTitleEl.textContent = `Información de la sede de: ${cardTitle}`;
            }

            // 8) Mostrar detalle
            elements.sedesBody.style.display = 'none';
            if (elements.sedeWorkersWrapper) elements.sedeWorkersWrapper.style.display = 'none';
            elements.sedeDetalleWrapper.style.display = 'block';
            if (elements.sedesTitle) elements.sedesTitle.style.display = 'none';
            if (elements.sedesToolbar) elements.sedesToolbar.style.display = 'none';

            // 9) Inicializar calendario si existe
            if (window.CHEAdmin && window.CHEAdmin.sedeCalendar) {
                window.CHEAdmin.sedeCalendar.setSede(sedeId);
            }

        } catch (error) {
            console.error('Error al cargar detalles de la sede:', error);
            alert('Error al cargar los detalles de la sede');
        }
    }

    /**
     * Renderizar trabajadores
     */
    function renderTrabajadores(workers) {
        const trabajadoresGrid = document.getElementById('che-admin-sede-trabajadores-grid');
        if (!trabajadoresGrid) return;

        trabajadoresGrid.innerHTML = '';

        if (!workers.length) {
            trabajadoresGrid.innerHTML = '<p class="che-empty-message">No hay trabajadores asignados a esta sede.</p>';
        } else {
            workers.forEach(user => {
                const card = document.createElement('div');
                card.className = 'che-trabajador-card-horizontal';
                card.innerHTML = `
                    <div class="che-trabajador-card__avatar">
                        ${user.avatar ? `<img src="${user.avatar}" alt="${user.display_name}">` : '<div class="che-avatar-placeholder">👤</div>'}
                    </div>
                    <div class="che-trabajador-card__content">
                        <div class="che-trabajador-card__name">${user.display_name || user.user_login || 'Sin nombre'}</div>
                        <div class="che-trabajador-card__email">${user.user_email || ''}</div>
                        <div class="che-trabajador-card__role">${Array.isArray(user.roles) ? user.roles.join(', ') : 'worker'}</div>
                    </div>
                `;
                trabajadoresGrid.appendChild(card);
            });
        }
    }

    /**
     * Renderizar partes
     */
    function renderPartes(partes) {
        const partesGrid = document.getElementById('che-admin-sede-partes-grid');
        if (!partesGrid) return;

        partesGrid.innerHTML = '';

        if (!partes.length) {
            partesGrid.innerHTML = '<p class="che-empty-message">No hay partes registrados para esta sede.</p>';
        } else {
            partes.forEach(parte => {
                const card = document.createElement('div');
                const fecha = parte.post_date.split(' ')[0];
                card.className = 'che-parte-card-horizontal';

                const estadoClass = {
                    'validado': 'che-parte-card--validado',
                    'rechazado': 'che-parte-card--rechazado',
                    'pendiente': 'che-parte-card--pendiente'
                }[parte.estado_validacion] || 'che-parte-card--pendiente';

                card.classList.add(estadoClass);

                card.innerHTML = `
                    <div class="che-parte-card__left">
                        <div class="che-parte-card__id">#${parte.ID}</div>
                        <div class="che-parte-card__estado-badge">${parte.estado_validacion || 'pendiente'}</div>
                    </div>
                    <div class="che-parte-card__content">
                        <div class="che-parte-card__trabajador">${parte.worker_name || 'Desconocido'}</div>
                        <div class="che-parte-card__details">
                            <span><strong>Fecha:</strong> ${fecha || '-'}</span>
                            <span><strong>Tiempo:</strong> ${parte.tiempo_total || '-'}</span>
                        </div>
                    </div>
                `;
                partesGrid.appendChild(card);
            });
        }
    }

    /**
     * Renderizar vacaciones
     */
    function renderVacaciones(vacaciones) {
        const vacacionesGrid = document.getElementById('che-admin-sede-vacaciones-grid');
        if (!vacacionesGrid) return;

        vacacionesGrid.innerHTML = '';

        if (!vacaciones.length) {
            vacacionesGrid.innerHTML = '<p class="che-empty-message">No hay solicitudes de vacaciones.</p>';
        } else {
            vacaciones.forEach(vacacion => {
                const card = document.createElement('div');
                card.className = 'che-vacacion-card-horizontal';

                const estadoClass = {
                    'aprobado': 'che-vacacion-card--aprobado',
                    'rechazado': 'che-vacacion-card--rechazado',
                    'pendiente': 'che-vacacion-card--pendiente'
                }[vacacion.estado?.toLowerCase()] || 'che-vacacion-card--pendiente';

                card.classList.add(estadoClass);
                card.dataset.vacacionId = vacacion.ID;

                card.innerHTML = `
                    <div class="che-vacacion-card__left">
                        <div class="che-vacacion-card__id">#${vacacion.ID}</div>
                        <div class="che-vacacion-card__estado-badge">${vacacion.estado || 'pendiente'}</div>
                    </div>
                    <div class="che-vacacion-card__content">
                        <div class="che-vacacion-card__trabajador">${vacacion.worker_name || 'Desconocido'}</div>
                        <div class="che-vacacion-card__details">
                            <span><strong>Inicio:</strong> ${vacacion.fecha_inicio || '-'}</span>
                            <span><strong>Fin:</strong> ${vacacion.fecha_fin || '-'}</span>
                            <span><strong>Días:</strong> ${vacacion.dias_totales || '-'}</span>
                        </div>
                        ${vacacion.estado?.toLowerCase() === 'pendiente' ? `
                        <div class="che-vacacion-card__actions">
                            <button type="button" class="all-btn success che-btn-aprobar-vac" data-vacacion-id="${vacacion.ID}">Aprobar</button>
                            <button type="button" class="all-btn danger che-btn-rechazar-vac" data-vacacion-id="${vacacion.ID}">Rechazar</button>
                        </div>
                        ` : ''}
                    </div>
                `;
                vacacionesGrid.appendChild(card);
            });
        }
    }

    /**
     * Configurar filtros de sede
     */
    function setupSedeFilters() {
        if (elements.trabajadoresFilter) {
            elements.trabajadoresFilter.value = '';
        }

        if (elements.partesFilter) {
            elements.partesFilter.value = '';
        }

        if (elements.vacacionesFilter) {
            elements.vacacionesFilter.value = '';
        }
    }

    /**
     * Filtrar trabajadores
     */
    function filterTrabajadores() {
        if (!elements.trabajadoresFilter) return;

        const searchTerm = elements.trabajadoresFilter.value.toLowerCase().trim();

        if (!searchTerm) {
            renderTrabajadores(state.sedeCurrentWorkers);
            return;
        }

        const filtered = state.sedeCurrentWorkers.filter(worker => {
            const name = (worker.display_name || '').toLowerCase();
            const email = (worker.user_email || '').toLowerCase();
            const login = (worker.user_login || '').toLowerCase();
            return name.includes(searchTerm) || email.includes(searchTerm) || login.includes(searchTerm);
        });

        renderTrabajadores(filtered);
    }

    /**
     * Filtrar partes
     */
    function filterPartes() {
        if (!elements.partesFilter) return;

        const workerId = elements.partesFilter.value;

        if (!workerId) {
            renderPartes(state.sedeCurrentPartes);
            return;
        }

        const filtered = state.sedeCurrentPartes.filter(parte =>
            String(parte.worker_id) === String(workerId)
        );
        renderPartes(filtered);
    }

    /**
     * Filtrar vacaciones
     */
    function filterVacaciones() {
        if (!elements.vacacionesFilter) return;

        const workerId = elements.vacacionesFilter.value;

        if (!workerId) {
            renderVacaciones(state.sedeCurrentVacaciones);
            return;
        }

        const filtered = state.sedeCurrentVacaciones.filter(vac =>
            String(vac.user_id) === String(workerId)
        );
        renderVacaciones(filtered);
    }

    /**
     * Cambiar estado de vacación
     */
    async function cambiarEstadoVacacion(vacacionId, accion) {
        if (!vacacionId) return;

        const endpoint = accion === 'aprobar'
            ? '/vacaciones/aprobar'
            : '/vacaciones/rechazar';

        try {
            const res = await fetch(apiUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify({ vacacion_id: vacacionId })
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'No se pudo actualizar la solicitud');
            }

            alert('Solicitud actualizada correctamente');

            // Recargar vacaciones
            if (elements.sedeDetalleWrapper && elements.sedeDetalleWrapper.style.display !== 'none') {
                const allVacaciones = state.sedeCurrentVacaciones;
                if (allVacaciones.length > 0) {
                    const sedeId = allVacaciones[0].sede_id;
                    if (sedeId) {
                        const urlVacaciones = new URL(apiUrl + 'admin/vacaciones');
                        urlVacaciones.searchParams.set('sede_id', sedeId);
                        const resVacaciones = await fetch(urlVacaciones.toString(), { method: 'GET' });
                        const dataVacaciones = await resVacaciones.json();
                        if (resVacaciones.ok && dataVacaciones.success) {
                            state.sedeCurrentVacaciones = dataVacaciones.vacaciones || [];
                            window.CHEAdmin.sedeCurrentVacaciones = state.sedeCurrentVacaciones;
                            renderVacaciones(dataVacaciones.vacaciones || []);
                        }
                    }
                }
            }
        } catch (e) {
            alert('Error: ' + (e.message || 'Error al actualizar la solicitud.'));
        }
    }

    /**
     * Volver a sedes desde detalle
     */
    function volverASedesDesdeDetalle() {
        if (!elements.sedesBody || !elements.sedeDetalleWrapper) return;
        elements.sedeDetalleWrapper.style.display = 'none';
        elements.sedesBody.style.display = 'grid';
        if (elements.sedesTitle) elements.sedesTitle.style.display = '';
        if (elements.sedesToolbar) elements.sedesToolbar.style.display = '';
    }

    /**
     * Mostrar trabajadores de una sede
     */
    async function mostrarWorkersDeSede(sedeId) {
        if (!elements.sedeWorkersBody || !elements.sedeWorkersWrapper || !elements.sedesBody) return;

        const allUsers = await cargarTodosLosWorkers();
        const users = allUsers.filter(u => String(u.sede_id) === String(sedeId));

        elements.sedeWorkersBody.innerHTML = '';

        if (!users.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.textContent = 'No hay trabajadores en esta sede.';
            tr.appendChild(td);
            elements.sedeWorkersBody.appendChild(tr);
        } else {
            users.forEach(user => {
                const roles = Array.isArray(user.roles) ? user.roles.join(', ') : '';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        ${user.avatar ? `<img src="${user.avatar}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : ''}
                    </td>
                    <td>${user.user_login || ''}</td>
                    <td>${user.display_name || ''}</td>
                    <td>${user.user_email || ''}</td>
                    <td>${roles}</td>
                `;
                elements.sedeWorkersBody.appendChild(tr);
            });
        }

        const cardTitle = elements.sedesBody
            .querySelector(`.che-admin-sede-ver-workers[data-sede-id="${sedeId}"]`)
            ?.closest('.che-sede-card')
            ?.querySelector('.che-sede-card__title')
            ?.textContent || 'Sede';

        if (elements.sedeWorkersTitleEl) {
            elements.sedeWorkersTitleEl.textContent = `Trabajadores de ${cardTitle}`;
        }

        elements.sedesBody.style.display = 'none';
        elements.sedeWorkersWrapper.style.display = 'block';
        if (elements.sedesTitle) elements.sedesTitle.style.display = 'none';
        if (elements.sedesToolbar) elements.sedesToolbar.style.display = 'none';
    }

    /**
     * Volver a sedes desde workers
     */
    function volverASedes() {
        if (!elements.sedesBody || !elements.sedeWorkersWrapper) return;
        elements.sedeWorkersWrapper.style.display = 'none';
        elements.sedesBody.style.display = 'grid';
        if (elements.sedesTitle) elements.sedesTitle.style.display = '';
        if (elements.sedesToolbar) elements.sedesToolbar.style.display = '';
    }

    /**
     * Abrir modal de sede
     */
    function abrirModalSede() {
        if (!elements.createSedeWrapper) return;
        elements.createSedeForm?.reset();
        setMessage(elements.sedeMsgBox, '');
        elements.createSedeWrapper.classList.add('is-open');
    }

    /**
     * Cerrar modal de sede
     */
    function cerrarModalSede() {
        if (!elements.createSedeWrapper) return;
        elements.createSedeWrapper.classList.remove('is-open');
    }

    /**
     * Crear sede
     */
    async function crearSede(event) {
        event.preventDefault();
        setMessage(elements.sedeMsgBox, '');

        const formData = new FormData(elements.createSedeForm);
        const payload = {
            nombre: formData.get('nombre') || '',
            direccion: formData.get('direccion') || '',
            cp: formData.get('cp') || '',
            encargado: formData.get('encargado') || '',
            telefono: formData.get('telefono') || '',
        };

        if (!payload.nombre) {
            setMessage(elements.sedeMsgBox, 'El nombre es obligatorio.', true);
            return;
        }

        try {
            const res = await fetch(apiUrl + 'admin/sedes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al crear la sede');
            }

            renderSedes(data.sedes || {});

            if (window.CHEAdmin && typeof window.CHEAdmin.rellenarSelectsSede === 'function') {
                window.CHEAdmin.rellenarSelectsSede(data.sedes || {});
            }

            elements.createSedeForm.reset();
            cerrarModalSede();
        } catch (e) {
            setMessage(elements.sedeMsgBox, e.message || 'Error inesperado al crear la sede.', true);
        }
    }

    /**
     * Eliminar sede
     */
    async function eliminarSede(sedeId, cardElement) {
        if (!sedeId) return;

        const confirmar = window.confirm('¿Seguro que quieres eliminar esta sede? Esta acción no se puede deshacer.');
        if (!confirmar) return;

        try {
            const res = await fetch(apiUrl + 'admin/sedes/eliminar', {
                method: 'POST', // o 'DELETE' según implementemos la ruta
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify({ sede_id: sedeId })
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al eliminar la sede');
            }

            // Quitar la tarjeta del DOM
            if (cardElement && cardElement.parentNode) {
                cardElement.parentNode.removeChild(cardElement);
            }

            alert('Sede eliminada correctamente.');
        } catch (e) {
            alert(e.message || 'Error inesperado al eliminar la sede.');
        }
    }


    /**
     * Registrar event listeners
     */
    function registerEventListeners() {
        // Click en cards de sedes
        if (elements.sedesBody) {
            state.eventHandlers.sedesBodyClick = function(e) {
                const btnWorkers = e.target.closest('.che-admin-sede-ver-workers');
                if (btnWorkers) {
                    const sedeId = btnWorkers.dataset.sedeId;
                    if (sedeId) mostrarWorkersDeSede(sedeId);
                    return;
                }

                const btnDetalle = e.target.closest('.che-admin-sede-ver-detalle');
                if (btnDetalle) {
                    const sedeId = btnDetalle.dataset.sedeId;
                    if (sedeId) mostrarDetalleSede(sedeId);
                    return;
                }
                 const btnDelete = e.target.closest('.che-admin-sede-delete-btn');
                if (btnDelete) {
                    const sedeId = btnDelete.dataset.sedeId;
                    const card = btnDelete.closest('.che-sede-card');
                    if (sedeId) eliminarSede(sedeId, card);
                    return;
                }
            };
            elements.sedesBody.addEventListener('click', state.eventHandlers.sedesBodyClick);
        }

        // Botones de navegación
        if (elements.backWorkersBtn) {
            state.eventHandlers.backWorkers = volverASedes;
            elements.backWorkersBtn.addEventListener('click', state.eventHandlers.backWorkers);
        }

        if (elements.backDetalleBtn) {
            state.eventHandlers.backDetalle = volverASedesDesdeDetalle;
            elements.backDetalleBtn.addEventListener('click', state.eventHandlers.backDetalle);
        }

        // Modal
        if (elements.newSedeBtn) {
            state.eventHandlers.openModal = abrirModalSede;
            elements.newSedeBtn.addEventListener('click', state.eventHandlers.openModal);
        }

        if (elements.closeSedeBtn) {
            state.eventHandlers.closeModal = cerrarModalSede;
            elements.closeSedeBtn.addEventListener('click', state.eventHandlers.closeModal);
        }

        if (elements.cancelSedeBtn) {
            state.eventHandlers.cancelModal = cerrarModalSede;
            elements.cancelSedeBtn.addEventListener('click', state.eventHandlers.cancelModal);
        }

        if (elements.createSedeWrapper) {
            state.eventHandlers.backdropClick = function(e) {
                if (e.target.classList.contains('che-modal__backdrop')) {
                    cerrarModalSede();
                }
            };
            elements.createSedeWrapper.addEventListener('click', state.eventHandlers.backdropClick);
        }

        // Formulario
        if (elements.createSedeForm) {
            state.eventHandlers.formSubmit = crearSede;
            elements.createSedeForm.addEventListener('submit', state.eventHandlers.formSubmit);
        }

        // Filtros
        if (elements.trabajadoresFilter) {
            state.eventHandlers.filterTrabajadores = filterTrabajadores;
            elements.trabajadoresFilter.addEventListener('input', state.eventHandlers.filterTrabajadores);
        }

        if (elements.partesFilter) {
            state.eventHandlers.filterPartes = filterPartes;
            elements.partesFilter.addEventListener('change', state.eventHandlers.filterPartes);
        }

        if (elements.vacacionesFilter) {
            state.eventHandlers.filterVacaciones = filterVacaciones;
            elements.vacacionesFilter.addEventListener('change', state.eventHandlers.filterVacaciones);
        }

        // Acciones de vacaciones (delegación global)
        state.eventHandlers.vacacionesActions = async function(e) {
            if (e.target.classList.contains('che-btn-aprobar-vac')) {
                const vacacionId = e.target.dataset.vacacionId;
                await cambiarEstadoVacacion(vacacionId, 'aprobar');
            }
            if (e.target.classList.contains('che-btn-rechazar-vac')) {
                const vacacionId = e.target.dataset.vacacionId;
                await cambiarEstadoVacacion(vacacionId, 'rechazar');
            }
        };
        document.addEventListener('click', state.eventHandlers.vacacionesActions);
    }

    /**
     * Remover event listeners
     */
    function removeEventListeners() {
        if (elements.sedesBody && state.eventHandlers.sedesBodyClick) {
            elements.sedesBody.removeEventListener('click', state.eventHandlers.sedesBodyClick);
        }

        if (elements.backWorkersBtn && state.eventHandlers.backWorkers) {
            elements.backWorkersBtn.removeEventListener('click', state.eventHandlers.backWorkers);
        }

        if (elements.backDetalleBtn && state.eventHandlers.backDetalle) {
            elements.backDetalleBtn.removeEventListener('click', state.eventHandlers.backDetalle);
        }

        if (elements.newSedeBtn && state.eventHandlers.openModal) {
            elements.newSedeBtn.removeEventListener('click', state.eventHandlers.openModal);
        }

        if (elements.closeSedeBtn && state.eventHandlers.closeModal) {
            elements.closeSedeBtn.removeEventListener('click', state.eventHandlers.closeModal);
        }

        if (elements.cancelSedeBtn && state.eventHandlers.cancelModal) {
            elements.cancelSedeBtn.removeEventListener('click', state.eventHandlers.cancelModal);
        }

        if (elements.createSedeWrapper && state.eventHandlers.backdropClick) {
            elements.createSedeWrapper.removeEventListener('click', state.eventHandlers.backdropClick);
        }

        if (elements.createSedeForm && state.eventHandlers.formSubmit) {
            elements.createSedeForm.removeEventListener('submit', state.eventHandlers.formSubmit);
        }

        if (elements.trabajadoresFilter && state.eventHandlers.filterTrabajadores) {
            elements.trabajadoresFilter.removeEventListener('input', state.eventHandlers.filterTrabajadores);
        }

        if (elements.partesFilter && state.eventHandlers.filterPartes) {
            elements.partesFilter.removeEventListener('change', state.eventHandlers.filterPartes);
        }

        if (elements.vacacionesFilter && state.eventHandlers.filterVacaciones) {
            elements.vacacionesFilter.removeEventListener('change', state.eventHandlers.filterVacaciones);
        }

        if (state.eventHandlers.vacacionesActions) {
            document.removeEventListener('click', state.eventHandlers.vacacionesActions);
        }
    }

    /**
     * INICIALIZAR MÓDULO
     */
    function init() {

        initElements();
        registerEventListeners();
        cargarSedesListado();
    }

    /**
     * DESTRUIR MÓDULO
     */
    function destroy() {

        removeEventListeners();

        // Limpiar estado
        state.todosLosWorkers = null;
        state.sedeCurrentWorkers = [];
        state.sedeCurrentPartes = [];
        state.sedeCurrentVacaciones = [];
        state.eventHandlers = {};
        elements = {};
    }

    // Exponer el módulo
    window.CHEModules.sedes = {
        init: init,
        destroy: destroy
    };

})();