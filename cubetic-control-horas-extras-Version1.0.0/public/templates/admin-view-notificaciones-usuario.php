<?php
if (!defined('ABSPATH')) exit;
?>

<div class="che-admin-section" data-section="notificaciones-usuario" style="display:none;">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Notificaciones</h3>
    </div>
    <p class="che-section-description">Notificaciones recibidas sobre validaciones, cambios y actualizaciones del sistema.</p>

    <div class="che-filter-header">
        <div class="tablenav top">
            <div class="alignleft actions">
                <button class="button action che-filter-btn active" data-filter="todas">Todas</button>
                <button class="button action che-filter-btn" data-filter="no-revisada">No Revisadas</button>
                <button class="button action che-filter-btn" data-filter="revisada">Revisadas</button>
            </div>
            <br class="clear">
        </div>
    </div>

    <div id="che-notifications-container">
        <div class="che-empty-state">
            <p>🔔 Cargando notificaciones...</p>
        </div>
    </div>
</div>
