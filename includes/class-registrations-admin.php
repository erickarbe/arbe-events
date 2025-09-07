<?php
/**
 * Registrations Admin - Manages the registrations admin page.
 */
class AE_Registrations_Admin {
    
    private $list_table;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('set-screen-option', [$this, 'set_screen_option'], 10, 3);
        
        add_action('wp_ajax_ae_get_registration_details', [$this, 'ajax_get_registration_details']);
    }
    
    /**
     * Add registrations menu page.
     */
    public function add_menu_page() {
        $hook = add_submenu_page(
            'edit.php?post_type=event',
            __('Registrations', 'arbe-events'),
            __('Registrations', 'arbe-events'),
            'manage_options',
            'ae-registrations',
            [$this, 'render_page']
        );
        
        add_action("load-$hook", [$this, 'screen_options']);
    }
    
    /**
     * Add screen options.
     */
    public function screen_options() {
        $option = 'per_page';
        $args = [
            'label'   => __('Registrations per page', 'arbe-events'),
            'default' => 20,
            'option'  => 'ae_registrations_per_page'
        ];
        
        add_screen_option($option, $args);
        
        require_once AE_PLUGIN_PATH . 'includes/class-registrations-list-table.php';
        $this->list_table = new AE_Registrations_List_Table();
    }
    
    /**
     * Set screen option value.
     */
    public function set_screen_option($status, $option, $value) {
        if ('ae_registrations_per_page' === $option) {
            return $value;
        }
        return $status;
    }
    
    /**
     * Handle admin actions.
     */
    public function handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'ae-registrations') {
            return;
        }
        
        require_once AE_PLUGIN_PATH . 'includes/class-csv-exporter.php';
        AE_CSV_Exporter::handle_export_request();
        
        if (isset($_GET['action']) && isset($_GET['registration_id'])) {
            $this->handle_single_action();
        }
    }
    
    /**
     * Handle single registration actions.
     */
    private function handle_single_action() {
        $action = sanitize_text_field($_GET['action']);
        $registration_id = absint($_GET['registration_id']);
        
        if (!$registration_id) {
            return;
        }
        
        require_once AE_PLUGIN_PATH . 'includes/class-database.php';
        $db = new AE_Database();
        
        switch ($action) {
            case 'cancel':
                if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ae_cancel_registration')) {
                    wp_die(__('Security check failed', 'arbe-events'));
                }
                
                if ($db->update_registration_status($registration_id, 'cancelled')) {
                    $this->add_admin_notice(__('Registration cancelled successfully.', 'arbe-events'), 'success');
                } else {
                    $this->add_admin_notice(__('Failed to cancel registration.', 'arbe-events'), 'error');
                }
                
                wp_redirect(remove_query_arg(['action', 'registration_id', '_wpnonce']));
                exit;
                break;
                
            case 'delete':
                if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ae_delete_registration')) {
                    wp_die(__('Security check failed', 'arbe-events'));
                }
                
                if ($db->delete_registration($registration_id)) {
                    $this->add_admin_notice(__('Registration deleted successfully.', 'arbe-events'), 'success');
                } else {
                    $this->add_admin_notice(__('Failed to delete registration.', 'arbe-events'), 'error');
                }
                
                wp_redirect(remove_query_arg(['action', 'registration_id', '_wpnonce']));
                exit;
                break;
        }
    }
    
    /**
     * Render the registrations page.
     */
    public function render_page() {
        if (!$this->list_table) {
            require_once AE_PLUGIN_PATH . 'includes/class-registrations-list-table.php';
            $this->list_table = new AE_Registrations_List_Table();
        }
        
        $this->list_table->prepare_items();
        
        $event = null;
        if (isset($_GET['event_id']) && $_GET['event_id']) {
            $event = get_post(absint($_GET['event_id']));
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php
                if ($event) {
                    printf(
                        __('Registrations for %s', 'arbe-events'),
                        '<em>' . esc_html($event->post_title) . '</em>'
                    );
                } else {
                    _e('Event Registrations', 'arbe-events');
                }
                ?>
            </h1>
            
            <?php if ($event) : ?>
                <a href="<?php echo admin_url('admin.php?page=ae-registrations'); ?>" class="page-title-action">
                    <?php _e('View All Registrations', 'arbe-events'); ?>
                </a>
            <?php endif; ?>
            
            <hr class="wp-header-end">
            
            <?php $this->display_notices(); ?>
            
            <form method="get">
                <input type="hidden" name="page" value="ae-registrations" />
                <?php if (isset($_GET['event_id'])) : ?>
                    <input type="hidden" name="event_id" value="<?php echo esc_attr($_GET['event_id']); ?>" />
                <?php endif; ?>
                
                <?php $this->list_table->search_box(__('Search Registrations', 'arbe-events'), 'registration'); ?>
            </form>
            
            <form method="post">
                <?php $this->list_table->views(); ?>
                <?php $this->list_table->display(); ?>
            </form>
        </div>
        
        <?php $this->render_view_modal(); ?>
        <?php
    }
    
    /**
     * Render the view registration modal.
     */
    private function render_view_modal() {
        ?>
        <div id="ae-registration-modal" style="display:none;">
            <div class="ae-modal-content">
                <span class="ae-modal-close">&times;</span>
                <h2><?php _e('Registration Details', 'arbe-events'); ?></h2>
                <div class="ae-modal-body">
                    <div class="ae-loading"><?php _e('Loading...', 'arbe-events'); ?></div>
                </div>
            </div>
        </div>
        
        <style>
            #ae-registration-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }
            .ae-modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 600px;
                max-width: 90%;
                border-radius: 4px;
            }
            .ae-modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                line-height: 20px;
            }
            .ae-modal-close:hover,
            .ae-modal-close:focus {
                color: #000;
            }
            .ae-modal-body {
                margin-top: 20px;
            }
            .ae-registration-detail {
                margin-bottom: 15px;
            }
            .ae-registration-detail label {
                font-weight: bold;
                display: inline-block;
                width: 150px;
            }
            .ae-loading {
                text-align: center;
                padding: 20px;
            }
        </style>
        <?php
    }
    
    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'event_page_ae-registrations') {
            return;
        }
        
        wp_enqueue_script(
            'ae-registrations-admin',
            AE_PLUGIN_URL . 'assets/js/registrations-admin.js',
            ['jquery'],
            AE_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('ae-registrations-admin', 'ae_registrations', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ae_admin_nonce'),
        ]);
    }
    
    /**
     * Display admin notices.
     */
    private function display_notices() {
        if (isset($_GET['ae_message'])) {
            $message = '';
            $type = 'success';
            
            switch ($_GET['ae_message']) {
                case 'exported':
                    $message = __('Registrations exported successfully.', 'arbe-events');
                    break;
                case 'deleted':
                    $message = __('Registration deleted successfully.', 'arbe-events');
                    break;
                case 'cancelled':
                    $message = __('Registration cancelled successfully.', 'arbe-events');
                    break;
            }
            
            if ($message) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($type),
                    esc_html($message)
                );
            }
        }
    }
    
    /**
     * Add admin notice.
     */
    private function add_admin_notice($message, $type = 'success') {
        set_transient('ae_admin_notice', [
            'message' => $message,
            'type' => $type
        ], 30);
    }
    
    /**
     * AJAX handler to get registration details.
     */
    public function ajax_get_registration_details() {
        check_ajax_referer('ae_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'arbe-events')]);
        }
        
        $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
        
        if (!$registration_id) {
            wp_send_json_error(['message' => __('Invalid registration ID.', 'arbe-events')]);
        }
        
        require_once AE_PLUGIN_PATH . 'includes/class-database.php';
        $db = new AE_Database();
        
        $registration = $db->get_registration($registration_id);
        
        if (!$registration) {
            wp_send_json_error(['message' => __('Registration not found.', 'arbe-events')]);
        }
        
        $event = get_post($registration->event_id);
        
        $status_labels = [
            'confirmed' => __('Confirmed', 'arbe-events'),
            'waitlist'  => __('Waitlist', 'arbe-events'),
            'cancelled' => __('Cancelled', 'arbe-events'),
            'pending'   => __('Pending', 'arbe-events'),
        ];
        
        $response_data = [
            'id' => $registration->id,
            'name' => $registration->name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'status' => isset($status_labels[$registration->status]) ? $status_labels[$registration->status] : $registration->status,
            'registration_date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->registration_date)),
            'event_title' => $event ? $event->post_title : __('Event not found', 'arbe-events'),
            'meta' => $registration->meta
        ];
        
        if ($event) {
            $start_date = get_post_meta($event->ID, '_ae_event_start_date', true);
            $start_time = get_post_meta($event->ID, '_ae_event_start_time', true);
            $venue = get_post_meta($event->ID, '_ae_event_venue', true);
            $address = get_post_meta($event->ID, '_ae_event_address', true);
            $city = get_post_meta($event->ID, '_ae_event_city', true);
            $state = get_post_meta($event->ID, '_ae_event_state', true);
            
            if ($start_date) {
                $response_data['event_date'] = date_i18n(get_option('date_format'), strtotime($start_date));
                if ($start_time) {
                    $response_data['event_date'] .= ' ' . date_i18n(get_option('time_format'), strtotime($start_time));
                }
            }
            
            $location_parts = array_filter([$venue, $address, $city, $state]);
            if (!empty($location_parts)) {
                $response_data['event_location'] = implode(', ', $location_parts);
            }
        }
        
        wp_send_json_success($response_data);
    }
}