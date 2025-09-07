<?php
/**
 * Utility/helper functions for the plugin.
 */

function ae_log($data) {
    if (WP_DEBUG === true) {
        error_log(print_r($data, true));
    }
}