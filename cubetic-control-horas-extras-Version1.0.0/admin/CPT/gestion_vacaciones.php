<?php
namespace ControlHoras\Admin\CPT;

if ( ! defined( 'ABSPATH' ) ) exit;

class Vacaciones {

    public static function register() {
        if ( post_type_exists( 'che_vacacion' ) ) {
            return;
        }

        $labels = [
            'name'          => __( 'Vacaciones', 'gestion-he' ),
            'singular_name' => __( 'Vacación', 'gestion-he' ),
        ];

        $args = [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'gestion-empresarial',  // Submenú de Gestión Empresarial
            'show_in_rest'  => false,
            'supports'      => [ 'title' ],
            'capability_type' => 'post',
            'has_archive'   => false,
        ];

        register_post_type( 'che_vacacion', $args );
    }
}
