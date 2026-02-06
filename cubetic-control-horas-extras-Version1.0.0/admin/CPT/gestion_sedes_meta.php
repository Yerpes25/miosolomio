<?php
namespace ControlHoras\Admin\CPT;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SedesMeta {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_che_sede', [ __CLASS__, 'save_meta_boxes' ] );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'che_sede_details',
            __( 'Datos de la sede', 'gestion-he' ),
            [ __CLASS__, 'render_meta_box' ],
            'che_sede',
            'normal',
            'high'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'che_sede_details', 'che_sede_nonce' );

        $fields = [
            'che_sede_direccion' => __( 'Dirección completa', 'gestion-he' ),
            'che_sede_cp'        => __( 'Código postal', 'gestion-he' ),
            'che_sede_encargado' => __( 'Encargado de la sede', 'gestion-he' ),
            'che_sede_telefono'  => __( 'Teléfono', 'gestion-he' ),
            'che_sede_lat'       => __( 'Latitud', 'gestion-he' ),
            'che_sede_lng'       => __( 'Longitud', 'gestion-he' ),
            'che_sede_maps_url'  => __( 'URL Google Maps', 'gestion-he' ),
        ];

        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, $key, true );
            echo '<p>';
            echo '<label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
            echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="widefat">';
            echo '</p>';
        }
    }

    public static function save_meta_boxes( $post_id ) {
        if (
            ! isset( $_POST['che_sede_nonce'] ) ||
            ! wp_verify_nonce( $_POST['che_sede_nonce'], 'che_sede_details' )
        ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            'che_sede_direccion',
            'che_sede_cp',
            'che_sede_encargado',
            'che_sede_telefono',
            'che_sede_lat',
            'che_sede_lng',
            'che_sede_maps_url',
        ];

        foreach ( $fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }
    }
}

SedesMeta::init();
