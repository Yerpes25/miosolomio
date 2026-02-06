<?php
/**
 * Plugin Name: Control Horas Extras
 * Plugin URI: https://control-horas-extras.com
 * Description: Plugin para control de horas extras.
 * Version: 1.2.0
 * Author: Cubetic Consultores S.C.I
 * Author URI: https://www.cubetic.com/
 */

if (!defined('ABSPATH')) {
    exit; // Salida de seguridad: previene el acceso directo al archivo
}

// Forzar timezone de Madrid para el plugin
date_default_timezone_set('Europe/Madrid');

// Rutas base del plugin
if ( ! defined( 'CHE_PLUGIN_PATH' ) ) {
    define( 'CHE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );          // .../wp-content/plugins/control-horas-extras/
}
if ( ! defined( 'CHE_PLUGIN_URL' ) ) {
    define( 'CHE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Constantes específicas que antes estaban en Init.php
if ( ! defined( 'CHE_PATH' ) ) {
    // Si quieres, puedes usar el mismo valor que CHE_PLUGIN_PATH
    define( 'CHE_PATH', CHE_PLUGIN_PATH );
}
if ( ! defined( 'CHE_URL' ) ) {
    define( 'CHE_URL', CHE_PLUGIN_URL );
}
if ( ! defined( 'CHE_JS_URL' ) ) {
    define( 'CHE_JS_URL', CHE_URL . 'assets/js/' );
}
if ( ! defined( 'CHE_CSS_URL' ) ) {
    define( 'CHE_CSS_URL', CHE_URL . 'assets/css/' );
}
if ( ! defined( 'CHE_VERSION' ) ) {
    define( 'CHE_VERSION', '1.1.9' );
}

// Registra el hook de activación para crear los roles y capacidades al activar el plugin
require_once CHE_PLUGIN_PATH . 'includes/activation/activation.php';
register_activation_hook( __FILE__, [ 'ControlHoras\Includes\activation\Activation', 'activate' ] );

// Cargar funciones de validación de DNI/NIE
require_once CHE_PLUGIN_PATH . 'includes/dni-validation.php';

require_once CHE_PLUGIN_PATH . 'admin/Init.php';
ControlHoras\Admin\Init::get_instance();
