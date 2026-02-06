/**
 * Gestor de la tabla de registros de actividades
 */
if (!window.ActivityLogManager) {
    window.ActivityLogManager = class {
        constructor() {
            this.currentPage = 1;
            this.totalPages = 1;
            this.filters = {
                activity_type: '',
                user_id: '',
            };
            this.activityTypes = {};
            this.adminUsers = [];
            this.logsData = []; // Guardar logs en memoria para ver detalles
            
            this.init();
        }

        init() {
            
            // Obtener nonce de las diferentes fuentes posibles
            this.nonce = window.cheRegistrosConfig?.nonce || window.cheAjax?.nonce || '';            
            this.setupEventListeners();
            this.loadActivityTypes();
            this.loadAdminUsers();
            this.loadLogs();
            
            // Botón refrescar
            const refreshBtn = document.getElementById('che-registros-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    // Recargar solo los logs, no toda la página
                    this.currentPage = 1;
                    this.loadLogs();
                });
            }
            
            // Cargar logs nuevamente cada vez que se enfoca esta sección
            this.observeVisibility();
        }

        /**
         * Observar cuando la sección se vuelve visible para recargar datos
         */
        observeVisibility() {
            const section = document.querySelector('[data-section="registros"]');
            if (!section) return;

            // Usar Intersection Observer para detectar cuando la sección es visible
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Recargar los datos cuando la sección sea visible
                        this.loadLogs();
                    }
                });
            }, { threshold: 0.1 });

            observer.observe(section);
        }

        setupEventListeners() {
            // Botón exportar
            const exportBtn = document.getElementById('che-registros-exportar');
            if (exportBtn) {
                exportBtn.addEventListener('click', () => this.exportToCSV());
            }

            // Cambios en los filtros para aplicar automáticamente
            document.getElementById('che-registros-filter-activity')?.addEventListener('change', (e) => {
                this.filters.activity_type = e.target.value;
                this.currentPage = 1;
                this.loadLogs();
            });

            document.getElementById('che-registros-filter-user')?.addEventListener('change', (e) => {
                this.filters.user_id = e.target.value;
                this.currentPage = 1;
                this.loadLogs();
            });
        }

        /**
         * Cargar tipos de actividad disponibles
         */
        loadActivityTypes() {
            fetch('/wp-json/che/v1/activity-types', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    this.activityTypes = data.data;
                    this.populateActivityTypeFilter();
                } else {
                    console.error('Error en activity types:', data);
                }
            })
            .catch(error => console.error('Error cargando tipos de actividad:', error));
        }

        /**
         * Cargar usuarios administradores para el filtro
         */
        loadAdminUsers() {
            fetch(`/wp-json/wp/v2/users?roles=admin,super_admin&per_page=100`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (Array.isArray(data)) {
                    this.adminUsers = data;
                    this.populateUserFilter();
                }
            })
            .catch(error => console.error('Error cargando usuarios:', error));
        }

        /**
         * Llenar dropdown de tipos de actividad
         */
        populateActivityTypeFilter() {
            const select = document.getElementById('che-registros-filter-activity');
            if (!select) return;

            Object.entries(this.activityTypes).forEach(([key, label]) => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = label;
                select.appendChild(option);
            });
        }

        /**
         * Llenar dropdown de usuarios
         */
        populateUserFilter() {
            const select = document.getElementById('che-registros-filter-user');
            if (!select) return;

            this.adminUsers.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                select.appendChild(option);
            });
        }

        /**
         * Cargar registros de actividad
         */
        loadLogs() {
            const tbody = document.getElementById('che-registros-tbody');
            if (!tbody) return;

            tbody.innerHTML = '<tr class="che-loading-row"><td colspan="6" class="che-text-center">Cargando registros...</td></tr>';

            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: 10,
                ...Object.fromEntries(Object.entries(this.filters).filter(([_, v]) => v !== ''))
            });

            fetch(`/wp-json/che/v1/activity-logs?${params}`, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.logsData = data.data; // Guardar en memoria
                    this.totalPages = data.total_pages;
                    this.currentPage = data.page; // Actualizar página actual
                    this.renderTable(data.data);
                    this.renderPagination(data.total_pages, data.page);
                } else {
                    console.error('Error al cargar registros:', data);
                    this.showMessage('Error al cargar los registros', 'error');
                }
            })
            .catch(error => {
                console.error('Error fetch:', error);
                this.showMessage('Error en la solicitud: ' + error.message, 'error');
            });
        }

        /**
         * Renderizar tabla de registros
         */
        renderTable(logs) {
            const tbody = document.getElementById('che-registros-tbody');
            if (!tbody) return;

            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="che-text-center">No hay registros disponibles</td></tr>';
                return;
            }

            tbody.innerHTML = logs.map(log => `
                <tr data-log-id="${log.id}">
                    <td class="col-fecha">${this.formatDate(log.created_at)}</td>
                    <td class="col-usuario">${this.escapeHtml(log.user_name)}</td>
                    <td class="col-actividad">
                        <span class="che-activity-badge ${this.getActivityClass(log.activity_type)}">
                            ${log.activity_label}
                        </span>
                    </td>
                    <td class="col-descripcion">${this.escapeHtml(log.action_description)}</td>
                    <td class="col-objeto">
                        ${log.object_name ? `<strong>${this.escapeHtml(log.object_name)}</strong>` : '-'}
                        ${log.object_type ? `<br><small>${log.object_type}</small>` : ''}
                    </td>
                    <td class="col-ip">${this.formatIP(log.ip_address)}</td>
                </tr>
            `).join('');
        }

        /**
         * Renderizar paginación
         */
        renderPagination(totalPages, currentPage) {
            const pagination = document.getElementById('che-registros-pagination');
            if (!pagination) return;

            pagination.innerHTML = '';

            // Botón anterior
            if (currentPage > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.textContent = '← Anterior';
                prevBtn.addEventListener('click', () => {
                    this.currentPage = currentPage - 1;
                    this.loadLogs();
                });
                pagination.appendChild(prevBtn);
            }

            // Números de página
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                if (i === currentPage) {
                    pageBtn.classList.add('active');
                }
                pageBtn.addEventListener('click', () => {
                    this.currentPage = i;
                    this.loadLogs();
                });
                pagination.appendChild(pageBtn);
            }

            // Botón siguiente
            if (currentPage < totalPages) {
                const nextBtn = document.createElement('button');
                nextBtn.textContent = 'Siguiente →';
                nextBtn.addEventListener('click', () => {
                    this.currentPage = currentPage + 1;
                    this.loadLogs();
                });
                pagination.appendChild(nextBtn);
            }
        }

        /**
         * Mostrar mensaje
         */
        showMessage(message, type = 'info') {
            const messageEl = document.getElementById('che-registros-message');
            if (!messageEl) return;

            messageEl.textContent = message;
            messageEl.className = `che-message che-message-${type}`;
            messageEl.style.display = 'block';

            setTimeout(() => {
                messageEl.style.display = 'none';
            }, 5000);
        }

        /**
         * Formatear fecha (ajuste timezone Madrid)
         */
        formatDate(dateString) {
            if (!dateString) return '-';
            
            const date = new Date(dateString);
            
            if (isNaN(date.getTime())) {
                return dateString; // Devolver el string original si no es una fecha válida
            }
            
            return date.toLocaleString('es-ES', {
                timeZone: 'Europe/Madrid',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        /**
         * Formatear IP (mostrar siempre)
         */
        formatIP(ip) {
            if (!ip) {
                return '-';
            }
            return this.escapeHtml(ip);
        }

        /**
         * Obtener clase CSS para el badge de actividad
         */
        getActivityClass(activityType) {
            if (activityType.includes('aprobacion')) return 'aprobacion';
            if (activityType.includes('rechazo')) return 'rechazo';
            if (activityType.includes('solicitud')) return 'solicitud';
            if (activityType.includes('nuevo') || activityType.includes('edicion')) return 'nuevo';
            return 'otro';
        }

        /**
         * Escapar HTML para prevenir XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Exportar registros a CSV
         */
        exportToCSV() {
            if (!this.logsData || this.logsData.length === 0) {
                alert('No hay datos para exportar');
                return;
            }

            const headers = [
                'Fecha y Hora',
                'Usuario',
                'Tipo de Actividad',
                'Descripcion',
                'Objeto Afectado',
                'IP'
            ];

            const rows = this.logsData.map(log => {
                return [
                    this.formatDate(log.created_at || log.activity_date) || '',
                    log.user_name || '',
                    log.activity_label || this.activityTypes[log.activity_type] || log.activity_type || '',
                    log.action_description || log.description || '', // Mantener saltos de línea en descripciones
                    log.related_object || log.object_name || '',
                    log.ip_address || ''
                ];
            });

            let csv = headers.join(';') + '\n';
            rows.forEach(row => {
                csv += row.map(cell => {
                    const cellStr = String(cell).replace(/"/g, '""');
                    // Siempre envolver en comillas para mantener saltos de línea y separadores
                    return `"${cellStr}"`;
                }).join(';') + '\n';
            });

            const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', `registros-${new Date().getTime()}.csv`);
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('che-registros-table') && !window.activityLogManager) {
        window.activityLogManager = new window.ActivityLogManager();
    }
});
