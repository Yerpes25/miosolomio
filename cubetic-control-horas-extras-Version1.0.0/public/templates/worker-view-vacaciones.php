<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="che-worker-section" data-section="vacaciones" style="display:none;">
    <h2>🏖️ Mis vacaciones</h2>

    <div id="che-worker-vacaciones-wrapper">
        <p id="che-worker-vacaciones-message" class="che-message"></p>

        <div class="che-vacaciones-form">
            <h3>📆 Solicitar vacaciones</h3>
            <div class="che-vacaciones-range">
                <div class="che-vacaciones-rangoiniciofin">
                    <div class="che-vacaciones-iniciodiv">
                        <label for="che-vacaciones-inicio">Inicio</label>
                        <input type="date" id="che-vacaciones-inicio" class="che-vacaciones-fondofecha">
                    </div>
                    <div class="che-vacaciones-findiv">
                        <label for="che-vacaciones-fin">Fin</label>
                        <input type="date" id="che-vacaciones-fin" class="che-vacaciones-fondofecha">
                    </div>
                    <div class="che-boton-vacaciones">
                        <button class="all-btn primary" type="button" id="che-vacaciones-solicitar-btn">
                            Solicitar vacaciones
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="che-vacaciones-list">
            <h3>🕓 Solicitudes realizadas</h3>
            <div id="che-worker-vacaciones-list" class="che-vacaciones-cards">
                <!-- Tarjetas por JS -->
            </div>
        </div>
    </div>
</div>
