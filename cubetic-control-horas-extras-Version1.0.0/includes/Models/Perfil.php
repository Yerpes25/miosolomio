<?php
namespace ControlHoras\Models;

if ( ! defined( 'ABSPATH' ) ) exit;

class Perfil {

    /**
     * Actualiza la contraseña del usuario comprobando la actual.
     */
    public static function update_password( $user_id, $current_password, $new_password ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [ 'success' => false, 'error' => 'Usuario no encontrado.' ];
        }

        if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
            return [ 'success' => false, 'error' => 'La contraseña actual no es correcta.' ];
        }

        if ( strlen( $new_password ) < 8 ) {
            return [ 'success' => false, 'error' => 'La nueva contraseña debe tener al menos 8 caracteres.' ];
        }

        // Actualizar contraseña
        wp_set_password( $new_password, $user_id );

        return [ 'success' => true ];
    }

    /**
     * Actualiza el avatar del usuario.
     * Recibe un attachment ID ya subido a la librería.
     */
    public static function update_avatar( $user_id, $attachment_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [ 'success' => false, 'error' => 'Usuario no encontrado.' ];
        }

        $attachment_id = (int) $attachment_id;
        if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
            return [ 'success' => false, 'error' => 'Archivo no válido.' ];
        }

        // Guardar como meta (puedes usar el key que prefieras)
        update_user_meta( $user_id, 'che_user_avatar_id', $attachment_id );

        return [ 'success' => true, 'attachment_id' => $attachment_id ];
    }
}
