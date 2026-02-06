<?php

namespace ControlHoras\Includes\Api;

use WP_REST_Request;
use WP_REST_Response;
use ControlHoras\Models\Sede;
use WP_Query;

class SedeController
{

    const NAMESPACE = 'che/v1';

    /**
     * Registrar rutas REST de sedes.
     */
    public function register_routes()
    {

        // Listar sedes
        register_rest_route(
            self::NAMESPACE,
            '/admin/sedes',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_sedes'],
                'permission_callback' => '__return_true', // luego puedes endurecerlo
            ]
        );

        // Crear nueva sede
        register_rest_route(
            self::NAMESPACE,
            '/admin/sedes',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create_sede'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'nombre' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'direccion' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'cp' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'encargado' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'telefono' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'lat' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'lng' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'maps_url' => [
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ]
        );

        // Eliminar sede
        register_rest_route(
            self::NAMESPACE,
            '/admin/sedes/eliminar',
            [
                'methods'             => 'POST', // puedes usar 'DELETE' si prefieres
                'callback'            => [ $this, 'delete_sede' ],
                'permission_callback' => '__return_true',
            ]
        );


        // Festivos de una sede: obtener
        register_rest_route(
            self::NAMESPACE,
            '/admin/sedes/(?P<sede_id>\d+)/festivos',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_sede_festivos'],
                'permission_callback' => function () {
                    return current_user_can('read');
                },
            ]
        );

        // Festivos de una sede: guardar
        register_rest_route(
            self::NAMESPACE,
            '/admin/sedes/(?P<sede_id>\d+)/festivos',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'save_sede_festivos'],
                'permission_callback' => function () {
                    return current_user_can('read');
                    /* return current_user_can( 'edit_posts' ); */
                },
                'args'                => [
                    'dias' => [
                        'required'          => true,
                        'validate_callback' => function ($value) {
                            return is_array($value);
                        },
                    ],
                ],
            ]
        );

        // Regenerar festivos automáticos de una sede
        register_rest_route(
            self::NAMESPACE,
            '/admin/sedes/(?P<sede_id>\d+)/festivos/regenerar',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'regenerar_festivos'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );
    }

    /**
     * Obtiene las sedes desde el CPT che_sede y devuelve
     * un array [ id => nombre ].
     */
    public function get_sedes(WP_REST_Request $request)
    {

        $sedes = [];
        $sedes = Sede::get_all_sedes();
        //retornamos succes y los datos de las sedes

        return new WP_REST_Response(
            ['success' => true, 'sedes' => $sedes],
            200
        );
    }

    /**
     * Crea una nueva sede como CPT che_sede.
     * POST /admin/sedes
     */
    public function create_sede(WP_REST_Request $request)
    {
        $data = [
            'nombre' => $request->get_param('nombre'),
            'direccion' => $request->get_param('direccion'),
            'cp' => $request->get_param('cp'),
            'encargado' => $request->get_param('encargado'),
            'telefono' => $request->get_param('telefono'),
        ];

        $result = Sede::create_sede($data);

        if (!$result['success']) {
            return new WP_REST_Response(
                ['success' => false, 'error' => $result['error']],
                400
            );
        }

        // Devolver la lista actualizada de sedes
        $sedes = Sede::get_all_sedes();

        return new WP_REST_Response(
            [
                'success' => true,
                'sedes' => $sedes,
            ],
            201
        );
    }
    
    /**
     * Obtener festivos de una sede.
     * GET /che/v1/admin/sedes/{sede_id}/festivos
     */
    public function get_sede_festivos(WP_REST_Request $request)
    {
        $sede_id = (int) $request->get_param('sede_id');
        
        $result = Sede::get_festivos($sede_id);

        if (!$result['success']) {
            return new WP_REST_Response(
                ['success' => false, 'error' => $result['error']],
                400
            );
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Guardar festivos de una sede.
     * POST /che/v1/admin/sedes/{sede_id}/festivos
     * body: { dias: ['YYYY-MM-DD', ...] }
     */
    public function save_sede_festivos(WP_REST_Request $request)
    {
        $sede_id = (int) $request->get_param('sede_id');
        $dias = $request->get_param('dias');

        $result = Sede::save_festivos($sede_id, $dias);

        if (!$result['success']) {
            return new WP_REST_Response(
                ['success' => false, 'error' => $result['error']],
                400
            );
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Regenerar festivos automáticos de una sede basándose en su código postal
     * POST /che/v1/admin/sedes/{sede_id}/festivos/regenerar
     */
    public function regenerar_festivos(WP_REST_Request $request)
    {
        $sede_id = (int) $request->get_param('sede_id');

        $result = Sede::regenerar_festivos($sede_id);

        if (!$result['success']) {
            return new WP_REST_Response(
                ['success' => false, 'error' => $result['error']],
                400
            );
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Eliminar una sede.
     * POST /che/v1/admin/sedes/eliminar
     * body: { sede_id: ID de la sede }
     */
    public function delete_sede( WP_REST_Request $request ) {
    $sede_id = (int) ( $request->get_param( 'sede_id' ) ?? 0 );

    if ( ! $sede_id ) {
        return new WP_REST_Response(
            [ 'success' => false, 'error' => 'ID de sede no válido.' ],
            400
        );
    }

    $result = \ControlHoras\Models\Sede::delete_sede( $sede_id );

    if ( ! $result['success'] ) {
        return new WP_REST_Response(
            [ 'success' => false, 'error' => $result['error'] ],
            400
        );
    }

    return new WP_REST_Response(
        [
            'success' => true,
            'message' => $result['message'],
            'sede_id' => $sede_id,
        ],
        200
    );
}

    
}
