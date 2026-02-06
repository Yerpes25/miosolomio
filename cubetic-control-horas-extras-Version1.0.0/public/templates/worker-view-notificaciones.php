<?php
if (!defined('ABSPATH')) exit;
?>

<div class="che-worker-section" data-section="notificaciones" style="display:none;">
    <div class="che-section-header">
        <h2 class="che-section-title">
            <span class="dashicons dashicons-bell"></span>
            Notificaciones
        </h2>
    </div>

    <div class="che-section-content">
        <!-- FILTROS -->
        <div class="che-notificaciones-filters">
            <div class="che-notificaciones-filters-inner">
                <button class="che-filter-btn active" data-filter="todas">
                    Todas
                </button>
                <button class="che-filter-btn" data-filter="no-revisada">
                    No Revisadas
                </button>
                <button class="che-filter-btn" data-filter="revisada">
                    Revisadas
                </button>
            </div>
        </div>

                <!-- LISTA DE NOTIFICACIONES (contenido dinámico) -->
        <div class="che-notificaciones-wrapper">
            <div class="che-notificaciones-loading">
                Cargando notificaciones...
            </div>

            <!-- NUEVO contenedor para las cards -->
            <div class="che-notificaciones-list" style="display:none;">
                <!-- Cards insertadas por JS -->
            </div>

            <div class="che-notificaciones-empty" style="display:none;">
                <div class="che-notificaciones-empty-icon">🔔</div>
                <p>No tienes notificaciones</p>
            </div>
        </div>
    </div>
</div>
