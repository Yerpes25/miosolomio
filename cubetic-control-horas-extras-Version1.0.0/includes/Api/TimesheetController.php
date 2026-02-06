<?php
namespace ControlHoras\includes\Api;

use WP_REST_Request;
use WP_REST_Response;
use ControlHoras\Models\Parte;
use ControlHoras\Includes\Services\OneSignalService;

// Cargar el servicio de notificaciones
require_once CHE_PATH . 'includes/Services/OneSignalService.php';

class TimesheetController
{

    const NAMESPACE = 'che/v1';

    public function register_routes()
    {
        /**
         * RUTA para iniciar un parte (worker)
         */
        register_rest_route(
            self::NAMESPACE ,
            '/parte/iniciar',
            [
                'methods' => 'POST',
                'callback' => [$this, 'start_parte'],
                'permission_callback' => '__return_true', // luego puedes endurecerlo
            ]
        );

        /**
         * RUTA para finalizar un parte (worker)
         */
        register_rest_route(
            self::NAMESPACE ,
            '/parte/finalizar',
            [
                'methods' => 'POST',
                'callback' => [$this, 'finish_parte'],
                'permission_callback' => '__return_true',
            ]
        );

        /**
         * RUTA para crear un parte manual (worker)
         */
        register_rest_route(
            self::NAMESPACE,
            '/parte/crear-manual',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'crear_parte_manual'],
                'permission_callback' => '__return_true',
            ]
        );


        /*
        Ruta para validar parte
        */
        register_rest_route(
            self::NAMESPACE ,
            '/parte/validar',
            [
                'methods' => 'POST',
                'callback' => [$this, 'validar_parte'],
                'permission_callback' => '__return_true',
            ]
        );

        /*
        Ruta para rechazar parte
        */
        register_rest_route(
            self::NAMESPACE ,
            '/parte/rechazar',
            [
                'methods' => 'POST',
                'callback' => [$this, 'rechazar_parte'],
                'permission_callback' => '__return_true',
            ]
        );


        /*
        Guardar archivos adjuntos a un parte
        */
        register_rest_route(
            self::NAMESPACE ,
            '/parte/guardar-adjuntos',
            [
                'methods' => 'POST',
                'callback' => [$this, 'guardar_adjuntos_parte'],
                'permission_callback' => '__return_true', // luego lo puedes endurecer
            ]
        );



        /** 
         * Ruta: obtener mas info de los partes
         */
        register_rest_route(
            self::NAMESPACE ,
            '/admin/parte-detalle',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_parte_detalle'],
                'permission_callback' => '__return_true',
            ]
        );



        /**
         * obtener partes por usuario
         */
        register_rest_route(
            self::NAMESPACE ,
            '/admin/partes-por-usuario',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_partes_por_usuario'],
                'permission_callback' => '__return_true',
            ]
        );

    }

    /**
     * RUTA para iniciar un parte (worker)
     */
    public function start_parte(WP_REST_Request $request)
    {
        $params = $request->get_json_params();


        $post_id = Parte::create_parte([
            'trabajador_id' => isset($params['trabajador_id']) ? intval($params['trabajador_id']) : 0,
            'proyecto' => $params['proyecto'] ?? '',
            'hora_inicio' => $params['hora_inicio'] ?? '',
            'titulo' => $params['titulo'] ?? 'Parte desde panel',
            'geo_inicio' => $params['geo_inicio'] ?? null,
        ]);

        if (is_wp_error($post_id) || !$post_id) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'No se pudo crear el parte'],
                500
            );
        }

        return new WP_REST_Response(
            ['success' => true, 'post_id' => $post_id],
            201
        );
    }

    /**
     * RUTA para finalizar un parte (worker)
     */
    public function finish_parte(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $parte_id = isset($params['parte_id']) ? intval($params['parte_id']) : 0;
        $geo_fin = $params['geo_fin'] ?? null;
        $elapsed = isset($params['elapsed_time']) ? intval($params['elapsed_time']) : 0;

        if (!$parte_id) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'ID de parte no válido'],
                400
            );
        }
        $ok = Parte::finalizar_parte( $parte_id, $geo_fin, $elapsed);


        if (!$ok) {
            return new WP_REST_Response(
                ['success' => false, 'error' => 'No se pudo finalizar el parte'],
                500
            );
        }

        // Enviar notificación push a los admins
        $post = get_post($parte_id);
        if ($post) {
            $worker_id = $post->post_author;
            $fecha = get_post_meta($parte_id, 'hora_inicio', true);
            if ($fecha) {
                $fecha = date('Y-m-d', strtotime($fecha));
            } else {
                $fecha = $post->post_date;
            }
            OneSignalService::get_instance()->notify_new_parte($parte_id, $worker_id, $fecha);
        }

        return new WP_REST_Response(
            ['success' => true, 'parte_id' => $parte_id],
            200
        );
    }

    /**
     * Endpoint: crear parte manual.
     * Delegamos la lógica en Parte::crear_parte_manual().
     */
    public function crear_parte_manual( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        $data = [
            'trabajador_id' => isset( $params['trabajador_id'] ) ? (int) $params['trabajador_id'] : 0,
            'proyecto'      => $params['proyecto'] ?? '',
            'hora_inicio'   => $params['hora_inicio'] ?? '',
            'titulo'        => $params['titulo'] ?? 'Parte manual desde panel',
            'geo_inicio'    => $params['geo_inicio'] ?? null,
            'geo_fin'       => $params['geo_fin'] ?? null,
        ];

        $post_id = Parte::crear_parte_manual( $data );

        if ( is_wp_error( $post_id ) ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error'   => $post_id->get_error_message(),
                ],
                400
            );
        }

        if ( ! $post_id ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'error'   => 'No se pudo crear el parte manual.',
                ],
                500
            );
        }

        return new WP_REST_Response(
            [
                'success'  => true,
                'parte_id' => $post_id,
            ],
            201
        );
    }


    /*
    Ruta para almacenar archivos adjuntos a un parte
    */
    public function guardar_adjuntos_parte(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $parte_id = isset($params['parte_id']) ? (int) $params['parte_id'] : 0;
        $attachments = isset($params['attachments']) && is_array($params['attachments'])
            ? array_map('intval', $params['attachments'])
            : [];

        if (!$parte_id || empty($attachments)) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Datos incompletos.'],
                400
            );
        }

        // Recuperar adjuntos existentes (si los hay) y fusionar
        $existing = get_post_meta($parte_id, 'parte_adjuntos', true);
        if (!is_array($existing)) {
            $existing = [];
        }

        $new_value = array_values(array_unique(array_merge($existing, $attachments)));

        update_post_meta($parte_id, 'parte_adjuntos', $new_value);

        return new \WP_REST_Response(
            ['success' => true, 'attachments' => $new_value],
            200
        );
    }

    /**
     * Recogemos los partes de un usuario en concreto
     */
    public function get_partes_por_usuario(\WP_REST_Request $request)
    {
        $user_id = (int) $request->get_param('user_id');

        if (!$user_id) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Usuario requerido.'],
                400
            );
        }

        $query = new \WP_Query([
            'post_type' => 'parte_horario',
            'post_status' => 'publish',
            'author' => $user_id,
            'meta_key' => 'estado',
            'meta_value' => ['finalizado', 'pendiente'],
            'meta_compare' => 'IN',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        /**
         * datods del parte
         */
        $rows = [];
        foreach ($query->posts as $post) {
            $rows[] = [
                'ID' => $post->ID,
                'post_title' => $post->post_title,
                'post_date' => $post->post_date,
                'worker_name' => get_the_author_meta('display_name', $user_id),
                'hora_inicio' => get_post_meta($post->ID, 'hora_inicio', true),
                'hora_fin' => get_post_meta($post->ID, 'hora_finalizacion', true),
                'tiempo_total' => get_post_meta($post->ID, 'elapsed_time', true),
                'estado' => get_post_meta($post->ID, 'estado', true),
                'estado_validacion' => get_post_meta($post->ID, 'estado_validacion', true),
            ];
        }

        return new \WP_REST_Response(
            ['success' => true, 'partes' => $rows],
            200
        );
    }

    /*
    Ruta para crear mas detalles del parte
    */

    public function get_parte_detalle(\WP_REST_Request $request)
    {
        $parte_id = (int) $request->get_param('parte_id');
        if (!$parte_id) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'ID de parte requerido.'],
                400
            );
        }

        $post = get_post($parte_id);
        if (!$post || $post->post_type !== 'parte_horario') {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Parte no encontrado.'],
                404
            );
        }

        $trabajador_id = (int) get_post_meta($parte_id, 'trabajador_id', true);
        $worker_name = $trabajador_id ? get_the_author_meta('display_name', $trabajador_id) : '';
        $geo_inicio_lat = get_post_meta( $parte_id, 'geo_inicio_lat', true );
        $geo_inicio_lng = get_post_meta( $parte_id, 'geo_inicio_lng', true );
        $geo_fin_lat    = get_post_meta( $parte_id, 'geo_fin_lat', true );
        $geo_fin_lng    = get_post_meta( $parte_id, 'geo_fin_lng', true );

        $parte = [
            'ID' => $post->ID,
            'post_date' => $post->post_date,
            'worker_name' => $worker_name,
            'proyecto' => get_post_meta($parte_id, 'proyecto', true),
            'hora_inicio' => get_post_meta($parte_id, 'hora_inicio', true),
            'hora_fin' => get_post_meta($parte_id, 'hora_finalizacion', true),
            'tiempo_total' => get_post_meta($parte_id, 'elapsed_time', true),
            'estado' => get_post_meta($parte_id, 'estado', true),
            'estado_validacion' => get_post_meta($parte_id, 'estado_validacion', true),
            'geo_inicio_lat'    => $geo_inicio_lat,
            'geo_inicio_lng'    => $geo_inicio_lng,
            'geo_fin_lat'       => $geo_fin_lat,
            'geo_fin_lng'       => $geo_fin_lng,

        ];

        // Adjuntos vinculados
        $adj_ids = get_post_meta($parte_id, 'parte_adjuntos', true);
        $adjuntos = [];
        if (is_array($adj_ids)) {
            foreach ($adj_ids as $att_id) {
                $url = wp_get_attachment_url($att_id);
                if (!$url) {
                    continue;
                }
                $mime = get_post_mime_type($att_id);
                $is_image = strpos((string) $mime, 'image/') === 0;

                $adjuntos[] = [
                    'id' => $att_id,
                    'url' => $url,
                    'title' => get_the_title($att_id),
                    'filename' => basename(get_attached_file($att_id)),
                    'mime_type' => $mime,
                    'is_image' => $is_image,
                ];

            }
        }
        $parte['adjuntos'] = $adjuntos;

        return new \WP_REST_Response(
            ['success' => true, 'parte' => $parte],
            200
        );
    }


    public function validar_parte(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $parte_id = isset($params['parte_id']) ? (int) $params['parte_id'] : 0;

        if (!$parte_id) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'ID de parte requerido.'],
                400
            );
        }

        $post = get_post($parte_id);
        if (!$post || $post->post_type !== 'parte_horario') {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Parte no encontrado.'],
                404
            );
        }

        update_post_meta($parte_id, 'estado_validacion', 'validado');
        //update_post_meta($parte_id, 'estado', 'aprobado');
        
        // Guardar datos del admin que validó
        $current_user = wp_get_current_user();
        update_post_meta($parte_id, 'validado_por_id', $current_user->ID);
        update_post_meta($parte_id, 'validado_por_nombre', $current_user->display_name);
        update_post_meta($parte_id, 'validacion_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        update_post_meta($parte_id, 'validacion_fecha', current_time('mysql'));

        // Enviar notificación push al trabajador
        $trabajador_id = get_post_meta($parte_id, 'trabajador_id', true);
        if ($trabajador_id) {
            $fecha = get_post_meta($parte_id, 'hora_inicio', true);
            if (!$fecha) {
                $fecha = $post->post_date;
            }
            OneSignalService::get_instance()->notify_parte_validated($parte_id, $trabajador_id, $fecha);
        }

        return new \WP_REST_Response(
            [
                'success' => true,
                'parte_id' => $parte_id,
                'estado_validacion' => 'validado',
            ],
            200
        );
    }

    public function rechazar_parte(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $parte_id = isset($params['parte_id']) ? (int) $params['parte_id'] : 0;
        $comentario = isset($params['comentario']) ? sanitize_textarea_field($params['comentario']) : '';

        if (!$parte_id) {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'ID de parte requerido.'],
                400
            );
        }

        $post = get_post($parte_id);
        if (!$post || $post->post_type !== 'parte_horario') {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Parte no encontrado.'],
                404
            );
        }

        update_post_meta($parte_id, 'estado_validacion', 'rechazado');
        
        // Guardar el motivo del rechazo si se proporcionó
        if ($comentario) {
            update_post_meta($parte_id, 'motivo_rechazo', $comentario);
        }
        
        // Guardar datos del admin que rechazó
        $current_user = wp_get_current_user();
        update_post_meta($parte_id, 'validado_por_id', $current_user->ID);
        update_post_meta($parte_id, 'validado_por_nombre', $current_user->display_name);
        update_post_meta($parte_id, 'validacion_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        update_post_meta($parte_id, 'validacion_fecha', current_time('mysql'));

        // Enviar notificación push al trabajador con el motivo
        $trabajador_id = get_post_meta($parte_id, 'trabajador_id', true);
        if ($trabajador_id) {
            $fecha = get_post_meta($parte_id, 'hora_inicio', true);
            if (!$fecha) {
                $fecha = $post->post_date;
            }
            OneSignalService::get_instance()->notify_parte_rejected($parte_id, $trabajador_id, $fecha, $comentario);
        }

        return new \WP_REST_Response(
            [
                'success' => true,
                'parte_id' => $parte_id,
                'estado_validacion' => 'rechazado',
            ],
            200
        );
    }

}