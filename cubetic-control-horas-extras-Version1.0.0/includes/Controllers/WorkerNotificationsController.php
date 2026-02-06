<?php
namespace ControlHoras\Includes\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WorkerNotificationsController {

    private static $instance = null;

    /**
     * Singleton
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado: registra los hooks
     */
    private function __construct() {
        $this->register_ajax_hooks();
    }

    /**
     * Registra los AJAX para la sección de notificaciones del trabajador
     */
    public function register_ajax_hooks() {
        add_action( 'wp_ajax_che_worker_get_notifications', [ $this, 'ajax_get_notifications' ] );
        add_action( 'wp_ajax_che_worker_mark_read',        [ $this, 'ajax_mark_read' ] );
        // Si algún día permites público no logueado, añadir también wp_ajax_nopriv_...
    }

    /**
     * Devuelve las notificaciones del usuario actual
     */
    public function ajax_get_notifications() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'No autorizado' ] );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'che_notifications';
        $user_id = get_current_user_id();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 500",
                $user_id
            )
        );

        $notifications = [];
        foreach ( $rows as $row ) {
            $notifications[] = [
                'id'       => 'db_' . (int) $row->id,
                'title'    => (string) $row->title,
                'message'  => (string) $row->message,
                'date'     => (string) $row->created_at,
                'time_ago' => $this->format_time_ago( $row->created_at ),
                'type'     => (string) $row->type,
                'read'     => (bool) $row->is_read,
            ];
        }

        wp_send_json_success( $notifications );
    }

    /**
     * Marca como leídas una o varias notificaciones
     */
    public function ajax_mark_read() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'No autorizado' ] );
        }

        if ( empty( $_POST['ids'] ) || ! is_array( $_POST['ids'] ) ) {
            wp_send_json_error( [ 'message' => 'IDs no válidos' ] );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'che_notifications';
        $user_id = get_current_user_id();

        foreach ( $_POST['ids'] as $raw_id ) {
            $raw_id = sanitize_text_field( wp_unslash( $raw_id ) );

            if ( strpos( $raw_id, 'db_' ) !== 0 ) {
                continue;
            }

            $id = (int) substr( $raw_id, 3 );
            if ( $id <= 0 ) {
                continue;
            }

            $wpdb->update(
                $table,
                [ 'is_read' => 1 ],
                [ 'id' => $id, 'user_id' => $user_id ],
                [ '%d' ],
                [ '%d', '%d' ]
            );
        }

        wp_send_json_success();
    }

    /**
     * Formatea "hace X tiempo"
     */
    private function format_time_ago( $date_string ) {
        if ( ! $date_string ) {
            return '';
        }

        $timestamp = strtotime( $date_string );
        if ( ! $timestamp ) {
            return '';
        }

        $now  = time();
        $diff = $now - $timestamp;

        if ( $diff < 60 ) {
            return 'hace un momento';
        }
        if ( $diff < 3600 ) {
            return 'hace ' . floor( $diff / 60 ) . ' min';
        }
        if ( $diff < 86400 ) {
            return 'hace ' . floor( $diff / 3600 ) . ' h';
        }
        if ( $diff < 604800 ) {
            return 'hace ' . floor( $diff / 86400 ) . ' días';
        }

        return date_i18n( 'd M', $timestamp );
    }
}