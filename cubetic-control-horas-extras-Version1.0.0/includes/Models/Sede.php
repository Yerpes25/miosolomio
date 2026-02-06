<?php
namespace ControlHoras\Models;

if (!defined('ABSPATH')) exit;

class Sede
{
    /**
     * Obtenemos todas las Sedes para la vista 
     * @return array{cp: mixed, direccion: mixed, encargado: mixed, nombre: string, telefono: mixed[]}
     */
    public static function get_all_sedes()
    {
        $query = new \WP_Query( [
        'post_type'      => 'che_sede',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ] );

    $sedes = [];

    foreach ( $query->posts as $sede_id ) {

        $sedes[ $sede_id ] = [
            'nombre'    => get_the_title( $sede_id ),
            'direccion' => get_post_meta( $sede_id, 'che_sede_direccion', true ),
            'cp'         => get_post_meta( $sede_id, 'che_sede_cp', true ),
            'encargado'  => get_post_meta( $sede_id, 'che_sede_encargado', true ),
            'telefono'  => get_post_meta( $sede_id, 'che_sede_telefono', true ),
            'lat'       => get_post_meta( $sede_id, 'che_sede_lat', true ),
            'lng'       => get_post_meta( $sede_id, 'che_sede_lng', true ),
        
        ];
    }


        return $sedes;
    }



    public static function get_sede_by_id($sede_id)
    {
        $sede = [
            'nombre'    => get_the_title( $sede_id ),
            'direccion' => get_post_meta( $sede_id, 'che_sede_direccion', true ),
            'cp'         => get_post_meta( $sede_id, 'che_sede_cp', true ),
            'encargado'  => get_post_meta( $sede_id, 'che_sede_encargado', true ),
            'telefono'  => get_post_meta( $sede_id, 'che_sede_telefono', true ),
            'lat'       => get_post_meta( $sede_id, 'che_sede_lat', true ),
            'lng'       => get_post_meta( $sede_id, 'che_sede_lng', true ),
        ];

        return $sede;
    }



    public static function create_sede($data)
    {
        $nombre = isset($data['nombre']) ? sanitize_text_field($data['nombre']) : '';
        $direccion = isset($data['direccion']) ? sanitize_text_field($data['direccion']) : '';
        $cp = isset($data['cp']) ? sanitize_text_field($data['cp']) : '';
        $encargado = isset($data['encargado']) ? sanitize_text_field($data['encargado']) : '';
        $telefono = isset($data['telefono']) ? sanitize_text_field($data['telefono']) : '';

        if (empty($nombre)) {
            return [
                'success' => false,
                'error' => 'El nombre de la sede es obligatorio.',
            ];
        }

        // Obtener coordenadas automáticamente según la dirección (opcional)
        $lat = null;
        $lng = null;
        
        if (!empty($direccion)) {
            [$lat, $lng] = self::obtener_coordenadas($direccion);
        }


        // Crear el post de tipo che_sede
        $post_id = wp_insert_post([
            'post_type' => 'che_sede',
            'post_title' => $nombre,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id) || !$post_id) {
            return [
                'success' => false,
                'error' => 'No se pudo crear la sede.',
            ];
        }

        // Guardar IP y usuario que creó la sede
        update_post_meta($post_id, 'creacion_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        update_post_meta($post_id, 'creado_por_id', get_current_user_id());
        update_post_meta($post_id, 'creacion_fecha', current_time('mysql'));

        // Guardar metadatos
        if ($direccion) {
            update_post_meta($post_id, 'che_sede_direccion', $direccion);
        }

        if ($cp) {
            update_post_meta($post_id, 'che_sede_cp', $cp);
        }

        if ($encargado) {
            update_post_meta($post_id, 'che_sede_encargado', $encargado);
        }

        if ($telefono) {
            update_post_meta($post_id, 'che_sede_telefono', $telefono);
        }

        if ($lat !== '') {
            update_post_meta($post_id, 'che_sede_lat', $lat);
        }
        if ($lng !== '') {
            update_post_meta($post_id, 'che_sede_lng', $lng);
        }
       

        // Generar festivos automáticamente si hay código postal
        if ($cp) {
            self::generar_festivos_automaticos($post_id, $cp);
        }

        return [
            'success' => true,
            'sede_id' => $post_id,
            'message' => 'Sede creada correctamente.',
        ];
    }

    private static function obtener_coordenadas($direccion)
{
    if (empty($direccion)) {
        return [null, null];
    }

    $url = "https://nominatim.openstreetmap.org/search";
    $params = [
        'q' => $direccion,
        'format' => 'json',
        'addressdetails' => 1,
        'limit' => 1,
    ];

     $headers = [
        'User-Agent' => 'ControlHorasApp/1.0 (nico.araoz@cubetic.com)',
    ];

    $response = wp_remote_get($url . '?' . http_build_query($params), [
        'timeout' => 15,
        'sslverify' => true,
         'headers' => $headers, // Agregar el encabezado User-Agent
    ]);

    if (is_wp_error($response)) {
        error_log('Error al obtener coordenadas: ' . $response->get_error_message());
        return [null, null];
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        $lat = $data[0]['lat'];
        $lng = $data[0]['lon'];
        return [$lat, $lng];
    }

    return [null, null];
}

    /**
     * Actualizar una sede existente
     */
    public static function update_sede($sede_id, $data)
    {
        if (!$sede_id || get_post_type($sede_id) !== 'che_sede') {
            return [
                'success' => false,
                'error' => 'Sede no válida.',
            ];
        }

        $nombre = isset($data['nombre']) ? sanitize_text_field($data['nombre']) : '';
        $direccion = isset($data['direccion']) ? sanitize_text_field($data['direccion']) : '';
        $cp = isset($data['cp']) ? sanitize_text_field($data['cp']) : '';
        $encargado = isset($data['encargado']) ? sanitize_text_field($data['encargado']) : '';
        $telefono = isset($data['telefono']) ? sanitize_text_field($data['telefono']) : '';

        // Actualizar título si se proporciona nombre
        if ($nombre) {
            wp_update_post([
                'ID' => $sede_id,
                'post_title' => $nombre,
            ]);
        }

        // Actualizar metadatos
        if (isset($data['direccion'])) {
            update_post_meta($sede_id, 'che_sede_direccion', $direccion);
        }

        if (isset($data['cp'])) {
            $cp_anterior = get_post_meta($sede_id, 'che_sede_cp', true);
            update_post_meta($sede_id, 'che_sede_cp', $cp);

            // Regenerar festivos si cambió el código postal
            if ($cp && $cp !== $cp_anterior) {
                self::generar_festivos_automaticos($sede_id, $cp);
            }
        }

        if (isset($data['encargado'])) {
            update_post_meta($sede_id, 'che_sede_encargado', $encargado);
        }

        if (isset($data['telefono'])) {
            update_post_meta($sede_id, 'che_sede_telefono', $telefono);
        }

        return [
            'success' => true,
            'sede_id' => $sede_id,
            'message' => 'Sede actualizada correctamente.',
        ];
    }

    /**
     * Eliminar una sede
     */
    public static function delete_sede($sede_id)
    {
        if (!$sede_id || get_post_type($sede_id) !== 'che_sede') {
            return [
                'success' => false,
                'error' => 'Sede no válida.',
            ];
        }

        $result = wp_delete_post($sede_id, true);

        if (!$result) {
            return [
                'success' => false,
                'error' => 'No se pudo eliminar la sede.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Sede eliminada correctamente.',
        ];
    }

    /**
     * Obtener festivos de una sede
     */
    public static function get_festivos($sede_id)
    {
        if (!$sede_id || get_post_type($sede_id) !== 'che_sede') {
            return [
                'success' => false,
                'error' => 'Sede no válida.',
            ];
        }

        $dias = get_post_meta($sede_id, 'che_sede_festivos', true);
        if (!is_array($dias)) {
            $dias = [];
        }

        $comunidad = get_post_meta($sede_id, 'che_sede_comunidad', true);
        $year = get_post_meta($sede_id, 'che_sede_festivos_year', true);

        return [
            'success' => true,
            'dias' => $dias,
            'comunidad' => $comunidad,
            'year' => $year,
        ];
    }

    /**
     * Guardar festivos personalizados de una sede
     */
    public static function save_festivos($sede_id, $dias)
    {
        if (!$sede_id || get_post_type($sede_id) !== 'che_sede') {
            return [
                'success' => false,
                'error' => 'Sede no válida.',
            ];
        }

        if (!is_array($dias)) {
            return [
                'success' => false,
                'error' => 'Formato de días inválido.',
            ];
        }

        // Normalizar a YYYY-MM-DD
        $limpios = [];
        foreach ($dias as $d) {
            $d = sanitize_text_field($d);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $limpios[] = $d;
            }
        }

        update_post_meta($sede_id, 'che_sede_festivos', $limpios);

        return [
            'success' => true,
            'dias' => $limpios,
            'message' => 'Festivos guardados correctamente.',
        ];
    }

    /**
     * Regenerar festivos automáticos de una sede
     */
    public static function regenerar_festivos($sede_id)
    {
        if (!$sede_id || get_post_type($sede_id) !== 'che_sede') {
            return [
                'success' => false,
                'error' => 'Sede no válida.',
            ];
        }

        $cp = get_post_meta($sede_id, 'che_sede_cp', true);

        if (!$cp) {
            return [
                'success' => false,
                'error' => 'La sede no tiene código postal configurado.',
            ];
        }

        // Regenerar festivos
        self::generar_festivos_automaticos($sede_id, $cp);

        // Obtener los festivos generados
        $festivos = get_post_meta($sede_id, 'che_sede_festivos', true);
        $comunidad = get_post_meta($sede_id, 'che_sede_comunidad', true);

        return [
            'success' => true,
            'dias' => $festivos,
            'comunidad' => $comunidad,
            'message' => 'Festivos regenerados correctamente.',
        ];
    }







    /**
     * Determina la comunidad autónoma según el código postal
     */
    private static function get_comunidad_by_cp($cp)
    {
        $cp = intval(substr($cp, 0, 2)); // Primeros 2 dígitos

        $map = [
            // Madrid
            '28' => 'madrid',
            // Cataluña
            '08' => 'cataluna',
            '17' => 'cataluna',
            '25' => 'cataluna',
            '43' => 'cataluna',
            // Andalucía
            '04' => 'andalucia',
            '11' => 'andalucia',
            '14' => 'andalucia',
            '18' => 'andalucia',
            '21' => 'andalucia',
            '23' => 'andalucia',
            '29' => 'andalucia',
            '41' => 'andalucia',
            // Comunidad Valenciana
            '03' => 'valencia',
            '12' => 'valencia',
            '46' => 'valencia',
            // Galicia
            '15' => 'galicia',
            '27' => 'galicia',
            '32' => 'galicia',
            '36' => 'galicia',
            // Castilla y León
            '05' => 'castilla_leon',
            '09' => 'castilla_leon',
            '24' => 'castilla_leon',
            '34' => 'castilla_leon',
            '37' => 'castilla_leon',
            '40' => 'castilla_leon',
            '42' => 'castilla_leon',
            '47' => 'castilla_leon',
            '49' => 'castilla_leon',
            // País Vasco
            '01' => 'pais_vasco',
            '20' => 'pais_vasco',
            '48' => 'pais_vasco',
            // Castilla-La Mancha
            '02' => 'castilla_mancha',
            '13' => 'castilla_mancha',
            '16' => 'castilla_mancha',
            '19' => 'castilla_mancha',
            '45' => 'castilla_mancha',
            // Canarias
            '35' => 'canarias',
            '38' => 'canarias',
            // Aragón
            '22' => 'aragon',
            '44' => 'aragon',
            '50' => 'aragon',
            // Extremadura
            '06' => 'extremadura',
            '10' => 'extremadura',
            // Asturias
            '33' => 'asturias',
            // Murcia
            '30' => 'murcia',
            // Baleares
            '07' => 'baleares',
            // Navarra
            '31' => 'navarra',
            // Cantabria
            '39' => 'cantabria',
            // La Rioja
            '26' => 'rioja',
            // Ceuta
            '51' => 'ceuta',
            // Melilla
            '52' => 'melilla',
        ];

        return isset($map[$cp]) ? $map[$cp] : null;
    }

    /**
     * Obtiene los festivos nacionales de España desde una API pública
     */
    private static function get_festivos_nacionales($year = null)
    {
        if (! $year) {
            $year = date('Y');
        }

        // API pública de festivos - Nager.Date
        $api_url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/ES";

        $response = wp_remote_get($api_url, [
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            error_log('CHE: Error al obtener festivos de API: ' . $response->get_error_message());
            return self::get_festivos_nacionales_fallback($year);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (! is_array($data)) {
            error_log('CHE: API festivos devolvió datos inválidos');
            return self::get_festivos_nacionales_fallback($year);
        }

        // Filtrar solo festivos nacionales (no regionales)
        $festivos = [];
        foreach ($data as $holiday) {
            if (isset($holiday['global']) && $holiday['global'] === true && isset($holiday['date'])) {
                $festivos[] = $holiday['date'];
            }
        }

        if (empty($festivos)) {
            error_log('CHE: API no devolvió festivos nacionales, usando fallback');
            return self::get_festivos_nacionales_fallback($year);
        }

        error_log('CHE: Festivos nacionales obtenidos de API: ' . count($festivos));
        return $festivos;
    }

    /**
     * Fallback: Festivos nacionales calculados localmente si falla la API
     */
    private static function get_festivos_nacionales_fallback($year)
    {
        // Festivos fijos nacionales
        $festivos = [
            "$year-01-01", // Año Nuevo
            "$year-01-06", // Reyes
            "$year-05-01", // Día del Trabajo
            "$year-08-15", // Asunción de la Virgen
            "$year-10-12", // Fiesta Nacional de España
            "$year-11-01", // Todos los Santos
            "$year-12-06", // Día de la Constitución
            "$year-12-08", // Inmaculada Concepción
            "$year-12-25", // Navidad
        ];

        // Calcular Semana Santa (Viernes Santo es festivo nacional)
        $pascua = self::calcular_pascua($year);
        $viernes_santo = date('Y-m-d', strtotime("$pascua -2 days"));
        $festivos[] = $viernes_santo;

        return $festivos;
    }

    /**
     * Obtiene los festivos autonómicos según la comunidad desde API
     */
    private static function get_festivos_autonomicos($comunidad, $year = null)
    {
        if (! $year) {
            $year = date('Y');
        }

        // Mapeo de comunidades a códigos ISO
        $comunidad_codes = [
            'cataluna' => ['CT', 'ES-CT'],
            'madrid' => ['MD', 'ES-MD'],
            'andalucia' => ['AN', 'ES-AN'],
            'valencia' => ['VC', 'ES-VC'],
            'galicia' => ['GA', 'ES-GA'],
            'pais_vasco' => ['PV', 'ES-PV'],
            'castilla_leon' => ['CL', 'ES-CL'],
            'castilla_mancha' => ['CM', 'ES-CM'],
            'canarias' => ['CN', 'ES-CN'],
            'aragon' => ['AR', 'ES-AR'],
            'extremadura' => ['EX', 'ES-EX'],
            'asturias' => ['AS', 'ES-AS'],
            'murcia' => ['MC', 'ES-MC'],
            'baleares' => ['IB', 'ES-IB'],
            'navarra' => ['NC', 'ES-NC'],
            'cantabria' => ['CB', 'ES-CB'],
            'rioja' => ['RI', 'ES-RI'],
            'ceuta' => ['CE', 'ES-CE'],
            'melilla' => ['ML', 'ES-ML'],
        ];

        $festivos = [];

        // API pública de festivos
        $api_url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/ES";

        $response = wp_remote_get($api_url, [
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (! is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (is_array($data) && isset($comunidad_codes[$comunidad])) {
                $codes = $comunidad_codes[$comunidad];

                foreach ($data as $holiday) {
                    if (
                        isset($holiday['global']) &&
                        $holiday['global'] === false &&
                        isset($holiday['counties']) &&
                        is_array($holiday['counties'])
                    ) {

                        foreach ($codes as $code) {
                            if (in_array($code, $holiday['counties']) && isset($holiday['date'])) {
                                $festivos[] = $holiday['date'];
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Si la API no devuelve festivos autonómicos o falla, usar fallback
        if (empty($festivos)) {
            error_log('CHE: Usando fallback para festivos de ' . $comunidad);
            $festivos = self::get_festivos_autonomicos_fallback($comunidad, $year);
        } else {
            error_log('CHE: Festivos autonómicos de ' . $comunidad . ' desde API: ' . count($festivos));
        }

        return $festivos;
    }

    /**
     * Fallback: Festivos autonómicos calculados localmente
     */
    private static function get_festivos_autonomicos_fallback($comunidad, $year)
    {
        $festivos = [];
        $pascua = self::calcular_pascua($year);
        $jueves_santo = date('Y-m-d', strtotime("$pascua -3 days"));
        $lunes_pascua = date('Y-m-d', strtotime("$pascua +1 day"));

        switch ($comunidad) {
            case 'cataluna':
                $festivos[] = "$year-06-24"; // San Juan
                $festivos[] = "$year-09-11"; // Diada de Cataluña
                $festivos[] = "$year-12-26"; // San Esteban
                $festivos[] = $lunes_pascua;
                break;

            case 'madrid':
                $festivos[] = "$year-05-02"; // Día de la Comunidad de Madrid
                $festivos[] = $jueves_santo;
                break;

            case 'andalucia':
                $festivos[] = "$year-02-28"; // Día de Andalucía
                $festivos[] = $jueves_santo;
                break;

            case 'valencia':
                $festivos[] = "$year-03-19"; // San José
                $festivos[] = "$year-10-09"; // Día de la Comunidad Valenciana
                $festivos[] = $lunes_pascua;
                break;

            case 'galicia':
                $festivos[] = "$year-05-17"; // Día de las Letras Gallegas
                $festivos[] = "$year-07-25"; // Día de Galicia
                $festivos[] = $jueves_santo;
                break;

            case 'pais_vasco':
                $festivos[] = $jueves_santo;
                $festivos[] = $lunes_pascua;
                break;

            case 'castilla_leon':
                $festivos[] = "$year-04-23"; // Día de Castilla y León
                $festivos[] = $jueves_santo;
                break;

            case 'castilla_mancha':
                $festivos[] = "$year-05-31"; // Día de Castilla-La Mancha
                $festivos[] = $jueves_santo;
                break;

            case 'canarias':
                $festivos[] = "$year-05-30"; // Día de Canarias
                $festivos[] = $jueves_santo;
                break;

            case 'aragon':
                $festivos[] = "$year-04-23"; // San Jorge
                $festivos[] = $jueves_santo;
                break;

            case 'extremadura':
                $festivos[] = "$year-09-08"; // Día de Extremadura
                $festivos[] = $jueves_santo;
                break;

            case 'asturias':
                $festivos[] = "$year-09-08"; // Día de Asturias
                $festivos[] = $jueves_santo;
                break;

            case 'murcia':
                $festivos[] = "$year-06-09"; // Día de Murcia
                $festivos[] = $jueves_santo;
                break;

            case 'baleares':
                $festivos[] = "$year-03-01"; // Día de Baleares
                $festivos[] = $lunes_pascua;
                break;

            case 'navarra':
                $festivos[] = $lunes_pascua;
                $festivos[] = $jueves_santo;
                break;

            case 'cantabria':
                $festivos[] = "$year-07-28"; // Día de Cantabria
                $festivos[] = $jueves_santo;
                break;

            case 'rioja':
                $festivos[] = "$year-06-09"; // Día de La Rioja
                $festivos[] = $jueves_santo;
                break;

            case 'ceuta':
                $festivos[] = "$year-08-05"; // Nuestra Señora de África
                $festivos[] = $jueves_santo;
                break;

            case 'melilla':
                $festivos[] = "$year-09-02"; // Día de Melilla
                $festivos[] = $jueves_santo;
                break;
        }

        return $festivos;
    }

    /**
     * Calcula la fecha de Pascua para un año dado (algoritmo de Gauss)
     */
    private static function calcular_pascua($year)
    {
        $a = $year % 19;
        $b = intval($year / 100);
        $c = $year % 100;
        $d = intval($b / 4);
        $e = $b % 4;
        $f = intval(($b + 8) / 25);
        $g = intval(($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intval($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intval(($a + 11 * $h + 22 * $l) / 451);
        $month = intval(($h + $l - 7 * $m + 114) / 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Genera automáticamente los festivos para una sede según su CP
     */
    private static function generar_festivos_automaticos($sede_id, $cp)
    {
        if (! $cp) {
            return;
        }

        $comunidad = self::get_comunidad_by_cp($cp);
        if (! $comunidad) {
            return;
        }

        $year = date('Y');

        // Obtener festivos nacionales
        $festivos_nacionales = self::get_festivos_nacionales($year);

        // Obtener festivos autonómicos
        $festivos_autonomicos = self::get_festivos_autonomicos($comunidad, $year);

        // Combinar y eliminar duplicados
        $todos_festivos = array_unique(array_merge($festivos_nacionales, $festivos_autonomicos));

        // Ordenar por fecha
        sort($todos_festivos);

        // Guardar en la sede
        update_post_meta($sede_id, 'che_sede_festivos', $todos_festivos);
        update_post_meta($sede_id, 'che_sede_comunidad', $comunidad);
        update_post_meta($sede_id, 'che_sede_festivos_year', $year);
    }
}
