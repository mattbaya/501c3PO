<?php
/*
Plugin Name: SWCA Simple Export Import
Description: Simple CSV export and import for SWCA member data
Version: 1.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Create table on activation
register_activation_hook(__FILE__, 'swca_simple_create_table');

function swca_simple_create_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'swca_members';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) DEFAULT '' NOT NULL,
        last_name varchar(100) DEFAULT '' NOT NULL,
        email_1 varchar(100) DEFAULT '' NOT NULL,
        phone varchar(20) DEFAULT '' NOT NULL,
        address text DEFAULT '' NOT NULL,
        city varchar(100) DEFAULT '' NOT NULL,
        state varchar(10) DEFAULT '' NOT NULL,
        zip_code varchar(10) DEFAULT '' NOT NULL,
        membership_type varchar(50) DEFAULT '' NOT NULL,
        status_2024_2025 varchar(50) DEFAULT '' NOT NULL,
        membership_amount decimal(10,2) DEFAULT 0.00,
        total_amount decimal(10,2) DEFAULT 0.00,
        payment_type varchar(50) DEFAULT '' NOT NULL,
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add admin menu
add_action('admin_menu', 'swca_simple_admin_menu');

function swca_simple_admin_menu() {
    add_menu_page(
        'SWCA Import/Export',
        'SWCA Data',
        'manage_options',
        'swca-simple',
        'swca_simple_admin_page',
        'dashicons-database-export'
    );
}

// Admin page
function swca_simple_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';
    
    // Handle import
    if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $result = swca_simple_import_csv($_FILES['csv_file']['tmp_name']);
        echo '<div class="notice notice-success"><p>' . $result . '</p></div>';
    }
    
    // Handle export
    if (isset($_POST['export_csv'])) {
        swca_simple_export_csv();
        return;
    }
    
    // Get member count
    $member_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    ?>
    <div class="wrap">
        <h1>SWCA Simple Import/Export</h1>
        
        <div class="card">
            <h2>Current Status</h2>
            <p><strong>Members in database:</strong> <?php echo $member_count; ?></p>
            <p><strong>Table name:</strong> <?php echo $table_name; ?></p>
        </div>
        
        <div class="card">
            <h2>Import CSV</h2>
            <form method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">CSV File</th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv" required>
                            <p class="description">Upload a CSV file with member data</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Import CSV', 'primary', 'import_csv'); ?>
            </form>
        </div>
        
        <div class="card">
            <h2>Export CSV</h2>
            <form method="post">
                <p>Export all member data to CSV file.</p>
                <?php submit_button('Export CSV', 'secondary', 'export_csv'); ?>
            </form>
        </div>
        
        <div class="card">
            <h2>View Members</h2>
            <?php swca_simple_display_members(); ?>
        </div>
    </div>
    <?php
}

function swca_simple_import_csv($file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';
    
    if (!file_exists($file_path)) {
        return 'File not found';
    }
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return 'Could not open file';
    }
    
    // Get headers
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return 'Could not read headers';
    }
    
    $imported = 0;
    $errors = 0;
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < count($headers)) {
            $errors++;
            continue;
        }
        
        // Map data to columns
        $member_data = array();
        for ($i = 0; $i < count($headers); $i++) {
            $column = trim($headers[$i]);
            $value = isset($data[$i]) ? trim($data[$i]) : '';
            
            // Skip ID column
            if (strtolower($column) === 'id') {
                continue;
            }
            
            $member_data[$column] = $value;
        }
        
        // Insert into database
        if (!empty($member_data)) {
            $result = $wpdb->insert($table_name, $member_data);
            if ($result) {
                $imported++;
            } else {
                $errors++;
            }
        }
    }
    
    fclose($handle);
    
    return "Import complete: $imported members imported, $errors errors";
}

function swca_simple_export_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';
    
    $members = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    
    if (empty($members)) {
        wp_die('No members found to export');
    }
    
    $filename = 'swca_members_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, array_keys($members[0]));
    
    // Write data
    foreach ($members as $member) {
        fputcsv($output, $member);
    }
    
    fclose($output);
    exit;
}

function swca_simple_display_members() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';
    
    $members = $wpdb->get_results("SELECT * FROM $table_name LIMIT 10");
    
    if (empty($members)) {
        echo '<p>No members found in database.</p>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($members as $member) {
        echo '<tr>';
        echo '<td>' . $member->id . '</td>';
        echo '<td>' . $member->first_name . ' ' . $member->last_name . '</td>';
        echo '<td>' . $member->email_1 . '</td>';
        echo '<td>' . $member->phone . '</td>';
        echo '<td>' . $member->status_2024_2025 . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '<p><em>Showing first 10 members</em></p>';
}

// Simple shortcode for displaying member directory
add_shortcode('swca_member_directory', 'swca_simple_member_directory_shortcode');

function swca_simple_member_directory_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'swca_members';
    
    $members = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_name, first_name");
    
    if (empty($members)) {
        return '<p>No members found.</p>';
    }
    
    $output = '<div class="swca-member-directory">';
    $output .= '<h3>SWCA Member Directory</h3>';
    $output .= '<p>Total members: ' . count($members) . '</p>';
    
    $output .= '<table class="swca-directory-table">';
    $output .= '<thead><tr>';
    $output .= '<th>Name</th><th>Email</th><th>Phone</th><th>City</th><th>Status</th>';
    $output .= '</tr></thead>';
    $output .= '<tbody>';
    
    foreach ($members as $member) {
        $output .= '<tr>';
        $output .= '<td>' . esc_html($member->first_name . ' ' . $member->last_name) . '</td>';
        $output .= '<td>' . esc_html($member->email_1) . '</td>';
        $output .= '<td>' . esc_html($member->phone) . '</td>';
        $output .= '<td>' . esc_html($member->city) . '</td>';
        $output .= '<td>' . esc_html($member->status_2024_2025) . '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</tbody></table>';
    $output .= '</div>';
    
    // Add basic CSS
    $output .= '<style>
    .swca-directory-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .swca-directory-table th, .swca-directory-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    .swca-directory-table th { background-color: #f2f2f2; font-weight: bold; }
    .swca-directory-table tr:nth-child(even) { background-color: #f9f9f9; }
    </style>';
    
    return $output;
}