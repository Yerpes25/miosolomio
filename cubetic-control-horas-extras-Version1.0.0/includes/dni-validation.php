<?php
/**
 * Funciones de validación y manejo de DNI/NIE
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Valida un DNI español
 * Formato: 8 dígitos + 1 letra (ej: 12345678A)
 * 
 * @param string $dni DNI a validar
 * @return bool True si es válido, false si no
 */
function che_validate_dni($dni) {
    $dni = strtoupper(trim($dni));
    
    // Verificar formato: 8 números y 1 letra
    if (!preg_match('/^[0-9]{8}[A-Z]$/', $dni)) {
        return false;
    }
    
    // Extraer número y letra
    $numero = substr($dni, 0, 8);
    $letra = substr($dni, 8, 1);
    
    // Letras válidas según el algoritmo del DNI
    $letras_validas = 'TRWAGMYFPDXBNJZSQVHLCKE';
    $letra_calculada = $letras_validas[$numero % 23];
    
    return $letra === $letra_calculada;
}

/**
 * Valida un NIE español
 * Formato: 1 letra (X, Y, Z) + 7 dígitos + 1 letra (ej: X1234567L)
 * 
 * @param string $nie NIE a validar
 * @return bool True si es válido, false si no
 */
function che_validate_nie($nie) {
    $nie = strtoupper(trim($nie));
    
    // Verificar formato: letra inicial + 7 números + letra final
    if (!preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $nie)) {
        return false;
    }
    
    // Convertir la primera letra a número para el cálculo
    $primera_letra = substr($nie, 0, 1);
    $numero = substr($nie, 1, 7);
    $letra = substr($nie, 8, 1);
    
    // Reemplazar letra inicial por número equivalente
    $reemplazos = ['X' => '0', 'Y' => '1', 'Z' => '2'];
    $numero_completo = $reemplazos[$primera_letra] . $numero;
    
    // Letras válidas según el algoritmo
    $letras_validas = 'TRWAGMYFPDXBNJZSQVHLCKE';
    $letra_calculada = $letras_validas[$numero_completo % 23];
    
    return $letra === $letra_calculada;
}

/**
 * Valida DNI o NIE indistintamente
 * 
 * @param string $documento DNI o NIE a validar
 * @return bool True si es válido (DNI o NIE), false si no
 */
function che_validate_dni_nie($documento) {
    $documento = strtoupper(trim($documento));
    
    // Intentar validar como NIE primero (empieza con X, Y, Z)
    if (preg_match('/^[XYZ]/', $documento)) {
        return che_validate_nie($documento);
    }
    
    // Si no empieza con X, Y, Z, validar como DNI
    return che_validate_dni($documento);
}

/**
 * Normaliza un DNI/NIE (elimina espacios, guiones, convierte a mayúsculas)
 * 
 * @param string $documento DNI o NIE a normalizar
 * @return string DNI/NIE normalizado
 */
function che_normalize_dni_nie($documento) {
    // Eliminar espacios, guiones y puntos
    $documento = str_replace([' ', '-', '.'], '', $documento);
    // Convertir a mayúsculas
    return strtoupper(trim($documento));
}

/**
 * Sanitiza un DNI/NIE para almacenamiento
 * 
 * @param string $documento DNI o NIE
 * @return string DNI/NIE sanitizado y normalizado
 */
function che_sanitize_dni_nie($documento) {
    $documento = che_normalize_dni_nie($documento);
    // Permitir solo caracteres válidos
    return preg_replace('/[^0-9A-Z]/', '', $documento);
}

/**
 * Busca un usuario por DNI/NIE
 * 
 * @param string $dni_nie DNI o NIE del usuario
 * @return WP_User|false Usuario encontrado o false si no existe
 */
function che_get_user_by_dni_nie($dni_nie) {
    $dni_nie = che_sanitize_dni_nie($dni_nie);
    
    // Buscar por meta_key 'dni_nie'
    $users = get_users([
        'meta_key' => 'dni_nie',
        'meta_value' => $dni_nie,
        'number' => 1,
    ]);
    
    if (!empty($users)) {
        return $users[0];
    }
    
    return false;
}

/**
 * Verifica si un DNI/NIE ya está registrado
 * 
 * @param string $dni_nie DNI o NIE a verificar
 * @param int $exclude_user_id ID de usuario a excluir de la búsqueda (para ediciones)
 * @return bool True si existe, false si no
 */
function che_dni_nie_exists($dni_nie, $exclude_user_id = 0) {
    $dni_nie = che_sanitize_dni_nie($dni_nie);
    
    $args = [
        'meta_key' => 'dni_nie',
        'meta_value' => $dni_nie,
        'number' => 1,
        'fields' => 'ID',
    ];
    
    if ($exclude_user_id > 0) {
        $args['exclude'] = [$exclude_user_id];
    }
    
    $users = get_users($args);
    
    return !empty($users);
}

/**
 * Formatea un DNI/NIE para mostrar (añade guión antes de la última letra)
 * 
 * @param string $documento DNI o NIE
 * @return string DNI/NIE formateado (ej: 12345678-A o X1234567-L)
 */
function che_format_dni_nie($documento) {
    $documento = che_normalize_dni_nie($documento);
    
    if (strlen($documento) < 2) {
        return $documento;
    }
    
    // Añadir guión antes de la última letra
    return substr($documento, 0, -1) . '-' . substr($documento, -1);
}
