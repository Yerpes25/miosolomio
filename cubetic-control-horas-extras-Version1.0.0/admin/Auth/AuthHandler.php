<?php

namespace ControlHoras\Admin\Auth;

/**
 * Maneja la autenticación de trabajadores vía AJAX
 */
class AuthHandler
{
    private static $instance = null;

    /**
     * Constructor privado para implementar singleton
     */
    private function __construct()
    {
        $this->define_hooks();
    }

    /**
     * Obtiene la instancia única de AuthHandler
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Define los hooks necesarios
     */
    private function define_hooks()
    {
        // Manejar la acción AJAX para el login del trabajador
        add_action('wp_ajax_che_worker_login', [$this, 'handle_login']);
        add_action('wp_ajax_nopriv_che_worker_login', [$this, 'handle_login']);
        /* add_action('wp_ajax_nopriv_cpm_worker_login', [$this, 'handle_login']); */

    }

    /**
     * Maneja la solicitud AJAX de login del trabajador
     */
    public function handle_login()
    {   
        
        /* error_log('CHE AJAX: entra handle_login'); */ // para debug

        // Valida el nonce que mandas en JS: cheAuth.che_auth_nonce
        //check_ajax_referer('che_auth_nonce', 'nonce');

        $dni_nie_input = isset($_POST['username']) ? trim(strtoupper($_POST['username'])) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($dni_nie_input) || empty($password)) {
            wp_send_json_error('Debes introducir tu DNI/NIE y contraseña.');
        }

        // Buscar usuario por DNI/NIE en meta
        $users = get_users([
            'meta_key' => 'dni_nie',
            'meta_value' => $dni_nie_input,
            'number' => 1,
        ]);

        // Si no encuentra por meta, buscar por username (compatibilidad)
        if (empty($users)) {
            $user_by_login = get_user_by('login', $dni_nie_input);
            if ($user_by_login) {
                $users = [$user_by_login];
            }
        }
        
        if (empty($users)) {
            wp_send_json_error('No existe ningún usuario con ese DNI/NIE. Verifica que lo hayas escrito correctamente.');
        }

        $user = $users[0];

        // Intentar autenticar con el username de WordPress y la contraseña proporcionada
        $creds = [
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'      => true,
        ];

        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            // Obtener el código de error específico
            $error_code = $user->get_error_code();
            $error_message = '';

            // Mensajes personalizados según el tipo de error
            switch ($error_code) {
                case 'invalid_username':
                    $error_message = 'El usuario no existe. Verifica que hayas escrito correctamente tu nombre de usuario.';
                    break;
                case 'incorrect_password':
                    $error_message = 'La contraseña es incorrecta. Por favor, intenta de nuevo.';
                    break;
                case 'invalid_email':
                    $error_message = 'El correo electrónico no es válido.';
                    break;
                case 'empty_username':
                    $error_message = 'Debes introducir un nombre de usuario.';
                    break;
                case 'empty_password':
                    $error_message = 'Debes introducir una contraseña.';
                    break;
                case 'authentication_failed':
                    $error_message = 'Error de autenticación. Verifica tus credenciales.';
                    break;
                case 'insufficient_permissions':
                    $error_message = 'No tienes permiso para acceder al sistema.';
                    break;
                default:
                    // Mensaje genérico con el código de error para debugging
                    $error_message = 'No se pudo iniciar sesión. Intenta de nuevo.';
                    error_log('CHE Auth Error: ' . $error_code . ' - ' . $user->get_error_message());
                    break;
            }

            wp_send_json_error($error_message);
        }

        // Preparar los datos del dashboard usando el controlador
        if (class_exists('\ControlHoras\Includes\Controllers\DashboardController')) {
                $dashboard_controller = \ControlHoras\Includes\Controllers\DashboardController::get_instance();

                if (method_exists($dashboard_controller, 'prepare_dashboard_data')) {
                    // Almacenar los datos en una transient de WordPress
                    $dashboard_data_key = 'cpm_dashboard_data_' . $user->ID;
                    $dashboard_data = $dashboard_controller->prepare_dashboard_data();
                    set_transient($dashboard_data_key, $dashboard_data, 5 * MINUTE_IN_SECONDS);
                }
            }

            // Redirección según rol (se devuelve en JSON, no con wp_redirect)
            $roles = (array) $user->roles;

            // Verificar roles del usuario y redirigir a la zona correspondiente
        if ( in_array( 'super_admin', $roles, true ) || in_array( 'admin', $roles, true ) ) {
            // Panel de administración
            $redirect_url = home_url( '/administracion/' );
        } elseif ( in_array( 'worker', $roles, true ) ) {
            // Panel de trabajador
            $redirect_url = home_url( '/trabajadores/panel/' );
        } else {
            wp_logout();
            wp_send_json_error('Tu cuenta no tiene permisos para acceder al sistema. Contacta con el administrador si crees que esto es un error.');
        }
        
        // Enviar respuesta exitosa con la URL de redirección
        wp_send_json_success([
            'redirect_url' => $redirect_url,
        ]);
    }            

        
    


    public function enqueue_worker_scripts()
    {
        wp_enqueue_script('che-auth', CHE_JS_URL . 'admin/login.js', ['jquery'], CHE_VERSION, true);

        wp_localize_script('che-auth', 'cheAuth', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('che_auth_nonce')
        ]);
    }
}