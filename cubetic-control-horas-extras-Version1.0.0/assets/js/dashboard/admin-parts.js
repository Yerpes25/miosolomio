// ============================================
// MÓDULO DE PARTES DE TRABAJO
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
        todosLosPartes: null,
        eventHandlers: {}
    };

    // Referencias a elementos DOM
    let elements = {};

    /**
     * Inicializar referencias a elementos DOM
     */
    function initElements() {
        elements = {
            messageBoxPartes: document.getElementById('che-admin-message'),
            table: document.querySelector('.che-admin-partes-table'),
            tbody: document.getElementById('che-admin-partes-tbody'),
            sedeFilter: document.getElementById('che-admin-sede-filter'),
            estadoFilter: document.getElementById('che-admin-parts-estado-filter'),
            searchInput: document.getElementById('che-admin-parts-search'),
            searchBtn: document.getElementById('che-admin-parts-search-btn'),
            partesWrapper: document.getElementById('che-admin-partes-wrapper'),
            partesTitle: document.getElementById('che-admin-partes-title'),
            modalDetalleParte: document.getElementById('che-modal-detalle-parte'),
            modalDetalleParteBody: document.getElementById('che-modal-detalle-parte-body'),
            modalDetalleParteTitle: document.getElementById('che-modal-detalle-parte-title')
        };
    }

    /**
     * Rellenar selects de sede
     */
    function rellenarSelectsSede(sedes) {
        const selects = [
            elements.sedeFilter,
            elements.userSedeSelect,
            elements.userSedeFilterSelect
        ].filter(Boolean);

        selects.forEach(select => {
            const current = select.value;
            select.innerHTML = '';

            const optDefault = document.createElement('option');
            optDefault.value = '';
            optDefault.textContent = (select === elements.userSedeFilterSelect)
                ? 'Todas las sedes'
                : '— Selecciona sede —';
            select.appendChild(optDefault);

            Object.entries(sedes).forEach(([id, data]) => {
                const opt = document.createElement('option');
                opt.value = id;
                opt.textContent = data.nombre;
                select.appendChild(opt);
            });

            if (current && sedes[current]) {
                select.value = current;
            }
        });

        // Llamar también a la función global
        if (window.CHEAdmin && window.CHEAdmin.rellenarSelectsSede) {
            window.CHEAdmin.rellenarSelectsSede(sedes);
        }
    }

    /**
     * Renderizar partes en la tabla
     */
    function renderPartes(partes) {
        if (!elements.tbody) return;

        elements.tbody.innerHTML = '';

        if (!partes || !partes.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 8;
            td.textContent = 'No hay partes para mostrar.';
            td.style.textAlign = 'center';
            tr.appendChild(td);
            elements.tbody.appendChild(tr);
            return;
        }

        // Ordenar: pendientes primero, luego validados/rechazados
        const sortedPartes = [...partes].sort((a, b) => {
            const estadoA = (a.estado_validacion || 'pendiente').toLowerCase();
            const estadoB = (b.estado_validacion || 'pendiente').toLowerCase();
            
            if (estadoA === 'pendiente' && estadoB !== 'pendiente') return -1;
            if (estadoA !== 'pendiente' && estadoB === 'pendiente') return 1;
            return 0;
        });

        sortedPartes.forEach(parte => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-parte-id', parte.ID);

            const estadoValid = (parte.estado_validacion || 'pendiente').toLowerCase();

            let estadoClass = 'vac-badge--pendiente';
            if (estadoValid === 'validado') {
                estadoClass = 'vac-badge--aprobado';
            } else if (estadoValid === 'rechazado') {
                estadoClass = 'vac-badge--rechazado';
            }
            
            const isDisabled = estadoValid !== 'pendiente';

            tr.innerHTML = `
                <td>${parte.ID}</td>
                <td>${parte.worker_name || ''}</td>
                <td>${parte.sede_nombre || ''}</td>
                <td>${parte.hora_inicio || ''}</td>
                <td>${parte.hora_fin || ''}</td>
                <td>${parte.tiempo_total || ''}</td>
                <td><span class="vac-badge ${estadoClass}">${estadoValid || ''}</span></td>
                <td>
                    <button type="button" class="all-btn che-admin-parte-detalle-btn primary"
                        data-parte-id="${parte.ID}">
                        Ver detalles
                    </button>
                    <button type="button" class="all-btn che-admin-parte-validar-btn success"
                        data-parte-id="${parte.ID}"
                        ${isDisabled ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                        Validar
                    </button>
                    <button type="button" class="all-btn che-admin-parte-rechazar-btn danger"
                        data-parte-id="${parte.ID}"
                        ${isDisabled ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                        Rechazar
                    </button>
                </td>
            `;
            elements.tbody.appendChild(tr);
        });
    }

    /**
     * Cargar y mostrar todos los partes
     */
    function cargarTodosLosPartes() {
        // Usar datos iniciales de PHP si están disponibles
        if (window.CHEInitialData && window.CHEInitialData.partes) {
            state.todosLosPartes = window.CHEInitialData.partes;

            // Si la tabla ya viene renderizada por PHP (filas en el tbody),
            // no reescribimos el contenido para no sobrescribir botones/HTML personalizados.
            const hasServerRows = elements.tbody && elements.tbody.querySelectorAll('tr').length > 0;
            if (!hasServerRows) {
                // Si no hay filas en el DOM, renderizamos desde JS como fallback.
                renderPartes(state.todosLosPartes);
            }

            if (elements.partesTitle) {
                elements.partesTitle.textContent = `Todos los partes (${state.todosLosPartes.length})`;
            }
            return;
        }

        console.error('No hay datos iniciales de partes');
        // Fallback: cargar desde API si no hay datos iniciales
        setMessage(elements.messageBoxPartes, 'Cargando partes...');
    }

    /**
     * Cargar partes desde la API (para refrescar)
     */
    async function cargarPartesDesdeAPI() {
        try {
            const usuarioId = elements.filterUsuario ? elements.filterUsuario.value : '';
            
            const url = usuarioId 
                ? `${apiUrl}admin/partes-por-usuario?usuario_id=${usuarioId}`
                : `${apiUrl}admin/partes-por-usuario`;

            const res = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al cargar partes');
            }

            state.todosLosPartes = data.partes || [];
            if (window.CHEInitialData) {
                window.CHEInitialData.partes = state.todosLosPartes;
            }

            return state.todosLosPartes;
        } catch (error) {
            console.error('[CHE Partes] Error al cargar desde API:', error);
            setMessage(elements.messageBoxPartes, 'Error al recargar partes', true);
            throw error;
        }
    }

    /**
     * Filtrar partes por sede, estado y búsqueda
     */
    function filtrarPartes() {
        if (!state.todosLosPartes) {
            console.error('No hay partes en el estado');
            return;
        }

        const sedeId = elements.sedeFilter?.value || '';
        const estado = elements.estadoFilter?.value || '';
        const searchTerm = (elements.searchInput?.value || '').toLowerCase();

        const rows = elements.tbody ? Array.from(elements.tbody.querySelectorAll('tr')) : [];

        let visibleCount = 0;
        rows.forEach(row => {
            const parteId = row.getAttribute('data-parte-id');
            if (!parteId) return;

            const parte = state.todosLosPartes.find(p => String(p.ID) === String(parteId));
            if (!parte) {
                row.style.display = '';
                visibleCount++;
                return;
            }

            // Aplicar filtros
            let mostrar = true;

            // Filtro por sede
            if (sedeId && String(parte.sede_id) !== String(sedeId)) {
                mostrar = false;
            }

            // Filtro por estado
            if (mostrar && estado) {
                const parteEstado = (parte.estado_validacion || 'pendiente').toLowerCase();
                if (parteEstado !== estado.toLowerCase()) {
                    mostrar = false;
                }
            }

            // Filtro por búsqueda (nombre o email)
            if (mostrar && searchTerm) {
                const nombre = (parte.worker_name || '').toLowerCase();
                const email = (parte.worker_email || '').toLowerCase();
                if (!nombre.includes(searchTerm) && !email.includes(searchTerm)) {
                    mostrar = false;
                }
            }

            row.style.display = mostrar ? '' : 'none';
            if (mostrar) visibleCount++;
        });

        if (elements.partesTitle) {
            elements.partesTitle.textContent = `Partes (${visibleCount})`;
        }
    }

    /**
     * Filtrar partes por sede (mantener para compatibilidad)
     */
    function filtrarPartesPorSede(sedeId) {
        filtrarPartes();
    }

    /**
     * Cargar sedes desde la API
     */
    async function cargarSedes() {
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

            rellenarSelectsSede(data);
        } catch (e) {
            console.error('Error sedes:', e);
        }
    }

    /**
     * Cargar todos los workers (con caché)
     */
    /**
     * Cargar todos los workers (con caché)
     */
    async function cargarTodosLosWorkers() {
        if (state.todosLosWorkers) return state.todosLosWorkers;

        // Primero intentar usar los datos iniciales de PHP
        if (window.CHEInitialData && window.CHEInitialData.workers) {
            state.todosLosWorkers = window.CHEInitialData.workers;
            return state.todosLosWorkers;
        }

        // Fallback: cargar desde API si no hay datos iniciales
        try {
            const url = apiUrl + 'admin/workers';
            const res = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                }
            });
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
     * Renderizar usuarios de una sede
     */
    function renderUsuariosDeSede(users) {
        if (!elements.usuariosSedeBox) return;

        elements.usuariosSedeBox.innerHTML = '';

        if (!users || !users.length) {
            elements.usuariosSedeBox.textContent = 'No hay usuarios en esta sede.';
            elements.usuariosSedeBox.style.display = 'block';
            return;
        }

        const table = document.createElement('table');
        table.className = 'che-admin-table che-admin-users-table--sede';

        table.innerHTML = `
            <thead>
                <tr>
                    <th></th>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;

        const tbody = table.querySelector('tbody');

        users.forEach(user => {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>
                    ${user.avatar ? `<img src="${user.avatar}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : ''}
                </td>
                <td>${user.user_login || ''}</td>
                <td>${user.display_name || ''}</td>
                <td>${user.user_email || ''}</td>
                <td>
                    <button type="button"
                            id="all-btn"
                            class="che-admin-usuario-sede-item"
                            data-user-id="${user.ID}">
                        Mostrar partes
                    </button>
                </td>
            `;

            tbody.appendChild(tr);
        });

        elements.usuariosSedeBox.appendChild(table);
        elements.usuariosSedeBox.style.display = 'block';
    }

    /**
     * Cargar usuarios por sede
     */
    async function cargarUsuariosPorSede(sede) {
        try {
            setMessage(elements.messageBoxPartes, 'Cargando usuarios de la sede...');

            const allUsers = await cargarTodosLosWorkers();
            const users = allUsers.filter(u => String(u.sede_id) === String(sede));

            renderUsuariosDeSede(users);
            setMessage(elements.messageBoxPartes, '');
        } catch (e) {
            setMessage(elements.messageBoxPartes, e.message, true);
        }
    }

    /**
     * Cargar partes por usuario
     */
    async function cargarPartesPorUsuario(userId) {
        try {
            setMessage(elements.messageBoxPartes, 'Cargando partes del usuario...');

            const url = new URL(apiUrl + 'admin/partes-por-usuario');
            url.searchParams.set('user_id', userId);

            const res = await fetch(url.toString(), { method: 'GET' });
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al cargar partes');
            }

            if (!elements.table || !elements.table.tBodies[0]) return;

            const tbody = elements.table.tBodies[0];
            tbody.innerHTML = '';

            if (!data.partes || !data.partes.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 7;
                td.textContent = 'No hay partes para este usuario.';
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else {
                data.partes.forEach(parte => {
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-parte-id', parte.ID);

                    const estadoValid = parte.estado_validacion || 'pendiente';

                    tr.innerHTML = `
                        <td>${parte.ID}</td>
                        <td>${parte.worker_name || ''}</td>
                        <td>${parte.hora_inicio || ''}</td>
                        <td>${parte.hora_fin || ''}</td>
                        <td>${parte.tiempo_total || ''}</td>
                        <td>${estadoValid}</td>
                        <td>
                            <button class="che-accion-mas-info" id="all-btn" data-parte="${parte.ID}">Más info</button>
                            <button class="che-accion-validar" id="all-btn" data-parte="${parte.ID}">Validar</button>
                            <button class="che-accion-rechazar" id="all-btn" data-parte="${parte.ID}">Rechazar</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            if (elements.partesTitle && data.partes && data.partes.length) {
                const nombreTrab = data.partes[0].worker_name || '';
                elements.partesTitle.textContent = nombreTrab
                    ? `Partes de ${nombreTrab}`
                    : 'Partes del trabajador';
            }

            if (elements.partesWrapper) elements.partesWrapper.style.display = 'block';
            if (elements.sedeFilter) elements.sedeFilter.style.display = 'none';

            setMessage(elements.messageBoxPartes, '');
        } catch (e) {
            setMessage(elements.messageBoxPartes, e.message, true);
            if (elements.partesWrapper) elements.partesWrapper.style.display = 'block';
            if (elements.sedeFilter) elements.sedeFilter.style.display = 'none';
        }
    }

    /**
     * Mostrar detalle de un parte en modal
     */
    async function mostrarDetallesParte(parteId) {
        if (!elements.modalDetalleParte || !elements.modalDetalleParteBody) return;

        try {
            const url = new URL(apiUrl + 'admin/parte-detalle');
            url.searchParams.set('parte_id', parteId);

            const res = await fetch(url.toString(), { method: 'GET' });
            const data = await res.json();

            if (!res.ok || !data.success || !data.parte) {
                throw new Error(data.error || 'No se pudo cargar el detalle del parte');
            }

            const p = data.parte;
            const adjuntos = Array.isArray(p.adjuntos) ? p.adjuntos : [];

            let adjuntosHtml = '<p style="color: #666;">No hay adjuntos.</p>';
            if (adjuntos.length) {
                adjuntosHtml = `
                    <div class="che-parte-adjuntos-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                        ${adjuntos.map(item => {
                            const url = item.url || '#';
                            const title = item.title || item.filename || 'Archivo';
                            const isImg = !!item.is_image;

                            if (isImg) {
                                return `
                                    <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #f9f9f9;">
                                        <a href="${url}" target="_blank" rel="noopener noreferrer" style="display: block;">
                                            <img src="${url}" alt="${title}" style="width: 100%; height: 150px; object-fit: cover;">
                                        </a>
                                        <div style="padding: 10px;">
                                            <div style="font-size: 12px; margin-bottom: 5px; color: #333;">${title}</div>
                                            <a href="${url}" download style="color: #2271b1; text-decoration: none; font-size: 12px;">⬇ Descargar</a>
                                        </div>
                                    </div>
                                `;
                            }

                            const ext = (item.filename || '').split('.').pop().toLowerCase();
                            let icon = '📄';
                            if (ext === 'pdf') icon = '📋';

                            return `
                                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9; display: flex; align-items: center; gap: 10px;">
                                    <div style="font-size: 32px;">${icon}</div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 12px; margin-bottom: 5px; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${title}</div>
                                        <div style="display: flex; gap: 10px;">
                                            <a href="${url}" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: none; font-size: 12px;">Ver</a>
                                            <a href="${url}" download style="color: #2271b1; text-decoration: none; font-size: 12px;">Descargar</a>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            }

            elements.modalDetalleParteBody.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                    <div class="che-form-group">
                        <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Trabajador</label>
                        <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">${p.worker_name || 'N/A'}</div>
                    </div>
                    <div class="che-form-group">
                        <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">ID del parte</label>
                        <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">${p.ID}</div>
                    </div>
                    <div class="che-form-group">
                        <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Hora inicio</label>
                        <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">${p.hora_inicio || p.post_date || 'N/A'}</div>
                    </div>
                    <div class="che-form-group">
                        <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Hora fin</label>
                        <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">${p.hora_fin || 'N/A'}</div>
                    </div>
                    <div class="che-form-group">
                        <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Tiempo total</label>
                        <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">${p.tiempo_total || 'N/A'}</div>
                    </div>
                    <div class="che-form-group">
                        <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Estado de validación</label>
                        <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">
                            <span style="padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; 
                                ${p.estado_validacion === 'validado' ? 'background: #d4edda; color: #155724;' : 
                                  p.estado_validacion === 'rechazado' ? 'background: #f8d7da; color: #721c24;' : 
                                  'background: #fff3cd; color: #856404;'}">
                                ${p.estado_validacion || 'pendiente'}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="che-form-group" style="margin-bottom: 20px;">
                    <label style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">Proyecto</label>
                    <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">${p.proyecto || 'N/A'}</div>
                </div>

                <div class="che-form-group">
                    <label style="font-weight: 600; color: #333; display: block; margin-bottom: 10px;">📎 Archivos adjuntos</label>
                    ${adjuntosHtml}
                </div>
            `;

            if (elements.modalDetalleParteTitle) {
                elements.modalDetalleParteTitle.textContent = `Detalles del Parte #${p.ID}`;
            }

            // Mostrar modal
            elements.modalDetalleParte.style.display = 'block';

            // Cerrar modal con botones
            const btnClose = elements.modalDetalleParte.querySelector('.che-modal-close');
            const btnCerrar = elements.modalDetalleParte.querySelector('.che-modal-detalle-parte-cerrar');

            const cerrarModal = () => {
                elements.modalDetalleParte.style.display = 'none';
            };

            if (btnClose) btnClose.onclick = cerrarModal;
            if (btnCerrar) btnCerrar.onclick = cerrarModal;

            // Cerrar al hacer clic fuera del modal
            elements.modalDetalleParte.onclick = (e) => {
                if (e.target === elements.modalDetalleParte) {
                    cerrarModal();
                }
            };

        } catch (e) {
            console.error('[CHE Partes] Error al cargar detalle:', e);
            alert('Error al cargar los detalles del parte: ' + e.message);
        }
    }

    /**
     * Volver al listado de partes desde detalle
     */
    function volverAListadoPartes() {
        if (elements.parteDetalleWrapper && elements.partesWrapper) {
            elements.parteDetalleWrapper.style.display = 'none';
            elements.partesWrapper.style.display = 'block';
        }
    }

    /**
     * Volver a usuarios desde partes
     */
    function volverAUsuariosDesdePartes() {
        if (!elements.usuariosSedeBox || !elements.partesWrapper) return;

        elements.partesWrapper.style.display = 'none';
        elements.usuariosSedeBox.style.display = 'block';

        if (elements.partesTitle) elements.partesTitle.textContent = 'Partes pendientes de validar';
        if (elements.sedeFilter) elements.sedeFilter.style.display = '';
    }

    /**
     * Enviar acción (validar/rechazar)
     */
    async function sendAccion(parteId, accion, comentario = '') {
        try {
            setMessage(elements.messageBoxPartes, '');

            const payload = { parte_id: parteId };
            
            // Si es rechazar y hay comentario, incluirlo
            if (accion === 'rechazar' && comentario) {
                payload.comentario = comentario;
            }

            const res = await fetch(apiUrl + 'parte/' + accion, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al ' + accion + ' parte');
            }
            
            // Actualizar el estado en window.CHEInitialData.partes
            if (window.CHEInitialData && window.CHEInitialData.partes) {
                const parte = window.CHEInitialData.partes.find(p => Number(p.ID) === Number(parteId));
                if (parte) {
                    parte.estado_validacion = accion === 'validar' ? 'validado' : 'rechazado';
                }
            }
            
            // Actualizar estado local
            if (state.todosLosPartes) {
                const parte = state.todosLosPartes.find(p => Number(p.ID) === Number(parteId));
                if (parte) {
                    parte.estado_validacion = accion === 'validar' ? 'validado' : 'rechazado';
                }
            }

            renderPartes(state.todosLosPartes);

            setMessage(elements.messageBoxPartes, 'Parte ' + parteId + ' ' + accion + ' correctamente.');
        } catch (e) {
            setMessage(elements.messageBoxPartes, e.message, true);
        }
    }

    /**
     * Validar parte
     */
    async function validarParte(parteId) {
        await sendAccion(parteId, 'validar');
    }

    /**
     * Rechazar parte - Ahora abre modal para pedir motivo
     */
    async function rechazarParte(parteId) {
        mostrarModalRechazarParte(parteId);
    }

    /**
     * Mostrar modal para rechazar con comentario
     */
    function mostrarModalRechazarParte(parteId) {
        const modal = document.getElementById('che-modal-rechazar-parte');
        const textarea = document.getElementById('che-parte-comentario-rechazo');
        const btnConfirmar = document.getElementById('che-modal-rechazar-parte-confirmar');
        const btnCancelar = document.getElementById('che-modal-rechazar-parte-cancelar');
        const btnClose = modal ? modal.querySelector('.che-modal-close') : null;

        if (!modal) {
            console.error('[CHE Partes] Modal de rechazo no encontrado');
            return;
        }

        // Limpiar textarea
        if (textarea) textarea.value = '';

        // Mostrar modal
        modal.style.display = 'block';

        // Función para cerrar modal
        const cerrarModal = () => {
            modal.style.display = 'none';
            btnConfirmar.onclick = null;
            btnCancelar.onclick = null;
            if (btnClose) btnClose.onclick = null;
        };

        // Configurar botones
        if (btnConfirmar) {
            btnConfirmar.onclick = () => {
                const comentario = textarea ? textarea.value.trim() : '';
                if (!comentario) {
                    alert('Por favor, indica el motivo del rechazo');
                    return;
                }
                cerrarModal();
                sendAccion(parteId, 'rechazar', comentario);
            };
        }

        if (btnCancelar) {
            btnCancelar.onclick = cerrarModal;
        }

        if (btnClose) {
            btnClose.onclick = cerrarModal;
        }

        // Cerrar modal al hacer clic fuera
        modal.onclick = (e) => {
            if (e.target === modal) {
                cerrarModal();
            }
        };
    }

    /**
     * Exportar partes a CSV
     */
    function exportPartesToCSV() {
        if (!state.todosLosPartes || state.todosLosPartes.length === 0) {
            alert('No hay datos para exportar');
            return;
        }
        
        const partesVisibles = obtenerPartesVisibles();
        
        if (!partesVisibles || partesVisibles.length === 0) {
            alert('No hay partes visibles con los filtros aplicados');
            return;
        }

        const headers = [
            'ID',
            'Trabajador',
            'Empresa',
            'Fecha Inicio',
            'Fecha Fin',
            'Horas',
            'Estado',
            'Notas'
        ];

        const rows = partesVisibles.map(parte => {
            // Formatear fechas - obtener desde la tabla renderizada si es posible
            let fechaInicio = '';
            let fechaFin = '';
            
            // Buscar la fila en la tabla para obtener el valor renderizado
            const row = elements.tbody?.querySelector(`tr[data-parte-id="${parte.ID}"]`);
            if (row) {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 6) {
                    fechaInicio = cells[3].textContent.trim();
                    fechaFin = cells[4].textContent.trim();
                }
            }
            
            // Fallback: intentar formatear desde los datos
            if ((!fechaInicio || fechaInicio === '') && parte.hora_inicio) {
                fechaInicio = parte.hora_inicio;
                if (fechaInicio.includes('T') || fechaInicio.includes('-')) {
                    const date = new Date(fechaInicio);
                    if (!isNaN(date.getTime())) {
                        fechaInicio = date.toLocaleDateString('es-ES', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit'
                        });
                    }
                }
            }
            
            if ((!fechaFin || fechaFin === '') && parte.hora_fin) {
                fechaFin = parte.hora_fin;
                if (fechaFin.includes('T') || fechaFin.includes('-')) {
                    const date = new Date(fechaFin);
                    if (!isNaN(date.getTime())) {
                        fechaFin = date.toLocaleDateString('es-ES', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit'
                        });
                    }
                }
            }
            
            // Si las fechas de la tabla tienen hora, extraer solo la fecha
            if (fechaInicio && (fechaInicio.includes(':') || fechaInicio.includes('/'))) {
                // Intentar parsear y quedarse solo con la fecha
                const parts = fechaInicio.split(/[\s,]+/);
                if (parts.length > 0) {
                    fechaInicio = parts[0]; // Solo la parte de fecha
                }
            }
            
            if (fechaFin && (fechaFin.includes(':') || fechaFin.includes('/'))) {
                const parts = fechaFin.split(/[\s,]+/);
                if (parts.length > 0) {
                    fechaFin = parts[0];
                }
            }
            
            return [
                '="' + (parte.ID || '') + '"', // ID como texto alineado a la izquierda
                parte.worker_name || '',
                parte.sede_nombre || '',
                fechaInicio,
                fechaFin,
                '="' + (parte.tiempo_total || '') + '"', // Horas como texto alineado a la izquierda
                parte.estado || parte.estado_validacion || '',
                (parte.notas && parte.notas.trim()) ? parte.notas.replace(/\n/g, ' ') : '------'
            ];
        });

        let csv = headers.join(';') + '\n';
        rows.forEach(row => {
            csv += row.join(';') + '\n';
        });

        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `partes-${new Date().getTime()}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Obtener partes visibles aplicando los filtros actuales
     */
    function obtenerPartesVisibles() {
        if (!state.todosLosPartes) return [];
        
        let partesFiltrados = [...state.todosLosPartes];
        
        // Filtrar por sede
        const sedeSeleccionada = elements.sedeFilter ? elements.sedeFilter.value : '';
        if (sedeSeleccionada) {
            partesFiltrados = partesFiltrados.filter(p => String(p.sede_id) === String(sedeSeleccionada));
        }
        
        // Filtrar por estado
        const estadoSeleccionado = elements.estadoFilter ? elements.estadoFilter.value : '';
        if (estadoSeleccionado) {
            partesFiltrados = partesFiltrados.filter(p => (p.estado_validacion || '').toLowerCase() === estadoSeleccionado.toLowerCase());
        }
        
        // Filtrar por búsqueda
        const busqueda = elements.searchInput ? elements.searchInput.value.toLowerCase().trim() : '';
        if (busqueda) {
            partesFiltrados = partesFiltrados.filter(p => {
                const nombre = (p.worker_name || '').toLowerCase();
                const email = (p.worker_email || '').toLowerCase();
                return nombre.includes(busqueda) || email.includes(busqueda);
            });
        }
        
        return partesFiltrados;
    }



    /**
     * Registrar event listeners
     */
    function registerEventListeners() {
        // Botón refrescar
        const refreshBtn = document.getElementById('che-partes-refresh-btn');
        if (refreshBtn) {
            state.eventHandlers.refreshClick = function() {
                try {
                    sessionStorage.setItem('che-current-section', 'partes');
                } catch (e) {
                    // sessionStorage may be unavailable in some contexts
                }
                location.reload();
            };
            refreshBtn.addEventListener('click', state.eventHandlers.refreshClick);
        }

        // Botón exportar
        const exportBtn = document.getElementById('che-partes-exportar');
        if (exportBtn) {
            state.eventHandlers.exportClick = exportPartesToCSV;
            exportBtn.addEventListener('click', state.eventHandlers.exportClick);
        }

        // Filtro por sede
        if (elements.sedeFilter) {
            state.eventHandlers.sedeChange = function() {
                filtrarPartes();
            };
            elements.sedeFilter.addEventListener('change', state.eventHandlers.sedeChange);
        }

        // Filtro por estado
        if (elements.estadoFilter) {
            state.eventHandlers.estadoChange = function() {
                filtrarPartes();
            };
            elements.estadoFilter.addEventListener('change', state.eventHandlers.estadoChange);
        }

        // Botón de búsqueda
        if (elements.searchBtn) {
            state.eventHandlers.searchClick = function() {
                filtrarPartes();
            };
            elements.searchBtn.addEventListener('click', state.eventHandlers.searchClick);
        }

        // Enter en input de búsqueda
        if (elements.searchInput) {
            state.eventHandlers.searchKeyup = function(e) {
                if (e.key === 'Enter') filtrarPartes();
            };
            elements.searchInput.addEventListener('keyup', state.eventHandlers.searchKeyup);
        }

        // Click en botones de la tabla de partes
        // Usar tbody si existe, sino usar table como contenedor
        const clickContainer = elements.tbody || (elements.table ? elements.table.querySelector('tbody') : null);
        
        if (clickContainer) {
            state.eventHandlers.tbodyClick = function(e) {
                // Botones generados por JS
                const btnMasInfo = e.target.closest('.che-accion-mas-info');
                const btnValidar = e.target.closest('.che-accion-validar');
                const btnRechazar = e.target.closest('.che-accion-rechazar');

                // Botones generados por PHP/template
                const altMasInfo = e.target.closest('.che-admin-parte-detalle-btn');
                const altValidar = e.target.closest('.che-admin-parte-validar-btn');
                const altRechazar = e.target.closest('.che-admin-parte-rechazar-btn');

                if (btnMasInfo || altMasInfo) {
                    const parteId = btnMasInfo ? btnMasInfo.dataset.parte : altMasInfo.dataset.parteId;
                    if (parteId) mostrarDetallesParte(parteId);
                } else if (btnValidar || altValidar) {
                    const parteId = btnValidar ? btnValidar.dataset.parte : altValidar.dataset.parteId;
                    if (parteId) validarParte(parteId);
                } else if (btnRechazar || altRechazar) {
                    const parteId = btnRechazar ? btnRechazar.dataset.parte : altRechazar.dataset.parteId;
                    if (parteId) rechazarParte(parteId);
                }
            };
            clickContainer.addEventListener('click', state.eventHandlers.tbodyClick);
        }

        // Volver de partes a usuarios (si existe el botón)
        if (elements.backUsersFromPartesBtn) {
            state.eventHandlers.backUsersFromPartes = volverAUsuariosDesdePartes;
            elements.backUsersFromPartesBtn.addEventListener('click', state.eventHandlers.backUsersFromPartes);
        }
    }
    function removeEventListeners() {
        if (elements.sedeFilter && state.eventHandlers.sedeChange) {
            elements.sedeFilter.removeEventListener('change', state.eventHandlers.sedeChange);
        }

        if (elements.estadoFilter && state.eventHandlers.estadoChange) {
            elements.estadoFilter.removeEventListener('change', state.eventHandlers.estadoChange);
        }

        if (elements.searchBtn && state.eventHandlers.searchClick) {
            elements.searchBtn.removeEventListener('click', state.eventHandlers.searchClick);
        }

        if (elements.searchInput && state.eventHandlers.searchKeyup) {
            elements.searchInput.removeEventListener('keyup', state.eventHandlers.searchKeyup);
        }

        if (elements.usuariosSedeBox && state.eventHandlers.usuarioSedeClick) {
            elements.usuariosSedeBox.removeEventListener('click', state.eventHandlers.usuarioSedeClick);
        }

        if (elements.backUsersFromPartesBtn && state.eventHandlers.backUsersFromPartes) {
            elements.backUsersFromPartesBtn.removeEventListener('click', state.eventHandlers.backUsersFromPartes);
        }

        if (elements.table && state.eventHandlers.tableClick) {
            elements.table.removeEventListener('click', state.eventHandlers.tableClick);
        }
    }

    /**
     * INICIALIZAR MÓDULO
     */
    function init() {

        initElements();
        registerEventListeners();
        cargarTodosLosPartes(); // Cargar todos los partes al inicio
    }

    /**
     * DESTRUIR MÓDULO
     */
    function destroy() {

        removeEventListeners();

        // Limpiar estado
        state.todosLosWorkers = null;
        state.todosLosPartes = null;
        state.eventHandlers = {};
        elements = {};
    }

    // Exponer el módulo
    window.CHEModules.partes = {
        init: init,
        destroy: destroy
    };

})();
