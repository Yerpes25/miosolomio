<?php

namespace ControlHoras\Models;

if (!defined('ABSPATH')) exit;

class Parte
{
    /**
     * Crea un nuevo parte de horario al iniciar jornada.
     * $data debe tener: trabajador_id, proyecto (opcional), hora_inicio (opcional).
     */
    public static function create_parte($data)
    {
        /**
         * Valores por defecto y saneamiento de datos
         */
        $worker_id   = isset($data['trabajador_id']) ? intval($data['trabajador_id']) : get_current_user_id();
        $proyecto    = isset($data['proyecto']) ? sanitize_text_field($data['proyecto']) : '';
        $hora_inicio = !empty($data['hora_inicio']) ? sanitize_text_field($data['hora_inicio']) : current_time('mysql');
        $titulo_base      = !empty($data['titulo']) ? sanitize_text_field($data['titulo']) : 'Parte de horario';


        $geo_inicio  = isset($data['geo_inicio']) && is_array($data['geo_inicio']) ? $data['geo_inicio'] : null;

        $user       = get_userdata($worker_id);
        $nombre     = $user && $user->display_name ? $user->display_name : 'Trabajador';
        $post_title = sprintf('%s - %s', $nombre, $hora_inicio);

        $meta = [
            'trabajador_id'     => $worker_id,
            'proyecto'          => $proyecto,
            'hora_inicio'       => $hora_inicio,
            'hora_finalizacion' => '',
            'estado'            => 'activo',
            'ubicacion'         => '',
            'ip_inicio'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'creado_por_id'     => get_current_user_id(),
        ];

        /**
         *  Guardar datos de geolocalización si se proporcionan
         */
        if ($geo_inicio) {
            if (isset($geo_inicio['lat'])) {
                $meta['geo_inicio_lat'] = (float) $geo_inicio['lat'];
            }
            if (isset($geo_inicio['lng'])) {
                $meta['geo_inicio_lng'] = (float) $geo_inicio['lng'];
            }
        }
        /**
         * Insertar el post de tipo 'parte_horario'
         */
        $post_data = [
            'post_type'   => 'parte_horario',
            'post_title'  => $post_title,
            'post_status' => 'publish',
            'post_author' => $worker_id,
            'post_date'   => $hora_inicio,
            'meta_input'  => $meta,
        ];

        return wp_insert_post($post_data);
    }

    /**
     * Finaliza un parte de horario existente.
     */
    public static function finalizar_parte($parte_id, $geo_fin = null, $elapsed = 0)
    {
        if (!$parte_id) return false;

        update_post_meta($parte_id, 'hora_finalizacion', current_time('mysql'));
        update_post_meta($parte_id, 'estado', 'finalizado');
        update_post_meta($parte_id, 'estado_validacion', 'pendiente');
        update_post_meta($parte_id, 'ip_fin', $_SERVER['REMOTE_ADDR'] ?? '');
        update_post_meta($parte_id, 'finalizado_por_id', get_current_user_id());

        if (is_array($geo_fin)) {
            if (isset($geo_fin['lat'])) {
                update_post_meta($parte_id, 'geo_fin_lat', (float) $geo_fin['lat']);
            }
            if (isset($geo_fin['lng'])) {
                update_post_meta($parte_id, 'geo_fin_lng', (float) $geo_fin['lng']);
            }
        }
        $geo_fin_lat = get_post_meta($parte_id, 'geo_fin_lat', true);
        $geo_fin_lng = get_post_meta($parte_id, 'geo_fin_lng', true);

        if ($geo_fin_lat !== '' && $geo_fin_lng !== '') {
            // Sede del trabajador (user_meta 'sede_trabajo')
            $worker_id = (int) get_post_field('post_author', $parte_id);
            $sede_id   = (int) get_user_meta($worker_id, 'sede_trabajo', true);

            if ($sede_id) {
                $sede_lat = get_post_meta($sede_id, 'che_sede_lat', true);
                $sede_lng = get_post_meta($sede_id, 'che_sede_lng', true);

                if ($sede_lat !== '' && $sede_lng !== '') {
                    $dist_m = self::distance_haversine_m(
                        $geo_fin_lat,
                        $geo_fin_lng,
                        $sede_lat,
                        $sede_lng
                    );

                    // 500 metros o menos -> validación automática
                    if ($dist_m <= 500) {
                        update_post_meta($parte_id, 'estado_validacion', 'validado');
                    }
                }
            }
        }
        /**
         * Guardar tiempo trabajado
         */
        if ($elapsed > 0) {
            update_post_meta($parte_id, 'elapsed_seconds', $elapsed);

            $h = floor($elapsed / 3600);
            $m = floor(($elapsed % 3600) / 60);
            $s = $elapsed % 60;
            $formatted = sprintf('%02d:%02d:%02d', $h, $m, $s);
            update_post_meta($parte_id, 'elapsed_time', $formatted);
        }

        return true;
    }

    /**
     * Crea un parte manual: hora_inicio indicada, hora_fin = ahora, elapsed_time calculado.
     *
     * @param array $data {
     *   @type int    $trabajador_id
     *   @type string $proyecto
     *   @type string $hora_inicio    Hora de inicio. Puede ser "HH:MM" o fecha completa "Y-m-d H:i:s".
     *   @type string $titulo
     *   @type array  $geo_inicio     ['lat' => ..., 'lng' => ...]
     *   @type array  $geo_fin        ['lat' => ..., 'lng' => ...]
     * }
     * @return int|\WP_Error  ID del parte creado o WP_Error.
     */
    public static function crear_parte_manual( $data ) {
        $trabajador_id = isset( $data['trabajador_id'] ) ? (int) $data['trabajador_id'] : get_current_user_id();
        if ( ! $trabajador_id ) {
            return new \WP_Error( 'no_worker', 'Trabajador requerido.' );
        }

        $proyecto    = isset( $data['proyecto'] ) ? sanitize_text_field( $data['proyecto'] ) : '';
        $hora_inicio = isset( $data['hora_inicio'] ) ? sanitize_text_field( $data['hora_inicio'] ) : '';
        $titulo      = isset( $data['titulo'] ) ? sanitize_text_field( $data['titulo'] ) : 'Parte manual';
        $geo_inicio  = isset( $data['geo_inicio'] ) && is_array( $data['geo_inicio'] ) ? $data['geo_inicio'] : null;
        $geo_fin     = isset( $data['geo_fin'] ) && is_array( $data['geo_fin'] ) ? $data['geo_fin'] : null;

        if ( empty( $hora_inicio ) ) {
            return new \WP_Error( 'no_start', 'Hora de inicio requerida.' );
        }

        // Normalizar hora_inicio: si viene como HH:MM, usar fecha de hoy.
        if ( preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $hora_inicio ) ) {
            $today           = current_time( 'Y-m-d' );
            // Si viene HH:MM, añadimos :00
            if ( strlen( $hora_inicio ) === 5 ) {
                $hora_inicio .= ':00';
            }
            $hora_inicio_full = $today . ' ' . $hora_inicio;
        } else {
            $hora_inicio_full = $hora_inicio; // ya contiene fecha completa
        }

        $hora_fin_full = current_time( 'mysql' );

        try {
            $dt_inicio = new \DateTime( $hora_inicio_full );
            $dt_fin    = new \DateTime( $hora_fin_full );

            if ( $dt_fin < $dt_inicio ) {
                return new \WP_Error( 'invalid_range', 'La hora de inicio no puede ser posterior a la hora actual.' );
            }

            $elapsed = (int) ( $dt_fin->getTimestamp() - $dt_inicio->getTimestamp() );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'bad_time', 'Formato de hora no válido.' );
        }

        // 1) Crear el parte como si se hubiera iniciado a esa hora
        $post_id = self::create_parte([
            'trabajador_id' => $trabajador_id,
            'proyecto'      => $proyecto,
            'hora_inicio'   => $hora_inicio_full,
            'titulo'        => $titulo,
            'geo_inicio'    => $geo_inicio,
        ]);

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return new \WP_Error( 'create_failed', 'No se pudo crear el parte manual.' );
        }

        // 2) Finalizarlo inmediatamente con hora_fin = ahora y elapsed_time calculado
        $ok = self::finalizar_parte( $post_id, $geo_fin, $elapsed );
        if ( ! $ok ) {
            return new \WP_Error( 'finish_failed', 'No se pudo finalizar el parte manual.' );
        }

        return $post_id;
    }
    
    /**
     * Obtiene los partes pendientes para el panel de administración.
     */
    public static function get_pendientes_para_admin($args = [])
    {
        global $wpdb;

        $defaults = [
            'estado' => 'finalizado',
            'limit'  => 50,
        ];
        $args = wp_parse_args($args, $defaults);

        $table_posts = $wpdb->prefix . 'posts';
        $table_meta  = $wpdb->prefix . 'postmeta';

        // Ajusta 'parte' y 'che_estado' a tu CPT / meta reales
        $sql = $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_date, u.display_name AS worker_name,
                    m_estado.meta_value AS estado,
                    m_ini.meta_value AS hora_inicio,
                    m_fin.meta_value AS hora_finalizacion
                FROM {$table_posts} p
                LEFT JOIN {$table_meta} m_estado ON (p.ID = m_estado.post_id AND m_estado.meta_key = %s)
                LEFT JOIN {$table_meta} m_ini    ON (p.ID = m_ini.post_id    AND m_ini.meta_key    = %s)
                LEFT JOIN {$table_meta} m_fin    ON (p.ID = m_fin.post_id    AND m_fin.meta_key    = %s)
                LEFT JOIN {$wpdb->users} u       ON (p.post_author = u.ID)
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND m_estado.meta_value = %s
                ORDER BY p.post_date DESC
                LIMIT %d",
            'estado',
            'hora_inicio',
            'hora_finalizacion',
            'parte_horario',
            $args['estado'],
            $args['limit']
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return $rows ? $rows : [];
    }

    /**
     * Obtiene todos los partes para admin (finalizado y pendiente)
     */
    public static function get_all_partes_admin()
    {
        $query = new \WP_Query([
            'post_type'      => 'parte_horario',
            'post_status'    => 'publish',
            'meta_key'       => 'estado',
            'meta_value'     => ['finalizado', 'pendiente'],
            'meta_compare'   => 'IN',
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $rows = [];
        foreach ($query->posts as $post) {
            $user_id = (int) $post->post_author;
            $worker_name = $user_id ? get_the_author_meta('display_name', $user_id) : '';
            $sede_id = (int) get_user_meta($user_id, 'sede_trabajo', true);
            $sede_nombre = '';

            if ($sede_id) {
                $sede_post = get_post($sede_id);
                if ($sede_post && $sede_post->post_type === 'che_sede') {
                    $sede_nombre = $sede_post->post_title;
                }
            }

            $rows[] = [
                'ID'                => $post->ID,
                'post_title'        => $post->post_title,
                'post_date'         => $post->post_date,
                'worker_id'         => $user_id,
                'worker_name'       => $worker_name,
                'hora_inicio'       => get_post_meta($post->ID, 'hora_inicio', true),
                'hora_fin'          => get_post_meta($post->ID, 'hora_finalizacion', true),
                'tiempo_total'      => get_post_meta($post->ID, 'elapsed_time', true),
                'elapsed_seconds'   => (int) get_post_meta($post->ID, 'elapsed_seconds', true),
                'estado'            => get_post_meta($post->ID, 'estado', true),
                'estado_validacion' => get_post_meta($post->ID, 'estado_validacion', true) ?: 'pendiente',
                'sede_id'           => $sede_id,
                'sede_nombre'       => $sede_nombre,
            ];
        }

        return $rows;
    }

    public static function get_all_partes_by_worker($worker_id)
    {
        $query = new \WP_Query([
            'post_type'      => 'parte_horario',
            'post_status'    => 'publish',
            'author'         => $worker_id,
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $rows = [];
        foreach ($query->posts as $post) {
            $rows[] = [
                'ID'              => $post->ID,
                'trabajador_id'   => (int) $post->post_author,
                'post_title'      => $post->post_title,
                'post_date'       => $post->post_date,
                'hora_inicio'     => get_post_meta($post->ID, 'hora_inicio', true),
                'hora_fin'        => get_post_meta($post->ID, 'hora_finalizacion', true),
                'tiempo_total'    => get_post_meta($post->ID, 'elapsed_time', true),
                'elapsed_seconds' => (int) get_post_meta($post->ID, 'elapsed_seconds', true),
                'estado'          => get_post_meta($post->ID, 'estado', true),
            ];
        }

        return $rows;
    }

    /**
     * Calcula la distancia Haversine entre dos puntos (lat/lng) en metros.
     */
    private static function distance_haversine_m($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // metros

        $latFrom = deg2rad((float) $lat1);
        $lonFrom = deg2rad((float) $lon1);
        $latTo   = deg2rad((float) $lat2);
        $lonTo   = deg2rad((float) $lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2 +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // metros
    }
}
