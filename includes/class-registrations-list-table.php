<?php
/**
 * Registrations List Table - Extends WP_List_Table for displaying registrations.
 */

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AE_Registrations_List_Table extends WP_List_Table {
    
    private $db;
    private $event_id;
    
    public function __construct() {
        parent::__construct([
            'singular' => __('Registration', 'arbe-events'),
            'plural'   => __('Registrations', 'arbe-events'),
            'ajax'     => false
        ]);
        
        require_once AE_PLUGIN_PATH . 'includes/class-database.php';
        $this->db = new AE_Database();
        
        $this->event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
    }
    
    /**
     * Define table columns.
     */
    public function get_columns() {
        $columns = [
            'cb'                => '<input type="checkbox" />',
            'name'              => __('Name', 'arbe-events'),
            'email'             => __('Email', 'arbe-events'),
            'phone'             => __('Phone', 'arbe-events'),
            'event'             => __('Event', 'arbe-events'),
            'status'            => __('Status', 'arbe-events'),
            'registration_date' => __('Registration Date', 'arbe-events'),
        ];
        
        if ($this->event_id) {
            unset($columns['event']);
        }
        
        return $columns;
    }
    
    /**
     * Define sortable columns.
     */
    public function get_sortable_columns() {
        return [
            'name'              => ['name', false],
            'email'             => ['email', false],
            'event'             => ['event_title', false],
            'status'            => ['status', false],
            'registration_date' => ['registration_date', true],
        ];
    }
    
    /**
     * Define bulk actions.
     */
    public function get_bulk_actions() {
        return [
            'confirm'  => __('Mark as Confirmed', 'arbe-events'),
            'waitlist' => __('Move to Waitlist', 'arbe-events'),
            'cancel'   => __('Cancel Registration', 'arbe-events'),
            'delete'   => __('Delete Permanently', 'arbe-events'),
            'export'   => __('Export Selected', 'arbe-events'),
        ];
    }
    
    /**
     * Process bulk actions.
     */
    public function process_bulk_action() {
        if (empty($_POST['registration']) || empty($_POST['_wpnonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
            wp_die(__('Security check failed', 'arbe-events'));
        }
        
        $action = $this->current_action();
        $registration_ids = array_map('absint', $_POST['registration']);
        
        switch ($action) {
            case 'confirm':
                foreach ($registration_ids as $id) {
                    $this->db->update_registration_status($id, 'confirmed');
                }
                $this->add_admin_notice(__('Registrations marked as confirmed.', 'arbe-events'), 'success');
                break;
                
            case 'waitlist':
                foreach ($registration_ids as $id) {
                    $this->db->update_registration_status($id, 'waitlist');
                }
                $this->add_admin_notice(__('Registrations moved to waitlist.', 'arbe-events'), 'success');
                break;
                
            case 'cancel':
                foreach ($registration_ids as $id) {
                    $this->db->update_registration_status($id, 'cancelled');
                }
                $this->add_admin_notice(__('Registrations cancelled.', 'arbe-events'), 'success');
                break;
                
            case 'delete':
                foreach ($registration_ids as $id) {
                    $this->db->delete_registration($id);
                }
                $this->add_admin_notice(__('Registrations deleted permanently.', 'arbe-events'), 'success');
                break;
                
            case 'export':
                $this->export_registrations($registration_ids);
                break;
        }
    }
    
    /**
     * Prepare items for display.
     */
    public function prepare_items() {
        $this->process_bulk_action();
        
        $per_page = $this->get_items_per_page('ae_registrations_per_page', 20);
        $current_page = $this->get_pagenum();
        
        $args = [
            'limit' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'registration_date',
            'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC',
        ];
        
        if (isset($_GET['status']) && $_GET['status'] !== 'all') {
            $args['status'] = sanitize_text_field($_GET['status']);
        }
        
        if (!empty($_GET['s'])) {
            $args['search'] = sanitize_text_field($_GET['s']);
        }
        
        if ($this->event_id) {
            $registrations = $this->db->get_event_registrations($this->event_id, $args);
            $total_items = $this->db->get_registration_count($this->event_id, isset($args['status']) ? $args['status'] : '');
        } else {
            $result = $this->db->get_all_registrations($args);
            $registrations = $result['registrations'];
            $total_items = $result['total'];
        }
        
        $this->items = $registrations;
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
    
    /**
     * Default column display.
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'phone':
                return !empty($item->phone) ? esc_html($item->phone) : '—';
            case 'registration_date':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->registration_date));
            default:
                return esc_html($item->$column_name);
        }
    }
    
    /**
     * Checkbox column.
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="registration[]" value="%s" />',
            $item->id
        );
    }
    
    /**
     * Name column with actions.
     */
    public function column_name($item) {
        $actions = [
            'view' => sprintf(
                '<a href="#" class="ae-view-registration" data-id="%s">%s</a>',
                $item->id,
                __('View', 'arbe-events')
            ),
            'email' => sprintf(
                '<a href="mailto:%s">%s</a>',
                esc_attr($item->email),
                __('Email', 'arbe-events')
            ),
        ];
        
        if ($item->status !== 'cancelled') {
            $actions['cancel'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    add_query_arg(['action' => 'cancel', 'registration_id' => $item->id]),
                    'ae_cancel_registration'
                ),
                esc_js(__('Are you sure you want to cancel this registration?', 'arbe-events')),
                __('Cancel', 'arbe-events')
            );
        }
        
        $actions['delete'] = sprintf(
            '<a href="%s" class="delete" onclick="return confirm(\'%s\');">%s</a>',
            wp_nonce_url(
                add_query_arg(['action' => 'delete', 'registration_id' => $item->id]),
                'ae_delete_registration'
            ),
            esc_js(__('Are you sure you want to permanently delete this registration?', 'arbe-events')),
            __('Delete', 'arbe-events')
        );
        
        return sprintf('%1$s %2$s',
            '<strong>' . esc_html($item->name) . '</strong>',
            $this->row_actions($actions)
        );
    }
    
    /**
     * Email column.
     */
    public function column_email($item) {
        return sprintf(
            '<a href="mailto:%1$s">%1$s</a>',
            esc_html($item->email)
        );
    }
    
    /**
     * Event column.
     */
    public function column_event($item) {
        if (!empty($item->event_title)) {
            return sprintf(
                '<a href="%s">%s</a>',
                admin_url('post.php?post=' . $item->event_id . '&action=edit'),
                esc_html($item->event_title)
            );
        }
        
        $event = get_post($item->event_id);
        if ($event) {
            return sprintf(
                '<a href="%s">%s</a>',
                admin_url('post.php?post=' . $item->event_id . '&action=edit'),
                esc_html($event->post_title)
            );
        }
        
        return '—';
    }
    
    /**
     * Status column.
     */
    public function column_status($item) {
        $status_labels = [
            'confirmed' => __('Confirmed', 'arbe-events'),
            'waitlist'  => __('Waitlist', 'arbe-events'),
            'cancelled' => __('Cancelled', 'arbe-events'),
            'pending'   => __('Pending', 'arbe-events'),
        ];
        
        $status_colors = [
            'confirmed' => '#46b450',
            'waitlist'  => '#f0ad4e',
            'cancelled' => '#dc3232',
            'pending'   => '#999999',
        ];
        
        $label = isset($status_labels[$item->status]) ? $status_labels[$item->status] : $item->status;
        $color = isset($status_colors[$item->status]) ? $status_colors[$item->status] : '#333';
        
        return sprintf(
            '<span style="color: %s; font-weight: bold;">%s</span>',
            $color,
            esc_html($label)
        );
    }
    
    /**
     * Display views (filters).
     */
    public function get_views() {
        $views = [];
        $current = isset($_GET['status']) ? $_GET['status'] : 'all';
        
        $base_url = admin_url('admin.php?page=ae-registrations');
        if ($this->event_id) {
            $base_url = add_query_arg('event_id', $this->event_id, $base_url);
        }
        
        $statuses = [
            'all'       => __('All', 'arbe-events'),
            'confirmed' => __('Confirmed', 'arbe-events'),
            'waitlist'  => __('Waitlist', 'arbe-events'),
            'cancelled' => __('Cancelled', 'arbe-events'),
        ];
        
        foreach ($statuses as $status => $label) {
            $url = $status === 'all' ? $base_url : add_query_arg('status', $status, $base_url);
            $class = $current === $status ? ' class="current"' : '';
            
            if ($this->event_id) {
                $count = $status === 'all' 
                    ? $this->db->get_registration_count($this->event_id)
                    : $this->db->get_registration_count($this->event_id, $status);
            } else {
                $args = $status === 'all' ? [] : ['status' => $status];
                $result = $this->db->get_all_registrations(array_merge($args, ['limit' => 1]));
                $count = $result['total'];
            }
            
            $views[$status] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                $url,
                $class,
                $label,
                $count
            );
        }
        
        return $views;
    }
    
    /**
     * Extra table navigation.
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions">
                <?php if (!$this->event_id) : ?>
                    <select name="event_id" id="filter-by-event">
                        <option value=""><?php _e('All Events', 'arbe-events'); ?></option>
                        <?php
                        $events = get_posts([
                            'post_type' => 'event',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC',
                        ]);
                        foreach ($events as $event) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                $event->ID,
                                selected($this->event_id, $event->ID, false),
                                esc_html($event->post_title)
                            );
                        }
                        ?>
                    </select>
                    <?php submit_button(__('Filter', 'arbe-events'), '', 'filter_action', false); ?>
                <?php endif; ?>
                
                <?php
                $export_url = wp_nonce_url(
                    add_query_arg([
                        'page' => 'ae-registrations',
                        'action' => 'export',
                        'event_id' => $this->event_id
                    ], admin_url('admin.php')),
                    'ae_export_csv'
                );
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button">
                    <?php _e('Export All to CSV', 'arbe-events'); ?>
                </a>
            </div>
            <?php
        }
    }
    
    /**
     * Display when no items found.
     */
    public function no_items() {
        _e('No registrations found.', 'arbe-events');
    }
    
    /**
     * Add admin notice.
     */
    private function add_admin_notice($message, $type = 'success') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
    
    /**
     * Export registrations to CSV.
     */
    private function export_registrations($registration_ids = []) {
        if (empty($registration_ids)) {
            if ($this->event_id) {
                $registrations = $this->db->get_event_registrations($this->event_id);
            } else {
                $result = $this->db->get_all_registrations(['limit' => -1]);
                $registrations = $result['registrations'];
            }
        } else {
            $registrations = [];
            foreach ($registration_ids as $id) {
                $registration = $this->db->get_registration($id);
                if ($registration) {
                    $registrations[] = $registration;
                }
            }
        }
        
        require_once AE_PLUGIN_PATH . 'includes/class-csv-exporter.php';
        $exporter = new AE_CSV_Exporter();
        $exporter->export($registrations);
    }
}