<?php
if (!defined('ABSPATH'))
    exit;

// Asegurar variable `$admin` para compatibilidad con la plantilla
$admin = isset($view_data['admin']) ? $view_data['admin'] : [];
$partes = isset($view_data['partes']) ? $view_data['partes'] : [];
$workers = isset($view_data['workers']) ? $view_data['workers'] : [];
$vacaciones = isset($view_data['vacaciones']) ? $view_data['vacaciones'] : [];
$partes_admin = isset($view_data['partes_admin']) ? $view_data['partes_admin'] : [];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestion de Proyectos</title>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4a90a4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Control Horario">
    <link rel="apple-touch-icon" href="<?php echo CHE_URL; ?>assets/icons/icon-192x192.png">
    
    <script>
        // Exponer datos iniciales de PHP a JavaScript
        window.CHEInitialData = {
            workers: <?php echo json_encode($workers); ?>,
            vacaciones: <?php echo json_encode($vacaciones); ?>,
            partes: <?php echo json_encode($partes_admin); ?>,
            contabilidad: <?php echo json_encode(isset($view_data['contabilidad']) ? $view_data['contabilidad'] : []); ?>,
            sedes: <?php echo json_encode(isset($view_data['sedes']) ? $view_data['sedes'] : []); ?>,
            nomina_mes: <?php echo json_encode(isset($view_data['nomina_mes']) ? $view_data['nomina_mes'] : date('Y-m')); ?>
        };
    </script>
    <?php wp_head(); ?>
</head>

<body class="che-admin-page">

    <header class="che-worker-header">
        <div class="che-worker-header-inner">
            <div class="logo-brand">
                <?php 
                $empresa_nombre = get_option('che_empresa_nombre', get_bloginfo('name'));
                if ($empresa_nombre): 
                ?>
                <div class="che-empresa-nombre">
                    <?php echo esc_html($empresa_nombre); ?>
                </div>
                <?php endif; ?>
                <div class="logo">
                    <?php 
                    // Usar la URL directa guardada en la opción - NO mostrar logo por defecto
                    $logo_url = get_option('che_empresa_logo', '');
                    if ($logo_url):
                    ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($empresa_nombre); ?>" class="che-admin-logo">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Badge de Notificaciones -->
            <div class="che-notification-wrapper">
                <button type="button" id="che-notification-btn" class="che-notification-btn" title="Notificaciones">
                    <span class="dashicons dashicons-bell"></span>
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

            <div class="che-worker-user" id="che-user-profile-trigger" style="cursor: pointer;">
                <div class="che-worker-user-info">
                    <span class="che-worker-name">
                        <?php echo esc_html($admin['name'] ?? wp_get_current_user()->display_name); ?>
                    </span>
                    <span class="che-worker-email">
                        <?php echo esc_html($admin['email'] ?? wp_get_current_user()->user_email); ?>
                    </span>
                </div>
                <?php echo get_avatar($admin['id'] ?? get_current_user_id(), 48); ?>
            </div>
        </div>
    </header>


    <main class="che-admin-main">
        <aside class="che-admin-sidebar">
            <nav class="che-admin-menu">
                <ul class="che-admin-menu-list">
                    <li class="has-submenu is-open" data-section="sedes">
                        <button type="button" class="menu-btn menu-parent">
                            <span class="dashicons dashicons-building"></span>
                            <span class="menu-text">Empresas</span>
                            <span class="dashicons dashicons-arrow-down-alt2 submenu-arrow"></span>
                        </button>
                        <ul class="submenu">
                            <li data-section="partes">
                                <button type="button" class="submenu-btn">
                                    <span class="dashicons dashicons-clock"></span>
                                    <span class="menu-text">Gestión de partes</span>
                                </button>
                            </li>
                            <li data-section="vacaciones">
                                <button type="button" class="submenu-btn">
                                    <span class="dashicons dashicons-palmtree"></span>
                                    <span class="menu-text">Gestión de vacaciones</span>
                                </button>
                            </li>
                        </ul>
                    </li>
                    <li data-section="usuarios">
                        <button type="button" class="menu-btn">
                            <span class="dashicons dashicons-admin-users"></span>
                            <span class="menu-text">Trabajadores</span>
                        </button>
                    </li>
                    <?php
                    // Incluir sección CONTABILIDAD solamente si el usuario es super_admin
                    if (isset($admin) && in_array('super_admin', $admin['roles'], true)):
                        ?>
                        <li data-section="contabilidad">
                            <button type="button" class="menu-btn">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <span class="menu-text">Contabilidad</span>
                            </button>
                           <!--  <ul>
                                <li data-section="informes">
                                    <button type="button" class="submenu-btn">
                                        <span class="dashicons dashicons-analytics"></span>
                                        <span class="menu-text">Informes</span>
                                    </button>
                                </li>
                            </ul>-->
                        </li>
                    <?php endif; ?>
                        <li data-section="registros">
                            <button type="button" class="menu-btn">
                                <span class="dashicons dashicons-list-view"></span>
                                <span class="menu-text">Historial</span>
                            </button>
                        </li>
                        <li data-section="notificaciones-usuario">
                            <button type="button" class="menu-btn">
                                <span class="dashicons dashicons-bell"></span>
                                <span class="menu-text">Notificaciones</span>
                            </button>
                        </li>
                        <li data-section="configuracion">
                            <button type="button" class="menu-btn">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <span class="menu-text">Configuración</span>
                            </button>
                        </li>

                </ul>
            </nav>
            <div class="che-admin-sidebar-footer">
                <a href="<?php echo esc_url(wp_logout_url(home_url('/acceso/'))); ?>" class="che-btn-logout"
                    id="admin-cerrar">
                    <span class="dashicons dashicons-exit"></span>
                    <span class="menu-text">Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <section class="che-admin-content">

            <!-- Con esto vamos a cargar la vista de usuarios -->
            <?php
            // Incluir sección CONTABILIDAD solo para super_admin
            if (isset($view_data['admin']['roles']) && in_array('super_admin', $view_data['admin']['roles'], true)) {
                $contabilidad_template = CHE_PATH . 'public/templates/contabilidad/admin-view-contabilidad.php';
                if (file_exists($contabilidad_template)) {
                    include $contabilidad_template;
                }
                // Incluir sección INFORMES dentro de CONTABILIDAD
                /*$informes_template = CHE_PATH . 'public/templates/contabilidad/admin-view-informes.php';
                if (file_exists($informes_template)) {
                    include $informes_template;
                }*/
            }

            // Incluir sección NOTIFICACIONES USUARIO - PRIORIDAD ALTA
            echo "<!-- FORZANDO INCLUDE NOTIFICACIONES -->";
            include CHE_PATH . 'public/templates/admin-view-notificaciones-usuario.php';
            echo "<!-- FIN INCLUDE NOTIFICACIONES -->";

            // Incluir sección CONFIGURACIÓN - PRIORIDAD ALTA
            echo "<!-- FORZANDO INCLUDE CONFIGURACION -->";
            include CHE_PATH . 'public/templates/admin-view-configuracion.php';
            echo "<!-- FIN INCLUDE CONFIGURACION -->";

            // Incluir sección REGISTROS
                $registros_template = CHE_PATH . 'public/templates/admin-view-registros.php';
                if (file_exists($registros_template)) {
                    include $registros_template;
                }
          

            // Incluir sección Usuarios
            $usuarios_template = CHE_PATH . 'public/templates/admin-view-usuarios.php';
            if (file_exists($usuarios_template)) {
                include $usuarios_template;
            }



            $vacaciones_template = CHE_PATH . 'public/templates/admin-view-vacaciones.php';
            if (file_exists($vacaciones_template)) {
                include $vacaciones_template;
            }



            $partes_template = CHE_PATH . 'public/templates/admin-view-partes.php';
            if (file_exists($partes_template)) {
                include $partes_template;
            }

            // Incluir sección SEDES
            $sedes_template = CHE_PATH . 'public/templates/admin-view-sedes.php';
            if (file_exists($sedes_template)) {
                include $sedes_template;
            }

            ?>


        </section>
    </main>

    <footer class="che-admin-footer">
        <p>Control Horas Extras v<?php echo esc_html(CHE_VERSION); ?> &copy; <?php echo date('Y'); ?></p>
    </footer>

    <!-- Modal de Editar Perfil - Estilo Tarifas -->
    <div id="che-edit-profile-modal" class="che-modal" style="display:none;">
        <div class="che-modal-content">
            <div class="che-modal-header">
                <h3>Editar Perfil</h3>
                <button type="button" class="che-modal-close">&times;</button>
            </div>
            <div class="che-modal-body">
                <form id="che-edit-profile-form">
                    <!-- Avatar -->
                    <div class="che-form-group">
                        <label>Foto de Perfil</label>
                        <div style="display:flex; align-items:center; gap:20px;">
                            <div style="width:80px; height:80px; border-radius:50%; overflow:hidden; border:2px solid #e0e0e0;">
                                <?php echo get_avatar(get_current_user_id(), 80); ?>
                            </div>
                            <div>
                                <button type="button" class="all-btn primary" id="che-change-avatar-btn">
                                    <span class="dashicons dashicons-camera"></span> Cambiar Foto
                                </button>
                                <input type="file" id="che-avatar-input" accept="image/*" style="display: none;">
                                <small style="display:block; margin-top:5px; color:#666;">JPG, PNG máx 2MB</small>
                            </div>
                        </div>
                    </div>

                    <!-- Datos Personales -->
                    <div class="che-form-group">
                        <label for="che-edit-nombre">Nombre Completo *</label>
                        <input type="text" id="che-edit-nombre" class="che-input" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" required>
                    </div>

                    <div class="che-form-group">
                        <label for="che-edit-usuario">Nombre de Usuario *</label>
                        <input type="text" id="che-edit-usuario" class="che-input" value="<?php echo esc_attr(wp_get_current_user()->user_login); ?>" required>
                        <small>El nombre de usuario no se puede cambiar en WordPress</small>
                    </div>

                    <div class="che-form-group">
                        <label for="che-edit-email">Correo Electrónico *</label>
                        <input type="email" id="che-edit-email" class="che-input" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required>
                    </div>

                    <!-- Cambiar Contraseña -->
                    <hr style="margin:20px 0; border:none; border-top:1px solid #e0e0e0;">
                    <h4 style="margin-bottom:15px;">Cambiar Contraseña (Opcional)</h4>
                    
                    <div class="che-form-group">
                        <label for="che-current-password">Contraseña Actual</label>
                        <input type="password" id="che-current-password" class="che-input" autocomplete="current-password">
                    </div>
                    
                    <div class="che-form-group">
                        <label for="che-new-password">Nueva Contraseña</label>
                        <input type="password" id="che-new-password" class="che-input" autocomplete="new-password">
                    </div>
                    
                    <div class="che-form-group">
                        <label for="che-confirm-password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="che-confirm-password" class="che-input" autocomplete="new-password">
                    </div>
                </form>
            </div>
            <div class="che-modal-footer">
                <button type="button" class="all-btn primary" id="che-cancel-profile-btn">Cancelar</button>
                <button type="button" class="all-btn success" id="che-save-profile-btn">
                    <span class="dashicons dashicons-saved"></span> Guardar Cambios
                </button>
            </div>
        </div>
    </div>

    <style>
    .che-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 9999;
    }

    .che-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
    }

    .che-modal-content {
        position: relative;
        max-width: 600px;
        margin: 50px auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        max-height: 90vh;
        overflow-y: auto;
    }

    .che-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        border-bottom: 1px solid #e0e0e0;
    }

    .che-modal-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
    }

    .che-modal-close {
        background: transparent;
        border: none;
        cursor: pointer;
        color: #666;
        padding: 4px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .che-modal-close:hover {
        background: #f5f5f5;
        color: #333;
    }

    .che-modal-close .dashicons {
        font-size: 24px;
        width: 24px;
        height: 24px;
    }

    .che-modal-body {
        padding: 30px;
    }

    .che-profile-avatar-section {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e0e0e0;
    }

    .che-profile-avatar img {
        border-radius: 50%;
        width: 120px;
        height: 120px;
    }

    .che-profile-section {
        margin-bottom: 20px;
    }

    .che-profile-section h4 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        color: #333;
    }

    .che-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 30px;
        border-top: 1px solid #e0e0e0;
    }

    #che-user-profile-trigger:hover {
        opacity: 0.8;
    }
    </style>

    <?php wp_footer(); ?>
</body>

</html>