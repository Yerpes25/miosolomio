(function () {
    // Namespace global común
    window.CHEAdmin = window.CHEAdmin || {};
    const apiUrl = window.cheAjax?.api_url || '/wp-json/che/v1/';
    window.CHEAdmin.apiUrl = apiUrl;

    // ============================================
    // GESTOR DE MÓDULOS DINÁMICOS
    // ============================================
    const ModuleManager = {
        currentModule: null,
        loadedScripts: {},
        loadedStyles: {},
        moduleInstances: {},

        /**
         * Carga un módulo dinámicamente
         */
        async loadModule(sectionId) {

            // Si ya está cargado este módulo, no hacer nada
           /* if (this.currentModule === sectionId && this.moduleInstances[sectionId]) {
                 return;
            }*/

            // Destruir módulo anterior si existe
            if (this.currentModule && this.currentModule !== sectionId) {
                await this.unloadModule(this.currentModule);
            }

            // Obtener configuración del módulo
            const moduleConfig = window.cheAjax?.modules?.[sectionId];
            if (!moduleConfig) {
                console.warn(`[ModuleManager] No hay configuración para módulo: ${sectionId}`);
                this.currentModule = sectionId;
                return;
            }

            try {
                // Cargar CSS del módulo
                if (moduleConfig.css && moduleConfig.css.length > 0) {
                    for (const cssFile of moduleConfig.css) {
                        await this.loadCSS(sectionId, cssFile);
                    }
                }

                // Cargar JS del módulo
                if (moduleConfig.js && moduleConfig.js.length > 0) {
                    for (const jsFile of moduleConfig.js) {
                        await this.loadScript(sectionId, jsFile);
                    }
                }

                // Inicializar el módulo si tiene método init
                if (window.CHEModules && window.CHEModules[sectionId] && typeof window.CHEModules[sectionId].init === 'function') {
                    try {
                        window.CHEModules[sectionId].init();
                    } catch (error) {
                        console.error(`[ModuleManager] Error al inicializar módulo ${sectionId}:`, error);
                    }
                }

                this.currentModule = sectionId;

            } catch (error) {
                console.error(`[ModuleManager] Error al cargar módulo ${sectionId}:`, error);
            }
        },

        /**
         * Carga un script dinámicamente
         */
        loadScript(moduleId, filename) {
            return new Promise((resolve, reject) => {
                const scriptId = `che-module-${moduleId}-${filename.replace('.js', '')}`;
                
                // Si ya está cargado, resolver inmediatamente
                if (this.loadedScripts[scriptId]) {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.id = scriptId;
                script.src = `${window.cheAjax.js_url}${filename}?ver=${window.cheAjax.version}`;
                script.async = false;
                
                script.onload = () => {
                    this.loadedScripts[scriptId] = script;
                    resolve();
                };
                
                script.onerror = () => {
                    console.error(`[ModuleManager] Error al cargar script: ${filename}`);
                    reject(new Error(`Failed to load script: ${filename}`));
                };
                
                document.head.appendChild(script);
            });
        },

        /**
         * Carga un CSS dinámicamente
         */
        loadCSS(moduleId, filename) {
            return new Promise((resolve, reject) => {
                const styleId = `che-module-${moduleId}-${filename.replace('.css', '')}`;
                
                // Si ya está cargado, resolver inmediatamente
                if (this.loadedStyles[styleId]) {
                    resolve();
                    return;
                }

                const link = document.createElement('link');
                link.id = styleId;
                link.rel = 'stylesheet';
                link.href = `${window.cheAjax.css_url}${filename}?ver=${window.cheAjax.version}`;
                
                link.onload = () => {
                    this.loadedStyles[styleId] = link;
                    resolve();
                };
                
                link.onerror = () => {
                    console.error(`[ModuleManager] Error al cargar CSS: ${filename}`);
                    reject(new Error(`Failed to load CSS: ${filename}`));
                };
                
                document.head.appendChild(link);
            });
        },

        /**
         * Descarga un módulo
         */
        async unloadModule(moduleId) {

            // Llamar al destructor del módulo si existe
            if (window.CHEModules && window.CHEModules[moduleId] && typeof window.CHEModules[moduleId].destroy === 'function') {
                try {
                    window.CHEModules[moduleId].destroy();
                   
                } catch (error) {
                    console.error(`[ModuleManager] Error al destruir módulo ${moduleId}:`, error);
                }
            }

            // Remover scripts del módulo
            Object.keys(this.loadedScripts).forEach(scriptId => {
                if (scriptId.includes(`che-module-${moduleId}-`)) {
                    const script = this.loadedScripts[scriptId];
                    if (script && script.parentNode) {
                        script.parentNode.removeChild(script);
                    }
                    delete this.loadedScripts[scriptId];
            
                }
            });

            // Remover CSS del módulo
            Object.keys(this.loadedStyles).forEach(styleId => {
                if (styleId.includes(`che-module-${moduleId}-`)) {
                    const link = this.loadedStyles[styleId];
                    if (link && link.parentNode) {
                        link.parentNode.removeChild(link);
                    }
                    delete this.loadedStyles[styleId];
                }
            });

            delete this.moduleInstances[moduleId];
        }
    };

    // Exponer el gestor de módulos
    window.CHEAdmin.ModuleManager = ModuleManager;

    function setMessage(box, text, isError = false) {
        if (!box) return;
        
        // Limpiar mensaje si no hay texto
        if (!text) {
            box.textContent = '';
            box.className = 'che-message';
            box.style.display = 'none';
            return;
        }
        
        // Mostrar mensaje con estilo apropiado
        box.textContent = text;
        box.className = isError ? 'che-message che-message-error' : 'che-message che-message-success';
        box.style.display = 'block';
        box.style.color = '';
    }
    window.CHEAdmin.setMessage = setMessage;

    // Navegación entre secciones

    async function showSection(sectionId) {
        const sections = document.querySelectorAll('.che-admin-section');
        if (!sections.length) return;

        sections.forEach(sec => {
            if (sec.dataset.section === sectionId) {
                sec.style.display = 'block';
                sec.style.visibility = 'visible';
                sec.style.opacity = '1';
                sec.style.position = 'relative';
                sec.style.zIndex = '1';
            } else {
                sec.style.display = 'none';
            }
        });

        // Primero, quitar todos los estados activos
        const menuItems = document.querySelectorAll('.che-admin-menu li');
        menuItems.forEach(item => item.classList.remove('is-active'));


        // Luego, marcar solo el elemento activo
        menuItems.forEach(item => {
            if (item.dataset.section === sectionId) {
                if (item.classList.contains('has-submenu')) {
                    item.classList.add('is-active');
                } else {
                    item.classList.add('is-active');
                    const parent = item.closest('.has-submenu');
                    if (parent) parent.classList.add('is-active');
                }
            }
        });

        // *** CARGAR MÓDULO DINÁMICAMENTE  ***
        await ModuleManager.loadModule(sectionId);
    }

    window.CHEAdmin.showSection = showSection;

    // Inicializar listeners cuando el DOM esté listo (o inmediatamente si ya cargado)
    function initMenu() {
        const menuItems = document.querySelectorAll('.che-admin-menu li');
        const sections  = document.querySelectorAll('.che-admin-section');
        
        if (!menuItems.length || !sections.length) return;

        // Manejar el toggle del submenú Y mostrar la sección
        const parentMenus = document.querySelectorAll('.has-submenu > .menu-parent');

        parentMenus.forEach(parentBtn => {
            parentBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const parentLi = parentBtn.closest('.has-submenu');

                if (!parentLi) return;

                // Toggle del submenú actual
                parentLi.classList.toggle('submenu-open');

                // Mostrar la sección correspondiente al padre
                const sectionId = parentLi.dataset.section;
                if (sectionId) {
                    showSection(sectionId);
                    document.dispatchEvent(
                        new CustomEvent('che-admin-section-change', { detail: { sectionId } })
                    );
                }
            });
        });

        // Manejar clicks en items del submenú
        menuItems.forEach(item => {
            // Solo agregar listener a los items que tienen data-section y no son padres
            if (item.dataset.section && !item.classList.contains('has-submenu')) {
                const btn = item.querySelector('.menu-btn');
                if (btn) {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const sectionId = item.dataset.section;
                        showSection(sectionId);

                        // Marcar activo el li clicado
                        item.classList.add('is-active');

                        //Eliminar la clase submenu-open de todos los padres
                        const allParents = document.querySelectorAll('.has-submenu');
                        allParents.forEach(parent => {
                            if (parent !== item.closest('.has-submenu')) {
                                parent.classList.remove('submenu-open');
                            }
                        });

                        // Marcar padre si existe
                        const parent = item.closest('.has-submenu');
                       if (parent) parent.classList.add('is-open');

                        document.dispatchEvent(
                            new CustomEvent('che-admin-section-change', { detail: { sectionId } })
                        );
                    });
                }

                const btnSubmenu = item.querySelector('.submenu-btn');
                if (btnSubmenu) {
                    btnSubmenu.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const sectionId = item.dataset.section;
                        showSection(sectionId);

                        // Marcar activo el li clicado
                        item.classList.add('is-active');

                        // Marcar padre si existe
                        const parent = item.closest('.has-submenu');
                       if (parent) parent.classList.add('is-open');

                        document.dispatchEvent(
                            new CustomEvent('che-admin-section-change', { detail: { sectionId } })
                        );
                    });
                }


            }
        });

        // Restaurar sección guardada en sessionStorage si existe, sino mostrar "sedes"
        try {
            const saved = sessionStorage.getItem('che-current-section');
            if (saved) {
                showSection(saved);
                sessionStorage.removeItem('che-current-section');
            } else {
                showSection('sedes');
            }
        } catch (e) {
            // sessionStorage no disponible -> comportamiento por defecto
            showSection('sedes');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenu);
    } else {
        initMenu();
    }

    // Toggle sidebar (ajustes para el menu de admin)
    const body         = document.body;
    const menuToggleBtn = document.querySelector('.che-admin-menu-toggle');

    function openSidebar() {
    body.classList.remove('che-sidebar-collapsed');
}

function closeSidebar() {
    body.classList.add('che-sidebar-collapsed');
}

function toggleSidebar() {
    body.classList.toggle('che-sidebar-collapsed');
}

if (menuToggleBtn && body) {
    // Abrir/cerrar con el botón
    menuToggleBtn.addEventListener('click', (e) => {
        e.stopPropagation(); // que el click no cuente como "fuera"
        toggleSidebar();
    });
}

// Cerrar al hacer click fuera (en main o en cualquier parte del body que no sea sidebar ni botón)
/* if (body && sidebar && main) {
    body.addEventListener('click', (e) => {
        const target = e.target;

        const clickInsideSidebar = sidebar.contains(target);
        const clickOnToggle      = menuToggleBtn.contains(target);

        // Si el menú está abierto y se hace click fuera -> cerrar
        if (!body.classList.contains('che-sidebar-collapsed') &&
            !clickInsideSidebar &&
            !clickOnToggle) {
            closeSidebar();
        }
    });
} */
// === NUEVO: rellenar selects de sede (partes + vacaciones) ===
window.CHEAdmin.rellenarSelectsSede = function (sedes) {
    const entries = Object.entries(sedes || {});

    // Filtro de sedes en Gestión de partes
    const sedeFilter = document.getElementById('che-admin-sede-filter');
    if (sedeFilter) {
        const current = sedeFilter.value;
        sedeFilter.innerHTML = '<option value="">Todas las sedes</option>';
        entries.forEach(([id, data]) => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = data.nombre || ('Sede ' + id);
            sedeFilter.appendChild(opt);
        });
        if (current) sedeFilter.value = current;
    }

    // Filtro de sedes en Gestión de vacaciones
    const vacSelect = document.getElementById('che-admin-vacaciones-sede');
    if (vacSelect) {
        const currentVac = vacSelect.value;
        vacSelect.innerHTML = '<option value="">Todas las sedes</option>';
        entries.forEach(([id, data]) => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = data.nombre || ('Sede ' + id);
            vacSelect.appendChild(opt);
        });
        if (currentVac) vacSelect.value = currentVac;
    }
};
    
})();