<?php
if (!defined('ABSPATH')) exit;


?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel del Trabajador - <?php echo esc_html($view_data['worker']['name'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"/>
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4a90a4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Control Horario">
    <link rel="apple-touch-icon" href="<?php echo CHE_URL; ?>assets/icons/icon-192x192.png">
    
    <?php wp_head(); ?>
</head>
<body class="che-worker-page">
    <div class="che-part-loader" id="che-part-loader">
         <div class="che-part-loader-backdrop"></div>
         <div class="che-part-loader-box">
              <div class="che-spinner"></div>
              <p>Procesando parte...</p>
        </div>
    </div>

<header class="che-worker-header">
    <div class="che-worker-header-inner">
        <div class="seccion-izquierda">
        <button type="button" class="che-worker-menu-toggle" aria-label="Abrir menú">
            ☰
        </button>

        <!-- Badge de Notificaciones -->
        <div class="che-notification-wrapper">
            <button type="button" id="che-notification-btn" class="che-notification-btn" title="Notificaciones">
                <span class="che-notification-icon">🔔</span>
                <span class="che-notification-text">Notificaciones</span>
                <span id="che-notification-badge" class="che-notification-badge" style="display:none;">
                    <span id="che-notification-count">0</span>
                </span>
            </button>
            <!-- Dropdown de notificaciones -->
            <div id="che-notification-dropdown" class="che-notification-dropdown">
                <div class="che-notification-dropdown-header">
                    <h4>Notificaciones</h4>
                    <button type="button" id="che-mark-all-read" class="che-mark-all-read">Marcar todas como leídas</button>
                </div>
                <div id="che-notification-list" class="che-notification-list">
                    <div class="che-notification-loading">Cargando...</div>
                </div>
                <div class="che-notification-dropdown-footer">
                    <button type="button" id="che-close-dropdown" class="che-go-to-section">Cerrar</button>
                </div>
            </div>
        </div>
        </div>
        <div class="che-worker-user" data-section-target="editar-perfil">
            <div class="che-worker-user-info">
                <span class="che-worker-name">
                    <?php echo esc_html($view_data['worker']['name'] ?? wp_get_current_user()->display_name); ?>
                </span>
                <span class="che-worker-email">
                    <?php echo esc_html($view_data['worker']['email'] ?? wp_get_current_user()->user_email); ?>
                </span>
            </div>
            <?php echo get_avatar($view_data['worker']['id'] ?? get_current_user_id(), 48); ?>
        </div>
    </div>
</header>

<main class="che-worker-main">
    <aside class="che-worker-sidebar">
        <nav class="che-worker-menu">
            <ul class="che-worker-menu-list">
                <li class="is-active" data-section="jornada">
                    <button type="button" class="menu-btn">
                        <span class="dashicons dashicons-clock"></span>
                        <span class="menu-text">Control horario</span>
                    </button>
                </li>
                <!-- <li data-section="partes">
                    <button type="button" class="menu-btn">
                        <span class="dashicons dashicons-list-view"></span>
                        <span class="menu-text">Partes</span>
                    </button>
                </li> -->
                <li data-section="vacaciones">
                    <button type="button" class="menu-btn">
                        <span class="dashicons dashicons-palmtree"></span>
                        <span class="menu-text">Vacaciones</span>
                    </button>
                </li>
                <li data-section="editar-perfil">
                    <button type="button" class="menu-btn">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span class="menu-text">Editar perfil</span>
                    </button>
                </li>
                <li data-section="notificaciones">
                    <button type="button" class="menu-btn">
                        <span class="dashicons dashicons-bell"></span>
                        <span class="menu-text">Notificaciones</span>
                    </button>
                </li>
            </ul>
        </nav>

        <div class="che-worker-sidebar-footer">
            <a href="<?php echo esc_url( wp_logout_url( home_url( '/acceso/' ) ) ); ?>"
               class="che-btn-logout"
               id="worker-cerrar">
                <span class="dashicons dashicons-exit"></span>
                <!-- <span class="menu-text">Cerrar sesión</span> -->
            </a>
        </div>
    </aside>
<section class="che-worker-content">
    <div class="che-worker-section" data-section="jornada">
    <section class="che-part-panel">
        <h2>🕒 Control de jornada</h2>

        <div class="che-part-status">
            Estado actual:
            <strong id="che-status-text">⏸️ Fuera de jornada</strong>
        </div>

        <div class="che-part-field">
            <label for="che-proyecto">📍 Proyecto / Localización</label>
            <input type="text" id="che-proyecto" placeholder="Proyecto o descripción corta">
        </div>
        <div class="che-part-field-2">
            <label>🗂️ Adjuntar archivos</label>

            <div class="che-dropzonediv">
                <div id="che-dropzone" class="che-dropzone">
                    <button class="all-btn primary">Subir archivos</button>
                </div>
            </div>

            <input
                type="file"
                id="che-parte-attachments"
                multiple
                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                style="display:none;"
            >

            <ul id="che-files-list" class="che-files-list"></ul>

            <!-- <small>Puedes subir imágenes o documentos (varios a la vez).</small> -->
        </div>
        <div>
            <p>Tiempo transcurrido: <span id="che-timer">00:00:00</span></p>
        </div>

        <div class="che-part-buttons">
            <button id="che-start-btn" class="button button-primary">
                Iniciar parte
            </button>
            <button id="che-create-manual-btn" class="button">
                Crear parte
            </button>
            <button id="che-stop-btn" class="button" style="display:none;">
                Finalizar parte
            </button>

        </div>

        <p id="che-parte-warning" class="che-parte-warning" style="display:none;">
            El parte solo será válido si se finaliza en el centro de trabajo.
        </p>
        
        <div id="che-parte-toast" class="che-parte-toast">
            <span id="che-parte-toast-text"></span>
        </div>

        <div id="che-message" class="che-message" style="display: none;"></div>
    </section>
    </div>
    <!-- MODAL PARTE MANUAL (oculto por defecto) -->
        <div class="che-manual-modal" id="che-manual-modal">
            <div class="che-manual-modal__backdrop"></div>

            <div class="che-manual-modal__dialog">
                <div class="che-manual-modal__loader" id="che-manual-loader">
                    <div class="che-manual-modal__loader-backdrop"></div>
                    <div class="che-manual-modal__loader-box">
                        <div class="che-spinner"></div>
                        <p>Procesando parte...</p>
                    </div>
                </div>
                <div class="che-manual-modal__result" id="che-manual-result">
                    <div class="che-manual-modal__result-box">
                        <p id="che-manual-result-text"></p>
                    </div>
                </div>
                <div class="che-manual-modal__header">
                    <h3 class="che-manual-modal__title">Nuevo parte</h3>
                    <button type="button" class="che-manual-modal__close" id="che-manual-close">
                        &times;
                    </button>
                </div>

                <div class="che-manual-modal__body">
                    <div class="che-manual-form-section">
                        <div class="che-manual-form-section__subtitle">
                            Datos del parte
                        </div>

                        <div class="che-manual-form-group">
                            <label class="che-manual-form-label">
                                Hora de inicio
                            </label>

                            <div class="che-manual-timepicker" id="che-manual-timepicker">
                                <div class="che-manual-timepicker__col" data-type="hour" id="che-manual-timepicker-hours"></div>
                                <span class="che-manual-timepicker__separator">:</span>
                                <div class="che-manual-timepicker__col" data-type="minute" id="che-manual-timepicker-minutes"></div>
                            </div>

                            <!-- valor real que usa tu JS (HH:MM) -->
                            <input type="hidden" id="che-manual-hora-inicio">
                        </div>


                        <div class="che-manual-form-group">
                            <label for="che-manual-proyecto" class="che-manual-form-label">
                                Proyecto / Localización
                            </label>
                            <input
                                type="text"
                                id="che-manual-proyecto"
                                class="che-manual-form-input"
                                placeholder="Proyecto o descripción corta"
                            >
                        </div>

                        <div class="che-manual-form-group">
                            <label for="che-manual-attachments" class="che-manual-form-label">
                                Adjuntar archivos
                            </label>
                            <input
                                type="file"
                                id="che-manual-attachments"
                                class="che-manual-form-input-file"
                                multiple
                                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                            >
                            <!-- <small class="che-manual-form-help">
                                Puedes subir imágenes o documentos.
                            </small> -->
                        </div>
                    </div>
                </div>

                <div class="che-manual-modal__footer">
                    <button type="button" class="che-manual-btn che-manual-btn--secondary" id="che-manual-cancel">
                        Cancelar
                    </button>
                    <button type="button" class="che-manual-btn che-manual-btn--primary" id="che-manual-save">
                        Crear parte
                    </button>
                </div>
            </div>
        </div>
    <!-- añadimos la seccion de partes -->
    <?php
    $worker_partes_template = CHE_PATH . 'public/templates/worker-view-partes.php';
    if ( file_exists( $worker_partes_template ) ) {
        include $worker_partes_template;
    }
    ?>
    <?php
    $worker_vacaciones_template = CHE_PATH . 'public/templates/worker-view-vacaciones.php';
    if ( file_exists( $worker_vacaciones_template ) ) {
        include $worker_vacaciones_template;
    }
    ?>
    <?php
    $worker_editar_perfil_template = CHE_PATH . 'public/templates/worker-view-editar-perfil.php';
    if ( file_exists( $worker_editar_perfil_template ) ) {
        include $worker_editar_perfil_template;
    }
    ?>
    <?php
    $worker_notificaciones_template = CHE_PATH . 'public/templates/worker-view-notificaciones.php';
    if ( file_exists( $worker_notificaciones_template ) ) {
        include $worker_notificaciones_template;
    }
    ?>
</section>
</main>

<?php wp_footer(); ?>
</body>
</html>