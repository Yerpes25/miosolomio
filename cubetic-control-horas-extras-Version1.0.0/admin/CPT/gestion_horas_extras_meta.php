<?php
namespace ControlHoras\Admin\CPT;

if (!defined("ABSPATH")) {
    exit;
}

class HorasExtrasMeta
{
    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_meta_boxes']);
    }

    public static function add_meta_boxes()
    {
        add_meta_box(
            'parte_horario_details',
            __('Detalles del parte', 'gestion-he'),
            [__CLASS__, 'render_meta_box'],
            'parte_horario', // aplicar los metaboxes al CPT `parte_horario`
            'normal',
            'high'
        );
    }


    public static function render_meta_box($post)
    {
        wp_nonce_field('parte_horario_details', 'parte_horario_nonce');

        $fields = [
            'trabajador_id' => __('ID Trabajador  (user_id)', 'gestion-he'),
            'proyecto' => __('Proyecto', 'gestion-he'),
            'hora_inicio' => __('Hora de Inicio (YYYY-mm-dd HH:ii)', 'gestion-he'),
            'hora_finalizacion' => __('Hora de Finalización (YYYY-mm-dd HH:ii)', 'gestion-he'),
            'elapsed_time'      => __('Tiempo total (HH:MM:SS)', 'gestion-he'),
            'estado' => __('Estado(activo/finalizado/validado)', 'gestion-he'),
            /* 'ubicacion' => __('Ubicación', 'gestion-he'), */
            
        ];

        /**
         * Renderizar los campos del metabox
         */
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, $key, true);
            echo '<p>';
            echo '<label for="' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
            echo '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="widefat">';
            echo '</p>';
        }

        /**
         * Mostrar datos de geolocalización si existen
         */
        $geo_inicio_lat = get_post_meta($post->ID, 'geo_inicio_lat', true);
        $geo_inicio_lng = get_post_meta($post->ID, 'geo_inicio_lng', true);
        $geo_fin_lat    = get_post_meta($post->ID, 'geo_fin_lat', true);
        $geo_fin_lng    = get_post_meta($post->ID, 'geo_fin_lng', true);

        echo '<hr>';

        echo '<p><strong>' . esc_html__('Geolocalización inicio', 'gestion-he') . '</strong><br>';
        if ($geo_inicio_lat && $geo_inicio_lng) {
            $url = 'https://www.google.com/maps?q=' . rawurlencode($geo_inicio_lat . ',' . $geo_inicio_lng);
            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html__('Ver en Google Maps', 'gestion-he') .
                '</a>';
        } else {
            echo esc_html__('Sin datos de geolocalización de inicio', 'gestion-he');
        }
        echo '</p>';

        echo '<p><strong>' . esc_html__('Geolocalización fin', 'gestion-he') . '</strong><br>';
        if ($geo_fin_lat && $geo_fin_lng) {
            $url = 'https://www.google.com/maps?q=' . rawurlencode($geo_fin_lat . ',' . $geo_fin_lng);
            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html__('Ver en Google Maps', 'gestion-he') .
                '</a>';
        } else {
            echo esc_html__('Sin datos de geolocalización de fin', 'gestion-he');
        }
        echo '</p>';
        
        // === AÑADIR AQUÍ LOS ADJUNTOS ===
        $adjuntos = get_post_meta( $post->ID, 'parte_adjuntos', true );
        if ( is_array( $adjuntos ) && $adjuntos ) {
            echo '<hr><p><strong>' . esc_html__( 'Adjuntos del parte', 'gestion-he' ) . '</strong></p>';
            echo '<ul>';
            foreach ( $adjuntos as $att_id ) {
                $url   = wp_get_attachment_url( $att_id );
                $title = get_the_title( $att_id );
                if ( $url ) {
                    echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">'
                        . esc_html( $title ?: $url )
                        . '</a></li>';
                }
            }
            echo '</ul>';
        }

    }

    public static function save_meta_boxes($post_id)
    {
        if (
            !isset($_POST['parte_horario_nonce']) ||
            !wp_verify_nonce($_POST['parte_horario_nonce'], 'parte_horario_details')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        /**
         * Guardar los campos del metabox
         */
        $fields = [
            'trabajador_id',
            'proyecto',
            'hora_inicio',
            'hora_finalizacion',
            'estado',
            /* 'ubicacion', */
            'geo_inicio_lat',
            'geo_inicio_lng',
            'geo_fin_lat',
            'geo_fin_lng',
        ];

        foreach ($fields as $key) {
            if (isset($_POST[$key])) {
                update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
            }
        }
    }

}
HorasExtrasMeta::init();