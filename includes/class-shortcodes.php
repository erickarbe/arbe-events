<?php
/**
 * Registers frontend shortcodes.
 */
class AE_Shortcodes {
    public function __construct() {
        add_shortcode('event_register', [$this, 'render_registration_form']);
    }

    public function render_registration_form($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $event_id = absint($atts['id']);

        if (!$event_id) return '';

        // Enqueue frontend form styles.
        wp_enqueue_style(
            'ae-frontend-form',
            AE_PLUGIN_URL . 'assets/css/frontend-form.css',
            [],
            AE_PLUGIN_VERSION
        );

        ob_start();
        include AE_PLUGIN_PATH . 'templates/registration-form.php';
        return ob_get_clean();
    }
}