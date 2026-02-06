<?php
namespace ControlHoras\Includes\Api;

class ActivityLogController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Registrar rutas REST API
     */
    public function register_routes() {
        // Obtener registros de actividad
        register_rest_route(
            'che/v1',
            '/activity-logs',
            [
                'methods' => 'GET',
                'callback' => [ $this, 'get_activity_logs' ],
                'permission_callback' => '__return_true',
            ]
        );

        // Obtener tipos de actividad disponibles
        register_rest_route(
            'che/v1',
            '/activity-types',
            [
                'methods' => 'GET',
                'callback' => [ $this, 'get_activity_types' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Obtener registros de actividad con filtros
     * Combina TODAS las actividades del sistema
     */
    public function get_activity_logs( \WP_REST_Request $request ) {
        $activity_type = $request->get_param( 'activity_type' );
        $user_id = $request->get_param( 'user_id' );
        $per_page = intval( $request->get_param( 'per_page' ) ?? 10 );
        if ( $per_page < 1 ) $per_page = 10;

        $logs = [];

        // 1. PARTES - Todas las actividades
        if ( empty( $activity_type ) || in_array( $activity_type, [
            'creacion_parte', 'aprobacion_parte', 'rechazo_parte'
        ] ) ) {
            $partes_logs = $this->get_partes_logs( $activity_type, $user_id );
            $logs = array_merge( $logs, $partes_logs );
        }

        // 2. VACACIONES - Todas las actividades
        if ( empty( $activity_type ) || in_array( $activity_type, [
            'solicitud_vacaciones', 'aprobacion_vacaciones', 'rechazo_vacaciones'
        ] ) ) {
            $vacaciones_logs = $this->get_vacaciones_logs( $activity_type, $user_id );
            $logs = array_merge( $logs, $vacaciones_logs );
        }

        // 3. USUARIOS - Todas las actividades
        if ( empty( $activity_type ) || in_array( $activity_type, [
            'creacion_usuario', 'edicion_usuario', 'eliminar_usuario'
        ] ) ) {
            $usuarios_logs = $this->get_usuarios_logs( $activity_type, $user_id );
            $logs = array_merge( $logs, $usuarios_logs );
        }

        // 4. SEDES - Todas las actividades
        if ( empty( $activity_type ) || in_array( $activity_type, [
            'creacion_sede', 'edicion_sede'
        ] ) ) {
            $sedes_logs = $this->get_sedes_logs( $activity_type, $user_id );
            $logs = array_merge( $logs, $sedes_logs );
        }

        // 5. DÉBITOS - Todas las actividades
        if ( empty( $activity_type ) || $activity_type === 'debito_trabajador' ) {
            $debitos_logs = $this->get_debitos_logs( $user_id );
            $logs = array_merge( $logs, $debitos_logs );
        }

        // Ordenar por fecha descendente (más recientes primero)
        usort( $logs, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        });

        $total = count( $logs );
        $total_pages = ceil( $total / $per_page );
        
        // Página 1 = más recientes, última página = más antiguos
        $page = intval( $request->get_param( 'page' ) ?? 1 );
        if ( $page < 1 ) $page = 1;
        if ( $page > $total_pages && $total_pages > 0 ) $page = $total_pages;
        
        $offset = ( $page - 1 ) * $per_page;
        
        // Aplicar paginación
        $logs = array_slice( $logs, $offset, $per_page );

        return rest_ensure_response( [
            'success' => true,
            'data' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );
    }

    /**
     * Obtener logs de PARTES - TODAS las actividades
     */
    private function get_partes_logs( $activity_type = null, $user_id = null ) {
        global $wpdb;

        $sql = "SELECT 
                    p.ID, 
                    p.post_title, 
                    p.post_date,
                    p.post_modified,
                    p.post_author,
                    u.display_name as worker_name,
                    COALESCE(m_estado.meta_value, 'en_curso') as estado,
                    COALESCE(m_validacion.meta_value, 'pendiente') as estado_validacion,
                    m_validado_por.meta_value as validado_por_nombre,
                    m_ip.meta_value as validacion_ip,
                    m_fecha_val.meta_value as validacion_fecha,
                    m_hora_fin.meta_value as hora_finalizacion,
                    m_ip_inicio.meta_value as ip_inicio,
                    m_ip_fin.meta_value as ip_fin
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} m_estado ON (p.ID = m_estado.post_id AND m_estado.meta_key = 'estado')
                LEFT JOIN {$wpdb->postmeta} m_validacion ON (p.ID = m_validacion.post_id AND m_validacion.meta_key = 'estado_validacion')
                LEFT JOIN {$wpdb->postmeta} m_validado_por ON (p.ID = m_validado_por.post_id AND m_validado_por.meta_key = 'validado_por_nombre')
                LEFT JOIN {$wpdb->postmeta} m_ip ON (p.ID = m_ip.post_id AND m_ip.meta_key = 'validacion_ip')
                LEFT JOIN {$wpdb->postmeta} m_fecha_val ON (p.ID = m_fecha_val.post_id AND m_fecha_val.meta_key = 'validacion_fecha')
                LEFT JOIN {$wpdb->postmeta} m_hora_fin ON (p.ID = m_hora_fin.post_id AND m_hora_fin.meta_key = 'hora_finalizacion')
                LEFT JOIN {$wpdb->postmeta} m_ip_inicio ON (p.ID = m_ip_inicio.post_id AND m_ip_inicio.meta_key = 'ip_inicio')
                LEFT JOIN {$wpdb->postmeta} m_ip_fin ON (p.ID = m_ip_fin.post_id AND m_ip_fin.meta_key = 'ip_fin')
                LEFT JOIN {$wpdb->users} u ON (p.post_author = u.ID)
                WHERE p.post_type = 'parte_horario'
                AND p.post_status = 'publish'";

        $query_args = [];

        if ( $user_id ) {
            $sql .= " AND p.post_author = %d";
            $query_args[] = intval( $user_id );
        }

        $sql .= " ORDER BY p.post_modified DESC LIMIT 500";

        if ( ! empty( $query_args ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ), ARRAY_A );
        } else {
            $results = $wpdb->get_results( $sql, ARRAY_A );
        }

        $logs = [];
        foreach ( $results as $row ) {
            $estado = $row['estado'] ?: 'en_curso';
            $estado_val = $row['estado_validacion'] ?: 'pendiente';
            $admin_name = $row['validado_por_nombre'] ?: '';
            $ip = $row['validacion_ip'] ?: '';
            $ip_inicio = $row['ip_inicio'] ?: '';
            $ip_fin = $row['ip_fin'] ?: '';
            
            // 1. Creación de parte (antes era inicio)
            if ( ! $activity_type || $activity_type === 'creacion_parte' ) {
                $logs[] = [
                    'id' => 'parte_' . $row['ID'],
                    'user_id' => $row['post_author'],
                    'user_name' => $row['worker_name'],
                    'worker_name' => $row['worker_name'],
                    'activity_type' => 'creacion_parte',
                    'activity_label' => 'Creación de Parte',
                    'action_description' => "{$row['worker_name']} creó un parte de trabajo",
                    'object_type' => 'parte',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => $ip_inicio ?: '-',
                    'created_at' => $row['post_date'],
                    'additional_data' => [ 'trabajador' => $row['worker_name'] ],
                ];
            }
            
            // 2. Validación (aprobación)
            if ( $estado_val === 'validado' && ( ! $activity_type || $activity_type === 'aprobacion_parte' ) ) {
                $logs[] = [
                    'id' => 'parte_val_' . $row['ID'],
                    'user_id' => $row['post_author'],
                    'user_name' => $admin_name ?: 'Admin',
                    'worker_name' => $row['worker_name'],
                    'activity_type' => 'aprobacion_parte',
                    'activity_label' => 'Aprobación de Parte',
                    'action_description' => $admin_name 
                        ? "Parte de '{$row['worker_name']}' validado por {$admin_name}" 
                        : "Parte de '{$row['worker_name']}' validado",
                    'object_type' => 'parte',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => $ip ?: '-',
                    'created_at' => $row['validacion_fecha'] ?: $row['post_modified'],
                    'additional_data' => [ 'trabajador' => $row['worker_name'], 'admin' => $admin_name ],
                ];
            }
            
            // 4. Rechazo
            if ( $estado_val === 'rechazado' && ( ! $activity_type || $activity_type === 'rechazo_parte' ) ) {
                $logs[] = [
                    'id' => 'parte_rech_' . $row['ID'],
                    'user_id' => $row['post_author'],
                    'user_name' => $admin_name ?: 'Admin',
                    'worker_name' => $row['worker_name'],
                    'activity_type' => 'rechazo_parte',
                    'activity_label' => 'Rechazo de Parte',
                    'action_description' => $admin_name 
                        ? "Parte de '{$row['worker_name']}' rechazado por {$admin_name}" 
                        : "Parte de '{$row['worker_name']}' rechazado",
                    'object_type' => 'parte',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => $ip ?: '-',
                    'created_at' => $row['validacion_fecha'] ?: $row['post_modified'],
                    'additional_data' => [ 'trabajador' => $row['worker_name'], 'admin' => $admin_name ],
                ];
            }
        }

        return $logs;
    }

    /**
     * Obtener logs de VACACIONES - TODAS las actividades
     */
    private function get_vacaciones_logs( $activity_type = null, $user_id = null ) {
        global $wpdb;

        $sql = "SELECT 
                    p.ID, 
                    p.post_title, 
                    p.post_date,
                    p.post_modified,
                    p.post_author,
                    u.display_name as worker_name,
                    COALESCE(m_estado.meta_value, 'pendiente') as estado,
                    m_inicio.meta_value as fecha_inicio,
                    m_fin.meta_value as fecha_fin,
                    m_aprobado_por.meta_value as aprobado_por_nombre,
                    m_ip.meta_value as aprobacion_ip,
                    m_fecha.meta_value as aprobacion_fecha,
                    m_sol_ip.meta_value as solicitud_ip
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} m_estado ON (p.ID = m_estado.post_id AND m_estado.meta_key = 'estado')
                LEFT JOIN {$wpdb->postmeta} m_inicio ON (p.ID = m_inicio.post_id AND m_inicio.meta_key = 'fecha_inicio')
                LEFT JOIN {$wpdb->postmeta} m_fin ON (p.ID = m_fin.post_id AND m_fin.meta_key = 'fecha_fin')
                LEFT JOIN {$wpdb->postmeta} m_aprobado_por ON (p.ID = m_aprobado_por.post_id AND m_aprobado_por.meta_key = 'aprobado_por_nombre')
                LEFT JOIN {$wpdb->postmeta} m_ip ON (p.ID = m_ip.post_id AND m_ip.meta_key = 'aprobacion_ip')
                LEFT JOIN {$wpdb->postmeta} m_fecha ON (p.ID = m_fecha.post_id AND m_fecha.meta_key = 'aprobacion_fecha')
                LEFT JOIN {$wpdb->postmeta} m_sol_ip ON (p.ID = m_sol_ip.post_id AND m_sol_ip.meta_key = 'solicitud_ip')
                LEFT JOIN {$wpdb->users} u ON (p.post_author = u.ID)
                WHERE p.post_type = 'che_vacacion'
                AND p.post_status = 'publish'";

        $query_args = [];

        if ( $user_id ) {
            $sql .= " AND p.post_author = %d";
            $query_args[] = intval( $user_id );
        }

        $sql .= " ORDER BY p.post_modified DESC LIMIT 500";

        if ( ! empty( $query_args ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ), ARRAY_A );
        } else {
            $results = $wpdb->get_results( $sql, ARRAY_A );
        }

        $logs = [];
        foreach ( $results as $row ) {
            $estado = $row['estado'] ?: 'pendiente';
            $fechas = "{$row['fecha_inicio']} al {$row['fecha_fin']}";
            $admin_name = $row['aprobado_por_nombre'] ?: '';
            $ip = $row['aprobacion_ip'] ?: '';
            $solicitud_ip = $row['solicitud_ip'] ?: '';
            
            // 1. Solicitud (siempre hay una solicitud por cada vacación)
            if ( ! $activity_type || $activity_type === 'solicitud_vacaciones' ) {
                $logs[] = [
                    'id' => 'vac_sol_' . $row['ID'],
                    'user_id' => $row['post_author'],
                    'user_name' => $row['worker_name'],
                    'worker_name' => $row['worker_name'],
                    'activity_type' => 'solicitud_vacaciones',
                    'activity_label' => 'Solicitud de Vacaciones',
                    'action_description' => "{$row['worker_name']} solicitó vacaciones: {$fechas}",
                    'object_type' => 'vacaciones',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => $solicitud_ip ?: '-',
                    'created_at' => $row['post_date'],
                    'additional_data' => [ 'trabajador' => $row['worker_name'], 'fechas' => $fechas ],
                ];
            }
            
            // 2. Aprobación
            if ( $estado === 'aprobado' && ( ! $activity_type || $activity_type === 'aprobacion_vacaciones' ) ) {
                $logs[] = [
                    'id' => 'vac_apr_' . $row['ID'],
                    'user_id' => $row['post_author'],
                    'user_name' => $admin_name ?: 'Admin',
                    'worker_name' => $row['worker_name'],
                    'activity_type' => 'aprobacion_vacaciones',
                    'activity_label' => 'Aprobación de Vacaciones',
                    'action_description' => $admin_name 
                        ? "Vacaciones de {$row['worker_name']} aprobadas por {$admin_name}: {$fechas}"
                        : "Vacaciones de {$row['worker_name']} aprobadas: {$fechas}",
                    'object_type' => 'vacaciones',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => $ip ?: '-',
                    'created_at' => $row['aprobacion_fecha'] ?: $row['post_modified'],
                    'additional_data' => [ 'trabajador' => $row['worker_name'], 'admin' => $admin_name, 'fechas' => $fechas ],
                ];
            }
            
            // 3. Rechazo
            if ( $estado === 'rechazado' && ( ! $activity_type || $activity_type === 'rechazo_vacaciones' ) ) {
                $logs[] = [
                    'id' => 'vac_rech_' . $row['ID'],
                    'user_id' => $row['post_author'],
                    'user_name' => $admin_name ?: 'Admin',
                    'worker_name' => $row['worker_name'],
                    'activity_type' => 'rechazo_vacaciones',
                    'activity_label' => 'Rechazo de Vacaciones',
                    'action_description' => $admin_name 
                        ? "Vacaciones de {$row['worker_name']} rechazadas por {$admin_name}: {$fechas}"
                        : "Vacaciones de {$row['worker_name']} rechazadas: {$fechas}",
                    'object_type' => 'vacaciones',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => $ip ?: '-',
                    'created_at' => $row['aprobacion_fecha'] ?: $row['post_modified'],
                    'additional_data' => [ 'trabajador' => $row['worker_name'], 'admin' => $admin_name, 'fechas' => $fechas ],
                ];
            }
        }

        return $logs;
    }

    /**
     * Obtener logs de USUARIOS - Creación, edición y eliminación
     */
    private function get_usuarios_logs( $activity_type = null, $user_id = null ) {
        global $wpdb;

        $logs = [];
        $role_labels = [
            'worker' => 'Trabajador',
            'admin' => 'Administrador',
            'super_admin' => 'Super Administrador',
        ];
        $plugin_roles = ['worker', 'admin', 'super_admin'];

        // 1. Logs de CREACIÓN de usuarios
        if ( ! $activity_type || $activity_type === 'creacion_usuario' ) {
            $sql = "SELECT 
                        u.ID,
                        u.user_login,
                        u.display_name,
                        u.user_email,
                        u.user_registered,
                        um.meta_value as user_role,
                        um_ip.meta_value as creacion_ip,
                        um_creador.meta_value as creado_por_id
                    FROM {$wpdb->users} u
                    LEFT JOIN {$wpdb->usermeta} um ON (u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities')
                    LEFT JOIN {$wpdb->usermeta} um_ip ON (u.ID = um_ip.user_id AND um_ip.meta_key = 'creacion_ip')
                    LEFT JOIN {$wpdb->usermeta} um_creador ON (u.ID = um_creador.user_id AND um_creador.meta_key = 'creado_por_id')
                    WHERE 1=1";

            $query_args = [];
            if ( $user_id ) {
                $sql .= " AND u.ID = %d";
                $query_args[] = intval( $user_id );
            }
            $sql .= " ORDER BY u.user_registered DESC LIMIT 200";

            $results = ! empty( $query_args ) 
                ? $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ), ARRAY_A )
                : $wpdb->get_results( $sql, ARRAY_A );

            foreach ( $results as $row ) {
                $roles = maybe_unserialize( $row['user_role'] );
                $role_name = is_array( $roles ) ? array_key_first( $roles ) : 'usuario';
                
                if ( ! in_array( $role_name, $plugin_roles ) ) {
                    continue;
                }

                $creador_name = '';
                if ( $row['creado_por_id'] ) {
                    $creador = get_userdata( intval($row['creado_por_id']) );
                    $creador_name = $creador ? ($creador->display_name ?: $creador->user_login) : '';
                }
                
                $logs[] = [
                    'id' => 'user_create_' . $row['ID'],
                    'user_id' => $row['creado_por_id'] ?: $row['ID'],
                    'user_name' => $creador_name ?: 'Sistema',
                    'activity_type' => 'creacion_usuario',
                    'activity_label' => 'Creación de Usuario',
                    'action_description' => $creador_name 
                        ? "{$creador_name} creó el usuario '{$row['display_name']}' como " . ($role_labels[$role_name] ?? $role_name)
                        : "Usuario '{$row['display_name']}' creado como " . ($role_labels[$role_name] ?? $role_name),
                    'object_type' => 'usuario',
                    'object_id' => $row['ID'],
                    'object_name' => $row['display_name'] ?: $row['user_login'],
                    'ip_address' => $row['creacion_ip'] ?: '-',
                    'created_at' => $row['user_registered'],
                    'additional_data' => [
                        'email' => $row['user_email'],
                        'rol' => $role_labels[$role_name] ?? $role_name,
                        'creado_por' => $creador_name,
                    ],
                ];
            }
        }

        // 2. Logs de EDICIÓN de usuarios
        if ( ! $activity_type || $activity_type === 'edicion_usuario' ) {
            $sql = "SELECT 
                        u.ID,
                        u.display_name,
                        u.user_email,
                        um.meta_value as user_role,
                        um_fecha.meta_value as edicion_fecha,
                        um_por.meta_value as edicion_por,
                        um_ip.meta_value as edicion_ip
                    FROM {$wpdb->users} u
                    INNER JOIN {$wpdb->usermeta} um_fecha ON (u.ID = um_fecha.user_id AND um_fecha.meta_key = 'edicion_fecha')
                    LEFT JOIN {$wpdb->usermeta} um ON (u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities')
                    LEFT JOIN {$wpdb->usermeta} um_por ON (u.ID = um_por.user_id AND um_por.meta_key = 'edicion_por')
                    LEFT JOIN {$wpdb->usermeta} um_ip ON (u.ID = um_ip.user_id AND um_ip.meta_key = 'edicion_ip')
                    WHERE 1=1";

            $query_args = [];
            if ( $user_id ) {
                $sql .= " AND u.ID = %d";
                $query_args[] = intval( $user_id );
            }
            $sql .= " ORDER BY um_fecha.meta_value DESC LIMIT 200";

            $results = ! empty( $query_args ) 
                ? $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ), ARRAY_A )
                : $wpdb->get_results( $sql, ARRAY_A );

            foreach ( $results as $row ) {
                $roles = maybe_unserialize( $row['user_role'] );
                $role_name = is_array( $roles ) ? array_key_first( $roles ) : 'usuario';
                
                if ( ! in_array( $role_name, $plugin_roles ) ) {
                    continue;
                }

                $editor_name = '';
                if ( $row['edicion_por'] ) {
                    $editor = get_userdata( intval($row['edicion_por']) );
                    $editor_name = $editor ? ($editor->display_name ?: $editor->user_login) : '';
                }
                
                $logs[] = [
                    'id' => 'user_edit_' . $row['ID'] . '_' . strtotime($row['edicion_fecha']),
                    'user_id' => $row['edicion_por'] ?: 0,
                    'user_name' => $editor_name ?: 'Sistema',
                    'activity_type' => 'edicion_usuario',
                    'activity_label' => 'Edición de Usuario',
                    'action_description' => $editor_name 
                        ? "{$editor_name} editó el usuario '{$row['display_name']}'"
                        : "Usuario '{$row['display_name']}' editado",
                    'object_type' => 'usuario',
                    'object_id' => $row['ID'],
                    'object_name' => $row['display_name'],
                    'ip_address' => $row['edicion_ip'] ?: '-',
                    'created_at' => $row['edicion_fecha'],
                    'additional_data' => [
                        'email' => $row['user_email'],
                        'rol' => $role_labels[$role_name] ?? $role_name,
                        'editado_por' => $editor_name,
                    ],
                ];
            }
        }

        // 3. Logs de ELIMINACIÓN de usuarios (desde option)
        if ( ! $activity_type || $activity_type === 'eliminar_usuario' ) {
            $deleted_users = get_option('che_deleted_users', []);
            foreach ( $deleted_users as $deleted ) {
                $logs[] = [
                    'id' => 'user_delete_' . $deleted['user_id'] . '_' . strtotime($deleted['deleted_at']),
                    'user_id' => $deleted['deleted_by'],
                    'user_name' => $deleted['deleted_by_name'],
                    'activity_type' => 'eliminar_usuario',
                    'activity_label' => 'Eliminación de Usuario',
                    'action_description' => "{$deleted['deleted_by_name']} eliminó el usuario '{$deleted['user_name']}'",
                    'object_type' => 'usuario',
                    'object_id' => $deleted['user_id'],
                    'object_name' => $deleted['user_name'],
                    'ip_address' => $deleted['deleted_ip'] ?: '-',
                    'created_at' => $deleted['deleted_at'],
                    'additional_data' => [
                        'email' => $deleted['user_email'],
                        'eliminado_por' => $deleted['deleted_by_name'],
                    ],
                ];
            }
        }

        return $logs;
    }

    /**
     * Obtener logs de SEDES - Creación y modificación
     */
    private function get_sedes_logs( $activity_type = null, $user_id = null ) {
        global $wpdb;

        $sql = "SELECT 
                    p.ID, 
                    p.post_title, 
                    p.post_date,
                    p.post_modified,
                    p.post_author,
                    u.display_name as author_name,
                    m_dir.meta_value as direccion,
                    m_cp.meta_value as cp,
                    m_ip.meta_value as creacion_ip,
                    m_creador.meta_value as creado_por_id
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->users} u ON (p.post_author = u.ID)
                LEFT JOIN {$wpdb->postmeta} m_dir ON (p.ID = m_dir.post_id AND m_dir.meta_key = 'che_sede_direccion')
                LEFT JOIN {$wpdb->postmeta} m_cp ON (p.ID = m_cp.post_id AND m_cp.meta_key = 'che_sede_cp')
                LEFT JOIN {$wpdb->postmeta} m_ip ON (p.ID = m_ip.post_id AND m_ip.meta_key = 'creacion_ip')
                LEFT JOIN {$wpdb->postmeta} m_creador ON (p.ID = m_creador.post_id AND m_creador.meta_key = 'creado_por_id')
                WHERE p.post_type = 'che_sede'
                AND p.post_status = 'publish'";

        $query_args = [];

        if ( $user_id ) {
            $sql .= " AND p.post_author = %d";
            $query_args[] = intval( $user_id );
        }

        $sql .= " ORDER BY p.post_modified DESC LIMIT 200";

        if ( ! empty( $query_args ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ), ARRAY_A );
        } else {
            $results = $wpdb->get_results( $sql, ARRAY_A );
        }

        $logs = [];
        foreach ( $results as $row ) {
            $author = $row['author_name'] ?: 'Admin';
            $creacion_ip = $row['creacion_ip'] ?: '';

            // Obtener nombre del creador real
            if ( $row['creado_por_id'] ) {
                $creador = get_userdata( intval($row['creado_por_id']) );
                if ( $creador ) {
                    $author = $creador->display_name ?: $creador->user_login;
                }
            }
            
            // Creación de sede
            if ( ! $activity_type || $activity_type === 'creacion_sede' ) {
                $logs[] = [
                    'id' => 'sede_' . $row['ID'],
                    'user_id' => $row['creado_por_id'] ?: $row['post_author'],
                    'user_name' => $author,
                    'activity_type' => 'creacion_sede',
                    'activity_label' => 'Creación de Sede',
                    'action_description' => "{$author} creó la sede '{$row['post_title']}'",
                    'object_type' => 'sede',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => $creacion_ip ?: '-',
                    'created_at' => $row['post_date'],
                    'additional_data' => [
                        'direccion' => $row['direccion'],
                        'cp' => $row['cp'],
                    ],
                ];
            }
            
            // Edición de sede (si post_modified es diferente a post_date, con al menos 60 segundos)
            $date_diff = strtotime($row['post_modified']) - strtotime($row['post_date']);
            if ( $date_diff > 60 && ( ! $activity_type || $activity_type === 'edicion_sede' ) ) {
                $logs[] = [
                    'id' => 'sede_edit_' . $row['ID'],
                    'user_id' => $row['post_author'],
                    'user_name' => $author,
                    'activity_type' => 'edicion_sede',
                    'activity_label' => 'Edición de Sede',
                    'action_description' => "{$author} modificó la sede '{$row['post_title']}'",
                    'object_type' => 'sede',
                    'object_id' => $row['ID'],
                    'object_name' => $row['post_title'],
                    'ip_address' => '-',
                    'created_at' => $row['post_modified'],
                    'additional_data' => [
                        'direccion' => $row['direccion'],
                        'cp' => $row['cp'],
                    ],
                ];
            }
        }

        return $logs;
    }

    /**
     * Obtener logs de DÉBITOS aplicados a trabajadores
     */
    private function get_debitos_logs( $user_id = null ) {
        global $wpdb;
        
        // Tabla personalizada para registrar débitos
        $table = $wpdb->prefix . 'che_debito_logs';
        
        // Verificar si la tabla existe
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            return [];
        }
        
        $sql = "SELECT 
                    l.id,
                    l.worker_id,
                    l.admin_id,
                    l.debito_amount,
                    l.ip_address,
                    l.created_at,
                    w.display_name as worker_name,
                    a.display_name as admin_name
                FROM $table l
                LEFT JOIN {$wpdb->users} w ON (l.worker_id = w.ID)
                LEFT JOIN {$wpdb->users} a ON (l.admin_id = a.ID)
                WHERE 1=1";
        
        $query_args = [];
        
        if ( $user_id ) {
            $sql .= " AND l.worker_id = %d";
            $query_args[] = intval( $user_id );
        }
        
        $sql .= " ORDER BY l.created_at DESC LIMIT 500";
        
        if ( ! empty( $query_args ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ), ARRAY_A );
        } else {
            $results = $wpdb->get_results( $sql, ARRAY_A );
        }
        
        $logs = [];
        foreach ( $results as $row ) {
            $worker_name = $row['worker_name'] ?: 'Usuario #' . $row['worker_id'];
            $admin_name = $row['admin_name'] ?: 'Admin';
            $amount = number_format( floatval( $row['debito_amount'] ), 2, ',', '.' );
            
            $logs[] = [
                'id' => 'debito_' . $row['id'],
                'user_id' => $row['admin_id'],
                'user_name' => $admin_name,
                'worker_name' => $worker_name,
                'activity_type' => 'debito_trabajador',
                'activity_label' => 'Débito a Trabajador',
                'action_description' => "{$admin_name} aplicó un débito de €{$amount} a {$worker_name}",
                'object_type' => 'debito',
                'object_id' => $row['worker_id'],
                'object_name' => "Débito: €{$amount}",
                'ip_address' => $row['ip_address'] ?: '-',
                'created_at' => $row['created_at'],
                'additional_data' => [
                    'trabajador' => $worker_name,
                    'admin' => $admin_name,
                    'monto' => $amount,
                ],
            ];
        }
        
        return $logs;
    }

    /**
     * Obtener tipos de actividad disponibles
     */
    public function get_activity_types() {
        $types = [
            // Partes
            'creacion_parte' => 'Creación de Parte',
            'aprobacion_parte' => 'Aprobación de Parte',
            'rechazo_parte' => 'Rechazo de Parte',
            // Vacaciones
            'solicitud_vacaciones' => 'Solicitud de Vacaciones',
            'aprobacion_vacaciones' => 'Aprobación de Vacaciones',
            'rechazo_vacaciones' => 'Rechazo de Vacaciones',
            // Usuarios
            'creacion_usuario' => 'Creación de Usuario',
            'edicion_usuario' => 'Edición de Usuario',
            'eliminar_usuario' => 'Eliminación de Usuario',
            // Sedes
            'creacion_sede' => 'Creación de Sede',
            'edicion_sede' => 'Edición de Sede',
            // Débitos
            'debito_trabajador' => 'Débito a Trabajador',
        ];

        return rest_ensure_response( [
            'success' => true,
            'data' => $types,
        ] );
    }
}
