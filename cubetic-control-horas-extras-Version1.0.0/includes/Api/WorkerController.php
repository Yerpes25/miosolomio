<?php
namespace ControlHoras\Includes\Api;

use WP_REST_Request;
use WP_REST_Response;

use ControlHoras\Models\Parte;
use ControlHoras\Models\Sede;
use ControlHoras\Models\Worker;
use ControlHoras\Includes\Services\OneSignalService;

// Cargar el servicio de notificaciones
require_once CHE_PATH . 'includes/Services/OneSignalService.php';

if (!defined('ABSPATH'))
    exit;

class WorkerController
{

    const NAMESPACE = 'che/v1';

    public function register_routes()
    {

        /**
         * RUTA para obtener la lista de trabajadores (roles worker)
         */
        register_rest_route(
            self::NAMESPACE ,
            '/admin/workers',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_workers'],
                //Puede que tengamos que cambiar el permiso más adelante por el siguiente:
                /* 'permission_callback' => function () {
                    return current_user_can( 'manage_che_users' ) || current_user_can( 'list_users' );
                }, */
                'permission_callback' => '__return_true',
            ]
        );

        /**
         * Ruta: obtener usuarios por sede
         */
        register_rest_route(
            self::NAMESPACE ,
            '/admin/usuarios-por-sede',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_usuarios_por_sede'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE ,
            '/admin/worker/(?P<id>\d+)',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_worker_by_id'],
                'permission_callback' => '__return_true',
            ]
        );


        /**
         * RUTA para crear un nuevo usuario worker
         */
        register_rest_route(
            self::NAMESPACE ,
            '/admin/crear-worker',
            [
                'methods' => 'POST',
                'callback' => [$this, 'crear_worker'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );


        register_rest_route(
            self::NAMESPACE ,
            '/admin/update-worker',
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_worker'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE ,
            '/admin/delete-worker',
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_worker'],
                'permission_callback' => function () {
                    $user = wp_get_current_user();
                    return in_array('admin', (array) $user->roles) || in_array('super_admin', (array) $user->roles);
                },
            ]
        );

        /**
         * RUTA para verificar credenciales de super admin (contabilidad)
         */
        register_rest_route(
            self::NAMESPACE,
            '/admin/verify-super-admin',
            [
                'methods' => 'POST',
                'callback' => [$this, 'verify_super_admin'],
                'permission_callback' => '__return_true',
            ]
        );

        /**
         * RUTA para obtener horas extra de un trabajador en un período
         */
        register_rest_route(
            self::NAMESPACE,
            '/admin/horas-extra-trabajador',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_horas_extra_trabajador'],
                'permission_callback' => '__return_true',
                'args' => [
                    'user_id' => [
                        'required' => true,
                        'type' => 'integer'
                    ],
                    'periodo' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'mes-actual'
                    ],
                    'fecha_inicio' => [
                        'required' => false,
                        'type' => 'string'
                    ],
                    'fecha_fin' => [
                        'required' => false,
                        'type' => 'string'
                    ]
                ]
            ]
        );

        /**
         * RUTA para actualizar tarifas de un trabajador
         */
        register_rest_route(
            self::NAMESPACE,
            '/admin/actualizar-tarifa-trabajador',
            [
                'methods' => 'POST',
                'callback' => [$this, 'actualizar_tarifa_trabajador'],
                'permission_callback' => '__return_true',
                'args' => [
                    'worker_id' => [
                        'required' => true,
                        'type' => 'integer'
                    ],
                    'tarifa_normal' => [
                        'required' => true,
                        'type' => 'number'
                    ],
                    'tarifa_extra' => [
                        'required' => true,
                        'type' => 'number'
                    ],
                    'tarifa_extra_festivo' => [
                        'required' => true,
                        'type' => 'number'
                    ],
                    'nomina' => [
                        'required' => true,
                        'type' => 'number'
                    ],
                    'irpf' => [
                        'required' => true,
                        'type' => 'number'
                    ]
                ]
            ]
        );


        register_rest_route(
            self::NAMESPACE,
            '/admin/get_summary_full',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_summary_full'],
                'permission_callback' => '__return_true',
            ]
           
        );
    }

    /**
     * RUTA para obtener la lista de trabajadores (roles worker)
     */
    public function get_workers(WP_REST_Request $request)
    {
        $search = $request->get_param('search');

        // Traer usuarios con cualquiera de estos roles
        $roles = ['worker', 'admin', 'super_admin'];

        $args = [
            'role__in' => $roles,
            'number' => 100,
        ];

        if ($search) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $users = get_users($args);

        $data = [];

        foreach ($users as $user) {

            $sede_id = get_user_meta($user->ID, 'sede_trabajo', true);
            $sede_nombre = '';

            if ($sede_id) {
                $sede_post = get_post((int) $sede_id);
                if ($sede_post && $sede_post->post_type === 'che_sede') {
                    $sede_nombre = $sede_post->post_title;
                }
            }

                        // Avatar personalizado si existe
            $avatar_id = get_user_meta( $user->ID, 'che_user_avatar_id', true );
            if ( $avatar_id ) {
                $avatar_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
            } else {
                $avatar_url = get_avatar_url( $user->ID );
            }

            $dni_nie = get_user_meta($user->ID, 'dni_nie', true);

            $data[] = [
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'dni_nie' => $dni_nie ? che_format_dni_nie($dni_nie) : '',
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'avatar'       => $avatar_url,
                'roles' => $user->roles,
                'sede_id' => $sede_id,
                'sede_nombre' => $sede_nombre,
                'nomina' => get_user_meta($user->ID, 'che_nomina', true) ?: 1,
                'tarifa_normal' => floatval(get_user_meta($user->ID, 'che_tarifa_normal', true)) ?: 10,
                'tarifa_extra' => floatval(get_user_meta($user->ID, 'che_tarifa_extra', true)) ?: 15,
                'tarifa_extra_festivo' => floatval(get_user_meta($user->ID, 'che_tarifa_extra_festivo', true)) ?: 20,
            ];
        }

        return new WP_REST_Response(
            [
                'success' => true,
                'users' => $data,
            ],
            200
        );
    }


    /**
     * Retornamos un usuario por id 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_worker_by_id(WP_REST_Request $request)
    {
        $user_id = $request->get_param('id');

        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'Usuario no encontrado.'],
                404
            );
        }

        $sede_id = get_user_meta($user->ID, 'sede_trabajo', true);
        $sede_nombre = '';

        if ($sede_id) {
            $sede_post = get_post((int) $sede_id);
            if ($sede_post && $sede_post->post_type === 'che_sede') {
                $sede_nombre = $sede_post->post_title;
            }
        }
        
        $avatar_id = get_user_meta( $user->ID, 'che_user_avatar_id', true );
        if ( $avatar_id ) {
            $avatar_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
        } else {
            $avatar_url = get_avatar_url( $user->ID );
        }

        $dni_nie = get_user_meta($user->ID, 'dni_nie', true);

        $data = [
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'dni_nie' => $dni_nie ? che_format_dni_nie($dni_nie) : '',
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'avatar' => $avatar_url,
            'roles' => $user->roles,
            'sede_id' => $sede_id,
            'sede_trabajo' => $sede_id,
            'sede_nombre' => $sede_nombre,
            'nomina' => get_user_meta($user->ID, 'che_nomina', true) ?: 1,
        ];

        return new WP_REST_Response(
            ['success' => true, 'user' => $data],
            200
        );
    }

    /**
     * Organizamos los usuarios segun la sede que elegimos
     */
    public function get_usuarios_por_sede(\WP_REST_Request $request)
    {
        $sede = $request->get_param('sede');

        if (!$sede) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Sede requerida.'],
                400
            );
        }

        $args = [
            'role__in' => ['worker', 'admin', 'super_admin'],
            'meta_key' => 'sede_trabajo',
            'meta_value' => $sede,
            'number' => 200,
        ];

        $users = get_users($args);

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
            ];
        }

        return new \WP_REST_Response(
            ['success' => true, 'users' => $data],
            200
        );
    }


    /**
     * RUTA para crear un nuevo usuario worker
     */
    public function crear_worker(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $dni_nie = isset($params['dni_nie']) ? che_sanitize_dni_nie($params['dni_nie']) : '';
        $user_email = isset($params['user_email']) ? sanitize_email($params['user_email']) : '';
        $first_name = isset($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
        $last_name = isset($params['last_name']) ? sanitize_text_field($params['last_name']) : '';
        $password = !empty($params['user_pass']) ? (string) $params['user_pass'] : wp_generate_password(12, true);
        $role = isset($params['role']) ? sanitize_key($params['role']) : 'worker';
        $sede = isset($params['sede_trabajo']) ? sanitize_text_field($params['sede_trabajo']) : '';

        // Validaciones
        if (empty($dni_nie) || empty($user_email)) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'DNI/NIE y email son obligatorios.'],
                400
            );
        }

        // Validar formato de DNI/NIE
        if (!che_validate_dni_nie($dni_nie)) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'El DNI/NIE introducido no es válido. Verifica el formato (DNI: 12345678A, NIE: X1234567L).'],
                400
            );
        }

        // Verificar si el DNI/NIE ya existe
        if (che_dni_nie_exists($dni_nie)) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'Ya existe un usuario con este DNI/NIE.'],
                400
            );
        }

        if (email_exists($user_email)) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'El email ya existe.'],
                400
            );
        }

        // Generar username único basado en el DNI/NIE
        $user_login = $dni_nie;
        $counter = 1;
        while (username_exists($user_login)) {
            $user_login = $dni_nie . $counter;
            $counter++;
        }

        // Calcular display_name
        $display = trim(($first_name . ' ' . $last_name)) ?: $dni_nie;

        // Por seguridad, solo permitimos estos roles
        $allowed_roles = ['worker', 'admin', 'super_admin'];
        if (!in_array($role, $allowed_roles, true)) {
            $role = 'worker';
        }

        $user_id = wp_insert_user([
            'user_login' => $user_login,
            'user_email' => $user_email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display,
            'user_pass' => $password,
            'role' => $role,
        ]);

        if (is_wp_error($user_id)) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'No se pudo crear el usuario: ' . $user_id->get_error_message()],
                500
            );
        }

        // Guardar DNI/NIE como meta
        update_user_meta($user_id, 'dni_nie', $dni_nie);

        if ($sede) {
            update_user_meta($user_id, 'sede_trabajo', $sede);
        }

        // Guardar IP y usuario que creó
        update_user_meta($user_id, 'creacion_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        update_user_meta($user_id, 'creado_por_id', get_current_user_id());
        update_user_meta($user_id, 'creacion_fecha', current_time('mysql'));

        // Si se crea un super_admin, notificar a todos los super_admins
        if ($role === 'super_admin') {
            $current_user_id = get_current_user_id();
            $current_user_data = get_userdata($current_user_id);
            $creator_name = $current_user_data ? ($current_user_data->display_name ?: $current_user_data->user_login) : 'Un administrador';
            $new_user_name = $display ?: $dni_nie;
            
            // Guardar la notificación en la base de datos
            $this->save_super_admin_notification($user_id, $new_user_name, $current_user_id, $creator_name);
            
            // Enviar push notification
            OneSignalService::get_instance()->notify_super_admin_created(
                $user_id,
                $new_user_name,
                $current_user_id,
                $creator_name
            );
        }

        // Guardar notificación de bienvenida para el nuevo usuario
        $this->save_welcome_notification($user_id, $dni_nie, $password, $role);

        return new WP_REST_Response(
            [
                'success' => true,
                'user_id' => $user_id,
                'dni_nie' => che_format_dni_nie($dni_nie),
            ],
            201
        );
    }

    /**
     * Actualizamos los datos de un usuario
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_worker(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;
        $first_name = isset($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
        $last_name = isset($params['last_name']) ? sanitize_text_field($params['last_name']) : '';
        $display = trim(($first_name . ' ' . $last_name));
        $user_email = isset($params['user_email']) ? sanitize_email($params['user_email']) : '';
        $dni_nie = isset($params['dni_nie']) ? che_sanitize_dni_nie($params['dni_nie']) : '';
        $user_pass = isset($params['user_pass']) ? $params['user_pass'] : '';
        $role = isset($params['role']) ? sanitize_key($params['role']) : '';
        $sede = isset($params['sede_trabajo']) ? sanitize_text_field($params['sede_trabajo']) : '';

        if (!$user_id || !get_userdata($user_id)) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'Usuario no válido.'],
                400
            );
        }

        // Si intenta añadir DNI/NIE, validar
        if (!empty($dni_nie)) {
            $existing_dni = get_user_meta($user_id, 'dni_nie', true);
            
            // Solo permitir si no tiene DNI/NIE asignado
            if (empty($existing_dni)) {
                // Validar formato
                if (!che_validate_dni_nie($dni_nie)) {
                    return new WP_REST_Response(
                        ['success' => false, 'error' => 'El DNI/NIE no es válido. Verifica el formato (ej: 12345678A o X1234567L).'],
                        400
                    );
                }
                // Verificar duplicados
                if (che_dni_nie_exists($dni_nie, $user_id)) {
                    return new WP_REST_Response(
                        ['success' => false, 'error' => 'Ya existe otro usuario con este DNI/NIE.'],
                        400
                    );
                }
            }
        }

        $update_data = ['ID' => $user_id];

        if ($first_name) {
            $update_data['first_name'] = $first_name;
        }
        if ($last_name) {
            $update_data['last_name'] = $last_name;
        }
        if ($display) {
            $update_data['display_name'] = $display;
        }
        if ($user_email) {
            $update_data['user_email'] = $user_email;
        }
        if (!empty($user_pass)) {
            $update_data['user_pass'] = $user_pass;
        }
        if ($role) {
            $allowed_roles = ['worker', 'admin', 'super_admin'];
            if (in_array($role, $allowed_roles, true)) {
                $update_data['role'] = $role;
            }
        }

        $result = wp_update_user($update_data);

        if (is_wp_error($result)) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'No se pudo actualizar el usuario.'],
                500
            );
        }

        // Guardar DNI/NIE si se proporciona y no existía
        if (!empty($dni_nie)) {
            $existing_dni = get_user_meta($user_id, 'dni_nie', true);
            if (empty($existing_dni)) {
                update_user_meta($user_id, 'dni_nie', $dni_nie);
            }
        }

        if ($sede) {
            update_user_meta($user_id, 'sede_trabajo', $sede);
        }

        // Registrar edición de usuario
        $current_user = wp_get_current_user();
        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) 
            ? sanitize_text_field(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
            : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        update_user_meta($user_id, 'edicion_fecha', current_time('mysql'));
        update_user_meta($user_id, 'edicion_por', $current_user->ID);
        update_user_meta($user_id, 'edicion_ip', $ip);

        return new WP_REST_Response(
            ['success' => true],
            200
        );
    }



    /**
     * Eliminamos a un usaurio de la BBDD
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_worker(WP_REST_Request $request)
    {
        $user_id = $request->get_param('user_id');
        $user = get_userdata($user_id);

        if (!$user_id || !$user) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'Usuario no válido.'],
                400
            );
        }

        // Guardar registro de eliminación antes de borrar
        $current_user = wp_get_current_user();
        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) 
            ? sanitize_text_field(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
            : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        
        $deleted_users = get_option('che_deleted_users', []);
        $deleted_users[] = [
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'deleted_by' => $current_user->ID,
            'deleted_by_name' => $current_user->display_name,
            'deleted_at' => current_time('mysql'),
            'deleted_ip' => $ip,
        ];
        update_option('che_deleted_users', $deleted_users);

        // Cargar la función wp_delete_user si no está disponible
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $result = wp_delete_user($user_id);

        if (!$result) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'No se pudo eliminar el usuario.'],
                500
            );
        }

        return new WP_REST_Response(
            ['success' => true],
            200
        );
    }

    /**
     * Verifica las credenciales de un super admin para acceso al módulo de contabilidad
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function verify_super_admin(WP_REST_Request $request)
    {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        if (empty($username) || empty($password)) {
            return new WP_REST_Response(
                ['success' => false, 'message' => 'Usuario y contraseña son requeridos.'],
                400
            );
        }

        // Intentar autenticar
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            $error_code = $user->get_error_code();
            
            switch ($error_code) {
                case 'invalid_username':
                    $message = 'El usuario no existe.';
                    break;
                case 'incorrect_password':
                    $message = 'Contraseña incorrecta.';
                    break;
                default:
                    $message = 'Credenciales inválidas.';
                    break;
            }
            
            return new WP_REST_Response(
                ['success' => false, 'message' => $message],
                401
            );
        }

        // Verificar que el usuario tenga rol de super_admin
        $roles = (array) $user->roles;
        
        if (!in_array('super_admin', $roles, true) && !in_array('administrator', $roles, true)) {
            return new WP_REST_Response(
                ['success' => false, 'message' => 'No tienes permisos de Super Admin para acceder a este módulo.'],
                403
            );
        }

        // Generar un token temporal para la sesión
        $token = wp_generate_password(32, false);
        set_transient('che_contabilidad_token_' . $user->ID, $token, 3600); // Válido por 1 hora

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => 'Autenticación exitosa',
                'data' => [
                    'token' => $token,
                    'user_id' => $user->ID,
                    'user_name' => $user->display_name
                ]
            ],
            200
        );
    }

    /**
     * Obtener horas extra de un trabajador en un período específico
     */
    public function get_horas_extra_trabajador(WP_REST_Request $request)
    {
        try {
            $user_id = $request->get_param('user_id');
            $periodo = $request->get_param('periodo');
            $fecha_inicio = $request->get_param('fecha_inicio');
            $fecha_fin = $request->get_param('fecha_fin');

            // Calcular fechas según el período
            $dates = $this->calcular_fechas_periodo($periodo, $fecha_inicio, $fecha_fin);

            if (!$dates) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Período inválido'
                ], 400);
            }

            // Obtener partes del trabajador en el período
            global $wpdb;
            $partes_table = $wpdb->prefix . 'che_partes';

            $query = $wpdb->prepare(
                "SELECT 
                    fecha,
                    entrada,
                    salida,
                    descanso,
                    es_festivo
                FROM $partes_table
                WHERE trabajador_id = %d
                AND fecha BETWEEN %s AND %s
                AND estado != 'rechazado'
                ORDER BY fecha ASC",
                $user_id,
                $dates['inicio'],
                $dates['fin']
            );

            $partes = $wpdb->get_results($query);

            // Calcular horas normales, extras y extras festivas
            $horas_normales = 0;
            $horas_extra = 0;
            $horas_extra_festivo = 0;

            foreach ($partes as $parte) {
                // Calcular horas trabajadas
                $entrada = strtotime($parte->entrada);
                $salida = strtotime($parte->salida);
                $descanso = intval($parte->descanso);

                $horas_trabajadas = ($salida - $entrada) / 3600 - ($descanso / 60);

                if ($parte->es_festivo) {
                    // En festivos, todo son horas extra festivas
                    $horas_extra_festivo += $horas_trabajadas;
                } else {
                    // En días normales: primeras 8 horas son normales, resto son extra
                    if ($horas_trabajadas <= 8) {
                        $horas_normales += $horas_trabajadas;
                    } else {
                        $horas_normales += 8;
                        $horas_extra += ($horas_trabajadas - 8);
                    }
                }
            }

            // Obtener tarifas del trabajador
            $tarifa_normal = floatval(get_user_meta($user_id, 'che_tarifa_normal', true)) ?: 10;
            $tarifa_extra = floatval(get_user_meta($user_id, 'che_tarifa_extra', true)) ?: 15;
            $tarifa_extra_festivo = floatval(get_user_meta($user_id, 'che_tarifa_extra_festivo', true)) ?: 20;

            // Calcular costes
            $coste_normal = $horas_normales * $tarifa_normal;
            $coste_extra = $horas_extra * $tarifa_extra;
            $coste_extra_festivo = $horas_extra_festivo * $tarifa_extra_festivo;
            $coste_total = $coste_normal + $coste_extra + $coste_extra_festivo;

            return new WP_REST_Response([
                'success' => true,
                'horas_normales' => round($horas_normales, 2),
                'horas_extra' => round($horas_extra, 2),
                'horas_extra_festivo' => round($horas_extra_festivo, 2),
                'total_horas' => round($horas_normales + $horas_extra + $horas_extra_festivo, 2),
                'coste_normal' => round($coste_normal, 2),
                'coste_extra' => round($coste_extra, 2),
                'coste_extra_festivo' => round($coste_extra_festivo, 2),
                'coste_total' => round($coste_total, 2),
                'periodo' => [
                    'inicio' => $dates['inicio'],
                    'fin' => $dates['fin']
                ]
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error al obtener horas extra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar tarifas de un trabajador
     */
    public function actualizar_tarifa_trabajador(WP_REST_Request $request)
    {
        try {
            $worker_id = $request->get_param('worker_id');
            $nomina = $request->get_param('nomina');
            $irpf = $request->get_param('irpf');
            $tarifa_normal = $request->get_param('tarifa_normal');
            $tarifa_extra = $request->get_param('tarifa_extra');
            $tarifa_extra_festivo = $request->get_param('tarifa_extra_festivo');

            // Validar que el usuario existe y es trabajador
            $user = get_userdata($worker_id);
            if (!$user) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Actualizar tarifas en user meta
            update_user_meta($worker_id, 'che_tarifa_normal', floatval($tarifa_normal));
            update_user_meta($worker_id, 'che_tarifa_extra', floatval($tarifa_extra));
            update_user_meta($worker_id, 'che_tarifa_extra_festivo', floatval($tarifa_extra_festivo));
            update_user_meta($worker_id, 'che_nomina', floatval($nomina));
            update_user_meta($worker_id, 'che_irpf', floatval($irpf));

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Tarifas actualizadas correctamente',
                'data' => [
                    'worker_id' => $worker_id,
                    'tarifa_semana' => floatval($tarifa_normal),
                    'tarifa_findesemana' => floatval($tarifa_extra),
                    'tarifa_festivo' => floatval($tarifa_extra_festivo),
                    'nomina' => floatval($nomina),
                    'irpf' => floatval($irpf)
                ]
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error al actualizar tarifas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular fechas según el período seleccionado
     */
    private function calcular_fechas_periodo($periodo, $fecha_inicio = null, $fecha_fin = null)
    {
        $now = current_time('timestamp');

        switch ($periodo) {
            case 'mes-actual':
                return [
                    'inicio' => date('Y-m-01', $now),
                    'fin' => date('Y-m-t', $now)
                ];

            case 'mes-anterior':
                $mes_anterior = strtotime('-1 month', $now);
                return [
                    'inicio' => date('Y-m-01', $mes_anterior),
                    'fin' => date('Y-m-t', $mes_anterior)
                ];

            case 'trimestre-actual':
                $mes = intval(date('n', $now));
                $trimestre = ceil($mes / 3);
                $primer_mes = ($trimestre - 1) * 3 + 1;
                $ultimo_mes = $primer_mes + 2;
                
                return [
                    'inicio' => date('Y-' . str_pad($primer_mes, 2, '0', STR_PAD_LEFT) . '-01', $now),
                    'fin' => date('Y-m-t', strtotime(date('Y-' . str_pad($ultimo_mes, 2, '0', STR_PAD_LEFT) . '-01', $now)))
                ];

            case 'año-actual':
                return [
                    'inicio' => date('Y-01-01', $now),
                    'fin' => date('Y-12-31', $now)
                ];

            case 'personalizado':
                if ($fecha_inicio && $fecha_fin) {
                    return [
                        'inicio' => $fecha_inicio,
                        'fin' => $fecha_fin
                    ];
                }
                return null;

            default:
                return null;
        }
    }

    public function get_summary_full(WP_REST_Request $request)
    {
        // aqui llamamos a la funcoin del modelo contabilidad 
        $summary = \ControlHoras\Models\Contabilidad::get_summary_full();
        return new WP_REST_Response(
            [
                'success' => true,
                'users' => $summary,
            ],
            200
        );
    }

    /**
     * Guarda una notificación de creación de super_admin
     */
    private function save_super_admin_notification($new_user_id, $new_user_name, $creator_id, $creator_name)
    {
        $notifications = get_option('che_super_admin_notifications', []);
        
        $notifications[] = [
            'id'            => 'super_admin_' . $new_user_id . '_' . time(),
            'type'          => 'super_admin_created',
            'section'       => 'usuarios',
            'title'         => 'Nuevo Super Administrador',
            'message'       => sprintf('%s ha creado un nuevo super administrador: %s', $creator_name, $new_user_name),
            'date'          => current_time('mysql'),
            'new_user_id'   => $new_user_id,
            'new_user_name' => $new_user_name,
            'creator_id'    => $creator_id,
            'creator_name'  => $creator_name,
        ];
        
        // Mantener solo las últimas 50 notificaciones
        $notifications = array_slice($notifications, -50);
        
        update_option('che_super_admin_notifications', $notifications);
    }

    /**
     * Guarda una notificación de bienvenida para el nuevo usuario
     */
    private function save_welcome_notification($user_id, $user_login, $password, $role)
    {
        $role_names = [
            'worker' => 'Trabajador',
            'admin' => 'Administrador',
            'super_admin' => 'Super Administrador'
        ];
        $role_name = $role_names[$role] ?? 'Usuario';
        
        $notification = [
            'id'        => 'welcome_' . $user_id . '_' . time(),
            'type'      => 'welcome',
            'title'     => '¡Bienvenido/a al sistema!',
            'message'   => sprintf(
                'Has sido dado/a de alta como %s. Tu usuario es: %s y tu contraseña: %s. Recuerda cambiar tu contraseña desde tu perfil.',
                $role_name,
                $user_login,
                $password
            ),
            'date'      => current_time('mysql'),
            'user_id'   => $user_id,
        ];
        
        // Guardar en user_meta para que solo lo vea este usuario
        update_user_meta($user_id, 'che_welcome_notification', $notification);
    }

}
