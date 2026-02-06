// ============================================
// MÓDULO VACACIONES (worker-vacaciones.js)
// ============================================
(function () {
    'use strict';

    if (!window.CHEWorker) return;
    window.CHEWorkerModules = window.CHEWorkerModules || {};

    const apiUrl = window.CHEWorker.apiUrl || window.cheAjax?.api_url || '/wp-json/che/v1/';

    let state = {
        eventHandlers: {}
    };

    let elements = {};

    function initElements() {
        elements = {
            msgBox: document.getElementById('che-worker-vacaciones-message'),
            inicioEl: document.getElementById('che-vacaciones-inicio'),
            finEl: document.getElementById('che-vacaciones-fin'),
            solicitarBtn: document.getElementById('che-vacaciones-solicitar-btn'),
            listEl: document.getElementById('che-worker-vacaciones-list')
        };
    }

    function setMessage(text, isError = false) {
        if (!elements.msgBox) return;
        elements.msgBox.textContent = text;
        elements.msgBox.style.color = isError ? 'red' : 'green';
    }

    function renderVacaciones(vacaciones) {
        if (!elements.listEl) return;

        elements.listEl.innerHTML = '';

        if (!vacaciones || !vacaciones.length) {
            const empty = document.createElement('div');
            empty.className = 'che-vacacion-card che-vacacion-card--empty';
            empty.textContent = 'No tienes solicitudes de vacaciones.';
            elements.listEl.appendChild(empty);
            return;
        }

        vacaciones.forEach(v => {
            const card = document.createElement('div');
            card.className = 'che-vacacion-card';

            const estado = v.estado || 'pendiente';
            const estadoClass = `che-vacacion-card__estado--${estado}`;

            card.innerHTML = `
                <div class="che-vacacion-card__header">
                    <span>Del ${v.fecha_inicio} al ${v.fecha_fin}</span>
                    <span class="che-vacacion-card__estado ${estadoClass}">
                        ${estado}
                    </span>
                </div>
                <div class="che-vacacion-card__body">
                    <p><strong>Días:</strong> ${v.dias_totales}</p>
                </div>
            `;

            elements.listEl.appendChild(card);
        });
    }

    async function cargarVacaciones() {
        if (!elements.listEl) return;

        setMessage('Cargando solicitudes...');
        try {
            const res  = await fetch(apiUrl + 'trabajador/vacaciones', {
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
            setMessage(e.message || 'Error al cargar vacaciones', true);
            renderVacaciones([]);
        }
    }

    async function solicitarVacaciones() {
        const inicio = elements.inicioEl?.value || '';
        const fin    = elements.finEl?.value || '';

        if (!inicio || !fin) {
            setMessage('Debes indicar fecha de inicio y fin.', true);
            return;
        }
        if (fin < inicio) {
            setMessage('La fecha fin no puede ser anterior al inicio.', true);
            return;
        }

        setMessage('Enviando solicitud...');

        try {
            const res  = await fetch(apiUrl + 'trabajador/vacaciones/solicitar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify({
                    fecha_inicio: inicio,
                    fecha_fin: fin
                })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'No se pudo crear la solicitud');
            }

            setMessage('Solicitud enviada correctamente.');
            if (elements.inicioEl) elements.inicioEl.value = '';
            if (elements.finEl)    elements.finEl.value = '';
            await cargarVacaciones();
        } catch (e) {
            setMessage(e.message || 'Error al enviar la solicitud.', true);
        }
    }

    function registerEventListeners() {
        if (elements.solicitarBtn) {
            state.eventHandlers.solicitarClick = solicitarVacaciones;
            elements.solicitarBtn.addEventListener('click', state.eventHandlers.solicitarClick);
        }

        state.eventHandlers.sectionChange = (e) => {
            if (e.detail?.sectionId === 'vacaciones') {
                cargarVacaciones();
            }
        };
        document.addEventListener('che-worker-section-change', state.eventHandlers.sectionChange);
    }

    function removeEventListeners() {
        if (elements.solicitarBtn && state.eventHandlers.solicitarClick) {
            elements.solicitarBtn.removeEventListener('click', state.eventHandlers.solicitarClick);
        }
        if (state.eventHandlers.sectionChange) {
            document.removeEventListener('che-worker-section-change', state.eventHandlers.sectionChange);
        }
    }

    // Contador para evitar bucle infinito
    let initAttempts = 0;
    const MAX_INIT_ATTEMPTS = 5;

    function init() {
        console.log('[CHE Worker Vacaciones] Inicializando módulo de vacaciones...');
        initAttempts++;
        
        // Esperar un momento para asegurar que el DOM está listo
        setTimeout(function() {
            initElements();
            
            // Verificar que los elementos existan
            if (!elements.listEl) {
                if (initAttempts < MAX_INIT_ATTEMPTS) {
                    console.warn('[CHE Worker Vacaciones] No se encontró el elemento listEl, reintentando... (' + initAttempts + '/' + MAX_INIT_ATTEMPTS + ')');
                    // Reintentar después de otro momento
                    setTimeout(init, 200);
                    return;
                } else {
                    console.error('[CHE Worker Vacaciones] No se encontró el elemento listEl después de ' + MAX_INIT_ATTEMPTS + ' intentos. Verifica que la plantilla worker-view-vacaciones.php existe.');
                    return;
                }
            }
            
            // Resetear contador si encontró los elementos
            initAttempts = 0;
            
            console.log('[CHE Worker Vacaciones] Elementos encontrados:', {
                listEl: !!elements.listEl,
                solicitarBtn: !!elements.solicitarBtn,
                inicioEl: !!elements.inicioEl,
                finEl: !!elements.finEl,
                msgBox: !!elements.msgBox
            });

            // Registrar listeners de eventos
            registerEventListeners();

            // Cargar siempre la primera vez que se entra en la sección
            cargarVacaciones();
        }, 100);
    }

    function destroy() {
        removeEventListeners();
        elements = {};
        state.eventHandlers = {};
        initAttempts = 0; // Resetear contador al destruir
    }

    window.CHEWorkerModules.vacaciones = {
        init,
        destroy
    };
})();

