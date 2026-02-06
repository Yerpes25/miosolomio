<?php
namespace ControlHoras\Includes\Controllers;

if (!defined('ABSPATH')) exit;

class DashboardController
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // En tu MVP no necesitas hooks aquí todavía
    }

    /**
     * Devuelve datos básicos para el dashboard del trabajador/admin.
     * Esta función es la que usa AuthHandler para guardar la transient.
     */
    public function prepare_dashboard_data(): array
    {
        $current_user = wp_get_current_user();

        return [
            'user' => [
                'id'    => $current_user->ID,
                'name'  => $current_user->display_name,
                'email' => $current_user->user_email,
                'avatar'=> get_avatar_url($current_user->ID),
                'roles' => $current_user->roles,
            ],
            // Espacio reservado para que luego añadas más cosas
            'summary' => [
                'partes_totales'    => 0,
                'partes_activas'    => 0,
                'partes_validadas'  => 0,
            ],
        ];
    }
}
