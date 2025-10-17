#!/usr/bin/env php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

echo "\n=== GRAVITY FORMS TABLE STRUCTURE ===\n\n";

$result = $mysqli->query("DESCRIBE swca_gf_addon_payment_transaction");

echo "Columns:\n";
while ($row = $result->fetch_assoc()) {
    echo sprintf("  %-30s %s\n", $row['Field'], $row['Type']);
}

echo "\n\n=== SAMPLE DATA ===\n\n";

$result = $mysqli->query("SELECT * FROM swca_gf_addon_payment_transaction LIMIT 3");

while ($row = $result->fetch_assoc()) {
    echo "Record:\n";
    foreach ($row as $key => $value) {
        if (!empty($value)) {
            echo sprintf("  %-30s: %s\n", $key, substr($value, 0, 50));
        }
    }
    echo "\n";
}

$mysqli->close();
