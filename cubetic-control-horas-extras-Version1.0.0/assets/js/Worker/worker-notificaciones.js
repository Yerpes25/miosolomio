(function () {
    'use strict';
    if (!window.CHEWorkerModules) return;
    if (!window.cheNotifications || !cheNotifications.ajaxUrl) return;

    let filterBtns = [];
    let notifCards = []; 
    let listEl, emptyEl, loadingEl; 
    let notifications = [];

    function showLoading(show) {
        if (loadingEl) loadingEl.style.display = show ? 'block' : 'none';
    }

    function loadNotifications() {
        const formData = new FormData();
        formData.append('action', 'che_worker_get_notifications');

        showLoading(true);

        return fetch(cheNotifications.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then(r => r.json())
            .then(data => {
                notifications = (data && data.success && Array.isArray(data.data)) ? data.data : [];
                renderRows();
                if (window.CHENotifications && typeof window.CHENotifications.updateBadge === 'function') {
                    window.CHENotifications.updateBadge();
                }
            })
            .catch(err => {
                console.error('[CHE Worker Notificaciones] Error carga:', err);
                notifications = [];
                renderRows();
            })
            .finally(() => showLoading(false));
    }

    function renderRows() {
        if (listEl) listEl.innerHTML = '';

        if (!notifications.length) {
            if (listEl) listEl.style.display = 'none';
            if (emptyEl) emptyEl.style.display = 'block';
            notifCards = [];
            return;
        }

        if (listEl) listEl.style.display = 'flex';
        if (emptyEl) emptyEl.style.display = 'none';

        const html = notifications.map(n => {
            const isRead = n.read ? '1' : '0';

            return `
                <div class="che-notificacion-card"
                     data-id="${n.id}"
                     data-read="${isRead}">
                    
                    <div class="che-notificacion-card-header">
                        <div class="che-notificacion-title">
                            ${n.title}
                            ${n.read ? '' : '<span class="che-notif-unread-dot"></span>'}
                        </div>
                        <div class="che-notificacion-status">
                            <span class="che-notif-status-pill ${n.read ? 'is-read' : 'is-unread'}">
                                ${n.read ? '✓ Leída' : '○ No leída'}
                            </span>
                        </div>
                    </div>

                    <div class="che-notificacion-message">
                        ${n.message}
                    </div>

                    <div class="che-notificacion-footer">
                        <span class="che-notificacion-date" title="${n.date}">
                            ${n.time_ago}
                        </span>
                    </div>
                </div>
            `;
        }).join('');

        if (listEl) listEl.innerHTML = html;

        notifCards = Array.from(document.querySelectorAll('.che-notificacion-card'));
        notifCards.forEach(card => card.addEventListener('click', handleRowClick));

        const active = filterBtns.find(b => b.classList.contains('active'));
        applyFilter(active ? active.dataset.filter : 'todas');
    }

    function applyFilter(filter) {
        notifCards.forEach(card => {
            const isRead = card.dataset.read === '1';
            let show = true;
            if (filter === 'revisada')    show = isRead;
            if (filter === 'no-revisada') show = !isRead;
            card.style.display = show ? '' : 'none';
        });
    }

    function handleFilterClick(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const filter = btn.dataset.filter;

        filterBtns.forEach(b => b.classList.toggle('active', b === btn));
        applyFilter(filter);
    }

    function handleRowClick(e) {
        const card    = e.currentTarget;
        const notifId = card.dataset.id;
        const isRead  = card.dataset.read === '1';
        if (isRead) return;

        const formData = new FormData();
        formData.append('action', 'che_mark_notifications_read');
        formData.append('ids[]', notifId);

        fetch(cheNotifications.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) return;
                
                // actualizar card
                card.dataset.read = '1';
                const dot = card.querySelector('.che-notif-unread-dot');
                if (dot) dot.remove();
                const pill = card.querySelector('.che-notif-status-pill');
                if (pill) {
                    pill.classList.remove('is-unread');
                    pill.classList.add('is-read');
                    pill.textContent = '✓ Leída';
                }

                // actualizar badge
                const badge   = document.getElementById('che-notification-badge');
                const countEl = document.getElementById('che-notification-count');
                if (badge && countEl) {
                    const current = parseInt(countEl.textContent || '0', 10) || 0;
                    const next    = Math.max(current - 1, 0);
                    countEl.textContent = next;
                    badge.style.display = next > 0 ? 'flex' : 'none';
                }

                if (window.CHENotifications && typeof window.CHENotifications.updateBadge === 'function') {
                    window.CHENotifications.updateBadge();
                }
            })
            .catch(err => {
                console.error('[CHE Worker Notificaciones] Error marcar leída:', err);
            });
    }

    function init() {
        filterBtns = Array.from(document.querySelectorAll('.che-filter-btn'));
        listEl     = document.querySelector('.che-notificaciones-list'); 
        emptyEl    = document.querySelector('.che-notificaciones-empty');
        loadingEl  = document.querySelector('.che-notificaciones-loading');

        filterBtns.forEach(btn => btn.addEventListener('click', handleFilterClick));

        loadNotifications();
    }

    function destroy() {
        filterBtns.forEach(btn => btn.removeEventListener('click', handleFilterClick));
        notifCards.forEach(card => card.removeEventListener('click', handleRowClick));
        filterBtns = [];
        notifCards = [];
        notifications = [];
    }

    function reload() {
        loadNotifications();
    }

    window.CHEWorkerModules.notificaciones = {
        init,
        destroy,
        reload,
    };
})();