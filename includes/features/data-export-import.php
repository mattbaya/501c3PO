<?php
/**
 * Data Export/Import Feature Module
 * Handles CSV export/import of member data and complete database backups
 */

// Prevent direct access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Add Export/Import admin page
 */
add_action('admin_menu', 'five01c3po_add_export_import_menu', 20);

function five01c3po_add_export_import_menu() {
    add_submenu_page(
        'membership-management',
        'Export & Import',
        'Export & Import',
        'manage_options',
        '501c3PO-export-import',
        'five01c3po_export_import_page'
    );
}

/**
 * Render Export/Import admin page
 */
function five01c3po_export_import_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'c3_members'; // TODO: Make dynamic with org prefix

    // Handle CSV import
    if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
        check_admin_referer('five01c3po_import_csv');
        $result = five01c3po_import_members_csv($_FILES['csv_file']['tmp_name']);
        echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
    }

    // Handle CSV export
    if (isset($_POST['export_csv'])) {
        check_admin_referer('five01c3po_export_csv');
        five01c3po_export_members_csv();
        return;
    }

    // Get member count
    $member_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    ?>
    <div class="wrap">
        <h1>📤 Export & Import Member Data</h1>

        <div class="card">
            <h2>Current Status</h2>
            <p><strong>Members in database:</strong> <?php echo esc_html($member_count); ?></p>
            <p><strong>Database table:</strong> <code><?php echo esc_html($table_name); ?></code></p>
        </div>

        <div class="card">
            <h2>📥 Import Members from CSV</h2>
            <p>Upload a CSV file with member data to import into the database.</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('five01c3po_import_csv'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">CSV File</th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv" required>
                            <p class="description">Upload a CSV file with member data. The first row should contain column headers.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Import CSV', 'primary', 'import_csv'); ?>
            </form>
        </div>

        <div class="card">
            <h2>📤 Export Members to CSV</h2>
            <p>Export all member data to a CSV file for backup or use in other applications.</p>
            <form method="post">
                <?php wp_nonce_field('five01c3po_export_csv'); ?>
                <p>This will export all members with all fields to a downloadable CSV file.</p>
                <?php submit_button('Export All Members to CSV', 'secondary', 'export_csv'); ?>
            </form>
        </div>

        <div class="card">
            <h2>📊 Recent Members</h2>
            <?php five01c3po_display_recent_members(); ?>
        </div>
    </div>
    <?php
}

/**
 * Import members from CSV file
 */
function five01c3po_import_members_csv($file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'c3_members'; // TODO: Make dynamic with org prefix

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

    // Clean headers
    $headers = array_map('trim', $headers);

    $imported = 0;
    $updated = 0;
    $errors = 0;

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < count($headers)) {
            $errors++;
            continue;
        }

        // Map data to columns
        $member_data = array();
        for ($i = 0; $i < count($headers); $i++) {
            $column = $headers[$i];
            $value = isset($data[$i]) ? trim($data[$i]) : '';

            // Skip ID column for new inserts
            if (strtolower($column) === 'id') {
                continue;
            }

            $member_data[$column] = $value;
        }

        // Check if member exists by email
        if (!empty($member_data['email_1'])) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE email_1 = %s",
                $member_data['email_1']
            ));

            if ($existing) {
                // Update existing member
                $result = $wpdb->update($table_name, $member_data, array('id' => $existing));
                if ($result !== false) {
                    $updated++;
                } else {
                    $errors++;
                }
                continue;
            }
        }

        // Insert new member
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

    return "Import complete: $imported new members, $updated updated, $errors errors";
}

/**
 * Export members to CSV file
 */
function five01c3po_export_members_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'c3_members'; // TODO: Make dynamic with org prefix

    $members = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_name, first_name", ARRAY_A);

    if (empty($members)) {
        wp_die('No members found to export');
    }

    $filename = 'members_export_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write headers
    fputcsv($output, array_keys($members[0]));

    // Write data
    foreach ($members as $member) {
        fputcsv($output, $member);
    }

    fclose($output);
    exit;
}

/**
 * Display recent members in admin
 */
function five01c3po_display_recent_members() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'c3_members'; // TODO: Make dynamic with org prefix

    $members = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 10");

    if (empty($members)) {
        echo '<p>No members found in database.</p>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>City</th><th>Status</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($members as $member) {
        echo '<tr>';
        echo '<td>' . esc_html($member->id) . '</td>';
        echo '<td>' . esc_html($member->first_name . ' ' . $member->last_name) . '</td>';
        echo '<td>' . esc_html($member->email_1) . '</td>';
        echo '<td>' . esc_html($member->phone) . '</td>';
        echo '<td>' . esc_html($member->city) . '</td>';
        echo '<td>' . esc_html($member->status_2024_2025 ?? 'N/A') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p><em>Showing 10 most recent members</em></p>';
}
