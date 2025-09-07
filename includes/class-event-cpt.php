<?php
/**
 * Registers the 'event' custom post type.
 */
class AE_Event_CPT {
    public function __construct() {
        add_action('init', [$this, 'register_event_post_type']);
    }

    public function register_event_post_type() {
        $labels = [
            'name'               => __('Events', 'arbe-events'),
            'singular_name'      => __('Event', 'arbe-events'),
            'add_new'            => __('Add New', 'arbe-events'),
            'add_new_item'       => __('Add New Event', 'arbe-events'),
            'edit_item'          => __('Edit Event', 'arbe-events'),
            'new_item'           => __('New Event', 'arbe-events'),
            'view_item'          => __('View Event', 'arbe-events'),
            'search_items'       => __('Search Events', 'arbe-events'),
            'not_found'          => __('No events found', 'arbe-events'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => ['slug' => 'events'],
            'supports'           => ['title', 'editor', 'thumbnail'],
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-calendar-alt',
        ];

        register_post_type('event', $args);
    }
}