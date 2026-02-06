// ============================================
// MÓDULO DE VACACIONES
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

    // Estado del módulo
    let state = {
        eventHandlers: {}
    };

    // Referencias a elementos DOM
    let elements = {};

    /**
     * Inicializar referencias a elementos DOM
     */
    function initElements() {
        elements = {
            msgBox: document.getElementById('che-admin-vacaciones-message'),
            listContainer: document.getElementById('che-admin-vacaciones-body'), // tbody de la tabla
            sedeSelect: document.getElementById('che-admin-vacaciones-sede'),
            estadoFilter: document.getElementById('che-admin-vacaciones-estado-filter'),
            searchInput: document.getElementById('che-admin-vacaciones-search'),
            searchBtn: document.getElementById('che-admin-vacaciones-search-btn')
        };
    }

    function setMessage(text, isError = false) {
        if (!elements.msgBox) return;
        elements.msgBox.textContent = text;
        elements.msgBox.style.color = isError ? 'red' : 'green';
    }

    function renderVacaciones(vacaciones) {
        if (!elements.listContainer) return;
        
        elements.listContainer.innerHTML = '';

        if (!vacaciones || !vacaciones.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 8;
            td.textContent = 'No hay solicitudes de vacaciones.';
            td.style.textAlign = 'center';
            tr.appendChild(td);
            elements.listContainer.appendChild(tr);
            return;
        }

        // Ordenar: pendientes primero, luego aprobados/rechazados
        const sortedVacaciones = [...vacaciones].sort((a, b) => {
            const estadoA = (a.estado || 'pendiente').toLowerCase();
            const estadoB = (b.estado || 'pendiente').toLowerCase();
            
            if (estadoA === 'pendiente' && estadoB !== 'pendiente') return -1;
            if (estadoA !== 'pendiente' && estadoB === 'pendiente') return 1;
            return 0;
        });

        sortedVacaciones.forEach(v => {
            const tr = document.createElement('tr');
            tr.dataset.vacacionId = v.ID;
            
            const estado = (v.estado || 'pendiente').toLowerCase();
            let estadoClass = 'vac-badge--pendiente';
            if (estado === 'aprobado') {
                estadoClass = 'vac-badge--aprobado';
            } else if (estado === 'rechazado') {
                estadoClass = 'vac-badge--rechazado';
            }
            
            const isDisabled = estado !== 'pendiente';

            tr.innerHTML = `
                <td>${v.ID}</td>
                <td>${v.worker_name || ''}</td>
                <td>${v.sede_nombre || ''}</td>
                <td>${v.fecha_inicio || ''}</td>
                <td>${v.fecha_fin || ''}</td>
                <td>${v.dias_totales || 0}</td>
                <td><span class="vac-badge ${estadoClass}">${v.estado || ''}</span></td>
                <td>
                    <button type="button" class="all-btn che-admin-vac-aprobar success" title="Validar"
                        ${isDisabled ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                        Validar
                    </button>
                    <button type="button" class="all-btn che-admin-vac-rechazar danger" title="Rechazar"
                        ${isDisabled ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                        Rechazar
                    </button>
                </td>
            `;

            elements.listContainer.appendChild(tr);
        });
    }

    async function cargarVacaciones() {
        // Usar datos iniciales de PHP si están disponibles y no hay filtro por sede
        const sedeId = elements.sedeSelect?.value || '';
        
        if (!sedeId && window.CHEInitialData && window.CHEInitialData.vacaciones) {
            renderVacaciones(window.CHEInitialData.vacaciones);
            setMessage('');
            return;
        }

        // Si hay filtro, hacer petición a la API
        setMessage('Cargando solicitudes...');
        const params = new URLSearchParams();
        if (sedeId) params.set('sede_id', sedeId);

        try {
            const res = await fetch(apiUrl + 'admin/vacaciones?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                }
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al cargar vacaciones');
            }
            renderVacaciones(data.vacaciones || []);
            setMessage('');
        } catch (e) {
            setMessage(e.message || 'Error al cargar vacaciones.', true);
            renderVacaciones([]);
        }
    }

    async function cambiarEstadoVacacion(vacacionId, accion, comentario = '') {
        if (!vacacionId) return;
        const endpoint = accion === 'aprobar'
            ? 'admin/vacaciones/aprobar'
            : 'admin/vacaciones/rechazar';

        try {
            const bodyData = { vacacion_id: vacacionId };
            if (accion === 'rechazar' && comentario) {
                bodyData.motivo = comentario;
            }

            const res = await fetch(apiUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify(bodyData)
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'No se pudo actualizar la solicitud');
            }

            // Actualizar el estado en window.CHEInitialData.vacaciones
            if (window.CHEInitialData && window.CHEInitialData.vacaciones) {
                // Convertir ambos valores a tipo número para asegurar la comparación correcta
                const vacacion = window.CHEInitialData.vacaciones.find(v => Number(v.ID) === Number(vacacionId));
                if (vacacion) {
                    vacacion.estado = accion === 'aprobar' ? 'aprobado' : 'rechazado';
                }
            }

            setMessage('Solicitud actualizada.');
            await cargarVacaciones();
        } catch (e) {
            setMessage(e.message || 'Error al actualizar la solicitud.', true);
        }
    }

    /**
     * Mostrar modal para rechazar con comentario
     */
    function mostrarModalRechazar(vacacionId) {
        const modal = document.getElementById('che-modal-rechazar-vacacion');
        const textarea = document.getElementById('che-vacacion-comentario-rechazo');
        const btnConfirmar = document.getElementById('che-modal-rechazar-confirmar');
        const btnCancelar = document.getElementById('che-modal-rechazar-cancelar');
        const btnClose = modal.querySelector('.che-modal-close');

        if (!modal) return;

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
                cambiarEstadoVacacion(vacacionId, 'rechazar', comentario);
            };
        }

        if (btnCancelar) {
            btnCancelar.onclick = cerrarModal;
        }

        if (btnClose) {
            btnClose.onclick = cerrarModal;
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = (event) => {
            if (event.target === modal) {
                cerrarModal();
            }
        };
    }

    /**
     * Filtrar vacaciones por sede, estado y búsqueda
     */
    async function filtrarVacaciones() {
        const sedeId = elements.sedeSelect?.value || '';
        const estado = elements.estadoFilter?.value || '';
        const searchTerm = (elements.searchInput?.value || '').toLowerCase();

        const rows = elements.listContainer ? Array.from(elements.listContainer.querySelectorAll('tr')) : [];

        let visibleCount = 0;
        rows.forEach(row => {
            const vacId = row.dataset.vacacionId;
            if (!vacId) return;

            // Aplicar filtros
            let mostrar = true;

            // Filtro por sede
            if (sedeId) {
                const sede = row.querySelector('td:nth-child(3)')?.textContent || '';
                const rowSedeId = row.dataset.sedeId;
                if (rowSedeId && String(rowSedeId) !== String(sedeId)) {
                    mostrar = false;
                }
            }

            // Filtro por estado
            if (mostrar && estado) {
                const estadoSpan = row.querySelector('.vac-badge');
                const rowEstado = (estadoSpan?.textContent || '').toLowerCase();
                if (rowEstado !== estado.toLowerCase()) {
                    mostrar = false;
                }
            }

            // Filtro por búsqueda (nombre o email)
            if (mostrar && searchTerm) {
                const nombre = (row.querySelector('td:nth-child(2)')?.textContent || '').toLowerCase();
                if (!nombre.includes(searchTerm)) {
                    mostrar = false;
                }
            }

            row.style.display = mostrar ? '' : 'none';
            if (mostrar) visibleCount++;
        });
    }

    /**
     * Exportar vacaciones a CSV
     */
    function exportVacacionesToCSV() {
        const vacacionesVisibles = obtenerVacacionesVisibles();
        
        if (!vacacionesVisibles || vacacionesVisibles.length === 0) {
            alert('No hay datos para exportar');
            return;
        }

        const headers = [
            'ID',
            'Trabajador',
            'Empresa',
            'Fecha Inicio',
            'Fecha Fin',
            'Dias Totales',
            'Estado'
        ];

        const rows = vacacionesVisibles.map(vac => {
            const diasTotales = vac.dias_totales || '0';
            return [
                '="' + (vac.ID || '') + '"', // ID como texto alineado a la izquierda
                vac.worker_name || '',
                vac.sede_nombre || '',
                vac.fecha_inicio || '',
                vac.fecha_fin || '',
                '="' + diasTotales + '"',
                vac.estado || ''
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
        link.setAttribute('download', `vacaciones-${new Date().getTime()}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Obtener vacaciones visibles aplicando los filtros actuales
     */
    function obtenerVacacionesVisibles() {
        const tbody = elements.listContainer;
        if (!tbody) return [];
        
        const rows = tbody.querySelectorAll('tr');
        const vacacionesVisibles = [];
        
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const vacId = row.dataset.vacacionId;
                const cells = row.querySelectorAll('td');
                
                if (cells.length >= 7) {
                    vacacionesVisibles.push({
                        ID: cells[0].textContent.trim(),
                        worker_name: cells[1].textContent.trim(),
                        sede_nombre: cells[2].textContent.trim(),
                        fecha_inicio: cells[3].textContent.trim(),
                        fecha_fin: cells[4].textContent.trim(),
                        dias_totales: cells[5].textContent.trim(),
                        estado: cells[6].textContent.trim()
                    });
                }
            }
        });
        
        return vacacionesVisibles;
    }

    /**
     * Registrar event listeners
     */
    function registerEventListeners() {
        // Botón refrescar
        const refreshBtn = document.getElementById('che-vacaciones-refresh-btn');
        if (refreshBtn) {
            state.eventHandlers.refreshClick = function() {
                try {
                    sessionStorage.setItem('che-current-section', 'vacaciones');
                } catch (e) {
                    // sessionStorage may be unavailable
                }
                location.reload();
            };
            refreshBtn.addEventListener('click', state.eventHandlers.refreshClick);
        }

        // Botón exportar
        const exportBtn = document.getElementById('che-vacaciones-exportar');
        if (exportBtn) {
            state.eventHandlers.exportClick = exportVacacionesToCSV;
            exportBtn.addEventListener('click', state.eventHandlers.exportClick);
        }

        // Filtro por sede
        if (elements.sedeSelect) {
            state.eventHandlers.sedeChange = filtrarVacaciones;
            elements.sedeSelect.addEventListener('change', state.eventHandlers.sedeChange);
        }

        // Filtro por estado
        if (elements.estadoFilter) {
            state.eventHandlers.estadoChange = filtrarVacaciones;
            elements.estadoFilter.addEventListener('change', state.eventHandlers.estadoChange);
        }

        // Botón de búsqueda
        if (elements.searchBtn) {
            state.eventHandlers.searchClick = filtrarVacaciones;
            elements.searchBtn.addEventListener('click', state.eventHandlers.searchClick);
        }

        // Enter en input de búsqueda
        if (elements.searchInput) {
            state.eventHandlers.searchKeyup = function(e) {
                if (e.key === 'Enter') filtrarVacaciones();
            };
            elements.searchInput.addEventListener('keyup', state.eventHandlers.searchKeyup);
        }

        // Botones aprobar/rechazar
        if (elements.listContainer) {
            state.eventHandlers.listClick = function(e) {
                const btnAprobar = e.target.closest('.che-admin-vac-aprobar');
                const btnRechazar = e.target.closest('.che-admin-vac-rechazar');
                const row = e.target.closest('tr');
                const vacId = row?.dataset.vacacionId;

                if (btnAprobar && vacId) {
                    cambiarEstadoVacacion(vacId, 'aprobar');
                } else if (btnRechazar && vacId) {
                    mostrarModalRechazar(vacId);
                }
            };
            elements.listContainer.addEventListener('click', state.eventHandlers.listClick);
        }
    }

    /**
     * Remover event listeners
     */
    function removeEventListeners() {
        if (elements.sedeSelect && state.eventHandlers.sedeChange) {
            elements.sedeSelect.removeEventListener('change', state.eventHandlers.sedeChange);
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

        if (elements.listContainer && state.eventHandlers.listClick) {
            elements.listContainer.removeEventListener('click', state.eventHandlers.listClick);
        }
    }

    /**
     * INICIALIZAR MÓDULO
     */
    function init() {
        initElements();
        registerEventListeners();
        cargarVacaciones();
    }

    /**
     * DESTRUIR MÓDULO
     */
    function destroy() {
        
        removeEventListeners();
        
        // Limpiar estado
        state.eventHandlers = {};
        elements = {};
    }

    // Exponer el módulo
    window.CHEModules.vacaciones = {
        init: init,
        destroy: destroy
    };

})();
