// Módulo de notificaciones de usuario
(function() {
    'use strict';

    window.CHEModules = window.CHEModules || {};

    const apiUrl = window.cheAjax?.api_url || '/wp-json/che/v1/';
    const nonce = window.cheAjax?.nonce || '';
    const userId = window.cheAjax?.current_user_id || 0;

    async function markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'che_mark_notifications_read');
            formData.append('ids[]', notificationId);

            const response = await fetch(window.cheAjax.ajax_url, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                // Recargar notificaciones
                await loadNotifications();
                
                // Actualizar badge si existe
                if (window.CHENotifications && typeof window.CHENotifications.updateBadge === 'function') {
                    window.CHENotifications.updateBadge();
                }
            } else {
                alert('Error al marcar notificación como leída');
            }
        } catch (error) {
            console.error('Error al marcar como leída:', error);
            alert('Error al marcar notificación como leída');
        }
    }

    function init() {
        loadNotifications();
        setupFilters();
    }

    async function loadNotifications() {
        const container = document.getElementById('che-notifications-container');
        if (!container) return;

        try {
            const response = await fetch(`${apiUrl}notifications?user_id=${userId}`, {
                headers: {
                    'X-WP-Nonce': nonce
                }
            });

            const data = await response.json();
            
            if (data.success && data.data && data.data.length > 0) {
                renderNotifications(data.data);
            } else if (data.notifications && data.notifications.length > 0) {
                // Formato alternativo de REST API
                renderNotifications(data.notifications);
            } else {
                container.innerHTML = '<div class="che-empty-state"><p>🔔 No hay notificaciones</p></div>';
            }
        } catch (error) {
            console.error('Error al cargar notificaciones:', error);
            container.innerHTML = '<div class="che-empty-state"><p>⚠️ Error al cargar notificaciones</p></div>';
        }
    }

    function renderNotifications(notifications) {
        const container = document.getElementById('che-notifications-container');
        
        const html = `
            <table class="che-admin-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Mensaje</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${notifications.map(notif => `
                        <tr class="che-notification-row ${notif.is_read ? 'read' : 'unread'}" data-id="${notif.id}" data-read="${notif.is_read ? '1' : '0'}">
                            <td>
                                ${notif.title}
                                ${!notif.is_read ? '<span class="che-unread-badge"></span>' : ''}
                            </td>
                            <td>${notif.message}</td>
                            <td><code>${notif.type}</code></td>
                            <td title="${notif.created_at}">${formatTimeAgo(notif.created_at)}</td>
                            <td>
                                <span class="che-status-badge ${notif.is_read ? 'read' : 'unread'}">
                                    ${notif.is_read ? '✓ Leída' : '○ No leída'}
                                </span>
                            </td>
                            <td>
                                ${!notif.is_read ? `<button class="button button-small che-mark-read-btn" data-notification-id="${notif.id}">Marcar como leída</button>` : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        
        container.innerHTML = html;
        
        // Agregar event listeners a botones de marcar como leída
        const markReadButtons = container.querySelectorAll('.che-mark-read-btn');
        markReadButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-notification-id');
                markAsRead(notificationId);
            });
        });
    }

    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'hace un momento';
        if (diff < 3600) return 'hace ' + Math.floor(diff / 60) + ' min';
        if (diff < 86400) return 'hace ' + Math.floor(diff / 3600) + ' h';
        if (diff < 604800) return 'hace ' + Math.floor(diff / 86400) + ' días';
        
        return date.toLocaleDateString('es-ES') + ' ' + date.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'});
    }

    function setupFilters() {
        const filterButtons = document.querySelectorAll('.che-filter-btn');

        filterButtons.forEach(button => {
            // Aplicar estilos iniciales al botón activo
            if (button.classList.contains('active')) {
                button.style.background = '#2271b1';
                button.style.color = 'white';
                button.style.borderColor = '#2271b1';
            }

            button.addEventListener('click', function() {
                // Actualizar botones activos
                filterButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.style.background = 'white';
                    btn.style.color = '#333';
                    btn.style.borderColor = '#ddd';
                });
                
                this.classList.add('active');
                this.style.background = '#2271b1';
                this.style.color = 'white';
                this.style.borderColor = '#2271b1';

                // Obtener filtro seleccionado
                const filter = this.dataset.filter;
                const notificationRows = document.querySelectorAll('.che-notification-row');

                // Filtrar filas
                notificationRows.forEach(row => {
                    const isRead = row.dataset.read === '1';
                    let shouldShow = true;

                    if (filter === 'revisada') {
                        shouldShow = isRead;
                    } else if (filter === 'no-revisada') {
                        shouldShow = !isRead;
                    }
                    // Si es 'todas', mostrar todas

                    row.style.display = shouldShow ? '' : 'none';
                });
            });
        });
    }

    window.CHEModules['notificaciones-usuario'] = { init };
})();
