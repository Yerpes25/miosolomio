// ============================================
// MÓDULO JORNADA (worker-jornada.js)
// ============================================
(function () {
    'use strict';

    if (!window.CHEWorker) return;

    window.CHEWorkerModules = window.CHEWorkerModules || {};

    const apiUrl     = window.CHEWorker.apiUrl || window.cheAjax?.api_url || '/wp-json/che/v1/';
    const wpRestRoot = window.wpApiSettings?.root || '/wp-json/';
    const wpRestNonce = window.wpApiSettings?.nonce || '';

    const OFFLINE_QUEUE_KEY = 'che_offline_partes';
    const ACTIVE_PARTE_KEY  = 'che_active_parte';

    let state = {
        selectedFiles: [],
        manualSelectedFiles: [],
        currentParteId: null,
        timerInterval: null,
        startTimestamp: null,
        accumulatedSeconds: 0,
        eventHandlers: {}
    };

    let elements = {};

    function initElements() {
        elements = {
            startBtn: document.getElementById('che-start-btn'),
            stopBtn: document.getElementById('che-stop-btn'),
            createManualBtn: document.getElementById('che-create-manual-btn'),
            manualModal: document.getElementById('che-manual-modal'),
            manualModalCloseBtn: document.getElementById('che-manual-close'),
            manualModalCancelBtn: document.getElementById('che-manual-cancel'),
            manualModalSaveBtn: document.getElementById('che-manual-save'),
            manualHoraInput: document.getElementById('che-manual-hora-inicio'),
            manualProyectoInput: document.getElementById('che-manual-proyecto'),
            manualAttachmentsInp: document.getElementById('che-manual-attachments'),
            manualTimepickerHours: document.getElementById('che-manual-timepicker-hours'),
            manualTimepickerMinutes: document.getElementById('che-manual-timepicker-minutes'),
            manualLoader: document.getElementById('che-manual-loader'),
            statusText: document.getElementById('che-status-text'),
            manualResult: document.getElementById('che-manual-result'),
            manualResultText: document.getElementById('che-manual-result-text'),
            messageBox: document.getElementById('che-message'),
            proyectoInp: document.getElementById('che-proyecto'),
            timerEl: document.getElementById('che-timer'),
            attachmentsInput: document.getElementById('che-parte-attachments'),
            dropzone: document.getElementById('che-dropzone'),
            filesList: document.getElementById('che-files-list'),
            parteWarning: document.getElementById('che-parte-warning'),
            parteToast: document.getElementById('che-parte-toast'),
            parteToastText: document.getElementById('che-parte-toast-text'),
            partLoader: document.getElementById('che-part-loader')
        };
    }

    // --------- Utilidades offline ---------

    function cargarColaOffline() {
        try {
            const raw = localStorage.getItem(OFFLINE_QUEUE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function guardarColaOffline(queue) {
        try {
            localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
        } catch (e) {}
    }

    function añadirAColaOffline(item) {
        const queue = cargarColaOffline();
        queue.push(item);
        guardarColaOffline(queue);
    }

    async function procesarColaOffline() {
        const queue = cargarColaOffline();
        if (!queue.length) return;

        const nuevaCola = [];

        for (const item of queue) {
            try {
                const res = await fetch(item.url, {
                    method: item.method || 'POST',
                    headers: item.headers || { 'Content-Type': 'application/json' },
                    body: JSON.stringify(item.body || {})
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Error al sincronizar');
                }
            } catch (e) {
                nuevaCola.push(item);
            }
        }

        guardarColaOffline(nuevaCola);
    }

    // --------- Estado parte activo ---------

    function guardarEstadoParteActivo() {
        if (!state.currentParteId) return;

        const estado = {
            parteId: state.currentParteId,
            startTimestamp: state.startTimestamp,
            statusText: elements.statusText?.textContent || '',
            accumulatedSeconds: state.accumulatedSeconds
        };
        localStorage.setItem(ACTIVE_PARTE_KEY, JSON.stringify(estado));
    }

    function limpiarEstadoParteActivo() {
        localStorage.removeItem(ACTIVE_PARTE_KEY);
    }

    function restaurarParteActivo() {
        try {
            const raw = localStorage.getItem(ACTIVE_PARTE_KEY);
            if (!raw) return;
            const estado = JSON.parse(raw);
            if (!estado.parteId) return;

            state.currentParteId     = estado.parteId;
            state.accumulatedSeconds = estado.accumulatedSeconds || 0;
            state.startTimestamp     = estado.startTimestamp || null;

            if (!state.startTimestamp) {
                state.startTimestamp = Date.now();
            } else {
                state.startTimestamp = Number(state.startTimestamp);
            }

            if (elements.startBtn && elements.stopBtn) {
                elements.startBtn.disabled = true;
                elements.stopBtn.disabled = false;
                elements.startBtn.style.display = 'none';
                elements.stopBtn.style.display  = '';
            }

            if (elements.createManualBtn) {
                elements.createManualBtn.style.display = 'none';
            }

            if (elements.statusText) {
                elements.statusText.textContent = estado.statusText || '▶️ Jornada activa';
            }

            if (elements.parteWarning) {
                elements.parteWarning.style.display = 'block';
            }

            if (elements.timerEl) {
                const elapsedSec = state.accumulatedSeconds + Math.floor((Date.now() - state.startTimestamp) / 1000);
                elements.timerEl.textContent = formatSeconds(elapsedSec);
            }

            if (state.timerInterval) clearInterval(state.timerInterval);
            state.timerInterval = setInterval(() => {
                const elapsedSec = state.accumulatedSeconds + Math.floor((Date.now() - state.startTimestamp) / 1000);
                if (elements.timerEl) {
                    elements.timerEl.textContent = formatSeconds(elapsedSec);
                }
            }, 1000);
        } catch (e) {
            console.error('No se pudo restaurar parte activo', e);
            limpiarEstadoParteActivo();
        }
    }

    // --------- Helpers ---------

    function setMessage(text, isError = false) {
        if (!elements.messageBox) return;
        elements.messageBox.textContent = text;
        elements.messageBox.style.color = isError ? 'red' : 'green';
    }

    function showParteToast(message) {
        if (!elements.parteToast || !elements.parteToastText) return;

        elements.parteToastText.textContent = message;
        elements.parteToast.classList.add('che-parte-toast--visible');

        setTimeout(() => {
            if (elements.parteToast) {
                elements.parteToast.classList.remove('che-parte-toast--visible');
            }
        }, 3000);
    }

    function showPartLoader() {
        if (elements.partLoader) {
            elements.partLoader.classList.add('is-active');
        }
    }

    function hidePartLoader() {
        if (elements.partLoader) {
            elements.partLoader.classList.remove('is-active');
        }
    }

    function formatSeconds(totalSeconds) {
        const h = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
        const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
        const s = String(totalSeconds % 60).padStart(2, '0');
        return `${h}:${m}:${s}`;
    }

    function startTimer() {
        state.startTimestamp = Date.now();
        if (state.timerInterval) clearInterval(state.timerInterval);
        state.timerInterval = setInterval(() => {
            const elapsedSec = state.accumulatedSeconds + Math.floor((Date.now() - state.startTimestamp) / 1000);
            if (elements.timerEl) {
                elements.timerEl.textContent = formatSeconds(elapsedSec);
            }
        }, 1000);
    }

    function stopTimer() {
        if (state.timerInterval) {
            clearInterval(state.timerInterval);
            state.timerInterval = null;
        }
    }

    function getElapsedSeconds() {
        if (!state.startTimestamp) {
            return state.accumulatedSeconds;
        }
        return state.accumulatedSeconds + Math.floor((Date.now() - state.startTimestamp) / 1000);
    }

    async function getCurrentCoords() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                return resolve(null);
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude
                    });
                },
                () => resolve(null),
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    // --------- Archivos adjuntos ---------

    function renderFilesList() {
        if (!elements.filesList) return;
        elements.filesList.innerHTML = '';

        if (!state.selectedFiles.length) {
            elements.filesList.style.display = 'none';
            return;
        }

        elements.filesList.style.display = 'block';

        state.selectedFiles.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'che-file-item';
            li.innerHTML = `
                <span class="che-file-name">${file.name}</span>
                <button type="button" class="che-file-remove" data-index="${index}">✕</button>
            `;
            elements.filesList.appendChild(li);
        });
    }

    function handleFilesFromInput(e) {
        const files = Array.from(e.target.files || []);
        if (!files.length) return;

        state.selectedFiles = state.selectedFiles.concat(files);
        if (elements.attachmentsInput) {
            elements.attachmentsInput.value = '';
        }
        renderFilesList();
    }

    async function subirAdjuntosParte(parteId) {
        if (!state.selectedFiles.length) return;

        const files = state.selectedFiles.slice();
        const attachmentIds = [];

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('title', `Adjunto parte ${parteId}`);

            try {
                const res = await fetch(wpRestRoot + 'wp/v2/media', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': wpRestNonce },
                    body: formData
                });
                const data = await res.json();
                if (res.ok && data.id) {
                    attachmentIds.push(data.id);
                } else {
                    console.error('Error subiendo adjunto', data);
                }
            } catch (e) {
                console.error('Error de red al subir adjunto', e);
            }
        }

        if (attachmentIds.length) {
            try {
                await fetch(apiUrl + 'parte/guardar-adjuntos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        parte_id: parteId,
                        attachments: attachmentIds
                    })
                });
            } catch (e) {
                console.error('Error guardando adjuntos en meta del parte', e);
            }
        }

        if (elements.attachmentsInput) {
            elements.attachmentsInput.value = '';
        }
        state.selectedFiles = [];
        renderFilesList();
    }

    async function subirAdjuntosParteManual(parteId) {
        if (!state.manualSelectedFiles.length) return;

        const files = state.manualSelectedFiles.slice();
        const attachmentIds = [];

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('title', `Adjunto parte manual ${parteId}`);

            try {
                const res = await fetch(wpRestRoot + 'wp/v2/media', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': wpRestNonce },
                    body: formData
                });
                const data = await res.json();
                if (res.ok && data.id) {
                    attachmentIds.push(data.id);
                } else {
                    console.error('Error subiendo adjunto manual', data);
                }
            } catch (e) {
                console.error('Error de red al subir adjunto manual', e);
            }
        }

        if (attachmentIds.length) {
            try {
                await fetch(apiUrl + 'parte/guardar-adjuntos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        parte_id: parteId,
                        attachments: attachmentIds
                    })
                });
            } catch (e) {
                console.error('Error guardando adjuntos manuales', e);
            }
        }

        state.manualSelectedFiles = [];
        if (elements.manualAttachmentsInp) {
            elements.manualAttachmentsInp.value = '';
        }
    }

    // --------- Parte automático ---------

    async function startParte() {
        setMessage('');
        const proyecto = elements.proyectoInp?.value || 'Proyecto demo';

        try {
            showPartLoader();

            const geo = await getCurrentCoords();
            const payload = {
                titulo: 'Parte desde panel',
                trabajador_id: window.cheAjax?.current_user_id || 0,
                proyecto: proyecto,
                geo_inicio: geo
            };

            const hacerPeticion = async () => {
                const res  = await fetch(apiUrl + 'parte/iniciar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Error al iniciar parte');
                }
                return data;
            };

            let data;
            if (navigator.onLine) {
                data = await hacerPeticion();
            } else {
                const tmpId = 'offline-' + Date.now();
                añadirAColaOffline({
                    type: 'start_parte',
                    url: apiUrl + 'parte/iniciar',
                    method: 'POST',
                    body: payload
                });
                data = { success: true, post_id: tmpId };
            }

            state.currentParteId = data.post_id;
            if (elements.statusText) elements.statusText.textContent = '▶️ Jornada activa';

            if (elements.startBtn && elements.stopBtn && elements.createManualBtn) {
                elements.startBtn.disabled = true;
                elements.stopBtn.disabled  = false;
                elements.startBtn.style.display = 'none';
                elements.createManualBtn.style.display = 'none';
                elements.stopBtn.style.display  = '';
            }

            state.accumulatedSeconds = 0;
            if (elements.timerEl) elements.timerEl.textContent = '00:00:00';
            startTimer();

            if (elements.parteWarning) {
                elements.parteWarning.style.display = 'block';
            }

            guardarEstadoParteActivo();
            setMessage('Parte iniciado. ID: ' + state.currentParteId);
            showParteToast('Parte iniciado correctamente.');
        } catch (e) {
            setMessage(e.message, true);
        } finally {
            hidePartLoader();
        }
    }

    async function stopParte() {
        setMessage('');

        if (!state.currentParteId) {
            setMessage('No hay ningún parte activo.', true);
            return;
        }

        try {
            showPartLoader();

            const geo        = await getCurrentCoords();
            const elapsedSec = getElapsedSeconds();

            const payload = {
                parte_id: state.currentParteId,
                geo_fin: geo,
                elapsed_time: elapsedSec
            };

            const hacerPeticion = async () => {
                const res  = await fetch(apiUrl + 'parte/finalizar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Error al finalizar parte');
                }
                return data;
            };

            if (navigator.onLine) {
                await hacerPeticion();
            } else {
                añadirAColaOffline({
                    type: 'finish_parte',
                    url: apiUrl + 'parte/finalizar',
                    method: 'POST',
                    body: payload
                });
            }

            if (navigator.onLine) {
                await subirAdjuntosParte(state.currentParteId);
            }

            if (elements.statusText) elements.statusText.textContent = '⏸️ Fuera de jornada';

            if (elements.startBtn && elements.stopBtn && elements.createManualBtn) {
                elements.startBtn.disabled = false;
                elements.stopBtn.disabled  = true;
                elements.startBtn.style.display = '';
                elements.createManualBtn.style.display = '';
                elements.stopBtn.style.display  = 'none';
            }

            stopTimer();
            state.startTimestamp = null;
            if (elements.timerEl) elements.timerEl.textContent = '00:00:00';
            state.accumulatedSeconds = 0;

            if (elements.parteWarning) {
                elements.parteWarning.style.display = 'none';
            }

            setMessage('Parte finalizado. ID: ' + state.currentParteId);
            state.currentParteId = null;
            limpiarEstadoParteActivo();
            showParteToast('Parte finalizado correctamente.');
        } catch (e) {
            setMessage(e.message, true);
        } finally {
            hidePartLoader();
        }
    }

    // --------- Timepicker + parte manual ---------

    function initManualTimepickerIfNeeded() {
        if (!elements.manualTimepickerHours || !elements.manualTimepickerMinutes) return;
        if (elements.manualTimepickerHours.dataset.ready === '1') return;

        const fragH = document.createDocumentFragment();
        const spacerTopH = document.createElement('div');
        spacerTopH.className = 'che-manual-timepicker__item che-manual-timepicker__item--spacer';
        fragH.appendChild(spacerTopH);

        for (let h = 0; h < 24; h++) {
            const div = document.createElement('div');
            div.className = 'che-manual-timepicker__item';
            div.dataset.value = String(h).padStart(2, '0');
            div.textContent = div.dataset.value;
            fragH.appendChild(div);
        }

        const spacerBottomH = spacerTopH.cloneNode();
        fragH.appendChild(spacerBottomH);
        elements.manualTimepickerHours.appendChild(fragH);

        const fragM = document.createDocumentFragment();
        const spacerTopM = document.createElement('div');
        spacerTopM.className = 'che-manual-timepicker__item che-manual-timepicker__item--spacer';
        fragM.appendChild(spacerTopM);

        for (let m = 0; m < 60; m++) {
            const div = document.createElement('div');
            div.className = 'che-manual-timepicker__item';
            div.dataset.value = String(m).padStart(2, '0');
            div.textContent = div.dataset.value;
            fragM.appendChild(div);
        }

        const spacerBottomM = spacerTopM.cloneNode();
        fragM.appendChild(spacerBottomM);
        elements.manualTimepickerMinutes.appendChild(fragM);

        elements.manualTimepickerHours.dataset.ready = '1';
    }

    // Solo mueve visualmente el timepicker a una hora por defecto (18:00)
    // NO cambia el valor real del input; eso lo hace updateActiveFromScroll.
    function scrollTimepickerToDefaultVisual() {
        if (!elements.manualTimepickerHours || !elements.manualTimepickerMinutes) return;

        const hh = '18';
        const mm = '00';

        const itemH = elements.manualTimepickerHours.querySelector(`.che-manual-timepicker__item[data-value="${hh}"]`);
        const itemM = elements.manualTimepickerMinutes.querySelector(`.che-manual-timepicker__item[data-value="${mm}"]`);

        const centerOffset = 50;

        if (itemH) {
            const top = itemH.offsetTop - centerOffset;
            elements.manualTimepickerHours.scrollTo({ top, behavior: 'auto' });
        }
        if (itemM) {
            const top = itemM.offsetTop - centerOffset;
            elements.manualTimepickerMinutes.scrollTo({ top, behavior: 'auto' });
        }
    }

    // Ajusta el valor real del input según la posición actual del timepicker
    function syncManualHoraFromCurrentScroll() {
        if (!elements.manualTimepickerHours || !elements.manualTimepickerMinutes) return;

        updateActiveFromScroll(elements.manualTimepickerHours);
        updateActiveFromScroll(elements.manualTimepickerMinutes);
    }

    function showManualResult(message, isError = false) {
        if (!elements.manualResult || !elements.manualResultText) return;

        elements.manualResultText.textContent = message;
        const box = elements.manualResult.querySelector('.che-manual-modal__result-box');
        if (box) {
            box.classList.toggle('is-error', !!isError);
        }

        elements.manualResult.classList.add('is-active');

        // Ocultar automáticamente a los 3 segundos
        setTimeout(() => {
            if (elements.manualResult) {
                elements.manualResult.classList.remove('is-active');
            }
        }, 3000);
    }


    function abrirManualModal() {
        if (!elements.manualModal) return;

        initManualTimepickerIfNeeded();
        elements.manualModal.classList.add('che-manual-modal--open');

        // Tras pintar el modal, centramos visualmente en 18:00
        // y sincronizamos el valor real del input con esa posición
        setTimeout(() => {
            scrollTimepickerToDefaultVisual();
            syncManualHoraFromCurrentScroll();
        }, 0);
    }



    function cerrarManualModal() {
        if (!elements.manualModal) return;
        elements.manualModal.classList.remove('che-manual-modal--open');
    }

    function updateActiveFromScroll(colEl) {
        const items = colEl.querySelectorAll('.che-manual-timepicker__item');
        if (!items.length) return;

        const center = colEl.scrollTop + colEl.clientHeight / 2;
        let closest = null;
        let closestDist = Infinity;

        items.forEach((item) => {
            if (item.classList.contains('che-manual-timepicker__item--spacer')) return;
            const itemCenter = item.offsetTop + item.offsetHeight / 2;
            const dist = Math.abs(itemCenter - center);
            if (dist < closestDist) {
                closestDist = dist;
                closest = item;
            }
        });

        items.forEach(i => i.classList.remove('che-manual-timepicker__item--active'));
        if (closest) {
            closest.classList.add('che-manual-timepicker__item--active');
        }

        const hourItem = elements.manualTimepickerHours
            ? elements.manualTimepickerHours.querySelector('.che-manual-timepicker__item--active:not(.che-manual-timepicker__item--spacer)')
            : null;
        const minuteItem = elements.manualTimepickerMinutes
            ? elements.manualTimepickerMinutes.querySelector('.che-manual-timepicker__item--active:not(.che-manual-timepicker__item--spacer)')
            : null;

        if (elements.manualHoraInput && hourItem && minuteItem) {
            elements.manualHoraInput.value = `${hourItem.dataset.value}:${minuteItem.dataset.value}`;
        }
    }

    async function crearParteManual() {
        if (!elements.manualHoraInput) return;

        const inputHora = elements.manualHoraInput.value;
        if (!inputHora) {
            setMessage('Debes indicar la hora de inicio.', true);
            return;
        }

        const match = inputHora.trim().match(/^(\d{2}):(\d{2})$/);
        if (!match) {
            setMessage('Formato de hora no válido. Usa HH:MM.', true);
            return;
        }

        const proyectoManual = elements.manualProyectoInput?.value?.trim() || '';
        const proyecto = proyectoManual || (elements.proyectoInp?.value || 'Parte manual');

        try {
            // Activar overlay de carga y bloquear botón "Guardar"
            if (elements.manualLoader) {
                elements.manualLoader.classList.add('is-active');
            }
            if (elements.manualModalSaveBtn) {
                elements.manualModalSaveBtn.disabled = true;
            }

            const geo = await getCurrentCoords();

            const payload = {
                trabajador_id: window.cheAjax?.current_user_id || 0,
                proyecto: proyecto,
                hora_inicio: inputHora.trim(),
                titulo: 'Parte manual desde panel',
                geo_inicio: geo,
                geo_fin: geo
            };

            setMessage('Creando parte manual...');

            const res = await fetch(apiUrl + 'parte/crear-manual', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            // Leer mensaje de WP_Error si viene
            if (!res.ok || !data.success) {
                let msg = 'Error al crear parte manual.';
                if (data && typeof data.error === 'string') {
                    msg = data.error;
                }
                throw new Error(msg);
            }

            const parteId = data.parte_id;

            if (navigator.onLine && parteId) {
                await subirAdjuntosParteManual(parteId);
            }

            setMessage('Parte manual creado correctamente (ID ' + parteId + ').');
            showManualResult('Parte manual creado correctamente.', false);

            // Limpiar campos del modal
            if (elements.manualProyectoInput) elements.manualProyectoInput.value = '';

            // Cerrar el modal después de que desaparezca la tarjeta (3s)
            setTimeout(() => {
                cerrarManualModal();
            }, 3000);

            document.dispatchEvent(
                new CustomEvent('che-worker-partes-refresh', { detail: { parteId } })
            );
        } catch (e) {
            const msg = e.message || 'Error al crear parte manual.';
            setMessage(msg, true);
            showManualResult(msg, true);
        } finally {
            // Desactivar loader y desbloquear botón, tanto si ha ido bien como si hay error
            if (elements.manualLoader) {
                elements.manualLoader.classList.remove('is-active');
            }
            if (elements.manualModalSaveBtn) {
                elements.manualModalSaveBtn.disabled = false;
            }
        }
    }

    // --------- Listeners ---------

    function registerEventListeners() {
        // cola offline
        state.eventHandlers.online = procesarColaOffline;
        window.addEventListener('online', state.eventHandlers.online);
        procesarColaOffline();

        // dropzone
        if (elements.dropzone && elements.attachmentsInput) {
            state.eventHandlers.dropClick = () => elements.attachmentsInput.click();
            elements.dropzone.addEventListener('click', state.eventHandlers.dropClick);

            state.eventHandlers.dragOver = (e) => {
                e.preventDefault();
                elements.dropzone.classList.add('is-dragover');
            };
            state.eventHandlers.dragLeave = (e) => {
                e.preventDefault();
                elements.dropzone.classList.remove('is-dragover');
            };
            state.eventHandlers.drop = (e) => {
                e.preventDefault();
                elements.dropzone.classList.remove('is-dragover');
                const files = Array.from(e.dataTransfer.files || []);
                if (!files.length) return;
                state.selectedFiles = state.selectedFiles.concat(files);
                renderFilesList();
            };
            elements.dropzone.addEventListener('dragover', state.eventHandlers.dragOver);
            elements.dropzone.addEventListener('dragleave', state.eventHandlers.dragLeave);
            elements.dropzone.addEventListener('drop', state.eventHandlers.drop);
        }

        if (elements.attachmentsInput) {
            state.eventHandlers.attachChange = handleFilesFromInput;
            elements.attachmentsInput.addEventListener('change', state.eventHandlers.attachChange);
        }

        if (elements.filesList) {
            state.eventHandlers.filesClick = (e) => {
                const btn = e.target.closest('.che-file-remove');
                if (!btn) return;
                const index = parseInt(btn.dataset.index, 10);
                if (Number.isNaN(index)) return;
                state.selectedFiles.splice(index, 1);
                renderFilesList();
            };
            elements.filesList.addEventListener('click', state.eventHandlers.filesClick);
        }

        if (elements.manualAttachmentsInp) {
            state.eventHandlers.manualAttachChange = (e) => {
                const files = Array.from(e.target.files || []);
                if (!files.length) return;
                state.manualSelectedFiles = state.manualSelectedFiles.concat(files);
            };
            elements.manualAttachmentsInp.addEventListener('change', state.eventHandlers.manualAttachChange);
        }

        if (elements.startBtn) {
            state.eventHandlers.startClick = startParte;
            elements.startBtn.addEventListener('click', state.eventHandlers.startClick);
        }
        if (elements.stopBtn) {
            state.eventHandlers.stopClick = stopParte;
            elements.stopBtn.addEventListener('click', state.eventHandlers.stopClick);
        }
        if (elements.createManualBtn) {
            state.eventHandlers.manualOpen = abrirManualModal;
            elements.createManualBtn.addEventListener('click', state.eventHandlers.manualOpen);
        }
        if (elements.manualModalCloseBtn) {
            state.eventHandlers.manualClose1 = cerrarManualModal;
            elements.manualModalCloseBtn.addEventListener('click', state.eventHandlers.manualClose1);
        }
        if (elements.manualModalCancelBtn) {
            state.eventHandlers.manualClose2 = cerrarManualModal;
            elements.manualModalCancelBtn.addEventListener('click', state.eventHandlers.manualClose2);
        }
        if (elements.manualModalSaveBtn) {
            state.eventHandlers.manualSave = crearParteManual;
            elements.manualModalSaveBtn.addEventListener('click', state.eventHandlers.manualSave);
        }
        if (elements.manualModal) {
            const backdrop = elements.manualModal.querySelector('.che-manual-modal__backdrop');
            if (backdrop) {
                state.eventHandlers.manualBackdrop = cerrarManualModal;
                backdrop.addEventListener('click', state.eventHandlers.manualBackdrop);
            }
        }

        if (elements.manualTimepickerHours) {
            state.eventHandlers.tpHourScroll = () => updateActiveFromScroll(elements.manualTimepickerHours);
            elements.manualTimepickerHours.addEventListener('scroll', state.eventHandlers.tpHourScroll);
        }
        if (elements.manualTimepickerMinutes) {
            state.eventHandlers.tpMinScroll = () => updateActiveFromScroll(elements.manualTimepickerMinutes);
            elements.manualTimepickerMinutes.addEventListener('scroll', state.eventHandlers.tpMinScroll);
        }
    }

    function removeEventListeners() {
        if (state.eventHandlers.online) {
            window.removeEventListener('online', state.eventHandlers.online);
        }

        if (elements.dropzone) {
            if (state.eventHandlers.dropClick) elements.dropzone.removeEventListener('click', state.eventHandlers.dropClick);
            if (state.eventHandlers.dragOver) elements.dropzone.removeEventListener('dragover', state.eventHandlers.dragOver);
            if (state.eventHandlers.dragLeave) elements.dropzone.removeEventListener('dragleave', state.eventHandlers.dragLeave);
            if (state.eventHandlers.drop) elements.dropzone.removeEventListener('drop', state.eventHandlers.drop);
        }

        if (elements.attachmentsInput && state.eventHandlers.attachChange) {
            elements.attachmentsInput.removeEventListener('change', state.eventHandlers.attachChange);
        }

        if (elements.filesList && state.eventHandlers.filesClick) {
            elements.filesList.removeEventListener('click', state.eventHandlers.filesClick);
        }

        if (elements.manualAttachmentsInp && state.eventHandlers.manualAttachChange) {
            elements.manualAttachmentsInp.removeEventListener('change', state.eventHandlers.manualAttachChange);
        }

        if (elements.startBtn && state.eventHandlers.startClick) {
            elements.startBtn.removeEventListener('click', state.eventHandlers.startClick);
        }
        if (elements.stopBtn && state.eventHandlers.stopClick) {
            elements.stopBtn.removeEventListener('click', state.eventHandlers.stopClick);
        }
        if (elements.createManualBtn && state.eventHandlers.manualOpen) {
            elements.createManualBtn.removeEventListener('click', state.eventHandlers.manualOpen);
        }
        if (elements.manualModalCloseBtn && state.eventHandlers.manualClose1) {
            elements.manualModalCloseBtn.removeEventListener('click', state.eventHandlers.manualClose1);
        }
        if (elements.manualModalCancelBtn && state.eventHandlers.manualClose2) {
            elements.manualModalCancelBtn.removeEventListener('click', state.eventHandlers.manualClose2);
        }
        if (elements.manualModal) {
            const backdrop = elements.manualModal.querySelector('.che-manual-modal__backdrop');
            if (backdrop && state.eventHandlers.manualBackdrop) {
                backdrop.removeEventListener('click', state.eventHandlers.manualBackdrop);
            }
        }

        if (elements.manualTimepickerHours && state.eventHandlers.tpHourScroll) {
            elements.manualTimepickerHours.removeEventListener('scroll', state.eventHandlers.tpHourScroll);
        }
        if (elements.manualTimepickerMinutes && state.eventHandlers.tpMinScroll) {
            elements.manualTimepickerMinutes.removeEventListener('scroll', state.eventHandlers.tpMinScroll);
        }
    }

    // --------- Ciclo de vida del módulo ---------

    function init() {
        initElements();
        if (!elements.startBtn || !elements.stopBtn) return;

        registerEventListeners();
        restaurarParteActivo();
    }

    function destroy() {
        removeEventListeners();
        stopTimer();
        state = {
            selectedFiles: [],
            manualSelectedFiles: [],
            currentParteId: null,
            timerInterval: null,
            startTimestamp: null,
            accumulatedSeconds: 0,
            eventHandlers: {}
        };
        elements = {};
    }

    window.CHEWorkerModules.jornada = {
        init,
        destroy
    };
})();
