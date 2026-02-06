<?php
/**
 * Controlador de Notificaciones - Versión Refactorizada
 * Maneja todas las notificaciones de forma eficiente con código reutilizable
 */

namespace ControlHoras\Includes\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationsController {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Constructor vacío - las rutas se registran desde Init.php
    }
    
    /**
     * Registra los hooks AJAX (más confiables que REST API en móviles)
     */
    public function register_ajax_hooks() {
        add_action('wp_ajax_che_get_notifications', [$this, 'ajax_get_notifications']);
        add_action('wp_ajax_che_get_unread_count', [$this, 'ajax_get_unread_count']);
        add_action('wp_ajax_che_mark_notifications_read', [$this, 'ajax_mark_as_read']);
    }
    
    /**
     * AJAX: Obtener notificaciones
     */
    public function ajax_get_notifications() {
        // Verificar nonce si está presente (opcional para mejor seguridad)
        if (isset($_POST['_ajax_nonce'])) {
            $nonce = sanitize_text_field($_POST['_ajax_nonce']);
            if (!wp_verify_nonce($nonce, 'che_ajax_nonce') && !wp_verify_nonce($nonce, 'wp_rest')) {
                // Si el nonce no coincide, pero el usuario está logueado, continuar de todas formas
                // (algunas instalaciones pueden tener problemas con los nonces)
                error_log('[CHE Notifications] ⚠️ Nonce no verificado, pero continuando porque el usuario está autenticado');
            }
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autenticado'], 401);
            return;
        }
        
        $user = wp_get_current_user();
        $read_notifications = get_user_meta($user->ID, 'che_read_notifications', true) ?: [];
        $notifications = $this->collect_all_notifications($user);
        
        usort($notifications, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        foreach ($notifications as &$notif) {
            // Para notificaciones de BD, el campo 'read' ya viene del query
            // Para otras notificaciones, verificar en user_meta
            $is_read_value = false;
            if (isset($notif['db_id'])) {
                $is_read_value = isset($notif['read']) ? $notif['read'] : false;
            } else {
                $is_read_value = in_array($notif['id'], $read_notifications);
            }
            $notif['is_read'] = $is_read_value;
            $notif['read'] = $is_read_value; // Mantener ambos por compatibilidad
        }
        
        wp_send_json_success($notifications);
    }
    
    /**
     * AJAX: Obtener conteo no leídas
     * OPTIMIZADO: Usa caché de transients para evitar consultas pesadas
     */
    public function ajax_get_unread_count() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autenticado'], 401);
        }
        
        $user_id = get_current_user_id();
        
        // Intentar obtener de caché (1 minuto de duración)
        $cache_key = 'che_unread_count_' . $user_id;
        $count = get_transient($cache_key);
        
        if (false === $count) {
            // Caché no disponible, calcular el conteo
            $user = wp_get_current_user();
            $read_notifications = get_user_meta($user->ID, 'che_read_notifications', true) ?: [];
            $notifications = $this->collect_all_notifications($user);
            
            $unread = 0;
            foreach ($notifications as $notif) {
                if (isset($notif['db_id'])) {
                    if (!$notif['read']) $unread++;
                } else {
                    if (!in_array($notif['id'], $read_notifications)) $unread++;
                }
            }
            
            $count = $unread;
            // Guardar en caché por 1 minuto
            set_transient($cache_key, $count, 60);
        }
        
        wp_send_json_success(['count' => (int)$count]);
    }
    
    /**
     * AJAX: Marcar como leídas
     */
    public function ajax_mark_as_read() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autenticado'], 401);
        }
        
        $ids = isset($_POST['ids']) ? array_map('sanitize_text_field', $_POST['ids']) : [];
        if (empty($ids)) {
            wp_send_json_error(['message' => 'No IDs provided']);
        }
        
        global $wpdb;
        $user = wp_get_current_user();
        $read_notifications = get_user_meta($user->ID, 'che_read_notifications', true) ?: [];
        $table_name = $wpdb->prefix . 'che_notifications';
        
        foreach ($ids as $id) {
            if (strpos($id, 'db_') === 0) {
                $db_id = intval(str_replace('db_', '', $id));
                $wpdb->update($table_name, ['is_read' => 1], ['id' => $db_id]);
            } else {
                if (!in_array($id, $read_notifications)) {
                    $read_notifications[] = $id;
                }
            }
        }
        
        update_user_meta($user->ID, 'che_read_notifications', $read_notifications);
        
        // Invalidar caché del conteo de no leídas
        $cache_key = 'che_unread_count_' . $user->ID;
        delete_transient($cache_key);
        
        wp_send_json_success(['marked' => count($ids)]);
    }
    
    /**
     * Registra las rutas de la API REST
     */
    public function register_routes() {
        register_rest_route('che/v1', '/notifications/unread-count', [
            'methods' => 'GET',
            'callback' => [$this, 'get_unread_count'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        register_rest_route('che/v1', '/notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_notifications'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        register_rest_route('che/v1', '/notifications/mark-read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_as_read'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }
    
    /**
     * Obtiene el conteo de notificaciones no leídas
     * OPTIMIZADO: Usa caché de transients para evitar consultas pesadas
     */
    public function get_unread_count() {
        $user_id = get_current_user_id();
        
        // Intentar obtener de caché (1 minuto de duración)
        $cache_key = 'che_unread_count_' . $user_id;
        $count = get_transient($cache_key);
        
        if (false === $count) {
            // Caché no disponible, calcular el conteo
            $user = wp_get_current_user();
            $read_notifications = get_user_meta($user->ID, 'che_read_notifications', true) ?: [];
            $notifications = $this->collect_all_notifications($user);
            
            $unread = 0;
            foreach ($notifications as $notif) {
                if (isset($notif['db_id'])) {
                    if (!$notif['read']) {
                        $unread++;
                    }
                } else {
                    if (!in_array($notif['id'], $read_notifications)) {
                        $unread++;
                    }
                }
            }
            
            $count = $unread;
            // Guardar en caché por 1 minuto
            set_transient($cache_key, $count, 60);
        }
        
        return rest_ensure_response(['count' => (int)$count]);
    }
    
    /**
     * Obtiene todas las notificaciones del usuario
     */
    public function get_notifications() {
        $user = wp_get_current_user();
        $read_notifications = get_user_meta($user->ID, 'che_read_notifications', true) ?: [];
        
        $notifications = $this->collect_all_notifications($user);
        
        // Ordenar por fecha más reciente
        usort($notifications, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // Añadir estado de lectura con 'is_read' para consistencia con el frontend
        foreach ($notifications as &$notif) {
            // Si ya tiene 'read', copiar a 'is_read' también
            $is_read_value = isset($notif['read']) ? $notif['read'] : in_array($notif['id'], $read_notifications);
            $notif['is_read'] = $is_read_value;
            $notif['read'] = $is_read_value; // Mantener ambos por compatibilidad
        }
        
        return rest_ensure_response(['notifications' => $notifications]);
    }
    
    /**
     * Marca notificaciones como leídas
     */
    public function mark_as_read($request) {
        global $wpdb;
        $user = wp_get_current_user();
        $notification_ids = $request->get_param('ids') ?: [];
        $single_id = $request->get_param('notification_id');
        
        // Si viene un ID único, añadirlo al array
        if ($single_id) {
            $notification_ids[] = sanitize_text_field($single_id);
        }
        
        $read_notifications = get_user_meta($user->ID, 'che_read_notifications', true) ?: [];
        
        if ($request->get_param('all')) {
            $all = $this->collect_all_notifications($user);
            foreach ($all as $notif) {
                if (!in_array($notif['id'], $read_notifications)) {
                    $read_notifications[] = $notif['id'];
                }
                // Marcar en BD si es notificación de BD
                if (isset($notif['db_id'])) {
                    $this->mark_db_notification_read($notif['db_id']);
                }
            }
        } else {
            foreach ($notification_ids as $id) {
                if (!in_array($id, $read_notifications)) {
                    $read_notifications[] = $id;
                }
                // Si es notificación de BD (formato: db_123)
                if (strpos($id, 'db_') === 0) {
                    $db_id = (int) str_replace('db_', '', $id);
                    $this->mark_db_notification_read($db_id);
                }
            }
        }
        
        update_user_meta($user->ID, 'che_read_notifications', array_values(array_unique($read_notifications)));
        
        // Invalidar caché del conteo de no leídas
        $cache_key = 'che_unread_count_' . $user->ID;
        delete_transient($cache_key);
        
        return rest_ensure_response(['success' => true]);
    }
    
    /**
     * Marca una notificación de BD como leída
     */
    private function mark_db_notification_read($db_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'che_notifications';
        $wpdb->update($table, ['is_read' => 1], ['id' => $db_id], ['%d'], ['%d']);
    }
    
    /**
     * Recolecta todas las notificaciones según el rol del usuario
     */
    private function collect_all_notifications($user) {
        $notifications = [];
        $roles = $user->roles;
        
        $is_admin = in_array('admin', $roles) || in_array('super_admin', $roles);
        $is_worker = in_array('worker', $roles);
        $is_super_admin = in_array('super_admin', $roles);
        
        // PRIMERO: Obtener notificaciones de la tabla de BD (las más recientes/push)
        // Estas son las notificaciones reales que se enviaron vía OneSignal y se guardaron en BD
        $db_notifications = $this->get_db_notifications($user->ID);
        
        // Crear un mapa de notificaciones de BD para detección de duplicados
        // Usar tipo + título + mensaje como clave única
        $db_notifications_map = [];
        foreach ($db_notifications as $db_notif) {
            // Crear una clave única basada en tipo, título y fecha aproximada (mismo día)
            $date_key = isset($db_notif['date']) ? substr($db_notif['date'], 0, 10) : '';
            $unique_key = md5(
                ($db_notif['type'] ?? '') . '|' .
                ($db_notif['title'] ?? '') . '|' .
                ($db_notif['message'] ?? '') . '|' .
                $date_key
            );
            $db_notifications_map[$unique_key] = true;
        }
        
        $notifications = array_merge($notifications, $db_notifications);
        
        // Notificación de bienvenida (para cualquier usuario nuevo)
        $welcome = $this->get_welcome_notification($user->ID);
        if ($welcome) {
            $notifications[] = $welcome;
        }
        
        // Notificaciones dinámicas DESHABILITADAS - Solo usar notificaciones de BD
        // Las notificaciones dinámicas causaban duplicados y confusión
        // Todas las notificaciones ahora se guardan en BD cuando se envían vía OneSignal
        // No mostrar notificaciones dinámicas generadas desde CPTs
        
        return $notifications;
    }
    
    /**
     * Obtiene las notificaciones guardadas en la tabla de BD
     * OPTIMIZADO: Eliminada verificación lenta de tabla en cada petición
     */
    private function get_db_notifications($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'che_notifications';
        
        // La tabla se crea en la activación, no necesitamos verificar en cada petición
        // Esto elimina una consulta SQL lenta (SHOW TABLES)
        $notifications = [];
        
        // Usar try-catch implícito: si la tabla no existe, retornar array vacío
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, type, title, message, created_at, is_read 
             FROM $table 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 50",
            $user_id
        ));
        
        // Si hay error SQL, retornar array vacío (la tabla no existe o hay problema)
        if ($wpdb->last_error) {
            error_log('[CHE Notifications] Error SQL al obtener notificaciones: ' . $wpdb->last_error);
            return [];
        }
        
        if (!$results) {
            return [];
        }
        
        foreach ($results as $row) {
            $notifications[] = [
                'id'      => 'db_' . $row->id,
                'type'    => $row->type,
                'title'   => $row->title,
                'message' => $row->message,
                'date'    => $row->created_at,
                'read'    => (bool) $row->is_read,
                'db_id'   => $row->id, // Para marcar como leída
            ];
        }
        
        return $notifications;
    }
    
    // ==========================================
    // MÉTODOS DE CONSULTA GENÉRICOS
    // ==========================================
    
    /**
     * Consulta genérica para CPT con estado
     */
    private function query_cpt_by_estado($post_type, $estado, $author_id = null) {
        $args = [
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'estado',
                    'value' => $estado,
                    'compare' => '='
                ]
            ]
        ];
        
        if ($author_id) {
            $args['author'] = $author_id;
        }
        
        return get_posts($args);
    }
    
    /**
     * Formatea un post como notificación
     */
    private function format_notification($post, $type, $title, $message) {
        // Convertir fecha a formato ISO 8601 con zona horaria del servidor
        $date = new \DateTime($post->post_date, wp_timezone());
        
        return [
            'id' => $type . '_' . $post->ID,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'date' => $date->format('c'), // ISO 8601 con zona horaria
            'post_id' => $post->ID
        ];
    }
    
    /**
     * Consulta partes por estado_validacion
     */
    private function query_partes_by_validacion($estado_validacion, $author_id = null) {
        $args = [
            'post_type' => 'parte_horario',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'estado_validacion',
                    'value' => $estado_validacion,
                    'compare' => '='
                ]
            ]
        ];
        
        if ($author_id) {
            $args['author'] = $author_id;
        }
        
        return get_posts($args);
    }
    
    // ==========================================
    // VACACIONES
    // ==========================================
    
    private function get_vacaciones_pendientes() {
        $posts = $this->query_cpt_by_estado('che_vacacion', 'pendiente');
        $notifications = [];
        
        foreach ($posts as $post) {
            $user = get_user_by('ID', $post->post_author);
            $name = $user ? $user->display_name : 'Usuario';
            
            $notifications[] = $this->format_notification(
                $post,
                'vacacion_pendiente',
                'Solicitud de vacaciones',
                "{$name} ha solicitado vacaciones"
            );
        }
        
        return $notifications;
    }
    
    private function get_vacaciones_aprobadas($user_id) {
        $posts = $this->query_cpt_by_estado('che_vacacion', 'aprobada', $user_id);
        $notifications = [];
        
        foreach ($posts as $post) {
            $inicio = get_post_meta($post->ID, 'fecha_inicio', true);
            $fin = get_post_meta($post->ID, 'fecha_fin', true);
            
            $notifications[] = $this->format_notification(
                $post,
                'vacacion_aprobada',
                'Vacaciones aprobadas',
                "Tus vacaciones del {$inicio} al {$fin} han sido aprobadas"
            );
        }
        
        return $notifications;
    }
    
    private function get_vacaciones_rechazadas($user_id) {
        $posts = $this->query_cpt_by_estado('che_vacacion', 'rechazada', $user_id);
        $notifications = [];
        
        foreach ($posts as $post) {
            $inicio = get_post_meta($post->ID, 'fecha_inicio', true);
            $fin = get_post_meta($post->ID, 'fecha_fin', true);
            
            $notifications[] = $this->format_notification(
                $post,
                'vacacion_rechazada',
                'Vacaciones rechazadas',
                "Tus vacaciones del {$inicio} al {$fin} han sido rechazadas"
            );
        }
        
        return $notifications;
    }
    
    // ==========================================
    // PARTES
    // ==========================================
    
    private function get_partes_pendientes() {
        $posts = $this->query_partes_by_validacion('pendiente');
        $notifications = [];
        
        foreach ($posts as $post) {
            $user = get_user_by('ID', $post->post_author);
            $name = $user ? $user->display_name : 'Usuario';
            $fecha = get_post_meta($post->ID, 'fecha', true);
            $fecha_str = $fecha ? " ({$fecha})" : '';
            
            $notifications[] = $this->format_notification(
                $post,
                'parte_pendiente',
                'Parte de trabajo pendiente',
                "{$name} tiene un parte pendiente{$fecha_str}"
            );
        }
        
        return $notifications;
    }
    
    private function get_partes_validados($user_id) {
        $posts = $this->query_partes_by_validacion('validado', $user_id);
        $notifications = [];
        
        foreach ($posts as $post) {
            $fecha = get_post_meta($post->ID, 'fecha', true);
            $fecha_str = $fecha ? " del {$fecha}" : '';
            
            $notifications[] = $this->format_notification(
                $post,
                'parte_validado',
                'Parte validado',
                "Tu parte{$fecha_str} ha sido validado"
            );
        }
        
        return $notifications;
    }
    
    private function get_partes_rechazados($user_id) {
        $posts = $this->query_partes_by_validacion('rechazado', $user_id);
        $notifications = [];
        
        foreach ($posts as $post) {
            $fecha = get_post_meta($post->ID, 'fecha', true);
            $motivo = get_post_meta($post->ID, 'motivo_rechazo', true);
            $fecha_str = $fecha ? " del {$fecha}" : '';
            $msg = "Tu parte{$fecha_str} ha sido rechazado";
            if ($motivo) $msg .= ". Motivo: {$motivo}";
            
            $notifications[] = $this->format_notification(
                $post,
                'parte_rechazado',
                'Parte rechazado',
                $msg
            );
        }
        
        return $notifications;
    }
    
    // ==========================================
    // SUPER ADMIN
    // ==========================================
    
    private function get_super_admin_notifications($current_user_id) {
        $stored = get_option('che_super_admin_notifications', []);
        $notifications = [];
        
        foreach ($stored as $notif) {
            // Convertir fecha a formato ISO 8601 con zona horaria
            $date = new \DateTime($notif['date'], wp_timezone());
            
            $notifications[] = [
                'id' => 'super_admin_created_' . $notif['new_user_id'] . '_' . strtotime($notif['date']),
                'type' => 'super_admin_created',
                'title' => 'Nuevo Super Administrador',
                'message' => $notif['message'],
                'date' => $date->format('c'), // ISO 8601 con zona horaria
                'user_id' => $notif['new_user_id']
            ];
        }
        
        return $notifications;
    }
    
    // ==========================================
    // BIENVENIDA
    // ==========================================
    
    private function get_welcome_notification($user_id) {
        $welcome = get_user_meta($user_id, 'che_welcome_notification', true);
        
        if (!$welcome || empty($welcome)) {
            return null;
        }
        
        // Convertir fecha a formato ISO 8601
        $date = new \DateTime($welcome['date'], wp_timezone());
        
        return [
            'id' => $welcome['id'],
            'type' => 'welcome',
            'title' => $welcome['title'],
            'message' => $welcome['message'],
            'date' => $date->format('c'),
            'user_id' => $user_id
        ];
    }
}
