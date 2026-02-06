<?php
namespace ControlHoras\Admin;

//define el espacio de nombre e importa clases para usar nombres cortos

use ControlHoras\Admin\CPT\HorasExtras;
use ControlHoras\Admin\CPT\HorasExtrasMeta;
use ControlHoras\Admin\CPT\Sedes;
use ControlHoras\Admin\CPT\SedesMeta;
use ControlHoras\Admin\CPT\Vacaciones;
use ControlHoras\Admin\CPT\VacacionesMeta;

use ControlHoras\Admin\Router\Router;

use ControlHoras\Admin\Auth\AuthHandler;

use ControlHoras\Includes\Controllers\DashboardController;
use ControlHoras\Includes\Controllers\WorkerViewController;
use ControlHoras\Includes\Controllers\AdminViewController;
use ControlHoras\Includes\Controllers\ContabilidadController;
use ControlHoras\Includes\Controllers\ConfiguracionController;


use ControlHoras\Includes\Api\TimesheetController;
use ControlHoras\Includes\Api\SedeController;
use ControlHoras\Includes\Api\VacacionesController;
use ControlHoras\Includes\Api\WorkerController;
use ControlHoras\Includes\Api\EditarPerfilController;

use ControlHoras\Includes\Controllers\WorkerNotificationsController;
use ControlHoras\Includes\Controllers\NotificationsController;
use ControlHoras\Includes\Api\ActivityLogController;

/**
 * Clase Init del plugin Control Horas Extras
 */
class Init
{
    private static $instance = null;
    public $database;
    public $router;

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_components();
        $this->define_hooks();
        $this->ensure_onesignal_worker(); // Verificar que OneSignalSDKWorker.js existe
        $this->ensure_manifest_json(); // Verificar que manifest.json existe

    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Carga los archivos necesarios del plugin
     */
    private function load_dependencies()
    {
        
        $files = [

            //Admin
           
            "admin/CPT/gestion_horas_extras.php",
            "admin/CPT/gestion_horas_extras_meta.php",
            "admin/CPT/gestion_sedes.php",
            "admin/CPT/gestion_sedes_meta.php",
            "admin/CPT/gestion_vacaciones.php",
            "admin/CPT/gestion_vacaciones_meta.php",
            "admin/Router/Router.php",
            "admin/Auth/AuthHandler.php",
            
            //Controladores
            "includes/Controllers/DashboardController.php",
            "includes/Controllers/WorkerViewController.php",
            "includes/Controllers/AdminViewController.php",
            "includes/Controllers/NotificationsController.php",
            "includes/Controllers/ContabilidadController.php",
            "includes/Controllers/ConfiguracionController.php",
            "includes/Controllers/WorkerNotificationsController.php",

            //Modelos
            "includes/Models/Parte.php",
            "includes/Models/Sede.php",
            "includes/Models/Contabilidad.php",
            "includes/Models/Perfil.php",

            //API
            "includes/Api/TimesheetController.php",
            "includes/Api/SedeController.php",
            "includes/Api/VacacionesController.php",
            "includes/Api/WorkerController.php",
            "includes/Api/EditarPerfilController.php",
            "includes/Api/ActivityLogController.php",

            // Hooks comunes
            "includes/avatar-hooks.php"

        ];

        $this->load_files($files);
    }


    /**
     * carga dinamica de archivos
     */
    private function load_files($files)
    {
        foreach ($files as $file) {
            $filepath = CHE_PATH . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            } else {
                error_log("Fichero no encontrado: " . $filepath);
            }
        }
    }

    
    /**
     * Inicializa los componentes del plugin
     */
    private function init_components()
    {
        $this->init_cpt();
        
        
         $this->init_admin_components();
         $this->init_controllers();
    }

    /**
     * Inicializa los componentes del área de administración
     */
    private function init_admin_components()
    {
        AuthHandler::get_instance();
        Router::get_instance();
    }

    /**
     * Inicializa los Custom Post Types
     */
    private function init_cpt()
    {
        
        HorasExtrasMeta::init();
        SedesMeta::init();
        VacacionesMeta::init();
    }

    /**
     * Registra los Custom Post Types y taxonomías
     */
    public function register_post_types_and_taxonomies()
    {
        
        HorasExtras::register();
        Sedes::register();
        Vacaciones::register();
    }

    /**
     * Registra el menú principal de Gestión Empresarial
     */
    public function register_main_menu()
    {
        add_menu_page(
            __('Gestión Empresarial', 'gestion-he'),     // Título de la página
            __('Gestión Empresarial', 'gestion-he'),     // Título del menú
            'edit_posts',                                  // Capacidad requerida
            'gestion-empresarial',                        // Slug del menú
            '',                                           // Función callback (vacía, los submenús lo manejan)
            'dashicons-building',                         // Icono del menú
            21                                            // Posición
        );
    }

    /**
     * Inicializa los controladores del plugin
     */
    private function init_controllers()
    {
        DashboardController::get_instance();
        WorkerViewController::get_instance();
        AdminViewController::get_instance();
        ContabilidadController::get_instance();
        ConfiguracionController::get_instance();
        WorkerNotificationsController::get_instance();
        
        // Inicializar controlador de notificaciones y registrar AJAX hooks
        $notifications = NotificationsController::get_instance();
        $notifications->register_ajax_hooks();
        
        new ActivityLogController();
    }

    /**
     * Encola los assets públicos del plugin
     */
    public function enqueue_public_assets()
    {
    // Cargar OneSignal SDK y notificaciones en todas las vistas del plugin (admin y worker)
    if ( get_query_var( 'admin_dashboard' ) || get_query_var( 'worker_dashboard' ) ) {
        // CSS de notificaciones
        wp_enqueue_style(
            'che-notifications-css',
            CHE_CSS_URL . 'notifications.css',
            [],
            CHE_VERSION
        );

        // Configuración para OneSignal (cargar primero)
        $config_file = CHE_PATH . 'config/onesignal-config.php';
        $onesignal_config = [];
        
        if (file_exists($config_file)) {
            $onesignal_config = require $config_file;
            // Validar que sea un array
            if (!is_array($onesignal_config)) {
                $onesignal_config = [];
                error_log('[CHE Notifications] Archivo onesignal-config.php no retorna un array válido');
            }
        } else {
            error_log('[CHE Notifications] Archivo onesignal-config.php no encontrado en: ' . $config_file);
        }
        
        $current_user = wp_get_current_user();
        $app_id = isset($onesignal_config['app_id']) ? sanitize_text_field($onesignal_config['app_id']) : '';
        
        // SDK de OneSignal (carga desde CDN con defer)
        wp_enqueue_script(
            'onesignal-sdk',
            'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js',
            [],
            '16.0.0',
            false // Cargar en head
        );
        
        // Añadir atributo async al script (OneSignal recomienda carga asíncrona)
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'onesignal-sdk') {
                return str_replace('<script ', '<script async ', $tag);
            }
            return $tag;
        }, 10, 2);
        
        // Nota: la inicialización de OneSignal se realiza en el script cliente
        // (`notifications.js`) para evitar condiciones de carrera entre el SDK
        // y los callbacks. Aquí solo inyectamos el SDK; la configuración (init)
        // se realiza en el JavaScript del plugin.

        // Script de notificaciones del plugin
        wp_enqueue_script(
            'che-notifications',
            CHE_JS_URL . 'notifications/notifications.js',
            ['onesignal-sdk'],
            CHE_VERSION,
            true
        );
        
        // Configuración para el cliente JavaScript
        // Generar URL de AJAX de forma más robusta
        $ajax_url = admin_url('admin-ajax.php');
        // Si admin_url falla, intentar construir manualmente
        if (empty($ajax_url) || strpos($ajax_url, 'admin-ajax.php') === false) {
            $ajax_url = home_url('/wp-admin/admin-ajax.php');
        }
        
        // Log para debug (solo en desarrollo)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CHE Notifications] ajaxUrl generado: ' . $ajax_url);
        }
        
        wp_localize_script('che-notifications', 'cheNotifications', [
            'appId'      => $app_id,
            'userId'     => absint(get_current_user_id()),
            'userRoles'  => array_map('sanitize_text_field', (array) $current_user->roles),
            'apiUrl'     => esc_url_raw(rest_url('che/v1/')),
            'ajaxUrl'    => esc_url_raw($ajax_url),
            'nonce'      => wp_create_nonce('wp_rest'),
            'ajaxNonce'  => wp_create_nonce('che_ajax_nonce'), // Nonce específico para AJAX
        ]);
    }

    // Vista admin (frontend): CSS + JS específicos
    if ( get_query_var( 'admin_dashboard' ) ) {

        // Cargar dashicons para los iconos del menú
        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'che-admin-view',
            CHE_CSS_URL . 'admin-view.css',
            ['dashicons'],
            CHE_VERSION
        );

         wp_enqueue_style(
            'che-admin-contabilidad',
            CHE_CSS_URL . 'admin/admin-contabilidad.css',
            ['dashicons'],
            CHE_VERSION 
        );

        wp_enqueue_style(
            'che-admin-registros',
            CHE_CSS_URL . 'admin/admin-registros.css',
            ['dashicons'],
            CHE_VERSION 
        );

        wp_enqueue_style(
            'che-admin-dashboard',
            CHE_CSS_URL . 'admin/dashboard.css',
            ['dashicons'],
            CHE_VERSION
        );



        

        // Solo cargar el script principal (orquestador)
        wp_enqueue_script(
            'che-admin-panel',
            CHE_JS_URL . 'dashboard/admin-panel.js',
            [],
            CHE_VERSION,
            true
        );

        // Script de editar perfil (siempre disponible)
        wp_enqueue_script(
            'che-admin-edit-profile',
            CHE_JS_URL . 'dashboard/admin-edit-profile.js',
            [],
            CHE_VERSION,
            true
        );

        // Encargar el script de registros directamente
        wp_enqueue_script(
            'che-admin-registros',
            CHE_JS_URL . 'dashboard/admin-registros.js',
            [],
            CHE_VERSION,
            true
        );

        // Pasar configuración al JavaScript para carga dinámica
        wp_localize_script('che-admin-panel', 'cheAjax', [
            'api_url' => rest_url('che/v1/'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wp_rest'),
            'js_url'  => CHE_JS_URL . 'dashboard/',
            'css_url' => CHE_CSS_URL . 'admin/',
            'version' => CHE_VERSION,
            // Mapeo de secciones a sus archivos JS/CSS
            'modules' => [
                'partes' => [
                    'js' => ['admin-parts.js'],
                    'css' => []
                ],
                'usuarios' => [
                    'js' => ['admin-users.js'],
                    'css' => []
                ],
                'sedes' => [
                    'js' => ['admin-sedes.js', 'admin-sedes-calendario.js'],
                    'css' => []
                ],
                'vacaciones' => [
                    'js' => ['admin-vacaciones.js'],
                    'css' => []
                ],
                'contabilidad' => [
                    'js' => ['admin-contabilidad.js'],
                    'css' => ['admin-contabilidad.css']
                ],
                'registros' => [
                    'js' => ['admin-registros.js'],
                    'css' => ['admin-registros.css']
                ],
                'notificaciones-usuario' => [
                    'js' => ['admin-notificaciones-usuario.js'],
                    'css' => ['admin-configuracion.css']
                ],
                'configuracion' => [
                    'js' => ['admin-configuracion.js'],
                    'css' => ['admin-configuracion.css']
                ]
            ]
        ]);

        // Pasar nonce adicional para el script de registros
        wp_localize_script('che-admin-registros', 'cheRegistrosConfig', [
            'nonce' => wp_create_nonce('wp_rest'),
            'api_url' => rest_url('che/v1/')
        ]);

        return; // Importante: salimos para no cargar CSS/JS del worker
    }

    /*
    Resto de vistas públicas (trabajador, login, etc.)
    */

    wp_enqueue_style('che-login-css', CHE_CSS_URL . 'login.css');
    wp_enqueue_style(
        'che-worker-panel',
        CHE_CSS_URL . 'worker-views.css',
        ['dashicons'],
        CHE_VERSION
    );
    wp_enqueue_style('che-worker-panel', CHE_CSS_URL . 'worker-views.css');

    
    // Script núcleo del panel de trabajador (orquestador)

    wp_enqueue_script(
        'che-worker-panel',
        CHE_JS_URL . 'Worker/worker-panel.js',
        [],
        CHE_VERSION,
        true
    );

    // Configuración global para el worker
    wp_localize_script('che-worker-panel', 'cheWorkerAjax', [
        'api_url'  => rest_url('che/v1/'),
        'nonce'    => wp_create_nonce('wp_rest'),
        'js_url'   => CHE_JS_URL . 'Worker/',
        'version'  => CHE_VERSION,
        'modules'  => [
            // keys = data-section de tus vistas
            'jornada' => [
                'js'  => ['worker-jornada.js'],   // lógica de control de jornada
                'css' => []
            ],
            'vacaciones' => [
                'js'  => ['worker-vacaciones.js'],
                'css' => []
            ],
            'editar-perfil' => [
                'js'  => ['worker-editar-perfil.js'],
                'css' => []
            ],
            'notificaciones' => [
                'js'  => ['worker-notificaciones.js'],
                'css' => []
            ],
        ],
    ]);





    wp_localize_script('che-worker-panel', 'cheAjax', [
        'api_url'         => rest_url('che/v1/'),
        'current_user_id' => get_current_user_id(),
        'nonce'           => wp_create_nonce('wp_rest'),
    ]);

    // Datos para subir archivos vía WP REST API
    wp_localize_script('che-worker-panel', 'wpApiSettings', [
        'root'  => esc_url_raw( rest_url() ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ]);

    if ( get_query_var('admin_login') ) {
        wp_enqueue_script(
            'che-login',
            CHE_JS_URL . 'login.js',
            ['jquery'],
            CHE_VERSION,
            true
        );

        wp_localize_script('che-login', 'cheAuth', [
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'che_auth_nonce' => wp_create_nonce('che_worker_login'),
        ]);
        }
    }
    
    /**
     * Registra los hooks del plugin
     */
    private function define_hooks()
    {
       
        add_action('init', [$this, 'register_post_types_and_taxonomies']);
        add_action('admin_menu', [$this, 'register_main_menu']);
       
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        
        // Añadir link al manifest.json en el <head> para PWA (Android e iOS)
        add_action('wp_head', [$this, 'add_manifest_link'], 1);
        
        // Ocultar la barra de administración en las páginas personalizadas del plugin
        add_filter('show_admin_bar', [$this, 'hide_admin_bar_on_custom_pages']);

        // Rutas REST del plugin
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        // Cuando la URL tenga ?worker_dashboard=1, mostrar el panel del trabajador
        add_filter('template_include', function ($template) {
            
            /**
             * Cuando la URL tenga ?worker_dashboard=1, mostrar el panel del trabajador
             */
            if (get_query_var('worker_dashboard') == 1) {

                $user_id = get_current_user_id();
                if (!$user_id) {
                    wp_redirect(home_url('/acceso/'));
                    exit;
                }

                // Renderizar la vista usando tu controlador (este ya prepara $view_data)
                WorkerViewController::get_instance()->render_view();

                exit; // Importante: evitar que WordPress cargue otra plantilla
            }

            /**
             * Cuando la URL tenga ?admin_dashboard=1, mostrar el panel de administración de partes
             */
            if ( get_query_var('admin_dashboard') == 1 ) {

                $user_id = get_current_user_id();
                if ( ! $user_id ) {
                    wp_redirect( home_url( '/acceso/' ) );
                    exit;
                }

                AdminViewController::get_instance()->render_view();
                exit;
            }

            return $template;
        });
        }

        // Rutas REST del plugin
            public function register_rest_routes()
        {
            $partes_controller = new TimesheetController();
            $partes_controller->register_routes();

            $sede_controller = new SedeController();
            $sede_controller->register_routes();

            $vacaciones_controller = new VacacionesController();
            $vacaciones_controller->register_routes();

            $worker_controller = new WorkerController();
            $worker_controller->register_routes();

            $perfil_controller = new EditarPerfilController();
            $perfil_controller->register_routes();

            $notifications_controller = NotificationsController::get_instance();
            $notifications_controller->register_routes();
        }

    /**
     * Oculta la barra de administración de WordPress en las páginas personalizadas del plugin
     * 
     * @param bool $show_admin_bar
     * @return bool
     */
    public function hide_admin_bar_on_custom_pages($show_admin_bar)
    {
        // Ocultar en el dashboard del trabajador
        if (get_query_var('worker_dashboard') == 1) {
            return false;
        }

        // Ocultar en el dashboard de administración
        if (get_query_var('admin_dashboard') == 1) {
            return false;
        }

        // Ocultar en la página de acceso/login
        if (get_query_var('admin_login')) {
            return false;
        }

        return $show_admin_bar;
    }

    /**
     * Verifica y crea OneSignalSDKWorker.js si no existe
     * Esto asegura que el archivo esté siempre presente en la raíz del sitio
     */
    private function ensure_onesignal_worker() {
        $onesignal_worker = ABSPATH . 'OneSignalSDKWorker.js';
        
        // Solo verificar una vez cada hora para evitar demasiadas escrituras
        $last_check = get_transient('che_onesignal_worker_check');
        if ($last_check && (time() - $last_check) < 3600) {
            return; // Ya se verificó recientemente
        }
        
        $content = 'importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");' . "\n";
        
        // Crear el archivo si no existe o si está desactualizado
        $needs_update = true;
        if (file_exists($onesignal_worker)) {
            $current_content = @file_get_contents($onesignal_worker);
            if ($current_content === $content) {
                $needs_update = false;
            }
        }
        
        if ($needs_update) {
            $result = @file_put_contents($onesignal_worker, $content);
            if ($result !== false) {
                error_log('CHE: OneSignalSDKWorker.js verificado/actualizado automáticamente');
            }
        }

        // También asegurar el Updater Worker (OneSignalSDKUpdaterWorker.js)
        $updater_worker = ABSPATH . 'OneSignalSDKUpdaterWorker.js';
        $updater_content = 'importScripts("https://cdn.onesignal.com/sdks/OneSignalSDKWorker.js");' . "\n";

        $needs_update_updater = true;
        if (file_exists($updater_worker)) {
            $current_updater = @file_get_contents($updater_worker);
            if ($current_updater === $updater_content) {
                $needs_update_updater = false;
            }
        }

        if ($needs_update_updater) {
            $res2 = @file_put_contents($updater_worker, $updater_content);
            if ($res2 !== false) {
                error_log('CHE: OneSignalSDKUpdaterWorker.js verificado/actualizado automáticamente');
            }
        }
        
        // Guardar timestamp de verificación
        set_transient('che_onesignal_worker_check', time(), 3600); // 1 hora
    }

    /**
     * Verifica y crea/actualiza manifest.json si no existe o está desactualizado
     * Necesario para PWA en Android e iOS (especialmente iOS)
     */
    private function ensure_manifest_json() {
        $manifest_dest = ABSPATH . 'manifest.json';
        
        // Solo verificar una vez cada hora para evitar demasiadas escrituras
        $last_check = get_transient('che_manifest_json_check');
        if ($last_check && (time() - $last_check) < 3600) {
            return; // Ya se verificó recientemente
        }
        
        // Intentar leer el manifest.json del plugin primero
        $manifest_source = CHE_PATH . 'public/manifest.json';
        $content = null;
        
        if (file_exists($manifest_source)) {
            $content = @file_get_contents($manifest_source);
            if ($content === false) {
                error_log('CHE: Error al leer manifest.json desde el plugin');
                $content = null;
            }
        }
        
        // Si no existe en el plugin, crear uno básico para PWA
        if ($content === null) {
            $site_name = get_bloginfo('name') ?: 'Control Horario';
            $site_short_name = strlen($site_name) > 12 ? substr($site_name, 0, 12) : $site_name;
            
            // Crear manifest.json básico para PWA
            $manifest_data = [
                'name' => $site_name,
                'short_name' => $site_short_name,
                'description' => 'Sistema de control de horas extras y gestión de vacaciones',
                'start_url' => home_url('/'),
                'scope' => '/',
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => '#4a90a4',
                'orientation' => 'portrait-primary',
                'prefer_related_applications' => false,
                'icons' => [
                    [
                        'src' => CHE_URL . 'assets/icons/icon-192x192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png',
                        'purpose' => 'any maskable'
                    ],
                    [
                        'src' => CHE_URL . 'assets/icons/icon-512x512.png',
                        'sizes' => '512x512',
                        'type' => 'image/png',
                        'purpose' => 'any maskable'
                    ]
                ],
                'categories' => ['business', 'productivity'],
                'lang' => get_locale() ?: 'es-ES',
                'dir' => 'ltr'
            ];
            
            $content = wp_json_encode($manifest_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        // Crear o actualizar el archivo si es necesario
        $needs_update = true;
        if (file_exists($manifest_dest)) {
            $current_content = @file_get_contents($manifest_dest);
            if ($current_content === $content) {
                $needs_update = false;
            }
        }
        
        if ($needs_update && $content) {
            $result = @file_put_contents($manifest_dest, $content);
            if ($result !== false) {
                error_log('CHE: manifest.json verificado/actualizado automáticamente en la raíz para PWA');
            } else {
                error_log('CHE: Error al crear/actualizar manifest.json - Verifica permisos de escritura en ' . ABSPATH);
            }
        }
        
        // Guardar timestamp de verificación
        set_transient('che_manifest_json_check', time(), 3600); // 1 hora
    }

    /**
     * Añade el link al manifest.json en el <head>
     * Necesario para que Android e iOS reconozcan la PWA
     */
    public function add_manifest_link() {
        $manifest_url = home_url('/manifest.json');
        echo '<link rel="manifest" href="' . esc_url($manifest_url) . '">' . "\n";
        
        // Meta tags adicionales para iOS (Apple)
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr(get_bloginfo('name') ?: 'Control Horario') . '">' . "\n";
        
        // Theme color para Android
        echo '<meta name="theme-color" content="#4a90a4">' . "\n";
        
        // Icon para iOS (usar el icono 192x192 como Apple Touch Icon)
        $apple_icon = CHE_URL . 'assets/icons/icon-192x192.png';
        echo '<link rel="apple-touch-icon" href="' . esc_url($apple_icon) . '">' . "\n";
    }

}

