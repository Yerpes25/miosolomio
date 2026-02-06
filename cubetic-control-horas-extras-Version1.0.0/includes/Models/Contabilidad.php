<?php
namespace ControlHoras\Models;

if (!defined('ABSPATH'))
    exit;

class Contabilidad
{
    /**
     * Obtiene trabajadores de una sede específica
     * @param int $sede_id ID de la sede (puede ser null para obtener todos)
     * @return array Array de trabajadores
     */
    public static function get_workers_by_sede($sede_id = null)
    {
        $users = get_users([
            'role__in' => ['worker'],
            'fields' => 'all',
        ]);

        if (empty($users)) {
            return [];
        }

        $workers = [];
        $sede_id = !empty($sede_id) ? (int) $sede_id : null;

        foreach ($users as $user) {
            $user_sede_id = (int) get_user_meta($user->ID, 'sede_trabajo', true);
            
            // Si se especifica una sede, filtrar por ella
            if ($sede_id !== null && $user_sede_id !== $sede_id) {
                continue;
            }

            $workers[(int) $user->ID] = [
                'ID' => (int) $user->ID,
                'display_name' => $user->display_name ?: $user->user_login,
                'user_email' => $user->user_email,
                'sede_id' => $user_sede_id,
                'nomina' => floatval(get_user_meta($user->ID, 'che_nomina', true) ?: 1200),
                'irpf' => floatval(get_user_meta($user->ID, 'che_irpf', true) ?: 15),
                'debito' => floatval(get_user_meta($user->ID, 'che_debito', true) ?: 0),
                'tarifa_semana' => floatval(get_user_meta($user->ID, 'che_tarifa_extra', true) ?: 15),
                'tarifa_festivo' => floatval(get_user_meta($user->ID, 'che_tarifa_extra_festivo', true) ?: 20),
            ];
        }

        return $workers;
    }

    /**
     * Calcula el desglose económico completo de un trabajador
     * @param int $worker_id ID del trabajador
     * @param array $options Opciones: mes, año, etc.
     * @return array Desglose económico
     */
    public static function calculate_worker_summary($worker_id, $options = [])
    {
        $defaults = [
            'mes' => date('m'),
            'año' => date('Y'),
        ];
        $options = wp_parse_args($options, $defaults);

        $worker_id = (int) $worker_id;
        $user = get_userdata($worker_id);

        if (!$user) {
            return [];
        }

        // Obtener datos del trabajador
        $sede_id = (int) get_user_meta($worker_id, 'sede_trabajo', true);
        $sede_nombre = '';
        if ($sede_id) {
            $sede_post = get_post($sede_id);
            if ($sede_post && $sede_post->post_type === 'che_sede') {
                $sede_nombre = $sede_post->post_title;
            }
        }

        $nomina = floatval(get_user_meta($worker_id, 'che_nomina', true) ?: 1200);
        $irpf_percent = floatval(get_user_meta($worker_id, 'che_irpf', true) ?: 15);
        $debito = floatval(get_user_meta($worker_id, 'che_debito', true) ?: 0);
        $tarifa_semana = floatval(get_user_meta($worker_id, 'che_tarifa_extra', true) ?: 15);
        $tarifa_festivo = floatval(get_user_meta($worker_id, 'che_tarifa_extra_festivo', true) ?: 20);
        $tarifa_hora_extra = floatval(get_user_meta($worker_id, 'che_tarifa_hora_extra', true) ?: 15);
        
        // Obtener horas extras guardadas manualmente en user_meta
        $horas_extras_meta = floatval(get_user_meta($worker_id, 'che_horas_extras', true) ?: 0);

        // Obtener partes del trabajador del mes especificado
        $partes = Parte::get_all_partes_by_worker($worker_id);
        
        $mes_año = sprintf('%04d-%02d', $options['año'], $options['mes']);
        $horas_extras = $horas_extras_meta;  // Usar valor guardado manualmente
        $partes_contados = 0;

        // Filtrar partes por mes y calcular horas extras
        if (is_array($partes)) {
            foreach ($partes as $parte) {
                $fecha_parte = !empty($parte['hora_inicio']) ? $parte['hora_inicio'] : ($parte['post_date'] ?? '');
                
                if (!$fecha_parte) {
                    continue;
                }

                if (substr($fecha_parte, 0, 7) !== $mes_año) {
                    continue;
                }

                // Calcular duración del parte
                if (!empty($parte['hora_inicio']) && !empty($parte['hora_fin'])) {
                    $segundos = strtotime($parte['hora_fin']) - strtotime($parte['hora_inicio']);
                    
                    if ($segundos > 0) {
                        $horas = $segundos / 3600;
                        $horas_extras += $horas;
                        $partes_contados++;
                    }
                }
            }
        }

        // Cálculos monetarios
        $horas_extras_cost = round($horas_extras * $tarifa_hora_extra, 2);
        $irpf_amount = round($nomina * ($irpf_percent / 100), 2);
        
        // Fórmula del Neto: Nómina - IRPF
        $neto = round($nomina - $irpf_amount, 2);
        
        // Total: (Nómina + Horas Extras) - Débito
        $total = round(($nomina + $horas_extras_cost) - $debito, 2);

        return [
            'worker_id' => $worker_id,
            'display_name' => $user->display_name ?: $user->user_login,
            'user_email' => $user->user_email,
            'sede_id' => $sede_id,
            'sede_nombre' => $sede_nombre,
            'nomina' => round($nomina, 2),
            'tarifa_semana' => round($tarifa_semana, 2),
            'tarifa_extra' => round($tarifa_semana, 2),
            'tarifa_festivo' => round($tarifa_festivo, 2),
            'horas_extras' => round($horas_extras, 2),
            'horas_extras_cost' => $horas_extras_cost,
            'irpf_percent' => $irpf_percent,
            'irpf_amount' => $irpf_amount,
            'debito' => round($debito, 2),
            'neto' => $neto,
            'total' => $total,
            'partes_contados' => $partes_contados,
        ];
    }

    /**
     * Obtiene el resumen contable de todos los trabajadores de una sede
     * @param int $sede_id ID de la sede
     * @param array $options Opciones: mes, año, etc.
     * @return array Array de resúmenes por trabajador
     */
    public static function get_sede_accounting_summary($sede_id, $options = [])
    {
        $workers = self::get_workers_by_sede($sede_id);
        $summary = [];

        foreach ($workers as $worker_id => $worker) {
            $summary[$worker_id] = self::calculate_worker_summary($worker_id, $options);
        }

        return $summary;
    }

    /**
     * Obtiene datos para tarjetas de empresas (sedes)
     * @return array Array con información de sedes
     */
    public static function get_sedes_summary()
    {
        $sedes = Sede::get_all_sedes();
        $summary = [];

        foreach ($sedes as $sede_id => $sede) {
            $workers_count = count(self::get_workers_by_sede($sede_id));
            
            // Calcular previsión de gasto mensual (nómina + horas extras)
            $workers_data = self::get_workers_by_sede($sede_id);
            $total_forecast = 0.0;

            foreach ($workers_data as $worker_id => $worker) {
                $worker_summary = self::calculate_worker_summary($worker_id);
                $total_forecast += $worker_summary['nomina'] + $worker_summary['horas_extras_cost'];
            }

            $summary[(int) $sede_id] = [
                'nombre' => $sede['nombre'],
                'workers_count' => $workers_count,
                'budget_forecast' => round($total_forecast, 2),
            ];
        }

        return $summary;
    }

    /**
     * Genera un resumen contable completo por trabajador (método legado)
     * @param array $options Opciones
     * @return array Resumen por trabajador
     */
    public static function get_summary_full($options = [])
    {
        $defaults = [
            'umbral_horas_dia' => 8.0,
            'filtrar_mes_actual' => true,
        ];
        $options = wp_parse_args($options, $defaults);

        $users = get_users([
            'role__in' => ['worker'],
            'fields' => 'all',
        ]);

        $workers_idx = [];
        $mes_actual = date('Y-m');

        foreach ($users as $user) {
            $id = (int) $user->ID;

            $sede_id = get_user_meta($id, 'sede_trabajo', true);
            $sede_nombre = '';
            if ($sede_id) {
                $sede_post = get_post($sede_id);
                if ($sede_post && $sede_post->post_type === 'che_sede') {
                    $sede_nombre = $sede_post->post_title;
                }
            }

            $nomina = floatval(get_user_meta($id, 'che_nomina', true) ?: 1200);
            $irpf = floatval(get_user_meta($id, 'che_irpf', true) ?: 15);
            $debito = floatval(get_user_meta($id, 'che_debito', true) ?: 0);
            $tarifa_semana = floatval(get_user_meta($id, 'che_tarifa_extra', true) ?: 15);
            $tarifa_festivo = floatval(get_user_meta($id, 'che_tarifa_extra_festivo', true) ?: 20);

            $workers_idx[$id] = [
                'ID' => $id,
                'display_name' => $user->display_name ?: $user->user_login,
                'sede_id' => $sede_id,
                'sede_nombre' => $sede_nombre,
                'nomina' => round($nomina, 2),
                'irpf' => round($irpf, 2),
                'debito' => round($debito, 2),
                'tarifa_semana' => round($tarifa_semana, 2),
                'tarifa_festivo' => round($tarifa_festivo, 2),
                'totales' => [
                    'horas_extra' => 0.0,
                    'horas_extra_cost' => 0.0,
                    'irpf_amount' => 0.0,
                    'neto' => 0.0,
                    'partes_contados' => 0,
                ],
            ];

            // Obtener partes del trabajador
            $partes_trabajador = Parte::get_all_partes_by_worker($id);
            
            if (is_array($partes_trabajador)) {
                foreach ($partes_trabajador as $p) {
                    $fecha_completa = !empty($p['hora_inicio']) ? $p['hora_inicio'] : ($p['post_date'] ?? '');
                    
                    if (!$fecha_completa) {
                        continue;
                    }

                    if ($options['filtrar_mes_actual'] && substr($fecha_completa, 0, 7) !== $mes_actual) {
                        continue;
                    }

                    // Calcular duración
                    if (!empty($p['hora_inicio']) && !empty($p['hora_fin'])) {
                        $segundos = strtotime($p['hora_fin']) - strtotime($p['hora_inicio']);
                        
                        if ($segundos > 0) {
                            $horas = $segundos / 3600;
                            $workers_idx[$id]['totales']['horas_extra'] += $horas;
                            $workers_idx[$id]['totales']['partes_contados']++;
                        }
                    }
                }
            }

            // Cálculos finales
            $horas_cost = round($workers_idx[$id]['totales']['horas_extra'] * $tarifa_semana, 2);
            $workers_idx[$id]['totales']['horas_extra_cost'] = $horas_cost;
            
            $irpf_amount = round(($nomina + $horas_cost) * ($irpf / 100), 2);
            $workers_idx[$id]['totales']['irpf_amount'] = $irpf_amount;
            
            $neto = round(($nomina + $horas_cost) - $debito, 2);
            $workers_idx[$id]['totales']['neto'] = $neto;
        }

        return $workers_idx;
    }
}
