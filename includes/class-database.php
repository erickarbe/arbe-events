<?php
/**
 * Database handler for Arbe Events plugin.
 * 
 * Manages database tables creation, updates, and queries for event registrations.
 */
class AE_Database {
    
    private static $db_version = '1.0.0';
    private static $table_name;
    
    public function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'ae_registrations';
    }
    
    /**
     * Create database tables for the plugin.
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ae_registrations';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) DEFAULT '',
            status varchar(20) DEFAULT 'confirmed',
            registration_date datetime DEFAULT CURRENT_TIMESTAMP,
            meta longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY email (email),
            KEY status (status),
            KEY registration_date (registration_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('ae_db_version', self::$db_version);
    }
    
    /**
     * Check and update database schema if needed.
     */
    public static function check_db_version() {
        $installed_ver = get_option('ae_db_version');
        
        if ($installed_ver !== self::$db_version) {
            self::create_tables();
        }
    }
    
    /**
     * Insert a new registration into the database.
     * 
     * @param array $data Registration data
     * @return int|false Registration ID on success, false on failure
     */
    public function insert_registration($data) {
        global $wpdb;
        
        $defaults = [
            'event_id' => 0,
            'name' => '',
            'email' => '',
            'phone' => '',
            'status' => 'confirmed',
            'registration_date' => current_time('mysql'),
            'meta' => ''
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        if (!empty($data['meta']) && is_array($data['meta'])) {
            $data['meta'] = maybe_serialize($data['meta']);
        }
        
        $result = $wpdb->insert(
            self::$table_name,
            [
                'event_id' => absint($data['event_id']),
                'name' => sanitize_text_field($data['name']),
                'email' => sanitize_email($data['email']),
                'phone' => sanitize_text_field($data['phone']),
                'status' => sanitize_text_field($data['status']),
                'registration_date' => $data['registration_date'],
                'meta' => $data['meta']
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get registrations for a specific event.
     * 
     * @param int $event_id Event ID
     * @param array $args Query arguments
     * @return array Array of registration objects
     */
    public function get_event_registrations($event_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => '',
            'orderby' => 'registration_date',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM " . self::$table_name . " WHERE event_id = %d";
        $query_args = [absint($event_id)];
        
        if (!empty($args['status'])) {
            $query .= " AND status = %s";
            $query_args[] = sanitize_text_field($args['status']);
        }
        
        $query .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        
        if ($args['limit'] > 0) {
            $query .= " LIMIT %d OFFSET %d";
            $query_args[] = absint($args['limit']);
            $query_args[] = absint($args['offset']);
        }
        
        $results = $wpdb->get_results($wpdb->prepare($query, $query_args));
        
        foreach ($results as &$result) {
            if (!empty($result->meta)) {
                $result->meta = maybe_unserialize($result->meta);
            }
        }
        
        return $results;
    }
    
    /**
     * Get a single registration by ID.
     * 
     * @param int $registration_id Registration ID
     * @return object|null Registration object or null if not found
     */
    public function get_registration($registration_id) {
        global $wpdb;
        
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE id = %d",
            absint($registration_id)
        ));
        
        if ($registration && !empty($registration->meta)) {
            $registration->meta = maybe_unserialize($registration->meta);
        }
        
        return $registration;
    }
    
    /**
     * Check if an email is already registered for an event.
     * 
     * @param int $event_id Event ID
     * @param string $email Email address
     * @return bool True if registered, false otherwise
     */
    public function is_email_registered($event_id, $email) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table_name . " 
            WHERE event_id = %d AND email = %s AND status != 'cancelled'",
            absint($event_id),
            sanitize_email($email)
        ));
        
        return $count > 0;
    }
    
    /**
     * Get registration count for an event.
     * 
     * @param int $event_id Event ID
     * @param string $status Optional status filter
     * @return int Registration count
     */
    public function get_registration_count($event_id, $status = '') {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM " . self::$table_name . " WHERE event_id = %d";
        $query_args = [absint($event_id)];
        
        if (!empty($status)) {
            $query .= " AND status = %s";
            $query_args[] = sanitize_text_field($status);
        } else {
            $query .= " AND status != 'cancelled'";
        }
        
        return (int) $wpdb->get_var($wpdb->prepare($query, $query_args));
    }
    
    /**
     * Update registration status.
     * 
     * @param int $registration_id Registration ID
     * @param string $status New status
     * @return bool True on success, false on failure
     */
    public function update_registration_status($registration_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update(
            self::$table_name,
            ['status' => sanitize_text_field($status)],
            ['id' => absint($registration_id)],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a registration.
     * 
     * @param int $registration_id Registration ID
     * @return bool True on success, false on failure
     */
    public function delete_registration($registration_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            self::$table_name,
            ['id' => absint($registration_id)],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get all registrations with pagination.
     * 
     * @param array $args Query arguments
     * @return array Array with 'registrations' and 'total' keys
     */
    public function get_all_registrations($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => '',
            'search' => '',
            'orderby' => 'registration_date',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT r.*, p.post_title as event_title 
                  FROM " . self::$table_name . " r 
                  LEFT JOIN {$wpdb->posts} p ON r.event_id = p.ID 
                  WHERE 1=1";
        
        $count_query = "SELECT COUNT(*) FROM " . self::$table_name . " WHERE 1=1";
        
        $query_args = [];
        $count_args = [];
        
        if (!empty($args['status'])) {
            $query .= " AND r.status = %s";
            $count_query .= " AND status = %s";
            $query_args[] = $count_args[] = sanitize_text_field($args['status']);
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= " AND (r.name LIKE %s OR r.email LIKE %s)";
            $count_query .= " AND (name LIKE %s OR email LIKE %s)";
            $query_args[] = $count_args[] = $search;
            $query_args[] = $count_args[] = $search;
        }
        
        $total = $wpdb->get_var(
            empty($count_args) ? $count_query : $wpdb->prepare($count_query, $count_args)
        );
        
        $query .= " ORDER BY r." . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        $query .= " LIMIT %d OFFSET %d";
        $query_args[] = absint($args['limit']);
        $query_args[] = absint($args['offset']);
        
        $registrations = $wpdb->get_results($wpdb->prepare($query, $query_args));
        
        foreach ($registrations as &$registration) {
            if (!empty($registration->meta)) {
                $registration->meta = maybe_unserialize($registration->meta);
            }
        }
        
        return [
            'registrations' => $registrations,
            'total' => (int) $total
        ];
    }
    
    /**
     * Drop plugin tables.
     */
    public static function drop_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ae_registrations';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        delete_option('ae_db_version');
    }
}