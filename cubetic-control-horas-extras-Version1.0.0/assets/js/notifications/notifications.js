/**
 * CHE Notifications - OneSignal Integration (Optimizada v16)
 * Sincronizada con OneSignalService.php
 * Eliminado SSE - Solo OneSignal para tiempo real y notificaciones nativas
 */
(function () {
    'use strict';

    if (!window.cheNotifications || !window.cheNotifications.ajaxUrl) {
        console.error('[CHE Notifications] Configuración base no encontrada.');
        return;
    }

    const config = window.cheNotifications;
    let shownToastIds = new Set();
    let pollingInterval = null;
    let dropdownOpen = false;
    let oneSignalInstance = null; // Guardar instancia de OneSignal
    let permissionStatus = 'unknown'; // Estado del permiso: 'default', 'granted', 'denied', 'unknown'

    window.CHENotifications = window.CHENotifications || {};
    
    // IMPORTANTE: Declarar funciones primero (se definirán después, pero el hoisting permite usarlas)
    // Las exportaremos al final del archivo para asegurar que estén disponibles cuando se configuren los listeners

    /**
     * Inicializa el sistema de notificaciones con OneSignal
     * Usa el patrón OneSignalDeferred de forma limpia
     */
    async function init() {
        // Asegurar que las funciones estén exportadas ANTES de configurar OneSignal
        // Esto garantiza que estén disponibles cuando se configuren los listeners
        
    // Central logout cleanup for OneSignal: removes external id on logout clicks
    function attachLogoutOneSignalCleanup() {
        if (!window.OneSignal) return;
        var handleLogoutClick = function (ev) {
            try {
                if (typeof OneSignal.setExternalUserId === 'function') {
                    OneSignal.setExternalUserId(null).then(function () {
                        console.log('[CHE Notifications] OneSignal external ID cleared on logout');
                    }).catch(function (e) {
                        console.warn('[CHE Notifications] Failed clearing OneSignal external ID', e);
                    });
                } else if (typeof OneSignal.logout === 'function') {
                    OneSignal.logout();
                }
            } catch (err) {
                console.warn('[CHE Notifications] logout cleanup error', err);
            }
            // Also clear local storage/session state if used
            try { sessionStorage.removeItem('che-one-signal-external-id'); } catch (e) {}
        };

        document.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t) return;
            if (t.matches && (t.matches('.che-btn-logout') || t.matches('#admin-cerrar') || t.matches('#worker-cerrar'))) {
                handleLogoutClick(ev);
            }
        });
    }
        window.CHENotifications.showToast = showToast;
        window.CHENotifications.updateBadge = updateNotificationBadge;
        window.CHENotifications.loadNotifications = loadNotifications;
        window.CHENotifications.renderNotifications = renderNotifications;
        
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        window.OneSignalDeferred.push(async function(OneSignal) {
            try {
                // CONFIGURACIÓN PARA ANDROID E iOS (Web Push)
                // IMPORTANTE: serviceWorkerParam y serviceWorkerPath son esenciales para que funcione en Android/iOS
                await OneSignal.init({
                    appId: config.appId,
                    // Service Worker en la raíz del sitio para compatibilidad con Android/iOS
                    serviceWorkerParam: { scope: "/" },
                    serviceWorkerPath: "OneSignalSDKWorker.js",
                    // Permitir localhost para desarrollo (remover en producción)
                    allowLocalhostAsSecureOrigin: true,
                    // Desactivar el botón nativo de OneSignal (usamos nuestro propio UI)
                    notifyButton: { enable: false }
                });

                // Guardar instancia de OneSignal para uso global
                oneSignalInstance = OneSignal;

                // VERIFICACIÓN DE PERMISOS (Especialmente importante para iOS)
                // En iOS, Safari requiere interacción del usuario para solicitar permisos
                await checkPermissionStatus();

                // Configurar listeners DESPUÉS de que OneSignal esté inicializado
                // Las funciones ya están exportadas arriba
                setupOneSignalListeners(OneSignal);

                // Sincronización vital con OneSignalService.php
                // External User ID debe coincidir con el formato usado en el servidor
                if (config.userId) {
                    const externalId = 'trabajador_' + config.userId;
                    // Usar la API correcta para asignar External User ID
                    if (typeof OneSignal.setExternalUserId === 'function') {
                        await OneSignal.setExternalUserId(externalId);
                        console.log('[CHE Notifications] ✅ OneSignal external ID establecido:', externalId);
                        // Attach logout cleanup so any browser logout clears the external id
                        try {
                            if (typeof attachLogoutOneSignalCleanup === 'function') {
                                attachLogoutOneSignalCleanup();
                            }
                        } catch (err) {
                            console.warn('[CHE Notifications] attachLogoutOneSignalCleanup call failed', err);
                        }
                    } else if (typeof OneSignal.login === 'function') {
                        // Fallback a login si la API antigua existe
                        await OneSignal.login(externalId);
                        console.log('[CHE Notifications] ✅ OneSignal sincronizado con External ID (fallback login):', externalId);
                    } else {
                        console.warn('[CHE Notifications] No se pudo sincronizar External ID: API OneSignal no encontrada');
                    }
                }

                // Polling de respaldo cada 2 minutos (reducido de 1 minuto para menos carga)
                // Solo como fallback si OneSignal no funciona
                startPolling(120000);

            } catch (error) {
                console.error('[CHE Notifications] ❌ Error en init OneSignal:', error);
                // Fallback: polling más frecuente si OneSignal falla
                startPolling(30000);
            }
        });

        // Inicializar eventos de la interfaz
        initDropdownEvents();
        updateNotificationBadge();
    }

    /**
     * Configura los listeners de OneSignal para notificaciones en tiempo real
     */
    function setupOneSignalListeners(OneSignal) {
        // Notificación recibida cuando la app está abierta (foreground)
        OneSignal.Notifications.addEventListener("foregroundWillDisplay", async (event) => {
            try {
                const n = event.notification;
                if (!n) {
                    console.warn('[CHE Notifications] Notificación sin datos');
                    return;
                }
                
                const data = n.additionalData || {};
                const title = n.title || 'Nueva notificación';
                const body = n.body || '';
                const type = data.type || 'default';
                
                // CORRECCIÓN: En OneSignal SDK v16, preventDefault() puede no estar disponible
                // en todos los navegadores. Intentar prevenir pero no fallar si no funciona.
                // El error "Browser does not support preventing display" es normal en algunos contextos.
                let preventDefaultSucceeded = false;
                try {
                    // Intentar prevenir la notificación nativa del navegador
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                        preventDefaultSucceeded = true;
                    } else if (event.notification && typeof event.notification.preventDefault === 'function') {
                        event.notification.preventDefault();
                        preventDefaultSucceeded = true;
                    }
                } catch (preventError) {
                    // Si preventDefault no está disponible, es normal - continuar de todas formas
                    // Esto ocurre en algunos navegadores o cuando la notificación viene del Service Worker
                    console.log('[CHE Notifications] preventDefault no disponible - mostrando toast personalizado de todas formas');
                }
                
                // IMPORTANTE: Mostrar nuestro toast personalizado SIEMPRE
                // Incluso si la notificación nativa aparece, nuestro toast será más visible
                showToast(title, body, type);
                
                // Actualizar badge inmediatamente
                await updateNotificationBadge();
                
                // Recargar notificaciones si el dropdown está abierto
                if (dropdownOpen) {
                    const notifications = await loadNotifications();
                    renderNotifications(notifications);
                }
                
                console.log('[CHE Notifications] 📨 Notificación procesada:', title, preventDefaultSucceeded ? '(nativa prevenida)' : '(nativa puede aparecer)');
            } catch (error) {
                console.error('[CHE Notifications] ❌ Error al procesar notificación foreground:', error);
            }
        });

        // Click en notificación nativa (app cerrada o en background)
        OneSignal.Notifications.addEventListener("click", (event) => {
            const notification = event.notification;
            const data  = notification?.additionalData || {};
            const roles = Array.isArray(config.userRoles) ? config.userRoles : [];

            // CASO WORKER: ir al panel del trabajador, sección "notificaciones"
            if (roles.includes('worker')) {
                try {
                    // Si ya estamos en el dashboard del trabajador
                    if (window.location.search.indexOf('worker_dashboard=1') !== -1) {
                        if (window.CHEWorker && typeof window.CHEWorker.showSection === 'function') {
                            window.CHEWorker.showSection('notificaciones');
                        } else {
                            const notiMenuBtn = document.querySelector(
                                '.che-worker-menu li[data-section="notificaciones"] .menu-btn'
                            );
                            if (notiMenuBtn) notiMenuBtn.click();
                        }
                    } else {
                        // No estamos en el panel → ir a la URL amigable del worker
                        window.location.href = `${window.location.origin}/trabajadores/panel/#notificaciones`;
                    }
                } catch (e) {
                    console.error('[CHE Notifications] Error al abrir sección notificaciones para worker:', e);
                }

                // Importante: no ejecutar la lógica de URL antigua
                return;
            }

            // RESTO DE ROLES (admin, super_admin, etc.): comportamiento actual
            if (data && data.url) {
                window.location.href = data.url;
            } else if (notification?.url) {
                window.location.href = notification.url;
            }
        });
    }


   
 /**
     * FUNCIONES DE UI Y RENDERIZADO
     */
    async function updateNotificationBadge() {
        try {
            const response = await fetch(`${config.ajaxUrl}?action=che_get_unread_count`);
            const data = await response.json();
            const count = data.data?.count || 0;
            
            const badges = document.querySelectorAll('.notification-badge, #notification-count');
            badges.forEach(badge => {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            });
        } catch (e) {
            console.warn('[CHE] Error badge');
        }
    }


     /**
     * Maneja una notificación recibida en tiempo real
     * @param {Object} notificationData - Datos de la notificación
     */
    function handleRealtimeNotification(notificationData) {
        const pushData = notificationData || {};

        // Extraer datos de la notificación
        let title = 'Nueva notificación';
        let body = '';
        let type = 'default';
        let notificationId = null;

        if (pushData.notification) {
            title = pushData.notification.title || title;
            body = pushData.notification.body || body;
        } else if (pushData.title) {
            title = pushData.title;
            body = pushData.body || body;
            type = pushData.type || type;
            notificationId = pushData.notification_id || null;
        }

        if (pushData.data) {
            type = pushData.data.type || type;
            notificationId = pushData.data.notification_id || notificationId;
        }

        // Mostrar toast inmediatamente
        showToast(title, body, type, notificationId);

        // Actualizar badge y recargar notificaciones
        updateNotificationBadge();

        // Si el dropdown está abierto, recargar la lista
        if (dropdownOpen) {
            loadNotifications().then(renderNotifications);
        }
    }

    function initDropdownEvents() {
        // Esperar un poco para asegurar que el DOM esté completamente listo
        setTimeout(function() {
            const notificationBtn = document.getElementById('che-notification-btn');
            const markAllBtn = document.getElementById('che-mark-all-read');
            const closeDropdownBtn = document.getElementById('che-close-dropdown');
            const dropdown = document.getElementById('che-notification-dropdown');

            console.log('[CHE Notifications] Inicializando eventos de dropdown:', {
                notificationBtn: !!notificationBtn,
                markAllBtn: !!markAllBtn,
                closeDropdownBtn: !!closeDropdownBtn,
                dropdown: !!dropdown
            });

            // Toggle dropdown al hacer click en el botón
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('[CHE Notifications] Click en botón de notificaciones');
                    toggleDropdown();
                });
            } else {
                console.error('[CHE Notifications] Botón de notificaciones no encontrado');
            }

            // Marcar todas como leídas
            if (markAllBtn) {
                markAllBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    markAllAsRead();
                });
            }

            // Cerrar dropdown
            if (closeDropdownBtn) {
                closeDropdownBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeDropdown();
                });
            }

            // Cerrar dropdown al hacer click fuera
            document.addEventListener('click', function (e) {
                if (dropdownOpen && dropdown && !dropdown.contains(e.target) &&
                    notificationBtn && !notificationBtn.contains(e.target)) {
                    closeDropdown();
                }
            });

            // Cerrar con tecla Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && dropdownOpen) {
                    closeDropdown();
                }
            });
        }, 100); // Esperar 100ms para asegurar que el DOM esté listo
    }

    /**
     *  Abre el dropdown de notificaciones
     * @returns 
     */
    
    async function openDropdown() {
        const dropdown = document.getElementById('che-notification-dropdown');
        if (!dropdown) return;

        // mostrar el contenedor antes de animar
        dropdown.style.display = 'flex';
        dropdown.style.flexDirection = 'column';

        // forzar reflow para que la transición tenga estado inicial
        // eslint-disable-next-line no-unused-expressions
        dropdown.offsetHeight;

        dropdown.classList.add('is-open');
        dropdownOpen = true;

        await updateNotificationBadge();
        await checkPermissionStatus();
        
        const notifications = await loadNotifications();
        renderNotifications(notifications);
    }

    /**
     * Cierra el dropdown de notificaciones
     */
    function closeDropdown() {
        const dropdown = document.getElementById('che-notification-dropdown');
        if (!dropdown) return;

        dropdown.classList.remove('is-open');
        dropdownOpen = false;

        const onTransitionEnd = (e) => {
            if (e.target !== dropdown) return;
            // ocultar SOLO al final de la animación
            dropdown.style.display = 'none';
            dropdown.removeEventListener('transitionend', onTransitionEnd);
        };

        dropdown.addEventListener('transitionend', onTransitionEnd);
    }

    /**
     * Toggle del dropdown
     */
    function toggleDropdown() {
        if (dropdownOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }



    /**
     * Maneja una notificación recibida en tiempo real (usado por OneSignal listener)
     */
    function handleRealtimeNotification(data) {
        showToast(data.title, data.body, data.type, data.notification_id);
        updateNotificationBadge();
        if (dropdownOpen) {
            loadNotifications().then(renderNotifications);
        }
    }

    /**
     * 5. POLLING (Respaldo por si falla el Push)
     */
    function startPolling(interval) {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(async () => {
            await  checkForNewNotifications();  // Al iniciar, verificar si hay notificaciones no leídas

        }, interval);
    }


     /**
     * Verifica si hay notificaciones nuevas y muestra toast
     */
    async function checkForNewNotifications() {
        try {
            const notifications = await loadNotifications();

                let isFirstLoad = shownToastIds.size === 0;

            // SIEMPRE actualizar el badge, incluso si no hay notificaciones
            // Esto asegura que el badge se actualice cuando cambia el conteo
            await updateNotificationBadge();

            if (!notifications || notifications.length === 0) {
                isFirstLoad = false;
                return;
            }

            // En la primera carga, solo guardamos los IDs sin mostrar toast
            if (isFirstLoad) {
                notifications.forEach(n => {
                    if (!n.read) shownToastIds.add(n.id);
                });
                isFirstLoad = false;
                console.log('[CHE] Primera carga - IDs guardados:', shownToastIds.size);
                // Actualizar badge después de primera carga
                await updateNotificationBadge();
                return;
            }

            // Buscar notificaciones no leídas que no hayamos mostrado aún
            // Usar ID único: combinar tipo + título + fecha para evitar duplicados
            const unreadNew = notifications.filter(n => {
                // Crear ID único para comparar (usar DB ID si está disponible, sino crear uno)
                const uniqueId = n.db_id ? `db_${n.db_id}` : `${n.type}_${n.title}_${n.date}`;
                return !n.read && !shownToastIds.has(uniqueId) && !shownToastIds.has(n.id);
            });

            // Mostrar toast para cada nueva notificación detectada por polling
            // SOLO si la aplicación está activa (foreground)
            if (unreadNew.length > 0 && !document.hidden) {
                console.log('[CHE Notifications] Nuevas notificaciones detectadas por polling:', unreadNew.length);

                unreadNew.forEach(notif => {
                    // Crear ID único para esta notificación
                    const uniqueId = notif.db_id ? `db_${notif.db_id}` : notif.id || `${notif.type}_${notif.title}_${notif.date}`;

                    // Mostrar toast (showToast verifica internamente que no se repita)
                    showToast(
                        notif.title || 'Nueva notificación',
                        notif.message || '',
                        notif.type || 'default',
                        uniqueId
                    );
                });

                // Actualizar badge nuevamente después de mostrar toasts
                await updateNotificationBadge();

                if (dropdownOpen) {
                    renderNotifications(notifications);
                }
            }

        } catch (error) {
            console.error('[CHE] Error en polling:', error);
            // Aún así, intentar actualizar el badge en caso de error
            updateNotificationBadge();
        }
    }

    /**
     * Obtiene el conteo de notificaciones no leídas (usando AJAX)
     */
    async function getUnreadCount() {
        try {
            const formData = new FormData();
            formData.append('action', 'che_get_unread_count');

            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            if (!response.ok) return 0;

            const data = await response.json();
            if (data.success) {
                return data.data.count || 0;
            }
            return 0;
        } catch (error) {
            console.error('[CHE] Error getting unread count:', error);
            return 0;
        }
    }

    /**
     * Actualiza el badge de notificaciones en el header
     * Incluye reintentos si el DOM no está listo aún
     */
    async function updateNotificationBadge(retries = 3) {
        const badge = document.getElementById('che-notification-badge');
        const countEl = document.getElementById('che-notification-count');

        // Si los elementos no existen y aún quedan reintentos, esperar y reintentar
        if ((!badge || !countEl) && retries > 0) {
            console.log(`[CHE Notifications] Badge elements no encontrados, reintentando... (${retries} intentos restantes)`);
            setTimeout(() => {
                updateNotificationBadge(retries - 1);
            }, 500);
            return;
        }

        // Si después de los reintentos aún no existen, registrar advertencia pero no fallar
        if (!badge || !countEl) {
            console.warn('[CHE Notifications] Badge elements no encontrados después de reintentos:', {
                badge: !!badge,
                countEl: !!countEl
            });
            return;
        }

        try {
            const count = await getUnreadCount();

            console.log('[CHE Notifications] Actualizando badge con conteo:', count);

            if (count > 0) {
                countEl.textContent = count > 99 ? '99+' : count;
                // Forzar estilos correctos para evitar que se vuelva pequeño o transparente
                badge.style.display = 'flex';
                badge.style.opacity = '1';
                badge.style.transform = 'scale(1)';
                badge.style.visibility = 'visible';
                badge.style.minWidth = '20px';
                badge.style.height = '20px';
                badge.style.background = '#dc3545';
                badge.style.color = '#fff';
                badge.style.fontSize = '11px';
                badge.style.fontWeight = '700';
                badge.className = 'che-notification-badge';
                console.log('[CHE Notifications] Badge mostrado con', count, 'notificaciones');
            } else {
                badge.style.display = 'none';
                console.log('[CHE Notifications] Badge ocultado (sin notificaciones)');
            }

        } catch (error) {
            console.error('[CHE Notifications] Error al actualizar badge:', error);
        }
    }


    /**
     * 6. DISEÑO DE TOAST (Tu lógica original optimizada)
     */
    function showToast(title, message, type = 'default', id = null) {
        if (id && shownToastIds.has(id)) return;
        if (id) shownToastIds.add(id);

        const container = document.getElementById('che-notifications-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `che-toast che-toast-${type}`;
        
        toast.innerHTML = `
            <div class="che-toast-content">
                <strong>${title}</strong>
                <p>${message}</p>
            </div>
            <button class="che-toast-close">&times;</button>
        `;

        container.appendChild(toast);
        
        toast.querySelector('.che-toast-close').onclick = () => toast.remove();
        setTimeout(() => { if(toast) toast.remove(); }, 10000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'che-notifications-container';
        container.style = "position:fixed; top:20px; right:20px; z-index:999999;";
        document.body.appendChild(container);
        return container;
    }
     /**
     * Carga las notificaciones desde AJAX
     */
    async function loadNotifications() {
        try {
            // Verificar que tenemos la URL de AJAX
            if (!config.ajaxUrl) {
                console.error('[CHE Notifications] ❌ ajaxUrl no está configurado');
                throw new Error('URL de AJAX no configurada');
            }

            console.log('[CHE Notifications] 🔍 Cargando notificaciones desde:', config.ajaxUrl);
            
            const formData = new FormData();
            formData.append('action', 'che_get_notifications');
            // Añadir nonce para seguridad si está disponible
            if (config.nonce) {
                formData.append('_ajax_nonce', config.nonce);
            }

            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Cache-Control': 'no-cache'
                },
                body: formData
            });

            console.log('[CHE Notifications] 📡 Respuesta recibida:', response.status, response.statusText);

            if (!response.ok) {
                // Intentar leer el cuerpo de la respuesta para más información
                const errorText = await response.text();
                console.error('[CHE Notifications] ❌ Error en respuesta:', response.status, errorText);
                throw new Error(`Error HTTP: ${response.status} - ${errorText.substring(0, 100)}`);
            }

            const data = await response.json();
            if (data.success) {
                let notificationsCache = data.data || [];
                console.log('[CHE Notifications] ✅ Notificaciones cargadas correctamente:', notificationsCache.length);
                return notificationsCache;
            } else {
                console.error('[CHE Notifications] ❌ Error en la respuesta AJAX:', data);
                throw new Error(data.data?.message || 'Error al cargar notificaciones');
            }
        } catch (error) {
            console.error('[CHE Notifications] ❌ Error al cargar notificaciones:', error);
            
            // Mostrar mensaje de error en el dropdown si está abierto
            if (dropdownOpen) {
                const listEl = document.getElementById('che-notification-list');
                if (listEl) {
                    const loadingEl = listEl.querySelector('.che-notification-loading');
                    if (loadingEl) {
                        loadingEl.innerHTML = `
                            <div class="che-notification-empty">
                                <div class="empty-icon">⚠️</div>
                                <p>Error al cargar notificaciones</p>
                                <p style="font-size: 12px; margin-top: 8px;">${error.message || 'Intenta recargar la página'}</p>
                            </div>
                        `;
                        loadingEl.className = 'che-notification-error';
                    }
                }
            }
            
            return [];
        }
    }

    /**
     * Verifica el estado del permiso de notificaciones
     * CORRECCIÓN: OneSignal puede devolver true/false en lugar de 'granted'/'denied'
     */
    async function checkPermissionStatus() {
        // Primero intentar verificar con la API nativa del navegador como respaldo
        let nativePermission = 'default';
        try {
            if ('Notification' in window && 'permission' in Notification) {
                nativePermission = Notification.permission; // 'granted', 'denied', 'default'
            }
        } catch (e) {
            // Ignorar errores de la API nativa
        }

        if (!oneSignalInstance) {
            // Si OneSignal no está disponible, usar la API nativa
            permissionStatus = nativePermission;
            console.log('[CHE Notifications] 📱 Estado de permiso (nativo):', permissionStatus);
            
            if (dropdownOpen) {
                renderPermissionStatus();
            }
            return permissionStatus;
        }

        try {
            // OneSignal puede devolver true/false o 'granted'/'denied'/'default'
            let onesignalPermission = await oneSignalInstance.Notifications.permission;
            console.log('[CHE Notifications] 📱 Estado de permiso OneSignal (raw):', onesignalPermission, typeof onesignalPermission);
            
            // Normalizar el valor devuelto por OneSignal
            // OneSignal SDK v16 puede devolver boolean true/false en lugar de strings
            if (onesignalPermission === true || onesignalPermission === 'granted') {
                permissionStatus = 'granted';
            } else if (onesignalPermission === false || onesignalPermission === 'denied') {
                permissionStatus = 'denied';
            } else if (onesignalPermission === 'default') {
                permissionStatus = 'default';
            } else {
                // Si OneSignal devuelve algo inesperado, usar la API nativa como respaldo
                permissionStatus = nativePermission;
                console.log('[CHE Notifications] ⚠️ OneSignal devolvió valor inesperado, usando API nativa:', nativePermission);
            }
            
            console.log('[CHE Notifications] 📱 Estado de permiso (normalizado):', permissionStatus);
            
            // Si el dropdown está abierto, actualizar el UI
            if (dropdownOpen) {
                renderPermissionStatus();
            }
            
            return permissionStatus;
        } catch (error) {
            console.log('[CHE Notifications] ⚠️ No se pudo verificar permisos con OneSignal, usando API nativa:', error);
            // Si OneSignal falla, usar la API nativa del navegador
            permissionStatus = nativePermission;
            
            if (dropdownOpen) {
                renderPermissionStatus();
            }
            return permissionStatus;
        }
    }

    /**
     * Solicita permisos de notificaciones al usuario
     * IMPORTANTE: En Android móvil, debe llamarse directamente desde un evento de click del usuario
     */
    async function requestPermission() {
        try {
            console.log('[CHE Notifications] 🔔 Solicitando permisos...');
            
            // Primero intentar con OneSignal si está disponible
            if (oneSignalInstance) {
                try {
                    await oneSignalInstance.Notifications.requestPermission();
                    console.log('[CHE Notifications] ✅ Solicitud de permisos enviada vía OneSignal');
                } catch (onesignalError) {
                    console.warn('[CHE Notifications] ⚠️ Error al solicitar permisos con OneSignal, intentando API nativa:', onesignalError);
                    // Si OneSignal falla, intentar con la API nativa
                    if ('Notification' in window && 'requestPermission' in Notification) {
                        await Notification.requestPermission();
                    }
                }
            } else {
                // Si OneSignal no está disponible, usar API nativa del navegador
                console.log('[CHE Notifications] OneSignal no disponible, usando API nativa');
                if ('Notification' in window && 'requestPermission' in Notification) {
                    await Notification.requestPermission();
                } else {
                    throw new Error('API de notificaciones no disponible en este navegador');
                }
            }
            
            // Esperar un poco para que el permiso se procese
            await new Promise(resolve => setTimeout(resolve, 500));
            
            // Verificar el nuevo estado
            await checkPermissionStatus();
            
            if (permissionStatus === 'granted') {
                showToast('¡Perfecto!', 'Ahora recibirás notificaciones cuando haya novedades.', 'success');
                console.log('[CHE Notifications] ✅ Permisos concedidos correctamente');
                
                // Si el dropdown está abierto, actualizar el UI
                if (dropdownOpen) {
                    renderPermissionStatus();
                }
                return true;
            } else if (permissionStatus === 'denied') {
                showToast('Permisos denegados', 'No podrás recibir notificaciones. Puedes activarlas desde la configuración del navegador.', 'error');
                console.warn('[CHE Notifications] ❌ Permisos denegados por el usuario');
                return false;
            } else {
                // Permiso aún en 'default' (puede pasar en iOS si la PWA no está instalada)
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
                if (isIOS) {
                    showToast('Instalación requerida', 'En iOS, debes instalar la app en tu pantalla de inicio para recibir notificaciones. Ve a Compartir → Añadir a pantalla de inicio.', 'info');
                } else {
                    showToast('Permisos no concedidos', 'Por favor, permite las notificaciones cuando tu navegador lo solicite.', 'info');
                }
                return false;
            }
        } catch (error) {
            console.error('[CHE Notifications] ❌ Error al solicitar permisos:', error);
            
            // Mensaje más específico para Android móvil
            const isAndroid = /Android/.test(navigator.userAgent);
            if (isAndroid) {
                showToast('Error', 'Cierra todos los cuadros o superposiciones e inténtalo de nuevo. Asegúrate de hacer clic directamente en el botón.', 'error');
            } else {
                showToast('Error', 'No se pudieron solicitar los permisos. Por favor, inténtalo de nuevo.', 'error');
            }
            return false;
        }
    }

    /**
     * Renderiza el estado de permisos en el dropdown
     */
    function renderPermissionStatus() {
        const listEl = document.getElementById('che-notification-list');
        if (!listEl) return;

        // Si los permisos están concedidos, no mostrar nada especial
        if (permissionStatus === 'granted') {
            // Si hay un banner de permisos, removerlo
            const permissionBanner = listEl.querySelector('.che-permission-banner');
            if (permissionBanner) {
                permissionBanner.remove();
            }
            return;
        }

        // Verificar si ya existe un banner de permisos
        let permissionBanner = listEl.querySelector('.che-permission-banner');
        
        if (!permissionBanner) {
            permissionBanner = document.createElement('div');
            permissionBanner.className = 'che-permission-banner';
            listEl.insertBefore(permissionBanner, listEl.firstChild);
        }

        let bannerHTML = '';
        
        if (permissionStatus === 'default') {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
            
            if (isIOS && !isStandalone) {
                // iOS sin PWA instalada
                bannerHTML = `
                    <div class="che-permission-content">
                        <div class="che-permission-icon">📱</div>
                        <div class="che-permission-text">
                            <strong>Instala la app para recibir notificaciones</strong>
                            <p>En iOS, debes instalar la app en tu pantalla de inicio para recibir notificaciones.</p>
                            <p class="che-permission-hint">👆 Ve a <strong>Compartir → Añadir a pantalla de inicio</strong></p>
                        </div>
                    </div>
                `;
            } else {
                // Android o iOS con PWA instalada - mostrar botón
                bannerHTML = `
                    <div class="che-permission-content">
                        <div class="che-permission-icon">🔔</div>
                        <div class="che-permission-text">
                            <strong>Activa las notificaciones</strong>
                            <p>Permite las notificaciones para estar al día de todas las novedades.</p>
                        </div>
                        <button type="button" id="che-request-permission-btn" class="che-request-permission-btn">
                            Activar notificaciones
                        </button>
                    </div>
                `;
            }
        } else if (permissionStatus === 'denied') {
            bannerHTML = `
                <div class="che-permission-content">
                    <div class="che-permission-icon">⚠️</div>
                    <div class="che-permission-text">
                        <strong>Notificaciones desactivadas</strong>
                        <p>Las notificaciones están bloqueadas. Puedes activarlas desde la configuración de tu navegador.</p>
                        <p class="che-permission-hint">🔧 Ve a <strong>Ajustes → Notificaciones</strong> en tu navegador</p>
                    </div>
                </div>
            `;
        } else {
            // Estado desconocido
            bannerHTML = `
                <div class="che-permission-content">
                    <div class="che-permission-icon">❓</div>
                    <div class="che-permission-text">
                        <strong>Estado de notificaciones desconocido</strong>
                        <p>No se pudo verificar el estado de las notificaciones.</p>
                        <button type="button" id="che-request-permission-btn" class="che-request-permission-btn">
                            Intentar activar
                        </button>
                    </div>
                </div>
            `;
        }

        permissionBanner.innerHTML = bannerHTML;

        // Añadir listener al botón si existe
        // IMPORTANTE: En Android móvil, la solicitud de permisos debe hacerse directamente desde el evento de click
        // Sin ningún delay o procesamiento asíncrono que pueda romper el "gesture" del usuario
        const requestBtn = permissionBanner.querySelector('#che-request-permission-btn');
        if (requestBtn) {
            // Usar mousedown en lugar de click para mejor compatibilidad con móviles
            requestBtn.addEventListener('mousedown', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Cerrar el dropdown primero para evitar conflictos en Android móvil
                closeDropdown();
                
                // Actualizar el botón
                requestBtn.disabled = true;
                const originalText = requestBtn.textContent;
                requestBtn.textContent = 'Solicitando...';
                
                // Pequeño delay para asegurar que el dropdown se cierre
                await new Promise(resolve => setTimeout(resolve, 100));
                
                // Solicitar permisos
                try {
                    await requestPermission();
                } finally {
                    // Restaurar el botón
                    requestBtn.disabled = false;
                    requestBtn.textContent = originalText;
                }
            });
            
            // También manejar touchstart para dispositivos táctiles
            requestBtn.addEventListener('touchstart', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                closeDropdown();
                
                requestBtn.disabled = true;
                const originalText = requestBtn.textContent;
                requestBtn.textContent = 'Solicitando...';
                
                await new Promise(resolve => setTimeout(resolve, 100));
                
                try {
                    await requestPermission();
                } finally {
                    requestBtn.disabled = false;
                    requestBtn.textContent = originalText;
                }
            });
        }
    }

    /**
     * Renderiza las notificaciones en el dropdown
     */
    function renderNotifications(notifications) {
        const listEl = document.getElementById('che-notification-list');
        if (!listEl) {
            console.error('[CHE Notifications] Elemento che-notification-list no encontrado en el DOM');
            return;
        }

        console.log('[CHE Notifications] Notificaciones cargadas:', notifications);
        
        // Eliminar el mensaje de "Cargando..." si existe
        const loadingEl = listEl.querySelector('.che-notification-loading');
        if (loadingEl) {
            loadingEl.remove();
        }
        
        // Renderizar estado de permisos primero
        renderPermissionStatus();
        
        // Obtener el banner de permisos si existe para insertar las notificaciones después
        const permissionBanner = listEl.querySelector('.che-permission-banner');
        
        // Crear contenedor para notificaciones si no existe
        let notificationsContainer = listEl.querySelector('.che-notifications-container');
        if (!notificationsContainer) {
            notificationsContainer = document.createElement('div');
            notificationsContainer.className = 'che-notifications-container';
            if (permissionBanner) {
                permissionBanner.insertAdjacentElement('afterend', notificationsContainer);
            } else {
                listEl.appendChild(notificationsContainer);
            }
        }

        // Limpiar contenido anterior del contenedor
        notificationsContainer.innerHTML = '';

        if (!notifications || notifications.length === 0) {
            console.warn('[CHE Notifications] No hay notificaciones para mostrar');
            notificationsContainer.innerHTML = `
                <div class="che-notification-empty">
                    <div class="empty-icon">🔔</div>
                    <p>No tienes notificaciones pendientes</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach(notification => {
            const icon = getNotificationIcon(notification.type);
            const tipoClass = `tipo-${notification.type || 'default'}`;
            const unreadClass = !notification.read ? 'unread' : '';
            const timeAgo = formatTimeAgo(notification.date);
            const section = getSectionFromType(notification.type);

            html += `
                <div class="che-notification-item ${tipoClass} ${unreadClass}" 
                     data-id="${notification.id}" 
                     data-type="${notification.type || ''}"
                     data-section="${section}">
                    <div class="notification-icon">${icon}</div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(notification.title)}</div>
                        <div class="notification-message">${escapeHtml(notification.message)}</div>
                        <div class="notification-meta">
                            <span class="notification-date">🕐 ${timeAgo}</span>
                        </div>
                    </div>
                </div>
            `;
        });


        notificationsContainer.innerHTML = html;

        // Procesar notificaciones después de renderizarlas
        //processNotifications(notifications);

                listEl.querySelectorAll('.che-notification-item').forEach(item => {
                    item.addEventListener('click', function () {
                        const notificationId = this.dataset.id;
                        const type    = this.dataset.type || '';
                        const section = this.dataset.section || 'vacaciones';

                        // marcar como leída
                        markNotificationAsRead(notificationId);
                        closeDropdown();

                        const roles = Array.isArray(config.userRoles) ? config.userRoles : [];

                        // Si es trabajador y la notificación es de parte, ir a la sección NOTIFICACIONES
                        const isParte = type.indexOf('parte_') === 0; // parte_validado, parte_rechazado, etc.

                        if (roles.includes('worker') && isParte) {
                            // Ya estamos dentro del panel worker
                            if (window.CHEWorker && typeof window.CHEWorker.showSection === 'function') {
                                window.CHEWorker.showSection('notificaciones');
                            } else {
                                // Fallback: simular click en el menú
                                const notiMenuBtn = document.querySelector(
                                    '.che-worker-menu li[data-section="notificaciones"] .menu-btn'
                                );
                                if (notiMenuBtn) notiMenuBtn.click();
                            }
                            return;
                        }

                        // Resto de casos (admin, vacaciones, etc.): comportamiento normal
                        navigateToSection(section);
                    });
                });
    }

    // Mostrar el dropdown automáticamente si hay notificaciones
    function showNotificationDropdown() {
        const notificationDropdown = document.getElementById('che-notification-dropdown');
        if (notificationDropdown) {
            notificationDropdown.style.display = 'block';
        } else {
            console.error('[CHE Notifications] Dropdown de notificaciones no encontrado');
        }
    }

     /**
     * Marca una notificación como leída (usando AJAX)
     */
    async function markNotificationAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'che_mark_notifications_read');
            formData.append('ids[]', notificationId);

            await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            await updateNotificationBadge();

            if (dropdownOpen) {
            const notifications = await loadNotifications();
            renderNotifications(notifications);
            }

            // Si estamos en el panel worker, recargar módulos relacionados
            if (window.CHEWorker && window.CHEWorkerModules && window.CHEWorkerModules.notificaciones) {
            const mod = window.CHEWorkerModules.notificaciones;
            if (typeof mod.reload === 'function') {
                mod.reload();
            }
            }

        } catch (error) {
            console.error('[CHE Notifications] Error al marcar como leída:', error);
        }
    }

    /**
     * Marca todas las notificaciones como leídas
     */
    async function markAllAsRead() {
        try {
            const notifications = await loadNotifications();
            if (!notifications || notifications.length === 0) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'che_mark_notifications_read');
            
            // Enviar todos los IDs de notificaciones
            notifications.forEach(notif => {
                formData.append('ids[]', notif.id);
            });

            await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            // Actualizar badge y recargar notificaciones
            updateNotificationBadge();
            if (dropdownOpen) {
                loadNotifications().then(renderNotifications);
            }
        } catch (error) {
            console.error('[CHE Notifications] Error al marcar todas como leídas:', error);
        }
    }

    /**
     * Obtiene el icono según el tipo de notificación
     */
    function getNotificationIcon(type) {
        switch (type) {
            // Bienvenida
            case 'welcome':
                return '🎉';
            // Vacaciones
            case 'solicitud':
            case 'vacacion_pendiente':
            case 'vacacion_solicitud':
                return '🏖️';
            case 'aprobado':
            case 'vacacion_aprobada':
                return '✅';
            case 'rechazado':
            case 'vacacion_rechazada':
                return '❌';
            // Partes de trabajo
            case 'parte_pendiente':
            case 'parte_nuevo':
                return '📝';
            case 'parte_validado':
                return '✔️';
            case 'parte_rechazado':
                return '✖️';
            // Usuarios
            case 'super_admin_created':
                return '👑';
            // General
            case 'pendiente':
                return '⏳';
            default:
                return '🔔';
        }
    }

    /**
     * Obtiene la sección a la que navegar según el tipo de notificación
     */
    function getSectionFromType(type) {
        switch (type) {
            // Bienvenida - ir a perfil (o quedarse donde está)
            case 'welcome':
                return 'perfil';
            // Partes de trabajo
            case 'parte_pendiente':
            case 'parte_validado':
            case 'parte_rechazado':
            case 'parte_nuevo':
                return 'partes';
            // Usuarios/Admin
            case 'super_admin_created':
                return 'usuarios';
            // Vacaciones (por defecto)
            case 'vacacion_pendiente':
            case 'vacacion_aprobada':
            case 'vacacion_rechazada':
            case 'vacacion_solicitud':
            case 'solicitud':
            case 'aprobado':
            case 'rechazado':
            default:
                return 'vacaciones';
        }
    }

     /**
     * Formatea la fecha como "hace X tiempo"
     */
    function formatTimeAgo(dateString) {
        if (!dateString) return '';

        let date;

        // Si tiene formato MySQL (sin T ni Z), añadir para que JavaScript lo interprete correctamente
        if (dateString.includes(' ') && !dateString.includes('T')) {
            date = new Date(dateString.replace(' ', 'T'));
        } else {
            date = new Date(dateString);
        }

        const now = new Date();
        const diffMs = now - date;
        const diffSecs = Math.floor(diffMs / 1000);
        const diffMins = Math.floor(diffSecs / 60);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        // Si la diferencia es negativa (fecha futura por error de zona), mostrar "ahora mismo"
        if (diffMs < 0) {
            return 'ahora mismo';
        }

        if (diffDays > 7) {
            return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
        } else if (diffDays > 0) {
            return `hace ${diffDays} día${diffDays > 1 ? 's' : ''}`;
        } else if (diffHours > 0) {
            return `hace ${diffHours} hora${diffHours > 1 ? 's' : ''}`;
        } else if (diffMins > 0) {
            return `hace ${diffMins} minuto${diffMins > 1 ? 's' : ''}`;
        } else {
            return 'ahora mismo';
        }
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


    /**
     * Navega a una sección específica
     */
    function navigateToSection(section) {
        if (window.CHEAdmin && typeof window.CHEAdmin.showSection === 'function') {
            window.CHEAdmin.showSection(section);
        } else if (window.CHEWorker && typeof window.CHEWorker.showSection === 'function') {
            window.CHEWorker.showSection(section);
        } else {
            const sectionBtn = document.querySelector(`[data-section="${section}"]`);
            if (sectionBtn) {
                const menuBtn = sectionBtn.querySelector('.menu-btn') || sectionBtn;
                menuBtn.click();
            }
        }
        closeDropdown();
    }

    // Las funciones ya se exportan en init() para asegurar disponibilidad
    // Pero también las exportamos aquí por si acaso se necesitan antes de init()
    window.CHENotifications.showToast = showToast;
    window.CHENotifications.updateBadge = updateNotificationBadge;
    window.CHENotifications.loadNotifications = loadNotifications;
    window.CHENotifications.renderNotifications = renderNotifications;
    window.CHENotifications.checkPermissionStatus = checkPermissionStatus;
    window.CHENotifications.requestPermission = requestPermission;

    // INICIALIZACIÓN
    document.addEventListener('DOMContentLoaded', init);
})();
