<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="che-worker-section" data-section="editar-perfil" style="display:none;">
    <section class="che-worker-profile">
        <h2>🛠️ Editar perfil</h2>

        <div id="che-profile-message" class="che-message"></div>

        <div class="che-profile-block">
            <h3>📷 Foto de perfil</h3>
            <div class="che-profile-avatar">
                <?php
                $user_id = get_current_user_id();
                echo get_avatar( $user_id, 96 );
                ?>
            </div>
            <div class="che-profile-avatar-upload">
                <input
                    id="che-profile-avatar-input"
                    type="file"
                    accept="image/*"
                    class="che-profile-avatar-input"
                >

                <button type="button" class="che-profile-avatar-trigger" id="che-profile-avatar-trigger">
                    Seleccionar imagen
                </button>

                <button class="all-btn primary" type="button" id="che-profile-avatar-save-btn">
                    Guardar foto
                </button>

                <!-- <span class="che-profile-avatar-filename" id="che-profile-avatar-filename">
                    Ningún archivo seleccionado
                </span> -->
            </div>
                
        </div>

        <div class="che-profile-block2">
            <h3>🔒 Cambiar contraseña</h3>
            <div class="che-field-cambio">
            <div class="che-profile-field">
                <label for="che-profile-current-pass">Contraseña actual</label>
                <div class="che-pass-wrapper">
                    <input
                        type="password"
                        id="che-profile-pass"
                        class="che-profile-pass"
                    />
                    <button
                        type="button"
                        class="che-pass-toggle"
                        aria-label="Mostrar u ocultar contraseña"
                        data-target="che-profile-pass"
                    >
                        👁
                    </button>
                </div>
            </div>
            <div class="che-profile-field">
                <label for="che-profile-new-pass">Nueva contraseña</label>
                <div class="che-pass-wrapper">
                    <input type="password" id="che-profile-new-pass" class="che-profile-pass">
                    <button
                        type="button"
                        class="che-pass-toggle"
                        aria-label="Mostrar u ocultar contraseña"
                        data-target="che-profile-new-pass"
                    >
                        👁
                    </button>
                </div>
            </div>
            <div class="che-profile-field">
                <label for="che-profile-new-pass-2">Repetir nueva contraseña</label>
                <div class="che-pass-wrapper">
                    <input type="password" id="che-profile-new-pass-2" class="che-profile-pass">
                    <button
                        type="button"
                        class="che-pass-toggle"
                        aria-label="Mostrar u ocultar contraseña"
                        data-target="che-profile-new-pass-2"
                    >
                        👁
                    </button>
                </div>
            </div>
            </div>
            <div>
            <button class="all-btn primary" type="button" id="che-profile-pass-save-btn">
                Guardar contraseña
            </button>
            </div>
        </div>
    </section>
</div>