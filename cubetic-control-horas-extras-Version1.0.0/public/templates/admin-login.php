<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php _e('Control Horario', 'che'); ?></title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4a90a4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Control Horario">
    <link rel="apple-touch-icon" href="/wp-content/plugins/cubetic-control-horas-extras/assets/icons/icon-192x192.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php wp_head(); ?>
</head>

<body class="che-login-page">
    <div class="che-login-container">
        <form id="che-login-form" class="che-login-form" method="POST" action="">
            <div class="che-login-header">
                <h2>Control Horario</h2>
                <p class="che-login-subtitle">Accede a tu espacio de trabajo</p>
            </div>

            <div class="form-group">
                <label for="username" class="form-label"><?php _e('DNI / NIE', 'che'); ?></label>
                <input type="text" class="form-control" id="username" name="username" placeholder="12345678A" required maxlength="9">
            </div>
            <div class="form-group">
                <label for="password" class="form-label"><?php _e('Contraseña', 'che'); ?></label>
                <div style="position:relative;">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button type="button" id="togglePassword"
                        style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer;"
                        aria-label="Mostrar u ocultar contraseña">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
            <input type="hidden" name="che_worker_login" value="1">
            <button type="submit" class="che-login-btn">
                <?php _e('Acceder', 'che'); ?>
            </button>

            <!--  Mejora: enlace para recuperar la contraseña -->
            <div class="mt-3 text-center">
                <a href="#" id="show-reset-form" class="text-muted">
                    <?php _e('¿Olvidaste tu contraseña?', 'che'); ?>
                </a>
            </div>
            
              <!-- Contenedor de mensajes para AJAX (siempre presente) -->
            <div id="login-message" class="che-login-message" style="display:none;"></div>
            
            <?php if (isset($_GET['login_error'])): ?>
                <div class="che-login-message error">
                    <?php
                    if ($_GET['login_error'] == '1') {
                        _e('Credenciales inválidas. Intenta nuevamente.', 'che');
                    } elseif ($_GET['login_error'] == '2') {
                        _e('No tienes permiso para acceder.', 'che');
                    }
                    ?>
                </div>
            <?php endif; ?>
        </form>

        <!-- Formulario de Restablecimiento de Contraseña (oculto por defecto) -->
        <form id="che-reset-form" class="che-login-form" method="POST" action="" style="display:none;">
            <h2 class="mb-4"><?php _e('Restablecer contraseña', 'che'); ?></h2>
            <div class="form-group">
                <label for="reset_email" class="form-label"><?php _e('Introduce tu email', 'che'); ?></label>
                <input type="email" class="form-control" id="reset_email" name="reset_email" required>
            </div>
            <button type="submit" class="che-login-btn">
                <?php _e('Restablecer contraseña', 'che'); ?>
            </button>
            <div class="mt-3 text-center">
                <a href="#" id="back-to-login" class="text-muted">
                    <?php _e('Volver al login', 'che'); ?>
                </a>
            </div>
            <?php if (isset($reset_message)): ?>
                <div id="reset-message" class="che-login-message <?php echo $reset_success ? 'success' : 'error'; ?>">
                    <?php echo esc_html($reset_message); ?>
                </div>
            <?php else: ?>
                <div id="reset-message" class="che-login-message" style="display:none;"></div>
            <?php endif; ?>
        </form>
    </div>

    <?php
    $reset_message = '';
    $reset_success = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_email'])) {
        $user_login = sanitize_email($_POST['reset_email']);
        if (empty($user_login)) {
            $reset_message = __('Debes introducir un email.', 'che');
        } else {
            $user = get_user_by('email', $user_login);
            if (!$user) {
                $reset_message = __('El email no existe en el sistema.', 'che');
            } else {
                $result = retrieve_password($user_login);
                if ($result === true) {
                    $reset_message = __('Te hemos enviado un correo con el enlace para restablecer tu contraseña.', 'che');
                    $reset_success = true;
                } else {
                    $reset_message = __('No se pudo enviar el correo. Intenta de nuevo.', 'che');
                }
            }
        }
    }
    ?>

    <!-- Mejora:  mostrar/ocultar la contraseña mediante un botón con icono de ojo -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.getElementById('togglePassword');
            var pwd = document.getElementById('password');
            if (toggle && pwd) {
                toggle.addEventListener('click', function () {
                    if (pwd.type === 'password') {
                        pwd.type = 'text';
                        toggle.textContent = '🙈';
                    } else {
                        pwd.type = 'password';
                        toggle.textContent = '👁️';
                    }
                });
            }

            var showResetForm = document.getElementById('show-reset-form');
            var resetForm = document.getElementById('che-reset-form');
            var loginForm = document.getElementById('che-login-form');
            var backToLogin = document.getElementById('back-to-login');

            if (showResetForm && resetForm && loginForm && backToLogin) {
                showResetForm.addEventListener('click', function (e) {
                    e.preventDefault();
                    loginForm.style.display = 'none';
                    resetForm.style.display = 'block';
                });

                backToLogin.addEventListener('click', function (e) {
                    e.preventDefault();
                    resetForm.style.display = 'none';
                    loginForm.style.display = 'block';
                });
            }
        });
    </script>
    <?php wp_footer(); ?>
</body>

</html>