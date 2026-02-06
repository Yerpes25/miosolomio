// ============================================
// MÓDULO DE CONFIGURACIÓN
// Gestión de configuración de empresa
// ============================================
(function () {
    'use strict';

    // Namespace
    window.CHEModules = window.CHEModules || {};

    // Variables globales de WordPress
    const ajaxUrl = window.cheAjax?.ajax_url || '';
    const nonce = window.cheAjax?.nonce || '';

    /**
     * Inicialización del módulo de configuración
     */
    function init() {
        setupEventListeners();
    }

    /**
     * Configurar event listeners
     */
    function setupEventListeners() {
        // Subir logo
        const uploadBtn = document.getElementById('che-upload-logo-btn');
        const logoInput = document.getElementById('che-logo-input');
        
        if (uploadBtn && logoInput) {
            uploadBtn.addEventListener('click', () => {
                logoInput.click();
            });

            logoInput.addEventListener('change', handleLogoUpload);
        }

        // Eliminar logo
        const removeLogoBtn = document.getElementById('che-remove-logo-btn');
        if (removeLogoBtn) {
            removeLogoBtn.addEventListener('click', handleLogoRemove);
        }

        // Guardar configuración
        const configForm = document.getElementById('che-configuracion-form');
        if (configForm) {
            configForm.addEventListener('submit', handleSaveConfig);
        }
    }

    /**
     * Subir logo
     */
    async function handleLogoUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validar tipo de archivo
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecciona un archivo de imagen válido.');
            return;
        }

        // Validar tamaño (máx 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('El archivo es demasiado grande. Máximo 5MB.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'che_upload_empresa_logo');
        formData.append('nonce', nonce);
        formData.append('logo', file);

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Actualizar vista previa
                const preview = document.getElementById('che-logo-preview-img');
                const placeholder = document.getElementById('che-logo-placeholder');
                const previewBox = document.querySelector('.che-logo-preview-box');
                
                if (placeholder && previewBox) {
                    // Si hay placeholder, crear imagen
                    placeholder.outerHTML = `<img src="${data.data.url}" alt="Logo empresa" id="che-logo-preview-img">`;
                    showRemoveButton();
                } else if (preview) {
                    // Si ya hay imagen, actualizar src
                    preview.src = data.data.url;
                }
                
                // Actualizar logo en el sidebar
                const sidebarLogo = document.querySelector('.che-admin-logo');
                if (sidebarLogo) {
                    sidebarLogo.src = data.data.url;
                }
                
                // Cambiar texto del botón
                const uploadBtn = document.getElementById('che-upload-logo-btn');
                if (uploadBtn) {
                    uploadBtn.innerHTML = '<span class="dashicons dashicons-upload"></span> Cambiar Logo';
                }
                
                console.log('✓ Logo actualizado correctamente');
                
                // Recargar la página para actualizar el logo en el sidebar
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                console.error('Error al subir el logo: ' + (data.data || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error al subir logo:', error);
        }
    }

    /**
     * Eliminar logo
     */
    async function handleLogoRemove() {
        if (!confirm('¿Estás seguro de que quieres eliminar el logo?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'che_remove_empresa_logo');
        formData.append('nonce', nonce);

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Actualizar vista previa
                const preview = document.getElementById('che-logo-preview-img');
                const previewBox = document.querySelector('.che-logo-preview-box');
                
                if (preview && previewBox) {
                    preview.outerHTML = `
                        <div class="che-logo-placeholder" id="che-logo-placeholder">
                            <span class="dashicons dashicons-format-image"></span>
                            <p>Sin logo</p>
                        </div>
                    `;
                }
                
                // Ocultar botón de eliminar
                const removeBtn = document.getElementById('che-remove-logo-btn');
                if (removeBtn) {
                    removeBtn.remove();
                }
                
                // Cambiar texto del botón de subir
                const uploadBtn = document.getElementById('che-upload-logo-btn');
                if (uploadBtn) {
                    uploadBtn.innerHTML = '<span class="dashicons dashicons-upload"></span> Subir Logo';
                }
                
                console.log('✓ Logo eliminado correctamente');
                
                // Recargar la página para actualizar el logo en el sidebar
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                console.error('Error al eliminar el logo');
            }
        } catch (error) {
            console.error('Error al eliminar logo:', error);
        }
    }

    /**
     * Guardar configuración
     */
    async function handleSaveConfig(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        formData.append('action', 'che_save_empresa_config');
        formData.append('nonce', nonce);

        const saveBtn = document.getElementById('che-save-config-btn');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="dashicons dashicons-update dashicons-spin"></span> Guardando...';

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Actualizar nombre de empresa en el sidebar si existe
                const empresaNombre = formData.get('empresa_nombre');
                const sidebarNombre = document.querySelector('.che-empresa-nombre');
                if (sidebarNombre && empresaNombre) {
                    sidebarNombre.textContent = empresaNombre;
                }
                
                console.log('✓ Configuración guardada correctamente');
            } else {
                console.error('Error al guardar la configuración');
            }
        } catch (error) {
            console.error('Error al guardar configuración:', error);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    }

    /**
     * Mostrar botón de eliminar logo
     */
    function showRemoveButton() {
        let removeBtn = document.getElementById('che-remove-logo-btn');
        if (!removeBtn) {
            const actions = document.querySelector('.che-logo-actions');
            if (actions) {
                removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.id = 'che-remove-logo-btn';
                removeBtn.className = 'che-btn che-btn-danger-outline';
                removeBtn.innerHTML = '<span class="dashicons dashicons-trash"></span> Eliminar';
                removeBtn.addEventListener('click', handleLogoRemove);
                actions.appendChild(removeBtn);
            }
        } else {
            removeBtn.style.display = 'inline-flex';
        }
    }

    // Exponer funciones públicas
    window.CHEModules.configuracion = {
        init: init
    };

})();
