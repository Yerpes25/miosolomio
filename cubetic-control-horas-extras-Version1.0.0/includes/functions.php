<?php
// Login Ajax trabajador
/* add_action('wp_ajax_che_worker_login', 'che_worker_login_callback');
add_action('wp_ajax_nopriv_che_worker_login', 'che_worker_login_callback');

function che_worker_login_callback()
{
    // Debug desactivado

    // 1) Validar el nonce: misma acción 'che_worker_login' y campo 'nonce'
    // check_ajax_referer('che_worker_login', 'nonce');

    $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        wp_send_json_error('Debes introducir usuario y contraseña.');
    }

    $creds = [
        'user_login' => $username,
        'user_password' => $password,
        'remember' => true,
    ];

    $user = wp_signon($creds, false);

    if (is_wp_error($user)) {
        wp_send_json_error('Credenciales inválidas. Intenta de nuevo.');
    }

    wp_send_json_success([
        'redirect_url' => home_url('/trabajadores/panel/'),
    ]);
} */
