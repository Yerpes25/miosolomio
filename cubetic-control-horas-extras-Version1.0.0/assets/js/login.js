/**
 * Archivo js de  login
 */
jQuery(document).ready(function ($) {
    $('#che-login-form').on('submit', function (e) {
        e.preventDefault();

        const formData = {
            action: 'che_worker_login',
            nonce: cheAuth.che_auth_nonce,
            username: $('#username').val(),
            password: $('#password').val()
        };

        $.ajax({
            url: cheAuth.ajaxurl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {

                if (response && response.success) {
                   // Mostrar mensaje de éxito
                    $('#login-message')
                        .text('¡Acceso exitoso! Redirigiendo...')
                        .addClass('success')
                        .show();
                        
                    
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        console.error('No se encontró redirect_url en la respuesta');
                        $('#login-message')
                            .text('Error: No se pudo obtener la URL de redirección.')
                            .removeClass('success')
                            .addClass('error')
                            .show();
                    }
                } else {
                    $('#login-message')
                        .text(response && response.data ? response.data : 'Error inesperado en login.')
                        .removeClass('success')
                        .addClass('error')
                        .show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error en AJAX:', textStatus, errorThrown);
                console.error('Respuesta del servidor:', jqXHR.responseText);
                $('#login-message')
                    .text('Error de conexión. Intenta de nuevo.')
                    .removeClass('success')
                    .addClass('error')
                    .show();
            }
        });
    });
}); 