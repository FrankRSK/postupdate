<?php
/*
Plugin Name: PostUpdate
Description: Aktualisiert das Veröffentlichungsdatum eines zufälligen Beitrags Y mal am Tag auf das aktuelle Datum, wenn der Beitrag länger als x Tage online ist.
Version: 1.3
*/

function postupdate_x_days_callback() {
    $x_days = get_option( 'postupdate_x_days', 30 );
    ?>
    <input type="number" name="postupdate_x_days" id="postupdate_x_days" min="1" value="<?php echo esc_attr( $x_days ); ?>">
    <?php
}

function postupdate_y_times_callback() {
    $y_times = get_option( 'postupdate_y_times', 1 );
    echo '<input type="number" name="postupdate_y_times" id="postupdate_y_times" min="1" max="24" value="' . esc_attr( $y_times ) . '">';
}

// Einmal täglich Cron-Job hinzufügen
function add_postupdate_cron_event() {
    if ( ! wp_next_scheduled( 'random_post_date_update_event' ) ) {
        wp_schedule_event( time(), 'daily', 'random_post_date_update_event' );
    }
}
add_action( 'wp', 'add_postupdate_cron_event' );

// Cron-Job für das nächste Update aktualisieren
function do_random_post_update() {
    // Einstellungen aus dem Adminmenü abrufen
    $x_days = get_option( 'postupdate_x_days', 30 ); // x Tage
    $y_times = get_option( 'postupdate_y_times', 1 ); // Y mal am Tag
    $update_interval = 24 / $y_times; // Aktualisierungsintervall in Stunden

    // Zeit bis zum nächsten Update berechnen
    $next_update_time = strtotime( 'today' ) + ( floor( ( time() - strtotime( 'today' ) ) / 3600 / $update_interval ) + 1 ) * 3600 * $update_interval;

    // Zufälligen Beitrag auswählen, der länger als x Tage online ist
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'orderby' => 'rand',
        'posts_per_page' => 1,
        'date_query' => array(
            array(
                'before' => '-' . $x_days . ' days',
                'column' => 'post_date',
            ),
        ),
    );
    $random_post = new WP_Query( $args );

    if ( $random_post->have_posts() ) {
        while ( $random_post->have_posts() ) {
            $random_post->the_post();

            // Das Veröffentlichungsdatum des Beitrags aktualisieren
            $post_id = get_the_ID();
            $post_data = array(
                'ID' => $post_id,
                'post_date' => current_time('mysql'),
                'post_date_gmt' => current_time('mysql', 1),
            );
            wp_update_post( $post_data );
        }
    }

    // Reset Postdata
    wp_reset_postdata();

    // Cron-Job für das nächste Update aktualisieren
    wp_schedule_single_event( $next_update_time, 'random_post_date_update_event' );
}
add_action( 'random_post_date_update_event', 'do_random_post_update' );

// Einstellungen ins Adminmenü hinzufügen
function postupdate_settings_init() {
    add_settings_section( 'postupdate_settings_section', 'PostUpdate Einstellungen', '', 'postupdate_settings' );
    add_settings_field( 'postupdate_x_days', 'Anzahl der Tage', 'postupdate_x_days_callback', 'postupdate_settings', 'postupdate_settings_section' );
    add_settings_field( 'postupdate_y_times', 'Anzahl der Aktualisierungen pro Tag', 'postupdate_y_times_callback', 'postupdate_settings', 'postupdate_settings_section' );
    register_setting( 'postupdate_settings', 'postupdate_x_days' );
    register_setting( 'postupdate_settings', 'postupdate_y_times', array(
        'type' => 'integer',
        'sanitize_callback' => 'intval',
        'show_in_rest' => true,
        'default' => 1,
    ) );
}


add_action( 'admin_init', 'postupdate_settings_init' );

// Anleitung im Admin-Menü anzeigen
function postupdate_settings_page() {
    ?>
    <div class="wrap">
        <h1>PostUpdate Einstellungen</h1>
        <p>Mit dem PostUpdate-Plugin können Sie das Veröffentlichungsdatum Ihrer Blog-Beiträge automatisch aktualisieren, um sicherzustellen, dass ältere Beiträge immer wieder in den Vordergrund rücken.</p>
        <p>Um die Einstellungen für das Plugin anzupassen, verwenden Sie das Formular unten. Dabei stellt Anzahl der Tage ein, wieviele Tage ein Artikel schon online sein muss, damit er aktualisiert werden kann.</p>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'postupdate_settings' );
            do_settings_sections( 'postupdate_settings' );
            submit_button( 'Speichern' );
            ?>

        </form>
    </div>
    <?php
}


// Menüpunkt ins Admin-Menü hinzufügen
function postupdate_add_options_page() {
add_options_page( 'PostUpdate Einstellungen', 'PostUpdate', 'manage_options', 'postupdate_options', 'postupdate_settings_page' );
}
add_action( 'admin_menu', 'postupdate_add_options_page' );