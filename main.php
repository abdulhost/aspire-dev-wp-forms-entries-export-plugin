<?php
/*
Plugin Name: Aspire Dev WPForms Entries Redirect
Description: Adds an admin menu item to directly access the WPForms Pro Entries page.
Version: 1.1
Author: Aspire Dev
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'sdep_add_admin_menu');
function sdep_add_admin_menu() {
    // Log to debug
    error_log('Aspire Dev WPForms Entries Redirect: Adding menu item');

    add_menu_page(
        'Download Form Entries',           // Page title
        'Download Form Entries',           // Menu title
        'read',                   // Capability (temporary for testing)
        'aspire-wpforms-entries', // Menu slug
        'sdep_redirect_to_entries', // Callback function
        'dashicons-download',     // Icon
        25                        // Position (just below Dashboard)
    );
}

// Redirect to WPForms Entries page
function sdep_redirect_to_entries() {
    // Check if WPForms is active
    if (!class_exists('WPForms')) {
        wp_die('WPForms Pro is not installed or active. Please install and activate WPForms Pro.');
    }

    // Redirect to WPForms Entries page
    wp_redirect(admin_url('admin.php?page=wpforms-entries'));
    exit;
}

// Add admin notice if WPForms is inactive
add_action('admin_notices', 'sdep_wpforms_notice');
function sdep_wpforms_notice() {
    if (!class_exists('WPForms')) {
        ?>
        <div class="notice notice-error">
            <p><strong>Aspire Dev WPForms Entries Redirect:</strong> WPForms Pro is not installed or active. Please activate WPForms Pro to use the Form Entries menu.</p>
        </div>
        <?php
    }
}
?>