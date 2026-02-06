<?php
if (!defined('ABSPATH')) exit;
?>

<div class="che-admin-section" data-section="registros">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Registro de Actividades</h3>
    </div>
    <p class="che-section-description">Historial completo de acciones: partes, vacaciones, usuarios, sedes y débitos aplicados.</p>

        <!-- Filtros -->
        <div class="che-filter-header">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <label for="che-registros-filter-activity" class="screen-reader-text">Tipo de Actividad</label>
                    <select id="che-registros-filter-activity" class="che-input">
                        <option value="">Todas las actividades</option>
                    </select>

                    <label for="che-registros-filter-user" class="screen-reader-text">Responsable</label>
                    <select id="che-registros-filter-user" class="che-input">
                        <option value="">Todos los usuarios</option>
                    </select>
                </div>
                
                <div class="alignright actions" style="margin-left: auto !important;">
                    <button type="button" id="che-registros-refresh-btn" class="button action">
                        <span class="dashicons dashicons-update"></span> Refrescar
                    </button>
                    <button type="button" id="che-registros-exportar" class="button action">Exportar</button>
                </div>
                
                <br class="clear">
            </div>
        </div>

        <!-- Tabla de registros -->
        <table id="che-registros-table" class="che-admin-table">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Usuario</th>
                    <th>Tipo de Actividad</th>
                    <th>Descripción</th>
                    <th>Objeto Afectado</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody id="che-registros-tbody">
                <tr class="che-loading-row">
                    <td colspan="6" style="text-align: center;">Cargando registros...</td>
                </tr>
            </tbody>
        </table>

        <!-- Paginación -->
        <div id="che-registros-pagination" class="che-pagination"></div>

        <!-- Mensajes -->
        <div id="che-registros-message" class="che-message" style="display: none;"></div>

    </div>
</div>


