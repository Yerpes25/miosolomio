<?php
namespace ControlHoras\Includes\Controllers;

if (!defined('ABSPATH')) exit;

/**
 * Controlador para la vista del trabajador.
 */
class WorkerViewController
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Si en el futuro quieres encolar CSS/JS solo en el panel, puedes poner hooks aquí.
    }

    /**
     * Renderiza la vista del trabajador.
     * Prepara $view_data['worker'] con los datos del usuario logueado.
     */
    public function render_view()
    {
        $current_user = wp_get_current_user();

        $view_data = [
            'worker' => [
                'id'    => $current_user->ID,
                'name'  => $current_user->display_name,
                'email' => $current_user->user_email,
            ],
        ];

        // Hacer accesible $view_data dentro de la plantilla
        // (en tu worker-view.php usas directamente $view_data['worker'][...])
        include CHE_PATH . 'public/templates/worker-view.php';
    }
}
