<?php
/**
 * Configuración de OneSignal
 * 
 * IMPORTANTE: Este archivo contiene credenciales sensibles.
 * No subir este archivo a repositorios públicos.
 * 
 * Para producción:
 * 1. Crea una cuenta en https://onesignal.com
 * 2. Crea una nueva App
 * 3. Obtén el App ID y REST API Key desde el dashboard
 * 4. Completa los valores abajo
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    // App ID de OneSignal (encuéntralo en Settings > Keys & IDs)
    'app_id' => '1903f3a5-c349-48e0-90d1-c8513b7571cc',
    
    // REST API Key de OneSignal (encuéntralo en Settings > Keys & IDs)
    'rest_api_key' => 'os_v2_app_deb7hjodjfeobegrzbitw5lrzs4ji7ndf2xel2uf2twr6q2dzabzcxirk7yiab3pg22u6yzybxohpc4qpphi3ty4jcfhwkiz7bkz4fy',
];
