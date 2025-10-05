<?php
$fp = fopen('/home/swca/scripts/501c3PO/treasurer-docs/stripe_with_names.csv', 'r');
$header = fgetcsv($fp);
$count = 0;
$failed = 0;

echo "Header columns: " . count($header) . "\n";
echo "Looking for 'Created date (UTC)' in header...\n";
$date_col_index = array_search('Created date (UTC)', $header);
echo "Found at index: $date_col_index\n\n";

while (($data = fgetcsv($fp)) !== FALSE) {
    if (count($data) < 5) {
        echo "Row too short: " . count($data) . " columns\n";
        $failed++;
        continue;
    }

    $row = array_combine($header, $data);

    if (empty($row['Created date (UTC)'])) {
        echo "No date for: " . $row['id'] . "\n";
        $failed++;
        continue;
    }

    $date = DateTime::createFromFormat('n/j/Y G:i', $row['Created date (UTC)']);
    if (!$date) {
        $date = DateTime::createFromFormat('n/j/Y H:i', $row['Created date (UTC)']);
    }

    if (!$date) {
        echo "Bad date format for: " . $row['id'] . " - '" . $row['Created date (UTC)'] . "'\n";
        $failed++;
        continue;
    }

    $count++;
}

fclose($fp);
echo "\nSuccessful: $count\n";
echo "Failed: $failed\n";
echo "Total: " . ($count + $failed) . "\n";
?>
