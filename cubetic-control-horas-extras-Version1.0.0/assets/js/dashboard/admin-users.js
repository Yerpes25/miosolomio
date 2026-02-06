// ============================================
// MÓDULO DE USUARIOS
// Sistema de carga dinámica
// ============================================
(function () {
    'use strict';

    if (!window.CHEAdmin) {
        console.error('CHEAdmin no está definido');
        return;
    }

    window.CHEModules = window.CHEModules || {};

    const apiUrl = window.CHEAdmin.apiUrl;
    const setMessage = window.CHEAdmin.setMessage;

    // Estado del módulo
    let state = {
        allUsers: [],
        eventHandlers: {}
    };

    // Referencias a elementos DOM
    let elements = {};

    /**
     * Inicializar referencias a elementos DOM
     */
    function initElements() {
        elements = {
            usersTbody: document.getElementById('che-admin-users-body'),
            searchInput: document.getElementById('che-admin-user-search'),
            searchBtn: document.getElementById('che-admin-user-search-btn'),
            newUserBtn: document.getElementById('che-admin-user-new-btn'),
            createWrapper: document.getElementById('che-admin-create-user-wrapper'),
            usersTable: document.querySelector('.che-admin-users-table'),
            userForm: document.getElementById('che-admin-create-user'),
            userMsgBox: document.getElementById('che-admin-user-message'),
            closeUserBtn: document.getElementById('che-admin-user-close-btn'),
            cancelUserBtn: document.getElementById('che-admin-user-cancel-btn'),
            sedeFilterSelect: document.getElementById('che-admin-user-sede-filter'),
            roleFilterSelect: document.getElementById('che-admin-user-role-filter')
        };
    }

    function renderUsers(users) {
        if (!elements.usersTbody) return;
        elements.usersTbody.innerHTML = '';

        if (!users || !users.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 8;
            td.textContent = 'No se han encontrado usuarios.';
            tr.appendChild(td);
            elements.usersTbody.appendChild(tr);
            return;
        }

        users.forEach(user => {
            const roles = Array.isArray(user.roles) ? user.roles.join(', ') : '';
            const dniNie = user.dni_nie || 'No especificado';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    ${user.avatar ? `<img src="${user.avatar}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">` : ''}
                </td>
                <td>${user.ID}</td>
                <td>${dniNie}</td>
                <td>${user.display_name || ''}</td>
                <td>${user.user_email || ''}</td>
                <td>${roles}</td>
                <td>${user.sede_nombre || ''}</td>
                <td>
                    <button class="che-admin-user-edit-btn primary" id="all-btn" data-user-id="${user.ID}">Editar</button>
                    <button class="che-admin-user-delete-btn danger danger" data-user-id="${user.ID}">Eliminar</button>
                </td>
            `;
            elements.usersTbody.appendChild(tr);
        });
    }

    async function cargarUsuarios(query = '') {
        if (!elements.usersTbody) return;

        try {
            const url = new URL(apiUrl + 'admin/workers');
            if (query) {
                url.searchParams.set('search', query);
            }

            const res = await fetch(url.toString(), { method: 'GET' });
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al cargar usuarios');
            }
            state.allUsers = data.users || [];
            renderUsers(data.users || []);
        } catch (e) {
            console.error(e);
            renderUsers([]);
        }
    }

    function filtrarUsuarios(searchTerm = '', sedeFilter = '', roleFilter = '') {
        if (!state.allUsers.length) return [];

        return state.allUsers.filter(u => {
            let matchSearch = true;
            if (searchTerm) {
                const t = searchTerm.toLowerCase();
                matchSearch =
                    (u.dni_nie || '').toLowerCase().includes(t) ||
                    (u.display_name || '').toLowerCase().includes(t) ||
                    (u.user_email || '').toLowerCase().includes(t) ||
                    (u.sede_nombre || '').toLowerCase().includes(t);
            }

            let matchSede = true;
            if (sedeFilter) {
                matchSede = u.sede_id == sedeFilter;
            }

            let matchRole = true;
            if (roleFilter) {
                matchRole = Array.isArray(u.roles) && u.roles.includes(roleFilter);
            }

            return matchSearch && matchSede && matchRole;
        });
    }

    function aplicarFiltros() {
        const searchTerm = elements.searchInput ? elements.searchInput.value.trim() : '';
        const sedeFilter = elements.sedeFilterSelect ? elements.sedeFilterSelect.value : '';
        const roleFilter = elements.roleFilterSelect ? elements.roleFilterSelect.value : '';

        const filtered = filtrarUsuarios(searchTerm, sedeFilter, roleFilter);
        renderUsers(filtered);
    }

    function abrirModalUsuario() {
        if (!elements.createWrapper) return;
        elements.userForm?.reset();
        setMessage(elements.userMsgBox, '');

        // Restaurar el campo DNI/NIE en modo editable
        const dniNieInput = elements.userForm?.querySelector('[name="dni_nie"]');
        if (dniNieInput) {
            dniNieInput.removeAttribute('readonly');
            dniNieInput.style.backgroundColor = '';
            dniNieInput.setAttribute('required', 'required');
        }

        const modalTitle = document.getElementById('che-modal-user-title');
        if (modalTitle) modalTitle.textContent = 'Nuevo usuario';

        const submitBtn = elements.userForm?.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.textContent = 'Crear usuario';

        if (elements.userForm) delete elements.userForm.dataset.editingUserId;

        elements.createWrapper.classList.add('is-open');
    }

    function cerrarModalUsuario() {
        if (!elements.createWrapper) return;
        elements.createWrapper.classList.remove('is-open');

        if (elements.userForm) {
            const passInput = elements.userForm.querySelector('[name="user_pass"]');
            if (passInput) {
                passInput.placeholder = '';
            }
            delete elements.userForm.dataset.editingUserId;
        }
    }

    async function editarUsuario(userId) {
        if (!userId) return;

        try {
            const res = await fetch(apiUrl + 'admin/worker/' + userId, {
                method: 'GET'
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al cargar usuario');
            }

            const usuario = data.user;

            if (!usuario) {
                alert('Usuario no encontrado');
                return;
            }

            if (elements.userForm) {
                elements.userForm.reset();
                setMessage(elements.userMsgBox, '');
            }

            if (elements.createWrapper) {
                elements.createWrapper.classList.add('is-open');
            }

            const modalTitle = document.getElementById('che-modal-user-title');
            if (modalTitle) {
                modalTitle.textContent = 'Editar usuario';
            }

            if (elements.userForm) {
                const dniNieInput = elements.userForm.querySelector('[name="dni_nie"]');
                const userEmailInput = elements.userForm.querySelector('[name="user_email"]');
                const firstNameInput = elements.userForm.querySelector('[name="first_name"]');
                const lastNameInput = elements.userForm.querySelector('[name="last_name"]');
                const roleSelect = elements.userForm.querySelector('[name="role"]');
                const sedeSelect = elements.userForm.querySelector('[name="sede_trabajo"]');
                const passInput = elements.userForm.querySelector('[name="user_pass"]');

                if (dniNieInput) {
                    dniNieInput.value = usuario.dni_nie || '';
                    // Solo readonly si ya tiene DNI/NIE, si está vacío permite editarlo
                    if (usuario.dni_nie) {
                        dniNieInput.setAttribute('readonly', 'readonly');
                        dniNieInput.style.backgroundColor = '#f0f0f0';
                    } else {
                        dniNieInput.removeAttribute('readonly');
                        dniNieInput.style.backgroundColor = '';
                    }
                }
                if (userEmailInput) userEmailInput.value = usuario.user_email || '';
                if (firstNameInput) firstNameInput.value = usuario.first_name || '';
                if (lastNameInput) lastNameInput.value = usuario.last_name || '';
                if (roleSelect) roleSelect.value = usuario.roles?.[0] || 'worker';
                if (sedeSelect && usuario.sede_id) sedeSelect.value = usuario.sede_id;

                if (passInput) {
                    passInput.value = '';
                    passInput.removeAttribute('required');
                    passInput.placeholder = 'Dejar vacío para mantener la actual';
                }

                elements.userForm.dataset.editingUserId = userId;

                const submitBtn = elements.userForm.querySelector('[type="submit"]');
                if (submitBtn) {
                    submitBtn.textContent = 'Actualizar usuario';
                }
            }
        } catch (e) {
            alert('Error al cargar el usuario: ' + e.message);
            console.error(e);
        }
    }

    async function eliminarUsuario(userId) {
        if (!userId) return;

        const confirmacion = confirm('¿Estás seguro de que deseas eliminar este usuario?');
        if (!confirmacion) return;

        try {
            const res = await fetch(apiUrl + 'admin/delete-worker', {
                method: 'DELETE',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify({ user_id: userId })
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al eliminar usuario');
            }

            alert('Usuario eliminado correctamente');
            cargarUsuarios();
        } catch (e) {
            alert('Error: ' + e.message);
            console.error(e);
        }
    }

    async function crearUsuarioWorker(event) {
        event.preventDefault();
        setMessage(elements.userMsgBox, '');

        const formData = new FormData(elements.userForm);
        const payload = {
            dni_nie: formData.get('dni_nie') || '',
            user_email: formData.get('user_email') || '',
            first_name: formData.get('first_name') || '',
            last_name: formData.get('last_name') || '',
            role: formData.get('role') || 'worker',
            sede_trabajo: formData.get('sede_trabajo') || ''
        };

        const editingUserId = elements.userForm.dataset.editingUserId;
        const isEditing = !!editingUserId;

        // En creación, DNI/NIE y email son obligatorios. En edición solo email
        if (isEditing) {
            if (!payload.user_email) {
                setMessage(elements.userMsgBox, 'El email es obligatorio.', true);
                return;
            }
        } else {
            if (!payload.dni_nie || !payload.user_email) {
                setMessage(elements.userMsgBox, 'DNI/NIE y email son obligatorios.', true);
                return;
            }
            if (!payload.sede_trabajo) {
                setMessage(elements.userMsgBox, 'Debe seleccionar una sede.', true);
                return;
            }
        }

        const userPass = formData.get('user_pass') || '';
        if (userPass) {
            payload.user_pass = userPass;
        } else if (!isEditing) {
            setMessage(elements.userMsgBox, 'La contraseña es obligatoria al crear un usuario.', true);
            return;
        }

        try {
            let endpoint = isEditing ? 'admin/update-worker' : 'admin/crear-worker';

            if (isEditing) {
                payload.user_id = editingUserId;
            }

            const res = await fetch(apiUrl + endpoint, {
                method: isEditing ? 'PUT' : 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || `Error al ${isEditing ? 'actualizar' : 'crear'} usuario`);
            }

            elements.userForm.reset();
            delete elements.userForm.dataset.editingUserId;

            const passInput = elements.userForm.querySelector('[name="user_pass"]');
            if (passInput) {
                passInput.placeholder = '';
            }

            const submitBtn = elements.userForm.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.textContent = 'Crear usuario';
            }

            const modalTitle = document.getElementById('che-modal-user-title');
            if (modalTitle) {
                modalTitle.textContent = 'Nuevo usuario';
            }

            cerrarModalUsuario();
            cargarUsuarios();
        } catch (e) {
            setMessage(elements.userMsgBox, e.message, true);
        }
    }

    /**
     * Registrar event listeners
     */
    function registerEventListeners() {
        // Búsqueda
        if (elements.searchBtn && elements.searchInput) {
            state.eventHandlers.searchClick = aplicarFiltros;
            elements.searchBtn.addEventListener('click', state.eventHandlers.searchClick);

            state.eventHandlers.searchKeyup = function(e) {
                if (e.key === 'Enter') {
                    aplicarFiltros();
                }
            };
            elements.searchInput.addEventListener('keyup', state.eventHandlers.searchKeyup);
        }

        // Filtros
        if (elements.sedeFilterSelect) {
            state.eventHandlers.sedeChange = aplicarFiltros;
            elements.sedeFilterSelect.addEventListener('change', state.eventHandlers.sedeChange);
        }

        if (elements.roleFilterSelect) {
            state.eventHandlers.roleChange = aplicarFiltros;
            elements.roleFilterSelect.addEventListener('change', state.eventHandlers.roleChange);
        }

        // Modal
        if (elements.newUserBtn) {
            state.eventHandlers.openModal = abrirModalUsuario;
            elements.newUserBtn.addEventListener('click', state.eventHandlers.openModal);
        }

        if (elements.closeUserBtn) {
            state.eventHandlers.closeModal = cerrarModalUsuario;
            elements.closeUserBtn.addEventListener('click', state.eventHandlers.closeModal);
        }

        if (elements.cancelUserBtn) {
            state.eventHandlers.cancelModal = cerrarModalUsuario;
            elements.cancelUserBtn.addEventListener('click', state.eventHandlers.cancelModal);
        }

        if (elements.createWrapper) {
            state.eventHandlers.backdropClick = function(e) {
                if (e.target.classList.contains('che-modal__backdrop')) {
                    cerrarModalUsuario();
                }
            };
            elements.createWrapper.addEventListener('click', state.eventHandlers.backdropClick);
        }

        // Acciones en tabla (editar/eliminar)
        if (elements.usersTbody) {
            state.eventHandlers.tableClick = async function(e) {
                if (e.target.classList.contains('che-admin-user-edit-btn')) {
                    const userId = e.target.dataset.userId;
                    await editarUsuario(userId);
                }

                if (e.target.classList.contains('che-admin-user-delete-btn')) {
                    const userId = e.target.dataset.userId;
                    await eliminarUsuario(userId);
                }
            };
            elements.usersTbody.addEventListener('click', state.eventHandlers.tableClick);
        }

        // Formulario
        if (elements.userForm) {
            state.eventHandlers.formSubmit = crearUsuarioWorker;
            elements.userForm.addEventListener('submit', state.eventHandlers.formSubmit);
        }
    }

    /**
     * Remover event listeners
     */
    function removeEventListeners() {
        if (elements.searchBtn && state.eventHandlers.searchClick) {
            elements.searchBtn.removeEventListener('click', state.eventHandlers.searchClick);
        }

        if (elements.searchInput && state.eventHandlers.searchKeyup) {
            elements.searchInput.removeEventListener('keyup', state.eventHandlers.searchKeyup);
        }

        if (elements.sedeFilterSelect && state.eventHandlers.sedeChange) {
            elements.sedeFilterSelect.removeEventListener('change', state.eventHandlers.sedeChange);
        }

        if (elements.roleFilterSelect && state.eventHandlers.roleChange) {
            elements.roleFilterSelect.removeEventListener('change', state.eventHandlers.roleChange);
        }

        if (elements.newUserBtn && state.eventHandlers.openModal) {
            elements.newUserBtn.removeEventListener('click', state.eventHandlers.openModal);
        }

        if (elements.closeUserBtn && state.eventHandlers.closeModal) {
            elements.closeUserBtn.removeEventListener('click', state.eventHandlers.closeModal);
        }

        if (elements.cancelUserBtn && state.eventHandlers.cancelModal) {
            elements.cancelUserBtn.removeEventListener('click', state.eventHandlers.cancelModal);
        }

        if (elements.createWrapper && state.eventHandlers.backdropClick) {
            elements.createWrapper.removeEventListener('click', state.eventHandlers.backdropClick);
        }

        if (elements.usersTbody && state.eventHandlers.tableClick) {
            elements.usersTbody.removeEventListener('click', state.eventHandlers.tableClick);
        }

        if (elements.userForm && state.eventHandlers.formSubmit) {
            elements.userForm.removeEventListener('submit', state.eventHandlers.formSubmit);
        }
    }

    /**
     * INICIALIZAR MÓDULO
     */
    function init() {

        initElements();
        registerEventListeners();
        cargarUsuarios();
    }

    /**
     * DESTRUIR MÓDULO
     */
    function destroy() {

        removeEventListeners();

        // Limpiar estado
        state.allUsers = [];
        state.eventHandlers = {};
        elements = {};
    }

    // Exponer el módulo
    window.CHEModules.usuarios = {
        init: init,
        destroy: destroy
    };

    // Exponer función para compatibilidad
    window.CHEAdmin.cargarUsuarios = cargarUsuarios;

})();