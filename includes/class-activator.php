<?php
/**
 * Activation and deactivation handler for Arbe Events plugin.
 */
class AE_Activator {
    
    /**
     * Plugin activation hook.
     * 
     * Creates database tables, sets default options, and flushes rewrite rules.
     */
    public static function activate() {
        require_once AE_PLUGIN_PATH . 'includes/class-database.php';
        AE_Database::create_tables();
        
        self::set_default_options();
        
        require_once AE_PLUGIN_PATH . 'includes/class-event-cpt.php';
        $cpt = new AE_Event_CPT();
        $cpt->register_event_post_type();
        
        flush_rewrite_rules();
        
        self::create_plugin_pages();
        
        update_option('ae_plugin_version', '1.0.0');
        update_option('ae_activation_time', current_time('timestamp'));
    }
    
    /**
     * Plugin deactivation hook.
     * 
     * Cleans up scheduled events and flushes rewrite rules.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('ae_daily_cleanup');
        
        wp_clear_scheduled_hook('ae_send_event_reminders');
        
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $default_options = [
            'ae_settings' => [
                'admin_email' => get_option('admin_email'),
                'enable_notifications' => 'yes',
                'enable_user_confirmation' => 'yes',
                'default_capacity' => 100,
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format'),
                'enable_recaptcha' => 'no',
                'recaptcha_site_key' => '',
                'recaptcha_secret_key' => '',
                'form_success_message' => __('Thank you for registering! You will receive a confirmation email shortly.', 'arbe-events'),
                'form_error_message' => __('Sorry, there was an error processing your registration. Please try again.', 'arbe-events'),
                'email_from_name' => get_bloginfo('name'),
                'email_from_address' => get_option('admin_email'),
            ],
            'ae_email_templates' => [
                'admin_notification_subject' => __('New Event Registration', 'arbe-events'),
                'admin_notification_body' => self::get_default_admin_email_template(),
                'user_confirmation_subject' => __('Registration Confirmation', 'arbe-events'),
                'user_confirmation_body' => self::get_default_user_email_template(),
                'reminder_subject' => __('Event Reminder', 'arbe-events'),
                'reminder_body' => self::get_default_reminder_template(),
            ]
        ];
        
        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
    }
    
    /**
     * Create default pages for the plugin.
     */
    private static function create_plugin_pages() {
        $pages = [
            'ae_events_page' => [
                'title' => __('Events', 'arbe-events'),
                'content' => '[event_list]',
                'slug' => 'events'
            ]
        ];
        
        foreach ($pages as $option_name => $page_data) {
            $page_id = get_option($option_name);
            
            if (!$page_id || !get_post($page_id)) {
                $page_id = wp_insert_post([
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $page_data['slug'],
                    'comment_status' => 'closed'
                ]);
                
                if ($page_id && !is_wp_error($page_id)) {
                    update_option($option_name, $page_id);
                }
            }
        }
    }
    
    /**
     * Get default admin email template.
     */
    private static function get_default_admin_email_template() {
        return __("A new registration has been received for your event.

Event: {event_title}
Name: {name}
Email: {email}
Phone: {phone}
Registration Date: {registration_date}

You can view all registrations in your WordPress admin panel.", 'arbe-events');
    }
    
    /**
     * Get default user confirmation email template.
     */
    private static function get_default_user_email_template() {
        return __("Dear {name},

Thank you for registering for {event_title}.

Event Details:
Date: {event_date}
Time: {event_time}
Location: {event_location}

We look forward to seeing you at the event!

If you need to cancel your registration, please contact us.

Best regards,
{site_name}", 'arbe-events');
    }
    
    /**
     * Get default reminder email template.
     */
    private static function get_default_reminder_template() {
        return __("Dear {name},

This is a reminder that you're registered for {event_title} tomorrow.

Event Details:
Date: {event_date}
Time: {event_time}
Location: {event_location}

We look forward to seeing you!

Best regards,
{site_name}", 'arbe-events');
    }
    
    /**
     * Schedule cron events.
     */
    public static function schedule_events() {
        if (!wp_next_scheduled('ae_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ae_daily_cleanup');
        }
        
        if (!wp_next_scheduled('ae_send_event_reminders')) {
            wp_schedule_event(time(), 'daily', 'ae_send_event_reminders');
        }
    }
}