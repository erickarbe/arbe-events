<?php
/**
 * Handles frontend form submissions for event registration.
 */
class AE_Registration_Form {
    
    private $db;
    
    public function __construct() {
        require_once AE_PLUGIN_PATH . 'includes/class-database.php';
        $this->db = new AE_Database();
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_ae_register', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_ae_register', [$this, 'handle_submission']);
        add_action('init', [$this, 'handle_ajax_registration']);
        
        add_action('wp_ajax_ae_check_capacity', [$this, 'ajax_check_capacity']);
        add_action('wp_ajax_nopriv_ae_check_capacity', [$this, 'ajax_check_capacity']);
    }

    public function enqueue_assets() {
        wp_enqueue_style('ae-form-css', AE_PLUGIN_URL . 'assets/css/form.css', [], AE_PLUGIN_VERSION);
        wp_enqueue_script('ae-form-js', AE_PLUGIN_URL . 'assets/js/form.js', ['jquery'], AE_PLUGIN_VERSION, true);
        
        wp_localize_script('ae-form-js', 'ae_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ae_ajax_nonce'),
            'messages' => [
                'processing' => __('Processing...', 'arbe-events'),
                'error' => __('An error occurred. Please try again.', 'arbe-events'),
                'duplicate' => __('You have already registered for this event.', 'arbe-events'),
                'capacity' => __('Sorry, this event is full.', 'arbe-events'),
                'closed' => __('Registration for this event is closed.', 'arbe-events'),
            ]
        ]);
    }
    
    public function handle_ajax_registration() {
        if (isset($_POST['ae_ajax_register']) && $_POST['ae_ajax_register'] === '1') {
            $this->handle_submission(true);
        }
    }

    public function handle_submission($is_ajax = false) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ae_register_nonce')) {
            if ($is_ajax) {
                wp_send_json_error(['message' => __('Security check failed', 'arbe-events')]);
            } else {
                wp_die(__('Security check failed', 'arbe-events'));
            }
        }

        $event_id = absint($_POST['event_id']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        $errors = [];
        
        if (empty($name)) {
            $errors[] = __('Name is required.', 'arbe-events');
        }
        
        if (empty($email) || !is_email($email)) {
            $errors[] = __('Valid email is required.', 'arbe-events');
        }
        
        if (empty($event_id) || get_post_type($event_id) !== 'event') {
            $errors[] = __('Invalid event.', 'arbe-events');
        }
        
        if (!empty($errors)) {
            if ($is_ajax) {
                wp_send_json_error(['message' => implode(' ', $errors)]);
            } else {
                wp_die(implode('<br>', $errors));
            }
        }
        
        $registration_enabled = get_post_meta($event_id, '_ae_registration_enabled', true);
        if ($registration_enabled === 'no') {
            $error = __('Registration is not enabled for this event.', 'arbe-events');
            if ($is_ajax) {
                wp_send_json_error(['message' => $error]);
            } else {
                wp_die($error);
            }
        }
        
        if ($this->db->is_email_registered($event_id, $email)) {
            $error = __('You have already registered for this event.', 'arbe-events');
            if ($is_ajax) {
                wp_send_json_error(['message' => $error]);
            } else {
                wp_redirect(add_query_arg('ae_error', 'duplicate', get_permalink($event_id)));
                exit;
            }
        }
        
        $capacity = get_post_meta($event_id, '_ae_event_capacity', true);
        $enable_waitlist = get_post_meta($event_id, '_ae_enable_waitlist', true);
        $status = 'confirmed';
        
        if ($capacity && $capacity > 0) {
            $current_count = $this->db->get_registration_count($event_id, 'confirmed');
            
            if ($current_count >= $capacity) {
                if ($enable_waitlist === 'yes') {
                    $status = 'waitlist';
                } else {
                    $error = __('Sorry, this event is full.', 'arbe-events');
                    if ($is_ajax) {
                        wp_send_json_error(['message' => $error]);
                    } else {
                        wp_redirect(add_query_arg('ae_error', 'full', get_permalink($event_id)));
                        exit;
                    }
                }
            }
        }
        
        $registration_data = [
            'event_id' => $event_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'status' => $status,
            'meta' => [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'registered_by' => is_user_logged_in() ? get_current_user_id() : 0,
            ]
        ];
        
        $registration_data = apply_filters('ae_registration_data', $registration_data, $event_id);
        
        do_action('ae_before_registration', $registration_data);
        
        $registration_id = $this->db->insert_registration($registration_data);
        
        if (!$registration_id) {
            $error = __('Failed to save registration. Please try again.', 'arbe-events');
            if ($is_ajax) {
                wp_send_json_error(['message' => $error]);
            } else {
                wp_die($error);
            }
        }
        
        do_action('ae_after_registration', $registration_id, $registration_data);
        
        $this->send_notifications($registration_id, $registration_data);
        
        if ($is_ajax) {
            $settings = get_option('ae_settings', []);
            $success_message = isset($settings['form_success_message']) 
                ? $settings['form_success_message'] 
                : __('Thank you for registering! You will receive a confirmation email shortly.', 'arbe-events');
            
            if ($status === 'waitlist') {
                $success_message = __('You have been added to the waitlist. We will notify you if a spot becomes available.', 'arbe-events');
            }
            
            wp_send_json_success([
                'message' => $success_message,
                'status' => $status,
                'registration_id' => $registration_id
            ]);
        } else {
            $redirect_args = ['ae_registered' => '1'];
            if ($status === 'waitlist') {
                $redirect_args['ae_status'] = 'waitlist';
            }
            wp_redirect(add_query_arg($redirect_args, get_permalink($event_id)));
            exit;
        }
    }
    
    /**
     * Send email notifications for new registration.
     */
    private function send_notifications($registration_id, $registration_data) {
        $registration = $this->db->get_registration($registration_id);
        if (!$registration) {
            return;
        }
        
        $event = get_post($registration->event_id);
        if (!$event) {
            return;
        }
        
        $settings = get_option('ae_settings', []);
        $email_templates = get_option('ae_email_templates', []);
        
        $placeholders = $this->get_email_placeholders($registration, $event);
        
        if (isset($settings['enable_notifications']) && $settings['enable_notifications'] === 'yes') {
            $admin_email = isset($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
            $admin_subject = isset($email_templates['admin_notification_subject']) 
                ? $email_templates['admin_notification_subject'] 
                : __('New Event Registration', 'arbe-events');
            $admin_body = isset($email_templates['admin_notification_body']) 
                ? $email_templates['admin_notification_body'] 
                : $this->get_default_admin_email();
            
            $admin_subject = $this->replace_placeholders($admin_subject, $placeholders);
            $admin_body = $this->replace_placeholders($admin_body, $placeholders);
            
            wp_mail($admin_email, $admin_subject, $admin_body, $this->get_email_headers());
        }
        
        if (isset($settings['enable_user_confirmation']) && $settings['enable_user_confirmation'] === 'yes') {
            $user_subject = isset($email_templates['user_confirmation_subject']) 
                ? $email_templates['user_confirmation_subject'] 
                : __('Registration Confirmation', 'arbe-events');
            $user_body = isset($email_templates['user_confirmation_body']) 
                ? $email_templates['user_confirmation_body'] 
                : $this->get_default_user_email();
            
            $user_subject = $this->replace_placeholders($user_subject, $placeholders);
            $user_body = $this->replace_placeholders($user_body, $placeholders);
            
            wp_mail($registration->email, $user_subject, $user_body, $this->get_email_headers());
        }
        
        do_action('ae_email_sent', $registration_id, $registration_data);
    }
    
    /**
     * Get email placeholders for templates.
     */
    private function get_email_placeholders($registration, $event) {
        $start_date = get_post_meta($event->ID, '_ae_event_start_date', true);
        $start_time = get_post_meta($event->ID, '_ae_event_start_time', true);
        $venue = get_post_meta($event->ID, '_ae_event_venue', true);
        $address = get_post_meta($event->ID, '_ae_event_address', true);
        $city = get_post_meta($event->ID, '_ae_event_city', true);
        $state = get_post_meta($event->ID, '_ae_event_state', true);
        $zip = get_post_meta($event->ID, '_ae_event_zip', true);
        
        $location_parts = array_filter([$venue, $address, $city, $state, $zip]);
        $location = implode(', ', $location_parts);
        
        return [
            '{name}' => $registration->name,
            '{email}' => $registration->email,
            '{phone}' => $registration->phone,
            '{event_title}' => $event->post_title,
            '{event_date}' => $start_date ? date_i18n(get_option('date_format'), strtotime($start_date)) : '',
            '{event_time}' => $start_time ? date_i18n(get_option('time_format'), strtotime($start_time)) : '',
            '{event_location}' => $location,
            '{registration_date}' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->registration_date)),
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
        ];
    }
    
    /**
     * Replace placeholders in email template.
     */
    private function replace_placeholders($template, $placeholders) {
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Get email headers.
     */
    private function get_email_headers() {
        $settings = get_option('ae_settings', []);
        $from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        $from_email = isset($settings['email_from_address']) ? $settings['email_from_address'] : get_option('admin_email');
        
        return [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Content-Type: text/plain; charset=UTF-8'
        ];
    }
    
    /**
     * Get default admin email template.
     */
    private function get_default_admin_email() {
        return "A new registration has been received.\n\nEvent: {event_title}\nName: {name}\nEmail: {email}\nPhone: {phone}\nRegistration Date: {registration_date}";
    }
    
    /**
     * Get default user email template.
     */
    private function get_default_user_email() {
        return "Dear {name},\n\nThank you for registering for {event_title}.\n\nEvent Details:\nDate: {event_date}\nTime: {event_time}\nLocation: {event_location}\n\nWe look forward to seeing you!\n\nBest regards,\n{site_name}";
    }
    
    /**
     * AJAX handler to check event capacity.
     */
    public function ajax_check_capacity() {
        check_ajax_referer('ae_ajax_nonce', 'nonce');
        
        $event_id = absint($_POST['event_id']);
        
        if (!$event_id || get_post_type($event_id) !== 'event') {
            wp_send_json_error(['message' => __('Invalid event.', 'arbe-events')]);
        }
        
        $capacity = get_post_meta($event_id, '_ae_event_capacity', true);
        $enable_waitlist = get_post_meta($event_id, '_ae_enable_waitlist', true);
        
        if (!$capacity || $capacity == 0) {
            wp_send_json_success(['available' => true, 'spots' => 'unlimited']);
        }
        
        $current_count = $this->db->get_registration_count($event_id, 'confirmed');
        $available = $capacity - $current_count;
        
        wp_send_json_success([
            'available' => $available > 0,
            'spots' => $available,
            'waitlist' => $enable_waitlist === 'yes',
            'capacity' => $capacity,
            'registered' => $current_count
        ]);
    }
}