<div class="che-admin-section" data-section="vacaciones" style="display:none;">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Gestión de vacaciones</h3>
    </div>
    <p class="che-section-description">Administra las solicitudes de vacaciones: aprobar, rechazar y consultar días disponibles.</p>

    <div class="che-filter-header">
        <div class="tablenav top">
            <div class="alignleft actions">
                <input type="text" id="che-admin-vacaciones-search" placeholder="Nombre o email" class="che-search-input">
                <button type="button" id="che-admin-vacaciones-search-btn" class="button action">Buscar</button>

                <select id="che-admin-vacaciones-sede" class="selects">
                    <?php 
                    $sedes = isset($view_data['sedes']) ? $view_data['sedes'] : [];
                    if ( empty( $sedes ) ) : 
                    ?>
                        <option value="">No hay sedes disponibles</option>
                    <?php else : ?>
                        <option value="">Todas las sedes</option>   
                        <?php foreach ( $sedes as $sede_id => $sede ) : ?>
                            <option value="<?php echo esc_attr( $sede_id ); ?>">
                                <?php echo esc_html( $sede['nombre'] ); ?>
                            </option>   
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <select id="che-admin-vacaciones-estado-filter" class="selects">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="rechazado">Rechazado</option>
                </select>
            </div>
            
            <div class="alignright actions" style="margin-left: auto !important;">
                <button type="button" id="che-vacaciones-refresh-btn" class="button button-primary che-btn-compact che-btn-contabilidad" title="Refrescar">
                    <span class="dashicons dashicons-update"></span>Refrescar
                </button>
                <button type="button" id="che-vacaciones-exportar" class="button button-primary che-btn-compact che-btn-contabilidad" title="Exportar">
                    <span class="dashicons dashicons-download"></span>Exportar
                </button>
            </div>
            
            <br class="clear">
        </div>
    </div>

    <p id="che-admin-vacaciones-message" class="che-message"></p>

    <table class="che-admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Trabajador</th>
                <th>Empresa</th>
                <th>Fecha inicio</th>
                <th>Fecha fin</th>
                <th>Días totales</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="che-admin-vacaciones-body">
            <!-- El JS rellenará aquí las filas -->
        </tbody>
    </table>

    <!-- Modal para rechazar vacaciones con comentario -->
    <div id="che-modal-rechazar-vacacion" class="che-modal" style="display: none;">
        <div class="che-modal-content">
            <div class="che-modal-header">
                <h3>Rechazar Solicitud de Vacaciones</h3>
                <button type="button" class="che-modal-close">&times;</button>
            </div>

            <div class="che-modal-body">
                <div class="che-form-group">
                    <label for="che-vacacion-comentario-rechazo">Motivo del rechazo</label>
                    <textarea id="che-vacacion-comentario-rechazo" 
                              class="che-input" 
                              rows="4" 
                              placeholder="Escribe el motivo del rechazo..."
                              style="resize: vertical; min-height: 100px;"></textarea>
                    <small>Este comentario será enviado al trabajador en la notificación</small>
                </div>
            </div>

            <div class="che-modal-footer">
                <button type="button" id="che-modal-rechazar-cancelar" class="che-btn che-btn-secondary">Cancelar</button>
                <button type="button" id="che-modal-rechazar-confirmar" class="che-btn che-btn-danger">Rechazar</button>
            </div>
        </div>
    </div>

</div>