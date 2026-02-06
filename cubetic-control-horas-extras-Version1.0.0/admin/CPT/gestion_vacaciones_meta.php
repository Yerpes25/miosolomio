<?php
namespace ControlHoras\Admin\CPT;

if ( ! defined( 'ABSPATH' ) ) exit;

class VacacionesMeta {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_che_vacacion', [ __CLASS__, 'save_meta' ] );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'che_vacacion_datos',
            __( 'Datos de la solicitud', 'gestion-he' ),
            [ __CLASS__, 'render_meta_box' ],
            'che_vacacion',
            'normal',
            'default'
        );
    }

    public static function render_meta_box( $post ) {
        $trabajador_id = get_post_meta( $post->ID, 'trabajador_id', true );
        $fecha_inicio  = get_post_meta( $post->ID, 'fecha_inicio', true );
        $fecha_fin     = get_post_meta( $post->ID, 'fecha_fin', true );
        $dias_totales  = get_post_meta( $post->ID, 'dias_totales', true );
        $estado        = get_post_meta( $post->ID, 'estado', true ) ?: 'pendiente';

        ?>
        <p><strong>Trabajador ID:</strong> <?php echo esc_html( $trabajador_id ); ?></p>
        <p>
            <label>Fecha inicio:</label><br>
            <input type="date" name="che_vacacion_fecha_inicio" value="<?php echo esc_attr( $fecha_inicio ); ?>">
        </p>
        <p>
            <label>Fecha fin:</label><br>
            <input type="date" name="che_vacacion_fecha_fin" value="<?php echo esc_attr( $fecha_fin ); ?>">
        </p>
        <p>
            <label>Días totales:</label><br>
            <input type="number" name="che_vacacion_dias_totales" value="<?php echo esc_attr( $dias_totales ); ?>" min="1">
        </p>
        <p>
            <label>Estado:</label><br>
            <select name="che_vacacion_estado">
                <option value="pendiente" <?php selected( $estado, 'pendiente' ); ?>>Pendiente</option>
                <option value="aprobado"  <?php selected( $estado, 'aprobado' );  ?>>Aprobado</option>
                <option value="rechazado" <?php selected( $estado, 'rechazado' ); ?>>Rechazado</option>
            </select>
        </p>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        if ( isset( $_POST['che_vacacion_fecha_inicio'] ) ) {
            update_post_meta( $post_id, 'fecha_inicio', sanitize_text_field( $_POST['che_vacacion_fecha_inicio'] ) );
        }
        if ( isset( $_POST['che_vacacion_fecha_fin'] ) ) {
            update_post_meta( $post_id, 'fecha_fin', sanitize_text_field( $_POST['che_vacacion_fecha_fin'] ) );
        }
        if ( isset( $_POST['che_vacacion_dias_totales'] ) ) {
            update_post_meta( $post_id, 'dias_totales', (int) $_POST['che_vacacion_dias_totales'] );
        }
        if ( isset( $_POST['che_vacacion_estado'] ) ) {
            update_post_meta( $post_id, 'estado', sanitize_text_field( $_POST['che_vacacion_estado'] ) );
        }
    }
}
