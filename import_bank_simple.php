#!/usr/bin/php
<?php
// Simple bank import without WordPress
$host = 'localhost';
$user = 'swca_swca2019';
$pass = '5Corners!';
$db = 'swca_swca2019';

// Get password from wp-config.php
$wp_config = file_get_contents('/home/swca/public_html/wp-config.php');
if (preg_match("/define\\s*\\(\\s*'DB_PASSWORD'\\s*,\\s*'([^']*)'\\s*\\)/", $wp_config, $matches)) {
    $pass = $matches[1];
}

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

// Create table
$sql = "CREATE TABLE IF NOT EXISTS wp_swca_bank_transactions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci";

if ($mysqli->query($sql)) {
    echo "Table created successfully.\n";
} else {
    echo "Error creating table: " . $mysqli->error . "\n";
}

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

$stmt = $mysqli->prepare("INSERT INTO wp_swca_bank_transactions
    (account_number, post_date, check_number, description, debit, credit, status, balance)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

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

    $account_number = $row['Account Number'] ?? '';
    $post_date = $date->format('Y-m-d');
    $check_number = $row['Check'] ?? '';
    $description = $row['Description'] ?? '';
    $debit = !empty($row['Debit']) ? floatval($row['Debit']) : 0;
    $credit = !empty($row['Credit']) ? floatval($row['Credit']) : 0;
    $status = $row['Status'] ?? '';
    $balance = !empty($row['Balance']) ? floatval($row['Balance']) : 0;

    $stmt->bind_param('ssssddsd', $account_number, $post_date, $check_number, $description, $debit, $credit, $status, $balance);

    if ($stmt->execute()) {
        $imported++;
    } else {
        echo "Insert error: " . $stmt->error . "\n";
        $errors++;
    }
}

$stmt->close();
fclose($handle);

echo "\nImport complete:\n";
echo "- Imported: $imported transactions\n";
echo "- Errors: $errors\n";

// Verify
$result = $mysqli->query("SELECT COUNT(*) as count FROM wp_swca_bank_transactions");
$row = $result->fetch_assoc();
echo "- Total in database: " . $row['count'] . "\n";

$mysqli->close();
?>
