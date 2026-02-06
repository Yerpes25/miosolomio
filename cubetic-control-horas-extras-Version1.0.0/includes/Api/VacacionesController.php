<?php
namespace ControlHoras\Includes\Api;

use WP_REST_Request;
use WP_REST_Response;
use ControlHoras\Includes\Services\OneSignalService;

if ( ! defined( 'ABSPATH' ) ) exit;

// Cargar el servicio de OneSignal
require_once CHE_PATH . 'includes/Services/OneSignalService.php';

class VacacionesController {

    const NAMESPACE = 'che/v1';

    public function register_routes() {

        // Trabajador: crear solicitud de vacaciones
        register_rest_route(
            self::NAMESPACE,
            '/trabajador/vacaciones/solicitar',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'solicitar_vacaciones' ],
                'permission_callback' => '__return_true',/* function () {
                    return is_user_logged_in();
                }, */
            ]
        );

        // Trabajador: listar sus solicitudes
        register_rest_route(
            self::NAMESPACE,
            '/trabajador/vacaciones',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_vacaciones_trabajador' ],
                'permission_callback' => '__return_true',/* function () {
                    return is_user_logged_in();
                }, */
            ]
        );

        // Admin: listar solicitudes (con filtro por sede)
        register_rest_route(
            self::NAMESPACE,
            '/admin/vacaciones',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_vacaciones_admin' ],
                'permission_callback' => '__return_true',/* function () {
                    return current_user_can( 'read' );
                }, */
            ]
        );

        // Admin: aprobar
        register_rest_route(
            self::NAMESPACE,
            '/admin/vacaciones/aprobar',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'aprobar_vacacion' ],
                'permission_callback' => '__return_true',/* function () {
                    return current_user_can( 'read' );
                }, */
            ]
        );

        // Admin: rechazar
        register_rest_route(
            self::NAMESPACE,
            '/admin/vacaciones/rechazar',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'rechazar_vacacion' ],
                'permission_callback' => '__return_true',/* function () {
                    return current_user_can( 'read' );
                }, */
            ]
        );
    }

    /**
     * Trabajador: crear solicitud
     * body: { fecha_inicio: 'YYYY-MM-DD', fecha_fin: 'YYYY-MM-DD' }
     */
    public function solicitar_vacaciones( WP_REST_Request $request ) {
        $user_id      = get_current_user_id();
        $fecha_inicio = sanitize_text_field( $request->get_param( 'fecha_inicio' ) );
        $fecha_fin    = sanitize_text_field( $request->get_param( 'fecha_fin' ) );

        if ( ! $user_id || ! $fecha_inicio || ! $fecha_fin ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'Datos incompletos.' ],
                400
            );
        }

        // Calcular días totales simples (sin excluir fines de semana)
        $dias_totales = max( 1, ( strtotime( $fecha_fin ) - strtotime( $fecha_inicio ) ) / DAY_IN_SECONDS + 1 );

        $user   = get_userdata( $user_id );
        $nombre = $user && $user->display_name ? $user->display_name : 'Trabajador';

        $post_title = sprintf(
            'Vacaciones %s (%s a %s)',
            $nombre,
            $fecha_inicio,
            $fecha_fin
        );

        $post_id = wp_insert_post( [
            'post_type'   => 'che_vacacion',
            'post_title'  => $post_title,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ] );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'No se pudo crear la solicitud.' ],
                500
            );
        }

        update_post_meta( $post_id, 'trabajador_id', $user_id );
        update_post_meta( $post_id, 'fecha_inicio', $fecha_inicio );
        update_post_meta( $post_id, 'fecha_fin', $fecha_fin );
        update_post_meta( $post_id, 'dias_totales', (int) $dias_totales );
        update_post_meta( $post_id, 'estado', 'pendiente' );
        update_post_meta( $post_id, 'solicitud_ip', $_SERVER['REMOTE_ADDR'] ?? '' );
        update_post_meta( $post_id, 'solicitado_por_id', $user_id );

        // Guardar también la sede para poder filtrar después
        $sede_id = get_user_meta( $user_id, 'sede_trabajo', true );
        if ( $sede_id ) {
            update_post_meta( $post_id, 'sede_id', (int) $sede_id );
        }

        // Enviar notificación push a los administradores
        $onesignal = OneSignalService::get_instance();
        $onesignal->notify_vacation_request( $post_id, $user_id, $fecha_inicio, $fecha_fin );

        return new WP_REST_Response(
            [ 'success' => true, 'vacacion_id' => $post_id ],
            201
        );
    }

    /** Trabajador: listar sus solicitudes */
    public function get_vacaciones_trabajador( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'Usuario no autenticado.' ],
                401
            );
        }

        $query = new \WP_Query( [
            'post_type'      => 'che_vacacion',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $rows = [];

        foreach ( $query->posts as $post ) {
            $rows[] = [
                'ID'           => $post->ID,
                'fecha_inicio' => get_post_meta( $post->ID, 'fecha_inicio', true ),
                'fecha_fin'    => get_post_meta( $post->ID, 'fecha_fin', true ),
                'dias_totales' => (int) get_post_meta( $post->ID, 'dias_totales', true ),
                'estado'       => get_post_meta( $post->ID, 'estado', true ),
            ];
        }

        return new WP_REST_RESPONSE(
            [ 'success' => true, 'vacaciones' => $rows ],
            200
        );
    }

    /** Admin: listar solicitudes, opcionalmente filtradas por sede_id */
    public function get_vacaciones_admin( WP_REST_Request $request ) {
        $sede_id = (int) $request->get_param( 'sede_id' );
        $estado  = $request->get_param( 'estado' ); // puede venir vacío

        $meta_query = [ 'relation' => 'AND' ];

        // Solo filtramos por estado si viene en la petición
        if ( $estado ) {
            $meta_query[] = [
                'key'   => 'estado',
                'value' => $estado,
            ];
        }

        // Filtrar por sede si se ha indicado
        if ( $sede_id ) {
            $meta_query[] = [
                'key'   => 'sede_id',
                'value' => $sede_id,
            ];
        }

        // Si no hay filtros, no pases meta_query
        $args = [
            'post_type'      => 'che_vacacion',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        $query = new \WP_Query( $args );


        $query = new \WP_Query( [
            'post_type'      => 'che_vacacion',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $meta_query,
        ] );

        $rows = [];

        foreach ( $query->posts as $post ) {
            $trabajador_id = (int) get_post_meta( $post->ID, 'trabajador_id', true );
            $worker_name   = $trabajador_id ? get_the_author_meta( 'display_name', $trabajador_id ) : '';

            $rows[] = [
                'ID'           => $post->ID,
                'worker_id'    => $trabajador_id,
                'worker_name'  => $worker_name,
                'fecha_inicio' => get_post_meta( $post->ID, 'fecha_inicio', true ),
                'fecha_fin'    => get_post_meta( $post->ID, 'fecha_fin', true ),
                'dias_totales' => (int) get_post_meta( $post->ID, 'dias_totales', true ),
                'estado'       => get_post_meta( $post->ID, 'estado', true ),
                'sede_id'      => (int) get_post_meta( $post->ID, 'sede_id', true ),
            ];
        }

        return new WP_REST_Response(
            [ 'success' => true, 'vacaciones' => $rows ],
            200
        );
    }

    /** Admin: aprobar */
    public function aprobar_vacacion( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'vacacion_id' );
        if ( ! $id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'ID requerido.' ],
                400
            );
        }

        update_post_meta( $id, 'estado', 'aprobado' );
        
        // Guardar datos del admin que aprobó
        $current_user = wp_get_current_user();
        update_post_meta( $id, 'aprobado_por_id', $current_user->ID );
        update_post_meta( $id, 'aprobado_por_nombre', $current_user->display_name );
        update_post_meta( $id, 'aprobacion_ip', $_SERVER['REMOTE_ADDR'] ?? '' );
        update_post_meta( $id, 'aprobacion_fecha', current_time('mysql') );

        // Obtener datos para la notificación
        $worker_id    = (int) get_post_meta( $id, 'trabajador_id', true );
        $fecha_inicio = get_post_meta( $id, 'fecha_inicio', true );
        $fecha_fin    = get_post_meta( $id, 'fecha_fin', true );
        
        // Si trabajador_id no está, usar el post_author como fallback
        if ( ! $worker_id ) {
            $post = get_post( $id );
            $worker_id = $post ? (int) $post->post_author : 0;
            error_log( '[CHE Vacaciones] trabajador_id no encontrado, usando post_author: ' . $worker_id );
        }
        
        // Validar que el worker_id existe y tiene rol worker
        if ( $worker_id ) {
            $worker = get_userdata( $worker_id );
            if ( ! $worker ) {
                error_log( '[CHE Vacaciones] ERROR: Worker ID ' . $worker_id . ' no existe' );
            } else {
                $worker_roles = $worker->roles;
                error_log( '[CHE Vacaciones] Notificación de aprobación - Worker ID: ' . $worker_id . ', nombre: ' . $worker->display_name . ', roles: ' . implode( ', ', $worker_roles ) );
                
                // Enviar notificación push al trabajador
                $onesignal = OneSignalService::get_instance();
                $onesignal->notify_vacation_approved( $id, $worker_id, $fecha_inicio, $fecha_fin );
            }
        } else {
            error_log( '[CHE Vacaciones] ERROR: No se pudo determinar worker_id para vacación ' . $id );
        }

        return new WP_REST_Response(
            [ 'success' => true, 'vacacion_id' => $id, 'estado' => 'aprobado' ],
            200
        );
    }

    /** Admin: rechazar */
    public function rechazar_vacacion( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'vacacion_id' );
        if ( ! $id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'ID requerido.' ],
                400
            );
        }

        $motivo = sanitize_text_field( $request->get_param( 'motivo' ) );
        update_post_meta( $id, 'estado', 'rechazado' );
        
        // Guardar datos del admin que rechazó
        $current_user = wp_get_current_user();
        update_post_meta( $id, 'aprobado_por_id', $current_user->ID );
        update_post_meta( $id, 'aprobado_por_nombre', $current_user->display_name );
        update_post_meta( $id, 'aprobacion_ip', $_SERVER['REMOTE_ADDR'] ?? '' );
        update_post_meta( $id, 'aprobacion_fecha', current_time('mysql') );
        
        if ( $motivo ) {
            update_post_meta( $id, 'motivo_rechazo', $motivo );
        }

        // Obtener datos para la notificación
        $worker_id    = (int) get_post_meta( $id, 'trabajador_id', true );
        $fecha_inicio = get_post_meta( $id, 'fecha_inicio', true );
        $fecha_fin    = get_post_meta( $id, 'fecha_fin', true );
        
        // Si trabajador_id no está, usar el post_author como fallback
        if ( ! $worker_id ) {
            $post = get_post( $id );
            $worker_id = $post ? (int) $post->post_author : 0;
            error_log( '[CHE Vacaciones] trabajador_id no encontrado, usando post_author: ' . $worker_id );
        }
        
        // Validar que el worker_id existe y tiene rol worker
        if ( $worker_id ) {
            $worker = get_userdata( $worker_id );
            if ( ! $worker ) {
                error_log( '[CHE Vacaciones] ERROR: Worker ID ' . $worker_id . ' no existe' );
            } else {
                $worker_roles = $worker->roles;
                error_log( '[CHE Vacaciones] Notificación de rechazo - Worker ID: ' . $worker_id . ', nombre: ' . $worker->display_name . ', roles: ' . implode( ', ', $worker_roles ) );
                
                // Enviar notificación push al trabajador
                $onesignal = OneSignalService::get_instance();
                error_log( '[CHE Vacaciones] Enviando notificación de rechazo con motivo: ' . $motivo );
                $onesignal->notify_vacation_rejected( $id, $worker_id, $fecha_inicio, $fecha_fin, $motivo );
            }
        } else {
            error_log( '[CHE Vacaciones] ERROR: No se pudo determinar worker_id para vacación ' . $id );
        }

        return new WP_REST_Response(
            [ 'success' => true, 'vacacion_id' => $id, 'estado' => 'rechazado' ],
            200
        );
    }
}
