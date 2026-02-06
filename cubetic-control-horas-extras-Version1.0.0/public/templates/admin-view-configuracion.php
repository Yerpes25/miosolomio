<?php
if (!defined('ABSPATH')) exit;
?>

<div class="che-admin-section" data-section="configuracion">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Configuración</h3>
    </div>
    <p class="che-section-description">Administra los datos y configuración de tu empresa</p>

    <?php
    $empresa_nombre = get_option('che_empresa_nombre', get_bloginfo('name'));
    $empresa_email = get_option('che_empresa_email', get_option('admin_email'));
    $empresa_telefono = get_option('che_empresa_telefono', '');
    $empresa_direccion = get_option('che_empresa_direccion', '');
    $empresa_cif = get_option('che_empresa_cif', '');
    $logo_url = get_option('che_empresa_logo', '');
    $logo_id = get_option('che_empresa_logo_id', '');
    // Usar la URL directa del logo si existe, sino mostrar placeholder
    $logo_display = $logo_url ? esc_url($logo_url) : '';
    ?>

    <div class="che-config-wrapper">
        <!-- Logo de la Empresa -->
        <div class="che-config-block">
            <h3>Logo de la Empresa</h3>
            <div class="che-logo-upload-box">
                <div class="che-logo-preview-box">
                    <?php if ($logo_display): ?>
                        <img id="che-logo-preview-img" src="<?php echo $logo_display; ?>" alt="Logo de la empresa" style="display: block;">
                    <?php endif; ?>
                </div>
                <div class="che-logo-buttons">
                    <input type="file" id="che-logo-input" accept="image/*" style="display:none;">
                    <button type="button" id="che-upload-logo-btn" class="button">
                        <span class="dashicons dashicons-upload"></span> <?php echo $logo_display ? 'Cambiar Logo' : 'Subir Logo'; ?>
                    </button>
                    <?php if ($logo_url): ?>
                    <button type="button" id="che-remove-logo-btn" class="button button-link-delete">
                        <span class="dashicons dashicons-trash"></span> Eliminar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Información General -->
        <form id="che-configuracion-form">
            <table class="che-admin-table">
                <tbody>
                    <tr>
                        <th>Nombre de la Empresa *</th>
                        <td><input type="text" name="empresa_nombre" class="che-input" value="<?php echo esc_attr($empresa_nombre); ?>" required></td>
                    </tr>
                    <tr>
                        <th>CIF/NIF</th>
                        <td><input type="text" name="empresa_cif" class="che-input" value="<?php echo esc_attr($empresa_cif); ?>"></td>
                    </tr>
                    <tr>
                        <th>Email de Contacto *</th>
                        <td><input type="email" name="empresa_email" class="che-input" value="<?php echo esc_attr($empresa_email); ?>" required></td>
                    </tr>
                    <tr>
                        <th>Teléfono</th>
                        <td><input type="tel" name="empresa_telefono" class="che-input" value="<?php echo esc_attr($empresa_telefono); ?>"></td>
                    </tr>
                    <tr>
                        <th>Dirección</th>
                        <td><textarea name="empresa_direccion" class="che-input" rows="3"><?php echo esc_textarea($empresa_direccion); ?></textarea></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="che-form-actions">
                <button type="submit" class="all-btn primary" id="che-save-config-btn">
                    <span class="dashicons dashicons-yes"></span> Guardar Configuración
                </button>
            </div>
        </form>
    </div>
</div>
