<?php
/*
Plugin Name: Aspire Dev WPForms Export Plugin
Description: Simple interface to view and download all WPForms Pro form entries as CSV.
Version: 1.0
Author: Aspire Dev
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'sdep_add_admin_menu');
function sdep_add_admin_menu() {
    add_menu_page(
        'Form Entries Export',
        'Form Entries Export',
        'manage_options',
        'aspire-wpforms-export',
        'sdep_admin_page',
        'dashicons-download',
        25 // Position just below Dashboard for easy access
    );
}

// Admin page UI
function sdep_admin_page() {
    global $wpdb;

    // Check if WPForms is active
    if (!class_exists('WPForms')) {
        echo '<div class="wrap"><h1>Form Entries Export</h1><p><strong>Error:</strong> Please install and activate WPForms Pro.</p></div>';
        return;
    }

    // Fetch all WPForms entries
    $submissions = $wpdb->get_results(
        "SELECT e.entry_id, e.date, e.form_id, p.post_title AS form_title 
         FROM {$wpdb->prefix}wpforms_entries e 
         LEFT JOIN {$wpdb->posts} p ON e.form_id = p.ID 
         WHERE p.post_type = 'wpforms' 
         ORDER BY e.date DESC"
    );

    // Fetch all unique field names
    $fields = $wpdb->get_col(
        "SELECT DISTINCT f.field_name 
         FROM {$wpdb->prefix}wpforms_entry_fields f 
         INNER JOIN {$wpdb->prefix}wpforms_entries e ON f.entry_id = e.entry_id 
         ORDER BY f.field_name"
    );
    ?>
    <div class="wrap">
        <h1>Form Entries Export</h1>
        <p>View and download all WPForms Pro form entries.</p>
        
        <?php if (empty($submissions)) : ?>
            <p>No form entries found. Submit a WPForms form to generate data.</p>
        <?php else : ?>
            <!-- Entries table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Form</th>
                        <?php foreach ($fields as $field) : ?>
                            <th><?php echo esc_html(ucwords(str_replace('-', ' ', $field))); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission) : 
                        $meta = $wpdb->get_results($wpdb->prepare(
                            "SELECT field_name, value 
                             FROM {$wpdb->prefix}wpforms_entry_fields 
                             WHERE entry_id = %d",
                            $submission->entry_id
                        ));
                        $meta_values = array_fill_keys($fields, '');
                        foreach ($meta as $m) {
                            $meta_values[$m->field_name] = $m->value;
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html($submission->entry_id); ?></td>
                            <td><?php echo esc_html($submission->date); ?></td>
                            <td><?php echo esc_html($submission->form_title ?: 'Unknown Form'); ?></td>
                            <?php foreach ($fields as $field) : ?>
                                <td><?php echo esc_html($meta_values[$field]); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Download button -->
            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=sdep_download_csv')); ?>" 
               class="button button-primary" 
               style="margin-top: 20px;">
                Download All as CSV
            </a>
        <?php endif; ?>
    </div>
    <style>
        .wp-list-table { margin-top: 20px; }
        .wp-list-table th, .wp-list-table td { padding: 10px; }
        .button-primary { margin: 0; }
    </style>
    <?php
}

// AJAX handler for downloading CSV
add_action('wp_ajax_sdep_download_csv', 'sdep_download_csv');
function sdep_download_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;

    // Fetch all WPForms entries
    $submissions = $wpdb->get_results(
        "SELECT e.entry_id, e.date, e.form_id, p.post_title AS form_title 
         FROM {$wpdb->prefix}wpforms_entries e 
         LEFT JOIN {$wpdb->posts} p ON e.form_id = p.ID 
         WHERE p.post_type = 'wpforms' 
         ORDER BY e.date DESC"
    );

    // Fetch all unique field names
    $fields = $wpdb->get_col(
        "SELECT DISTINCT f.field_name 
         FROM {$wpdb->prefix}wpforms_entry_fields f 
         INNER JOIN {$wpdb->prefix}wpforms_entries e ON f.entry_id = e.entry_id 
         ORDER BY f.field_name"
    );

    if (empty($submissions) || empty($fields)) {
        wp_die('No data to export');
    }

    // Create temporary CSV
    $upload_dir = wp_upload_dir();
    $export_dir = trailingslashit($upload_dir['basedir']) . 'wpforms-exports';
    if (!file_exists($export_dir)) {
        wp_mkdir_p($export_dir);
    }
    $filename = 'wpforms-entries-' . date('Ymd_His') . '.csv';
    $file_path = $export_dir . '/' . $filename;

    $file = fopen($file_path, 'w');
    
    // Write headers
    $header = array_merge(['ID', 'Date', 'Form'], $fields);
    fputcsv($file, $header);

    // Write submission data
    foreach ($submissions as $submission) {
        $meta = $wpdb->get_results($wpdb->prepare(
            "SELECT field_name, value 
             FROM {$wpdb->prefix}wpforms_entry_fields 
             WHERE entry_id = %d",
            $submission->entry_id
        ));
        $meta_values = array_fill_keys($fields, '');
        foreach ($meta as $m) {
            $meta_values[$m->field_name] = $m->value;
        }
        $row = array_merge([$submission->entry_id, $submission->date, $submission->form_title ?: 'Unknown Form'], array_values($meta_values));
        fputcsv($file, $row);
    }

    fclose($file);

    // Serve the file
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($file_path);

    // Clean up
    unlink($file_path);
    exit;
}
?>