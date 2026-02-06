// ============================================
// MÃ“DULO DE CONTABILIDAD
// Sistema de tarjetas de empresas y tabla de trabajadores
// ============================================
(function () {
    'use strict';

    // Namespace para mÃ³dulos
    window.CHEModules = window.CHEModules || {};

    // Variables globales de WordPress
    const apiUrl = window.cheAjax?.api_url || '';
    const nonce = window.cheAjax?.nonce || '';
    const ajaxUrl = window.cheAjax?.ajax_url || '';

    // Estado del mÃ³dulo
    let state = {
        currentSedeId: null,
        currentMonth: new Date().toISOString().slice(0, 7), // YYYY-MM
        sedesData: {},
        workersData: [],
        eventHandlers: {},
        debitoHandlersAttached: false,  // Flag para evitar re-agregar listeners
        eventListenersRegistered: false,  // Flag para evitar duplicar event listeners
        // Referencias a elementos y handlers
        exportBtn: null,
        exportAsesBtn: null,
        searchInput: null,
        filterBtn: null
    };

    /**
     * Cargar y renderizar datos de sedes (tarjetas)
     */
    async function loadSedesCards() {
        const sedesGrid = document.getElementById('che-contabilidad-sedes-grid');
        if (!sedesGrid) return;

        try {
            const formData = new FormData();
            formData.append('action', 'che_get_sedes_accounting_summary');

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success && data.data) {
                state.sedesData = data.data;
                renderSedesCards(data.data);
            }
        } catch (error) {
            console.error('Error cargando sedes:', error);
        }
    }

    /**
     * Renderizar tarjetas de sedes
     */
    function renderSedesCards(sedesData) {
        const sedesGrid = document.getElementById('che-contabilidad-sedes-grid');
        if (!sedesGrid) return;

        // Limpiar contenedor
        const existingCards = sedesGrid.querySelectorAll('.che-contabilidad-sede-card');
        existingCards.forEach(card => {
            const sedeId = card.dataset.sedeId;
            if (sedesData[sedeId]) {
                // Actualizar datos de la tarjeta existente
                const workersCount = card.querySelector('.che-sede-workers-count');
                const budgetForecast = card.querySelector('.che-sede-budget-forecast');

                if (workersCount) {
                    workersCount.textContent = sedesData[sedeId].workers_count || 0;
                }
                if (budgetForecast) {
                    budgetForecast.textContent = formatCurrency(sedesData[sedeId].budget_forecast || 0);
                }
            }
        });
    }

    /**
     * Ver trabajadores de una sede
     */
    function viewWorkersBySede(sedeId) {
        state.currentSedeId = sedeId;
        const sedeName = state.sedesData[sedeId]?.nombre || 'Empresa';

        const sedesView = document.getElementById('che-contabilidad-sedes-view');
        const workersView = document.getElementById('che-contabilidad-workers-view');

        if (sedesView && workersView) {
            sedesView.style.display = 'none';
            workersView.style.display = 'block';

            const title = document.getElementById('che-contabilidad-workers-title');
            if (title) {
                title.textContent = 'Trabajadores de ' + sedeName;
            }
        }

        loadWorkersTable();
    }

    /**
     * Volver a vista de sedes
     */
    function backToSedes() {
        state.currentSedeId = null;

        const sedesView = document.getElementById('che-contabilidad-sedes-view');
        const workersView = document.getElementById('che-contabilidad-workers-view');

        if (sedesView && workersView) {
            sedesView.style.display = 'block';
            workersView.style.display = 'none';
        }
    }

    /**
     * Cargar tabla de trabajadores
     */
    async function loadWorkersTable() {
        const tbody = document.getElementById('che-contabilidad-tbody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Cargando trabajadores...</td></tr>';

        try {
            const formData = new FormData();
            formData.append('action', 'che_get_workers_accounting');
            formData.append('sede_id', state.currentSedeId || '');
            formData.append('month', state.currentMonth);

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success && data.data) {
                state.workersData = data.data;
                renderWorkersTable(data.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay trabajadores disponibles</td></tr>';
            }
        } catch (error) {
            console.error('Error cargando trabajadores:', error);
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Error al cargar trabajadores</td></tr>';
        }
    }

    /**
     * Filtrar sedes por nombre
     */
    function filterSedesByName() {
        const searchInput = document.getElementById('che-contabilidad-buscar-sedes');
        const searchTerm = searchInput?.value.toLowerCase() || '';

        const sedesGrid = document.getElementById('che-contabilidad-sedes-grid');
        if (!sedesGrid) return;

        const cards = sedesGrid.querySelectorAll('.che-contabilidad-sede-card');
        cards.forEach(card => {
            const titleElement = card.querySelector('.che-contabilidad-sede-card-header h4');
            const name = titleElement?.textContent.toLowerCase() || '';
            
            if (name.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    /**
     * Filtrar trabajadores por nombre
     */
    function filterWorkersByName() {
        const searchInput = document.getElementById('che-contabilidad-buscar');
        const searchTerm = searchInput?.value.toLowerCase() || '';

        const tbody = document.getElementById('che-contabilidad-tbody');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            const nameCell = row.querySelector('td:first-child');
            const name = nameCell?.textContent.toLowerCase() || '';
            
            if (name.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    /**
     * Renderizar tabla de trabajadores
     */
    function renderWorkersTable(workers) {
        const tbody = document.getElementById('che-contabilidad-tbody');
        if (!tbody) return;

        if (!workers || workers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay trabajadores disponibles</td></tr>';
            return;
        }

        tbody.innerHTML = '';

        workers.forEach(worker => {

                /*Calculo de la nomina total con horas extras y debito*/

                const brutoTotal = worker.nomina + worker.horas_extras_cost;
                const netoCalculado = brutoTotal * (1 - (worker.irpf_percent / 100));
                const total = netoCalculado - worker.debito;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="text-align: left;">${escapeHtml(worker.display_name)}</td>
                <td style="text-align: center;">${formatCurrency(worker.nomina)}</td>
                <td style="text-align: center;">${worker.irpf_percent.toFixed(2)}%</td>
                <td style="text-align: center;">${formatCurrency(netoCalculado)}</td> 
                <td style="text-align: center;">${formatCurrency(worker.horas_extras_cost)}</td>
                <td style="text-align: center;">${formatCurrency(worker.debito)}</td>
                <td style="text-align: center;">${formatCurrency(total)}</td>
                <td style="text-align: center;">
                    <button type="button" class="che-edit-worker-btn che-btn-compact che-btn-trabajadores" data-worker-id="${worker.worker_id}">
                        Tarifas
                    </button>
                    <button type="button" class="che-debito-worker-btn che-btn-compact che-btn-trabajadores" data-worker-id="${worker.worker_id}">
                        Débito
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });

        // Vincular eventos de ediciÃ³n
        document.querySelectorAll('.che-edit-worker-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = this.dataset.workerId;
                openEditModal(workerId);
            });
        });

        // Vincular eventos de dÃ©bito por trabajador
        document.querySelectorAll('.che-debito-worker-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = this.dataset.workerId;
                openDebitoModalForWorker(workerId);
            });
        });
    }

    /**
     * Abrir modal de ediciÃ³n
     */
    function openEditModal(workerId) {
        const worker = state.workersData.find(w => w.worker_id == workerId);
        if (!worker) return;

        const modal = document.getElementById('che-modal-tarifas');
        const workerName = document.getElementById('che-modal-worker-name');
        const nominaInput = document.getElementById('che-nomina-total');
        const irpfInput = document.getElementById('che-retencion-irpf');
        const tarifaNormalInput = document.getElementById('che-tarifa-normal');
        const tarifaExtraInput = document.getElementById('che-tarifa-extra');
        const tarifaFestivoInput = document.getElementById('che-tarifa-extra-festivo');

        if (modal && workerName) {
            workerName.textContent = worker.display_name || worker.user_email;
            nominaInput.value = worker.nomina || 1200;
            irpfInput.value = worker.irpf_percent || 15;
            tarifaNormalInput.value = worker.tarifa_semana || 15;
            tarifaExtraInput.value = worker.tarifa_extra || 20;
            tarifaFestivoInput.value = worker.tarifa_festivo || 20;

            modal.setAttribute('data-worker-id', workerId);
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }

    /**
     * Cerrar modal
     */
    function closeModal() {
        const modal = document.getElementById('che-modal-tarifas');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            modal.removeAttribute('data-worker-id');
        }
    }

    /**
     * Abrir modal de dÃ©bito para un trabajador especÃ­fico
     */
    function openDebitoModalForWorker(workerId) {
        const modal = document.getElementById('che-modal-debito');
        const input = document.getElementById('che-debito-global-input');
        const workerNameSpan = document.getElementById('che-modal-debito-worker-name');
        const worker = state.workersData.find(w => w.worker_id == workerId || w.user_id == workerId);
        
        if (modal && worker) {
            if (workerNameSpan) {
                workerNameSpan.textContent = worker.display_name || worker.user_email;
            }
            input.value = worker.debito || '';
            modal.setAttribute('data-worker-id', workerId);
            modal.style.display = 'flex';
            modal.classList.add('active');
            setTimeout(() => input.focus(), 100);
        }
    }

    /**
     * Cerrar modal de d\u00e9bito
     */
    function closeDebitoModal() {
        const modal = document.getElementById('che-modal-debito');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    }

    /**
     * Handler para cerrar modal de d\u00e9bito al hacer click fuera
     */
    function handleDebitoModalClick(e) {
        if (e.target.id === 'che-modal-debito') {
            closeDebitoModal();
        }
    }

    /**
     * Aplicar d\u00e9bito global a todos los trabajadores
     */
    async function applyDebitoGlobal() {
        const input = document.getElementById('che-debito-global-input');
        const modal = document.getElementById('che-modal-debito');
        const workerId = modal?.getAttribute('data-worker-id');
        const debito = parseFloat(input.value) || 0;

        if (debito < 0) {
            console.error('El débito no puede ser negativo');
            return;
        }

        if (!workerId) {
            console.error('No se pudo identificar al trabajador');
            return;
        }

        const worker = state.workersData.find(w => w.worker_id == workerId || w.user_id == workerId);
        if (!worker) {
            console.error('Trabajador no encontrado');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'che_save_worker_tarifas');
            formData.append('worker_id', workerId);
            formData.append('debito', debito);
            formData.append('nomina', worker.nomina || 1200);
            formData.append('irpf', worker.irpf_percent || 15);
            formData.append('tarifa_semana', worker.tarifa_semana || 15);
            formData.append('tarifa_extra', worker.tarifa_extra || 20);
            formData.append('tarifa_festivo', worker.tarifa_festivo || 20);

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                closeDebitoModal();
                loadWorkersTable();
                alert(`Débito de €${debito.toFixed(2)} aplicado correctamente`);
            } else {
                console.error('Error:', result);
            }
        } catch (error) {
            console.error('Error al aplicar débito:', error);
        }
    }

    /**
     * Guardar tarifas del trabajador
     */
    async function saveTarifas(workerId) {
        const nominaInput = document.getElementById('che-nomina-total');
        const irpfInput = document.getElementById('che-retencion-irpf');
        const tarifaNormalInput = document.getElementById('che-tarifa-normal');
        const tarifaExtraInput = document.getElementById('che-tarifa-extra');
        const tarifaFestivoInput = document.getElementById('che-tarifa-extra-festivo');

        const data = {
            action: 'che_save_worker_tarifas',
            worker_id: workerId,
            nomina: parseFloat(nominaInput.value) || 1200,
            irpf: parseFloat(irpfInput.value) || 15,
            tarifa_semana: parseFloat(tarifaNormalInput.value) || 15,
            tarifa_extra: parseFloat(tarifaExtraInput.value) || 20,
            tarifa_festivo: parseFloat(tarifaFestivoInput.value) || 20
        };

        try {
            const formData = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, value);
            });

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                closeModal();
                // Recargar tabla para mostrar cambios
                loadWorkersTable();
                alert('Tarifas guardadas correctamente');
            } else {
                alert('Error: ' + (result.data || 'No se pudieron guardar las tarifas'));
            }
        } catch (error) {
            console.error('Error al guardar tarifas:', error);
            alert('Error al guardar tarifas');
        }
    }

    /**
     * Aplicar filtros
     */
    function applyFilters() {
        // Simplemente recargar la tabla con los datos actuales
        loadWorkersTable();
    }

    /**
     * Establecer fechas por defecto (21 del mes anterior al 20 del siguiente)
     */
    function setDefaultDates() {
        const today = new Date();
        let startDate, endDate;

        // Si estamos antes del dÃ­a 21, el perÃ­odo comienza el 21 del mes anterior
        if (today.getDate() < 21) {
            // Ir al mes anterior, dÃ­a 21
            const prevMonth = new Date(today.getFullYear(), today.getMonth() - 1, 21);
            startDate = prevMonth;
            // El fin es el 20 del mes actual
            endDate = new Date(today.getFullYear(), today.getMonth(), 20);
        } else {
            // El perÃ­odo comienza hoy (o el 21 del mes actual)
            startDate = new Date(today.getFullYear(), today.getMonth(), 21);
            // El fin es el 20 del prÃ³ximo mes
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 20);
        }

        // Formatear fechas como YYYY-MM-DD
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        const startInput = document.getElementById('che-contabilidad-fecha-inicio');
        const endInput = document.getElementById('che-contabilidad-fecha-fin');

        if (startInput) startInput.value = formatDate(startDate);
        if (endInput) endInput.value = formatDate(endDate);
    }

    /**
     * Formatear moneda
     */
    function formatCurrency(value) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(value || 0);
    }

    /**
     * Escapar HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Exportar datos a CSV
     */
    function exportToCSV() {
        const tbody = document.getElementById('che-contabilidad-tbody');
        const tableRows = tbody ? tbody.querySelectorAll('tr') : [];
        
        if (!state.workersData || state.workersData.length === 0 || tableRows.length === 0) {
            alert('No hay datos para exportar. Asegúrate de seleccionar una empresa primero.');
            return;
        }

        // ==== CAMBIO DE HEADERS (orden como la plantilla larga) ====
        // NOMBRE | SALARIO | H.EXTR | TOTAL | DEBITO | NETO | RETE
        const headers = [
            'Trabajador',
            'SALARIO',
            'H.EXTR',
            'TOTAL',
            'DEBITO',
            'NETO',
            'RETE'
        ];

        const rows = state.workersData.map(worker => {
            const brutoTotal = worker.nomina + worker.horas_extras_cost;
            const netoCalculado = brutoTotal * (1 - (worker.irpf_percent / 100));
            const total = netoCalculado - worker.debito;
            return [
                // ==== MISMO ORDEN QUE headers ====
                worker.display_name || worker.user_email,                 // Trabajador
                '="' + worker.nomina.toFixed(2) + ' €"',                    // SALARIO
                '="' + worker.horas_extras_cost.toFixed(2) + ' €"',         // H.EXTR
                '="' + total.toFixed(2) + ' €"',                            // TOTAL
                '="' + worker.debito.toFixed(2) + ' €"',                    // DEBITO
                '="' + netoCalculado.toFixed(2) + ' €"',                    // NETO
                '="' + worker.irpf_percent.toFixed(2) + ' %"',             // RETE
            ];
        });

        // Calcular totales (solo columnas numéricas, índices 1-6)
        const totals = [''];
        for (let i = 1; i < 6; i++) {
            const sum = state.workersData.reduce((acc, worker) => {
                const brutoTotal = worker.nomina + worker.horas_extras_cost;
                const netoCalculado = brutoTotal * (1 - (worker.irpf_percent / 100));
                const total = netoCalculado - worker.debito;

                if (i === 1) return acc + worker.nomina;             // SALARIO
                if (i === 2) return acc + worker.horas_extras_cost;  // H.EXTR
                if (i === 3) return acc + total;                     // TOTAL
                if (i === 4) return acc + worker.debito;             // DEBITO
                if (i === 5) return acc + netoCalculado;             // NETO
                return acc;
            }, 0);
            totals.push('="' + sum.toFixed(2) + ' €"');
        }

        // Crear CSV
        let csv = headers.join(';') + '\n';
        rows.forEach(row => {
            csv += row.join(';') + '\n';
        });
        
        // Añadir dos saltos de línea antes de los totales
        csv += '\n';
        csv += totals.join(';') + '\n';

        // Crear blob y descargar
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `contabilidad-${state.currentMonth}-${new Date().getTime()}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }


    /**
     * Exportar datos de Asesoría a CSV (4 campos: SALARIO, HORAS EXTRAS, PAGO TOTAL, RETENCIÓN)
     */
    function exportToCSVAsesoria() {
        const tbody = document.getElementById('che-contabilidad-tbody');
        const tableRows = tbody ? tbody.querySelectorAll('tr') : [];
        
        if (!state.workersData || state.workersData.length === 0 || tableRows.length === 0) {
            alert('No hay datos para exportar. Asegúrate de seleccionar una empresa primero.');
            return;
        }

        // ==== CAMBIO DE HEADERS (orden como la plantilla corta) ====
        // NOMBRE | SALARIO | H.EXTR | TOTAL | RETE
        const headers = [
            'Trabajador',
            'SALARIO',
            'H.EXTR',
            'TOTAL',
            'RETENCION'
        ];

        const rows = state.workersData.map(worker => {
            const brutoTotal = worker.nomina + worker.horas_extras_cost;
            const netoCalculado = brutoTotal * (1 - (worker.irpf_percent / 100));
            const total = netoCalculado - worker.debito;
            return [
                // MISMO ORDEN QUE headers
                worker.display_name || worker.user_email,                 // Trabajador
                '="' + worker.nomina.toFixed(2) + ' €"',                    // SALARIO
                '="' + worker.horas_extras_cost.toFixed(2) + ' €"',         // H.EXTR
                '="' + total.toFixed(2) + ' €"',                            // TOTAL
                '="' + worker.irpf_percent.toFixed(2) + ' %"',              // RETENCION
            ];
        });

        // Calcular totales (solo columnas numéricas, índices 1-4)
        const totals = [''];
        for (let i = 1; i < 4; i++) {
            const sum = state.workersData.reduce((acc, worker) => {
                const brutoTotal = worker.nomina + worker.horas_extras_cost;
                const netoCalculado = brutoTotal * (1 - (worker.irpf_percent / 100));
                const total = netoCalculado - worker.debito;

                if (i === 1) return acc + worker.nomina;             // SALARIO
                if (i === 2) return acc + worker.horas_extras_cost;  // H.EXTR
                if (i === 3) return acc + total;                     // TOTAL
                return acc;
            }, 0);
            totals.push('="' + sum.toFixed(2) + ' €"');
        }

        // Crear CSV
        let csv = headers.join(';') + '\n';
        rows.forEach(row => {
            csv += row.join(';') + '\n';
        });

        // Añadir dos saltos de línea antes de los totales
        csv += '\n';
        csv += totals.join(';') + '\n';

        // Crear blob y descargar
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `asesoria-${state.currentMonth}-${new Date().getTime()}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Setup de listeners del modal de débito - Solo se ejecuta una vez
     */
    function setupDebitoModalListeners() {
        const debitoModalClose = document.querySelector('#che-modal-debito .che-modal-close');
        const debitoModalCancel = document.getElementById('che-modal-debito-cancelar');
        const debitoModalApply = document.getElementById('che-modal-debito-aplicar');
        const debitoModal = document.getElementById('che-modal-debito');

        // Usar dataset flags para evitar duplicados
        if (debitoModalClose && !debitoModalClose.dataset.listenerAttached) {
            debitoModalClose.addEventListener('click', closeDebitoModal);
            debitoModalClose.dataset.listenerAttached = 'true';
        }
        if (debitoModalCancel && !debitoModalCancel.dataset.listenerAttached) {
            debitoModalCancel.addEventListener('click', closeDebitoModal);
            debitoModalCancel.dataset.listenerAttached = 'true';
        }
        if (debitoModalApply && !debitoModalApply.dataset.listenerAttached) {
            debitoModalApply.addEventListener('click', applyDebitoGlobal);
            debitoModalApply.dataset.listenerAttached = 'true';
        }
        if (debitoModal && !debitoModal.dataset.listenerAttached) {
            debitoModal.addEventListener('click', handleDebitoModalClick);
            debitoModal.dataset.listenerAttached = 'true';
        }
    }

    /**
     * Registrar event listeners
     */
    function registerEventListeners() {
        // Botones de ver trabajadores por sede
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('che-contabilidad-view-workers-btn')) {
                const sedeId = e.target.dataset.sedeId;
                viewWorkersBySede(sedeId);
            }
        });

        // Filtro de bÃºsqueda de sedes
        const searchSedesInput = document.getElementById('che-contabilidad-buscar-sedes');
        if (searchSedesInput) {
            searchSedesInput.addEventListener('keyup', filterSedesByName);
        }

        // BotÃ³n volver a sedes
        const backBtn = document.getElementById('che-contabilidad-back-to-sedes');
        if (backBtn) {
            backBtn.addEventListener('click', backToSedes);
        }

        // Los campos de fecha ya no tienen un select asociado, simplemente establecer fechas por defecto
        setDefaultDates();

        // Filtro de bÃºsqueda por nombre
        state.searchInput = document.getElementById('che-contabilidad-buscar');
        if (state.searchInput) {
            state.searchInput.addEventListener('keyup', filterWorkersByName);
        }

        // Aplicar filtros
        state.filterBtn = document.getElementById('che-contabilidad-aplicar-filtros');
        if (state.filterBtn) {
            state.filterBtn.addEventListener('click', applyFilters);
        }

        // Exportar datos - usar flag para evitar mÃºltiples listeners
        state.exportBtn = document.getElementById('che-contabilidad-exportar');
        if (state.exportBtn && !state.exportBtn.dataset.listenerAttached) {
            state.exportBtn.addEventListener('click', exportToCSV);
            state.exportBtn.dataset.listenerAttached = 'true';
        }

        // Exportar datos de asesorÃ­a - usar flag para evitar mÃºltiples listeners
        state.exportAsesBtn = document.getElementById('che-contabilidad-exportar-asesoria');
        if (state.exportAsesBtn && !state.exportAsesBtn.dataset.listenerAttached) {
            state.exportAsesBtn.addEventListener('click', exportToCSVAsesoria);
            state.exportAsesBtn.dataset.listenerAttached = 'true';
        }

        // Modal de tarifas
        const modalClose = document.querySelector('#che-modal-tarifas .che-modal-close');
        const modalCancel = document.getElementById('che-modal-cancelar');
        const modalSave = document.getElementById('che-modal-guardar-tarifas');
        const modal = document.getElementById('che-modal-tarifas');

        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }
        if (modalCancel) {
            modalCancel.addEventListener('click', closeModal);
        }
        if (modalSave) {
            modalSave.addEventListener('click', function() {
                const modal = document.getElementById('che-modal-tarifas');
                const workerId = modal?.getAttribute('data-worker-id');
                if (workerId) {
                    saveTarifas(workerId);
                }
            });
        }
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }
    }

    /**
     * INICIALIZAR moduloo
     */
    function init() {
        setDefaultDates();
        loadSedesCards();
        setupDebitoModalListeners();  // Agregar listeners solo una vez
        
        // Solo registrar event listeners una vez
        if (!state.eventListenersRegistered) {
            registerEventListeners();
            state.eventListenersRegistered = true;
        }
    }

    /**
     * DESTRUIR Modulo  - Limpiar estado y remover listeners si es necesario
     */
    function destroy() {
        // Remover event listeners
        if (state.searchInput) {
            state.searchInput.removeEventListener('keyup', filterWorkersByName);
        }
        if (state.filterBtn) {
            state.filterBtn.removeEventListener('click', applyFilters);
        }
        if (state.exportBtn) {
            state.exportBtn.removeEventListener('click', exportToCSV);
            delete state.exportBtn.dataset.listenerAttached;
        }
        if (state.exportAsesBtn) {
            state.exportAsesBtn.removeEventListener('click', exportToCSVAsesoria);
            delete state.exportAsesBtn.dataset.listenerAttached;
        }
        
        // Reset estado
        state.currentSedeId = null;
        state.currentMonth = new Date().toISOString().slice(0, 7);
        state.sedesData = {};
        state.workersData = [];
        state.eventListenersRegistered = false;
        state.exportBtn = null;
        state.exportAsesBtn = null;
        state.searchInput = null;
        state.filterBtn = null;
    }

    // Exponer el modelo en el namespace global
    window.CHEModules.contabilidad = {
        init: init,
        destroy: destroy
    };

})();




