<?php
namespace ControlHoras\Admin\CPT;


if (!defined("ABSPATH")) {
    exit;
}


class HorasExtras
{

    public static function register()
    {
        /**verificamos si el cpt ya existe */
        if (post_type_exists("gestion_horas_extras")) {
            return;
        }
        
        /**
         * Registrar el Custom Post Type "parte_horario"
         */
        $labels = [

            'name' => __('Horas Extras', 'gestion-he'),
            'singular_name' => __('Horas Extras de los Trabajadores', 'gestion-he'),
            'add_new' => __('Añadir nuevo parte', 'gestion-he'),
            'add_new_item' => __('Añadir nuevo parte de horario', 'gestion-he'),
            'edit_item' => __('Editar parte de horario', 'gestion-he'),
            'new_item' => __('Nuevo parte de horario', 'gestion-he'),
            'view_item' => __('Ver parte de horario', 'gestion-he'),
            'search_items' => __('Buscar parte de horario', 'gestion-he'),
            'not_found' => __('No se encontraron partes', 'gestion-he'),
            'not_found_in_trash' => __('No se encontraron partes en la papelera', 'gestion-he'),

        ];


        $args = [
            'labels' => $labels,
            'public' => false,              // No visible como post normal
            'show_ui' => true,               // Sí visible en admin
            'show_in_menu' => 'gestion-empresarial',  // Submenú de Gestión Empresarial
            'show_in_rest' => true,               // Para WP REST API
            'supports' => ['title'],          // De momento solo título
            'capability_type' => 'post',
            'has_archive' => false,
            'menu_position' => 21,
            'menu_icon' => 'dashicons-clock',
        ];

        register_post_type("parte_horario", $args);


    }
}