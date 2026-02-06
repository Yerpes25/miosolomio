<?php
namespace ControlHoras\Includes\Services;

if (!defined('ABSPATH')) exit;

/**
 * Servicio para enviar notificaciones push a través de OneSignal
 */
class OneSignalService
{
    private static $instance = null;

    // Credenciales de OneSignal
    private $app_id;
    private $rest_api_key;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Cargar configuración desde archivo seguro
        $config_file = dirname(dirname(dirname(__FILE__))) . '/config/onesignal-config.php';
        if (file_exists($config_file)) {
            $config = require $config_file;
            $this->app_id = $config['app_id'] ?? '';
            $this->rest_api_key = $config['rest_api_key'] ?? '';
        }
    }

    /**
     * Envía una notificación push a los segmentos/usuarios especificados
     *
     * @param array  $interests  Array de intereses (ej: ['admin-notifications', 'user-5'])
     * @param string $title      Título de la notificación
     * @param string $body       Cuerpo de la notificación
     * @param array  $data       Datos adicionales para la notificación
     * @param string $deep_link  URL a abrir al hacer clic (opcional)
     * @return array|WP_Error
     */
    public function send_notification($interests, $title, $body, $data = [], $deep_link = null)
    {
        // Validar parámetros
        if (empty($interests) || !is_array($interests)) {
            return new \WP_Error('invalid_interests', 'Se requiere al menos un interés válido');
        }
        
        if (empty($title) || empty($body)) {
            return new \WP_Error('invalid_params', 'El título y el cuerpo son requeridos');
        }
        
        // Verificar que tenemos las credenciales necesarias
        if (empty($this->app_id) || empty($this->rest_api_key)) {
            error_log('[CHE OneSignal] Credenciales de OneSignal no configuradas');
            // Aún así, guardar en BD para que el polling funcione
            $this->save_notification_to_db($interests, $title, $body, $data);
            return new \WP_Error('onesignal_not_configured', 'OneSignal no está configurado, pero la notificación se guardó en BD');
        }

        // PASO 2: Usar External User IDs en lugar de filters por tags
        // Convertir intereses a External User IDs (formato: 'trabajador_{user_id}')
        $external_user_ids = $this->convert_interests_to_external_user_ids($interests);
        
        // IMPORTANTE: Excluir al originator (quien está haciendo la acción)
        if (!empty($data['exclude_user_id'])) {
            $exclude_id = 'trabajador_' . $data['exclude_user_id'];
            $external_user_ids = array_diff($external_user_ids, [$exclude_id]);
            error_log('[CHE OneSignal] Excluyendo originator ' . $exclude_id . ' de la notificación');
        }
        
        if (empty($external_user_ids)) {
            error_log('[CHE OneSignal] No se encontraron usuarios para los intereses: ' . implode(', ', $interests));
            // Guardar en BD de todas formas
            $this->save_notification_to_db($interests, $title, $body, $data);
            return new \WP_Error('no_users_found', 'No se encontraron usuarios para los intereses especificados');
        }

        error_log('[CHE OneSignal] Enviando notificación a External User IDs: ' . implode(', ', $external_user_ids));

        // Construir el payload de la notificación usando include_external_user_ids
        $notification_data = [
            'app_id' => $this->app_id,
            'headings' => ['en' => $title, 'es' => $title],
            'contents' => ['en' => $body, 'es' => $body],
            'include_external_user_ids' => $external_user_ids, // PASO 2: Usar External User IDs
            'data' => $data,
        ];

        // Añadir deep link si se proporciona
        if ($deep_link) {
            $notification_data['url'] = $deep_link;
        }

        // Añadir icono del sitio solo si existe y es una URL válida
        $site_icon = get_site_icon_url(192);
        if ($site_icon && filter_var($site_icon, FILTER_VALIDATE_URL)) {
            $notification_data['chrome_web_icon'] = $site_icon;
            $notification_data['firefox_icon'] = $site_icon;
        }

        // Enviar notificación a través de la API de OneSignal
        $url = 'https://onesignal.com/api/v1/notifications';
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Basic ' . $this->rest_api_key,
            ],
            'body'    => json_encode($notification_data),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('[CHE OneSignal] Error al enviar notificación: ' . $response->get_error_message());
            // Guardar en BD de todas formas
            $this->save_notification_to_db($interests, $title, $body, $data);
            return $response;
        }

        $body_response = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            error_log('[CHE OneSignal] Error HTTP ' . $status_code . ': ' . print_r($body_response, true));
            // Aún así, guardar en BD
            $this->save_notification_to_db($interests, $title, $body, $data);
            return new \WP_Error('onesignal_error', 'Error al enviar notificación', $body_response);
        }
        
        // Guardar en BD después de enviar (para tener registro local)
        $this->save_notification_to_db($interests, $title, $body, $data);

        return $body_response;
    }

    /**
     * PASO 2: Convierte intereses a External User IDs (formato: 'trabajador_{user_id}')
     * Este método reemplaza convert_interests_to_filters para usar External User IDs
     * 
     * @param array $interests Array de intereses (ej: ['admin-notifications', 'user-5'])
     * @return array Array de External User IDs (ej: ['trabajador_5', 'trabajador_3'])
     */
    private function convert_interests_to_external_user_ids($interests)
    {
        $user_ids = $this->get_users_from_interests($interests);
        
        if (empty($user_ids)) {
            return [];
        }
        
        // Convertir User IDs a External User IDs (formato: 'trabajador_{user_id}')
        $external_user_ids = array_map(function($user_id) {
            return 'trabajador_' . $user_id;
        }, $user_ids);
        
        return array_unique($external_user_ids);
    }
    
    /**
     * Convierte intereses de formato Pusher a filtros de OneSignal (DEPRECADO - usar convert_interests_to_external_user_ids)
     * OneSignal usa tags personalizados para segmentación
     * 
     * @param array $interests Array de intereses (ej: ['admin-notifications', 'user-5'])
     * @return array Array de filtros para OneSignal
     */
    private function convert_interests_to_filters($interests)
    {
        $filters = [];
        $user_ids = $this->get_users_from_interests($interests);

        if (empty($user_ids)) {
            return [];
        }

        // Si hay un solo usuario, usar player_id directo
        // Si hay múltiples, usar tags
        if (count($user_ids) === 1) {
            // OneSignal: usar tag "user_id" para filtrar
            $filters[] = [
                'field' => 'tag',
                'key' => 'user_id',
                'relation' => '=',
                'value' => (string) $user_ids[0]
            ];
        } else {
            // Múltiples usuarios: usar OR con tags
            $or_filters = [];
            foreach ($user_ids as $user_id) {
                $or_filters[] = [
                    'field' => 'tag',
                    'key' => 'user_id',
                    'relation' => '=',
                    'value' => (string) $user_id
                ];
            }
            
            // Envolver en un filtro OR si hay múltiples
            if (count($or_filters) === 1) {
                $filters = $or_filters;
            } else {
                // OneSignal requiere un array con "operator": "OR"
                // Para múltiples usuarios, usar un enfoque diferente: segmentos
                // Por ahora, usar tags individuales con OR
                $filters = [
                    ['operator' => 'OR'] + $or_filters
                ];
            }
        }

        // Alternativa: usar segmentos si están configurados
        // Por ejemplo, 'admin-notifications' -> segment 'Admin Users'
        // Esto sería más eficiente si tienes muchos usuarios

        return $filters;
    }
    
    /**
     * Guarda la notificación en la base de datos para que el polling la detecte
     * IMPORTANTE: Excluye al usuario que originó la acción (ej: el trabajador que solicita vacaciones)
     * 
     * @param array  $interests  Intereses de Pusher
     * @param string $title      Título de la notificación
     * @param string $body       Cuerpo de la notificación
     * @param array  $data       Datos adicionales (puede contener 'exclude_user_id' para excluir un usuario)
     * @return array Array de user_id => notification_id (devuelve array vacío si hay error)
     */
    private function save_notification_to_db($interests, $title, $body, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'che_notifications';
        
        // Crear tabla si no existe
        $this->ensure_notifications_table();
        
        // Determinar los usuarios destinatarios según los intereses
        $user_ids = $this->get_users_from_interests($interests);
        
        if (empty($user_ids)) {
            error_log('[CHE OneSignal] No se encontraron usuarios para los intereses: ' . implode(', ', $interests));
            return [];
        }
        
        // Excluir al usuario que originó la acción (ej: el trabajador que solicita vacaciones no debe recibir su propia notificación)
        $exclude_user_id = isset($data['exclude_user_id']) ? (int)$data['exclude_user_id'] : 0;
        if ($exclude_user_id > 0) {
            $user_ids = array_filter($user_ids, function($user_id) use ($exclude_user_id) {
                return (int)$user_id !== $exclude_user_id;
            });
            error_log('[CHE OneSignal] Excluyendo usuario ' . $exclude_user_id . ' de la notificación');
        }
        
        if (empty($user_ids)) {
            error_log('[CHE OneSignal] No quedan usuarios después de excluir al originador');
            return [];
        }
        
        // Usar transacción para insertar todas las notificaciones de forma eficiente
        $wpdb->query('START TRANSACTION');
        
        $inserted = 0;
        $db_ids = []; // Para retornar los IDs de notificaciones creadas
        
        foreach ($user_ids as $user_id) {
            $result = $wpdb->insert(
                $table,
                [
                    'user_id'    => $user_id,
                    'title'      => sanitize_text_field($title),
                    'message'    => sanitize_textarea_field($body),
                    'type'       => sanitize_text_field($data['type'] ?? 'default'),
                    'is_read'    => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s']
            );
            
            if ($result === false) {
                error_log('[CHE OneSignal] Error al insertar notificación para user ' . $user_id . ': ' . $wpdb->last_error);
            } else {
                $inserted++;
                $db_ids[$user_id] = $wpdb->insert_id; // Guardar el ID de la notificación para este usuario
            }
        }
        
        // Confirmar transacción si todas las inserciones fueron exitosas
        if ($inserted === count($user_ids)) {
            $wpdb->query('COMMIT');
            error_log('[CHE OneSignal] Notificaciones guardadas correctamente para ' . $inserted . ' usuarios');
        } else {
            $wpdb->query('ROLLBACK');
            error_log('[CHE OneSignal] Error al guardar algunas notificaciones, transacción revertida');
            $db_ids = []; // Retornar array vacío si falló
        }
        
        return $db_ids;
    }
    
    /**
     * Crea la tabla de notificaciones si no existe
     * OPTIMIZADO: Usa transients para cachear verificación (1 hora)
     */
    private function ensure_notifications_table()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'che_notifications';
        
        // Cachear verificación en transient para evitar SHOW TABLES en cada petición
        $cache_key = 'che_notifications_table_exists';
        $table_exists = get_transient($cache_key);
        
        if ($table_exists === false) {
            // Solo verificar una vez por hora
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            set_transient($cache_key, $table_exists ? 'yes' : 'no', HOUR_IN_SECONDS);
        } else {
            $table_exists = $table_exists === 'yes';
        }
        
        if (!$table_exists) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT,
                type VARCHAR(50) DEFAULT 'default',
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY is_read (is_read),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            dbDelta($sql);
            
            // Invalidar caché después de crear tabla
            delete_transient($cache_key);
            
            error_log('[CHE OneSignal] Tabla de notificaciones creada');
        }
    }
    
    /**
     * Obtiene los IDs de usuarios según los intereses
     * IMPORTANTE: Solo incluye usuarios que tengan EXACTAMENTE el rol correcto
     */
    private function get_users_from_interests($interests)
    {
        $user_ids = [];
        
        foreach ($interests as $interest) {
            // Interés específico de usuario: user-123
            if (preg_match('/^user-(\d+)$/', $interest, $matches)) {
                $user_id = (int)$matches[1];
                // Validar que el usuario existe antes de agregarlo
                if (get_userdata($user_id)) {
                    $user_ids[] = $user_id;
                    error_log('[CHE OneSignal] Interés user específico: ' . $user_id);
                } else {
                    error_log('[CHE OneSignal] Usuario no encontrado: ' . $user_id);
                }
            }
            // Interés de admins (incluir todos los roles administrativos)
            // IMPORTANTE: Solo incluir usuarios que sean admin/super_admin, NO workers
            elseif ($interest === 'admin-notifications' || $interest === 'vacaciones-solicitudes') {
                // Buscar usuarios con roles admin, pero EXCLUIR workers
                $admins = get_users([
                    'role__in' => ['admin', 'super_admin', 'administrator'],
                    'fields' => ['ID', 'display_name']
                ]);
                
                // Filtrar: solo incluir si NO tienen rol 'worker'
                foreach ($admins as $admin) {
                    $user_obj = get_userdata($admin->ID);
                    $user_roles = $user_obj ? $user_obj->roles : [];
                    
                    // Solo incluir si NO tiene rol 'worker'
                    if (!in_array('worker', $user_roles)) {
                        $user_ids[] = $admin->ID;
                        error_log('[CHE OneSignal] Admin encontrado: ID=' . $admin->ID . ', nombre=' . $admin->display_name . ', roles=' . implode(',', $user_roles));
                    } else {
                        error_log('[CHE OneSignal] Usuario ' . $admin->ID . ' excluido (tiene rol worker además de admin)');
                    }
                }
                
                error_log('[CHE OneSignal] Buscando admins para interés: ' . $interest . ', encontrados: ' . count($admins) . ', filtrados: ' . count(array_filter($user_ids, function($id) use ($admins) {
                    return in_array($id, array_column($admins, 'ID'));
                })));
            }
            // Interés de super admins (solo super_admin, sin workers)
            elseif ($interest === 'super-admin-notifications') {
                $super_admins = get_users([
                    'role__in' => ['super_admin', 'administrator'],
                    'fields' => ['ID']
                ]);
                foreach ($super_admins as $sa) {
                    $user_obj = get_userdata($sa->ID);
                    $user_roles = $user_obj ? $user_obj->roles : [];
                    
                    // Solo incluir si NO tiene rol 'worker'
                    if (!in_array('worker', $user_roles)) {
                        $user_ids[] = $sa->ID;
                    }
                }
            }
            // Interés de workers (SOLO workers, sin admins)
            elseif ($interest === 'worker-notifications') {
                // Buscar SOLO usuarios con rol 'worker'
                // EXCLUIR usuarios que también tengan roles admin
                $workers = get_users([
                    'role' => 'worker',
                    'fields' => ['ID']
                ]);
                
                error_log('[CHE OneSignal] Buscando workers para interés: ' . $interest . ', encontrados: ' . count($workers));
                
                foreach ($workers as $worker) {
                    $user_obj = get_userdata($worker->ID);
                    $user_roles = $user_obj ? $user_obj->roles : [];
                    
                    // Solo incluir si tiene rol 'worker' y NO tiene roles admin
                    if (in_array('worker', $user_roles) && 
                        !in_array('admin', $user_roles) && 
                        !in_array('super_admin', $user_roles) &&
                        !in_array('administrator', $user_roles)) {
                        $user_ids[] = $worker->ID;
                        error_log('[CHE OneSignal] Worker incluido: ID=' . $worker->ID . ', roles=' . implode(',', $user_roles));
                    } else {
                        error_log('[CHE OneSignal] Worker ' . $worker->ID . ' excluido (tiene roles admin: ' . implode(',', $user_roles) . ')');
                    }
                }
                
                error_log('[CHE OneSignal] Workers finales después de filtrar: ' . count($user_ids));
            }
        }
        
        $unique_user_ids = array_unique($user_ids);
        error_log('[CHE OneSignal] Intereses: ' . implode(', ', $interests) . ' -> Users únicos: ' . implode(', ', $unique_user_ids));
        
        return $unique_user_ids;
    }

    /**
     * Envía notificación a un usuario específico
     * OPTIMIZADO: Usa external_user_id directamente en lugar de pasar por interests
     *
     * @param int    $user_id  ID del usuario
     * @param string $title    Título
     * @param string $body     Cuerpo
     * @param array  $data     Datos adicionales
     * @param string $deep_link URL opcional
     */
    public function send_to_user($user_id, $title, $body, $data = [], $deep_link = null)
    {
        // Validar parámetros
        if (empty($title) || empty($body)) {
            return new \WP_Error('invalid_params', 'El título y el cuerpo son requeridos');
        }
        
        // Verificar credenciales
        if (empty($this->app_id) || empty($this->rest_api_key)) {
            error_log('[CHE OneSignal] Credenciales de OneSignal no configuradas');
            // Guardar en BD para fallback
            $this->save_notification_to_db(['user-' . $user_id], $title, $body, $data);
            return new \WP_Error('onesignal_not_configured', 'OneSignal no está configurado, pero la notificación se guardó en BD');
        }
        
        // External User ID sincronizado con JS (formato: 'trabajador_{user_id}')
        $external_id = 'trabajador_' . $user_id;
        
        // Construir payload directamente usando external_user_id
        $fields = [
            'app_id' => $this->app_id,
            'include_external_user_ids' => [$external_id],
            'contents' => ['en' => $body, 'es' => $body],
            'headings' => ['en' => $title, 'es' => $title],
            'data' => $data,
        ];
        
        // Añadir deep link si se proporciona
        if ($deep_link) {
            $fields['url'] = $deep_link;
        }
        
        // Añadir icono del sitio si existe
        $site_icon = get_site_icon_url(192);
        if ($site_icon && filter_var($site_icon, FILTER_VALIDATE_URL)) {
            $fields['chrome_web_icon'] = $site_icon;
            $fields['firefox_icon'] = $site_icon;
        }
        
        // Enviar notificación
        $url = 'https://onesignal.com/api/v1/notifications';
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Basic ' . $this->rest_api_key,
            ],
            'body'    => json_encode($fields),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            error_log('[CHE OneSignal] Error al enviar notificación: ' . $response->get_error_message());
            $this->save_notification_to_db(['user-' . $user_id], $title, $body, $data);
            return $response;
        }
        
        $body_response = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            error_log('[CHE OneSignal] Error HTTP ' . $status_code . ': ' . print_r($body_response, true));
            $this->save_notification_to_db(['user-' . $user_id], $title, $body, $data);
            return new \WP_Error('onesignal_error', 'Error al enviar notificación', $body_response);
        }
        
        // Guardar en BD después de enviar exitosamente
        $this->save_notification_to_db(['user-' . $user_id], $title, $body, $data);
        
        error_log('[CHE OneSignal] ✅ Notificación enviada a external_user_id: ' . $external_id);
        
        return $body_response;
    }

    /**
     * Envía notificación a todos los administradores
     *
     * @param string $title    Título
     * @param string $body     Cuerpo
     * @param array  $data     Datos adicionales
     * @param string $deep_link URL opcional
     */
    public function send_to_admins($title, $body, $data = [], $deep_link = null)
    {
        $interests = ['admin-notifications'];
        return $this->send_notification($interests, $title, $body, $data, $deep_link);
    }

    /**
     * Envía notificación a todos los trabajadores
     *
     * @param string $title    Título
     * @param string $body     Cuerpo
     * @param array  $data     Datos adicionales
     * @param string $deep_link URL opcional
     */
    public function send_to_workers($title, $body, $data = [], $deep_link = null)
    {
        $interests = ['worker-notifications'];
        return $this->send_notification($interests, $title, $body, $data, $deep_link);
    }

    // =========================================
    // MÉTODOS ESPECÍFICOS PARA VACACIONES
    // =========================================

    /**
     * Notifica a los admins cuando un trabajador solicita vacaciones
     *
     * @param int    $vacacion_id   ID del post de vacación
     * @param int    $worker_id     ID del trabajador
     * @param string $fecha_inicio  Fecha inicio
     * @param string $fecha_fin     Fecha fin
     */
    public function notify_vacation_request($vacacion_id, $worker_id, $fecha_inicio, $fecha_fin)
    {
        $worker = get_userdata($worker_id);
        $worker_name = $worker ? $worker->display_name : 'Un trabajador';

        $title = 'Admin: has recibido 📅 Nueva solicitud de vacaciones';
        $body = sprintf(
            '%s ha solicitado vacaciones del %s al %s',
            $worker_name,
            date('d/m/Y', strtotime($fecha_inicio)),
            date('d/m/Y', strtotime($fecha_fin))
        );

        $data = [
            'type'           => 'vacacion_solicitud',
            'vacacion_id'    => $vacacion_id,
            'worker_id'      => $worker_id,
            'fecha_inicio'   => $fecha_inicio,
            'fecha_fin'      => $fecha_fin,
            'exclude_user_id' => $worker_id, // IMPORTANTE: Excluir al trabajador que solicita
        ];

        $deep_link = home_url('/administracion/#vacaciones');

        // Enviar solo a admins con el interés correcto
        return $this->send_notification(['vacaciones-solicitudes'], $title, $body, $data, $deep_link);
    }

    /**
     * Notifica al trabajador cuando su solicitud es aprobada
     *
     * @param int    $vacacion_id   ID del post de vacación
     * @param int    $worker_id     ID del trabajador
     * @param string $fecha_inicio  Fecha inicio
     * @param string $fecha_fin     Fecha fin
     */
    public function notify_vacation_approved($vacacion_id, $worker_id, $fecha_inicio, $fecha_fin)
    {
        $worker = get_userdata($worker_id);
        $worker_name = $worker ? $worker->display_name : 'Trabajador';
        
        $title = sprintf('%s: has recibido ✅ ¡Vacaciones aprobadas!', $worker_name);
        $body = sprintf(
            'Tu solicitud de vacaciones del %s al %s ha sido aprobada',
            date('d/m/Y', strtotime($fecha_inicio)),
            date('d/m/Y', strtotime($fecha_fin))
        );

        $data = [
            'type'         => 'vacacion_aprobada',
            'vacacion_id'  => $vacacion_id,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin'    => $fecha_fin,
        ];

        $deep_link = home_url('/trabajadores/panel/#vacaciones');

        return $this->send_to_user($worker_id, $title, $body, $data, $deep_link);
    }

    /**
     * Notifica al trabajador cuando su solicitud es rechazada
     *
     * @param int    $vacacion_id   ID del post de vacación
     * @param int    $worker_id     ID del trabajador
     * @param string $fecha_inicio  Fecha inicio
     * @param string $fecha_fin     Fecha fin
     * @param string $motivo        Motivo del rechazo (opcional)
     */
    public function notify_vacation_rejected($vacacion_id, $worker_id, $fecha_inicio, $fecha_fin, $motivo = '')
    {
        $worker = get_userdata($worker_id);
        $worker_name = $worker ? $worker->display_name : 'Trabajador';
        
        $title = sprintf('%s: has recibido ❌ Vacaciones rechazadas', $worker_name);
        $body = sprintf(
            'Tu solicitud de vacaciones del %s al %s ha sido rechazada',
            date('d/m/Y', strtotime($fecha_inicio)),
            date('d/m/Y', strtotime($fecha_fin))
        );

        if ($motivo) {
            $body .= '. Motivo: ' . $motivo;
        }

        $data = [
            'type'         => 'vacacion_rechazada',
            'vacacion_id'  => $vacacion_id,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin'    => $fecha_fin,
            'motivo'       => $motivo,
        ];

        $deep_link = home_url('/trabajadores/panel/#vacaciones');

        return $this->send_to_user($worker_id, $title, $body, $data, $deep_link);
    }

    // =========================================================================
    // NOTIFICACIONES DE PARTES DE TRABAJO
    // =========================================================================

    /**
     * Notifica al trabajador cuando su parte es validado
     *
     * @param int    $parte_id    ID del parte
     * @param int    $worker_id   ID del trabajador
     * @param string $fecha       Fecha del parte
     */
    public function notify_parte_validated($parte_id, $worker_id, $fecha)
    {
        $worker = get_userdata($worker_id);
        $worker_name = $worker ? $worker->display_name : 'Trabajador';
        
        $title = sprintf('%s: has recibido ✔️ Parte validado', $worker_name);
        $body = sprintf(
            'Tu parte de trabajo del %s ha sido validado',
            date('d/m/Y', strtotime($fecha))
        );

        $data = [
            'type'     => 'parte_validado',
            'parte_id' => $parte_id,
            'fecha'    => $fecha,
        ];

        $deep_link = home_url('/trabajadores/panel/#partes');

        return $this->send_to_user($worker_id, $title, $body, $data, $deep_link);
    }

    /**
     * Notifica al trabajador cuando su parte es rechazado
     *
     * @param int    $parte_id    ID del parte
     * @param int    $worker_id   ID del trabajador
     * @param string $fecha       Fecha del parte
     * @param string $motivo      Motivo del rechazo (opcional)
     */
    public function notify_parte_rejected($parte_id, $worker_id, $fecha, $motivo = '')
    {
        $worker = get_userdata($worker_id);
        $worker_name = $worker ? $worker->display_name : 'Trabajador';
        
        $title = sprintf('%s: has recibido ✖️ Parte rechazado', $worker_name);
        $body = sprintf(
            'Tu parte de trabajo del %s ha sido rechazado',
            date('d/m/Y', strtotime($fecha))
        );

        if ($motivo) {
            $body .= '. Motivo: ' . $motivo;
        }

        $data = [
            'type'     => 'parte_rechazado',
            'parte_id' => $parte_id,
            'fecha'    => $fecha,
            'motivo'   => $motivo,
        ];

        $deep_link = home_url('/trabajadores/panel/#partes');

        return $this->send_to_user($worker_id, $title, $body, $data, $deep_link);
    }

    /**
     * Notifica a los admins cuando un trabajador envía un nuevo parte
     *
     * @param int    $parte_id    ID del parte
     * @param int    $worker_id   ID del trabajador
     * @param string $fecha       Fecha del parte
     */
    public function notify_new_parte($parte_id, $worker_id, $fecha)
    {
        $worker = get_userdata($worker_id);
        $worker_name = $worker ? $worker->display_name : 'Un trabajador';

        $title = 'Admin: has recibido 📝 Nuevo parte de trabajo';
        $body = sprintf(
            '%s ha enviado un parte del %s',
            $worker_name,
            date('d/m/Y', strtotime($fecha))
        );

        $data = [
            'type'        => 'parte_nuevo',
            'parte_id'    => $parte_id,
            'worker_id'   => $worker_id,
            'worker_name' => $worker_name,
            'fecha'       => $fecha,
        ];

        $deep_link = home_url('/administracion/#partes');

        return $this->send_to_admins($title, $body, $data, $deep_link);
    }

    // =========================================================================
    // NOTIFICACIONES DE USUARIOS
    // =========================================================================

    /**
     * Notifica a todos los super_admins cuando se crea un nuevo super_admin
     *
     * @param int    $new_user_id    ID del nuevo usuario
     * @param string $new_user_name  Nombre del nuevo usuario
     * @param int    $creator_id     ID del usuario que lo creó
     * @param string $creator_name   Nombre del usuario que lo creó
     */
    public function notify_super_admin_created($new_user_id, $new_user_name, $creator_id, $creator_name)
    {
        $title = 'Nuevo Super Administrador';
        $body = sprintf(
            '%s ha creado un nuevo super administrador: %s',
            $creator_name,
            $new_user_name
        );

        $data = [
            'type'          => 'super_admin_created',
            'new_user_id'   => $new_user_id,
            'new_user_name' => $new_user_name,
            'creator_id'    => $creator_id,
            'creator_name'  => $creator_name,
        ];

        $deep_link = home_url('/administracion/#usuarios');

        // Enviar solo a super_admins
        return $this->send_to_super_admins($title, $body, $data, $deep_link);
    }

    /**
     * Envía notificación a todos los super_admins
     *
     * @param string $title     Título de la notificación
     * @param string $body      Cuerpo del mensaje
     * @param array  $data      Datos adicionales
     * @param string $deep_link URL para abrir al hacer click
     * @return bool|array
     */
    private function send_to_super_admins($title, $body, $data = [], $deep_link = '')
    {
        // Obtener todos los super_admins
        $super_admins = get_users(['role' => 'super_admin']);
        
        if (empty($super_admins)) {
            return false;
        }

        // Construir intereses para todos los super_admins
        $interests = ['super-admin-notifications'];
        foreach ($super_admins as $admin) {
            $interests[] = 'user-' . $admin->ID;
        }

        return $this->send_notification($interests, $title, $body, $data, $deep_link);
    }
}
