<?php
if (!defined('ABSPATH'))
    exit;
?>

<!-- Sección USUARIOS -->
<div class="che-admin-section" data-section="usuarios" style="display:none;">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Trabajadores</h3>
    </div>
    <p class="che-section-description">Gestiona los trabajadores del sistema: crear, editar, asignar roles y empresas.</p>
    <div id="che-admin-users-content">

        <div class="che-filter-header">
            <!-- Filtros estilo WordPress -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <label for="che-admin-user-search" class="screen-reader-text">Buscar trabajador</label>
                    <input type="text" id="che-admin-user-search" placeholder="DNI/NIE, nombre o email" class="che-search-input">

                    <label for="che-admin-user-sede-filter" class="screen-reader-text">Filtrar por sede</label>
                    <select id="che-admin-user-sede-filter" name="sede">
                        <option value="">Todas las sedes</option>
                        <?php $sedes = isset( $view_data['sedes'] ) ? $view_data['sedes'] : []; ?>
                        <?php if ( ! empty( $sedes ) ) : ?>
                            <?php foreach ( $sedes as $sid => $s ) : ?>
                                <option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $s['nombre'] ); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <label for="che-admin-user-role-filter" class="screen-reader-text">Filtrar por rol</label>
                    <select id="che-admin-user-role-filter" name="role">
                        <option value="">Todos los roles</option>
                        <option value="worker">Trabajador</option>
                        <option value="admin">Admin horario</option>
                        <option value="super_admin">Super Admin horario</option>
                    </select>
                </div>

                <div class="alignright actions" style="margin-left: auto !important; display: flex !important; justify-content: flex-end !important;">
                    <button type="button" id="che-admin-user-search-btn" class="button action" style="margin-right: 8px;">Buscar</button>
                    <button type="button" id="che-admin-user-new-btn" class="button action">Nuevo Trabajador</button>
                </div>

                <br class="clear">
            </div>
        </div>


        <!-- Listado de usuarios -->
        <table class="che-admin-table">
            <thead>
                <tr>
                    <th>Avatar</th>
                    <th>ID</th>
                    <th>DNI / NIE</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Empresa</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="che-admin-users-body">
                <!-- El JS rellenará aquí las filas -->
            </tbody>
        </table>

        <!-- Modal crear/editar trabajador - Reutiliza CSS del modal de tarifas -->
        <div id="che-admin-create-user-wrapper" class="che-modal">
            <div class="che-modal-content">
                <div class="che-modal-header">
                    <h3 id="che-modal-user-title">Nuevo Trabajador</h3>
                    <button type="button" class="che-modal-close" id="che-admin-user-close-btn">&times;</button>
                </div>

                <div class="che-modal-body">
                    <form id="che-admin-create-user" class="che-form">
                        
                        <div class="che-form-section">
                            <h4 class="che-form-subtitle">Datos de cuenta</h4>
                            <div class="che-form-grid">
                                <div class="che-form-group">
                                    <label for="dni_nie">DNI / NIE *</label>
                                    <input type="text" id="dni_nie" name="dni_nie" class="che-input" placeholder="12345678A o X1234567L" required maxlength="9">
                                    <small class="che-form-help">DNI: 8 números + letra. NIE: letra (X,Y,Z) + 7 números + letra</small>
                                </div>
                                <div class="che-form-group">
                                    <label for="user_email">Email</label>
                                    <input type="email" id="user_email" name="user_email" class="che-input" placeholder="usuario@ejemplo.com" required>
                                </div>
                                <div class="che-form-group">
                                    <label for="user_pass">Contraseña</label>
                                    <input type="password" id="user_pass" name="user_pass" class="che-input" placeholder="Dejar vacío para generar automáticamente">
                                    <small class="che-form-help">Si no indicas una, se generará automáticamente</small>
                                </div>
                            </div>
                        </div>

                        <div class="che-form-section">
                            <h4 class="che-form-subtitle">Información personal</h4>
                            <div class="che-form-grid">
                                <div class="che-form-group">
                                    <label for="first_name">Nombre</label>
                                    <input type="text" id="first_name" name="first_name" class="che-input" placeholder="Nombre">
                                </div>
                                <div class="che-form-group">
                                    <label for="last_name">Apellidos</label>
                                    <input type="text" id="last_name" name="last_name" class="che-input" placeholder="Apellidos">
                                </div>
                            </div>
                           <div class="che-form-group">
                                    <label for="user_phone">Teléfono</label>
                                    <input type="text" id="user_phone" name="user_phone" class="che-input" placeholder="Número de teléfono" required>
                                </div>
                                <div class="che-form-group">
                                    <label for="phone_number">Teléfono alternativo</label>
                                    <input type="text" id="phone_number" name="phone_number" class="che-input" placeholder="Número de teléfono">
                                </div>  
                        </div>

                        <div class="che-form-section">
                            <h4 class="che-form-subtitle">Asignación laboral</h4>
                            <div class="che-form-grid">
                                <div class="che-form-group">
                                    <label for="role">Rol de acceso</label>
                                    <select name="role" id="role" class="che-input">
                                        <option value="worker">Trabajador</option>
                                        <option value="admin">Admin horario</option>
                                        <?php
                                        //validamos si es super-adminpara quepueda crear otro super-admin
                                        $current_user = wp_get_current_user();
                                        if ( in_array( 'super_admin', (array) $current_user->roles, true ) ) : ?>   
                                        <option value="super_admin">Super Admin horario</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="che-form-group">
                                    <label for="che-admin-user-sede">Asignar sede</label>
                                    <select name="sede_trabajo" id="che-admin-user-sede" class="che-input" required>
                                        <option value="">Seleccionar sedes</option>
                                <?php $sedes = isset( $view_data['sedes'] ) ? $view_data['sedes'] : []; ?>
                                <?php if ( ! empty( $sedes ) ) : ?>
                                    <?php foreach ( $sedes as $sid => $s ) : ?>
                                        <option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $s['nombre'] ); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <p id="che-admin-user-message" class="che-message"></p>
                    </form>
                </div>

                <div class="che-modal-footer">
                    <button type="button" class="all-btn primary" id="che-admin-user-cancel-btn">Cancelar</button>
                    <button type="submit" form="che-admin-create-user" class="all-btn success">
                        Guardar usuario
                    </button>
                </div>
            </div>
        </div>
    </div>
 </div>
