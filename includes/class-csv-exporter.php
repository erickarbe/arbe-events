<?php
/**
 * CSV Exporter - Handles exporting registration data to CSV format.
 */
class AE_CSV_Exporter {
    
    /**
     * Export registrations to CSV.
     * 
     * @param array $registrations Array of registration objects
     * @param string $filename Optional filename
     */
    public function export($registrations, $filename = null) {
        if (empty($registrations)) {
            wp_die(__('No registrations to export.', 'arbe-events'));
        }
        
        if (!$filename) {
            $filename = 'event-registrations-' . date('Y-m-d-His') . '.csv';
        }
        
        $this->send_headers($filename);
        
        $output = fopen('php://output', 'w');
        
        $this->write_utf8_bom($output);
        
        $headers = $this->get_csv_headers();
        fputcsv($output, $headers);
        
        foreach ($registrations as $registration) {
            $row = $this->prepare_row($registration);
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export registrations for a specific event.
     * 
     * @param int $event_id Event ID
     */
    public function export_event_registrations($event_id) {
        require_once AE_PLUGIN_PATH . 'includes/class-database.php';
        $db = new AE_Database();
        
        $registrations = $db->get_event_registrations($event_id);
        
        if (empty($registrations)) {
            wp_die(__('No registrations found for this event.', 'arbe-events'));
        }
        
        $event = get_post($event_id);
        $filename = 'registrations-' . sanitize_file_name($event->post_title) . '-' . date('Y-m-d') . '.csv';
        
        $this->export($registrations, $filename);
    }
    
    /**
     * Send CSV headers.
     * 
     * @param string $filename CSV filename
     */
    private function send_headers($filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        
        nocache_headers();
    }
    
    /**
     * Write UTF-8 BOM for Excel compatibility.
     * 
     * @param resource $output File handle
     */
    private function write_utf8_bom($output) {
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    }
    
    /**
     * Get CSV column headers.
     * 
     * @return array Column headers
     */
    private function get_csv_headers() {
        $headers = [
            __('Registration ID', 'arbe-events'),
            __('Event', 'arbe-events'),
            __('Name', 'arbe-events'),
            __('Email', 'arbe-events'),
            __('Phone', 'arbe-events'),
            __('Status', 'arbe-events'),
            __('Registration Date', 'arbe-events'),
            __('Event Date', 'arbe-events'),
            __('Event Time', 'arbe-events'),
            __('Event Location', 'arbe-events'),
        ];
        
        return apply_filters('ae_csv_export_headers', $headers);
    }
    
    /**
     * Prepare registration row for CSV.
     * 
     * @param object $registration Registration object
     * @return array Row data
     */
    private function prepare_row($registration) {
        $event = get_post($registration->event_id);
        
        if ($event) {
            $event_title = $event->post_title;
            $start_date = get_post_meta($event->ID, '_ae_event_start_date', true);
            $start_time = get_post_meta($event->ID, '_ae_event_start_time', true);
            $venue = get_post_meta($event->ID, '_ae_event_venue', true);
            $address = get_post_meta($event->ID, '_ae_event_address', true);
            $city = get_post_meta($event->ID, '_ae_event_city', true);
            $state = get_post_meta($event->ID, '_ae_event_state', true);
            
            $location_parts = array_filter([$venue, $address, $city, $state]);
            $location = implode(', ', $location_parts);
            
            $event_date = $start_date ? date_i18n(get_option('date_format'), strtotime($start_date)) : '';
            $event_time = $start_time ? date_i18n(get_option('time_format'), strtotime($start_time)) : '';
        } else {
            $event_title = '';
            $event_date = '';
            $event_time = '';
            $location = '';
        }
        
        $status_labels = [
            'confirmed' => __('Confirmed', 'arbe-events'),
            'waitlist'  => __('Waitlist', 'arbe-events'),
            'cancelled' => __('Cancelled', 'arbe-events'),
            'pending'   => __('Pending', 'arbe-events'),
        ];
        
        $status = isset($status_labels[$registration->status]) 
            ? $status_labels[$registration->status] 
            : $registration->status;
        
        $row = [
            $registration->id,
            $event_title,
            $registration->name,
            $registration->email,
            $registration->phone ?: '',
            $status,
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($registration->registration_date)),
            $event_date,
            $event_time,
            $location,
        ];
        
        return apply_filters('ae_csv_export_row', $row, $registration);
    }
    
    /**
     * Handle CSV export request.
     */
    public static function handle_export_request() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export') {
            return;
        }
        
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ae_export_csv')) {
            wp_die(__('Security check failed', 'arbe-events'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export registrations.', 'arbe-events'));
        }
        
        $exporter = new self();
        
        if (isset($_GET['registration_id'])) {
            require_once AE_PLUGIN_PATH . 'includes/class-database.php';
            $db = new AE_Database();
            
            $registration = $db->get_registration(absint($_GET['registration_id']));
            if ($registration) {
                $exporter->export([$registration]);
            } else {
                wp_die(__('Registration not found.', 'arbe-events'));
            }
        } elseif (isset($_GET['event_id']) && $_GET['event_id']) {
            $exporter->export_event_registrations(absint($_GET['event_id']));
        } else {
            require_once AE_PLUGIN_PATH . 'includes/class-database.php';
            $db = new AE_Database();
            
            $args = ['limit' => -1];
            
            if (isset($_GET['status']) && $_GET['status'] !== 'all') {
                $args['status'] = sanitize_text_field($_GET['status']);
            }
            
            $result = $db->get_all_registrations($args);
            $exporter->export($result['registrations']);
        }
    }
}