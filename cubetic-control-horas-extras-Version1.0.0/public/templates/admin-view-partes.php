<!-- Sección PARTES -->
<div class="che-admin-section" data-section="partes">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Gestión de partes de horas extra</h3>
    </div>
    <p class="che-section-description">Supervisa, valida o rechaza los partes de horas extra registrados por los trabajadores.</p>
    <div class="che-filter-header">
        <div class="tablenav top">
            <div class="alignleft actions">
                <input type="text" id="che-admin-parts-search" placeholder="Nombre o email" class="che-search-input">
                <button type="button" id="che-admin-parts-search-btn" class="button action">Buscar</button>

                <select id="che-admin-sede-filter" class="selects">
                    <?php
                    $sedes = isset($view_data['sedes']) ? $view_data['sedes'] : [];
                    if (empty($sedes)):
                        ?>
                        <option value="">No hay empresas disponibles</option>
                    <?php else: ?>
                        <option value="">Todas</option>
                        <?php foreach ($sedes as $sede_id => $sede): ?>
                            <option value="<?php echo esc_attr($sede_id); ?>">
                                <?php echo esc_html($sede['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <select id="che-admin-parts-estado-filter" class="selects">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="validado">Validado</option>
                    <option value="rechazado">Rechazado</option>
                </select>
            </div>
            
            <div class="alignright actions" style="margin-left: auto !important;">
                <button type="button" id="che-partes-refresh-btn" class="button button-primary che-btn-compact che-btn-contabilidad" title="Refrescar">
                    <span class="dashicons dashicons-update"></span>Refrescar
                </button>
                <button type="button" id="che-partes-exportar" class="button button-primary che-btn-compact che-btn-contabilidad" title="Exportar">
                    <span class="dashicons dashicons-download"></span>Exportar
                </button>
            </div>
            
            <br class="clear">
        </div>
    </div>

    <!-- Tabla de partes visible desde el inicio -->
    <div id="che-admin-partes-wrapper" style="margin-top: 1.5rem;">
        <table class="che-admin-table che-admin-partes-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Trabajador</th>
                    <th>Empresa</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Tiempo total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="che-admin-partes-tbody">
                <!-- Aquí se rellenará por JS -->
            </tbody>
        </table>
    </div>

    <!-- Modal para ver detalles del parte -->
    <div id="che-modal-detalle-parte" class="che-modal" style="display: none;">
        <div class="che-modal-content" style="max-width: 800px;">
            <div class="che-modal-header">
                <h3 id="che-modal-detalle-parte-title">Detalles del Parte</h3>
                <button type="button" class="che-modal-close">&times;</button>
            </div>

            <div class="che-modal-body" id="che-modal-detalle-parte-body">
                <!-- Aquí se rellenará con JS -->
            </div>

            <div class="che-modal-footer">
                <button type="button" class="che-btn che-btn-secondary che-modal-detalle-parte-cerrar">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal para rechazar parte con motivo -->
    <div id="che-modal-rechazar-parte" class="che-modal" style="display: none;">
        <div class="che-modal-content">
            <div class="che-modal-header">
                <h3>Rechazar Parte de Trabajo</h3>
                <button type="button" class="che-modal-close">&times;</button>
            </div>

            <div class="che-modal-body">
                <div class="che-form-group">
                    <label for="che-parte-comentario-rechazo">Motivo del rechazo</label>
                    <textarea id="che-parte-comentario-rechazo" 
                              class="che-input" 
                              rows="4" 
                              placeholder="Escribe el motivo del rechazo..."
                              style="resize: vertical; min-height: 100px;"></textarea>
                    <small>Este comentario será enviado al trabajador en la notificación</small>
                </div>
            </div>

            <div class="che-modal-footer">
                <button type="button" id="che-modal-rechazar-parte-cancelar" class="che-btn che-btn-secondary">Cancelar</button>
                <button type="button" id="che-modal-rechazar-parte-confirmar" class="che-btn che-btn-danger">Rechazar</button>
            </div>
        </div>
    </div>

</div>