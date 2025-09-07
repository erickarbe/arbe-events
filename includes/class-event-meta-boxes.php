<?php
/**
 * Event Meta Boxes - Handles custom fields for events.
 */
class AE_Event_Meta_Boxes {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_event_meta_boxes']);
        add_action('save_post_event', [$this, 'save_event_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        add_filter('manage_event_posts_columns', [$this, 'add_event_columns']);
        add_action('manage_event_posts_custom_column', [$this, 'render_event_columns'], 10, 2);
        add_filter('manage_edit-event_sortable_columns', [$this, 'make_columns_sortable']);
    }
    
    /**
     * Add meta boxes to event edit screen.
     */
    public function add_event_meta_boxes() {
        add_meta_box(
            'ae_event_details',
            __('Event Details', 'arbe-events'),
            [$this, 'render_event_details_box'],
            'event',
            'normal',
            'high'
        );
        
        add_meta_box(
            'ae_registration_settings',
            __('Registration Settings', 'arbe-events'),
            [$this, 'render_registration_settings_box'],
            'event',
            'side',
            'default'
        );
        
        add_meta_box(
            'ae_registration_stats',
            __('Registration Stats', 'arbe-events'),
            [$this, 'render_registration_stats_box'],
            'event',
            'side',
            'default'
        );
    }
    
    /**
     * Render event details meta box.
     */
    public function render_event_details_box($post) {
        wp_nonce_field('ae_save_event_meta', 'ae_event_meta_nonce');
        
        $start_date = get_post_meta($post->ID, '_ae_event_start_date', true);
        $start_time = get_post_meta($post->ID, '_ae_event_start_time', true);
        $end_date = get_post_meta($post->ID, '_ae_event_end_date', true);
        $end_time = get_post_meta($post->ID, '_ae_event_end_time', true);
        $venue_name = get_post_meta($post->ID, '_ae_event_venue', true);
        $address = get_post_meta($post->ID, '_ae_event_address', true);
        $city = get_post_meta($post->ID, '_ae_event_city', true);
        $state = get_post_meta($post->ID, '_ae_event_state', true);
        $zip = get_post_meta($post->ID, '_ae_event_zip', true);
        $virtual_url = get_post_meta($post->ID, '_ae_event_virtual_url', true);
        ?>
        <style>
            .ae-meta-row {
                margin-bottom: 15px;
            }
            .ae-meta-row label {
                display: inline-block;
                width: 150px;
                font-weight: 600;
                vertical-align: top;
            }
            .ae-meta-row input[type="text"],
            .ae-meta-row input[type="date"],
            .ae-meta-row input[type="time"],
            .ae-meta-row input[type="url"],
            .ae-meta-row textarea {
                width: calc(100% - 160px);
                max-width: 400px;
            }
            .ae-meta-row.half {
                display: inline-block;
                width: 48%;
                margin-right: 2%;
            }
            .ae-meta-row.half input {
                width: 100%;
                max-width: none;
            }
            .ae-meta-section {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .ae-meta-section h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #23282d;
            }
        </style>
        
        <div class="ae-meta-container">
            <h4><?php _e('Date & Time', 'arbe-events'); ?></h4>
            
            <div class="ae-meta-row half">
                <label for="ae_event_start_date"><?php _e('Start Date', 'arbe-events'); ?></label>
                <input type="date" id="ae_event_start_date" name="ae_event_start_date" 
                       value="<?php echo esc_attr($start_date); ?>" />
            </div>
            
            <div class="ae-meta-row half">
                <label for="ae_event_start_time"><?php _e('Start Time', 'arbe-events'); ?></label>
                <input type="time" id="ae_event_start_time" name="ae_event_start_time" 
                       value="<?php echo esc_attr($start_time); ?>" />
            </div>
            
            <div class="ae-meta-row half">
                <label for="ae_event_end_date"><?php _e('End Date', 'arbe-events'); ?></label>
                <input type="date" id="ae_event_end_date" name="ae_event_end_date" 
                       value="<?php echo esc_attr($end_date); ?>" />
            </div>
            
            <div class="ae-meta-row half">
                <label for="ae_event_end_time"><?php _e('End Time', 'arbe-events'); ?></label>
                <input type="time" id="ae_event_end_time" name="ae_event_end_time" 
                       value="<?php echo esc_attr($end_time); ?>" />
            </div>
            
            <div class="ae-meta-section">
                <h4><?php _e('Location', 'arbe-events'); ?></h4>
                
                <div class="ae-meta-row">
                    <label for="ae_event_venue"><?php _e('Venue Name', 'arbe-events'); ?></label>
                    <input type="text" id="ae_event_venue" name="ae_event_venue" 
                           value="<?php echo esc_attr($venue_name); ?>" 
                           placeholder="<?php esc_attr_e('e.g., Conference Center', 'arbe-events'); ?>" />
                </div>
                
                <div class="ae-meta-row">
                    <label for="ae_event_address"><?php _e('Street Address', 'arbe-events'); ?></label>
                    <input type="text" id="ae_event_address" name="ae_event_address" 
                           value="<?php echo esc_attr($address); ?>" 
                           placeholder="<?php esc_attr_e('123 Main Street', 'arbe-events'); ?>" />
                </div>
                
                <div class="ae-meta-row half">
                    <label for="ae_event_city"><?php _e('City', 'arbe-events'); ?></label>
                    <input type="text" id="ae_event_city" name="ae_event_city" 
                           value="<?php echo esc_attr($city); ?>" />
                </div>
                
                <div class="ae-meta-row half">
                    <label for="ae_event_state"><?php _e('State/Province', 'arbe-events'); ?></label>
                    <input type="text" id="ae_event_state" name="ae_event_state" 
                           value="<?php echo esc_attr($state); ?>" />
                </div>
                
                <div class="ae-meta-row">
                    <label for="ae_event_zip"><?php _e('ZIP/Postal Code', 'arbe-events'); ?></label>
                    <input type="text" id="ae_event_zip" name="ae_event_zip" 
                           value="<?php echo esc_attr($zip); ?>" 
                           style="width: 150px;" />
                </div>
                
                <div class="ae-meta-row">
                    <label for="ae_event_virtual_url"><?php _e('Virtual Event URL', 'arbe-events'); ?></label>
                    <input type="url" id="ae_event_virtual_url" name="ae_event_virtual_url" 
                           value="<?php echo esc_attr($virtual_url); ?>" 
                           placeholder="<?php esc_attr_e('https://zoom.us/...', 'arbe-events'); ?>" />
                    <p class="description"><?php _e('Leave blank for in-person only events', 'arbe-events'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render registration settings meta box.
     */
    public function render_registration_settings_box($post) {
        $enabled = get_post_meta($post->ID, '_ae_registration_enabled', true);
        $capacity = get_post_meta($post->ID, '_ae_event_capacity', true);
        $waitlist = get_post_meta($post->ID, '_ae_enable_waitlist', true);
        $close_date = get_post_meta($post->ID, '_ae_registration_close_date', true);
        
        if (empty($enabled)) {
            $enabled = 'yes';
        }
        if (empty($capacity)) {
            $settings = get_option('ae_settings', []);
            $capacity = isset($settings['default_capacity']) ? $settings['default_capacity'] : 100;
        }
        ?>
        <p>
            <label for="ae_registration_enabled">
                <input type="checkbox" id="ae_registration_enabled" name="ae_registration_enabled" 
                       value="yes" <?php checked($enabled, 'yes'); ?> />
                <?php _e('Enable Registration', 'arbe-events'); ?>
            </label>
        </p>
        
        <p>
            <label for="ae_event_capacity"><?php _e('Capacity', 'arbe-events'); ?></label><br>
            <input type="number" id="ae_event_capacity" name="ae_event_capacity" 
                   value="<?php echo esc_attr($capacity); ?>" 
                   min="0" style="width: 100%;" />
            <span class="description"><?php _e('Set to 0 for unlimited', 'arbe-events'); ?></span>
        </p>
        
        <p>
            <label for="ae_enable_waitlist">
                <input type="checkbox" id="ae_enable_waitlist" name="ae_enable_waitlist" 
                       value="yes" <?php checked($waitlist, 'yes'); ?> />
                <?php _e('Enable Waitlist', 'arbe-events'); ?>
            </label>
            <span class="description"><?php _e('Allow registrations after capacity is reached', 'arbe-events'); ?></span>
        </p>
        
        <p>
            <label for="ae_registration_close_date"><?php _e('Registration Closes', 'arbe-events'); ?></label><br>
            <input type="datetime-local" id="ae_registration_close_date" name="ae_registration_close_date" 
                   value="<?php echo esc_attr($close_date); ?>" 
                   style="width: 100%;" />
            <span class="description"><?php _e('Leave blank to close at event start', 'arbe-events'); ?></span>
        </p>
        <?php
    }
    
    /**
     * Render registration stats meta box.
     */
    public function render_registration_stats_box($post) {
        require_once AE_PLUGIN_PATH . 'includes/class-database.php';
        $db = new AE_Database();
        
        $total = $db->get_registration_count($post->ID);
        $confirmed = $db->get_registration_count($post->ID, 'confirmed');
        $waitlist = $db->get_registration_count($post->ID, 'waitlist');
        $cancelled = $db->get_registration_count($post->ID, 'cancelled');
        
        $capacity = get_post_meta($post->ID, '_ae_event_capacity', true);
        $capacity = $capacity ? intval($capacity) : 0;
        
        $available = $capacity > 0 ? $capacity - $confirmed : __('Unlimited', 'arbe-events');
        ?>
        <style>
            .ae-stats-row {
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .ae-stats-row:last-child {
                border-bottom: none;
            }
            .ae-stats-label {
                font-weight: 600;
                display: inline-block;
                width: 70%;
            }
            .ae-stats-value {
                float: right;
                font-size: 16px;
                font-weight: 600;
            }
            .ae-stats-value.confirmed { color: #46b450; }
            .ae-stats-value.waitlist { color: #f0ad4e; }
            .ae-stats-value.cancelled { color: #dc3232; }
            .ae-stats-actions {
                margin-top: 15px;
                text-align: center;
            }
            .ae-stats-actions a.button {
                width: 100%;
                text-align: center;
                margin-bottom: 5px;
            }
        </style>
        
        <div class="ae-stats-container">
            <div class="ae-stats-row">
                <span class="ae-stats-label"><?php _e('Confirmed', 'arbe-events'); ?></span>
                <span class="ae-stats-value confirmed"><?php echo $confirmed; ?></span>
            </div>
            
            <?php if ($waitlist > 0) : ?>
            <div class="ae-stats-row">
                <span class="ae-stats-label"><?php _e('Waitlist', 'arbe-events'); ?></span>
                <span class="ae-stats-value waitlist"><?php echo $waitlist; ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($cancelled > 0) : ?>
            <div class="ae-stats-row">
                <span class="ae-stats-label"><?php _e('Cancelled', 'arbe-events'); ?></span>
                <span class="ae-stats-value cancelled"><?php echo $cancelled; ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($capacity > 0) : ?>
            <div class="ae-stats-row">
                <span class="ae-stats-label"><?php _e('Available Spots', 'arbe-events'); ?></span>
                <span class="ae-stats-value"><?php echo $available; ?></span>
            </div>
            <?php endif; ?>
            
            <div class="ae-stats-actions">
                <a href="<?php echo admin_url('admin.php?page=ae-registrations&event_id=' . $post->ID); ?>" 
                   class="button button-primary">
                    <?php _e('View Registrations', 'arbe-events'); ?>
                </a>
                <?php if ($total > 0) : ?>
                <a href="<?php echo admin_url('admin.php?page=ae-registrations&action=export&event_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce('ae_export_csv')); ?>" 
                   class="button">
                    <?php _e('Export CSV', 'arbe-events'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save event meta data.
     */
    public function save_event_meta($post_id, $post) {
        if (!isset($_POST['ae_event_meta_nonce']) || 
            !wp_verify_nonce($_POST['ae_event_meta_nonce'], 'ae_save_event_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $meta_fields = [
            '_ae_event_start_date' => 'sanitize_text_field',
            '_ae_event_start_time' => 'sanitize_text_field',
            '_ae_event_end_date' => 'sanitize_text_field',
            '_ae_event_end_time' => 'sanitize_text_field',
            '_ae_event_venue' => 'sanitize_text_field',
            '_ae_event_address' => 'sanitize_text_field',
            '_ae_event_city' => 'sanitize_text_field',
            '_ae_event_state' => 'sanitize_text_field',
            '_ae_event_zip' => 'sanitize_text_field',
            '_ae_event_virtual_url' => 'esc_url_raw',
            '_ae_event_capacity' => 'absint',
            '_ae_registration_close_date' => 'sanitize_text_field',
        ];
        
        foreach ($meta_fields as $meta_key => $sanitize_callback) {
            $field_name = str_replace('_ae_', 'ae_', $meta_key);
            
            if (isset($_POST[$field_name])) {
                $value = call_user_func($sanitize_callback, $_POST[$field_name]);
                update_post_meta($post_id, $meta_key, $value);
            }
        }
        
        $checkbox_fields = [
            '_ae_registration_enabled' => 'ae_registration_enabled',
            '_ae_enable_waitlist' => 'ae_enable_waitlist',
        ];
        
        foreach ($checkbox_fields as $meta_key => $field_name) {
            $value = isset($_POST[$field_name]) && $_POST[$field_name] === 'yes' ? 'yes' : 'no';
            update_post_meta($post_id, $meta_key, $value);
        }
    }
    
    /**
     * Add custom columns to event list.
     */
    public function add_event_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['event_date'] = __('Event Date', 'arbe-events');
                $new_columns['registrations'] = __('Registrations', 'arbe-events');
                $new_columns['capacity'] = __('Capacity', 'arbe-events');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom column content.
     */
    public function render_event_columns($column, $post_id) {
        switch ($column) {
            case 'event_date':
                $start_date = get_post_meta($post_id, '_ae_event_start_date', true);
                $start_time = get_post_meta($post_id, '_ae_event_start_time', true);
                
                if ($start_date) {
                    $date = date_i18n(get_option('date_format'), strtotime($start_date));
                    if ($start_time) {
                        $time = date_i18n(get_option('time_format'), strtotime($start_time));
                        echo $date . '<br><small>' . $time . '</small>';
                    } else {
                        echo $date;
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'registrations':
                require_once AE_PLUGIN_PATH . 'includes/class-database.php';
                $db = new AE_Database();
                $count = $db->get_registration_count($post_id);
                
                echo '<a href="' . admin_url('admin.php?page=ae-registrations&event_id=' . $post_id) . '">';
                echo '<strong>' . $count . '</strong>';
                echo '</a>';
                break;
                
            case 'capacity':
                $capacity = get_post_meta($post_id, '_ae_event_capacity', true);
                if ($capacity && $capacity > 0) {
                    require_once AE_PLUGIN_PATH . 'includes/class-database.php';
                    $db = new AE_Database();
                    $registered = $db->get_registration_count($post_id);
                    $percentage = round(($registered / $capacity) * 100);
                    
                    $color = 'green';
                    if ($percentage >= 100) {
                        $color = 'red';
                    } elseif ($percentage >= 75) {
                        $color = 'orange';
                    }
                    
                    echo '<span style="color: ' . $color . ';">';
                    echo $registered . ' / ' . $capacity;
                    echo ' (' . $percentage . '%)';
                    echo '</span>';
                } else {
                    echo __('Unlimited', 'arbe-events');
                }
                break;
        }
    }
    
    /**
     * Make columns sortable.
     */
    public function make_columns_sortable($columns) {
        $columns['event_date'] = 'event_date';
        return $columns;
    }
    
    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'event') {
            wp_enqueue_script('ae-admin-js', AE_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], AE_PLUGIN_VERSION, true);
            wp_enqueue_style('ae-admin-css', AE_PLUGIN_URL . 'assets/css/admin.css', [], AE_PLUGIN_VERSION);
        }
    }
}