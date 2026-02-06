<?php
namespace ControlHoras\Includes\Controllers;

use ControlHoras\Models\Parte;
use ControlHoras\Models\Sede;
use ControlHoras\Models\Contabilidad;


if ( ! defined( 'ABSPATH' ) ) exit;

class AdminViewController
{
    private static $instance = null;

    private $sedes;

    private $partes;

    private $workers;

    public static function get_instance()
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
       $this->sedes = Sede::get_all_sedes();
       $this->partes = Parte::get_pendientes_para_admin();
       // No cargar workers aquí, se cargarán en render_view() cuando WordPress esté listo
    }

    /**
     * Obtener todos los workers
     */
    private function get_all_workers()
    {
        $roles = ['worker', 'admin', 'super_admin'];

        $args = [
            'role__in' => $roles,
            'number' => 100,
        ];

        $users = get_users($args);
        $data = [];

        foreach ($users as $user) {
            $sede_id = get_user_meta($user->ID, 'sede_trabajo', true);
            $sede_nombre = '';

            if ($sede_id) {
                $sede_post = get_post((int) $sede_id);
                if ($sede_post && $sede_post->post_type === 'che_sede') {
                    $sede_nombre = $sede_post->post_title;
                }
            }

            $data[] = [
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID),
                'roles' => $user->roles,
                'sede_id' => $sede_id,
                'sede_nombre' => $sede_nombre,
                'nomina_dia' => get_user_meta($user->ID, 'che_nomina_dia', true) ?: 1,
                'tarifa_normal' => floatval(get_user_meta($user->ID, 'che_tarifa_normal', true)) ?: 10,
                'tarifa_extra' => floatval(get_user_meta($user->ID, 'che_tarifa_extra', true)) ?: 15,
                'tarifa_extra_festivo' => floatval(get_user_meta($user->ID, 'che_tarifa_extra_festivo', true)) ?: 20,
            ];
        }

        return $data;
    }

    /**
     * Obtener todas las vacaciones
     */
    private function get_all_vacaciones()
    {
        $query = new \WP_Query( [
            'post_type'      => 'che_vacacion',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $rows = [];

        foreach ( $query->posts as $post ) {
            $trabajador_id = (int) get_post_meta( $post->ID, 'trabajador_id', true );
            $worker_name   = $trabajador_id ? get_the_author_meta( 'display_name', $trabajador_id ) : '';
            $sede_id       = (int) get_post_meta( $post->ID, 'sede_id', true );
            $sede_nombre   = '';

            if ( $sede_id ) {
                $sede_post = get_post( $sede_id );
                if ( $sede_post && $sede_post->post_type === 'che_sede' ) {
                    $sede_nombre = $sede_post->post_title;
                }
            }

            // Limpiamos las fechas ANTES de enviarlas ---
            $raw_inicio = get_post_meta( $post->ID, 'fecha_inicio', true );
            $raw_fin    = get_post_meta( $post->ID, 'fecha_fin', true );

            // Si está vacío o es ceros, lo ponemos como NULL
            $fecha_inicio = (empty($raw_inicio) || $raw_inicio === '0000-00-00') ? null : $raw_inicio;
            $fecha_fin    = (empty($raw_fin)    || $raw_fin === '0000-00-00')    ? null : $raw_fin;
            // -----------------------------------------------------------

            $rows[] = [
                'ID'           => $post->ID,
                'worker_id'    => $trabajador_id,
                'worker_name'  => $worker_name,
                
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin'    => $fecha_fin,
                
                'dias_totales' => (int) get_post_meta( $post->ID, 'dias_totales', true ),
                'estado'       => get_post_meta( $post->ID, 'estado', true ),
                'sede_id'      => $sede_id,
                'sede_nombre'  => $sede_nombre,
            ];
        }

        return $rows;
    }

    /**
     * Renderiza la vista del panel de administración de partes.
     * Solo accesible para roles time_admin y time_super_admin.
     */
    public function render_view()
    {
        if ( ! is_user_logged_in() ) {
            wp_redirect( home_url( '/acceso/' ) );
            exit;
        }

        $current_user = wp_get_current_user();
        $roles        = (array) $current_user->roles;

        if ( ! in_array( 'admin', $roles, true ) && ! in_array( 'super_admin', $roles, true ) ) {
            //redirigir con error de permisos
            wp_redirect( home_url( '/acceso/' ) );
            exit;
        }

        // Obtener todos los datos del usuario para validación en la vista 
        $admin = [
            'id'    => $current_user->ID,
            'name'  => $current_user->display_name,
            'email' => $current_user->user_email,
            'roles' => $roles,
            'avatar'=> get_avatar_url( $current_user->ID ),
        ];
        

        // Cargar workers aquí, cuando WordPress está completamente listo
        if (!$this->workers) {
            $this->workers = $this->get_all_workers();
        }

        // Nomina mes por defecto (YYYY-MM) para la vista
        $nomina_mes = date('Y-m');

        $view_data = [
            'admin'        => $admin,
            'partes'       => $this->partes,
            'summary'      => [],
            'sedes'        => $this->sedes,
            'workers'      => $this->workers,
            'vacaciones'   => $this->get_all_vacaciones(),
            'partes_admin' => Parte::get_all_partes_admin(),
            // Generar summary completo de contabilidad en PHP
            'contabilidad' => Contabilidad::get_summary_full(['filtrar_mes_actual' => true]),
        ];

        include CHE_PATH . 'public/templates/admin-view.php';
    }
}
