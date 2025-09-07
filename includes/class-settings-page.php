<?php
/**
 * Enhanced settings page with tabbed interface.
 */
class AE_Settings_Page {
    
    private $tabs;
    private $current_tab;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_ae_save_settings', [$this, 'ajax_save_settings']);
        
        $this->init_tabs();
    }
    
    /**
     * Initialize settings tabs.
     */
    private function init_tabs() {
        $this->tabs = [
            'general' => [
                'title' => __('General', 'arbe-events'),
                'icon' => 'dashicons-admin-settings',
            ],
            'email' => [
                'title' => __('Email', 'arbe-events'),
                'icon' => 'dashicons-email-alt',
            ],
            'display' => [
                'title' => __('Display', 'arbe-events'),
                'icon' => 'dashicons-visibility',
            ],
            'advanced' => [
                'title' => __('Advanced', 'arbe-events'),
                'icon' => 'dashicons-admin-tools',
            ],
        ];
        
        // Allow Pro version to add tabs
        $this->tabs = apply_filters('ae_settings_tabs', $this->tabs);
        
        $this->current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        if (!array_key_exists($this->current_tab, $this->tabs)) {
            $this->current_tab = 'general';
        }
    }

    public function add_settings_page() {
        $hook = add_submenu_page(
            'edit.php?post_type=event',
            __('Settings', 'arbe-events'),
            __('Settings', 'arbe-events'),
            'manage_options',
            'ae-settings',
            [$this, 'render_settings_page']
        );
        
        add_action("load-$hook", [$this, 'load_settings_page']);
    }
    
    /**
     * Load settings page and process form submissions.
     */
    public function load_settings_page() {
        if (isset($_POST['ae_save_settings'])) {
            $this->save_settings();
        }
    }

    public function register_settings() {
        register_setting('ae_settings_group', 'ae_settings', [$this, 'sanitize_settings']);
        register_setting('ae_email_templates_group', 'ae_email_templates', [$this, 'sanitize_email_templates']);
    }
    
    /**
     * Sanitize general settings.
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        if (isset($input['admin_email'])) {
            $sanitized['admin_email'] = sanitize_email($input['admin_email']);
        }
        
        if (isset($input['enable_notifications'])) {
            $sanitized['enable_notifications'] = $input['enable_notifications'] === 'yes' ? 'yes' : 'no';
        }
        
        if (isset($input['enable_user_confirmation'])) {
            $sanitized['enable_user_confirmation'] = $input['enable_user_confirmation'] === 'yes' ? 'yes' : 'no';
        }
        
        if (isset($input['default_capacity'])) {
            $sanitized['default_capacity'] = absint($input['default_capacity']);
        }
        
        if (isset($input['date_format'])) {
            $sanitized['date_format'] = sanitize_text_field($input['date_format']);
        }
        
        if (isset($input['time_format'])) {
            $sanitized['time_format'] = sanitize_text_field($input['time_format']);
        }
        
        if (isset($input['enable_recaptcha'])) {
            $sanitized['enable_recaptcha'] = $input['enable_recaptcha'] === 'yes' ? 'yes' : 'no';
        }
        
        if (isset($input['recaptcha_site_key'])) {
            $sanitized['recaptcha_site_key'] = sanitize_text_field($input['recaptcha_site_key']);
        }
        
        if (isset($input['recaptcha_secret_key'])) {
            $sanitized['recaptcha_secret_key'] = sanitize_text_field($input['recaptcha_secret_key']);
        }
        
        if (isset($input['form_success_message'])) {
            $sanitized['form_success_message'] = sanitize_textarea_field($input['form_success_message']);
        }
        
        if (isset($input['form_error_message'])) {
            $sanitized['form_error_message'] = sanitize_textarea_field($input['form_error_message']);
        }
        
        if (isset($input['email_from_name'])) {
            $sanitized['email_from_name'] = sanitize_text_field($input['email_from_name']);
        }
        
        if (isset($input['email_from_address'])) {
            $sanitized['email_from_address'] = sanitize_email($input['email_from_address']);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize email templates.
     */
    public function sanitize_email_templates($input) {
        $sanitized = [];
        
        $template_fields = [
            'admin_notification_subject',
            'admin_notification_body',
            'user_confirmation_subject',
            'user_confirmation_body',
            'reminder_subject',
            'reminder_body',
        ];
        
        foreach ($template_fields as $field) {
            if (isset($input[$field])) {
                if (strpos($field, '_body') !== false) {
                    $sanitized[$field] = sanitize_textarea_field($input[$field]);
                } else {
                    $sanitized[$field] = sanitize_text_field($input[$field]);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Save settings via AJAX or regular form submission.
     */
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to save settings.', 'arbe-events'));
        }
        
        if (!isset($_POST['ae_settings_nonce']) || !wp_verify_nonce($_POST['ae_settings_nonce'], 'ae_save_settings')) {
            wp_die(__('Security check failed.', 'arbe-events'));
        }
        
        $tab = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : 'general';
        
        switch ($tab) {
            case 'general':
                $current_settings = get_option('ae_settings', []);
                $new_settings = isset($_POST['ae_settings']) ? $_POST['ae_settings'] : [];
                $settings = $this->sanitize_settings(array_merge($current_settings, $new_settings));
                update_option('ae_settings', $settings);
                break;
                
            case 'email':
                $current_templates = get_option('ae_email_templates', []);
                $new_templates = isset($_POST['ae_email_templates']) ? $_POST['ae_email_templates'] : [];
                $templates = $this->sanitize_email_templates(array_merge($current_templates, $new_templates));
                update_option('ae_email_templates', $templates);
                break;
                
            case 'display':
                $current_settings = get_option('ae_settings', []);
                $new_settings = isset($_POST['ae_settings']) ? $_POST['ae_settings'] : [];
                $settings = $this->sanitize_settings(array_merge($current_settings, $new_settings));
                update_option('ae_settings', $settings);
                break;
                
            case 'advanced':
                $current_settings = get_option('ae_settings', []);
                $new_settings = isset($_POST['ae_settings']) ? $_POST['ae_settings'] : [];
                $settings = $this->sanitize_settings(array_merge($current_settings, $new_settings));
                update_option('ae_settings', $settings);
                break;
        }
        
        do_action('ae_settings_saved', $tab);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'arbe-events') . '</p></div>';
        });
    }
    
    /**
     * AJAX save settings handler.
     */
    public function ajax_save_settings() {
        $this->save_settings();
        wp_send_json_success(['message' => __('Settings saved successfully!', 'arbe-events')]);
    }
    
    /**
     * Enqueue scripts and styles for settings page.
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'event_page_ae-settings') {
            return;
        }
        
        wp_enqueue_style('ae-settings-css', AE_PLUGIN_URL . 'assets/css/settings.css', [], AE_PLUGIN_VERSION);
        wp_enqueue_script('ae-settings-js', AE_PLUGIN_URL . 'assets/js/settings.js', ['jquery'], AE_PLUGIN_VERSION, true);
        
        wp_localize_script('ae-settings-js', 'ae_settings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ae_settings_nonce'),
            'saving_text' => __('Saving...', 'arbe-events'),
            'saved_text' => __('Saved!', 'arbe-events'),
        ]);
    }

    public function render_settings_page() {
        ?>
        <div class="wrap ae-settings-wrap">
            <h1 class="ae-settings-title">
                <span class="ae-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                    </svg>
                </span>
                <?php _e('Arbe Events Settings', 'arbe-events'); ?>
            </h1>
            
            <div class="ae-settings-container">
                <nav class="ae-settings-nav">
                    <?php $this->render_nav_tabs(); ?>
                </nav>
                
                <main class="ae-settings-main">
                    <form method="post" id="ae-settings-form" class="ae-settings-form">
                        <?php wp_nonce_field('ae_save_settings', 'ae_settings_nonce'); ?>
                        <input type="hidden" name="tab" value="<?php echo esc_attr($this->current_tab); ?>">
                        
                        <div class="ae-settings-content">
                            <?php $this->render_tab_content(); ?>
                        </div>
                        
                        <div class="ae-settings-footer">
                            <button type="submit" name="ae_save_settings" class="button button-primary ae-save-button">
                                <span class="ae-save-text"><?php _e('Save Settings', 'arbe-events'); ?></span>
                                <span class="ae-save-spinner" style="display: none;">
                                    <span class="spinner is-active"></span>
                                    <?php _e('Saving...', 'arbe-events'); ?>
                                </span>
                            </button>
                            
                            <?php if ($this->current_tab !== 'general') : ?>
                                <a href="<?php echo admin_url('edit.php?post_type=event&page=ae-settings'); ?>" class="button">
                                    <?php _e('Reset to Defaults', 'arbe-events'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </main>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render navigation tabs.
     */
    private function render_nav_tabs() {
        foreach ($this->tabs as $tab_key => $tab_data) {
            $is_active = $this->current_tab === $tab_key;
            $tab_url = add_query_arg(['tab' => $tab_key], admin_url('edit.php?post_type=event&page=ae-settings'));
            
            printf(
                '<a href="%s" class="ae-nav-tab%s" data-tab="%s">
                    <span class="ae-nav-icon %s"></span>
                    <span class="ae-nav-text">%s</span>
                </a>',
                esc_url($tab_url),
                $is_active ? ' active' : '',
                esc_attr($tab_key),
                esc_attr($tab_data['icon']),
                esc_html($tab_data['title'])
            );
        }
    }
    
    /**
     * Render current tab content.
     */
    private function render_tab_content() {
        switch ($this->current_tab) {
            case 'general':
                $this->render_general_tab();
                break;
            case 'email':
                $this->render_email_tab();
                break;
            case 'display':
                $this->render_display_tab();
                break;
            case 'advanced':
                $this->render_advanced_tab();
                break;
            default:
                do_action('ae_render_settings_tab_' . $this->current_tab);
        }
    }
    
    /**
     * Render general settings tab.
     */
    private function render_general_tab() {
        $settings = get_option('ae_settings', []);
        ?>
        <div class="ae-settings-section">
            <h2><?php _e('General Settings', 'arbe-events'); ?></h2>
            <p class="description"><?php _e('Configure basic plugin settings and defaults.', 'arbe-events'); ?></p>
            
            <div class="ae-settings-grid">
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="admin_email">
                        <?php _e('Admin Email', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="email" 
                               id="admin_email" 
                               name="ae_settings[admin_email]" 
                               value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>" 
                               class="regular-text" />
                        <p class="description"><?php _e('Email address to receive registration notifications.', 'arbe-events'); ?></p>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label">
                        <?php _e('Email Notifications', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <label class="ae-toggle">
                            <input type="checkbox" 
                                   name="ae_settings[enable_notifications]" 
                                   value="yes" 
                                   <?php checked($settings['enable_notifications'] ?? 'yes', 'yes'); ?> />
                            <span class="ae-toggle-slider"></span>
                            <span class="ae-toggle-label"><?php _e('Send admin notifications for new registrations', 'arbe-events'); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label">
                        <?php _e('User Confirmations', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <label class="ae-toggle">
                            <input type="checkbox" 
                                   name="ae_settings[enable_user_confirmation]" 
                                   value="yes" 
                                   <?php checked($settings['enable_user_confirmation'] ?? 'yes', 'yes'); ?> />
                            <span class="ae-toggle-slider"></span>
                            <span class="ae-toggle-label"><?php _e('Send confirmation emails to users', 'arbe-events'); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="default_capacity">
                        <?php _e('Default Capacity', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="number" 
                               id="default_capacity" 
                               name="ae_settings[default_capacity]" 
                               value="<?php echo esc_attr($settings['default_capacity'] ?? 100); ?>" 
                               min="0" 
                               class="small-text" />
                        <p class="description"><?php _e('Default capacity for new events. Set to 0 for unlimited.', 'arbe-events'); ?></p>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="date_format">
                        <?php _e('Date Format', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <select id="date_format" name="ae_settings[date_format]">
                            <option value="F j, Y" <?php selected($settings['date_format'] ?? get_option('date_format'), 'F j, Y'); ?>>
                                <?php echo date('F j, Y'); ?> (F j, Y)
                            </option>
                            <option value="Y-m-d" <?php selected($settings['date_format'] ?? get_option('date_format'), 'Y-m-d'); ?>>
                                <?php echo date('Y-m-d'); ?> (Y-m-d)
                            </option>
                            <option value="m/d/Y" <?php selected($settings['date_format'] ?? get_option('date_format'), 'm/d/Y'); ?>>
                                <?php echo date('m/d/Y'); ?> (m/d/Y)
                            </option>
                            <option value="d/m/Y" <?php selected($settings['date_format'] ?? get_option('date_format'), 'd/m/Y'); ?>>
                                <?php echo date('d/m/Y'); ?> (d/m/Y)
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="time_format">
                        <?php _e('Time Format', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <select id="time_format" name="ae_settings[time_format]">
                            <option value="g:i a" <?php selected($settings['time_format'] ?? get_option('time_format'), 'g:i a'); ?>>
                                <?php echo date('g:i a'); ?> (g:i a)
                            </option>
                            <option value="H:i" <?php selected($settings['time_format'] ?? get_option('time_format'), 'H:i'); ?>>
                                <?php echo date('H:i'); ?> (H:i)
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render email settings tab.
     */
    private function render_email_tab() {
        $settings = get_option('ae_settings', []);
        $templates = get_option('ae_email_templates', []);
        ?>
        <div class="ae-settings-section">
            <h2><?php _e('Email Settings', 'arbe-events'); ?></h2>
            <p class="description"><?php _e('Configure email sender information and templates.', 'arbe-events'); ?></p>
            
            <div class="ae-settings-grid">
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="email_from_name">
                        <?php _e('From Name', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="text" 
                               id="email_from_name" 
                               name="ae_settings[email_from_name]" 
                               value="<?php echo esc_attr($settings['email_from_name'] ?? get_bloginfo('name')); ?>" 
                               class="regular-text" />
                        <p class="description"><?php _e('Name that appears in the "From" field of emails.', 'arbe-events'); ?></p>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="email_from_address">
                        <?php _e('From Address', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="email" 
                               id="email_from_address" 
                               name="ae_settings[email_from_address]" 
                               value="<?php echo esc_attr($settings['email_from_address'] ?? get_option('admin_email')); ?>" 
                               class="regular-text" />
                        <p class="description"><?php _e('Email address that appears in the "From" field.', 'arbe-events'); ?></p>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="admin_notification_subject">
                        <?php _e('Admin Notification Subject', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="text" 
                               id="admin_notification_subject" 
                               name="ae_email_templates[admin_notification_subject]" 
                               value="<?php echo esc_attr($templates['admin_notification_subject'] ?? __('New Event Registration', 'arbe-events')); ?>" 
                               class="regular-text" />
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="admin_notification_body">
                        <?php _e('Admin Notification Body', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <textarea id="admin_notification_body" 
                                  name="ae_email_templates[admin_notification_body]" 
                                  rows="8" 
                                  class="large-text"><?php echo esc_textarea($templates['admin_notification_body'] ?? $this->get_default_admin_template()); ?></textarea>
                        <p class="description">
                            <?php _e('Available placeholders:', 'arbe-events'); ?> 
                            <code>{name}</code>, <code>{email}</code>, <code>{phone}</code>, <code>{event_title}</code>, <code>{registration_date}</code>
                        </p>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="user_confirmation_subject">
                        <?php _e('User Confirmation Subject', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="text" 
                               id="user_confirmation_subject" 
                               name="ae_email_templates[user_confirmation_subject]" 
                               value="<?php echo esc_attr($templates['user_confirmation_subject'] ?? __('Registration Confirmation', 'arbe-events')); ?>" 
                               class="regular-text" />
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="user_confirmation_body">
                        <?php _e('User Confirmation Body', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <textarea id="user_confirmation_body" 
                                  name="ae_email_templates[user_confirmation_body]" 
                                  rows="10" 
                                  class="large-text"><?php echo esc_textarea($templates['user_confirmation_body'] ?? $this->get_default_user_template()); ?></textarea>
                        <p class="description">
                            <?php _e('Available placeholders:', 'arbe-events'); ?> 
                            <code>{name}</code>, <code>{event_title}</code>, <code>{event_date}</code>, <code>{event_time}</code>, <code>{event_location}</code>, <code>{site_name}</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render display settings tab.
     */
    private function render_display_tab() {
        $settings = get_option('ae_settings', []);
        ?>
        <div class="ae-settings-section">
            <h2><?php _e('Display Settings', 'arbe-events'); ?></h2>
            <p class="description"><?php _e('Configure how the registration form appears to users.', 'arbe-events'); ?></p>
            
            <div class="ae-settings-grid">
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="form_success_message">
                        <?php _e('Success Message', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <textarea id="form_success_message" 
                                  name="ae_settings[form_success_message]" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($settings['form_success_message'] ?? __('Thank you for registering! You will receive a confirmation email shortly.', 'arbe-events')); ?></textarea>
                        <p class="description"><?php _e('Message shown after successful registration.', 'arbe-events'); ?></p>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label" for="form_error_message">
                        <?php _e('Error Message', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <textarea id="form_error_message" 
                                  name="ae_settings[form_error_message]" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($settings['form_error_message'] ?? __('Sorry, there was an error processing your registration. Please try again.', 'arbe-events')); ?></textarea>
                        <p class="description"><?php _e('Message shown when registration fails.', 'arbe-events'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="ae-settings-section">
            <h2><?php _e('Security Settings', 'arbe-events'); ?></h2>
            <p class="description"><?php _e('Enable additional security features for your registration forms.', 'arbe-events'); ?></p>
            
            <div class="ae-settings-grid">
                <div class="ae-setting-row">
                    <label class="ae-setting-label">
                        <?php _e('reCAPTCHA', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <label class="ae-toggle">
                            <input type="checkbox" 
                                   name="ae_settings[enable_recaptcha]" 
                                   value="yes" 
                                   <?php checked($settings['enable_recaptcha'] ?? 'no', 'yes'); ?> />
                            <span class="ae-toggle-slider"></span>
                            <span class="ae-toggle-label"><?php _e('Enable Google reCAPTCHA v2', 'arbe-events'); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="ae-setting-row ae-recaptcha-field">
                    <label class="ae-setting-label" for="recaptcha_site_key">
                        <?php _e('reCAPTCHA Site Key', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="text" 
                               id="recaptcha_site_key" 
                               name="ae_settings[recaptcha_site_key]" 
                               value="<?php echo esc_attr($settings['recaptcha_site_key'] ?? ''); ?>" 
                               class="regular-text" />
                    </div>
                </div>
                
                <div class="ae-setting-row ae-recaptcha-field">
                    <label class="ae-setting-label" for="recaptcha_secret_key">
                        <?php _e('reCAPTCHA Secret Key', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <input type="text" 
                               id="recaptcha_secret_key" 
                               name="ae_settings[recaptcha_secret_key]" 
                               value="<?php echo esc_attr($settings['recaptcha_secret_key'] ?? ''); ?>" 
                               class="regular-text" />
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render advanced settings tab.
     */
    private function render_advanced_tab() {
        ?>
        <div class="ae-settings-section">
            <h2><?php _e('Advanced Settings', 'arbe-events'); ?></h2>
            <p class="description"><?php _e('Advanced configuration options for developers.', 'arbe-events'); ?></p>
            
            <div class="ae-settings-grid">
                <div class="ae-setting-row">
                    <label class="ae-setting-label">
                        <?php _e('Debug Mode', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <label class="ae-toggle">
                            <input type="checkbox" name="ae_settings[debug_mode]" value="yes" />
                            <span class="ae-toggle-slider"></span>
                            <span class="ae-toggle-label"><?php _e('Enable debug logging', 'arbe-events'); ?></span>
                        </label>
                        <p class="description"><?php _e('Log registration events for troubleshooting.', 'arbe-events'); ?></p>
                    </div>
                </div>
                
                <div class="ae-setting-row">
                    <label class="ae-setting-label">
                        <?php _e('Data Retention', 'arbe-events'); ?>
                    </label>
                    <div class="ae-setting-field">
                        <select name="ae_settings[data_retention]">
                            <option value="forever"><?php _e('Keep Forever', 'arbe-events'); ?></option>
                            <option value="1year"><?php _e('1 Year', 'arbe-events'); ?></option>
                            <option value="2years"><?php _e('2 Years', 'arbe-events'); ?></option>
                            <option value="5years"><?php _e('5 Years', 'arbe-events'); ?></option>
                        </select>
                        <p class="description"><?php _e('Automatically delete old registration data after this period.', 'arbe-events'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="ae-settings-section">
            <h2><?php _e('Pro Features', 'arbe-events'); ?></h2>
            <p class="description"><?php _e('Unlock additional functionality with Arbe Events Pro.', 'arbe-events'); ?></p>
            
            <?php if (class_exists('AE_Pro_Loader')) : ?>
                <div class="ae-pro-active">
                    <p><strong><?php _e('Pro version is active!', 'arbe-events'); ?></strong></p>
                </div>
            <?php else : ?>
                <div class="ae-pro-features">
                    <ul>
                        <li><?php _e('Recurring events', 'arbe-events'); ?></li>
                        <li><?php _e('Payment integration (Stripe/PayPal)', 'arbe-events'); ?></li>
                        <li><?php _e('Advanced form builder', 'arbe-events'); ?></li>
                        <li><?php _e('Email marketing integrations', 'arbe-events'); ?></li>
                        <li><?php _e('QR code check-ins', 'arbe-events'); ?></li>
                        <li><?php _e('Priority support', 'arbe-events'); ?></li>
                    </ul>
                    <a href="#" class="button button-primary"><?php _e('Upgrade to Pro', 'arbe-events'); ?></a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get default admin email template.
     */
    private function get_default_admin_template() {
        return __("A new registration has been received for your event.

Event: {event_title}
Name: {name}
Email: {email}
Phone: {phone}
Registration Date: {registration_date}

You can view all registrations in your WordPress admin panel.", 'arbe-events');
    }
    
    /**
     * Get default user email template.
     */
    private function get_default_user_template() {
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
}