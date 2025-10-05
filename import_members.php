#!/usr/bin/env php
<?php
/**
 * Import SWCA Members from Treasurer's Excel file
 */

// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

global $wpdb;

// Check if table exists
$table_name = $wpdb->prefix . 'swca_members';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

echo "Checking for table: $table_name\n";
if ($table_exists) {
    echo "✓ Table exists\n";

    // Get current count
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "Current member count: $count\n\n";

    // Show table structure
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    echo "Table columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type})\n";
    }
} else {
    echo "✗ Table does not exist\n";
    echo "You may need to activate the SWCA plugin first.\n";
    exit(1);
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Ready to import members from Excel file\n";
echo str_repeat("=", 60) . "\n\n";

// Now parse the Excel file
$excelFile = '/home/swca/scripts/501c3PO/treasurer-docs/2024-2025 SWCA Membership List for Matt.xlsx';
$jsonFile = '/home/swca/scripts/501c3PO/members_data.json';

// Check if we already parsed it
if (!file_exists($jsonFile)) {
    echo "Please run: node /home/swca/scripts/501c3PO/parse_members.js\n";
    echo "This will create the JSON file needed for import.\n";
    exit(1);
}

// Read the JSON data
$membersData = json_decode(file_get_contents($jsonFile), true);
echo "Found " . count($membersData) . " members to import\n\n";

$imported = 0;
$updated = 0;
$skipped = 0;

foreach ($membersData as $index => $member) {
    // Skip empty rows
    if (empty($member['Last Name']) && empty($member['First Name'])) {
        $skipped++;
        continue;
    }

    $lastName = sanitize_text_field($member['Last Name'] ?? '');
    $firstName = sanitize_text_field($member['First Name'] ?? '');

    // Check if member already exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE last_name = %s AND first_name = %s",
        $lastName, $firstName
    ));

    // Prepare data
    $data = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'partner_first_name' => sanitize_text_field($member['First Name (Partner)'] ?? ''),
        'partner_last_name' => sanitize_text_field($member['Last Name (Partner)'] ?? ''),
        'family_members' => sanitize_text_field($member['Family Members'] ?? ''),
        'email_1' => sanitize_email($member['email 1'] ?? ''),
        'email_2' => sanitize_email($member['email 2'] ?? ''),
        'email_3' => sanitize_email($member['email 3'] ?? ''),
        'email_4' => sanitize_email($member['email 4'] ?? ''),
        'phone' => sanitize_text_field($member['Phone #'] ?? ''),
        'address' => sanitize_text_field($member['Address'] ?? ''),
        'city' => sanitize_text_field($member['City'] ?? ''),
        'state' => sanitize_text_field($member['State'] ?? ''),
        'zip_code' => sanitize_text_field($member['ZIP'] ?? ''),
        'membership_type' => sanitize_text_field($member['Individual or Family'] ?? ''),
        'status_2024_2025' => sanitize_text_field($member['2024-2025 Status'] ?? ''),
        'membership_amount' => floatval($member['Membership Amount'] ?? 0),
        'donation_amount' => floatval($member['Donation Amount'] ?? 0),
        'total_amount' => floatval($member['Total'] ?? 0),
        'payment_type' => sanitize_text_field($member['Type'] ?? ''),
        'on_swca_email_list' => !empty($member['On SWCA email list']) && $member['On SWCA email list'] !== 'No' ? 'Yes' : 'No',
        'notes' => sanitize_textarea_field($member['On Bette\'s List'] ?? ''),
    ];

    // Parse membership month if it's an Excel date number
    if (!empty($member['Membership Month']) && is_numeric($member['Membership Month'])) {
        $excelDate = intval($member['Membership Month']);
        // Excel dates start from 1900-01-01, but Excel has a bug counting 1900 as leap year
        $unixTimestamp = ($excelDate - 25569) * 86400;
        $data['membership_month'] = date('Y-m', $unixTimestamp);
    } elseif (!empty($member['Membership Month'])) {
        $data['membership_month'] = sanitize_text_field($member['Membership Month']);
    }

    if ($existing) {
        // Update existing member
        $wpdb->update($table_name, $data, ['id' => $existing->id]);
        $updated++;
        if ($updated % 50 == 0) {
            echo "Updated $updated members...\n";
        }
    } else {
        // Insert new member
        $wpdb->insert($table_name, $data);
        $imported++;
        if ($imported % 50 == 0) {
            echo "Imported $imported new members...\n";
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Import Complete!\n";
echo str_repeat("=", 60) . "\n";
echo "Imported: $imported new members\n";
echo "Updated: $updated existing members\n";
echo "Skipped: $skipped empty rows\n";
echo "Total processed: " . ($imported + $updated + $skipped) . "\n";
