<?php
namespace ControlHoras\Admin\CPT;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sedes {

    public static function register() {
        if ( post_type_exists( 'che_sede' ) ) {
            return;
        }

        $labels = [
            'name'               => __( 'Sedes', 'gestion-he' ),
            'singular_name'      => __( 'Sede', 'gestion-he' ),
            'add_new'            => __( 'Añadir sede', 'gestion-he' ),
            'add_new_item'       => __( 'Añadir nueva sede', 'gestion-he' ),
            'edit_item'          => __( 'Editar sede', 'gestion-he' ),
            'new_item'           => __( 'Nueva sede', 'gestion-he' ),
            'view_item'          => __( 'Ver sede', 'gestion-he' ),
            'search_items'       => __( 'Buscar sedes', 'gestion-he' ),
            'not_found'          => __( 'No se han encontrado sedes', 'gestion-he' ),
            'not_found_in_trash' => __( 'No hay sedes en la papelera', 'gestion-he' ),
            'menu_name'          => __( 'Sedes', 'gestion-he' ),
        ];

        $args = [
            'labels'        => $labels,
            'public'        => false,          // solo backend
            'show_ui'       => true,
            'show_in_menu'  => 'gestion-empresarial',  // Submenú de Gestión Empresarial
            'show_in_rest'  => true,          // disponible vía REST wp/v2/sedes
            'rest_base'     => 'sedes',
            'supports'      => [ 'title' ],   // de momento solo nombre
            'has_archive'   => false,
            'menu_position' => 22,
            'menu_icon'     => 'dashicons-store',
        ];

        register_post_type( 'che_sede', $args );
    }
}
