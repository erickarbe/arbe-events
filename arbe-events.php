<?php
/**
 * Plugin Name: Arbe Events
 * Description: Lightweight event registration plugin for WordPress by Arbé Digital.
 * Version: 1.0.0
 * Author: Arbé Digital
 * Text Domain: arbe-events
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Define constants
define('AE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AE_PLUGIN_VERSION', '1.0.0');
define('AE_PLUGIN_FILE', __FILE__);

// Load core files
require_once AE_PLUGIN_PATH . 'includes/class-activator.php';
require_once AE_PLUGIN_PATH . 'includes/class-database.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, ['AE_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['AE_Activator', 'deactivate']);

// Load plugin files
require_once AE_PLUGIN_PATH . 'includes/functions.php';
require_once AE_PLUGIN_PATH . 'includes/class-event-cpt.php';
require_once AE_PLUGIN_PATH . 'includes/class-registration-form.php';
require_once AE_PLUGIN_PATH . 'includes/class-settings-page.php';
require_once AE_PLUGIN_PATH . 'includes/class-shortcodes.php';

// Initialize plugin components
add_action('plugins_loaded', function () {
    load_plugin_textdomain('arbe-events', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    AE_Database::check_db_version();
    
    new AE_Event_CPT();
    new AE_Registration_Form();
    new AE_Settings_Page();
    new AE_Shortcodes();
    
    AE_Activator::schedule_events();
    
    if (is_admin()) {
        require_once AE_PLUGIN_PATH . 'includes/class-event-meta-boxes.php';
        require_once AE_PLUGIN_PATH . 'includes/class-registrations-admin.php';
        require_once AE_PLUGIN_PATH . 'includes/class-csv-exporter.php';
        
        new AE_Event_Meta_Boxes();
        new AE_Registrations_Admin();
    }
});