(function () {
    const apiUrl = window.cheAjax?.api_url || '/wp-json/che/v1/';
    const messageBox = document.getElementById('che-profile-message');
    const avatarInput = document.getElementById('che-profile-avatar-input');
    const avatarSaveBtn = document.getElementById('che-profile-avatar-save-btn');
    const avatarTrigger  = document.getElementById('che-profile-avatar-trigger');
    const avatarFilename = document.getElementById('che-profile-avatar-filename');
    const currentPassInput = document.getElementById('che-profile-current-pass');
    const newPassInput = document.getElementById('che-profile-new-pass');
    const newPass2Input = document.getElementById('che-profile-new-pass-2');
    const passSaveBtn = document.getElementById('che-profile-pass-save-btn');

    if (!messageBox) return;

    function setMessage(msg, isError = false) {
        messageBox.textContent = msg;
        messageBox.style.color = isError ? 'red' : 'green';
    }

    // Botón personalizado para seleccionar avatar
    if (avatarTrigger && avatarInput) {
        avatarTrigger.addEventListener('click', () => {
            avatarInput.click();
        });

        avatarInput.addEventListener('change', () => {
            if (!avatarInput.files || !avatarInput.files.length) {
                if (avatarFilename) {
                    avatarFilename.textContent = 'Ningún archivo seleccionado';
                }
                return;
            }
            const file = avatarInput.files[0];
            if (avatarFilename) {
                avatarFilename.textContent = file.name;
            }
        });
    }

    async function guardarAvatar() {
        if (!avatarInput || !avatarInput.files.length) {
            setMessage('Selecciona una imagen primero.', true);
            return;
        }
        const file = avatarInput.files[0];
        const formData = new FormData();
        formData.append('avatar', file);

        try {
            const res = await fetch(apiUrl + 'perfil/avatar', {
                method: 'POST',
                headers: { 'X-WP-Nonce': window.cheAjax?.nonce || '' },
                body: formData
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'No se pudo actualizar la foto.');
            }
            setMessage('Foto de perfil actualizada.');
            // opcional: recargar la página o la imagen
            /* window.location.reload(); */
        } catch (e) {
            setMessage(e.message || 'Error al subir la foto.', true);
        }
    }

    async function guardarPassword() {
        const currentPass = currentPassInput?.value || '';
        const newPass = newPassInput?.value || '';
        const newPass2 = newPass2Input?.value || '';

        if (!currentPass || !newPass || !newPass2) {
            setMessage('Rellena todos los campos de contraseña.', true);
            return;
        }
        if (newPass !== newPass2) {
            setMessage('Las contraseñas nuevas no coinciden.', true);
            return;
        }

        try {
            const res = await fetch(apiUrl + 'perfil/password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.cheAjax?.nonce || ''
                },
                body: JSON.stringify({
                    current_password: currentPass,
                    new_password: newPass
                })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'No se pudo actualizar la contraseña.');
            }
            setMessage('Contraseña actualizada correctamente.');
            currentPassInput.value = '';
            newPassInput.value = '';
            newPass2Input.value = '';
        } catch (e) {
            setMessage(e.message || 'Error al actualizar la contraseña.', true);
        }
    }

    if (avatarSaveBtn && avatarInput) {
        avatarSaveBtn.addEventListener('click', guardarAvatar);
    }
    if (passSaveBtn) {
        passSaveBtn.addEventListener('click', guardarPassword);
    }
     const passToggles = document.querySelectorAll('.che-pass-toggle');

    passToggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.textContent = isPassword ? '👁' : '👁';
        });
    });
})();