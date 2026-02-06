<?php
namespace ControlHoras\Includes\Api;

use WP_REST_Request;
use WP_REST_Response;
use ControlHoras\Models\Perfil;

if ( ! defined( 'ABSPATH' ) ) exit;

class EditarPerfilController {

    const NAMESPACE = 'che/v1';

    public function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/perfil/password',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_password' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/perfil/avatar',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_avatar' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );

        // Ruta para editar perfil completo (nombre, email, etc)
        register_rest_route(
            self::NAMESPACE,
            '/editar-perfil',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'update_perfil' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );
    }

    /**
     * Actualizar contraseña del usuario logueado.
     * body JSON: { current_password, new_password }
     */
    public function update_password( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'No autenticado.' ],
                401
            );
        }

        $params           = $request->get_json_params();
        $current_password = isset( $params['current_password'] ) ? (string) $params['current_password'] : '';
        $new_password     = isset( $params['new_password'] ) ? (string) $params['new_password'] : '';

        if ( $current_password === '' || $new_password === '' ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'Faltan campos de contraseña.' ],
                400
            );
        }

        $result = Perfil::update_password( $user_id, $current_password, $new_password );

        if ( ! $result['success'] ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => $result['error'] ],
                400
            );
        }

        return new WP_REST_Response(
            [ 'success' => true ],
            200
        );
    }

    /**
     * Actualizar avatar del usuario logueado.
     * body multipart/form-data: campo 'avatar' (file)
     */
    public function update_avatar( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'No autenticado.' ],
                401
            );
        }

        // Fichero subido en el campo 'avatar'
        $files = $request->get_file_params();
        if ( empty( $files['avatar'] ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'No se ha enviado ningún archivo.' ],
                400
            );
        }

        $file = $files['avatar'];

        // Subir a la librería de medios
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = [
            'test_form' => false,
        ];

        $file_result = wp_handle_upload( $file, $overrides );

        if ( isset( $file_result['error'] ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => $file_result['error'] ],
                400
            );
        }

        // Crear attachment
        $attachment = [
            'post_mime_type' => $file_result['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment( $attachment, $file_result['file'] );

        if ( ! $attach_id || is_wp_error( $attach_id ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'No se pudo crear el adjunto.' ],
                500
            );
        }

        // Generar metadatos de la imagen
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_result['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        // Guardar en el modelo
        $result = Perfil::update_avatar( $user_id, $attach_id );

        if ( ! $result['success'] ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => $result['error'] ],
                400
            );
        }

        return new WP_REST_Response(
            [
                'success'       => true,
                'attachment_id' => $attach_id,
                'url'           => wp_get_attachment_url( $attach_id ),
            ],
            200
        );
    }

    /**
     * Actualizar perfil del usuario (nombre, email).
     * body JSON: { nombre, email, current_password?, new_password? }
     */
    public function update_perfil( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'No autenticado.' ],
                401
            );
        }

        $params = $request->get_json_params();
        $nombre = isset( $params['nombre'] ) ? sanitize_text_field( $params['nombre'] ) : '';
        $email  = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';

        // Validaciones básicas
        if ( empty( $nombre ) || empty( $email ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'Nombre y email son obligatorios.' ],
                400
            );
        }

        if ( ! is_email( $email ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'Email no válido.' ],
                400
            );
        }

        // Verificar si el email ya existe (y no es del usuario actual)
        $existing_user = get_user_by( 'email', $email );
        if ( $existing_user && $existing_user->ID !== $user_id ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => 'Este email ya está en uso.' ],
                400
            );
        }

        // Actualizar datos del usuario
        $user_data = [
            'ID'           => $user_id,
            'display_name' => $nombre,
            'user_email'   => $email,
        ];

        $updated = wp_update_user( $user_data );

        if ( is_wp_error( $updated ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'error' => $updated->get_error_message() ],
                400
            );
        }

        // Si hay contraseña, actualizarla
        $current_pass = isset( $params['current_password'] ) ? (string) $params['current_password'] : '';
        $new_pass     = isset( $params['new_password'] ) ? (string) $params['new_password'] : '';

        if ( $current_pass && $new_pass ) {
            $pass_result = Perfil::update_password( $user_id, $current_pass, $new_pass );
            if ( ! $pass_result['success'] ) {
                return new WP_REST_Response(
                    [ 'success' => false, 'error' => $pass_result['error'] ],
                    400
                );
            }
        }

        return new WP_REST_Response(
            [ 'success' => true, 'message' => 'Perfil actualizado correctamente.' ],
            200
        );
    }
}
