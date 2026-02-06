<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- Sección SEDES -->
<div class="che-admin-section" data-section="sedes">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Gestión de Empresas</h3>
    </div>
    <p class="che-section-description">Crea, edita y administra las empresas donde trabajan tus empleados.</p>

    <div class="che-admin-sedes-toolbar">
        <div class="div-newsede">
            <button type="button" id="che-admin-sede-new-btn" class="all-btn back">Crear nueva empresa</button>
        </div>
    </div>

    <div id="che-admin-sedes-body" class="che-admin-sedes-grid">
        <!-- JS pintará aquí las cards de sedes -->
    </div>


            <!-- Detalle de sede -->
            <div id="che-admin-sede-detalle-wrapper" style="display:none;">
                <div style="margin-bottom: 1rem;">
                    <button type="button" id="che-admin-sede-detalle-back-btn" class="all-btn back">
                        ← Volver a empresas
                    </button>
                </div>
                <div class="che-admin-sede-detalle-top">  
                    <div class="che-admin-sede-detalle-kpis">
                        <div class="che-sede-card_header">
                        <h3 id="che-admin-sede-detalle-title">Información de la empresa</h3>
                        </div>
                        <div class="che-sede-card_body">
                        <p><strong>Trabajadores asignados:</strong> <span id="che-admin-sede-detalle-trabajadores">0</span></p>
                        <p><strong>Total partes:</strong> <span id="che-admin-sede-detalle-partes">0</span></p>
                        <p><strong>Total horas extras:</strong> <span id="che-admin-sede-detalle-horas">0</span></p>
                        </div>
                    </div>
                    <div class="che-sede-card--calendar">
                        <div class="che-sede-card__header">
                            <h3 class="che-sede-card__title">Calendario de festivos</h3>
                            <button type="button" id="che-sede-cal-toggle" class="all-btn success">
                                Añadir días
                            </button>
                        </div>
                        <div class="che-sede-card__body">
                            <div class="che-sede-calendar-header">
                                <button type="button" id="che-sede-cal-prev">&lt;</button>
                                <span id="che-sede-cal-title"></span>
                                <button type="button" id="che-sede-cal-next">&gt;</button>
                            </div>
                            <div class="che-sede-calendar-grid" id="che-sede-cal-grid">
                                    <!-- JS pintará el mes -->
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Contenedor de dos columnas -->
                <div class="che-admin-sede-two-columns">
                    <!-- Sección de trabajadores -->
                    <div class="che-admin-sede-column">
                        <div class="che-admin-sede-column-header">
                            <h3>Trabajadores</h3>
                            <input type="text" id="che-admin-sede-trabajadores-filter" class="che-filter-input" placeholder="Buscar trabajador...">
                        </div>
                        <div id="che-admin-sede-trabajadores-grid" class="che-trabajadores-list">
                            <!-- JS pintará las tarjetas de trabajadores aquí -->
                        </div>
                    </div>

                    <!-- Sección de partes -->
                    <div class="che-admin-sede-column">
                        <div class="che-admin-sede-column-header">
                            <h3>Partes de Trabajo</h3>
                            <select id="che-admin-sede-partes-filter" class="che-filter-select">
                                <option value="">Todos los trabajadores</option>
                            </select>
                        </div>
                        <div id="che-admin-sede-partes-grid" class="che-partes-list">
                            <!-- JS pintará las tarjetas de partes aquí -->
                        </div>
                    </div>

                    <!-- Seccion de vacaciones -->
                    <div class="che-admin-sede-column">
                        <div class="che-admin-sede-column-header">
                            <h3>Vacaciones</h3>
                            <select id="che-admin-sede-vacaciones-filter" class="che-filter-select">
                                <option value="">Todos los trabajadores</option>
                            </select>
                        </div>
                        <div id="che-admin-sede-vacaciones-grid" class="che-vacaciones-list">
                            <!-- JS pintará las tarjetas de vacaciones aquí -->
                        </div>
                </div>
            </div>

    <div id="che-admin-sede-workers-wrapper" style="display:none;"> <!-- margin-top:1.5rem; -->

        <div class="che-admin-sede-workers-header">
            <button type="button" id="che-admin-sede-workers-back-btn" class="all-btn">
                ← Volver a empresas
            </button>
            <h3 id="che-admin-sede-workers-title">Trabajadores de</h3>
        </div>

        <table class="che-admin-users-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody id="che-admin-sede-workers-body"></tbody>
        </table>
    </div>

</div>

<!-- Modal para crear sede (fuera de la sección para evitar overflow) -->
<div id="che-admin-create-sede-wrapper" class="che-modal">
    <div class="che-modal-content">
        <div class="che-modal-header">
            <h3 id="che-modal-sede-title">Nueva Empresa</h3>
            <button type="button" class="che-modal-close" id="che-admin-sede-close-btn">&times;</button>
        </div>

        <div class="che-modal-body">
            <form id="che-admin-create-sede" class="che-form">
                <div class="che-form-group">
                    <label for="sede-nombre">Nombre de la Empresa</label>
                    <input type="text" id="sede-nombre" name="nombre" class="che-input" placeholder="Ej. Central Úbeda" required>
                </div>

                <div class="che-form-group">
                    <label for="sede-direccion">Dirección completa</label>
                    <input type="text" id="sede-direccion" name="direccion" class="che-input" placeholder="Calle, número, planta...">
                </div>

                <div class="che-form-grid">
                    <div class="che-form-group">
                        <label for="sede-cp">Código postal</label>
                        <input type="text" id="sede-cp" name="cp" class="che-input" placeholder="23400">
                    </div>
                    <div class="che-form-group">
                        <label for="sede-telefono">Teléfono</label>
                        <input type="tel" id="sede-telefono" name="telefono" class="che-input" placeholder="600 000 000">
                    </div>
                </div>

                <div class="che-form-group">
                    <label for="sede-encargado">Encargado de la empresa</label>
                    <input type="text" id="sede-encargado" name="encargado" class="che-input" placeholder="Nombre del responsable">
                </div>
                <p id="che-admin-sede-message" class="che-message"></p>
            </form>
        </div>

        <div class="che-modal-footer">
            <button type="button" class="all-btn primary" id="che-admin-sede-cancel-btn">Cancelar</button>
            <button type="submit" form="che-admin-create-sede" class="all-btn success">
                Guardar sede
            </button>
        </div>
    </div>
</div>
