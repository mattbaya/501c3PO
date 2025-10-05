#!/usr/bin/php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$default_password = 'F1v3C0rn3rs';

// Check if password is already set
$result = $mysqli->query("SELECT option_value FROM swca_options WHERE option_name = 'swca_board_portal_password'");

if ($result->num_rows == 0) {
    // Insert default password
    $mysqli->query("INSERT INTO swca_options (option_name, option_value, autoload) VALUES ('swca_board_portal_password', '$default_password', 'yes')");
    echo "✓ Set default Board Portal password: $default_password\n";
} else {
    $row = $result->fetch_assoc();
    $current = $row['option_value'];

    if (empty($current)) {
        // Update with default password
        $mysqli->query("UPDATE swca_options SET option_value = '$default_password' WHERE option_name = 'swca_board_portal_password'");
        echo "✓ Updated Board Portal password to: $default_password\n";
    } else {
        echo "✓ Board Portal password already set (not changed)\n";
        echo "  Current password: $current\n";
    }
}

echo "\n✓ Password protection is now active!\n";
echo "  - Non-logged-in users will need the password\n";
echo "  - Logged-in WordPress users can bypass the password\n";
echo "  - Password can be changed via Settings page\n";

$mysqli->close();
?>
