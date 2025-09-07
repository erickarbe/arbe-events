<?php
/**
 * Uninstall script for Arbe Events plugin.
 * 
 * Removes all plugin data when the plugin is deleted.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'ae_registrations';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

$options_to_delete = [
    'ae_db_version',
    'ae_plugin_version',
    'ae_activation_time',
    'ae_settings',
    'ae_email_templates',
    'ae_events_page',
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}

$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ae_%'");

$events = get_posts([
    'post_type' => 'event',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($events as $event) {
    wp_delete_post($event->ID, true);
}

wp_clear_scheduled_hook('ae_daily_cleanup');
wp_clear_scheduled_hook('ae_send_event_reminders');

wp_cache_flush();