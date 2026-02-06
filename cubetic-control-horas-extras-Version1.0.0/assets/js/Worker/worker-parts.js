(function () {
    const apiUrl       = window.cheAjax?.api_url || '/wp-json/che/v1/';
    const partesTbody  = document.getElementById('che-worker-partes-body');
    const partesMsgBox = document.getElementById('che-worker-partes-message');

    if (!partesTbody) return;

    /*
     * Renderiza la lista de partes del trabajador
    */
    function renderWorkerPartes(partes) {
        partesTbody.innerHTML = '';

        if (!partes || !partes.length) {
            const empty = document.createElement('div');
            empty.className = 'che-parte-card che-parte-card--empty';
            empty.textContent = 'No tienes partes registrados.';
            partesTbody.appendChild(empty);
            return;
        }

        partes.forEach(parte => {
            const card = document.createElement('div');
            card.className = 'che-parte-card';

            const inicio = parte.hora_inicio || parte.post_title || '';
            const fin    = parte.hora_fin || parte.post_date || '';
            const total  = parte.tiempo_total || '';
            const estado = parte.estado || '';
            const valid  = parte.estado_validacion || 'pendiente';
            const validClass = `che-parte-card__estado-validacion--${valid}`;

            card.innerHTML = `
                <div class="che-parte-card__header">
                    <span class="che-parte-card__id">Parte #${parte.ID || ''}</span>
                    <span class="che-parte-card__estado-validacion ${validClass}">${valid}</span>
                </div>

                <div class="che-parte-card__body">
                    <p><strong>Inicio:</strong> ${inicio}</p>
                    <p><strong>Fin:</strong> ${fin}</p>
                    <p><strong>Tiempo total:</strong> ${total}</p>
                    <p><strong>Estado jornada:</strong> ${estado}</p>
                </div>
            `;

            partesTbody.appendChild(card);
        });
    }

    /*
     * Carga los partes del trabajador desde la API
    */
    async function cargarMisPartes() {
        if (partesMsgBox) {
            partesMsgBox.textContent = 'Cargando partes...';
            partesMsgBox.style.color = '';
        }

        try {
            const currentUserId = window.cheAjax?.current_user_id || 0;

            const url = new URL(apiUrl + 'admin/partes-por-usuario');
            if (currentUserId) {
                url.searchParams.set('user_id', currentUserId);
            }

            const res  = await fetch(url.toString(), {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al cargar partes');
            }

            renderWorkerPartes(data.partes || data || []);
            if (partesMsgBox) partesMsgBox.textContent = '';
        } catch (e) {
            if (partesMsgBox) {
                partesMsgBox.textContent = e.message || 'Error al cargar partes.';
                partesMsgBox.style.color = 'red';
            }
            renderWorkerPartes([]);
        }
    }

    // Escuchar cambio de sección desde worker-panel.js
    document.addEventListener('che-worker-section-change', (e) => {
        if (e.detail?.sectionId === 'partes') {
            cargarMisPartes();
        }
    });

    /* Escuchar evento de refresco de partes */
    document.addEventListener('che-worker-partes-refresh', () => {
        const partesSection = document.querySelector('.che-worker-section[data-section="partes"]');
        if (!partesSection) return;
        const visible = partesSection.style.display !== 'none';
        if (visible) {
            cargarMisPartes();
        }
    });

})();
