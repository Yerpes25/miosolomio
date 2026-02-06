// ============================================
// Núcleo Panel Trabajador (router + módulos)
// ============================================
(function () {
    'use strict';

    // Namespace global
    window.CHEWorker = window.CHEWorker || {};
    window.CHEWorkerModules = window.CHEWorkerModules || {};

    const apiUrl = window.cheWorkerAjax?.api_url || window.cheAjax?.api_url || '/wp-json/che/v1/';
    window.CHEWorker.apiUrl = apiUrl;

    // Helper global de mensajes por si algún módulo lo quiere usar
    window.CHEWorker.setMessage = function (box, text, isError = false) {
        if (!box) return;
        box.textContent = text || '';
        box.style.color = isError ? 'red' : 'green';
    };

    // ============================================
    // GESTOR DE MÓDULOS DINÁMICOS (similar a admin)
    // ============================================
    const ModuleManager = {
        currentModule: null,
        loadedScripts: {},
        loadedStyles: {},

        async loadModule(sectionId) {
            // Si es el mismo módulo, no recargamos
            if (this.currentModule === sectionId &&
                window.CHEWorkerModules[sectionId] &&
                typeof window.CHEWorkerModules[sectionId].init === 'function') {
                return;
            }

            // Destruir módulo anterior
            if (this.currentModule && this.currentModule !== sectionId) {
                await this.unloadModule(this.currentModule);
            }

            const moduleConfig = window.cheWorkerAjax?.modules?.[sectionId];
            if (!moduleConfig) {
                console.warn('[WorkerModuleManager] Sin config para sección:', sectionId);
                this.currentModule = sectionId;
                return;
            }

            try {
                // CSS (por si algún día lo usas)
                if (moduleConfig.css && moduleConfig.css.length) {
                    for (const cssFile of moduleConfig.css) {
                        await this.loadCSS(sectionId, cssFile);
                    }
                }

                // JS
                if (moduleConfig.js && moduleConfig.js.length) {
                    for (const jsFile of moduleConfig.js) {
                        await this.loadScript(sectionId, jsFile);
                    }
                }

                // Inicializar el módulo
                if (window.CHEWorkerModules[sectionId] &&
                    typeof window.CHEWorkerModules[sectionId].init === 'function') {
                    window.CHEWorkerModules[sectionId].init();
                }

                this.currentModule = sectionId;
            } catch (err) {
                console.error('[WorkerModuleManager] Error al cargar módulo', sectionId, err);
            }
        },

        async unloadModule(moduleId) {
            // Llamar destroy()
            if (window.CHEWorkerModules[moduleId] &&
                typeof window.CHEWorkerModules[moduleId].destroy === 'function') {
                try {
                    window.CHEWorkerModules[moduleId].destroy();
                } catch (err) {
                    console.error('[WorkerModuleManager] Error al destruir módulo', moduleId, err);
                }
            }

            // Borrar scripts cargados dinámicamente de ese módulo
            Object.keys(this.loadedScripts).forEach(id => {
                if (id.includes(`che-worker-module-${moduleId}-`)) {
                    const el = this.loadedScripts[id];
                    if (el && el.parentNode) el.parentNode.removeChild(el);
                    delete this.loadedScripts[id];
                }
            });

            // Borrar CSS si hubiera
            Object.keys(this.loadedStyles).forEach(id => {
                if (id.includes(`che-worker-module-${moduleId}-`)) {
                    const el = this.loadedStyles[id];
                    if (el && el.parentNode) el.parentNode.removeChild(el);
                    delete this.loadedStyles[id];
                }
            });
        },

        loadScript(moduleId, filename) {
            return new Promise((resolve, reject) => {
                const scriptId = `che-worker-module-${moduleId}-${filename.replace('.js', '')}`;

                if (this.loadedScripts[scriptId]) {
                    resolve();
                    return;
                }

                const s = document.createElement('script');
                s.id = scriptId;
                s.src = `${window.cheWorkerAjax.js_url}${filename}?ver=${window.cheWorkerAjax.version}`;
                s.async = false;

                s.onload = () => {
                    this.loadedScripts[scriptId] = s;
                    resolve();
                };
                s.onerror = () => {
                    console.error('[WorkerModuleManager] Error script', filename);
                    reject(new Error('Error script ' + filename));
                };

                document.head.appendChild(s);
            });
        },

        loadCSS(moduleId, filename) {
            return new Promise((resolve, reject) => {
                const styleId = `che-worker-module-${moduleId}-${filename.replace('.css', '')}`;

                if (this.loadedStyles[styleId]) {
                    resolve();
                    return;
                }

                const l = document.createElement('link');
                l.id = styleId;
                l.rel = 'stylesheet';
                l.href = `${window.cheWorkerAjax.css_url || ''}${filename}?ver=${window.cheWorkerAjax.version}`;

                l.onload = () => {
                    this.loadedStyles[styleId] = l;
                    resolve();
                };
                l.onerror = () => {
                    console.error('[WorkerModuleManager] Error CSS', filename);
                    reject(new Error('Error CSS ' + filename));
                };

                document.head.appendChild(l);
            });
        }
    };

    window.CHEWorker.ModuleManager = ModuleManager;

    // ============================================
    // NAVEGACIÓN ENTRE SECCIONES (worker)
    // ============================================
    async function showSection(sectionId) {
        const sections = document.querySelectorAll('.che-worker-section');
        if (!sections.length) return;

        sections.forEach(sec => {
            sec.style.display = (sec.dataset.section === sectionId) ? '' : 'none';
        });

        const menuItems = document.querySelectorAll('.che-worker-menu li');
        menuItems.forEach(item => {
            item.classList.toggle('is-active', item.dataset.section === sectionId);
        });

        const body = document.body;
        if (body) {
            body.classList.add('che-sidebar-collapsed');
        }

        // Notificar al resto de scripts
        document.dispatchEvent(
            new CustomEvent('che-worker-section-change', { detail: { sectionId } })
        );

        // Cargar módulo dinámicamente
        await ModuleManager.loadModule(sectionId);
    }

    window.CHEWorker.showSection = showSection;

    function initWorkerMenu() {
        const menuItems = document.querySelectorAll('.che-worker-menu li');
        const sections   = document.querySelectorAll('.che-worker-section');

        if (!menuItems.length || !sections.length) return;

        menuItems.forEach(item => {
            const btn = item.querySelector('.menu-btn');
            if (!btn) return;

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const sectionId = item.dataset.section;
                if (!sectionId) return;
                showSection(sectionId);
            });
        });

        // Sección por defecto
        showSection('jornada');
    }

    // Sidebar (versión worker)
    function initSidebarToggle() {
        const body   = document.body;
        const menuToggleBtn = document.querySelector('.che-worker-menu-toggle');
        const sidebar       = document.querySelector('.che-worker-sidebar');
        const main          = document.querySelector('.che-worker-main');

        if (!menuToggleBtn || !body || !sidebar || !main) return;

        function openSidebar() {
            body.classList.remove('che-sidebar-collapsed');
        }

        function closeSidebar() {
            body.classList.add('che-sidebar-collapsed');
        }

        function toggleSidebar() {
            body.classList.toggle('che-sidebar-collapsed');
        }

        // 🔹 Iniciar SIEMPRE minimizado
        closeSidebar();

        menuToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSidebar();
        });

        body.addEventListener('click', (e) => {
            const target = e.target;
            const clickInsideSidebar = sidebar.contains(target);
            const clickOnToggle      = menuToggleBtn.contains(target);

            if (!body.classList.contains('che-sidebar-collapsed') &&
                !clickInsideSidebar &&
                !clickOnToggle) {
                closeSidebar();
            }
        });
    }

    // Navegar a "editar perfil" al hacer clic en el bloque de usuario
    function initUserHeaderShortcut() {
        const userBox = document.querySelector('.che-worker-user');
        if (!userBox) return;

        userBox.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const targetSection = userBox.dataset.sectionTarget || 'editar-perfil';
            window.CHEWorker.showSection(targetSection);
        });
    }


    // Inicializar núcleo
    function init() {
        initWorkerMenu();
        initSidebarToggle();
        initUserHeaderShortcut();
         // 🔹 Sincronizar el badge cada 30 segundos como respaldo
        if (window.CHENotifications && typeof window.CHENotifications.updateBadge === 'function') {
            // Llamada inicial
            window.CHENotifications.updateBadge();

            // Polling de respaldo
            setInterval(function () {
                window.CHENotifications.updateBadge();
            }, 3000); // 3.000 ms = 3 s
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // OneSignal logout cleanup centralized in notifications.js
    // (handler removed from this module to avoid duplication)
})();
