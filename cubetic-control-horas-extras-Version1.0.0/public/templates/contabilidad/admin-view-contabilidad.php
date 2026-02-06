<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- Sección CONTABILIDAD -->
<div class="che-admin-section" data-section="contabilidad" style="display:none;">
    <div class="che-page-header">
        <h3 style="font-weight: 500; font-size: 1.3em;">Gestión de Contabilidad</h3>
    </div>
    <p class="che-section-description">Administra nóminas, tarifas, débitos y resúmenes contables de trabajadores por empresa.</p>

    <!-- Contenido principal del módulo -->
    <div id="che-contabilidad-content">
        
        <!-- Vista de tarjetas de empresas -->
        <div id="che-contabilidad-sedes-view">
            
            <!-- Filtro de búsqueda de empresas -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                <input type="text" id="che-contabilidad-buscar-sedes" class="che-input" placeholder="Buscar empresa..." style="width: 250px;">
            </div>

            <div id="che-contabilidad-sedes-grid" class="che-sedes-grid">
                <!-- JS pintará aquí las tarjetas de empresas -->
                <?php $sedes = isset( $view_data['sedes'] ) ? $view_data['sedes'] : []; ?>
                <?php if ( ! empty( $sedes ) ) : ?>
                    <?php foreach ( $sedes as $sede_id => $sede ) : ?>
                        <div class="che-contabilidad-sede-card" data-sede-id="<?php echo esc_attr( $sede_id ); ?>">
                            <div class="che-contabilidad-sede-card-header">
                                <h4><?php echo esc_html( $sede['nombre'] ); ?></h4>
                            </div>
                            <div class="che-contabilidad-sede-card-body">
                                <p>
                                    <strong>Trabajadores:</strong> 
                                    <span class="che-sede-workers-count">0</span>
                                </p>
                                <p>
                                    <strong>Previsiones de los gastos:</strong> 
                                    <span class="che-sede-budget-forecast">€ 0.00</span>
                                </p>
                            </div>
                            <div class="che-contabilidad-sede-card-footer">
                                <button type="button" class="all-btn primary che-contabilidad-view-workers-btn che-btn-compact che-btn-contabilidad" 
                                    data-sede-id="<?php echo esc_attr( $sede_id ); ?>">
                                    Ver trabajadores
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay empresas disponibles</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vista de tabla de desglose por trabajador (oculta hasta seleccionar empresa) -->
        <div id="che-contabilidad-workers-view" style="display:none;">
                <div class="che-contabilidad-workers-header">
                <button type="button" id="che-contabilidad-back-to-sedes" class="all-btn back che-btn-compact che-btn-contabilidad">
                    ← Volver
                </button>
                <h3 id="che-contabilidad-workers-title">Trabajadores de empresa</h3>
            </div>

            <!-- Filtros estilo WordPress -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <input type="text" id="che-contabilidad-buscar" class="che-input" placeholder="Buscar por nombre..." style="width: 200px;">
                        <input type="date" id="che-contabilidad-fecha-inicio" class="che-date-input" placeholder="Fecha inicio">
                        <input type="date" id="che-contabilidad-fecha-fin" class="che-date-input" placeholder="Fecha fin">
                        <input type="submit" id="che-contabilidad-aplicar-filtros" class="button action" value="Aplicar">
                    </div>
                </div>

                <div class="alignright actions">
                    <button type="button" id="che-contabilidad-exportar-asesoria" class="button action che-btn-compact che-btn-contabilidad">Exportar Asesoría</button>
                    <button type="button" id="che-contabilidad-exportar" class="button action che-btn-compact che-btn-contabilidad">Exportar</button>
                </div>
            </div>

            <!-- Tabla de trabajadores -->
            <table class="che-admin-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Trabajador</th>
                        <th style="text-align: center;">Nómina</th>
                        <th style="text-align: center;">IRPF</th>
                        <th style="text-align: center;">Neto</th>
                        <th style="text-align: center;">Horas Extras</th>
                        <th style="text-align: center;">Débito</th>
                        <th style="text-align: center;">Total</th>
                        <th style="text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="che-contabilidad-tbody">
                    <!-- Se rellenará por JavaScript -->
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Modal para configurar tarifas -->
<div id="che-modal-tarifas" class="che-modal" style="display:none;">
    <div class="che-modal-content">
        <div class="che-modal-header">
            <h3>Tarifas - <span id="che-modal-worker-name"></span></h3>
            <button type="button" class="che-modal-close">&times;</button>
        </div>

        <!-- Bloque Hora Semanal -->
        <div class="che-modal-body">
            <div class="che-form-group">
                <label for="che-tarifa-normal">Hora semanal (€/hora)</label>
                <input type="number" 
                       id="che-tarifa-normal" 
                       class="che-input" 
                       min="0" 
                       step="0.01" 
                       placeholder="10.00">
                <small>Tarifa para horas extras semanales</small>
            </div>

            <!-- Bloque Hora Fin de Semana -->
            <div class="che-form-group">
                <label for="che-tarifa-extra">Hora fin de semana  (€/hora)</label>
                <input type="number" 
                       id="che-tarifa-extra" 
                       class="che-input" 
                       min="0" 
                       step="0.01" 
                       placeholder="15.00">
                <small>Tarifa para horas extra en el fin de semana </small>
            </div>

            <!-- Bloque Hora Festivo -->
            <div class="che-form-group">
                <label for="che-tarifa-extra-festivo">Hora Festivo (€/hora)</label>
                <input type="number" 
                       id="che-tarifa-extra-festivo" 
                       class="che-input" 
                       min="0" 
                       step="0.01" 
                       placeholder="20.00">
                <small>Tarifa para todas las horas extras trabajadas en días festivos</small>
            </div>

            <!--Bloque Nómina Bruto -->
            <div class="che-form-group">
                <label for="che-nomina-total">Nómina Bruto(€/mes)</label>
                <input type="number" 
                       id="che-nomina-total" 
                       class="che-input" 
                       min="1" 
                       max="100000" 
                       step="0.01" 
                       placeholder="1000.00">
                <small>Nómina Bruto mensual a percibir</small>
            </div>
            <!-- Bloque de la retencion de IRPF en la nomina -->
            <div class="che-form-group">
                <label for="che-retencion-irpf">Retención IRPF (%)</label>
                <input type="number" 
                       id="che-retencion-irpf" 
                       class="che-input" 
                       min="0" 
                       max="100" 
                       step="0.01" 
                       placeholder="15.00">
                <small>Porcentaje de retención de IRPF aplicado en la nómina</small>
            </div>
        </div>
        <div class="che-modal-footer">
            <button type="button" class="all-btn primary" id="che-modal-cancelar">Cancelar</button>
            <button type="button" class="all-btn success" id="che-modal-guardar-tarifas">Guardar Tarifas</button>
        </div>
    </div>
</div>

<!-- Modal Débito -->
<div id="che-modal-debito" class="che-modal">
    <div class="che-modal-content" style="max-width: 400px;">
        <div class="che-modal-header">
            <h3>Débito - <span id="che-modal-debito-worker-name"></span></h3>
            <button type="button" class="che-modal-close">&times;</button>
        </div>
        <div class="che-modal-body">
            <p style="margin-bottom: 1rem; color: #666;">Ingresa el monto de débito para este trabajador.</p>
            <div class="che-form-group">
                <label for="che-debito-global-input">Débito (€)</label>
                <input type="number" 
                       id="che-debito-global-input" 
                       class="che-input" 
                       min="0" 
                       step="0.01" 
                       placeholder="0.00">
                <small>Monto adelantado al trabajador en el mes</small>
            </div>
        </div>
        <div class="che-modal-footer">
            <button type="button" class="all-btn primary" id="che-modal-debito-cancelar">Cancelar</button>
            <button type="button" class="all-btn success" id="che-modal-debito-aplicar">Aplicar</button>
        </div>
    </div>
</div>
