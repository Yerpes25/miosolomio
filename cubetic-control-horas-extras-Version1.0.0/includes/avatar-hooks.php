<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'get_avatar_url', function( $url, $id_or_email, $args ) {
    $user = false;

    if ( is_numeric( $id_or_email ) ) {
        $user = get_user_by( 'id', (int) $id_or_email );
    } elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
        $user = get_user_by( 'id', (int) $id_or_email->user_id );
    } elseif ( is_string( $id_or_email ) ) {
        $user = get_user_by( 'email', $id_or_email );
    }

    if ( ! $user ) {
        return $url;
    }

    $avatar_id = get_user_meta( $user->ID, 'che_user_avatar_id', true );
    if ( ! $avatar_id ) {
        return $url;
    }

    $size   = isset( $args['size'] ) ? (int) $args['size'] : 96;
    $custom = wp_get_attachment_image_url( $avatar_id, [ $size, $size ] );

    return $custom ?: $url;
}, 10, 3 );
