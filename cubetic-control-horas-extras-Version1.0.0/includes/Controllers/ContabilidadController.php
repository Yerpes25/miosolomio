<?php
namespace ControlHoras\Includes\Controllers;

use ControlHoras\Models\Contabilidad;
use ControlHoras\Models\Sede;

if (!defined('ABSPATH')) exit;

class ContabilidadController
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
        add_action('wp_ajax_che_get_sedes_accounting_summary', [$this, 'ajax_get_sedes_summary']);
        add_action('wp_ajax_che_get_workers_accounting', [$this, 'ajax_get_workers_accounting']);
        add_action('wp_ajax_che_save_worker_tarifas', [$this, 'ajax_save_worker_tarifas']);
    }

    /**
     * AJAX: Obtener resumen de sedes (tarjetas)
     */
    public function ajax_get_sedes_summary()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes');
        }

        try {
            $summary = Contabilidad::get_sedes_summary();
            wp_send_json_success($summary);
        } catch (\Exception $e) {
            error_log('Error en get_sedes_summary: ' . $e->getMessage());
            wp_send_json_error('Error al cargar sedes: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Obtener contabilidad de trabajadores por sede
     */
    public function ajax_get_workers_accounting()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes');
        }

        $sede_id = isset($_POST['sede_id']) ? intval($_POST['sede_id']) : null;
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : date('Y-m');

        // Extraer mes y año
        $date_parts = explode('-', $month);
        if (count($date_parts) === 2) {
            $year = intval($date_parts[0]);
            $month_num = intval($date_parts[1]);
        } else {
            $year = intval(date('Y'));
            $month_num = intval(date('m'));
        }

        $options = [
            'mes' => $month_num,
            'año' => $year,
        ];

        // Obtener trabajadores de la sede
        $workers = Contabilidad::get_workers_by_sede($sede_id);
        $summary = [];

        foreach ($workers as $worker_id => $worker) {
            $worker_summary = Contabilidad::calculate_worker_summary($worker_id, $options);
            if (!empty($worker_summary)) {
                $summary[] = $worker_summary;
            }
        }

        wp_send_json_success($summary);
    }

    /**
     * AJAX: Guardar tarifas de un trabajador
     */
    public function ajax_save_worker_tarifas()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos suficientes');
        }

        $worker_id = isset($_POST['worker_id']) ? intval($_POST['worker_id']) : 0;

        if (!$worker_id) {
            wp_send_json_error('ID de trabajador inválido');
        }

        // Validar que el usuario exista
        $user = get_userdata($worker_id);
        if (!$user) {
            wp_send_json_error('Trabajador no encontrado');
        }

        // Guardar los valores
        $fields = [
            'che_nomina' => 'nomina',
            'che_irpf' => 'irpf',
            'che_tarifa_extra' => 'tarifa_semana',
            'che_tarifa_extra_fin_semana' => 'tarifa_extra',
            'che_tarifa_extra_festivo' => 'tarifa_festivo',
            'che_tarifa_hora_extra' => 'tarifa_hora_extra',
            'che_debito' => 'debito',
            'che_horas_extras' => 'horas_extras',
        ];

        $saved = [];
        $old_debito = floatval(get_user_meta($worker_id, 'che_debito', true) ?: 0);
        $new_debito = 0;
        
        foreach ($fields as $meta_key => $post_key) {
            if (isset($_POST[$post_key])) {
                // Convertir horas_extras a integer, el resto a float
                if ($post_key === 'horas_extras') {
                    $value = intval($_POST[$post_key]);
                } else {
                    $value = floatval($_POST[$post_key]);
                }
                
                // Guardar el nuevo valor de débito
                if ($post_key === 'debito') {
                    $new_debito = $value;
                }
                
                update_user_meta($worker_id, $meta_key, $value);
                $saved[$meta_key] = $value;
            }
        }

        // Registrar actividad SOLO si el débito cambió y es mayor que 0
        if ($new_debito > 0 && $new_debito != $old_debito) {
            $this->log_debito_activity($worker_id, $new_debito);
        }

        // Retornar los datos actualizados
        $updated = Contabilidad::calculate_worker_summary($worker_id, [
            'mes' => intval(date('m')),
            'año' => intval(date('Y')),
        ]);

        wp_send_json_success($updated);
    }

    /**
     * Registrar actividad de débito en el historial
     */
    private function log_debito_activity($worker_id, $debito_amount)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'che_debito_logs';
        $current_time = current_time('mysql');
        $admin_id = get_current_user_id();
        
        // Verificar si ya existe un registro duplicado en los últimos 10 segundos
        $recent_duplicate = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table 
             WHERE worker_id = %d 
             AND admin_id = %d 
             AND debito_amount = %f 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)",
            $worker_id,
            $admin_id,
            $debito_amount
        ));
        
        // No insertar si ya existe un registro duplicado reciente
        if ($recent_duplicate) {
            error_log('[CHE Débito] Duplicado detectado y no insertado para worker_id: ' . $worker_id . ', amount: ' . $debito_amount);
            return;
        }
        
        // Insertar registro
        $result = $wpdb->insert(
            $table,
            [
                'worker_id' => $worker_id,
                'admin_id' => $admin_id,
                'debito_amount' => $debito_amount,
                'ip_address' => $this->get_client_ip(),
                'created_at' => $current_time,
            ],
            ['%d', '%d', '%f', '%s', '%s']
        );
        
        if ($result) {
            error_log('[CHE Débito] Registro insertado correctamente para worker_id: ' . $worker_id);
        } else {
            error_log('[CHE Débito] Error al insertar registro: ' . $wpdb->last_error);
        }
    }

    /**
     * Obtener IP del cliente
     */
    private function get_client_ip()
    {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return sanitize_text_field($ip);
    }
}