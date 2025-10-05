#!/usr/local/bin/php
<?php
// Load WordPress
require_once('/home/swca/public_html/wp-load.php');

global $wpdb;

// Create bank transactions table
$bank_table = $wpdb->prefix . 'swca_bank_transactions';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS $bank_table (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    account_number varchar(50) DEFAULT '' NOT NULL,
    post_date date NOT NULL,
    check_number varchar(20) DEFAULT '' NOT NULL,
    description text DEFAULT '' NOT NULL,
    debit decimal(10,2) DEFAULT 0.00,
    credit decimal(10,2) DEFAULT 0.00,
    status varchar(50) DEFAULT '' NOT NULL,
    balance decimal(10,2) DEFAULT 0.00,
    imported_date datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY post_date (post_date)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

echo "Table created successfully.\n";

// Import CSV
$csv_file = '/home/swca/scripts/501c3PO/treasurer-docs/MoutainOne Bank AccountHistory_Jan - Sept 2025.csv';

if (!file_exists($csv_file)) {
    die("CSV file not found: $csv_file\n");
}

$handle = fopen($csv_file, 'r');
$headers = fgetcsv($handle);

echo "Headers: " . implode(', ', $headers) . "\n";

$imported = 0;
$errors = 0;

while (($data = fgetcsv($handle)) !== FALSE) {
    if (count($data) < count($headers)) {
        $errors++;
        continue;
    }

    $row = array_combine($headers, $data);

    // Parse date
    $date = DateTime::createFromFormat('n/j/Y', $row['Post Date']);
    if (!$date) {
        echo "Date parse error for: " . $row['Post Date'] . "\n";
        $errors++;
        continue;
    }

    // Prepare data
    $insert_data = [
        'account_number' => $row['Account Number'] ?? '',
        'post_date' => $date->format('Y-m-d'),
        'check_number' => $row['Check'] ?? '',
        'description' => $row['Description'] ?? '',
        'debit' => !empty($row['Debit']) ? floatval($row['Debit']) : 0,
        'credit' => !empty($row['Credit']) ? floatval($row['Credit']) : 0,
        'status' => $row['Status'] ?? '',
        'balance' => !empty($row['Balance']) ? floatval($row['Balance']) : 0,
    ];

    $result = $wpdb->insert($bank_table, $insert_data);
    if ($result) {
        $imported++;
    } else {
        echo "Insert error: " . $wpdb->last_error . "\n";
        $errors++;
    }
}

fclose($handle);

echo "\nImport complete:\n";
echo "- Imported: $imported transactions\n";
echo "- Errors: $errors\n";

// Verify
$count = $wpdb->get_var("SELECT COUNT(*) FROM $bank_table");
echo "- Total in database: $count\n";
?>
