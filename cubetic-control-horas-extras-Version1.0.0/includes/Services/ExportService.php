<?php
namespace ControlHoras\Includes\Services;

if (!defined('ABSPATH')) exit;

class ExportService
{
    /**
     * Exportar datos a CSV
     * 
     * @param array $data Array de datos a exportar
     * @param string $filename Nombre del archivo (sin extensión)
     * @param array $headers Headers personalizados (opcional)
     */
    public static function export_csv($data, $filename = 'export', $headers = [])
    {
        if (empty($data)) {
            wp_send_json_error('No hay datos para exportar');
            return;
        }

        // Usar los headers del primer elemento si no se especifican
        if (empty($headers) && is_array($data[0])) {
            $headers = array_keys((array)$data[0]);
        }

        // Headers del archivo
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '-' . date('Y-m-d_H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // BOM para UTF-8
        echo "\xEF\xBB\xBF";

        // Output stream
        $output = fopen('php://output', 'w');

        // Escribir headers
        fputcsv($output, $headers, ';');

        // Escribir filas
        foreach ($data as $row) {
            $row_data = is_array($row) ? $row : (array)$row;
            fputcsv($output, $row_data, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Exportar datos a JSON
     * 
     * @param array $data Array de datos a exportar
     * @param string $filename Nombre del archivo (sin extensión)
     */
    public static function export_json($data, $filename = 'export')
    {
        if (empty($data)) {
            wp_send_json_error('No hay datos para exportar');
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '-' . date('Y-m-d_H-i-s') . '.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Exportar datos a Excel (CSV)
     * 
     * @param array $data Array de datos a exportar
     * @param string $filename Nombre del archivo
     * @param array $headers Headers personalizados
     */
    public static function export_excel($data, $filename = 'export', $headers = [])
    {
        return self::export_csv($data, $filename, $headers);
    }

    /**
     * Preparar datos para exportación
     * 
     * @param array $rows Array de registros
     * @param array $field_mapping Mapeo de campos: ['bd_field' => 'Nombre en Excel']
     * @return array
     */
    public static function prepare_export_data($rows, $field_mapping = [])
    {
        $formatted_data = [];

        foreach ($rows as $row) {
            $formatted_row = [];

            if (empty($field_mapping)) {
                // Sin mapeo, usar valores directamente
                $formatted_row = is_array($row) ? $row : (array)$row;
            } else {
                // Con mapeo
                $row_array = is_array($row) ? $row : (array)$row;
                foreach ($field_mapping as $db_field => $display_field) {
                    $formatted_row[$display_field] = $row_array[$db_field] ?? '';
                }
            }

            $formatted_data[] = $formatted_row;
        }

        return $formatted_data;
    }

    /**
     * Generar nombre de archivo con timestamp
     * 
     * @param string $base_name Nombre base
     * @return string
     */
    public static function generate_filename($base_name)
    {
        return sanitize_file_name($base_name) . '-' . date('Y-m-d_H-i-s');
    }
}
