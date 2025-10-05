<?php
/**
 * Import 2023-2024 SWCA Membership data
 */

// Set WordPress environment
$wp_path = '/var/www/html';
require_once($wp_path . '/wp-config.php');
require_once($wp_path . '/wp-load.php');

echo "=== IMPORTING 2023-2024 SWCA MEMBERSHIP DATA ===\n\n";

// First, let's check the current database structure
global $wpdb;

// Check if we need to add a column for 2023-2024 status
$columns = $wpdb->get_results("SHOW COLUMNS FROM wp_swca_members");
$has_2023_status = false;
foreach ($columns as $column) {
    if ($column->Field == 'status_2023_2024') {
        $has_2023_status = true;
        break;
    }
}

if (!$has_2023_status) {
    echo "Adding status_2023_2024 column to database...\n";
    $wpdb->query("ALTER TABLE wp_swca_members ADD COLUMN status_2023_2024 VARCHAR(50) DEFAULT NULL");
    echo "Column added successfully.\n\n";
}

// Also add membership_month_2023 if it doesn't exist
$has_2023_month = false;
foreach ($columns as $column) {
    if ($column->Field == 'membership_month_2023') {
        $has_2023_month = true;
        break;
    }
}

if (!$has_2023_month) {
    echo "Adding membership_month_2023 column to database...\n";
    $wpdb->query("ALTER TABLE wp_swca_members ADD COLUMN membership_month_2023 DATE DEFAULT NULL");
    echo "Column added successfully.\n\n";
}

// Read and process the CSV data
$csv_file = '/home/developer/2023_members.csv';
if (!file_exists($csv_file)) {
    echo "Error: CSV file not found at $csv_file\n";
    exit(1);
}

$handle = fopen($csv_file, 'r');
if (!$handle) {
    echo "Error: Cannot open CSV file\n";
    exit(1);
}

$row_count = 0;
$updated_count = 0;
$new_count = 0;
$error_count = 0;

// Skip header
fgetcsv($handle);

while (($data = fgetcsv($handle)) !== FALSE) {
    $row_count++;
    
    // Map CSV columns to database fields
    $membership_month = $data[0];
    $last_name = trim($data[1]);
    $first_name = trim($data[2]);
    $partner_last_name = trim($data[3]);
    $partner_first_name = trim($data[4]);
    $family_members = trim($data[5]);
    $address = trim($data[6]);
    $city = trim($data[7]);
    $state = trim($data[8]);
    $zip = trim($data[9]);
    $phone = trim($data[10]);
    $membership_amount = floatval($data[11]);
    $donation_amount = floatval($data[12]);
    $type = trim($data[13]);
    $email1 = trim($data[14]);
    $email2 = trim($data[15]);
    $email3 = trim($data[16]);
    $email4 = trim($data[17]);
    
    // Skip empty rows
    if (empty($last_name) && empty($first_name)) {
        continue;
    }
    
    // Parse membership month (Excel date format)
    $membership_date = null;
    if (!empty($membership_month) && is_numeric($membership_month)) {
        // Excel date serial number to PHP date
        $unix_timestamp = ($membership_month - 25569) * 86400;
        $membership_date = date('Y-m-d', $unix_timestamp);
    }
    
    // Determine status based on membership amount
    $status_2023 = ($membership_amount > 0) ? 'Paid' : 'Unpaid';
    
    // Try to find existing member by name and email
    $existing_member = null;
    
    // First try exact match on name
    if (!empty($last_name) && !empty($first_name)) {
        $existing_member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wp_swca_members WHERE last_name = %s AND first_name = %s",
            $last_name, $first_name
        ));
    }
    
    // If not found, try by primary email
    if (!$existing_member && !empty($email1)) {
        $existing_member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wp_swca_members WHERE email_1 = %s",
            $email1
        ));
    }
    
    if ($existing_member) {
        // Update existing member with 2023-2024 data
        $update_data = array(
            'status_2023_2024' => $status_2023,
            'membership_month_2023' => $membership_date
        );
        
        // Update address if provided and current is empty
        if (!empty($address) && empty($existing_member->address)) {
            $update_data['address'] = $address;
        }
        if (!empty($city) && empty($existing_member->city)) {
            $update_data['city'] = $city;
        }
        if (!empty($state) && empty($existing_member->state)) {
            $update_data['state'] = $state;
        }
        if (!empty($zip) && empty($existing_member->zip_code)) {
            $update_data['zip_code'] = $zip;
        }
        if (!empty($phone) && empty($existing_member->phone)) {
            $update_data['phone'] = $phone;
        }
        
        $result = $wpdb->update(
            'wp_swca_members',
            $update_data,
            array('id' => $existing_member->id)
        );
        
        if ($result !== false) {
            $updated_count++;
            echo "Updated: $first_name $last_name (2023-2024: $status_2023)\n";
        } else {
            $error_count++;
            echo "Error updating: $first_name $last_name\n";
        }
    } else {
        // Insert new member (only from 2023 data)
        $insert_data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'partner_first_name' => $partner_first_name,
            'partner_last_name' => $partner_last_name,
            'family_members' => $family_members,
            'email_1' => $email1,
            'email_2' => $email2,
            'email_3' => $email3,
            'email_4' => $email4,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zip,
            'membership_type' => (!empty($partner_first_name) || !empty($family_members)) ? 'Family' : 'Individual',
            'status_2023_2024' => $status_2023,
            'membership_month_2023' => $membership_date,
            'membership_amount' => $membership_amount,
            'donation_amount' => $donation_amount
        );
        
        $result = $wpdb->insert('wp_swca_members', $insert_data);
        
        if ($result !== false) {
            $new_count++;
            echo "Added new: $first_name $last_name (2023-2024: $status_2023)\n";
        } else {
            $error_count++;
            echo "Error adding: $first_name $last_name\n";
        }
    }
}

fclose($handle);

echo "\n=== IMPORT SUMMARY ===\n";
echo "Total rows processed: $row_count\n";
echo "Existing members updated: $updated_count\n";
echo "New members added: $new_count\n";
echo "Errors: $error_count\n";

// Show updated statistics
$stats = $wpdb->get_row("
    SELECT 
        COUNT(*) as total_members,
        SUM(CASE WHEN status_2024_2025 = 'Paid' THEN 1 ELSE 0 END) as paid_2024,
        SUM(CASE WHEN status_2023_2024 = 'Paid' THEN 1 ELSE 0 END) as paid_2023,
        SUM(CASE WHEN status_2024_2025 = 'Paid' AND status_2023_2024 = 'Paid' THEN 1 ELSE 0 END) as paid_both_years
    FROM wp_swca_members
");

echo "\n=== DATABASE STATISTICS ===\n";
echo "Total members in database: " . $stats->total_members . "\n";
echo "Paid 2024-2025: " . $stats->paid_2024 . "\n";
echo "Paid 2023-2024: " . $stats->paid_2023 . "\n";
echo "Paid both years: " . $stats->paid_both_years . "\n";

echo "\n=== IMPORT COMPLETE ===\n";
?>