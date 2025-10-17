#!/usr/bin/env php
<?php
// Quick script to check what transaction tables exist
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "\n=== TRANSACTION TABLES CHECK ===\n\n";

// Check for bank tables
$result = $mysqli->query("SHOW TABLES LIKE '%bank%'");
echo "Bank-related tables:\n";
while ($row = $result->fetch_array()) {
    $table = $row[0];
    $count = $mysqli->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch_object()->cnt;
    echo "  - $table ($count rows)\n";
}

// Check for stripe tables
$result = $mysqli->query("SHOW TABLES LIKE '%stripe%'");
echo "\nStripe-related tables:\n";
while ($row = $result->fetch_array()) {
    $table = $row[0];
    $count = $mysqli->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch_object()->cnt;
    echo "  - $table ($count rows)\n";
}

// Check for transaction match tables
$result = $mysqli->query("SHOW TABLES LIKE '%transaction%'");
echo "\nTransaction-related tables:\n";
while ($row = $result->fetch_array()) {
    $table = $row[0];
    $count = $mysqli->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch_object()->cnt;
    echo "  - $table ($count rows)\n";
}

// Check for gravity forms tables
$result = $mysqli->query("SHOW TABLES LIKE '%gf%payment%'");
echo "\nGravity Forms payment tables:\n";
while ($row = $result->fetch_array()) {
    $table = $row[0];
    $count = $mysqli->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch_object()->cnt;
    echo "  - $table ($count rows)\n";
}

$mysqli->close();
echo "\n";
