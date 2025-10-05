#!/usr/local/bin/php
<?php
/**
 * Match Gravity Forms entries to Stripe transactions
 */

// Load Gravity Forms entries
$gf_file = '/home/swca/scripts/501c3PO/treasurer-docs/gravity_forms_entries.csv';
$stripe_file = '/home/swca/scripts/501c3PO/treasurer-docs/STRIPE unified_payments_Jan - Sept 2025.csv';

echo "=== LOADING DATA ===\n";

// Parse Gravity Forms entries
$gf_entries = [];
if (($handle = fopen($gf_file, "r")) !== FALSE) {
    $header = fgetcsv($handle);
    $header_map = array_flip($header);

    while (($data = fgetcsv($handle)) !== FALSE) {
        $entry = array_combine($header, $data);

        // Extract name from various field formats
        $first_name = '';
        $last_name = '';
        $email = '';

        // Try different field mappings
        if (isset($entry['field_4.3'])) $first_name = $entry['field_4.3'];
        if (isset($entry['field_4.6'])) $last_name = $entry['field_4.6'];
        if (isset($entry['Email'])) $email = $entry['Email'];

        // Also check transaction_id for matching
        $transaction_id = isset($entry['transaction_id']) ? $entry['transaction_id'] : '';

        if ($transaction_id) {
            // Strip "pi_" prefix if present to match both formats
            $txn_key = str_replace(['pi_', 'ch_', 'py_'], '', $transaction_id);

            $gf_entries[$txn_key] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'full_name' => trim($first_name . ' ' . $last_name),
                'entry_id' => $entry['entry_id'],
                'form_title' => $entry['form_title'],
                'payment_amount' => $entry['payment_amount'],
                'date_created' => $entry['date_created'],
            ];
        }

        // Also index by email for fallback matching
        if ($email) {
            $gf_entries['email_' . strtolower($email)] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'full_name' => trim($first_name . ' ' . $last_name),
                'entry_id' => $entry['entry_id'],
                'form_title' => $entry['form_title'],
                'payment_amount' => $entry['payment_amount'],
                'date_created' => $entry['date_created'],
            ];
        }

        // ALSO index by entry ID for direct matching from descriptions
        $gf_entries['entry_' . $entry['entry_id']] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'full_name' => trim($first_name . ' ' . $last_name),
            'entry_id' => $entry['entry_id'],
            'form_title' => $entry['form_title'],
            'payment_amount' => $entry['payment_amount'],
            'date_created' => $entry['date_created'],
        ];
    }
    fclose($handle);
}

echo "Loaded " . count($gf_entries) . " Gravity Forms entries\n";

// Parse Stripe transactions and match
$stripe_matched = [];
$stripe_unmatched = [];

if (($handle = fopen($stripe_file, "r")) !== FALSE) {
    $header = fgetcsv($handle);

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 10) continue;

        $row = array_combine($header, $data);

        $stripe_id = $row['id'];
        $email = isset($row['Customer Email']) ? strtolower(trim($row['Customer Email'])) : '';
        $payer_email = isset($row['payer_email (metadata)']) ? strtolower(trim($row['payer_email (metadata)'])) : '';
        $payer_name = isset($row['payer_name (metadata)']) ? trim($row['payer_name (metadata)']) : '';

        // Strip prefix from Stripe ID for matching
        $txn_key = str_replace(['pi_', 'ch_', 'py_'], '', $stripe_id);

        $match = null;

        // Try to match by transaction ID first
        if (isset($gf_entries[$txn_key])) {
            $match = $gf_entries[$txn_key];
            $match['match_method'] = 'transaction_id';
        }
        // Try to match by customer email
        elseif ($email && isset($gf_entries['email_' . $email])) {
            $match = $gf_entries['email_' . $email];
            $match['match_method'] = 'email';
        }
        // Try to match by payer_email metadata
        elseif ($payer_email && isset($gf_entries['email_' . $payer_email])) {
            $match = $gf_entries['email_' . $payer_email];
            $match['match_method'] = 'payer_email';
        }
        // Try to match by Entry ID in description
        else {
            $description = isset($row['Description']) ? $row['Description'] : '';
            if (preg_match('/Entry ID:\s*(\d+)/', $description, $matches)) {
                $entry_id = $matches[1];
                if (isset($gf_entries['entry_' . $entry_id])) {
                    $match = $gf_entries['entry_' . $entry_id];
                    $match['match_method'] = 'entry_id';
                }
            }
        }

        if ($match) {
            $row['matched_name'] = $match['full_name'];
            $row['matched_first_name'] = $match['first_name'];
            $row['matched_last_name'] = $match['last_name'];
            $row['match_method'] = $match['match_method'];
            $row['gf_entry_id'] = $match['entry_id'];
            $row['gf_form'] = $match['form_title'];
            $stripe_matched[] = $row;
        } else {
            // Use payer_name from metadata if available
            if ($payer_name) {
                $row['matched_name'] = $payer_name;
            }
            $stripe_unmatched[] = $row;
        }
    }
    fclose($handle);
}

echo "Matched " . count($stripe_matched) . " Stripe transactions to Gravity Forms\n";
echo "Unmatched " . count($stripe_unmatched) . " Stripe transactions\n";

// Show sample matches
echo "\n=== SAMPLE MATCHES ===\n";
foreach (array_slice($stripe_matched, 0, 5) as $t) {
    echo "{$t['id']} | {$t['matched_name']} | {$t['Amount']} | {$t['match_method']}\n";
}

echo "\n=== SAMPLE UNMATCHED ===\n";
foreach (array_slice($stripe_unmatched, 0, 5) as $t) {
    $name = isset($t['matched_name']) ? $t['matched_name'] : 'No name';
    echo "{$t['id']} | $name | {$t['Amount']} | Email: {$t['Customer Email']}\n";
}

// Save matched data
$output_file = '/home/swca/scripts/501c3PO/treasurer-docs/stripe_with_names.csv';
$fp = fopen($output_file, 'w');

// Write header
$all_stripe = array_merge($stripe_matched, $stripe_unmatched);
if (!empty($all_stripe)) {
    fputcsv($fp, array_keys($all_stripe[0]));
    foreach ($all_stripe as $row) {
        fputcsv($fp, $row);
    }
}

fclose($fp);

echo "\n✓ Saved Stripe transactions with names to: $output_file\n";
echo "File size: " . number_format(filesize($output_file)) . " bytes\n";
?>
