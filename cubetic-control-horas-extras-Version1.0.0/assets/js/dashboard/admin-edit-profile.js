// Modal de Editar Perfil
(function() {
    'use strict';

    const apiUrl = window.cheAjax?.api_url || '/wp-json/che/v1/';
    const nonce = window.cheAjax?.nonce || '';
    
    const modal = document.getElementById('che-edit-profile-modal');
    const openBtn = document.getElementById('che-user-profile-trigger');
    const closeBtn = modal?.querySelector('.che-modal-close');
    const cancelBtn = document.getElementById('che-cancel-profile-btn');
    const saveBtn = document.getElementById('che-save-profile-btn');
    const avatarBtn = document.getElementById('che-change-avatar-btn');
    const avatarInput = document.getElementById('che-avatar-input');

    // Abrir modal
    if (openBtn && modal) {
        openBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });
    }

    // Cerrar modal
    [closeBtn, cancelBtn].forEach(btn => {
        if (btn && modal) {
            btn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }
    });

    // Cambiar avatar
    if (avatarBtn && avatarInput) {
        avatarBtn.addEventListener('click', () => avatarInput.click());
        avatarInput.addEventListener('change', handleAvatarChange);
    }

    // Guardar perfil
    if (saveBtn) {
        saveBtn.addEventListener('click', handleSaveProfile);
    }

    async function handleAvatarChange(e) {
        if (!avatarInput.files || !avatarInput.files.length) return;

        const file = avatarInput.files[0];
        if (file.size > 2 * 1024 * 1024) {
            alert('El archivo es muy grande. Máximo 2MB.');
            return;
        }

        const formData = new FormData();
        formData.append('avatar', file);

        try {
            const res = await fetch(`${apiUrl}perfil/avatar`, {
                method: 'POST',
                headers: { 'X-WP-Nonce': nonce },
                body: formData
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al subir foto');
            }
            alert('Foto actualizada correctamente');
            location.reload();
        } catch (e) {
            alert(e.message || 'Error al subir la foto');
        }
    }

    async function handleSaveProfile() {
        const nombre = document.getElementById('che-edit-nombre')?.value || '';
        const email = document.getElementById('che-edit-email')?.value || '';
        const currentPass = document.getElementById('che-current-password')?.value || '';
        const newPass = document.getElementById('che-new-password')?.value || '';
        const confirmPass = document.getElementById('che-confirm-password')?.value || '';

        if (!nombre || !email) {
            alert('Nombre y correo son obligatorios');
            return;
        }

        if (newPass && newPass !== confirmPass) {
            alert('Las contraseñas no coinciden');
            return;
        }

        const payload = { nombre, email };
        if (currentPass && newPass) {
            payload.current_password = currentPass;
            payload.new_password = newPass;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="dashicons dashicons-update dashicons-spin"></span> Guardando...';

        try {
            const res = await fetch(`${apiUrl}editar-perfil`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Error al guardar');
            }
            alert('Perfil actualizado correctamente');
            location.reload();
        } catch (e) {
            alert(e.message || 'Error al actualizar el perfil');
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<span class="dashicons dashicons-saved"></span> Guardar Cambios';
        }
    }
})();
