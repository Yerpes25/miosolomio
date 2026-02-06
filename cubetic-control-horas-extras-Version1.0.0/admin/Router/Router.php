<?php
namespace ControlHoras\Admin\Router;

/* use WP_REST_Request;
use WP_REST_Response; */

if (!defined('ABSPATH'))
    exit;

class Router
{
    private static $instance = null;
    private $routes = [];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('init', [$this, 'register_routes']);
    
    }
    /**
     * Registra las rutas del plugin
     */
    public function register_routes()
    {


        // Login 
        $this->add_route('acceso', [
            'regex' => 'acceso/?$',
            'query' => 'index.php?admin_login=1',
            'template' => 'public/templates/admin-login.php'
        ]);

        $this->add_route('trabajadores/panel', [
            'regex' => 'trabajadores/panel/?$',
            'query' => 'index.php?worker_dashboard=1',
            'template' => 'public/templates/worker-view.php'
        ]);

        $this->add_route('administracion', [
            'regex'    => 'administracion/?$',
            'query'    => 'index.php?admin_dashboard=1',
            'template' => 'public/templates/admin-view.php'
        ]);


        // Aplica todas las rutas registradas
        $this->apply_routes();
    }

    private function add_route($path, $config)
    {
        $this->routes[$path] = $config;
    }

    /**
     * Aplica las rutas registradas
     */
    private function apply_routes(): void
    {
        foreach ($this->routes as $path => $config) {
            // Añadir regla de reescritura
            add_rewrite_rule(
                $config['regex'],
                $config['query'],
                'top'
            );

            // Extraer la variable de consulta del query
            preg_match('/\?(.*?)=(.*)/', $config['query'], $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $query_var = $matches[1];
                // Registrar el tag para la query var
                add_rewrite_tag('%' . $query_var . '%', $matches[2]);
            }

            // Registrar la plantilla asociada
            add_filter('template_include', function ($template) use ($config, $query_var) {
                if (get_query_var($query_var)) {
                    return CHE_PATH . $config['template'];
                }
                return $template;
            });
        }


    }

   
}
