#!/usr/local/bin/php
<?php
/**
 * Explore Gravity Forms data in WordPress database
 */

$db_host = 'localhost';
$db_name = 'swca_swca2019';
$db_user = 'swca_swca2019';
$db_pass = '5Corners!';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database: $db_name\n\n";

    // Find Gravity Forms tables
    echo "=== GRAVITY FORMS TABLES ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE '%gf%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "No Gravity Forms tables found!\n";
        exit;
    }

    foreach ($tables as $table) {
        echo "- $table\n";
    }

    // Find the forms
    echo "\n=== GRAVITY FORMS LIST ===\n";
    $forms_table = null;
    foreach ($tables as $table) {
        if (strpos($table, '_gf_form') !== false && strpos($table, '_meta') === false) {
            $forms_table = $table;
            break;
        }
    }

    if ($forms_table) {
        $stmt = $pdo->query("SELECT id, title, is_active FROM $forms_table ORDER BY id");
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($forms as $form) {
            $active = $form['is_active'] ? 'Active' : 'Inactive';
            echo "Form ID: {$form['id']} | Title: {$form['title']} | Status: $active\n";
        }

        // Find the specific forms we want
        echo "\n=== TARGET FORMS ===\n";
        $target_forms = [];
        foreach ($forms as $form) {
            $title = strtolower($form['title']);
            if (strpos($title, 'membership 2025') !== false ||
                strpos($title, 'donate') !== false ||
                strpos($title, 'membership - old') !== false ||
                strpos($title, 'membership-old') !== false) {
                $target_forms[] = $form;
                echo "✓ Found: {$form['title']} (ID: {$form['id']})\n";
            }
        }

        if (empty($target_forms)) {
            echo "No matching forms found. Searching for any forms with 'membership' or 'donate'...\n";
            foreach ($forms as $form) {
                $title = strtolower($form['title']);
                if (strpos($title, 'membership') !== false || strpos($title, 'donate') !== false) {
                    echo "- {$form['title']} (ID: {$form['id']})\n";
                }
            }
        } else {
            // Get entry count for each target form
            $entry_table = str_replace('_gf_form', '_gf_entry', $forms_table);
            echo "\n=== ENTRY COUNTS ===\n";

            foreach ($target_forms as $form) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $entry_table WHERE form_id = ? AND status = 'active'");
                $stmt->execute([$form['id']]);
                $count = $stmt->fetchColumn();
                echo "Form '{$form['title']}': $count entries\n";
            }

            // Show sample entries
            echo "\n=== SAMPLE ENTRIES ===\n";
            foreach ($target_forms as $form) {
                echo "\nForm: {$form['title']} (ID: {$form['id']})\n";

                $stmt = $pdo->prepare("SELECT * FROM $entry_table WHERE form_id = ? AND status = 'active' LIMIT 3");
                $stmt->execute([$form['id']]);
                $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($entries as $entry) {
                    echo "  Entry ID: {$entry['id']} | Date: {$entry['date_created']} | IP: {$entry['ip']}\n";
                }
            }
        }
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
