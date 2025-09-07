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

        ob_start();
        include AE_PLUGIN_PATH . 'templates/registration-form.php';
        return ob_get_clean();
    }
}