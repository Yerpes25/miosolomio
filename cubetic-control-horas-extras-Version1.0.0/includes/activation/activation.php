<?php
namespace ControlHoras\Includes\activation;

class Activation
{
    public static function activate() {
        self::register_roles();
        self::copy_service_worker();
        self::copy_manifest();
        self::create_notifications_table();
        self::create_debito_logs_table();
    }
    
    /**
     * Crea la tabla de logs de débito
     */
    private static function create_debito_logs_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'che_debito_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            worker_id bigint(20) NOT NULL,
            admin_id bigint(20) NOT NULL,
            debito_amount decimal(10,2) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY worker_id (worker_id),
            KEY admin_id (admin_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        error_log('CHE: Tabla de débito logs creada/verificada');
    }
    
    /**
     * Crea la tabla de notificaciones
     */
    private static function create_notifications_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'che_notifications';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            type VARCHAR(50) DEFAULT 'default',
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        error_log('CHE: Tabla de notificaciones creada/verificada');
    }
    
    /**
     * Copia el manifest.json a la raíz del sitio para PWA
     * Necesario para que la app sea instalable en móviles
     */
    private static function copy_manifest() {
        $manifest_source = dirname(dirname(dirname(__FILE__))) . '/public/manifest.json';
        $manifest_dest = ABSPATH . 'manifest.json';
        
        if (!file_exists($manifest_source)) {
            error_log('CHE: manifest.json no encontrado en: ' . $manifest_source);
            return;
        }
        
        // Leer el contenido del manifest.json del plugin
        $content = @file_get_contents($manifest_source);
        if ($content === false) {
            error_log('CHE: Error al leer manifest.json desde el plugin');
            return;
        }
        
        // Crear el archivo en la raíz si no existe o si está desactualizado
        $needs_update = true;
        if (file_exists($manifest_dest)) {
            $current_content = @file_get_contents($manifest_dest);
            if ($current_content === $content) {
                $needs_update = false;
            }
        }
        
        if ($needs_update) {
            $result = @file_put_contents($manifest_dest, $content);
            if ($result !== false) {
                error_log('CHE: manifest.json copiado a la raíz del sitio para PWA');
            } else {
                error_log('CHE: Error al copiar manifest.json - Verifica permisos de escritura en ' . ABSPATH);
            }
        }
    }
    
    /**
     * Crea el OneSignalSDKWorker.js en la raíz del sitio
     * Este archivo es necesario para que OneSignal funcione correctamente
     */
    private static function copy_service_worker() {
        // Crear OneSignalSDKWorker.js en la raíz de WordPress (ABSPATH)
        $onesignal_worker = ABSPATH . 'OneSignalSDKWorker.js';
        
        // Contenido del Service Worker de OneSignal
        $content = 'importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");' . "\n";
        
        // Crear el archivo si no existe o si está desactualizado
        $needs_update = true;
        if (file_exists($onesignal_worker)) {
            $current_content = @file_get_contents($onesignal_worker);
            if ($current_content === $content) {
                $needs_update = false;
            }
        }
        
        if ($needs_update) {
            $result = @file_put_contents($onesignal_worker, $content);
            if ($result !== false) {
                error_log('CHE: OneSignalSDKWorker.js creado/actualizado en la raíz del sitio');
            } else {
                error_log('CHE: Error al crear OneSignalSDKWorker.js - Verifica permisos de escritura en ' . ABSPATH);
            }
        }
    }

    private static function register_roles() {

        if ( ! get_role( 'worker' ) ) {
            add_role( 'worker', 'Trabajador', [
                'read'                   => true,
                'upload_files'           => true,
                'che_view_own_parts'     => true,
                'che_start_finish_part'  => true,
                'che_request_holidays'   => true,
            ] );
            error_log( 'CHE: Rol worker creado' );
        }

        // Obtener todas las capacidades del rol administrator
        $admin_role = get_role( 'administrator' );
        $admin_capabilities = $admin_role ? $admin_role->capabilities : [];

        if ( ! get_role( 'admin' ) ) {
            // Crear el rol con las capacidades de administrator más las personalizadas
            $admin_caps = array_merge( $admin_capabilities, [
                'manage_che_users'     => true,
                'validate_che_parts'   => true,
                'manage_che_holidays'  => true,
                'manage_che_festivos'  => true,
            ] );
            add_role( 'admin', 'Admin Horario', $admin_caps );
            error_log( 'CHE: Rol admin creado con capacidades de administrator' );
        } else {
            // Si ya existe, actualizar sus capacidades
            $role = get_role( 'admin' );
            foreach ( $admin_capabilities as $cap => $grant ) {
                $role->add_cap( $cap, $grant );
            }
        }

        if ( ! get_role( 'super_admin' ) ) {
            // Crear el rol con las capacidades de administrator más las personalizadas
            $super_admin_caps = array_merge( $admin_capabilities, [
                'manage_che_users'      => true,
                'validate_che_parts'    => true,
                'manage_che_holidays'   => true,
                'manage_che_festivos'   => true,
                'manage_che_economics'  => true,
            ] );
            add_role( 'super_admin', 'Super Admin Horario', $super_admin_caps );
            error_log( 'CHE: Rol super_admin creado con capacidades de administrator' );
        } else {
            // Si ya existe, actualizar sus capacidades
            $role = get_role( 'super_admin' );
            foreach ( $admin_capabilities as $cap => $grant ) {
                $role->add_cap( $cap, $grant );
            }
        }
    }
}
