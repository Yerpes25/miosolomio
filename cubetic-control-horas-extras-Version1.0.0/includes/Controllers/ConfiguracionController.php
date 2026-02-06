<?php
namespace ControlHoras\Includes\Controllers;

if (!defined('ABSPATH')) exit;

class ConfiguracionController
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
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('wp_ajax_che_save_empresa_config', [$this, 'ajax_save_config']);
        add_action('wp_ajax_che_upload_empresa_logo', [$this, 'ajax_upload_logo']);
        add_action('wp_ajax_che_remove_empresa_logo', [$this, 'ajax_remove_logo']);
    }

    /**
     * AJAX: Guardar configuración de empresa
     */
    public function ajax_save_config()
    {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes');
        }

        $empresa_nombre = isset($_POST['empresa_nombre']) ? sanitize_text_field($_POST['empresa_nombre']) : '';
        $empresa_email = isset($_POST['empresa_email']) ? sanitize_email($_POST['empresa_email']) : '';
        $empresa_telefono = isset($_POST['empresa_telefono']) ? sanitize_text_field($_POST['empresa_telefono']) : '';
        $empresa_direccion = isset($_POST['empresa_direccion']) ? sanitize_textarea_field($_POST['empresa_direccion']) : '';
        $empresa_cif = isset($_POST['empresa_cif']) ? sanitize_text_field($_POST['empresa_cif']) : '';

        // Validar campos obligatorios
        if (empty($empresa_nombre)) {
            wp_send_json_error('El nombre de la empresa es obligatorio');
        }

        if (empty($empresa_email) || !is_email($empresa_email)) {
            wp_send_json_error('El email no es válido');
        }

        // Guardar opciones
        update_option('che_empresa_nombre', $empresa_nombre);
        update_option('che_empresa_email', $empresa_email);
        update_option('che_empresa_telefono', $empresa_telefono);
        update_option('che_empresa_direccion', $empresa_direccion);
        update_option('che_empresa_cif', $empresa_cif);

        wp_send_json_success([
            'message' => 'Configuración guardada correctamente'
        ]);
    }

    /**
     * AJAX: Subir logo de empresa
     */
    public function ajax_upload_logo()
    {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes');
        }

        if (empty($_FILES['logo'])) {
            wp_send_json_error('No se ha enviado ningún archivo');
        }

        // Validar tipo de archivo
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['logo']['type'];

        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error('Tipo de archivo no permitido. Solo se permiten imágenes (JPEG, PNG, GIF, WebP)');
        }

        // Validar tamaño (máx 5MB)
        if ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
            wp_send_json_error('El archivo es demasiado grande. Máximo 5MB');
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Subir archivo
        $attachment_id = media_handle_upload('logo', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error('Error al subir el archivo: ' . $attachment_id->get_error_message());
        }

        // Obtener URL del archivo
        $logo_url = wp_get_attachment_url($attachment_id);

        // Eliminar logo anterior si existe
        $old_logo_id = get_option('che_empresa_logo_id');
        if ($old_logo_id) {
            wp_delete_attachment($old_logo_id, true);
        }

        // Guardar en opciones
        update_option('che_empresa_logo', $logo_url);
        update_option('che_empresa_logo_id', $attachment_id);

        wp_send_json_success([
            'url' => $logo_url,
            'id' => $attachment_id
        ]);
    }

    /**
     * AJAX: Eliminar logo de empresa
     */
    public function ajax_remove_logo()
    {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes');
        }

        $logo_id = get_option('che_empresa_logo_id');
        
        if ($logo_id) {
            wp_delete_attachment($logo_id, true);
        }

        delete_option('che_empresa_logo');
        delete_option('che_empresa_logo_id');

        wp_send_json_success([
            'message' => 'Logo eliminado correctamente'
        ]);
    }
}
